<?php

namespace App\Support;

use Transliterator;

/** Normalização de texto para comparações: sem acento e sem diferença de caixa. */
final class TextoNormalizado
{
    private static ?Transliterator $transliterador = null;

    private static bool $transliteradorCriado = false;

    public static function semAcento(string $texto): string
    {
        $transliterador = self::transliterador();
        $convertido = $transliterador?->transliterate($texto);

        return trim($convertido === false || $convertido === null ? mb_strtolower($texto) : $convertido);
    }

    /** A regra ICU é compilada uma única vez; sem a extensão intl, devolve nulo e vale o fallback. */
    private static function transliterador(): ?Transliterator
    {
        if (! self::$transliteradorCriado) {
            self::$transliteradorCriado = true;
            self::$transliterador = class_exists(Transliterator::class)
                ? Transliterator::create('Any-Latin; Latin-ASCII; Lower()')
                : null;
        }

        return self::$transliterador;
    }
}
