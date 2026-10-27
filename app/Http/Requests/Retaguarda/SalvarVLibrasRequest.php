<?php

namespace App\Http\Requests\Retaguarda;

use App\Enums\AvatarVLibras;
use App\Enums\LadoVLibras;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** Liga ou desliga o VLibras no portal e escolhe o lado da tela e o avatar. */
class SalvarVLibrasRequest extends FormRequest
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
            'ativo' => ['required', 'boolean'],
            'lado' => ['required', Rule::enum(LadoVLibras::class)],
            'avatar' => ['required', Rule::enum(AvatarVLibras::class)],
        ];
    }

    /**
     * O app não tem tradução do Laravel; as mensagens aparecem embaixo de cada campo.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Este campo é obrigatório.',
            'boolean' => 'Valor inválido.',
            Enum::class => 'Selecione uma opção da lista.',
        ];
    }
}
