<?php

namespace App\Http\Requests\Retaguarda;

use App\Enums\ProvedorIa;
use App\Http\Requests\Retaguarda\Concerns\ValidaConexaoIa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Buscar" modelos e "Testar conexão" (Integração com IA), com a chave digitada ou, em branco, a guardada.
 * Sem nenhuma das duas o pedido para aqui (422) e nada sai para o provedor.
 */
class ChamarProvedorIaRequest extends FormRequest
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
            'provedor' => ['required', Rule::enum(ProvedorIa::class)],
            'modelo' => [Rule::requiredIf(fn (): bool => $this->routeIs('retaguarda.integracao-ia.teste')), 'nullable', ...$this->regrasDoModelo()],
            'chave_api' => $this->regrasDaChave(),
        ];
    }

    /** A chave digitada ou, em branco, a guardada (a validação já garantiu que uma das duas existe). */
    public function chave(): string
    {
        return $this->chaveNova() ?? (string) $this->conexaoGuardada()?->chaveGuardada();
    }
}
