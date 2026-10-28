<?php

namespace App\Enums;

/** Natureza de quem se inscreve; cada categoria do edital diz quais aceita. */
enum NaturezaProponente: string
{
    case PessoaFisica = 'pessoa_fisica';
    case Mei = 'mei';
    case SemFinsLucrativos = 'sem_fins';
    case ComFinsLucrativos = 'com_fins';

    public function rotulo(): string
    {
        return match ($this) {
            self::PessoaFisica => 'Pessoa Física',
            self::Mei => 'Pessoa Jurídica – MEI',
            self::SemFinsLucrativos => 'Pessoa Jurídica sem fins lucrativos',
            self::ComFinsLucrativos => 'Pessoa Jurídica com fins lucrativos',
        };
    }
}
