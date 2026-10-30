<?php

namespace App\Http\Requests\Retaguarda;

use App\Models\Avaliador;
use App\Rules\TamanhoEmBytes;
use App\Support\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Criação e edição de avaliador. CPF e e-mail não se repetem entre avaliadores; o CPF é comparado só com os dígitos.
 */
class SalvarAvaliadorRequest extends FormRequest
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
            'nome' => ['required', 'string', 'max:120', new TamanhoEmBytes(120)],
            'data_nascimento' => ['required', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            'cpf' => ['required', 'string', function (string $atributo, mixed $valor, Closure $falhar): void {
                if (! is_string($valor) || ! Cpf::valido($valor)) {
                    $falhar('Digite um CPF válido.');
                }
            }],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('avaliadores', 'email')->ignore($this->avaliador()?->id)],
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
            'string' => 'Digite um texto.',
            'max' => 'Use no máximo :max caracteres.',
            'data_nascimento.date_format' => 'Digite uma data válida.',
            'data_nascimento.after' => 'Digite uma data válida.',
            'data_nascimento.before' => 'A data de nascimento precisa ser anterior a hoje.',
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está cadastrado como avaliador.',
        ];
    }

    /**
     * CPF único comparado já normalizado (o banco guarda só os 11 dígitos).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('cpf')) {
                    return;
                }

                $emUso = Avaliador::query()
                    ->where('cpf', $this->cpf())
                    ->when($this->avaliador(), fn ($q, Avaliador $avaliador) => $q->whereKeyNot($avaliador->id))
                    ->exists();

                if ($emUso) {
                    $validator->errors()->add('cpf', 'Este CPF já está cadastrado como avaliador.');
                }
            },
        ];
    }

    /** E-mail chega como digitado; vale (e é comparado) sem espaços e em minúsculas. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array{nome: string, data_nascimento: string, cpf: string, email: string}
     */
    public function dados(): array
    {
        return [
            'nome' => $this->validated('nome'),
            'data_nascimento' => $this->validated('data_nascimento'),
            'cpf' => $this->cpf(),
            'email' => $this->validated('email'),
        ];
    }

    private function cpf(): string
    {
        return Cpf::normalizar((string) $this->input('cpf'));
    }

    private function avaliador(): ?Avaliador
    {
        $avaliador = $this->route('avaliador');

        return $avaliador instanceof Avaliador ? $avaliador : null;
    }
}
