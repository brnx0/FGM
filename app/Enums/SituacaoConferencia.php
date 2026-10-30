<?php

namespace App\Enums;

/** Situação da conferência por IA de um documento da inscrição. */
enum SituacaoConferencia: string
{
    case Enviado = 'enviado';
    case Conferindo = 'conferindo';
    case Aprovado = 'aprovado';
    case Reprovado = 'reprovado';
    case NaoConferido = 'nao_conferido';

    public function rotulo(): string
    {
        return match ($this) {
            self::Enviado => 'Enviado',
            self::Conferindo => 'Conferindo',
            self::Aprovado => 'Aprovado',
            self::Reprovado => 'Reprovado',
            self::NaoConferido => 'Não conferido',
        };
    }
}
