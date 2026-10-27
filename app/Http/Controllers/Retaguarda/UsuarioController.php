<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\SalvarUsuarioRequest;
use App\Models\Role;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gestão de usuários da retaguarda (usuários com papel) na retaguarda. Sem exclusão: quem sai é desativado.
 * Proponentes (usuários sem papel) não aparecem aqui.
 */
class UsuarioController extends Controller
{
    private const string ERRO_ULTIMO_ADMINISTRADOR = 'É preciso manter ao menos um administrador ativo.';

    public function index(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));

        $usuarios = User::query()
            ->whereHas('papeis')
            ->with('papeis')
            // No Oracle a comparação de texto diferencia maiúsculas; % e _ digitados valem literalmente.
            ->when($busca !== '', function (Builder $q) use ($busca): void {
                $termo = '%'.addcslashes(mb_strtolower($busca), '\\%_').'%';
                $q->where(fn (Builder $q) => $q
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$termo])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$termo]));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'nome' => $user->name,
                'email' => $user->email,
                'cpf' => Cpf::mascarar($user->cpf),
                'papeis' => $user->papeis->sortBy('nome')->pluck('nome')->values()->all(),
                'ativo' => $user->ativo,
                'ehVoce' => $user->is($request->user()),
            ]);

        return Inertia::render('Retaguarda/Usuarios/Index', [
            'usuarios' => $usuarios,
            'filtros' => ['busca' => $busca],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Retaguarda/Usuarios/Form', [
            'usuario' => null,
            'papeisDisponiveis' => $this->papeisDisponiveis(),
        ]);
    }

    /** CPF de proponente já cadastrado: o mesmo usuário vira usuário da retaguarda (os dados de cadastro dele ficam como estão). */
    public function store(SalvarUsuarioRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $user = $request->usuarioReaproveitado();

            if ($user === null) {
                $user = new User;
                $user->name = $request->validated('name');
                $user->cpf = $request->cpf();
            }

            $user->email = $request->validated('email');
            $user->password = $request->senhaNova();
            $user->ativo = true;
            $user->save();

            $user->papeis()->sync($this->idsDosPapeis($request->papeis()));
        });

        return redirect()->route('retaguarda.usuarios.index')->with('sucesso', 'Usuário salvo.');
    }

    public function edit(User $user): Response
    {
        $this->garantirQueTemPapel($user);

        return Inertia::render('Retaguarda/Usuarios/Form', [
            'usuario' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // CPF inválido (ex.: o do primeiro administrador) vem em branco para ser preenchido.
                'cpf' => Cpf::valido($user->cpf) ? Cpf::formatar($user->cpf) : '',
                'papeis' => $user->papeis()->pluck('slug')->all(),
                'ativo' => $user->ativo,
            ],
            'papeisDisponiveis' => $this->papeisDisponiveis(),
        ]);
    }

    public function update(SalvarUsuarioRequest $request, User $user): RedirectResponse
    {
        $this->garantirQueTemPapel($user);

        DB::transaction(function () use ($request, $user): void {
            $perdeAdministrador = $user->temPapel(Role::ADMINISTRADOR)
                && ! in_array(Role::ADMINISTRADOR, $request->papeis(), true);

            if ($perdeAdministrador && $user->is($request->user())) {
                throw ValidationException::withMessages(['papeis' => 'Você não pode remover o seu próprio papel de administrador.']);
            }

            if ($perdeAdministrador && $this->ehUltimoAdministradorAtivo($user)) {
                throw ValidationException::withMessages(['papeis' => self::ERRO_ULTIMO_ADMINISTRADOR]);
            }

            $user->name = $request->validated('name');
            $user->email = $request->validated('email');
            $user->cpf = $request->cpf();
            if ($request->senhaNova() !== null) {
                $user->password = $request->senhaNova();
            }
            $user->save();

            $user->papeis()->sync($this->idsDosPapeis($request->papeis()));
        });

        return redirect()->route('retaguarda.usuarios.index')->with('sucesso', 'Usuário salvo.');
    }

    /** Alterna ativo/inativo. Quem é desativado com a sessão aberta sai na próxima requisição (GarantirGestor). */
    public function ativo(Request $request, User $user): RedirectResponse
    {
        $this->garantirQueTemPapel($user);

        if ($user->ativo && $user->is($request->user())) {
            return back()->with('erro', 'Você não pode desativar a si mesmo.');
        }

        if ($user->ativo && $this->ehUltimoAdministradorAtivo($user)) {
            return back()->with('erro', self::ERRO_ULTIMO_ADMINISTRADOR);
        }

        $user->update(['ativo' => ! $user->ativo]);

        return back()->with('sucesso', $user->ativo ? 'Usuário ativado.' : 'Usuário desativado.');
    }

    /** Proponente (sem papel) não é gerido aqui. */
    private function garantirQueTemPapel(User $user): void
    {
        abort_unless($user->papeis()->exists(), 404);
    }

    private function ehUltimoAdministradorAtivo(User $user): bool
    {
        return $user->ativo
            && $user->temPapel(Role::ADMINISTRADOR)
            && ! User::query()
                ->where('ativo', true)
                ->whereKeyNot($user->id)
                ->whereHas('papeis', fn (Builder $q) => $q->where('slug', Role::ADMINISTRADOR))
                ->exists();
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function idsDosPapeis(array $slugs): array
    {
        return Role::whereIn('slug', $slugs)->pluck('id')->all();
    }

    /** @return list<array{slug: string, nome: string}> */
    private function papeisDisponiveis(): array
    {
        return Role::orderBy('nome')->get(['slug', 'nome'])
            ->map(fn (Role $papel): array => ['slug' => $papel->slug, 'nome' => $papel->nome])
            ->all();
    }
}
