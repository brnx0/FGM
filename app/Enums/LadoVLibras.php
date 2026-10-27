<?php

namespace App\Enums;

/** Lado da tela em que fica o botão do VLibras; o valor é o do parâmetro position do plugin. */
enum LadoVLibras: string
{
    case Direita = 'R';
    case Esquerda = 'L';

    public function rotulo(): string
    {
        return match ($this) {
            self::Direita => 'Direita',
            self::Esquerda => 'Esquerda',
        };
    }
}
