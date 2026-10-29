<?php

namespace App\Enums;

/** Modalidades de cota oferecidas pelo edital. */
enum ModalidadeCota: string
{
    case Negros = 'negros';
    case Indigenas = 'indigenas';
    case Pcd = 'pcd';

    public function rotulo(): string
    {
        return match ($this) {
            self::Negros => 'Pessoas negras',
            self::Indigenas => 'Pessoas indígenas',
            self::Pcd => 'Pessoas com deficiência',
        };
    }
}
