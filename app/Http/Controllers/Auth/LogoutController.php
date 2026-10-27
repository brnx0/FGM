<?php

namespace App\Http\Controllers\Auth;

use App\Auth\GovBr\GovBrClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(Request $request, GovBrClient $govbr): RedirectResponse
    {
        // Só quem entrou pelo gov.br (portal ou retaguarda) tem sessão lá para encerrar.
        $viaGovBr = (bool) $request->session()->get('portal.via_govbr')
            || (bool) $request->session()->get('retaguarda.via_govbr');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($viaGovBr && config('govbr.driver') === 'govbr') {
            // Encerra também a sessão no gov.br, que devolve o usuário para /auth/govbr/logout.
            return redirect()->away($govbr->logoutUrl());
        }

        return redirect('/');
    }
}
