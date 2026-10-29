<?php

namespace App\Models;

use App\Enums\SituacaoEdital;
use App\Models\Concerns\UsaDiaCivil;
use Carbon\CarbonInterface;
use Database\Factories\EditalChamadaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Período de inscrição do edital; o edital comum tem uma, alguns têm várias. */
#[Fillable(['nome', 'inicio', 'fim', 'ordem'])]
class EditalChamada extends Model
{
    /** @use HasFactory<EditalChamadaFactory> */
    use HasFactory;

    use UsaDiaCivil;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
            'ordem' => 'integer',
        ];
    }

    protected function inicio(): Attribute
    {
        return Attribute::set(fn ($v) => self::diaParaGravar($v));
    }

    protected function fim(): Attribute
    {
        return Attribute::set(fn ($v) => self::diaParaGravar($v));
    }

    /** Mesma regra do dia civil de Edital::situacao(); nula sem as duas datas. */
    public function situacao(?CarbonInterface $agora = null): ?SituacaoEdital
    {
        if ($this->inicio === null || $this->fim === null) {
            return null;
        }

        $hoje = self::hoje($agora)->toDateString();

        return match (true) {
            $hoje < $this->inicio->toDateString() => SituacaoEdital::EmBreve,
            $hoje > $this->fim->toDateString() => SituacaoEdital::Encerrado,
            default => SituacaoEdital::Aberto,
        };
    }

    /** @return BelongsTo<Edital, $this> */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }
}
