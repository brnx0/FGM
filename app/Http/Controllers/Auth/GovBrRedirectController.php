<?php

namespace App\Http\Controllers\Auth;

use App\Auth\GovBr\FakeLoginGate;
use App\Auth\GovBr\GovBrClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GovBrRedirectController extends Controller
{
    public function __invoke(Request $request, GovBrClient $govbr): RedirectResponse
    {
        if (FakeLoginGate::permitido()) {
            return redirect()->route('govbr.fake');
        }

        abort_unless(config('govbr.driver') === 'govbr', 404);

        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);

        $request->session()->put([
            'govbr.state' => $state,
            'govbr.nonce' => $nonce,
            'govbr.verifier' => $verifier,
        ]);

        return redirect()->away($govbr->authorizationUrl($state, $nonce, $verifier));
    }
}
