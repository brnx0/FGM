<?php

namespace App\Enums;

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
}
