<?php

namespace App\Models;

use Database\Factories\InscricaoRespostaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Resposta da inscrição a uma pergunta do questionário do edital. Textos, data (Y-m-d) e número em texto; escolhas,
 * sim/não e declaração em opcoes (list<string> de chaves); o "Qual?" da opção "Outro" em outro.
 */
#[Fillable(['inscricao_id', 'edital_pergunta_id', 'texto', 'opcoes', 'outro'])]
class InscricaoResposta extends Model
{
    /** @use HasFactory<InscricaoRespostaFactory> */
    use HasFactory;

    protected $table = 'inscricao_respostas';

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return ['opcoes' => 'array'];
    }

    /** @return BelongsTo<Inscricao, $this> */
    public function inscricao(): BelongsTo
    {
        return $this->belongsTo(Inscricao::class);
    }

    /** @return BelongsTo<EditalPergunta, $this> */
    public function pergunta(): BelongsTo
    {
        return $this->belongsTo(EditalPergunta::class, 'edital_pergunta_id');
    }

    /**
     * O valor da resposta no formato que o servidor e a tela usam.
     *
     * @return array{texto: ?string, opcoes: list<string>, outro: ?string}
     */
    public function valor(): array
    {
        return [
            'texto' => $this->texto,
            'opcoes' => array_values(array_map('strval', $this->opcoes ?? [])),
            'outro' => $this->outro,
        ];
    }
}
