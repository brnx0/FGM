<?php

namespace App\Console\Commands;

use App\Enums\ChecagemDocumento;
use App\Enums\ProvedorIa;
use App\Enums\SituacaoConferencia;
use App\Http\Controllers\InscricaoDocumentoController;
use App\Models\ConexaoIa;
use App\Models\ConfiguracaoIa;
use App\Models\Inscricao;
use App\Models\InscricaoDocumento;
use App\Support\DocumentoIa\PromptDaConferencia;
use App\Support\EditalIa\AnaliseComRedundancia;
use App\Support\EditalIa\RespostaInvalida;
use App\Support\FalhaNaIa;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Confere com a IA (o provedor principal e, se ele falhar, a redundância) o PDF ou a imagem de um documento da inscrição. Lançado
 * em segundo plano por LancadorDeConferencia com a execução (InscricaoDocumento::execucao) para a qual foi iniciado.
 *
 * Cada gravação confere, com a linha travada, que o documento ainda está conferindo nesta execução: um processo
 * antigo (o arquivo foi trocado, a conferência pedida de novo) ou repetido não desfaz o resultado de outro, e o
 * documento apagado no meio do caminho não quebra nada. Sem resposta da IA o documento fica não conferido, com a
 * mensagem genérica para o proponente e o detalhe em `erro`, que é sempre texto em português, nunca a chave da API.
 */
#[Signature('inscricoes:conferir {documento : Id do documento enviado na inscrição} {execucao? : Execução a conferir (padrão: a atual do documento)}')]
#[Description('Confere com a IA o PDF ou a imagem de um documento da inscrição')]
class ConferirDocumentoCommand extends Command
{
    /** O que o proponente vê quando a IA não consegue conferir. */
    public const string NAO_CONFERIDO = InscricaoDocumentoController::NAO_CONFERIDO;

    public const string ERRO_INESPERADO = 'Erro inesperado na conferência.';

    private const int TEMPO_LIMITE = 300;

    private const string MEMORIA = '256M';

    private const float TEMPERATURA = 0.1;

    /** O tamanho das colunas `motivo` e `erro`. */
    private const int TAMANHO_MAXIMO_EM_BYTES = 1000;

    private const string SUPERADA = 'O documento não está mais nesta conferência; nada foi gravado.';

    private int|string $id;

    private string $execucao;

    public function handle(AnaliseComRedundancia $analise): int
    {
        set_time_limit(self::TEMPO_LIMITE);
        // O arquivo (até 10 MB), o base64 e o JSON do pedido ficam na memória ao mesmo tempo; o padrão da CLI é 128M.
        $memoria = (string) ini_get('memory_limit');
        if ($memoria !== '-1' && ini_parse_quantity($memoria) < ini_parse_quantity(self::MEMORIA)) {
            ini_set('memory_limit', self::MEMORIA);
        }

        $this->id = $this->argument('documento');
        $execucao = $this->argument('execucao') ?? InscricaoDocumento::query()->whereKey($this->id)->value('execucao');
        if (! is_string($execucao) || $execucao === '') {
            $this->error('Documento não encontrado.');

            return self::FAILURE;
        }

        $this->execucao = $execucao;
        $documento = InscricaoDocumento::query()->whereKey($this->id)->daExecucao($this->execucao)->first();
        if ($documento === null) {
            $this->error(self::SUPERADA);

            return self::FAILURE;
        }

        // A equipe pode ter posto o documento em "Não validar" (ou desligado a validação por IA) depois do lançamento: o
        // arquivo não sai do servidor.
        if ($documento->documento?->checagemNaInscricao(ConfiguracaoIa::validacaoLigada()) === ChecagemDocumento::Nenhuma) {
            return $this->dispensar();
        }

        $provedores = [];

        try {
            $provedores = ConfiguracaoIa::atual()->provedoresDaImportacao();
            if ($provedores === []) {
                throw new FalhaNaIa(AnaliseComRedundancia::SEM_CONFIGURACAO);
            }

            $disco = Storage::disk(Inscricao::DISCO);
            $arquivo = $disco->get($documento->arquivo)
                ?? throw new FalhaNaIa('O arquivo enviado não foi encontrado. Envie o documento de novo.');
            // O tipo vem do conteúdo gravado (PDF ou imagem): é ele que diz ao provedor como ler o arquivo.
            $tipo = $disco->mimeType($documento->arquivo);
            if (! in_array($tipo, Inscricao::TIPOS_DE_ARQUIVO, true)) {
                throw new FalhaNaIa('O arquivo enviado não é um PDF nem uma imagem JPG ou PNG. Envie o documento de novo.');
            }

            $resultado = $analise->analisar(
                $provedores,
                $arquivo,
                PromptDaConferencia::montar($documento),
                self::TEMPERATURA,
                PromptDaConferencia::esquema(),
                PromptDaConferencia::conferir(...),
                $tipo,
            );
            unset($arquivo);

            $aprovado = $resultado['dados']['aprovado'] === true;

            $guardou = $this->naExecucao(fn (InscricaoDocumento $atual) => $atual->update([
                'situacao' => $aprovado ? SituacaoConferencia::Aprovado : SituacaoConferencia::Reprovado,
                'motivo' => $aprovado ? null : $this->cortar(trim($resultado['dados']['motivo'])),
                'erro' => null,
                'provedor' => $resultado['provedor'],
                'modelo' => $resultado['modelo'],
                'conferido_em' => now(),
            ]));

            if (! $guardou) {
                $this->error(self::SUPERADA);

                return self::FAILURE;
            }
        } catch (FalhaNaIa|RespostaInvalida $falha) {
            return $this->falhar($falha->getMessage(), $provedores);
        } catch (Throwable $erro) {
            // Sem corpo de pedido nem de resposta; as chaves, se aparecerem na mensagem, saem.
            $chaves = array_values(array_filter(array_map(fn (array $provedor): string => (string) $provedor['conexao']->chaveGuardada(), $provedores)));
            Log::error('Conferência de documento: erro inesperado.', [
                'documento' => (int) $this->id,
                'excecao' => $erro::class,
                'mensagem' => mb_substr(str_replace($chaves, '[chave]', $erro->getMessage()), 0, 500),
                'local' => $erro->getFile().':'.$erro->getLine(),
            ]);

            return $this->falhar(self::ERRO_INESPERADO, $provedores);
        }

        $this->info($aprovado ? 'Documento aprovado.' : 'Documento reprovado.');

        return self::SUCCESS;
    }

    /**
     * Roda $gravar numa transação com o documento travado, só se ele ainda estiver conferindo nesta execução.
     *
     * @param  callable(InscricaoDocumento): mixed  $gravar
     */
    private function naExecucao(callable $gravar): bool
    {
        return DB::transaction(function () use ($gravar): bool {
            $atual = InscricaoDocumento::query()
                ->whereKey($this->id)
                ->daExecucao($this->execucao)
                ->lockForUpdate()
                ->first();

            if ($atual === null) {
                return false;
            }

            $gravar($atual);

            return true;
        });
    }

    /**
     * Deixa o documento não conferido, se ele ainda estiver nesta execução (senão, outra conferência já cuidou dele).
     * O provedor e o modelo gravados são os do principal, o primeiro a ser chamado.
     *
     * @param  list<array{provedor: ProvedorIa, conexao: ConexaoIa}>  $provedores
     */
    private function falhar(string $mensagem, array $provedores): int
    {
        $principal = $provedores[0] ?? null;

        $this->naExecucao(fn (InscricaoDocumento $atual) => $atual->update([
            'situacao' => SituacaoConferencia::NaoConferido,
            'motivo' => self::NAO_CONFERIDO,
            'erro' => $this->cortar($mensagem),
            'provedor' => $principal['provedor'] ?? null,
            'modelo' => $principal === null ? null : (string) $principal['conexao']->modelo,
            'conferido_em' => now(),
        ]));

        $this->error($mensagem);

        return self::FAILURE;
    }

    /** Documento em "Não validar": volta a ser só enviado, sem passar pela IA (se ainda estiver nesta execução). */
    private function dispensar(): int
    {
        $guardou = $this->naExecucao(fn (InscricaoDocumento $atual) => $atual->update([
            'situacao' => SituacaoConferencia::Enviado,
            'motivo' => null,
            'erro' => null,
            'provedor' => null,
            'modelo' => null,
            'execucao' => null,
            'iniciada_em' => null,
            'conferido_em' => null,
        ]));

        if (! $guardou) {
            $this->error(self::SUPERADA);

            return self::FAILURE;
        }

        $this->info('Documento em "Não validar": a IA não foi chamada.');

        return self::SUCCESS;
    }

    private function cortar(string $texto): string
    {
        return mb_strcut($texto, 0, self::TAMANHO_MAXIMO_EM_BYTES, 'UTF-8');
    }
}
