<?php

namespace App\Models;

use App\Enums\EtapaDocumento;
use App\Enums\ModalidadeCota;
use App\Enums\NaturezaProponente;
use Database\Factories\EditalDocumentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Documento exigido numa etapa do edital; naturezas nulas = todas, categorias só contam sem todas_categorias. */
#[Fillable([
    'nome', 'orientacao', 'etapa', 'obrigatorio', 'naturezas', 'todas_categorias',
    'modalidade_cota', 'anexo_referencia', 'modelo', 'modelo_nome', 'ordem',
])]
class EditalDocumento extends Model
{
    /** @use HasFactory<EditalDocumentoFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'etapa' => EtapaDocumento::class,
            'obrigatorio' => 'boolean',
            'naturezas' => AsEnumCollection::of(NaturezaProponente::class),
            'todas_categorias' => 'boolean',
            'modalidade_cota' => ModalidadeCota::class,
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
        return $this->belongsToMany(EditalCategoria::class, 'edital_documento_categoria');
    }
}
