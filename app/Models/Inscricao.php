<?php

namespace App\Models;

use App\Enums\ChecagemDocumento;
use App\Enums\ModalidadeCota;
use App\Enums\NaturezaProponente;
use App\Enums\SituacaoConferencia;
use App\Enums\SituacaoEdital;
use App\Enums\SituacaoInscricao;
use App\Support\Inscricao\RegrasDaInscricao;
use Carbon\CarbonInterface;
use Database\Factories\InscricaoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Inscrição de um proponente (CPF ou CNPJ, só dígitos em documento_cadastro) num edital.
 * cotas: lista de ModalidadeCota escolhidas na inscrição.
 */
class Inscricao extends Model
{
    /** @use HasFactory<InscricaoFactory> */
    use HasFactory;

    /** Disco e pasta dos documentos enviados. */
    public const string DISCO = 'local';

    public const string PASTA = 'inscricoes';

    /** Tipos aceitos nos documentos enviados: PDF ou imagem, que a IA confere do mesmo jeito. */
    public const array TIPOS_DE_ARQUIVO = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $table = 'inscricoes';

    protected $fillable = [
        'edital_id', 'edital_chamada_id', 'user_id', 'organizacao_id', 'documento_cadastro', 'natureza',
        'edital_categoria_id', 'cotas', 'situacao', 'numero', 'finalizada_em',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'natureza' => NaturezaProponente::class,
            'cotas' => AsEnumCollection::of(ModalidadeCota::class),
            'situacao' => SituacaoInscricao::class,
            'finalizada_em' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organizacao, $this> */
    public function organizacao(): BelongsTo
    {
        return $this->belongsTo(Organizacao::class);
    }

    /** @return BelongsTo<EditalCategoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(EditalCategoria::class, 'edital_categoria_id');
    }

    /** @return HasMany<InscricaoDocumento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(InscricaoDocumento::class);
    }

    /**
     * Documentos do edital que esta inscrição precisa enviar (de todas as etapas), na ordem do edital:
     * os da natureza do cadastro, da categoria escolhida e das cotas marcadas.
     *
     * @return Collection<int, EditalDocumento>
     */
    public function documentosExigidos(): Collection
    {
        $categoriaId = $this->edital_categoria_id;
        $cotas = $this->cotas?->map(fn (ModalidadeCota $cota): string => $cota->value)->all() ?? [];

        return $this->edital->documentos()
            ->where(function (Builder $query) use ($categoriaId): void {
                $query->where('todas_categorias', true);
                if ($categoriaId !== null) {
                    $query->orWhereHas('categorias', fn (Builder $categorias) => $categorias->whereKey($categoriaId));
                }
            })
            ->where(fn (Builder $query) => $query->whereNull('modalidade_cota')->orWhereIn('modalidade_cota', $cotas))
            ->get()
            ->filter(fn (EditalDocumento $documento): bool => RegrasDaInscricao::aceitaNatureza($documento->naturezas, $this->natureza))
            ->values();
    }

    /**
     * O que impede de finalizar, na ordem dos documentos do edital: obrigatório sem arquivo, documento ainda sendo
     * conferido (a conferência parada há mais de 5 minutos já conta como não conferida) e documento em "Rigorosa"
     * (obrigatório ou opcional anexado) que não foi aprovado. Reprovado ou não conferido em "Aceita sem validar" não impede,
     * nem "Rigorosa" com a validação por IA desligada (o documento segue como enviado).
     *
     * @return list<string>
     */
    public function pendenciasParaFinalizar(?CarbonInterface $agora = null): array
    {
        $enviados = $this->documentos()->get()->keyBy(fn (InscricaoDocumento $enviado): int => (int) $enviado->edital_documento_id);
        $pendencias = [];
        $validacaoLigada = ConfiguracaoIa::validacaoLigada();

        foreach ($this->documentosExigidos() as $exigido) {
            $situacao = $enviados->get((int) $exigido->id)?->situacaoExibida($agora);

            if ($situacao === null) {
                if ($exigido->obrigatorio) {
                    $pendencias[] = "Anexe \"{$exigido->nome}\".";
                }
            } elseif ($situacao === SituacaoConferencia::Conferindo) {
                $pendencias[] = "Aguarde a conferência de \"{$exigido->nome}\".";
            } elseif ($exigido->checagemNaInscricao($validacaoLigada) === ChecagemDocumento::Rigorosa && $situacao !== SituacaoConferencia::Aprovado) {
                $pendencias[] = "\"{$exigido->nome}\" precisa ser aprovado na conferência.";
            }
        }

        return $pendencias;
    }

    /** Edital no portal e chamada da inscrição aberta (dias da chamada, ver UsaDiaCivil). */
    public function noPeriodo(?CarbonInterface $agora = null): bool
    {
        return $this->edital->estaVisivel() && $this->chamada?->situacao($agora) === SituacaoEdital::Aberto;
    }
}
