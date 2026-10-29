<?php

namespace App\Enums;

/** Sobre o que a reserva de cotas é calculada. */
enum BaseCota: string
{
    case Vagas = 'vagas';
    case Recursos = 'recursos';

    public function rotulo(): string
    {
        return match ($this) {
            self::Vagas => 'Vagas',
            self::Recursos => 'Recursos',
        };
    }
}
