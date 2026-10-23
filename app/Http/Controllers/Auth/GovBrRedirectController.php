<?php

namespace App\Http\Controllers\Auth;

use App\Auth\GovBr\FakeLoginGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class GovBrRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        if (FakeLoginGate::permitido()) {
            return redirect()->route('govbr.fake');
        }

        // Task 8: ramo real (driver 'govbr') — montar a URL de autorização e redirecionar.
        abort(404);
    }
}
