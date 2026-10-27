<?php

namespace App\Support;

/** Municípios brasileiros (IBGE, código de 7 dígitos), lidos de database/data/municipios.json. */
final class Municipios
{
    /** @var list<array{codigo: string, nome: string, uf: string}>|null */
    private static ?array $todos = null;

    /** @var array<string, array{codigo: string, nome: string, uf: string}>|null */
    private static ?array $porCodigo = null;

    /** @var list<string>|null nome normalizado, na mesma ordem de; só montado na busca por nome */
    private static ?array $nomesNormalizados = null;

    /**
     * Começa-com antes de contém (nomes mais curtos primeiro); sem acento e sem diferença de caixa.
     *
     * @return list<array{codigo: string, nome: string, uf: string}>
     */
    public static function buscar(string $termo, int $limite = 20): array
    {
        $termo = self::normalizarTexto($termo);
        if ($termo === '' || $limite < 1) {
            return [];
        }

        $nomes = self::nomesNormalizados();
        $comecam = [];
        $contem = [];
        foreach (self::todos() as $indice => $municipio) {
            $posicao = strpos($nomes[$indice], $termo);
            if ($posicao === 0) {
                $comecam[] = $municipio;
            } elseif ($posicao !== false) {
                $contem[] = $municipio;
            }
        }

        usort($comecam, fn (array $a, array $b): int => strlen($a['nome']) <=> strlen($b['nome']));

        return array_slice([...$comecam, ...$contem], 0, $limite);
    }

    /** @return array{codigo: string, nome: string, uf: string}|null */
    public static function porCodigo(string $codigo): ?array
    {
        self::todos();

        return self::$porCodigo[$codigo] ?? null;
    }

    public static function existe(string $codigo): bool
    {
        return self::porCodigo($codigo) !== null;
    }

    /** @return list<array{codigo: string, nome: string, uf: string}> */
    private static function todos(): array
    {
        if (self::$todos === null) {
            $json = file_get_contents(dirname(__DIR__, 2).'/database/data/municipios.json');
            /** @var list<array{codigo: string, nome: string, uf: string}> $lista */
            $lista = json_decode($json === false ? '[]' : $json, true, 512, JSON_THROW_ON_ERROR);

            self::$todos = $lista;
            self::$porCodigo = array_column($lista, null, 'codigo');
        }

        return self::$todos;
    }

    /** @return list<string> */
    private static function nomesNormalizados(): array
    {
        return self::$nomesNormalizados ??= array_map(
            fn (array $municipio): string => self::normalizarTexto($municipio['nome']),
            self::todos(),
        );
    }

    private static function normalizarTexto(string $texto): string
    {
        return TextoNormalizado::semAcento($texto);
    }
}
