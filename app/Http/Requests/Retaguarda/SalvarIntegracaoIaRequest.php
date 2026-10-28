<?php

namespace App\Http\Requests\Retaguarda;

use App\Enums\ProvedorIa;
use App\Http\Requests\Retaguarda\Concerns\ValidaConexaoIa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Provedor principal da IA (cartão 1 da Integração com IA): provedor, modelo, chave e se a validação por IA está ligada. */
class SalvarIntegracaoIaRequest extends FormRequest
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
            'modelo' => ['required', ...$this->regrasDoModelo()],
            'chave_api' => $this->regrasDaChave(),
            'ativo' => ['required', 'boolean'],
        ];
    }
}
