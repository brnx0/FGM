<?php

namespace App\Http\Middleware;

use App\Enums\TipoCadastro;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Telas do menu do portal: só para quem finalizou o cadastro marcando o tipo da rota (tipo-cadastro:proponente). */
class ExigirTipoCadastro
{
    public const AVISO = 'Finalize seu cadastro escolhendo o tipo de cadastro.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $tipo): Response
    {
        $user = $request->user();

        if (! $user?->cadastroFinalizado()) {
            return redirect()->route('cadastro.tipo')->with('aviso', self::AVISO);
        }

        // Quem desmarcou este tipo (ou abriu um link antigo) vai para a tela de um tipo que tem.
        if (! $user->temTipoCadastro(TipoCadastro::from($tipo))) {
            return redirect()->route($user->rotaInicialDoPortal());
        }

        return $next($request);
    }
}
