<?php

namespace App\Enums;

/** Como a IA confere um documento enviado na inscrição. */
enum ChecagemDocumento: string
{
    case Rigorosa = 'rigorosa';
    case AceitaSemValidar = 'aceita_sem_validar';
    case Nenhuma = 'nenhuma';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rigorosa => 'Rigorosa',
            self::AceitaSemValidar => 'Aceita sem validar',
            self::Nenhuma => 'Não validar',
        };
    }
}
