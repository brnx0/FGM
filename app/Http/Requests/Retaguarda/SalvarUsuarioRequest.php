<?php

namespace App\Http\Requests\Retaguarda;

use App\Models\User;
use App\Support\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Criação e edição de usuário na retaguarda. Na edição, senha em branco mantém a atual.
 * Na criação, um CPF que já é de proponente (usuário sem papel) reaproveita esse usuário.
 */
class SalvarUsuarioRequest extends FormRequest
{
    private ?User $reaproveitado = null;

    private bool $reaproveitadoBuscado = false;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->idDoProprio()),
            ],
            'cpf' => [
                'required',
                'string',
                function (string $atributo, mixed $valor, Closure $falhar): void {
                    if (! is_string($valor) || ! Cpf::valido($valor)) {
                        $falhar('Digite um CPF válido.');
                    }
                },
            ],
            'senha' => [$this->usuarioEditado() ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'papeis' => ['required', 'array'],
            'papeis.*' => ['string', 'distinct', Rule::exists('roles', 'slug')],
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
            'email.email' => 'Digite um e-mail válido.',
            'email.unique' => 'Este e-mail já está em uso.',
            'senha.min' => 'A senha precisa ter ao menos 8 caracteres.',
            'senha.confirmed' => 'A confirmação não confere com a senha.',
            'papeis.required' => 'Escolha ao menos um papel.',
            'papeis.array' => 'Escolha ao menos um papel.',
            'papeis.*.string' => 'Papel inválido.',
            'papeis.*.distinct' => 'Papel repetido.',
            'papeis.*.exists' => 'Papel inválido.',
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

                $emUso = User::query()
                    ->where('cpf', $this->cpf())
                    ->when($this->idDoProprio(), fn ($q, int $id) => $q->whereKeyNot($id))
                    ->exists();

                if ($emUso) {
                    $validator->errors()->add('cpf', 'Este CPF já está cadastrado.');
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

    /** Usuário da rota (edição) ou null (criação). */
    public function usuarioEditado(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }

    /** Na criação: o proponente (sem papel) que já tem este CPF, se houver. */
    public function usuarioReaproveitado(): ?User
    {
        if ($this->usuarioEditado() !== null) {
            return null;
        }

        if (! $this->reaproveitadoBuscado) {
            $this->reaproveitadoBuscado = true;
            $cpf = $this->input('cpf');

            $this->reaproveitado = is_string($cpf) && Cpf::valido($cpf)
                ? User::where('cpf', Cpf::normalizar($cpf))->whereDoesntHave('papeis')->first()
                : null;
        }

        return $this->reaproveitado;
    }

    public function cpf(): string
    {
        return Cpf::normalizar((string) $this->input('cpf'));
    }

    /** Senha nova, ou null quando deixada em branco na edição. */
    public function senhaNova(): ?string
    {
        $senha = $this->safe()->input('senha');

        return filled($senha) ? $senha : null;
    }

    /** @return list<string> */
    public function papeis(): array
    {
        return array_values($this->safe()->input('papeis'));
    }

    /** Usuário que não conta como duplicado de si mesmo: o editado ou o proponente reaproveitado. */
    private function idDoProprio(): ?int
    {
        return ($this->usuarioEditado() ?? $this->usuarioReaproveitado())?->id;
    }
}
