<?php

namespace App\Enums;

use App\Models\Organizacao;

/** Natureza jurídica de quem pode se inscrever num edital. */
enum NaturezaProponente: string
{
    case PessoaFisica = 'pessoa_fisica';
    case Mei = 'mei';
    case SemFins = 'sem_fins';
    case ComFins = 'com_fins';

    public function rotulo(): string
    {
        return match ($this) {
            self::PessoaFisica => 'Pessoa Física',
            self::Mei => 'MEI',
            self::SemFins => 'Pessoa Jurídica sem fins lucrativos',
            self::ComFins => 'Pessoa Jurídica com fins lucrativos',
        };
    }

    /** A natureza com que a organização se inscreve: MEI, com ou sem fins lucrativos. */
    public static function daOrganizacao(Organizacao $organizacao): self
    {
        if ($organizacao->mei) {
            return self::Mei;
        }

        return $organizacao->finalidade_lucrativa ? self::ComFins : self::SemFins;
    }
}
