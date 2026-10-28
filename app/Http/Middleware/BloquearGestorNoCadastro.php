<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A área de cadastro do portal é só para proponentes: gestor (ativo e com papel) volta à home, onde aparece
 * como visitante. O portal nunca leva à retaguarda, que tem login próprio.
 */
class BloquearGestorNoCadastro
{
    public const ERRO = 'A área de cadastro é para proponentes. Você está conectado como usuário da retaguarda.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->ehGestor()) {
            return redirect()->route('home')->with('erro', self::ERRO);
        }

        return $next($request);
    }
}
