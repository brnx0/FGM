<?php

namespace App\Models;

use App\Enums\NaturezaProponente;
use App\Enums\SituacaoEdital;
use App\Enums\TipoEdital;
use App\Models\Concerns\UsaDiaCivil;
use Carbon\CarbonInterface;
use Database\Factories\EditalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Edital da vitrine. Rascunho pode ficar sem título, categoria, resumo e datas; publicar exige pendenciasParaPublicar() vazia.
 * As datas de inscrição do edital são as que a vitrine, a situação e os filtros usam.
 */
class Edital extends Model
{
    /** @use HasFactory<EditalFactory> */
    use HasFactory;

    use SoftDeletes;
    use UsaDiaCivil;

    /** Seções de texto informativas (coluna `informacoes`), na ordem de exibição: chave => rótulo. */
    public const array SECOES_INFORMATIVAS = [
        'recursos_pagamento' => 'Recursos e pagamento',
        'vedacoes' => 'Vedações',
        'etapas_prazos' => 'Etapas e prazos',
        'contrapartida_execucao' => 'Contrapartida e execução',
        'prestacao_contas' => 'Prestação de contas',
        'base_legal' => 'Base legal',
        'anexos' => 'Anexos',
        'contatos' => 'Contatos',
        'outras_regras' => 'Outras regras',
    ];

    protected $table = 'editais';

    protected $fillable = [
        'titulo', 'slug', 'resumo', 'categoria',
        'inscricoes_inicio', 'inscricoes_fim', 'imagem', 'pdf', 'publicado',
        'numero', 'tipo', 'objeto', 'dom_data', 'dom_numero', 'naturezas', 'aceita_coletivos',
        'inscricoes_por_proponente', 'limite_por_chamada', 'cotas', 'nota_maxima', 'nota_corte',
        'pontuacao_extra', 'informacoes', 'forma_inscricao_original',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'inscricoes_inicio' => 'date',
            'inscricoes_fim' => 'date',
            'publicado' => 'boolean',
            'tipo' => TipoEdital::class,
            'dom_data' => 'date',
            'naturezas' => AsEnumCollection::of(NaturezaProponente::class),
            'aceita_coletivos' => 'boolean',
            'inscricoes_por_proponente' => 'integer',
            'limite_por_chamada' => 'boolean',
            'cotas' => 'array',
            'nota_maxima' => 'integer',
            'nota_corte' => 'decimal:2',
            'pontuacao_extra' => 'array',
            'informacoes' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function inscricoesInicio(): Attribute
    {
        return Attribute::set(fn ($v) => self::diaParaGravar($v));
    }

    protected function inscricoesFim(): Attribute
    {
        return Attribute::set(fn ($v) => self::diaParaGravar($v));
    }

    protected function domData(): Attribute
    {
        return Attribute::set(fn ($v) => self::diaParaGravar($v));
    }

    /** @return HasMany<EditalChamada, $this> */
    public function chamadas(): HasMany
    {
        return $this->hasMany(EditalChamada::class)->orderBy('ordem')->orderBy('id');
    }

    /** @return HasMany<EditalCategoria, $this> */
    public function categorias(): HasMany
    {
        return $this->hasMany(EditalCategoria::class)->orderBy('ordem')->orderBy('id');
    }

    /** @return HasMany<EditalDocumento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(EditalDocumento::class)->orderBy('ordem')->orderBy('id');
    }

    /** @return HasMany<EditalRequisito, $this> */
    public function requisitos(): HasMany
    {
        return $this->hasMany(EditalRequisito::class)->orderBy('ordem')->orderBy('id');
    }

    /** @return HasMany<EditalCriterio, $this> */
    public function criterios(): HasMany
    {
        return $this->hasMany(EditalCriterio::class)->orderBy('ordem')->orderBy('id');
    }

    /** Nula enquanto o edital não tem as duas datas de inscrição (rascunho). */
    public function situacao(?CarbonInterface $agora = null): ?SituacaoEdital
    {
        if (! $this->temPeriodo()) {
            return null;
        }

        $hoje = self::hoje($agora)->toDateString();

        return match (true) {
            $hoje < $this->inscricoes_inicio->toDateString() => SituacaoEdital::EmBreve,
            $hoje > $this->inscricoes_fim->toDateString() => SituacaoEdital::Encerrado,
            default => SituacaoEdital::Aberto,
        };
    }

    public function temPeriodo(): bool
    {
        return $this->inscricoes_inicio !== null && $this->inscricoes_fim !== null;
    }

    /**
     * O que falta para o edital poder ser publicado; vazia quando está completo.
     *
     * @return list<string>
     */
    public function pendenciasParaPublicar(): array
    {
        $pendencias = [];

        if (blank($this->titulo)) {
            $pendencias[] = 'Informe o título do edital.';
        }
        if (blank($this->pdf)) {
            $pendencias[] = 'Envie o PDF do edital.';
        }
        if (! $this->chamadas()->whereNotNull('inicio')->whereNotNull('fim')->exists()) {
            $pendencias[] = 'Cadastre ao menos uma chamada com início e fim das inscrições.';
        }
        if (blank($this->naturezas)) {
            $pendencias[] = 'Marque ao menos uma natureza que pode se inscrever.';
        }
        if (! $this->categorias()->exists()) {
            $pendencias[] = 'Cadastre ao menos uma categoria.';
        }

        return $pendencias;
    }

    /** URL da rota que entrega o PDF; nula enquanto o edital não tem arquivo. */
    public function pdfUrl(): ?string
    {
        return $this->pdf ? route('editais.pdf', $this) : null;
    }

    public function imagemUrl(): ?string
    {
        return $this->imagem ? Storage::disk('public')->url($this->imagem) : null;
    }

    public function scopePublicados(Builder $query): void
    {
        $query->where('publicado', true);
    }

    /** Visível no portal: publicado, com PDF e com as datas de inscrição. */
    public function scopeVisiveis(Builder $query): void
    {
        $query->publicados()
            ->whereNotNull('pdf')
            ->whereNotNull('inscricoes_inicio')
            ->whereNotNull('inscricoes_fim');
    }

    public function estaVisivel(): bool
    {
        return $this->publicado && filled($this->pdf) && $this->temPeriodo();
    }

    /** Sem as colunas CLOB (resumo, cotas, pontuação extra, informações): o que listagens e cards precisam. */
    public function scopeParaListagem(Builder $query): void
    {
        $query->select([
            'id', 'titulo', 'slug', 'categoria', 'inscricoes_inicio', 'inscricoes_fim', 'imagem', 'pdf', 'publicado',
        ]);
    }

    public function scopeNaVitrine(Builder $query, ?CarbonInterface $agora = null): void
    {
        $query->visiveis()
            ->where('inscricoes_fim', '>=', self::hoje($agora)->toDateString())
            ->orderBy('inscricoes_inicio');
    }

    /** Mesma regra de situacao(), em consulta: dias em intervalo semiaberto (ver UsaDiaCivil). Sem datas não entra em nenhuma (comparação com nulo é falsa). */
    public function scopeNaSituacao(Builder $query, SituacaoEdital $situacao, ?CarbonInterface $agora = null): void
    {
        $hoje = self::hoje($agora);
        $amanha = $hoje->addDay()->toDateString();

        match ($situacao) {
            SituacaoEdital::EmBreve => $query->where('inscricoes_inicio', '>=', $amanha),
            SituacaoEdital::Encerrado => $query->where('inscricoes_fim', '<', $hoje->toDateString()),
            SituacaoEdital::Aberto => $query
                ->where('inscricoes_inicio', '<', $amanha)
                ->where('inscricoes_fim', '>=', $hoje->toDateString()),
        };
    }

    public static function contarAbertos(?CarbonInterface $agora = null): int
    {
        return static::query()->visiveis()->naSituacao(SituacaoEdital::Aberto, $agora)->count();
    }
}
