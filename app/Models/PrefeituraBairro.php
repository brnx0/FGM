<?php

namespace App\Models;

use Database\Factories\PrefeituraBairroFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Prefeitura-Bairro de Salvador, cadastrada na retaguarda (Parametrização). As ativas são as opções de território
 * no cadastro do proponente, que guarda o código em pessoas_fisicas.territorio.
 */
class PrefeituraBairro extends Model
{
    /** @use HasFactory<PrefeituraBairroFactory> */
    use HasFactory;

    /** Território de quem mora fora da cidade; não é uma Prefeitura-Bairro. */
    public const string FORA_DE_SALVADOR = 'fora_de_salvador';

    private const string ROTULO_FORA_DE_SALVADOR = 'Fora de Salvador';

    protected $table = 'prefeituras_bairro';

    protected $fillable = ['numero', 'nome', 'codigo', 'ativo'];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /** @return HasMany<Bairro, $this> */
    public function bairros(): HasMany
    {
        return $this->hasMany(Bairro::class);
    }

    /** Código de uma Prefeitura-Bairro nova, tirado do nome; nunca repete um existente nem "fora_de_salvador". */
    public static function codigoLivrePara(string $nome): string
    {
        $base = mb_substr(Str::slug($nome, '_'), 0, 26) ?: 'prefeitura';
        $codigo = $base;

        for ($sufixo = 2; $codigo === self::FORA_DE_SALVADOR || self::where('codigo', $codigo)->exists(); $sufixo++) {
            $codigo = "{$base}_{$sufixo}";
        }

        return $codigo;
    }

    /** Número em algarismos romanos, como no nome oficial ("Prefeitura-Bairro I"). */
    public function numeroRomano(): string
    {
        $romano = '';
        $resto = $this->numero;
        foreach (['L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1] as $algarismo => $valor) {
            $romano .= str_repeat($algarismo, intdiv($resto, $valor));
            $resto %= $valor;
        }

        return $romano;
    }

    /**
     * Opções de território do cadastro do proponente: as ativas, a já escolhida (mesmo desativada depois) e "Fora de Salvador".
     *
     * @return list<array{valor: string, rotulo: string}>
     */
    public static function opcoesDoPortal(?string $atual = null): array
    {
        return self::query()
            ->where(fn (Builder $q) => $q->where('ativo', true)->when($atual !== null, fn (Builder $q) => $q->orWhere('codigo', $atual)))
            ->orderBy('numero')
            ->get(['codigo', 'nome'])
            ->map(fn (self $prefeitura): array => ['valor' => $prefeitura->codigo, 'rotulo' => $prefeitura->nome])
            ->push(['valor' => self::FORA_DE_SALVADOR, 'rotulo' => self::ROTULO_FORA_DE_SALVADOR])
            ->all();
    }

    /** @return list<string> */
    public static function codigosAceitos(?string $atual = null): array
    {
        return array_column(self::opcoesDoPortal($atual), 'valor');
    }

    public static function rotuloDoCodigo(?string $codigo): ?string
    {
        return match ($codigo) {
            null => null,
            self::FORA_DE_SALVADOR => self::ROTULO_FORA_DE_SALVADOR,
            default => self::where('codigo', $codigo)->value('nome') ?? $codigo,
        };
    }
}
