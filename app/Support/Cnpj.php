<?php

namespace App\Support;

/** CNPJ é sempre string de 14 caracteres (a partir de 2026 pode ter letras nos 12 primeiros). */
final class Cnpj
{
    private const array PESOS_DV1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    private const array PESOS_DV2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

    /** 12.abc.345/01de-35 → 12ABC34501DE35 */
    public static function normalizar(string $cnpj): string
    {
        return strtoupper(str_replace(['.', '/', '-', ' '], '', $cnpj));
    }

    public static function valido(string $cnpj): bool
    {
        $cnpj = self::normalizar($cnpj);

        if (preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $cnpj) !== 1) {
            return false;
        }

        if (preg_match('/^(.)\1{13}$/', $cnpj) === 1) {
            return false;
        }

        $dv1 = self::digitoVerificador(substr($cnpj, 0, 12), self::PESOS_DV1);
        $dv2 = self::digitoVerificador(substr($cnpj, 0, 12).$dv1, self::PESOS_DV2);

        return substr($cnpj, 12) === $dv1.$dv2;
    }

    /** 12ABC34501DE35 → 12.ABC.345/01DE-35 */
    public static function formatar(string $cnpj): string
    {
        $cnpj = self::normalizar($cnpj);

        return substr($cnpj, 0, 2).'.'.substr($cnpj, 2, 3).'.'.substr($cnpj, 5, 3).'/'.substr($cnpj, 8, 4).'-'.substr($cnpj, 12, 2);
    }

    /**
     * Valor de cada caractere = ord($c) - 48 (dígitos 0-9, letras A=17 … Z=42).
     *
     * @param  list<int>  $pesos
     */
    private static function digitoVerificador(string $base, array $pesos): int
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (ord($base[$i]) - 48) * $peso;
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
