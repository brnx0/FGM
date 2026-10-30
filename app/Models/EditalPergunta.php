<?php

namespace App\Models;

use App\Enums\NaturezaProponente;
use App\Enums\TipoPergunta;
use App\Support\Inscricao\RegrasDaInscricao;
use Database\Factories\EditalPerguntaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Pergunta do questionário do edital. Naturezas nulas = todas; categorias só contam sem todas_categorias.
 * Pergunta sem categoria nenhuma e sem todas_categorias é pendência do edital (não aparece a ninguém).
 * opcoes: list<array{chave: string, texto: string, outro: bool}>; condicao_opcoes: list<string> de chaves da origem.
 */
#[Fillable([
    'edital_questionario_secao_id', 'enunciado', 'ajuda', 'tipo', 'obrigatoria', 'limite_caracteres', 'opcoes',
    'todas_categorias', 'naturezas', 'condicao_pergunta_id', 'condicao_opcoes', 'ordem',
])]
class EditalPergunta extends Model
{
    /** @use HasFactory<EditalPerguntaFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoPergunta::class,
            'obrigatoria' => 'boolean',
            'limite_caracteres' => 'integer',
            'opcoes' => 'array',
            'todas_categorias' => 'boolean',
            'naturezas' => AsEnumCollection::of(NaturezaProponente::class),
            'condicao_opcoes' => 'array',
            'ordem' => 'integer',
        ];
    }

    /** @return BelongsTo<Edital, $this> */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    /** @return BelongsTo<EditalQuestionarioSecao, $this> */
    public function secao(): BelongsTo
    {
        return $this->belongsTo(EditalQuestionarioSecao::class, 'edital_questionario_secao_id');
    }

    /** @return BelongsToMany<EditalCategoria, $this> */
    public function categorias(): BelongsToMany
    {
        return $this->belongsToMany(EditalCategoria::class, 'edital_pergunta_categoria');
    }

    /** @return BelongsTo<EditalPergunta, $this> */
    public function condicao(): BelongsTo
    {
        return $this->belongsTo(self::class, 'condicao_pergunta_id');
    }

    /** Sem "todas as categorias" e sem nenhuma categoria ligada: a pergunta não aparece a ninguém. Usa as categorias carregadas, se houver. */
    public function semVinculo(): bool
    {
        if ($this->todas_categorias) {
            return false;
        }

        return $this->relationLoaded('categorias') ? $this->categorias->isEmpty() : ! $this->categorias()->exists();
    }

    public function valePara(?int $categoriaId, NaturezaProponente $natureza): bool
    {
        if ($this->semVinculo()) {
            return false;
        }

        $categoriaServe = $this->todas_categorias
            || ($categoriaId !== null && ($this->relationLoaded('categorias')
                ? $this->categorias->contains(fn (EditalCategoria $categoria): bool => (int) $categoria->id === $categoriaId)
                : $this->categorias()->whereKey($categoriaId)->exists()));

        return $categoriaServe && RegrasDaInscricao::aceitaNatureza($this->naturezas, $natureza);
    }

    /** @return list<string> */
    public function chavesDasOpcoes(): array
    {
        return match ($this->tipo) {
            TipoPergunta::SimNao => [TipoPergunta::CHAVE_SIM, TipoPergunta::CHAVE_NAO],
            TipoPergunta::Declaracao => [TipoPergunta::CHAVE_DECLARACAO],
            TipoPergunta::EscolhaUnica, TipoPergunta::MultiplaEscolha => array_values(array_map(
                fn (array $opcao): string => (string) $opcao['chave'],
                $this->opcoes ?? [],
            )),
            default => [],
        };
    }

    public function limiteDeCaracteres(): ?int
    {
        return $this->limite_caracteres ?? $this->tipo->limitePadrao();
    }
}
