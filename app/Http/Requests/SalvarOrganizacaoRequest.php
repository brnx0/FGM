<?php

namespace App\Http\Requests;

use App\Enums\OrigemRepresentacao;
use App\Enums\PorteOrganizacao;
use App\Enums\TipoDocumentoIdentidade;
use App\Models\Organizacao;
use App\Support\Cnpj;
use App\Support\Cpf;
use App\Support\Estados;
use App\Support\Municipios;
use App\Support\NaturezasJuridicas;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** Cadastro e edição de organização. O CNPJ só é validado no cadastro; na edição o enviado é ignorado. */
class SalvarOrganizacaoRequest extends FormRequest
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
            ...($this->isMethod('POST') ? ['cnpj' => [
                'required',
                'string',
                fn (string $atributo, mixed $valor, Closure $falhar) => Cnpj::valido((string) $valor) ? null : $falhar('CNPJ inválido. Confira os números e letras.'),
                Rule::unique(Organizacao::class, 'cnpj'),
            ]] : []),

            'razao_social' => ['required', 'string', 'max:255'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'natureza_juridica' => ['required', Rule::in(array_keys(NaturezasJuridicas::TODAS))],
            'mei' => ['boolean'],
            'finalidade_lucrativa' => ['nullable', 'boolean'],
            'municipio_ibge' => [
                'required',
                'string',
                fn (string $atributo, mixed $valor, Closure $falhar) => Municipios::existe((string) $valor) ? null : $falhar('Selecione um município da lista.'),
            ],
            'inscricao_municipal' => ['nullable', 'string', 'max:30'],
            'inscricao_estadual' => ['nullable', 'string', 'max:30'],
            'porte' => ['nullable', Rule::enum(PorteOrganizacao::class)],
            'data_fundacao' => ['nullable', 'date', 'before_or_equal:today'],
            'telefone' => ['nullable', 'digits_between:10,11'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'cep' => ['nullable', 'digits:8'],
            'logradouro' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'sem_numero' => ['boolean'],
            'complemento' => ['nullable', 'string', 'max:120'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', Rule::in(array_keys(Estados::TODOS))],

            'representantes' => ['required', 'array', 'min:1', 'max:'.Organizacao::LIMITE_REPRESENTANTES],
            'representantes.*.cpf' => [
                'required',
                'string',
                fn (string $atributo, mixed $valor, Closure $falhar) => Cpf::valido((string) $valor) ? null : $falhar('CPF inválido. Confira os números.'),
                'distinct',
            ],
            'representantes.*.nome' => ['required', 'string', 'max:255'],
            'representantes.*.tipo_documento' => ['nullable', Rule::enum(TipoDocumentoIdentidade::class)],
            'representantes.*.numero_documento' => ['nullable', 'string', 'max:30'],
            'representantes.*.cargo' => ['nullable', 'string', 'max:120'],
            'representantes.*.telefone' => ['nullable', 'digits_between:10,11'],
            'representantes.*.email' => ['nullable', 'email', 'max:255'],
            'representantes.*.origem' => ['nullable', Rule::enum(OrigemRepresentacao::class)],
            'representantes.*.origem_descricao' => ['required_if:representantes.*.origem,'.OrigemRepresentacao::Outro->value, 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * O app tem poucas traduções do Laravel; as mensagens aparecem embaixo de cada campo.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => 'Este campo é obrigatório.',
            'string' => 'Digite um texto.',
            'max' => 'Use no máximo :max caracteres.',
            'in' => 'Selecione uma opção da lista.',
            Enum::class => 'Selecione uma opção da lista.',
            'boolean' => 'Valor inválido.',
            'email' => 'Digite um e-mail válido.',
            'date' => 'Digite uma data válida.',
            'before_or_equal' => 'A data não pode ser futura.',
            'telefone.digits_between' => 'Digite o telefone com DDD.',
            'representantes.*.telefone.digits_between' => 'Digite o telefone com DDD.',
            'cep.digits' => 'Digite os 8 números do CEP.',
            'cnpj.unique' => 'Esta organização já está cadastrada. Peça ao responsável por ela para vincular você.',
            'logo.image' => 'Envie uma imagem JPG, PNG ou WEBP.',
            'logo.mimes' => 'Envie uma imagem JPG, PNG ou WEBP.',
            'logo.uploaded' => 'Não foi possível enviar o arquivo. Ele deve ter no máximo 5 MB.',
            'logo.max' => 'A imagem deve ter no máximo 5 MB.',
            'representantes.required' => 'Informe pelo menos um representante legal.',
            'representantes.min' => 'Informe pelo menos um representante legal.',
            'representantes.max' => 'Informe no máximo :max representantes legais.',
            'representantes.*.cpf.distinct' => 'Este CPF já está em outro representante.',
            'representantes.*.origem_descricao.required_if' => 'Descreva a origem da representação.',
        ];
    }

    /** CNPJ, CEP e telefones chegam com máscara; ficam só os caracteres úteis. */
    protected function prepareForValidation(): void
    {
        $somenteNumeros = fn (mixed $valor): mixed => is_string($valor) ? preg_replace('/\D/', '', $valor) : $valor;

        $this->merge(collect(['cep', 'telefone'])
            ->filter(fn (string $campo): bool => is_string($this->input($campo)))
            ->mapWithKeys(fn (string $campo): array => [$campo => $somenteNumeros($this->input($campo))])
            ->all());

        if (is_string($this->input('cnpj'))) {
            $this->merge(['cnpj' => Cnpj::normalizar($this->input('cnpj'))]);
        }

        if (is_array($this->input('representantes'))) {
            $this->merge(['representantes' => array_map(
                fn (mixed $representante): mixed => is_array($representante)
                    ? array_merge($representante, array_map($somenteNumeros, array_intersect_key($representante, array_flip(['cpf', 'telefone']))))
                    : $representante,
                $this->input('representantes'),
            )]);
        }
    }
}
