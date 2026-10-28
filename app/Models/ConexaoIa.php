<?php

namespace App\Models;

use App\Enums\ProvedorIa;
use Database\Factories\ConexaoIaFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Chave da API e modelo de um provedor de IA (Retaguarda > Parametrização > Integração com IA). Uma linha por provedor,
 * criada na primeira gravação. A chave fica cifrada com a APP_KEY e nunca vai para o navegador: lá só chega a máscara.
 */
#[Fillable(['provedor', 'modelo', 'chave_api'])]
#[Hidden(['chave_api'])]
class ConexaoIa extends Model
{
    /** @use HasFactory<ConexaoIaFactory> */
    use HasFactory;

    protected $table = 'conexoes_ia';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provedor' => ProvedorIa::class,
            'chave_api' => 'encrypted',
        ];
    }

    public static function doProvedor(?ProvedorIa $provedor): ?self
    {
        return $provedor === null ? null : self::query()->where('provedor', $provedor)->first();
    }

    /** Grava o modelo do provedor; a chave só muda quando vem uma nova (em branco mantém a guardada). */
    public static function salvar(ProvedorIa $provedor, string $modelo, #[SensitiveParameter] ?string $chave): self
    {
        $conexao = self::query()->firstOrNew(['provedor' => $provedor]);
        $conexao->modelo = $modelo;

        if ($chave !== null) {
            $conexao->chave_api = $chave;
        }

        $conexao->save();

        return $conexao;
    }

    /**
     * A chave decifrada, ou null sem chave. Também null quando ela não decifra (APP_KEY trocada sem APP_PREVIOUS_KEYS):
     * a tela trata o provedor como sem chave e pede outra, em vez de quebrar.
     */
    public function chaveGuardada(): ?string
    {
        try {
            return $this->chave_api;
        } catch (DecryptException) {
            return null;
        }
    }

    /** Começo e fim da chave guardada (ex.: "sk-…H2UA"), para o gestor reconhecer qual é. */
    public function chaveMascarada(): ?string
    {
        $chave = $this->chaveGuardada();

        return $chave === null ? null : Str::substr($chave, 0, 3).'…'.Str::substr($chave, -4);
    }
}
