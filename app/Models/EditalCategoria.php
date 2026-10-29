<?php

namespace App\Models;

use App\Enums\NaturezaProponente;
use Database\Factories\EditalCategoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Categoria do edital; naturezas nulas = as do edital, chamada nula = quando o edital tem uma só. */
#[Fillable(['edital_chamada_id', 'nome', 'descricao', 'valor_centavos', 'vagas', 'naturezas', 'ordem'])]
class EditalCategoria extends Model
{
    /** @use HasFactory<EditalCategoriaFactory> */
    use HasFactory;

    protected $table = 'edital_categorias';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'valor_centavos' => 'integer',
            'vagas' => 'integer',
            'naturezas' => AsEnumCollection::of(NaturezaProponente::class),
            'ordem' => 'integer',
        ];
    }

    /** @return BelongsTo<Edital, $this> */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    /** @return BelongsTo<EditalChamada, $this> */
    public function chamada(): BelongsTo
    {
        return $this->belongsTo(EditalChamada::class, 'edital_chamada_id');
    }

    /** @return HasMany<EditalCategoriaItem, $this> */
    public function itens(): HasMany
    {
        return $this->hasMany(EditalCategoriaItem::class)->orderBy('ordem')->orderBy('id');
    }
}
