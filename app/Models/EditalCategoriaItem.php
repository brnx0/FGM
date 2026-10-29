<?php

namespace App\Models;

use Database\Factories\EditalCategoriaItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item que o proponente escolhe dentro da categoria (ex.: um bem do inventário, um espaço). */
#[Fillable(['nome', 'valor_centavos', 'vagas', 'suplentes', 'ordem'])]
class EditalCategoriaItem extends Model
{
    /** @use HasFactory<EditalCategoriaItemFactory> */
    use HasFactory;

    protected $table = 'edital_categoria_itens';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'vagas' => 'integer',
            'suplentes' => 'integer',
            'ordem' => 'integer',
        ];
    }

    /** @return BelongsTo<EditalCategoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(EditalCategoria::class, 'edital_categoria_id');
    }
}
