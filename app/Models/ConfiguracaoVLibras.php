<?php

namespace App\Models;

use App\Enums\AvatarVLibras;
use App\Enums\LadoVLibras;
use Database\Factories\ConfiguracaoVLibrasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Parâmetros do VLibras fora da retaguarda (Retaguarda > Portal > VLibras). Uma linha só, criada na primeira gravação. */
#[Fillable(['ativo', 'lado', 'avatar'])]
class ConfiguracaoVLibras extends Model
{
    /** @use HasFactory<ConfiguracaoVLibrasFactory> */
    use HasFactory;

    private const string CHAVE_CACHE = 'configuracao-vlibras';

    protected $table = 'configuracoes_vlibras';

    /**
     * Mesmos padrões do banco, para valerem antes da primeira gravação (ver atual()).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ativo' => true,
        'lado' => 'R',
        'avatar' => 'icaro',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'lado' => LadoVLibras::class,
            'avatar' => AvatarVLibras::class,
        ];
    }

    /**
     * A configuração salva ou, enquanto ninguém salvou, uma nova com os padrões. Fica em cache
     * (lida a cada página do portal, muda raramente) e o cache é limpo quando ela é salva ou apagada.
     *
     * O cache guarda só as colunas da linha ([] quando não há linha), não o model: com
     * cache.serializable_classes = false nenhum objeto volta do cache (viraria __PHP_Incomplete_Class).
     */
    public static function atual(): self
    {
        /** @var array<string, mixed> $colunas */
        $colunas = Cache::rememberForever(
            self::CHAVE_CACHE,
            fn (): array => self::query()->orderBy('id')->first()?->getAttributes() ?? [],
        );

        return $colunas === [] ? new self : (new self)->newFromBuilder($colunas);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CHAVE_CACHE));
        static::deleted(fn () => Cache::forget(self::CHAVE_CACHE));
    }
}
