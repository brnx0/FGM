<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuardarVinculoOrganizacaoRequest;
use App\Models\Organizacao;
use App\Models\User;
use App\Models\VinculoOrganizacao;
use App\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VinculoOrganizacaoController extends Controller
{
    /** Pessoas vinculadas; quem tem vínculo vê, só o responsável altera. */
    public function index(Request $request, Organizacao $organizacao): Response
    {
        Gate::authorize('update', $organizacao);

        $user = $request->user();
        $vinculos = $organizacao->vinculos()->get();
        $nomesDosUsuarios = User::query()->whereIn('cpf', $vinculos->pluck('cpf'))->pluck('name', 'cpf');

        return Inertia::render('Organizacoes/Vinculos', [
            'organizacao' => [
                'id' => $organizacao->id,
                'nome' => $organizacao->nome_fantasia ?? $organizacao->razao_social,
            ],
            'podeGerenciar' => $user->can('gerenciarVinculos', $organizacao),
            'vinculos' => $vinculos
                ->sortBy(fn (VinculoOrganizacao $vinculo): string => mb_strtolower($nomesDosUsuarios[$vinculo->cpf] ?? $vinculo->nome))
                ->map(fn (VinculoOrganizacao $vinculo): array => [
                    'id' => $vinculo->id,
                    'nome' => $nomesDosUsuarios[$vinculo->cpf] ?? $vinculo->nome,
                    'cpf' => Cpf::mascarar($vinculo->cpf),
                    'responsavel' => $vinculo->responsavel,
                    'ehVoce' => $vinculo->cpf === $user->cpf,
                ])
                ->values(),
        ]);
    }

    public function store(GuardarVinculoOrganizacaoRequest $request, Organizacao $organizacao): RedirectResponse
    {
        $organizacao->vinculos()->create([
            'cpf' => $request->validated('cpf'),
            'nome' => $request->validated('nome'),
            'responsavel' => false,
        ]);

        return to_route('organizacoes.vinculos', $organizacao)->with('sucesso', 'Pessoa vinculada.');
    }

    /**
     * O responsável remove qualquer pessoa, menos ele mesmo; as demais só saem da organização.
     * O responsável nunca é removido.
     */
    public function destroy(Request $request, Organizacao $organizacao, VinculoOrganizacao $vinculo): RedirectResponse
    {
        Gate::authorize('update', $organizacao);

        $user = $request->user();
        $removendoOProprio = $vinculo->cpf === $user->cpf;

        abort_if($vinculo->responsavel, 403);
        abort_unless($removendoOProprio || $user->can('gerenciarVinculos', $organizacao), 403);

        $vinculo->delete();

        if ($removendoOProprio) {
            return to_route('organizacoes.index')->with('sucesso', 'Você saiu da organização.');
        }

        return to_route('organizacoes.vinculos', $organizacao)->with('sucesso', 'Pessoa desvinculada.');
    }
}
