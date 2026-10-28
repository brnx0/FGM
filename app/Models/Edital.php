<?php

namespace App\Models;

use App\Enums\SituacaoEdital;
use App\Enums\TipoFomento;
use App\Models\Concerns\UsaDiaCivil;
use App\Support\HtmlSeguro;
use Carbon\CarbonInterface;
use Database\Factories\EditalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Edital extends Model
{
    /** @use HasFactory<EditalFactory> */
    use HasFactory;

    use SoftDeletes;
    use UsaDiaCivil;

    protected $table = 'editais';

    protected $fillable = [
        'titulo', 'slug', 'categoria', 'tipo_fomento', 'divulgacao',
        'inscricoes_inicio', 'inscricoes_fim', 'vigencia_inicio', 'vigencia_fim',
        'imagem', 'pdf', 'publicado',
    ];

    protected function casts(): array
    {
        return [
            'inscricoes_inicio' => 'date',
            'inscricoes_fim' => 'date',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'tipo_fomento' => TipoFomento::class,
            'publicado' => 'boolean',
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

    protected function vigenciaInicio(): Attribute
    {
        return Attribute::set(fn ($v) => self::diaParaGravar($v));
    }

    protected function vigenciaFim(): Attribute
    {
        return Attribute::set(fn ($v) => self::diaParaGravar($v));
    }

    /** Toda gravação passa pela allowlist (HtmlSeguro é idempotente). */
    protected function divulgacao(): Attribute
    {
        return Attribute::set(fn (?string $html) => HtmlSeguro::limpar($html));
    }

    public function situacao(?CarbonInterface $agora = null): SituacaoEdital
    {
        $hoje = self::hoje($agora)->toDateString();

        return match (true) {
            $hoje < $this->inscricoes_inicio->toDateString() => SituacaoEdital::EmBreve,
            $hoje > $this->inscricoes_fim->toDateString() => SituacaoEdital::Encerrado,
            default => SituacaoEdital::Aberto,
        };
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

    /** Visível no portal: publicado e com PDF. */
    public function scopeVisiveis(Builder $query): void
    {
        $query->publicados()->whereNotNull('pdf');
    }

    public function estaVisivel(): bool
    {
        return $this->publicado && filled($this->pdf);
    }

    public function scopeNaVitrine(Builder $query, ?CarbonInterface $agora = null): void
    {
        $query->visiveis()
            ->where('inscricoes_fim', '>=', self::hoje($agora)->toDateString())
            ->orderBy('inscricoes_inicio');
    }

    /** Mesma regra de situacao(), em consulta: dias em intervalo semiaberto (ver UsaDiaCivil). */
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
