<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\SalvarPrefeituraBairroRequest;
use App\Models\Bairro;
use App\Models\PrefeituraBairro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Parametrização: Prefeituras-Bairro de Salvador e os bairros de cada uma. Sem exclusão: a desativada sai das opções
 * do cadastro do proponente, mas continua valendo para quem já a escolheu.
 */
class PrefeituraBairroController extends Controller
{
    public function index(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));

        $prefeituras = PrefeituraBairro::query()
            ->select(['id', 'numero', 'nome', 'ativo'])
            ->withCount('bairros')
            // Acha a Prefeitura-Bairro pelo nome dela ou de um dos seus bairros. No Oracle a comparação de texto
            // diferencia maiúsculas; % e _ digitados valem literalmente.
            ->when($busca !== '', function (Builder $q) use ($busca): void {
                $termo = '%'.addcslashes(mb_strtolower($busca), '\\%_').'%';
                $q->where(fn (Builder $q) => $q
                    ->whereRaw("LOWER(prefeituras_bairro.nome) LIKE ? ESCAPE '\\'", [$termo])
                    ->orWhereHas('bairros', fn (Builder $q) => $q->whereRaw("LOWER(bairros.nome) LIKE ? ESCAPE '\\'", [$termo])));
            })
            ->orderBy('numero')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PrefeituraBairro $prefeitura): array => [
                'id' => (int) $prefeitura->id,
                'numero' => $prefeitura->numeroRomano(),
                'nome' => $prefeitura->nome,
                'totalBairros' => (int) $prefeitura->bairros_count,
                'ativo' => $prefeitura->ativo,
            ]);

        return Inertia::render('Retaguarda/PrefeiturasBairro/Index', [
            'prefeituras' => $prefeituras,
            'filtros' => ['busca' => $busca],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Retaguarda/PrefeiturasBairro/Form', ['prefeitura' => null]);
    }

    public function store(SalvarPrefeituraBairroRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $prefeitura = PrefeituraBairro::create([
                'numero' => (int) $request->validated('numero'),
                'nome' => $request->validated('nome'),
                'codigo' => PrefeituraBairro::codigoLivrePara($request->validated('nome')),
            ]);

            $this->sincronizarBairros($prefeitura, $request->bairrosParaSalvar());
        });

        return redirect()->route('retaguarda.prefeituras-bairro.index')->with('sucesso', 'Prefeitura-Bairro salva.');
    }

    public function edit(PrefeituraBairro $prefeitura): Response
    {
        return Inertia::render('Retaguarda/PrefeiturasBairro/Form', [
            'prefeitura' => [
                'id' => (int) $prefeitura->id,
                'numero' => $prefeitura->numero,
                'nome' => $prefeitura->nome,
                'ativo' => $prefeitura->ativo,
                // Em ordem alfabética sem depender da ordenação do banco (no Oracle, binária: "Águas Claras" iria para o fim).
                'bairros' => $prefeitura->bairros()->get(['id', 'nome'])
                    ->sortBy(fn (Bairro $bairro): string => Str::lower(Str::ascii($bairro->nome)))
                    ->map(fn (Bairro $bairro): array => ['id' => (int) $bairro->id, 'nome' => $bairro->nome])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function update(SalvarPrefeituraBairroRequest $request, PrefeituraBairro $prefeitura): RedirectResponse
    {
        DB::transaction(function () use ($request, $prefeitura): void {
            $prefeitura->update([
                'numero' => (int) $request->validated('numero'),
                'nome' => $request->validated('nome'),
            ]);

            $this->sincronizarBairros($prefeitura, $request->bairrosParaSalvar());
        });

        return redirect()->route('retaguarda.prefeituras-bairro.index')->with('sucesso', 'Prefeitura-Bairro salva.');
    }

    public function ativo(PrefeituraBairro $prefeitura): RedirectResponse
    {
        $prefeitura->update(['ativo' => ! $prefeitura->ativo]);

        return back()->with('sucesso', $prefeitura->ativo ? 'Prefeitura-Bairro ativada.' : 'Prefeitura-Bairro desativada.');
    }

    /**
     * Atualiza os bairros desta Prefeitura-Bairro que vieram com id, cria os novos e apaga os que não vieram.
     * Id de outra Prefeitura-Bairro (ou inexistente) vale como bairro novo. Apaga e renomeia antes de criar: o nome
     * é único, e um nome liberado na mesma gravação pode ser reaproveitado.
     *
     * @param  list<array{id: ?int, nome: string}>  $bairros
     */
    private function sincronizarBairros(PrefeituraBairro $prefeitura, array $bairros): void
    {
        $existentes = $prefeitura->bairros()->get()->keyBy(fn (Bairro $bairro): int => (int) $bairro->id);
        $renomeados = [];
        $novos = [];

        foreach ($bairros as ['id' => $id, 'nome' => $nome]) {
            if ($id !== null && $existentes->has($id) && ! array_key_exists($id, $renomeados)) {
                $renomeados[$id] = $nome;
            } else {
                $novos[] = $nome;
            }
        }

        $existentes->except(array_keys($renomeados))->each(fn (Bairro $bairro) => $bairro->delete());

        foreach ($renomeados as $id => $nome) {
            $existentes[$id]->update(['nome' => $nome]);
        }

        foreach ($novos as $nome) {
            $prefeitura->bairros()->create(['nome' => $nome]);
        }
    }
}
