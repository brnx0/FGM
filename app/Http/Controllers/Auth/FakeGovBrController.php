<?php

namespace App\Http\Controllers\Auth;

use App\Actions\EntrarNaRetaguardaPorGovBr;
use App\Actions\LoginGovBrUser;
use App\Auth\GovBr\FakeLoginGate;
use App\Auth\GovBr\GovBrIdentity;
use App\Http\Controllers\Controller;
use App\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FakeGovBrController extends Controller
{
    public function __construct()
    {
        abort_unless(FakeLoginGate::permitido(), 404);
    }

    /** Vindo da retaguarda no ambiente local, a tela oferece a conta do administrador; nos demais casos, as do portal. */
    public function create(Request $request): Response
    {
        $daRetaguarda = $request->session()->get('govbr.destino') === 'retaguarda' && app()->environment('local');

        return Inertia::render('Auth/FakeLogin', [
            'contas' => config($daRetaguarda ? 'govbr.fake_accounts_retaguarda' : 'govbr.fake_accounts'),
        ]);
    }

    /** Como no gov.br real, o CPF escolhe a conta e os dados vêm dela. A senha da tela não chega aqui. */
    public function store(Request $request, LoginGovBrUser $login, EntrarNaRetaguardaPorGovBr $entrarNaRetaguarda): RedirectResponse
    {
        $contas = $this->contasAceitas();

        // O CPF fictício do administrador não passa na conta dos dígitos: vale só digitado por inteiro e
        // comparado estrito, porque o normalizar completa com zeros e '0' == '00000000000' no PHP.
        $dados = $request->validate([
            'cpf' => ['required', 'string', fn ($attr, $valor, $fail) => Cpf::valido((string) $valor)
                || $contas->pluck('cpf')->containsStrict(preg_replace('/\D/', '', (string) $valor)) ?: $fail('CPF inválido.')],
        ]);

        $conta = $contas->firstWhere('cpf', Cpf::normalizar($dados['cpf']));

        if ($conta === null) {
            throw ValidationException::withMessages(['cpf' => 'Este CPF não está entre as contas de teste.']);
        }

        // Consumido só após a conta ser aceita: erro de digitação no formulário mantém o destino.
        if ($request->session()->pull('govbr.destino') === 'retaguarda') {
            return $entrarNaRetaguarda->handle($conta['cpf']) !== null
                ? redirect()->route('retaguarda.inicio')
                : redirect()->route('retaguarda.login')->with('erro', 'Seu CPF não tem acesso à retaguarda.');
        }

        $user = $login->handle(new GovBrIdentity(
            cpf: $conta['cpf'],
            nome: $conta['nome'],
            email: $conta['email'] ?? null,
            emailVerificado: false,
            telefone: null,
        ));

        if ($user === null) {
            return redirect('/')->with('erro', LoginGovBrUser::GESTOR_RECUSADO);
        }

        return redirect($login->destinoPosLogin($user));
    }

    /**
     * Contas do portal e, só no ambiente local, a do administrador da retaguarda.
     *
     * @return Collection<int, array{cpf: string, nome: string, email: ?string}>
     */
    private function contasAceitas(): Collection
    {
        return collect(config('govbr.fake_accounts'))
            ->merge(app()->environment('local') ? config('govbr.fake_accounts_retaguarda') : []);
    }
}
