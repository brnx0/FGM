<?php

namespace App\Http\Requests;

use App\Enums\TipoCadastro;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Última etapa do cadastro: Proponente, Patrocinador ou os dois. */
class SalvarTipoCadastroRequest extends FormRequest
{
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
            // Com a regra array, o in confere cada item da lista.
            'tipos_cadastro' => ['required', 'array', Rule::in(array_column(TipoCadastro::cases(), 'value'))],
        ];
    }

    /**
     * O app não tem tradução do Laravel.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipos_cadastro' => 'Selecione pelo menos um tipo de cadastro.',
        ];
    }
}
