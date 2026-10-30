<?php

namespace App\Console\Commands;

use App\Enums\ProvedorIa;
use App\Enums\SituacaoImportacao;
use App\Models\ConfiguracaoIa;
use App\Models\Edital;
use App\Models\ImportacaoEdital;
use App\Support\ArquivoArmazenado;
use App\Support\EditalIa\AnaliseComRedundancia;
use App\Support\EditalIa\EditalDaResposta;
use App\Support\EditalIa\EsquemaDaResposta;
use App\Support\EditalIa\PromptModelo;
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
 * Analisa o PDF de uma importação com a IA (o provedor principal e, se ele falhar, a redundância) e cria o edital (não
 * publicado). Lançado em segundo plano por LancadorDeAnalise com a execução (ImportacaoEdital::execucao) para a qual
 * foi iniciado.
 *
 * Cada gravação confere, com a linha travada, que a importação ainda está nesta execução, analisando e sem edital:
 * um processo antigo (nova tentativa depois de 15 minutos) ou repetido não cria um segundo edital nem desfaz o
 * resultado de outro. O erro gravado é sempre uma mensagem em português, nunca a chave da API.
 */
#[Signature('editais:analisar {importacao : Id da importação} {execucao? : Execução a analisar (padrão: a atual da importação)}')]
#[Description('Analisa o PDF de uma importação de edital com a IA e cria o edital não publicado')]
class AnalisarEditalCommand extends Command
{
    public const string ERRO_INESPERADO = 'Erro inesperado na análise.';

    private const int TEMPO_LIMITE = 600;

    private const string MEMORIA = '512M';

    private const string SUPERADA = 'A importação não está mais nesta análise; nada foi gravado.';

    private int|string $id;

    private string $execucao;

    public function handle(AnaliseComRedundancia $analise, EditalDaResposta $criador): int
    {
        set_time_limit(self::TEMPO_LIMITE);
        // O PDF (até 14 MB), o base64 e o JSON do pedido ficam na memória ao mesmo tempo; o padrão da CLI é 128M.
        $memoria = (string) ini_get('memory_limit');
        if ($memoria !== '-1' && ini_parse_quantity($memoria) < ini_parse_quantity(self::MEMORIA)) {
            ini_set('memory_limit', self::MEMORIA);
        }

        $importacao = ImportacaoEdital::query()->find($this->argument('importacao'));
        $execucao = $this->argument('execucao') ?? $importacao?->execucao;
        if ($importacao === null || ! is_string($execucao) || $execucao === '') {
            $this->error('Importação não encontrada.');

            return self::FAILURE;
        }

        $this->id = $importacao->getKey();
        $this->execucao = $execucao;

        if (! $this->naExecucao(fn (ImportacaoEdital $atual) => $atual->update(['iniciada_em' => now(), 'erro' => null]))) {
            $this->error(self::SUPERADA);

            return self::FAILURE;
        }

        $provedores = [];

        try {
            $config = ConfiguracaoIa::atual();
            $provedores = $config->provedoresDaImportacao();
            if ($provedores === []) {
                throw new FalhaNaIa(AnaliseComRedundancia::SEM_CONFIGURACAO);
            }

            $pdf = Storage::disk(ImportacaoEdital::DISCO)->get($importacao->pdf)
                ?? throw new FalhaNaIa('O PDF enviado não foi encontrado. Descarte esta importação e envie o edital de novo.');

            $resultado = $analise->analisar(
                $provedores,
                $pdf,
                PromptModelo::fixo()."\n\n".$config->promptDaImportacao(),
                $config->temperaturaDaImportacao(),
                EsquemaDaResposta::esquema(),
                EditalDaResposta::conferirRaiz(...),
            );
            unset($pdf);

            $guardou = $this->naExecucao(fn (ImportacaoEdital $atual) => $atual->update([
                'provedor' => $resultado['provedor'],
                'modelo' => $resultado['modelo'],
                'tokens_entrada' => $resultado['tokens']['entrada'],
                'tokens_saida' => $resultado['tokens']['saida'],
                'tokens_total' => $resultado['tokens']['total'],
                'resposta' => $resultado['dados'],
            ]));

            if (! $guardou || ! $this->criarEdital($resultado['dados'], $criador, $this->avisoDaRedundancia($resultado))) {
                $this->error(self::SUPERADA);

                return self::FAILURE;
            }
        } catch (FalhaNaIa $falha) {
            return $this->falhar($falha->getMessage());
        } catch (RespostaInvalida $invalida) {
            return $this->falhar($invalida->getMessage());
        } catch (Throwable $erro) {
            // Sem corpo de pedido nem de resposta; as chaves, se aparecerem na mensagem, saem.
            $chaves = array_values(array_filter(array_map(fn (array $provedor): string => (string) $provedor['conexao']->chaveGuardada(), $provedores)));
            Log::error('Importação de edital: erro inesperado na análise.', [
                'importacao' => (int) $this->id,
                'excecao' => $erro::class,
                'mensagem' => mb_substr(str_replace($chaves, '[chave]', $erro->getMessage()), 0, 500),
                'local' => $erro->getFile().':'.$erro->getLine(),
            ]);

            return $this->falhar(self::ERRO_INESPERADO);
        }

        $this->info('Edital criado.');

        return self::SUCCESS;
    }

    /**
     * Roda $gravar numa transação com a importação travada, só se ela ainda estiver nesta execução.
     *
     * @param  callable(ImportacaoEdital): mixed  $gravar
     */
    private function naExecucao(callable $gravar): bool
    {
        return DB::transaction(function () use ($gravar): bool {
            $atual = ImportacaoEdital::query()
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
     * Cria o edital e conclui a importação na mesma transação, com a linha travada: se algo falhar ou a execução
     * tiver mudado, nem edital nem conclusão ficam. A cópia do PDF é apagada se a transação não se confirmar.
     *
     * @param  array<mixed>  $dados
     * @param  list<array{bloco: string, tipo: string, texto: string, itens: list<string>}>  $avisos  Pontos de atenção a acrescentar.
     */
    private function criarEdital(array $dados, EditalDaResposta $criador, array $avisos): bool
    {
        $edital = null;

        try {
            return $this->naExecucao(function (ImportacaoEdital $atual) use ($dados, $criador, $avisos, &$edital): void {
                $edital = $criador->criar($dados, $atual);

                $atual->fill([
                    'atencao' => [...$avisos, ...$atual->atencao],
                    'edital_id' => $edital->getKey(),
                    'situacao' => SituacaoImportacao::Concluida,
                ])->save();
            });
        } catch (Throwable $erro) {
            if ($edital instanceof Edital) {
                ArquivoArmazenado::apagar(ImportacaoEdital::DISCO, $edital->pdf);
            }

            throw $erro;
        }
    }

    /**
     * Ponto de atenção quando quem respondeu foi a redundância, com o motivo da falha do principal.
     *
     * @param  array{provedor: ProvedorIa, falhas: list<array{provedor: ProvedorIa, motivo: string}>}  $resultado
     * @return list<array{bloco: string, tipo: string, texto: string, itens: list<string>}>
     */
    private function avisoDaRedundancia(array $resultado): array
    {
        if ($resultado['falhas'] === []) {
            return [];
        }

        $motivo = rtrim($resultado['falhas'][0]['motivo'], '.');

        return [[
            'bloco' => 'identificacao',
            'tipo' => EditalDaResposta::TIPO_INCERTA,
            'texto' => "Analisado pelo provedor de redundância ({$resultado['provedor']->rotulo()}) porque o principal falhou: {$motivo}.",
            'itens' => [],
        ]];
    }

    /** Grava o erro, se a importação ainda estiver nesta execução (senão, outra análise já cuidou dela). */
    private function falhar(string $mensagem): int
    {
        $this->naExecucao(fn (ImportacaoEdital $atual) => $atual->update([
            'situacao' => SituacaoImportacao::Erro,
            'erro' => mb_strcut($mensagem, 0, 1000, 'UTF-8'),
            'confiabilidade' => null,
            'atencao' => null,
        ]));

        $this->error($mensagem);

        return self::FAILURE;
    }
}
