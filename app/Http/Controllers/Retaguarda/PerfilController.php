<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\AtualizarPerfilRequest;
use App\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Perfil do próprio usuário logado na retaguarda. */
class PerfilController extends Controller
{
    public function edit(Request $request): Response
    {
        $usuario = $request->user();

        return Inertia::render('Retaguarda/Perfil', [
            'perfil' => [
                'name' => $usuario->name,
                'email' => $usuario->email,
                'telefone' => $usuario->phone !== null ? preg_replace('/\D/', '', $usuario->phone) : null,
                'cpf' => Cpf::mascarar($usuario->cpf),
                'papeis' => $usuario->papeis()->orderBy('nome')->pluck('nome')->all(),
            ],
            'temSenha' => $usuario->password !== null,
        ]);
    }

    public function update(AtualizarPerfilRequest $request): RedirectResponse
    {
        $request->user()->update([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('telefone') ?: null,
        ]);

        return redirect()->route('retaguarda.perfil.edit')->with('sucesso', 'Perfil atualizado.');
    }
}
