<?php

namespace App\Enums;

/** Nível de escolaridade. */
enum Escolaridade: string
{
    case SemEducacaoFormal = 'sem_educacao_formal';
    case FundamentalIncompleto = 'fundamental_incompleto';
    case FundamentalCompleto = 'fundamental_completo';
    case MedioIncompleto = 'medio_incompleto';
    case MedioCompleto = 'medio_completo';
    case TecnicoIncompleto = 'tecnico_incompleto';
    case TecnicoCompleto = 'tecnico_completo';
    case SuperiorIncompleto = 'superior_incompleto';
    case SuperiorCompleto = 'superior_completo';
    case PosIncompleta = 'pos_incompleta';
    case PosCompleta = 'pos_completa';

    public function rotulo(): string
    {
        return match ($this) {
            self::SemEducacaoFormal => 'Não possui educação formal',
            self::FundamentalIncompleto => 'Ensino fundamental incompleto ou equivalente',
            self::FundamentalCompleto => 'Ensino fundamental completo ou equivalente',
            self::MedioIncompleto => 'Ensino médio incompleto ou equivalente',
            self::MedioCompleto => 'Ensino médio completo ou equivalente',
            self::TecnicoIncompleto => 'Curso técnico incompleto',
            self::TecnicoCompleto => 'Curso técnico completo',
            self::SuperiorIncompleto => 'Ensino superior incompleto ou equivalente',
            self::SuperiorCompleto => 'Ensino superior completo',
            self::PosIncompleta => 'Pós-graduação incompleto',
            self::PosCompleta => 'Pós-graduação completo',
        };
    }
}
