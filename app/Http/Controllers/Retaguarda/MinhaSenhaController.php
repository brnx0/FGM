<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\TrocarSenhaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Troca da senha do próprio usuário logado na retaguarda. */
class MinhaSenhaController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Retaguarda/MinhaSenha', [
            'temSenha' => $request->user()->password !== null,
        ]);
    }

    public function update(TrocarSenhaRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('nova_senha')]);
        $request->session()->regenerate();

        return redirect()->route('retaguarda.senha.edit')->with('sucesso', 'Senha alterada.');
    }
}
