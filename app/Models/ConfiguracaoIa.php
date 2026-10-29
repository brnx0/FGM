<?php

namespace App\Models;

use App\Enums\ProvedorIa;
use App\Support\EditalIa\PromptModelo;
use Database\Factories\ConfiguracaoIaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Uso da IA nas validações automáticas (Retaguarda > Parametrização > Integração com IA): se está ligada, o provedor
 * principal e o de redundância, que só recebe a chamada que o principal não atender. Uma linha só, criada na primeira gravação.
 *
 * Guarda também os parâmetros da importação de edital por IA (prompt, temperatura e tamanho máximo do PDF), que usa os
 * mesmos provedores, mas não depende de "ativo": a importação fica sempre disponível.
 */
#[Fillable(['ativo', 'provedor_principal', 'provedor_redundancia', 'importacao_prompt', 'importacao_temperatura', 'importacao_tamanho_maximo_mb'])]
class ConfiguracaoIa extends Model
{
    /** @use HasFactory<ConfiguracaoIaFactory> */
    use HasFactory;

    /**
     * Teto do PDF da importação: no Gemini, o pedido vai inteiro no corpo (base64, cerca de 4/3 do arquivo) e o limite
     * dele é de uns 20 MB. Um valor salvo maior vale este teto.
     */
    public const int TAMANHO_MAXIMO_PDF_MB = 14;

    protected $table = 'configuracoes_ia';

    /**
     * Mesmo padrão do banco, para valer antes da primeira gravação (ver atual()).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ativo' => false,
        'importacao_temperatura' => 0.10,
        'importacao_tamanho_maximo_mb' => self::TAMANHO_MAXIMO_PDF_MB,
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
            'importacao_temperatura' => 'float',
            'importacao_tamanho_maximo_mb' => 'integer',
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

    /**
     * Provedores que a importação de edital usa, na ordem: o principal e, se houver, a redundância. Só entra o que tem
     * modelo e chave legível (a que não decifra vale como sem chave, como na tela). Sem o principal, a lista fica vazia.
     *
     * @return list<array{provedor: ProvedorIa, conexao: ConexaoIa}>
     */
    public function provedoresDaImportacao(): array
    {
        $provedores = [];

        foreach ([$this->provedor_principal, $this->provedor_redundancia] as $posicao => $provedor) {
            $conexao = ConexaoIa::doProvedor($provedor);

            if ($provedor === null || $conexao === null || blank($conexao->modelo) || blank($conexao->chaveGuardada())) {
                if ($posicao === 0) {
                    return [];
                }

                continue;
            }

            $provedores[] = ['provedor' => $provedor, 'conexao' => $conexao];
        }

        return $provedores;
    }

    /** O prompt editável da importação: o da equipe ou, se não houver, o prompt modelo. */
    public function promptDaImportacao(): string
    {
        return blank($this->importacao_prompt) ? PromptModelo::editavel() : $this->importacao_prompt;
    }

    public function temperaturaDaImportacao(): float
    {
        return (float) $this->importacao_temperatura;
    }

    /** O tamanho máximo do PDF da importação: o configurado, sem passar do teto. */
    public function tamanhoMaximoPdfMb(): int
    {
        return min((int) $this->importacao_tamanho_maximo_mb, self::TAMANHO_MAXIMO_PDF_MB);
    }
}
