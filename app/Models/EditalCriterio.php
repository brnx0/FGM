<?php

namespace App\Models;

use Database\Factories\EditalCriterioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Critério da avaliação de mérito; categorias só contam sem todas_categorias. */
#[Fillable(['nome', 'descricao', 'peso', 'pontos_maximos', 'todas_categorias', 'ordem'])]
class EditalCriterio extends Model
{
    /** @use HasFactory<EditalCriterioFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peso' => 'decimal:2',
            'pontos_maximos' => 'decimal:2',
            'todas_categorias' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    /** @return BelongsTo<Edital, $this> */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    /** @return BelongsToMany<EditalCategoria, $this> */
    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(EditalCategoria::class, 'edital_criterio_categoria');
    }
}
