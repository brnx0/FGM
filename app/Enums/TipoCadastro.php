<?php

namespace App\Enums;

/** Escolhido na última etapa do cadastro (um ou os dois); define o menu do portal. */
enum TipoCadastro: string
{
    case Proponente = 'proponente';
    case Patrocinador = 'patrocinador';

    public function rotulo(): string
    {
        return match ($this) {
            self::Proponente => 'Proponente',
            self::Patrocinador => 'Patrocinador',
        };
    }

    /** Tela em que o portal abre para este tipo (após o login e ao finalizar o cadastro). */
    public function rotaInicial(): string
    {
        return match ($this) {
            self::Proponente => 'portal.editais',
            self::Patrocinador => 'portal.projetos',
        };
    }
}
