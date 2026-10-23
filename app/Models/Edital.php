<?php

namespace App\Models;

use App\Enums\SituacaoEdital;
use App\Models\Concerns\UsaDiaCivil;
use Carbon\CarbonInterface;
use Database\Factories\EditalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Edital extends Model
{
    /** @use HasFactory<EditalFactory> */
    use HasFactory;

    use UsaDiaCivil;

    protected $table = 'editais';

    protected $fillable = [
        'titulo', 'slug', 'resumo', 'categoria',
        'inscricoes_inicio', 'inscricoes_fim', 'imagem', 'publicado',
    ];

    protected function casts(): array
    {
        return [
            'inscricoes_inicio' => 'date',
            'inscricoes_fim' => 'date',
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

    public function situacao(?CarbonInterface $agora = null): SituacaoEdital
    {
        $hoje = self::hoje($agora)->toDateString();

        return match (true) {
            $hoje < $this->inscricoes_inicio->toDateString() => SituacaoEdital::EmBreve,
            $hoje > $this->inscricoes_fim->toDateString() => SituacaoEdital::Encerrado,
            default => SituacaoEdital::Aberto,
        };
    }

    public function scopePublicados(Builder $query): void
    {
        $query->where('publicado', true);
    }

    public function scopeNaVitrine(Builder $query, ?CarbonInterface $agora = null): void
    {
        $query->publicados()
            ->where('inscricoes_fim', '>=', self::hoje($agora)->toDateString())
            ->orderBy('inscricoes_inicio');
    }

    public static function contarAbertos(?CarbonInterface $agora = null): int
    {
        $hoje = self::hoje($agora);

        return static::query()->publicados()
            ->where('inscricoes_inicio', '<', $hoje->addDay()->toDateString())
            ->where('inscricoes_fim', '>=', $hoje->toDateString())
            ->count();
    }
}
