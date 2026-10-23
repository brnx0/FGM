<?php

namespace App\Models;

use App\Enums\SituacaoEdital;
use Carbon\CarbonInterface;
use Database\Factories\EditalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Edital extends Model
{
    /** @use HasFactory<EditalFactory> */
    use HasFactory;

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

    /** "Hoje" no fuso da aplicação (America/Bahia). */
    private static function hoje(?CarbonInterface $agora = null): string
    {
        return ($agora ?? now())->setTimezone(config('app.timezone'))->toDateString();
    }

    public function situacao(?CarbonInterface $agora = null): SituacaoEdital
    {
        $hoje = self::hoje($agora);

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
            ->where('inscricoes_fim', '>=', self::hoje($agora))
            ->orderBy('inscricoes_inicio');
    }

    public static function contarAbertos(?CarbonInterface $agora = null): int
    {
        $hoje = self::hoje($agora);

        return static::query()->publicados()
            ->where('inscricoes_inicio', '<=', $hoje)
            ->where('inscricoes_fim', '>=', $hoje)
            ->count();
    }
}
