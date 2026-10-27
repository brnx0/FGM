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
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FakeGovBrController extends Controller
{
    public function __construct()
    {
        abort_unless(FakeLoginGate::permitido(), 404);
    }

    public function create(): Response
    {
        return Inertia::render('Auth/FakeLogin', [
            'contas' => config('govbr.fake_accounts'),
        ]);
    }

    /** Como no gov.br real, o CPF escolhe a conta e os dados vêm dela. A senha da tela não chega aqui. */
    public function store(Request $request, LoginGovBrUser $login, EntrarNaRetaguardaPorGovBr $entrarNaRetaguarda): RedirectResponse
    {
        $dados = $request->validate([
            'cpf' => ['required', 'string', fn ($attr, $valor, $fail) => Cpf::valido((string) $valor) ?: $fail('CPF inválido.')],
        ]);

        $conta = collect(config('govbr.fake_accounts'))->firstWhere('cpf', Cpf::normalizar($dados['cpf']));

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

        return redirect($login->destinoPosLogin($user));
    }
}
