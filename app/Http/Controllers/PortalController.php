<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoEdital;
use App\Models\Edital;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Telas do menu do portal depois do cadastro finalizado: Editais (proponente) e Projetos (patrocinador). */
class PortalController extends Controller
{
    /** Os editais cadastrados na retaguarda, só os publicados com PDF (os mesmos que o site mostra). */
    public function editais(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));
        $situacao = SituacaoEdital::tryFrom((string) $request->query('situacao', ''));

        $editais = Edital::visiveis()
            // No Oracle a comparação de texto diferencia maiúsculas; % e _ digitados valem literalmente.
            ->when($busca !== '', fn (Builder $q) => $q->whereRaw(
                "LOWER(titulo) LIKE ? ESCAPE '\\'",
                ['%'.addcslashes(mb_strtolower($busca), '\\%_').'%'],
            ))
            ->when($situacao, fn (Builder $q) => $q->naSituacao($situacao))
            ->orderByDesc('inscricoes_inicio')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Edital $edital): array {
                $situacao = $edital->situacao();

                return [
                    'slug' => $edital->slug,
                    'titulo' => $edital->titulo,
                    'categoria' => $edital->categoria,
                    'periodo' => $edital->inscricoes_inicio->format('d/m/Y').' a '.$edital->inscricoes_fim->format('d/m/Y'),
                    'situacao' => $situacao->value,
                    'situacaoRotulo' => $situacao->rotulo(),
                    'pdf' => $edital->pdfUrl(),
                ];
            });

        return Inertia::render('Portal/Editais', [
            'editais' => $editais,
            'filtros' => [
                'busca' => $busca,
                'situacao' => $situacao?->value ?? '',
            ],
            'situacoes' => array_map(
                fn (SituacaoEdital $s): array => ['valor' => $s->value, 'rotulo' => $s->rotulo()],
                SituacaoEdital::cases(),
            ),
        ]);
    }

    /** Ainda não há envio de projetos pelos proponentes: a tela mostra só o estado vazio. */
    public function projetos(): Response
    {
        return Inertia::render('Portal/Projetos');
    }
}
