<?php

namespace App\Enums;

/** Tipo de deficiência da pessoa com deficiência (PcD). */
enum TipoDeficiencia: string
{
    case Auditiva = 'auditiva';
    case FisicaMotora = 'fisica_motora';
    case Intelectual = 'intelectual';
    case Visual = 'visual';
    case Multipla = 'multipla';
    case Tea = 'transtorno_espectro_autista';
    case Outra = 'outra';

    public function rotulo(): string
    {
        return match ($this) {
            self::Auditiva => 'Auditiva',
            self::FisicaMotora => 'Física-motora',
            self::Intelectual => 'Intelectual',
            self::Visual => 'Visual',
            self::Multipla => 'Múltipla',
            self::Tea => 'Transtorno do espectro autista',
            self::Outra => 'Outra',
        };
    }
}
