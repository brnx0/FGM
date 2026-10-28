<?php

namespace App\Models;

use App\Enums\NaturezaProponente;
use Database\Factories\EditalDocumentoFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Documento exigido na inscrição de um edital. */
class EditalDocumento extends Model
{
    /** @use HasFactory<EditalDocumentoFactory> */
    use HasFactory;

    protected $table = 'edital_documentos';

    protected $fillable = [
        'ordem', 'nome', 'orientacao', 'obrigatorio', 'naturezas', 'todas_categorias', 'tamanho_max_mb',
    ];

    protected function casts(): array
    {
        return [
            'naturezas' => AsEnumCollection::of(NaturezaProponente::class),
            'obrigatorio' => 'boolean',
            'todas_categorias' => 'boolean',
            'tamanho_max_mb' => 'integer',
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

    public function valeParaCategoria(EditalCategoria $categoria): bool
    {
        return $this->todas_categorias || $this->categorias->contains('id', $categoria->id);
    }

    public function valeParaNatureza(NaturezaProponente $natureza): bool
    {
        return $this->naturezas->contains($natureza);
    }
}
