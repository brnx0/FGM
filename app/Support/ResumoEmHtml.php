<?php

namespace App\Support;

/** Converte o antigo resumo em texto puro do edital no HTML da divulgação (usado pela migration). */
final class ResumoEmHtml
{
    public static function converter(?string $resumo): ?string
    {
        if ($resumo === null || trim($resumo) === '') {
            return null;
        }

        return '<p>'.e($resumo).'</p>';
    }
}
