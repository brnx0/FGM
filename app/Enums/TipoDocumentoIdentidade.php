<?php

namespace App\Enums;

enum TipoDocumentoIdentidade: string
{
    case Rg = 'rg';
    case Cnh = 'cnh';
    case Passaporte = 'passaporte';
    case RneCrnm = 'rne_crnm';
    case Outro = 'outro';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rg => 'RG',
            self::Cnh => 'CNH',
            self::Passaporte => 'Passaporte',
            self::RneCrnm => 'RNE/CRNM',
            self::Outro => 'Outro',
        };
    }
}
