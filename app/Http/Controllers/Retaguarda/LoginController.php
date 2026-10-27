<?php

namespace App\Http\Controllers\Retaguarda;

use App\Auth\GovBr\GovBrClient;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Entrada da retaguarda por e-mail e senha, e saída. */
class LoginController extends Controller
{
    private const ERRO = 'E-mail ou senha inválidos.';

    public function create(): Response
    {
        return Inertia::render('Retaguarda/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'senha' => ['required', 'string'],
        ]);

        $user = User::where('email', mb_strtolower(trim($dados['email'])))->first();

        // Mesma mensagem para e-mail desconhecido, senha errada, sem senha, inativo ou sem papel.
        if ($user === null
            || $user->password === null
            || ! Hash::check($dados['senha'], $user->password)
            || ! $user->ehGestor()) {
            throw ValidationException::withMessages(['email' => self::ERRO]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('retaguarda.inicio');
    }

    public function destroy(Request $request, GovBrClient $govbr): RedirectResponse
    {
        $viaGovBr = (bool) $request->session()->get('retaguarda.via_govbr');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($viaGovBr && config('govbr.driver') === 'govbr') {
            // Encerra também a sessão no gov.br.
            return redirect()->away($govbr->logoutUrl());
        }

        return redirect()->route('retaguarda.login');
    }
}
