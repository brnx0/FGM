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
 * A importação de edital por IA usa os mesmos provedores, mas não depende de "ativo": fica sempre disponível. Seus
 * parâmetros (prompt, temperatura e tamanho máximo do PDF) são fixos no código, não se configuram na tela.
 */
#[Fillable(['ativo', 'provedor_principal', 'provedor_redundancia'])]
class ConfiguracaoIa extends Model
{
    /** @use HasFactory<ConfiguracaoIaFactory> */
    use HasFactory;

    /** Temperatura da IA na importação: baixa, para a leitura do edital ser a mais fiel possível. */
    public const float TEMPERATURA_DA_IMPORTACAO = 0.1;

    /**
     * Tamanho máximo do PDF da importação: no Gemini, o pedido vai inteiro no corpo (base64, cerca de 4/3 do arquivo) e o limite
     * dele é de uns 20 MB.
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

    /** O prompt editável da importação: sempre o prompt modelo. */
    public function promptDaImportacao(): string
    {
        return PromptModelo::editavel();
    }

    public function temperaturaDaImportacao(): float
    {
        return self::TEMPERATURA_DA_IMPORTACAO;
    }

    public function tamanhoMaximoPdfMb(): int
    {
        return self::TAMANHO_MAXIMO_PDF_MB;
    }
}
