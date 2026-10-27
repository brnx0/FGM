<?php

namespace App\Http\Middleware;

use App\Enums\TipoCadastro;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Telas do menu do portal: só para quem finalizou o cadastro com o tipo da rota (tipo-cadastro:proponente). */
class ExigirTipoCadastro
{
    public const AVISO = 'Finalize seu cadastro escolhendo o tipo de cadastro.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $tipo): Response
    {
        $tipoDoUsuario = $request->user()?->tipo_cadastro;

        if ($tipoDoUsuario === null) {
            return redirect()->route('cadastro.tipo')->with('aviso', self::AVISO);
        }

        // Quem trocou de tipo (ou abriu um link antigo) vai para a tela do próprio tipo.
        if ($tipoDoUsuario !== TipoCadastro::from($tipo)) {
            return redirect()->route($tipoDoUsuario->rotaInicial());
        }

        return $next($request);
    }
}
