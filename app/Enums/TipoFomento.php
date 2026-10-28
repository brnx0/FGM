<?php

namespace App\Enums;

enum TipoFomento: string
{
    case Direto = 'direto';
    case Indireto = 'indireto';

    public function rotulo(): string
    {
        return match ($this) {
            self::Direto => 'Direto',
            self::Indireto => 'Indireto',
        };
    }
}
