<?php

namespace App\Models;

use App\Enums\ProvedorIa;
use Database\Factories\ConfiguracaoIaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uso da IA nas validações automáticas (Retaguarda > Parametrização > Integração com IA): se está ligada, o provedor
 * principal e o de redundância, que só recebe a chamada que o principal não atender. Uma linha só, criada na primeira gravação.
 */
#[Fillable(['ativo', 'provedor_principal', 'provedor_redundancia'])]
class ConfiguracaoIa extends Model
{
    /** @use HasFactory<ConfiguracaoIaFactory> */
    use HasFactory;

    protected $table = 'configuracoes_ia';

    /**
     * Mesmo padrão do banco, para valer antes da primeira gravação (ver atual()).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ativo' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'provedor_principal' => ProvedorIa::class,
            'provedor_redundancia' => ProvedorIa::class,
        ];
    }

    /**
     * A configuração salva ou, enquanto ninguém salvou, uma nova com os padrões. Sem cache, ao contrário da do VLibras:
     * é lida só na retaguarda (e, mais adiante, a cada validação), não a cada página do portal.
     */
    public static function atual(): self
    {
        return self::query()->orderBy('id')->first() ?? new self;
    }
}
