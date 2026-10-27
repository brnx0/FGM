<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Organização só pode ser cadastrada por quem já completou os dados de pessoa física. */
class ExigirCadastroCompleto
{
    public const AVISO = 'Complete seus dados de pessoa física antes de cadastrar uma organização.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->profile_completed_at === null) {
            return redirect()->route('cadastro')->with('aviso', self::AVISO);
        }

        return $next($request);
    }
}
