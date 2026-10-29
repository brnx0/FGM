<?php

namespace App\Http\Controllers;

use App\Enums\ModalidadeCota;
use App\Enums\SituacaoConferencia;
use App\Enums\SituacaoInscricao;
use App\Http\Requests\SalvarInscricaoRequest;
use App\Models\Edital;
use App\Models\EditalDocumento;
use App\Models\Inscricao;
use App\Models\InscricaoDocumento;
use App\Support\ArquivoArmazenado;
use App\Support\Inscricao\PropsDaInscricao;
use App\Support\Inscricao\RegrasDaInscricao;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Inscrição do proponente num edital: criar o rascunho, abrir e trocar as escolhas (cadastro, categoria e cotas). */
class InscricaoController extends Controller
{
    public const string ERRO_LIMITE_ATINGIDO = 'Este cadastro já atingiu o limite de inscrições deste edital.';

    public const string ERRO_REABRIR = 'Reabra a inscrição para alterar as escolhas.';

    public function store(SalvarInscricaoRequest $request, Edital $edital): RedirectResponse
    {
        $inscricao = new Inscricao([
            'edital_id' => $edital->id,
            'user_id' => $request->user()->id,
            'situacao' => SituacaoInscricao::Rascunho,
        ]);
        $inscricao->setRelation('edital', $edital);
        $this->preencher($inscricao, $request);

        if (! $inscricao->noPeriodo()) {
            return $this->inscricoesFechadas();
        }

        DB::transaction(function () use ($inscricao, $edital): void {
            $this->travarEdital($edital);
            $this->recusarSeLimiteAtingido($inscricao);
            $inscricao->save();
        });

        return redirect()->route('portal.inscricoes.show', $inscricao);
    }

    public function show(Request $request, Inscricao $inscricao): Response
    {
        Gate::authorize('gerenciar', $inscricao);

        $user = $request->user();
        $edital = $inscricao->edital;
        $inscricao->load(['organizacao', 'categoria', 'chamada']);

        return Inertia::render('Portal/InscricaoDocumentos', [
            'edital' => PropsDaInscricao::edital($edital),
            'inscricao' => [
                'id' => (int) $inscricao->id,
                'numero' => $inscricao->numero,
                'situacao' => $inscricao->situacao->value,
                'situacaoRotulo' => $inscricao->situacao->rotulo(),
                'cadastro' => PropsDaInscricao::nomeDoCadastro($inscricao, $user),
                'cadastroChave' => PropsDaInscricao::chaveDoCadastro($inscricao->organizacao_id === null ? null : (int) $inscricao->organizacao_id),
                'categoriaId' => $inscricao->edital_categoria_id === null ? null : (int) $inscricao->edital_categoria_id,
                'categoria' => $inscricao->categoria?->nome,
                'cotas' => $inscricao->cotas?->map(fn (ModalidadeCota $cota): string => $cota->value)->values()->all() ?? [],
                'fimDoPeriodo' => $inscricao->chamada?->fim?->format('d/m/Y'),
            ],
            'podeEditar' => $inscricao->situacao === SituacaoInscricao::Rascunho && $inscricao->noPeriodo(),
            'documentos' => $inscricao->documentosExigidos()
                ->map(fn (EditalDocumento $documento): array => [
                    'id' => (int) $documento->id,
                    'nome' => $documento->nome,
                    'orientacao' => $documento->orientacao,
                    'etapa' => $documento->etapa->value,
                    'etapaRotulo' => $documento->etapa->rotulo(),
                    'obrigatorio' => $documento->obrigatorio,
                    'modeloUrl' => filled($documento->modelo) ? route('editais.documentos.modelo', [$edital, $documento]) : null,
                    'modeloNome' => $documento->modelo_nome,
                ])
                ->all(),
            ...PropsDaInscricao::opcoes($user, $edital),
        ]);
    }

    /**
     * Situação da conferência de cada documento enviado, para a tela consultar enquanto algum está conferindo. Sem o
     * detalhe técnico da falha, que fica só no banco; conferindo parado aparece como não conferido.
     */
    public function situacao(Inscricao $inscricao): JsonResponse
    {
        Gate::authorize('gerenciar', $inscricao);

        return response()->json([
            'documentos' => $inscricao->documentos()->orderBy('id')->get()
                ->map(function (InscricaoDocumento $documento): array {
                    $situacao = $documento->situacaoExibida();

                    return [
                        'id' => (int) $documento->edital_documento_id,
                        'situacao' => $situacao->value,
                        'motivo' => $documento->motivo ?? ($situacao === SituacaoConferencia::NaoConferido ? InscricaoDocumentoController::NAO_CONFERIDO : null),
                    ];
                })
                ->all(),
        ]);
    }

    public function update(SalvarInscricaoRequest $request, Inscricao $inscricao): RedirectResponse
    {
        if (! $inscricao->noPeriodo()) {
            return $this->inscricoesFechadas();
        }

        // Saída antes de travar; a situação que vale é a relida com a linha travada, dentro da transação.
        if ($inscricao->situacao !== SituacaoInscricao::Rascunho) {
            return $this->pedirParaReabrir($inscricao);
        }

        $arquivosDescartados = DB::transaction(function () use ($inscricao, $request): array {
            $this->travarEdital($inscricao->edital);
            $travada = $this->travarInscricao($inscricao);

            if ($travada->situacao !== SituacaoInscricao::Rascunho) {
                throw new HttpResponseException($this->pedirParaReabrir($inscricao));
            }

            $this->preencher($travada, $request);

            // A nova categoria pode levar a inscrição para uma chamada que já fechou.
            if (! $travada->noPeriodo()) {
                throw new HttpResponseException($this->inscricoesFechadas());
            }

            if ($travada->isDirty(['documento_cadastro', 'edital_chamada_id'])) {
                $this->recusarSeLimiteAtingido($travada);
            }
            $travada->save();

            return $this->descartarDocumentosForaDaLista($travada);
        });

        ArquivoArmazenado::apagarTodos($arquivosDescartados);

        return redirect()->route('portal.inscricoes.show', $inscricao)->with('sucesso', 'Escolhas salvas.');
    }

    /** Põe na inscrição o que foi escolhido: cadastro, categoria, cotas e a chamada que a categoria define. */
    private function preencher(Inscricao $inscricao, SalvarInscricaoRequest $request): void
    {
        $cadastro = $request->cadastroEscolhido();
        $categoria = $request->categoriaEscolhida();
        $chamada = RegrasDaInscricao::chamada($inscricao->edital, $categoria);

        $inscricao->fill([
            'edital_chamada_id' => $chamada?->id,
            'organizacao_id' => $cadastro['id'],
            'documento_cadastro' => $cadastro['documento'],
            'natureza' => $cadastro['natureza'],
            'edital_categoria_id' => $categoria?->id,
            'cotas' => $request->cotasEscolhidas(),
        ]);
        $inscricao->setRelation('chamada', $chamada);
    }

    private function inscricoesFechadas(): RedirectResponse
    {
        return redirect()->route('portal.editais')->with('aviso', PortalController::AVISO_INSCRICOES_FECHADAS);
    }

    private function pedirParaReabrir(Inscricao $inscricao): RedirectResponse
    {
        return redirect()->route('portal.inscricoes.show', $inscricao)->with('erro', self::ERRO_REABRIR);
    }

    /**
     * Duas gravações ao mesmo tempo no edital esperam uma pela outra, para a segunda ver a primeira no limite.
     * Ordem das travas, sempre: o edital e depois a inscrição (quem finaliza ou anexa documento segue a mesma).
     */
    private function travarEdital(Edital $edital): void
    {
        Edital::query()->whereKey($edital->id)->lockForUpdate()->value('id');
    }

    /**
     * Relê a inscrição travando a linha: quem finaliza, reabre ou anexa documento ao mesmo tempo espera aqui, e
     * a situação e as escolhas que valem são as da linha travada, não as da cópia lida antes da transação.
     */
    private function travarInscricao(Inscricao $inscricao): Inscricao
    {
        $travada = Inscricao::query()->whereKey($inscricao->id)->lockForUpdate()->firstOrFail();
        $travada->setRelation('edital', $inscricao->edital);

        return $travada;
    }

    private function recusarSeLimiteAtingido(Inscricao $inscricao): void
    {
        $ignorar = $inscricao->exists ? (int) $inscricao->id : null;

        if (RegrasDaInscricao::limiteAtingido($inscricao->edital, $inscricao->chamada, $inscricao->documento_cadastro, $ignorar)) {
            throw ValidationException::withMessages(['cadastro' => self::ERRO_LIMITE_ATINGIDO]);
        }
    }

    /**
     * Apaga os documentos enviados que a inscrição deixou de exigir. Devolve os arquivos deles para apagar do
     * disco depois do commit; um processo de conferência em andamento não os regrava (a linha não existe mais).
     *
     * @return list<array{string, ?string}>
     */
    private function descartarDocumentosForaDaLista(Inscricao $inscricao): array
    {
        $exigidos = $inscricao->documentosExigidos()->map(fn (EditalDocumento $documento): int => (int) $documento->id)->all();

        $descartados = $inscricao->documentos()->get()
            ->reject(fn (InscricaoDocumento $documento): bool => in_array((int) $documento->edital_documento_id, $exigidos, true));

        if ($descartados->isEmpty()) {
            return [];
        }

        $inscricao->documentos()->whereKey($descartados->modelKeys())->delete();

        return $descartados->map(fn (InscricaoDocumento $documento): array => [Inscricao::DISCO, $documento->arquivo])->values()->all();
    }
}
