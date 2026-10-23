<?php

namespace App\Http\Controllers\Auth;

use App\Actions\LoginGovBrUser;
use App\Auth\GovBr\FakeLoginGate;
use App\Auth\GovBr\GovBrIdentity;
use App\Http\Controllers\Controller;
use App\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, LoginGovBrUser $login): RedirectResponse
    {
        $dados = $request->validate([
            'cpf' => ['required', 'string', fn ($attr, $valor, $fail) => Cpf::valido((string) $valor) ?: $fail('CPF inválido.')],
            'nome' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $user = $login->handle(new GovBrIdentity(
            cpf: Cpf::normalizar($dados['cpf']),
            nome: $dados['nome'],
            email: $dados['email'] ?: null,
            emailVerificado: false,
            telefone: null,
        ));

        return redirect($login->destinoPosLogin($user));
    }
}
