<?php

namespace App\Support\EditalIa;

use BackedEnum;

/**
 * Peças do esquema de resposta no formato do Gemini (responseSchema, subconjunto OpenAPI: type, properties, items,
 * enum, nullable, required, description), traduzível para a OpenAI por EsquemaOpenAi.
 */
trait MontaEsquema
{
    /**
     * @param  array<string, array<string, mixed>>  $propriedades
     * @param  list<string>  $obrigatorias
     * @return array<string, mixed>
     */
    private static function objeto(array $propriedades, array $obrigatorias = []): array
    {
        return array_filter([
            'type' => 'OBJECT',
            'properties' => $propriedades,
            'required' => $obrigatorias,
        ], fn (mixed $valor): bool => $valor !== []);
    }

    /**
     * @param  array<string, mixed>  $itens
     * @return array<string, mixed>
     */
    private static function lista(array $itens, ?string $descricao = null): array
    {
        return self::campo(['type' => 'ARRAY', 'items' => $itens], $descricao, false);
    }

    /**
     * @return array<string, mixed>
     */
    private static function texto(?string $descricao = null, bool $anulavel = true): array
    {
        return self::campo(['type' => 'STRING'], $descricao, $anulavel);
    }

    /**
     * @return array<string, mixed>
     */
    private static function data(string $descricao): array
    {
        return self::texto("{$descricao} Formato AAAA-MM-DD.");
    }

    /**
     * @return array<string, mixed>
     */
    private static function numero(?string $descricao = null): array
    {
        return self::campo(['type' => 'NUMBER'], $descricao, true);
    }

    /**
     * @return array<string, mixed>
     */
    private static function inteiro(?string $descricao = null, bool $anulavel = true): array
    {
        return self::campo(['type' => 'INTEGER'], $descricao, $anulavel);
    }

    /**
     * @return array<string, mixed>
     */
    private static function booleano(?string $descricao = null): array
    {
        return self::campo(['type' => 'BOOLEAN'], $descricao, true);
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return array<string, mixed>
     */
    private static function enum(string $enum, ?string $descricao = null): array
    {
        return self::enumDe(array_map(fn (BackedEnum $caso): string => (string) $caso->value, $enum::cases()), $descricao, true);
    }

    /**
     * @param  list<string>  $valores
     * @return array<string, mixed>
     */
    private static function enumDe(array $valores, ?string $descricao, bool $anulavel): array
    {
        return self::campo(['type' => 'STRING', 'enum' => $valores], $descricao, $anulavel);
    }

    /**
     * @param  array<string, mixed>  $campo
     * @return array<string, mixed>
     */
    private static function campo(array $campo, ?string $descricao, bool $anulavel): array
    {
        if ($anulavel) {
            $campo['nullable'] = true;
        }
        if ($descricao !== null) {
            $campo['description'] = $descricao;
        }

        return $campo;
    }
}
