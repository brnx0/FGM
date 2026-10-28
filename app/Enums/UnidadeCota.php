<?php

namespace App\Enums;

/** Como a reserva de cota de uma categoria é informada. */
enum UnidadeCota: string
{
    case Vagas = 'vagas';
    case Percentual = 'percentual';

    public function rotulo(): string
    {
        return match ($this) {
            self::Vagas => 'vagas',
            self::Percentual => '%',
        };
    }
}
