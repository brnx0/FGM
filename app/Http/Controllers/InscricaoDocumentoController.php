<?php

namespace App\Http\Controllers;

use App\Enums\ChecagemDocumento;
use App\Enums\SituacaoConferencia;
use App\Enums\SituacaoInscricao;
use App\Models\EditalDocumento;
use App\Models\Inscricao;
use App\Models\InscricaoDocumento;
use App\Support\ArquivoArmazenado;
use App\Support\DocumentoIa\LancadorDeConferencia;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Documentos da inscrição: anexar (ou trocar), remover, baixar o arquivo e pedir a conferência de novo. Tudo só do
 * dono (InscricaoPolicy); anexar, remover e conferir de novo só com a inscrição em rascunho e no período. A
 * conferência por IA roda em segundo plano (ConferirDocumentoCommand).
 */
class InscricaoDocumentoController extends Controller
{
    public const string ERRO_ARQUIVO = 'Envie um PDF de até 10 MB.';

    public const string ERRO_OBRIGATORIO = 'Este documento é obrigatório: envie outro arquivo para trocá-lo.';

    public const string ERRO_AO_INICIAR = 'O documento foi enviado, mas não foi possível iniciar a conferência. Tente conferir de novo em instantes.';

    /** Motivo mostrado ao proponente quando a IA não consegue conferir; o detalhe fica só em `erro`. */
    public const string NAO_CONFERIDO = 'Não foi possível conferir o documento agora.';

    public const string AVISO_CONFERIR_DE_NOVO = 'Só é possível conferir de novo um documento reprovado ou não conferido.';

    private const string ERRO_DO_PROCESSO = 'Não foi possível iniciar o processo da conferência.';

    public function store(Request $request, Inscricao $inscricao, EditalDocumento $documento, LancadorDeConferencia $lancador): RedirectResponse
    {
        Gate::authorize('gerenciar', $inscricao);
        $this->exigirDocumento($inscricao, $documento);

        if (($recusa = $this->recusar($inscricao)) !== null) {
            return $recusa;
        }

        $arquivo = $request->validate([
            'arquivo' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:10240'],
        ], array_fill_keys(
            ['arquivo.required', 'arquivo.file', 'arquivo.mimes', 'arquivo.mimetypes', 'arquivo.max', 'arquivo.uploaded'],
            self::ERRO_ARQUIVO,
        ))['arquivo'];

        $caminho = ArquivoArmazenado::guardar($arquivo, Inscricao::DISCO, Inscricao::PASTA.'/'.$inscricao->id);
        $nome = mb_strcut($arquivo->getClientOriginalName(), 0, 255, 'UTF-8');
        $substituido = null;

        try {
            $enviado = DB::transaction(function () use ($inscricao, $documento, $caminho, $nome, &$substituido): InscricaoDocumento {
                $travada = $this->travarInscricao($inscricao);
                $this->exigirEditavel($travada);
                $this->exigirDocumento($travada, $documento);

                $enviado = $travada->documentos()->firstOrNew(['edital_documento_id' => $documento->id]);
                $substituido = $enviado->arquivo;
                $enviado->fill($this->atributosDoEnvio($documento, $caminho, $nome))->save();

                return $enviado;
            });
        } catch (Throwable $erro) {
            ArquivoArmazenado::apagar(Inscricao::DISCO, $caminho);

            throw $erro;
        }

        ArquivoArmazenado::apagar(Inscricao::DISCO, $substituido);

        if ($enviado->situacao === SituacaoConferencia::Conferindo && ! $this->lancar($enviado, $lancador)) {
            return redirect()->route('portal.inscricoes.show', $inscricao)->with('erro', self::ERRO_AO_INICIAR);
        }

        return redirect()->route('portal.inscricoes.show', $inscricao)->with('sucesso', 'Documento enviado.');
    }

    public function destroy(Inscricao $inscricao, EditalDocumento $documento): RedirectResponse
    {
        Gate::authorize('gerenciar', $inscricao);
        $this->exigirDocumento($inscricao, $documento);

        if (($recusa = $this->recusar($inscricao)) !== null) {
            return $recusa;
        }

        if ($documento->obrigatorio) {
            return redirect()->route('portal.inscricoes.show', $inscricao)->with('erro', self::ERRO_OBRIGATORIO);
        }

        $arquivo = DB::transaction(function () use ($inscricao, $documento): ?string {
            $travada = $this->travarInscricao($inscricao);
            $this->exigirEditavel($travada);

            $removido = $travada->documentos()->where('edital_documento_id', $documento->id)->first();
            $removido?->delete();

            return $removido?->arquivo;
        });

        ArquivoArmazenado::apagar(Inscricao::DISCO, $arquivo);

        return redirect()->route('portal.inscricoes.show', $inscricao)->with('sucesso', 'Documento removido.');
    }

    /** "Conferir de novo": só o documento reprovado ou não conferido (inclusive o conferindo parado) ganha uma execução nova. */
    public function conferir(Inscricao $inscricao, EditalDocumento $documento, LancadorDeConferencia $lancador): RedirectResponse
    {
        Gate::authorize('gerenciar', $inscricao);
        $this->exigirDocumento($inscricao, $documento);

        if (($recusa = $this->recusar($inscricao)) !== null) {
            return $recusa;
        }

        $reiniciado = DB::transaction(function () use ($inscricao, $documento): ?InscricaoDocumento {
            $travada = $this->travarInscricao($inscricao);
            $this->exigirEditavel($travada);

            $enviado = $travada->documentos()->where('edital_documento_id', $documento->id)->lockForUpdate()->first();

            if (! in_array($enviado?->situacaoExibida(), [SituacaoConferencia::Reprovado, SituacaoConferencia::NaoConferido], true)) {
                return null;
            }

            $enviado->fill($this->atributosDaConferencia())->save();

            return $enviado;
        });

        if ($reiniciado === null) {
            return redirect()->route('portal.inscricoes.show', $inscricao)->with('aviso', self::AVISO_CONFERIR_DE_NOVO);
        }

        if (! $this->lancar($reiniciado, $lancador)) {
            return redirect()->route('portal.inscricoes.show', $inscricao)->with('erro', self::ERRO_AO_INICIAR);
        }

        return redirect()->route('portal.inscricoes.show', $inscricao)->with('sucesso', 'Conferência iniciada.');
    }

    public function arquivo(Inscricao $inscricao, EditalDocumento $documento): StreamedResponse
    {
        Gate::authorize('gerenciar', $inscricao);

        $enviado = $inscricao->documentos()->where('edital_documento_id', $documento->id)->firstOrFail();
        $disco = Storage::disk(Inscricao::DISCO);

        abort_unless($disco->exists($enviado->arquivo), 404);

        return $disco->download($enviado->arquivo, $enviado->arquivo_nome);
    }

    /**
     * O arquivo novo e o recomeço da conferência.
     *
     * @return array<string, mixed>
     */
    private function atributosDoEnvio(EditalDocumento $documento, string $caminho, string $nome): array
    {
        return [
            'arquivo' => $caminho,
            'arquivo_nome' => $nome,
            ...$this->atributosDaConferencia($documento->checagem !== ChecagemDocumento::Nenhuma),
        ];
    }

    /**
     * O recomeço da conferência: sem checagem o documento fica só enviado, senão conferindo, com uma execução nova (a
     * anterior, se ainda rodar, não grava mais) e sem nada do resultado anterior.
     *
     * @return array<string, mixed>
     */
    private function atributosDaConferencia(bool $confere = true): array
    {
        return [
            'situacao' => $confere ? SituacaoConferencia::Conferindo : SituacaoConferencia::Enviado,
            'motivo' => null,
            'erro' => null,
            'provedor' => null,
            'modelo' => null,
            'execucao' => $confere ? (string) Str::uuid() : null,
            'iniciada_em' => $confere ? now() : null,
            'conferido_em' => null,
        ];
    }

    /** Inicia a conferência; se o processo nem começar, o documento já fica não conferido (com "Conferir de novo"). */
    private function lancar(InscricaoDocumento $documento, LancadorDeConferencia $lancador): bool
    {
        try {
            $lancador->lancar($documento);
        } catch (Throwable $erro) {
            Log::error('Conferência de documento: não foi possível iniciar o processo.', [
                'documento' => (int) $documento->getKey(),
                'excecao' => $erro::class,
            ]);

            InscricaoDocumento::daExecucao((string) $documento->execucao)->whereKey($documento->getKey())->update([
                'situacao' => SituacaoConferencia::NaoConferido->value,
                'motivo' => self::NAO_CONFERIDO,
                'erro' => self::ERRO_DO_PROCESSO,
                'conferido_em' => now(),
            ]);

            return false;
        }

        return true;
    }

    /** O documento precisa ser um dos que a inscrição exige (com as escolhas dela), senão a página não existe. */
    private function exigirDocumento(Inscricao $inscricao, EditalDocumento $documento): void
    {
        abort_unless($inscricao->documentosExigidos()->contains('id', $documento->id), 404);
    }

    /** Para onde mandar quem tenta mexer nos documentos fora do período ou com a inscrição finalizada. */
    private function recusar(Inscricao $inscricao): ?RedirectResponse
    {
        if (! $inscricao->noPeriodo()) {
            return redirect()->route('portal.editais')->with('aviso', PortalController::AVISO_INSCRICOES_FECHADAS);
        }

        if ($inscricao->situacao !== SituacaoInscricao::Rascunho) {
            return redirect()->route('portal.inscricoes.show', $inscricao)->with('erro', InscricaoController::ERRO_REABRIR);
        }

        return null;
    }

    /** Vale a inscrição lida com a linha travada: finalizar ou trocar as escolhas ao mesmo tempo não passa por aqui. */
    private function exigirEditavel(Inscricao $travada): void
    {
        if (($recusa = $this->recusar($travada)) !== null) {
            throw new HttpResponseException($recusa);
        }
    }

    /**
     * Relê a inscrição travando a linha: quem finaliza, reabre ou troca as escolhas ao mesmo tempo espera aqui.
     * Sem travar o edital (só o limite de inscrições precisa dele); a ordem das travas continua a mesma.
     */
    private function travarInscricao(Inscricao $inscricao): Inscricao
    {
        $travada = Inscricao::query()->whereKey($inscricao->id)->lockForUpdate()->firstOrFail();
        $travada->setRelation('edital', $inscricao->edital);

        return $travada;
    }
}
