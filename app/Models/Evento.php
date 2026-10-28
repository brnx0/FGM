<?php

namespace App\Models;

use App\Models\Concerns\UsaDiaCivil;
use App\Support\HtmlSeguro;
use Carbon\CarbonInterface;
use Database\Factories\EventoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Evento extends Model
{
    /** @use HasFactory<EventoFactory> */
    use HasFactory;

    use SoftDeletes;
    use UsaDiaCivil;

    protected $table = 'eventos';

    protected $fillable = [
        'titulo', 'slug', 'resumo', 'descricao', 'data_realizacao', 'hora_inicio', 'hora_fim',
        'local', 'endereco', 'latitude', 'longitude', 'imagem', 'publicado',
    ];

    protected function casts(): array
    {
        return [
            'data_realizacao' => 'date',
            'publicado' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
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

    /** Toda gravação passa pela allowlist (HtmlSeguro é idempotente: reler e regravar não muda nada). */
    protected function descricao(): Attribute
    {
        return Attribute::set(fn (?string $html) => HtmlSeguro::limpar($html));
    }

    public function imagemUrl(): ?string
    {
        return $this->imagem ? Storage::disk('public')->url($this->imagem) : null;
    }

    /** "19h às 22h30", "A partir das 19h" ou nulo quando não há horário. */
    public function horarioPorExtenso(): ?string
    {
        if ($this->hora_inicio === null) {
            return null;
        }

        $hora = fn (string $hhmm): string => (int) substr($hhmm, 0, 2).'h'.(substr($hhmm, 3, 2) === '00' ? '' : substr($hhmm, 3, 2));

        return $this->hora_fim === null
            ? 'A partir das '.$hora($this->hora_inicio)
            : $hora($this->hora_inicio).' às '.$hora($this->hora_fim);
    }

    /** Pin marcado no mapa pela retaguarda. */
    public function temLocalizacao(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Agenda do portal: publicado e de hoje em diante, do mais próximo ao mais distante. */
    public function scopeProximos(Builder $query, ?CarbonInterface $agora = null): void
    {
        $query->where('publicado', true)
            ->where('data_realizacao', '>=', self::hoje($agora)->toDateString())
            ->orderBy('data_realizacao');
    }
}
