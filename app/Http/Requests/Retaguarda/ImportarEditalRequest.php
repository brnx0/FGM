<?php

namespace App\Http\Requests\Retaguarda;

use App\Models\ConfiguracaoIa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** PDF de edital para a IA analisar, até o tamanho máximo fixo da importação (14 MB). */
class ImportarEditalRequest extends FormRequest
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
            // mimetypes confere o conteúdo do arquivo, não só a extensão.
            'pdf' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.($this->tamanhoMaximoMb() * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pdf.required' => 'Este campo é obrigatório.',
            'pdf.file' => 'O arquivo precisa ser um PDF.',
            'pdf.mimes' => 'O arquivo precisa ser um PDF.',
            'pdf.mimetypes' => 'O arquivo precisa ser um PDF.',
            'pdf.max' => "O PDF deve ter no máximo {$this->tamanhoMaximoMb()} MB.",
            'pdf.uploaded' => 'Não foi possível receber o arquivo. Confira o tamanho e tente de novo.',
        ];
    }

    private function tamanhoMaximoMb(): int
    {
        return ConfiguracaoIa::atual()->tamanhoMaximoPdfMb();
    }
}
