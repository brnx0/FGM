<?php

namespace App\Models;

use App\Models\Concerns\UsaDiaCivil;
use Carbon\CarbonInterface;
use Database\Factories\EventoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evento extends Model
{
    /** @use HasFactory<EventoFactory> */
    use HasFactory;

    use UsaDiaCivil;

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

    protected function dataRealizacao(): Attribute
    {
        return Attribute::set(fn ($v) => self::diaParaGravar($v));
    }

    public function scopeRealizados(Builder $query, ?CarbonInterface $agora = null): void
    {
        $query->where('publicado', true)
            ->where('data_realizacao', '<', self::hoje($agora)->addDay()->toDateString())
            ->orderByDesc('data_realizacao');
    }
}
