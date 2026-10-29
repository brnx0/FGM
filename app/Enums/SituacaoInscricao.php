<?php

namespace App\Enums;

/** Situação de uma inscrição em edital. */
enum SituacaoInscricao: string
{
    case Rascunho = 'rascunho';
    case Finalizada = 'finalizada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Finalizada => 'Finalizada',
        };
    }
}
