<?php

namespace App\Enums;

/** Avatar com que o VLibras abre; o valor é o do parâmetro avatar do plugin. */
enum AvatarVLibras: string
{
    case Icaro = 'icaro';
    case Hosana = 'hosana';
    case Guga = 'guga';
    case Aleatorio = 'random';

    public function rotulo(): string
    {
        return match ($this) {
            self::Icaro => 'Ícaro',
            self::Hosana => 'Hosana',
            self::Guga => 'Guga',
            self::Aleatorio => 'Aleatório',
        };
    }
}
