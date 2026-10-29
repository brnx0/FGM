<?php

namespace App\Enums;

/** Tipo do instrumento publicado. */
enum TipoEdital: string
{
    case Edital = 'edital';
    case Chamamento = 'chamamento';

    public function rotulo(): string
    {
        return match ($this) {
            self::Edital => 'Edital',
            self::Chamamento => 'Chamamento Público',
        };
    }
}
