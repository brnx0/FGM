<?php

namespace App\Models;

use Database\Factories\EditalQuestionarioSecaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Seção do questionário do edital: agrupa perguntas. */
#[Fillable(['titulo', 'descricao', 'ordem'])]
class EditalQuestionarioSecao extends Model
{
    /** @use HasFactory<EditalQuestionarioSecaoFactory> */
    use HasFactory;

    protected $table = 'edital_questionario_secoes';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['ordem' => 'integer'];
    }

    /** @return BelongsTo<Edital, $this> */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    /** @return HasMany<EditalPergunta, $this> */
    public function perguntas(): HasMany
    {
        return $this->hasMany(EditalPergunta::class, 'edital_questionario_secao_id')->orderBy('ordem')->orderBy('id');
    }
}
