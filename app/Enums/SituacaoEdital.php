<?php

namespace App\Enums;

enum SituacaoEdital: string
{
    case EmBreve = 'em_breve';
    case Aberto = 'aberto';
    case Encerrado = 'encerrado';

    public function rotulo(): string
    {
        return match ($this) {
            self::EmBreve => 'Em breve',
            self::Aberto => 'Aberto',
            self::Encerrado => 'Encerrado',
        };
    }
}
