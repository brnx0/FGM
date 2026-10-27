<?php

namespace App\Models;

use App\Enums\AvatarVLibras;
use App\Enums\LadoVLibras;
use Database\Factories\ConfiguracaoVLibrasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Parâmetros do VLibras fora da retaguarda (Retaguarda > Portal > VLibras). Uma linha só, criada na primeira gravação. */
#[Fillable(['ativo', 'lado', 'avatar'])]
class ConfiguracaoVLibras extends Model
{
    /** @use HasFactory<ConfiguracaoVLibrasFactory> */
    use HasFactory;

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

    /** A configuração salva ou, enquanto ninguém salvou, uma nova com os padrões. */
    public static function atual(): self
    {
        return self::query()->orderBy('id')->first() ?? new self;
    }
}
