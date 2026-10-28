<?php

namespace App\Http\Requests\Retaguarda;

use App\Enums\ProvedorIa;
use App\Http\Requests\Retaguarda\Concerns\ValidaConexaoIa;
use App\Models\ConfiguracaoIa;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Provedor de redundância (cartão 2 da Integração com IA), com modelo e chave próprios. Recebe só a chamada que o
 * principal não atender. Sem provedor, a redundância sai (a chave do provedor continua guardada).
 */
class SalvarRedundanciaIaRequest extends FormRequest
{
    use ValidaConexaoIa;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provedor' => [
                'nullable',
                Rule::enum(ProvedorIa::class),
                function (string $atributo, mixed $valor, Closure $falhar): void {
                    $principal = ConfiguracaoIa::atual()->provedor_principal;

                    if ($principal === null) {
                        $falhar('Salve o provedor principal antes da redundância.');
                    } elseif ($valor === $principal->value) {
                        $falhar('Escolha um provedor diferente do principal.');
                    }
                },
            ],
            // "Sem redundância": modelo e chave nem entram na validação.
            'modelo' => [Rule::excludeIf(fn (): bool => $this->provedor() === null), 'required', ...$this->regrasDoModelo()],
            'chave_api' => [Rule::excludeIf(fn (): bool => $this->provedor() === null), ...$this->regrasDaChave()],
        ];
    }
}
