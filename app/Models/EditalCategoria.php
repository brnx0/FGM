<?php

namespace App\Models;

use App\Enums\NaturezaProponente;
use App\Enums\UnidadeCota;
use Database\Factories\EditalCategoriaFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Categoria de inscrição de um edital. */
class EditalCategoria extends Model
{
    /** @use HasFactory<EditalCategoriaFactory> */
    use HasFactory;

    public const array COTAS = ['negros', 'indigenas', 'pcd'];

    protected $table = 'edital_categorias';

    protected $fillable = [
        'ordem', 'nome', 'valor_centavos', 'vagas', 'naturezas',
        'cota_negros_valor', 'cota_negros_unidade',
        'cota_indigenas_valor', 'cota_indigenas_unidade',
        'cota_pcd_valor', 'cota_pcd_unidade',
    ];

    protected function casts(): array
    {
        return [
            'naturezas' => AsEnumCollection::of(NaturezaProponente::class),
            'valor_centavos' => 'integer',
            'vagas' => 'integer',
            'cota_negros_unidade' => UnidadeCota::class,
            'cota_indigenas_unidade' => UnidadeCota::class,
            'cota_pcd_unidade' => UnidadeCota::class,
        ];
    }

    /** @return BelongsTo<Edital, $this> */
    public function edital(): BelongsTo
    {
        return $this->belongsTo(Edital::class);
    }

    /** Vagas reservadas para a cota (negros, indigenas ou pcd); percentual arredonda para cima. */
    public function vagasReservadas(string $cota): int
    {
        $valor = $this->{"cota_{$cota}_valor"};
        $unidade = $this->{"cota_{$cota}_unidade"};

        if ($valor === null || $unidade === null) {
            return 0;
        }

        return match ($unidade) {
            UnidadeCota::Vagas => (int) $valor,
            UnidadeCota::Percentual => (int) ceil($this->vagas * $valor / 100),
        };
    }

    public function totalReservado(): int
    {
        return array_sum(array_map(fn (string $cota): int => $this->vagasReservadas($cota), self::COTAS));
    }
}
