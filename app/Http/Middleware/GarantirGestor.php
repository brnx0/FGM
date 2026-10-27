<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Só deixa passar gestor (ativo e com papel). O proponente logado é barrado sem perder a sessão;
 * quem já entrou na retaguarda e deixou de ser gestor (desativado ou sem papel) é deslogado.
 */
class GarantirGestor
{
    public const ERRO = 'Você não tem acesso à retaguarda.';

    /** Marca na sessão que este usuário já passou pela retaguarda como gestor. */
    private const SESSAO_GESTOR = 'retaguarda.gestor';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('retaguarda.login');
        }

        if ($user->ehGestor()) {
            $request->session()->put(self::SESSAO_GESTOR, $user->getAuthIdentifier());

            return $next($request);
        }

        if ($request->session()->get(self::SESSAO_GESTOR) === $user->getAuthIdentifier()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('retaguarda.login')->with('erro', self::ERRO);
    }
}
