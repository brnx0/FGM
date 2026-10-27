<?php

namespace App\Enums;

enum PorteOrganizacao: string
{
    case Micro = 'micro';
    case Pequeno = 'pequeno';
    case Medio = 'medio';
    case Grande = 'grande';

    public function rotulo(): string
    {
        return match ($this) {
            self::Micro => 'Micro',
            self::Pequeno => 'Pequeno',
            self::Medio => 'Médio',
            self::Grande => 'Grande porte',
        };
    }
}
