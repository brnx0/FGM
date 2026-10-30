<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\SalvarAreaAtuacaoRequest;
use App\Models\AreaAtuacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Parametrização: áreas de atuação cultural. Sem exclusão: a desativada sai das opções, mas continua valendo onde já
 * foi escolhida.
 */
class AreaAtuacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));

        $areas = AreaAtuacao::query()
            ->select(['id', 'nome', 'ativo'])
            // No Oracle a comparação de texto diferencia maiúsculas; % e _ digitados valem literalmente.
            ->when($busca !== '', fn (Builder $q) => $q->whereRaw(
                "LOWER(nome) LIKE ? ESCAPE '\'",
                ['%'.addcslashes(mb_strtolower($busca), '\%_').'%'],
            ))
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (AreaAtuacao $area): array => [
                'id' => (int) $area->id,
                'nome' => $area->nome,
                'ativo' => $area->ativo,
            ]);

        return Inertia::render('Retaguarda/AreasAtuacao/Index', [
            'areas' => $areas,
            'filtros' => ['busca' => $busca],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Retaguarda/AreasAtuacao/Form', ['area' => null]);
    }

    public function store(SalvarAreaAtuacaoRequest $request): RedirectResponse
    {
        AreaAtuacao::create(['nome' => $request->validated('nome')]);

        return redirect()->route('retaguarda.areas-atuacao.index')->with('sucesso', 'Área de atuação salva.');
    }

    public function edit(AreaAtuacao $area): Response
    {
        return Inertia::render('Retaguarda/AreasAtuacao/Form', [
            'area' => [
                'id' => (int) $area->id,
                'nome' => $area->nome,
                'ativo' => $area->ativo,
            ],
        ]);
    }

    public function update(SalvarAreaAtuacaoRequest $request, AreaAtuacao $area): RedirectResponse
    {
        $area->update(['nome' => $request->validated('nome')]);

        return redirect()->route('retaguarda.areas-atuacao.index')->with('sucesso', 'Área de atuação salva.');
    }

    public function ativo(AreaAtuacao $area): RedirectResponse
    {
        $area->update(['ativo' => ! $area->ativo]);

        return back()->with('sucesso', $area->ativo ? 'Área de atuação ativada.' : 'Área de atuação desativada.');
    }
}
