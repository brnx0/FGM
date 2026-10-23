<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\EventoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    /** @use HasFactory<EventoFactory> */
    use HasFactory;

    protected $table = 'eventos';

    protected $fillable = [
        'titulo', 'slug', 'resumo', 'data_realizacao', 'local', 'imagem', 'publicado',
    ];

    protected function casts(): array
    {
        return [
            'data_realizacao' => 'date',
            'publicado' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeRealizados(Builder $query, ?CarbonInterface $agora = null): void
    {
        $hoje = ($agora ?? now())->setTimezone(config('app.timezone'))->toDateString();

        $query->where('publicado', true)
            ->where('data_realizacao', '<=', $hoje)
            ->orderByDesc('data_realizacao');
    }
}
