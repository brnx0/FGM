<?php

namespace App\Http\Requests\Retaguarda;

use App\Models\ConfiguracaoIa;
use App\Support\EditalIa\PromptModelo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Parâmetros da importação de edital (cartão 3 da Integração com IA): o prompt editável, a temperatura e o tamanho
 * máximo do PDF. Não mexe no provedor nem na chave, que são dos cartões 1 e 2.
 */
class SalvarImportacaoIaRequest extends FormRequest
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
            'prompt' => ['required', 'string', 'max:20000'],
            'temperatura' => ['required', 'numeric', 'between:0,1'],
            'tamanho_maximo_mb' => ['required', 'integer', 'between:1,'.ConfiguracaoIa::TAMANHO_MAXIMO_PDF_MB],
        ];
    }

    /**
     * O app não tem tradução do Laravel; as mensagens aparecem embaixo de cada campo.
     *
     * @return array<string, string|array<string, string>>
     */
    public function messages(): array
    {
        return [
            'required' => 'Este campo é obrigatório.',
            'string' => 'Digite um texto.',
            'numeric' => 'Informe um número.',
            'integer' => 'Informe um número inteiro.',
            'between' => ['numeric' => 'Informe um valor entre :min e :max.'],
            'max' => ['string' => 'Use no máximo :max caracteres.'],
        ];
    }

    /**
     * Os campos prontos para gravar. O prompt igual ao modelo fica nulo, para acompanhar o modelo quando ele mudar.
     *
     * @return array{importacao_prompt: ?string, importacao_temperatura: float, importacao_tamanho_maximo_mb: int}
     */
    public function parametros(): array
    {
        $prompt = str_replace("\r\n", "\n", $this->validated('prompt'));

        return [
            'importacao_prompt' => trim($prompt) === trim(PromptModelo::editavel()) ? null : $prompt,
            'importacao_temperatura' => (float) $this->validated('temperatura'),
            'importacao_tamanho_maximo_mb' => (int) $this->validated('tamanho_maximo_mb'),
        ];
    }
}
