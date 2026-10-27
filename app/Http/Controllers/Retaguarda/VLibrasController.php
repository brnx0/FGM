<?php

namespace App\Http\Controllers\Retaguarda;

use App\Enums\AvatarVLibras;
use App\Enums\LadoVLibras;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\SalvarVLibrasRequest;
use App\Models\ConfiguracaoVLibras;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Parâmetros do VLibras (tradutor de Libras do governo federal) nas páginas fora da retaguarda. */
class VLibrasController extends Controller
{
    public function edit(): Response
    {
        $configuracao = ConfiguracaoVLibras::atual();

        return Inertia::render('Retaguarda/VLibras', [
            'configuracao' => [
                'ativo' => $configuracao->ativo,
                'lado' => $configuracao->lado->value,
                'avatar' => $configuracao->avatar->value,
            ],
            'lados' => array_map(fn (LadoVLibras $lado): array => ['valor' => $lado->value, 'rotulo' => $lado->rotulo()], LadoVLibras::cases()),
            'avatares' => array_map(fn (AvatarVLibras $avatar): array => ['valor' => $avatar->value, 'rotulo' => $avatar->rotulo()], AvatarVLibras::cases()),
        ]);
    }

    public function update(SalvarVLibrasRequest $request): RedirectResponse
    {
        ConfiguracaoVLibras::atual()->fill($request->validated())->save();

        return redirect()->route('retaguarda.vlibras.edit')->with('sucesso', 'Configuração do VLibras salva.');
    }
}
