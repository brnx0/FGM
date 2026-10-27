<?php

namespace App\Http\Requests;

use App\Models\Organizacao;
use App\Models\VinculoOrganizacao;
use App\Support\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Vínculo de uma pessoa, pelo CPF, a uma organização. Só o responsável pode. */
class GuardarVinculoOrganizacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('gerenciarVinculos', $this->route('organizacao'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Organizacao $organizacao */
        $organizacao = $this->route('organizacao');

        return [
            'cpf' => [
                'required',
                'string',
                fn (string $atributo, mixed $valor, Closure $falhar) => Cpf::valido((string) $valor) ? null : $falhar('CPF inválido. Confira os números.'),
                Rule::unique(VinculoOrganizacao::class, 'cpf')->where('organizacao_id', $organizacao->id),
                fn (string $atributo, mixed $valor, Closure $falhar) => $organizacao->vinculos()->count() < Organizacao::LIMITE_VINCULOS
                    ? null
                    : $falhar('Limite de '.Organizacao::LIMITE_VINCULOS.' pessoas vinculadas.'),
            ],
            'nome' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Este campo é obrigatório.',
            'string' => 'Digite um texto.',
            'max' => 'Use no máximo :max caracteres.',
            'cpf.unique' => 'Esta pessoa já está vinculada.',
        ];
    }

    /** O CPF chega com máscara; fica só os números. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cpf'))) {
            $this->merge(['cpf' => Cpf::normalizar($this->input('cpf'))]);
        }
    }
}
