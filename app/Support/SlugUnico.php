<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slug do título, único na tabela do modelo, com sufixo -2, -3…; registros excluídos contam (slug nunca é reaproveitado).
 * A base é cortada para o slug com sufixo caber na coluna (VARCHAR2 de 255 bytes).
 */
class SlugUnico
{
    private const int BYTES_BASE = 240;

    /**
     * @param  class-string<Model>  $modelo  Modelo com SoftDeletes e coluna `slug`.
     */
    public static function para(string $modelo, string $titulo): string
    {
        $base = rtrim(mb_strcut(Str::slug($titulo), 0, self::BYTES_BASE, 'UTF-8'), '-') ?: 'item';
        $slug = $base;

        for ($sufixo = 2; $modelo::withTrashed()->where('slug', $slug)->exists(); $sufixo++) {
            $slug = "{$base}-{$sufixo}";
        }

        return $slug;
    }
}
