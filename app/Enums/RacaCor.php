<?php

namespace App\Enums;

/** Categorias de cor ou raça do IBGE. */
enum RacaCor: string
{
    case Branca = 'branca';
    case Preta = 'preta';
    case Parda = 'parda';
    case Amarela = 'amarela';
    case Indigena = 'indigena';
    case NaoInformada = 'nao_informada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Branca => 'Branca',
            self::Preta => 'Preta',
            self::Parda => 'Parda',
            self::Amarela => 'Amarela',
            self::Indigena => 'Indígena',
            self::NaoInformada => 'Prefiro não informar',
        };
    }
}
