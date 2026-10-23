<?php

namespace App\Support;

/** CPF é sempre string de 11 dígitos, nunca int (zeros à esquerda). */
final class Cpf
{
    public static function normalizar(string $cpf): string
    {
        $digitos = preg_replace('/\D/', '', $cpf) ?? '';

        return str_pad($digitos, 11, '0', STR_PAD_LEFT);
    }

    public static function valido(string $cpf): bool
    {
        // Só dígitos e a pontuação usual; 10 dígitos ainda vale (zero à esquerda perdido).
        if (preg_match('/^[\d.\-\s]*$/', $cpf) !== 1) {
            return false;
        }

        $digitos = preg_replace('/\D/', '', $cpf) ?? '';
        if (strlen($digitos) < 10 || strlen($digitos) > 11) {
            return false;
        }

        $cpf = self::normalizar($digitos);

        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * ($t + 1 - $i);
            }
            $digito = ($soma * 10) % 11 % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    /** 52998224725 → ***.982.247-** */
    public static function mascarar(string $cpf): string
    {
        $cpf = self::normalizar($cpf);

        return '***.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-**';
    }
}
