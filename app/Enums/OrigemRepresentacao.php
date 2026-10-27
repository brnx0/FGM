<?php

namespace App\Enums;

enum OrigemRepresentacao: string
{
    case ContratoSocial = 'contrato_social';
    case Procuracao = 'procuracao';
    case AtaEleicao = 'ata_eleicao';
    case Outro = 'outro';

    public function rotulo(): string
    {
        return match ($this) {
            self::ContratoSocial => 'Contrato social',
            self::Procuracao => 'Procuração',
            self::AtaEleicao => 'Ata de eleição',
            self::Outro => 'Outro',
        };
    }
}
