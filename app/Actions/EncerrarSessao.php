<?php

namespace App\Actions;

use App\Auth\GovBr\GovBrClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/** Encerra a sessão no portal (botão Sair, Voltar do navegador e página inicial). */
class EncerrarSessao
{
    public function __construct(private GovBrClient $govbr) {}

    /** Devolve o endereço de saída do gov.br quando a sessão também precisa ser encerrada lá; nulo quando não. */
    public function handle(Request $request): ?string
    {
        // Só quem entrou pelo gov.br (portal ou retaguarda) tem sessão lá para encerrar.
        $viaGovBr = (bool) $request->session()->get('portal.via_govbr')
            || (bool) $request->session()->get('retaguarda.via_govbr');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Depois do invalidate: a marcação fica na sessão nova. Telas antigas com dados pessoais
        // não voltam pelo histórico do navegador.
        Inertia::clearHistory();

        return $viaGovBr && config('govbr.driver') === 'govbr' ? $this->govbr->logoutUrl() : null;
    }
}
