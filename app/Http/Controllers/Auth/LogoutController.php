<?php

namespace App\Http\Controllers\Auth;

use App\Actions\EncerrarSessao;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(Request $request, EncerrarSessao $encerrarSessao): RedirectResponse
    {
        $saidaGovBr = $encerrarSessao->handle($request);

        if ($saidaGovBr !== null) {
            // Encerra também a sessão no gov.br, que devolve o usuário para /auth/govbr/logout.
            return redirect()->away($saidaGovBr);
        }

        return redirect('/');
    }
}
