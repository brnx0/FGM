<?php

namespace App\Models;

use Database\Factories\EditalRequisitoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Condição que a inscrição precisa cumprir para ser homologada, com os documentos que a comprovam. */
#[Fillable(['descricao', 'ordem'])]
class EditalRequisito extends Model
{
    /** @use HasFactory<EditalRequisitoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    /** @return BelongsTo<Edital, $this> */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    /** @return BelongsToMany<EditalDocumento, $this> */
    public function documentos(): BelongsToMany
    {
        return $this->belongsToMany(EditalDocumento::class, 'edital_requisito_documento');
    }
}
