<?php

namespace App\Enums;

/** Faixa de renda mensal, em salários mínimos. */
enum FaixaDeRenda: string
{
    case Nenhuma = 'nenhuma';
    case AteUm = 'ate_1';
    case DeUmATres = 'de_1_a_3';
    case DeTresACinco = 'de_3_a_5';
    case DeCincoAOito = 'de_5_a_8';
    case DeOitoADez = 'de_8_a_10';
    case AcimaDeDez = 'acima_de_10';

    public function rotulo(): string
    {
        return match ($this) {
            self::Nenhuma => 'Nenhuma renda',
            self::AteUm => 'Até 1 salário mínimo',
            self::DeUmATres => 'De 1 a 3 salários mínimos',
            self::DeTresACinco => 'De 3 a 5 salários mínimos',
            self::DeCincoAOito => 'De 5 a 8 salários mínimos',
            self::DeOitoADez => 'De 8 a 10 salários mínimos',
            self::AcimaDeDez => 'Acima de 10 salários mínimos',
        };
    }
}
