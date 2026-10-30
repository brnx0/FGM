<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\SalvarAvaliadorRequest;
use App\Models\Avaliador;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cadastro: avaliadores dos editais. Sem exclusão, só desativação.
 */
class AvaliadorController extends Controller
{
    public function index(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));

        $avaliadores = Avaliador::query()
            ->select(['id', 'nome', 'cpf', 'email', 'ativo'])
            ->when($busca !== '', fn (Builder $q) => $this->buscar($q, $busca))
            ->orderBy('nome')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Avaliador $avaliador): array => [
                'id' => (int) $avaliador->id,
                'nome' => $avaliador->nome,
                'cpf' => Cpf::mascarar($avaliador->cpf),
                'email' => $avaliador->email,
                'ativo' => $avaliador->ativo,
            ]);

        return Inertia::render('Retaguarda/Avaliadores/Index', [
            'avaliadores' => $avaliadores,
            'filtros' => ['busca' => $busca],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Retaguarda/Avaliadores/Form', ['avaliador' => null]);
    }

    public function store(SalvarAvaliadorRequest $request): RedirectResponse
    {
        Avaliador::create($request->dados());

        return redirect()->route('retaguarda.avaliadores.index')->with('sucesso', 'Avaliador salvo.');
    }

    public function edit(Avaliador $avaliador): Response
    {
        return Inertia::render('Retaguarda/Avaliadores/Form', [
            'avaliador' => [
                'id' => (int) $avaliador->id,
                'nome' => $avaliador->nome,
                'data_nascimento' => $avaliador->data_nascimento->toDateString(),
                'cpf' => Cpf::formatar($avaliador->cpf),
                'email' => $avaliador->email,
                'ativo' => $avaliador->ativo,
            ],
        ]);
    }

    public function update(SalvarAvaliadorRequest $request, Avaliador $avaliador): RedirectResponse
    {
        $avaliador->update($request->dados());

        return redirect()->route('retaguarda.avaliadores.index')->with('sucesso', 'Avaliador salvo.');
    }

    public function ativo(Avaliador $avaliador): RedirectResponse
    {
        $avaliador->update(['ativo' => ! $avaliador->ativo]);

        return back()->with('sucesso', $avaliador->ativo ? 'Avaliador ativado.' : 'Avaliador desativado.');
    }

    /** CPF completo digitado procura exato (a lista continua mostrando só o mascarado); o resto, nome e e-mail. */
    private function buscar(Builder $query, string $busca): void
    {
        $cpf = preg_replace('/\D/', '', $busca);
        if (strlen($cpf) === 11 && preg_match('/^[\d.\-\s]+$/', $busca) === 1) {
            $query->where('cpf', $cpf);

            return;
        }

        // No Oracle a comparação de texto diferencia maiúsculas; % e _ digitados valem literalmente.
        $termo = '%'.addcslashes(mb_strtolower($busca), '\\%_').'%';
        $query->where(fn (Builder $q) => $q
            ->whereRaw("LOWER(nome) LIKE ? ESCAPE '\\'", [$termo])
            ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$termo]));
    }
}
