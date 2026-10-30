<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoInscricao;
use App\Http\Requests\SalvarRespostasRequest;
use App\Models\EditalQuestionarioSecao;
use App\Models\Inscricao;
use App\Support\Inscricao\PassosDaInscricao;
use App\Support\Inscricao\QuestionarioDaInscricao;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Respostas do questionário na inscrição, uma seção por vez, só do dono (InscricaoPolicy), com a inscrição em
 * rascunho e no período. O que veio fica salvo mesmo com a seção incompleta.
 */
class InscricaoRespostaController extends Controller
{
    public function update(SalvarRespostasRequest $request, Inscricao $inscricao, EditalQuestionarioSecao $secao): RedirectResponse
    {
        abort_unless((int) $secao->edital_id === (int) $inscricao->edital_id, 404);

        if (! $inscricao->noPeriodo()) {
            return $this->inscricoesFechadas();
        }

        // Saída antes de travar; a situação que vale é a relida com a linha travada, dentro da transação.
        if ($inscricao->situacao !== SituacaoInscricao::Rascunho) {
            return $this->pedirParaReabrir($inscricao);
        }

        $questionario = DB::transaction(function () use ($inscricao, $request): QuestionarioDaInscricao {
            $travada = Inscricao::query()->whereKey($inscricao->id)->lockForUpdate()->firstOrFail();
            $travada->setRelation('edital', $inscricao->edital);

            if ($travada->situacao !== SituacaoInscricao::Rascunho) {
                throw new HttpResponseException($this->pedirParaReabrir($inscricao));
            }

            if (! $travada->noPeriodo()) {
                throw new HttpResponseException($this->inscricoesFechadas());
            }

            // As perguntas que a request validou; se a categoria mudou no meio, o descarte abaixo limpa o que não vale.
            foreach ($request->perguntas() as $pergunta) {
                $valor = QuestionarioDaInscricao::normalizar($pergunta, $request->validated('respostas.'.$pergunta->id));

                if ($valor === null) {
                    $travada->respostas()->where('edital_pergunta_id', $pergunta->id)->delete();
                } else {
                    $travada->respostas()->updateOrCreate(
                        ['edital_pergunta_id' => $pergunta->id],
                        [...$valor, 'opcoes' => $valor['opcoes'] === [] ? null : $valor['opcoes']],
                    );
                }
            }

            $questionario = new QuestionarioDaInscricao($travada);
            $questionario->descartarRespostasQueNaoValem();

            return $questionario;
        });

        $faltando = $questionario->faltando($questionario->respostasSalvas(), (int) $secao->id);

        if ($faltando !== []) {
            throw ValidationException::withMessages($faltando);
        }

        $passos = PassosDaInscricao::para($inscricao, $questionario);

        return redirect()->route('portal.inscricoes.show', [
            $inscricao,
            'passo' => PassosDaInscricao::seguinte($passos, PassosDaInscricao::idDaSecao((int) $secao->id)),
        ]);
    }

    private function inscricoesFechadas(): RedirectResponse
    {
        return redirect()->route('portal.editais')->with('aviso', PortalController::AVISO_INSCRICOES_FECHADAS);
    }

    private function pedirParaReabrir(Inscricao $inscricao): RedirectResponse
    {
        return redirect()->route('portal.inscricoes.show', $inscricao)->with('erro', InscricaoController::ERRO_REABRIR);
    }
}
