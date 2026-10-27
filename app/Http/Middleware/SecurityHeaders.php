<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const VITE_DEV_SERVER = 'http://localhost:5173';

    public function handle(Request $request, Closure $next): Response
    {
        // Nonce novo a cada request; o Vite o aplica nas tags que gera e o Blade
        // o usa no script inline de preferências de acessibilidade.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->politica($nonce));
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function politica(string $nonce): string
    {
        $dev = app()->environment('local') ? ' '.self::VITE_DEV_SERVER : '';
        $devWs = app()->environment('local') ? ' ws:' : '';

        // O login gov.br sai do portal por redirecionamento após GET/POST.
        $govbr = config('govbr.base_url') ? ' '.config('govbr.base_url') : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$dev}",
            "style-src 'self' 'unsafe-inline'{$dev}",
            "img-src 'self' data:{$dev}",
            "font-src 'self' data:{$dev}",
            "connect-src 'self'{$dev}{$devWs}",
            // Vídeos do YouTube na descrição do evento (HtmlSeguro só deixa este domínio).
            'frame-src https://www.youtube-nocookie.com',
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'{$govbr}",
            "frame-ancestors 'none'",
        ]);
    }
}
