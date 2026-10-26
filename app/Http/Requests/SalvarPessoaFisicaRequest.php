<?php

namespace App\Http\Requests;

use App\Enums\IdentidadeGenero;
use App\Enums\RacaCor;
use App\Enums\Territorio;
use App\Models\PessoaFisica;
use App\Support\Estados;
use App\Support\Paises;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Nome, CPF e, quando o gov.br os informa, e-mail e telefone não são validados aqui:
 * o controller usa os dados do gov.br e ignora o que vier no formulário.
 */
class SalvarPessoaFisicaRequest extends FormRequest
{
    /** Imagem ou PDF de até 2 MB (max em KB). */
    private const array DOCUMENTO = ['file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        $salvo = $user->pessoaFisica;

        // Documentos obrigatórios só precisam ser reenviados se ainda não houver um salvo.
        $documentoObrigatorio = fn (string $coluna): array => [
            Rule::requiredIf(fn (): bool => $salvo?->{$coluna} === null),
            'nullable',
            ...self::DOCUMENTO,
        ];

        return [
            'nome_social' => ['nullable', 'string', 'max:255'],
            'nome_artistico' => ['nullable', 'string', 'max:255'],
            'foto_perfil' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif', 'max:2048'],

            'pais_origem' => ['required', Rule::in(Paises::TODOS)],
            'rg' => ['required', 'string', 'max:20'],
            'documento_cpf' => $documentoObrigatorio('documento_cpf'),
            'documento_rg' => $documentoObrigatorio('documento_rg'),
            'documento_passaporte' => ['nullable', ...self::DOCUMENTO],
            'documento_crnm' => ['nullable', ...self::DOCUMENTO],
            'documento_outros' => ['nullable', ...self::DOCUMENTO],

            'drt' => ['nullable', 'string', 'max:30'],
            'cultura_principal_renda' => ['boolean'],
            'ja_foi_avaliador' => ['boolean'],
            'interesse_avaliador' => ['boolean'],
            'representa_coletivo' => ['boolean'],

            ...($user->phone === null ? ['telefone' => ['required', 'digits_between:10,11']] : []),
            'telefone_adicional' => ['nullable', 'digits_between:10,11'],
            ...($user->email === null ? ['email' => ['required', 'email', 'max:255']] : []),

            'identidade_genero' => ['required', Rule::enum(IdentidadeGenero::class)],
            'raca_cor' => ['required', Rule::enum(RacaCor::class)],
            'pcd' => ['boolean'],

            'cep' => ['required', 'digits:8'],
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['required', 'string', 'max:120'],
            'complemento' => ['nullable', 'string', 'max:120'],
            'cidade' => ['required', 'string', 'max:120'],
            'estado' => ['required', Rule::in(array_keys(Estados::TODOS))],
            'territorio' => ['required', Rule::enum(Territorio::class)],
            'comprovante_residencia' => ['nullable', ...self::DOCUMENTO],
        ];
    }

    /**
     * O app não tem tradução do Laravel; as mensagens aparecem embaixo de cada campo.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $mensagens = [
            'required' => 'Este campo é obrigatório.',
            'string' => 'Digite um texto.',
            'max' => 'Use no máximo :max caracteres.',
            'in' => 'Selecione uma opção da lista.',
            Enum::class => 'Selecione uma opção da lista.',
            'boolean' => 'Valor inválido.',
            'email' => 'Digite um e-mail válido.',
            'telefone.digits_between' => 'Digite o telefone com DDD.',
            'telefone_adicional.digits_between' => 'Digite o telefone com DDD.',
            'cep.digits' => 'Digite os 8 números do CEP.',
            'foto_perfil.image' => 'Envie uma imagem JPG, PNG ou GIF.',
            'foto_perfil.mimes' => 'Envie uma imagem JPG, PNG ou GIF.',
        ];

        foreach (PessoaFisica::ARQUIVOS as $campo) {
            $mensagens["{$campo}.uploaded"] = 'Não foi possível enviar o arquivo. Ele deve ter no máximo 2 MB.';
            $mensagens["{$campo}.file"] = 'Envie um arquivo.';
            $mensagens["{$campo}.mimes"] ??= 'Envie um PDF ou uma imagem JPG ou PNG.';
            $mensagens["{$campo}.max"] = 'O arquivo deve ter no máximo 2 MB.';
        }

        return $mensagens;
    }

    /** Telefones e CEP chegam com máscara; ficam só os números. */
    protected function prepareForValidation(): void
    {
        $this->merge(collect(['telefone', 'telefone_adicional', 'cep'])
            ->filter(fn (string $campo): bool => is_string($this->input($campo)))
            ->mapWithKeys(fn (string $campo): array => [$campo => preg_replace('/\D/', '', $this->input($campo))])
            ->all());
    }
}
