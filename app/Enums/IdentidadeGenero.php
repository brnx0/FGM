<?php

namespace App\Enums;

enum IdentidadeGenero: string
{
    case MulherCis = 'mulher_cis';
    case HomemCis = 'homem_cis';
    case MulherTrans = 'mulher_trans';
    case HomemTrans = 'homem_trans';
    case Travesti = 'travesti';
    case NaoBinaria = 'nao_binaria';
    case Outra = 'outra';
    case NaoInformada = 'nao_informada';

    public function rotulo(): string
    {
        return match ($this) {
            self::MulherCis => 'Mulher cisgênero',
            self::HomemCis => 'Homem cisgênero',
            self::MulherTrans => 'Mulher transgênero',
            self::HomemTrans => 'Homem transgênero',
            self::Travesti => 'Travesti',
            self::NaoBinaria => 'Pessoa não binária',
            self::Outra => 'Outra',
            self::NaoInformada => 'Prefiro não informar',
        };
    }
}
