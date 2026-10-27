<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Slug do título, único na tabela do modelo, com sufixo -2, -3…; registros excluídos contam (slug nunca é reaproveitado). */
class SlugUnico
{
    /**
     * @param  class-string<Model>  $modelo  Modelo com SoftDeletes e coluna `slug`.
     */
    public static function para(string $modelo, string $titulo): string
    {
        $base = Str::slug($titulo) ?: 'item';
        $slug = $base;

        for ($sufixo = 2; $modelo::withTrashed()->where('slug', $slug)->exists(); $sufixo++) {
            $slug = "{$base}-{$sufixo}";
        }

        return $slug;
    }
}
