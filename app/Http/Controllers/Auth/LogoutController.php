<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (config('govbr.driver') === 'govbr') {
            // Task 8: redirecionar para GovBrClient::logoutUrl() para encerrar também a sessão no gov.br.
        }

        return redirect('/');
    }
}
