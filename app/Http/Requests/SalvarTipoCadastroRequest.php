<?php

namespace App\Http\Requests;

use App\Enums\TipoCadastro;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Última etapa do cadastro: Proponente ou Patrocinador. */
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
            'tipo_cadastro' => ['required', Rule::enum(TipoCadastro::class)],
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
            'tipo_cadastro' => 'Selecione o tipo de cadastro.',
        ];
    }
}
