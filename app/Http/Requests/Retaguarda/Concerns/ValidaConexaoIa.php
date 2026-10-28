<?php

namespace App\Http\Requests\Retaguarda\Concerns;

use App\Enums\ProvedorIa;
use App\Models\ConexaoIa;
use App\Support\ClienteIa;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Partes comuns aos pedidos da Integração com IA: o provedor escolhido, o modelo e a chave da API.
 * A chave só é exigida quando o provedor ainda não tem uma guardada; em branco, vale a guardada.
 */
trait ValidaConexaoIa
{
    private ?ConexaoIa $conexaoGuardada = null;

    private bool $conexaoBuscada = false;

    /** Provedor escolhido no formulário, ou null quando veio vazio ou fora da lista. */
    public function provedor(): ?ProvedorIa
    {
        $valor = $this->input('provedor');

        return is_string($valor) ? ProvedorIa::tryFrom($valor) : null;
    }

    /** O que já está guardado para o provedor escolhido (é dali que vem a chave quando o campo fica em branco). */
    public function conexaoGuardada(): ?ConexaoIa
    {
        if (! $this->conexaoBuscada) {
            $this->conexaoBuscada = true;
            $this->conexaoGuardada = ConexaoIa::doProvedor($this->provedor());
        }

        return $this->conexaoGuardada;
    }

    /** Chave digitada, ou null quando deixada em branco. */
    public function chaveNova(): ?string
    {
        $chave = $this->safe()->input('chave_api');

        return filled($chave) ? $chave : null;
    }

    /**
     * @return list<string>
     */
    protected function regrasDoModelo(): array
    {
        return ['string', 'max:100', 'regex:'.ClienteIa::FORMATO_MODELO];
    }

    /**
     * Exigida só quando o provedor escolhido não tem chave guardada (ou ela não decifra mais).
     *
     * @return list<mixed>
     */
    protected function regrasDaChave(): array
    {
        return [
            Rule::requiredIf(fn (): bool => $this->provedor() !== null && $this->conexaoGuardada()?->chaveGuardada() === null),
            'nullable',
            'string',
            'min:20',
            'max:300',
            'regex:/^\S+$/',
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
            'modelo.string' => 'Escolha um modelo da lista.',
            'modelo.max' => 'Escolha um modelo da lista.',
            'modelo.regex' => 'Escolha um modelo da lista.',
            'chave_api.required' => 'Informe a chave da API.',
            'chave_api.string' => 'Valor inválido.',
            'chave_api.min' => 'A chave parece incompleta. Copie a chave inteira.',
            'chave_api.max' => 'A chave passa do tamanho esperado. Confira se colou só a chave.',
            'chave_api.regex' => 'A chave não pode ter espaços.',
        ];
    }
}
