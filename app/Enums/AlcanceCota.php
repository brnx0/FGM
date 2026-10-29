<?php

namespace App\Enums;

/** Onde a reserva de cotas é aplicada. */
enum AlcanceCota: string
{
    case Edital = 'edital';
    case Categoria = 'categoria';
    case Chamada = 'chamada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Edital => 'Todo o edital',
            self::Categoria => 'Por categoria',
            self::Chamada => 'Por chamada',
        };
    }
}
