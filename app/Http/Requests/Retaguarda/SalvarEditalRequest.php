<?php

namespace App\Http\Requests\Retaguarda;

use App\Models\Edital;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Criação e edição de edital na retaguarda. Sem novo upload, o PDF e a imagem atuais ficam. */
class SalvarEditalRequest extends FormRequest
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
            'titulo' => ['required', 'string', 'max:255'],
            'resumo' => ['required', 'string'],
            'categoria' => ['required', 'string', 'max:255'],
            'inscricoes_inicio' => ['required', 'date'],
            'inscricoes_fim' => ['required', 'date', 'after_or_equal:inscricoes_inicio'],
            'imagem' => ['nullable', 'image', 'mimes:jpg,png,webp', 'max:5120'],
            // mimetypes confere o conteúdo do arquivo, não só a extensão.
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:20480'],
            'publicado' => ['boolean'],
            'remover_imagem' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pdf.file' => 'O arquivo precisa ser um PDF.',
            'pdf.mimes' => 'O arquivo precisa ser um PDF.',
            'pdf.mimetypes' => 'O arquivo precisa ser um PDF.',
        ];
    }

    /**
     * Edital publicado precisa de PDF: o enviado agora ou o já salvo.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $edital = $this->route('edital');
                $temPdf = $this->hasFile('pdf') || ($edital instanceof Edital && filled($edital->pdf));

                if ($this->boolean('publicado') && ! $temPdf) {
                    $validator->errors()->add('pdf', 'Envie o PDF do edital para publicar.');
                }
            },
        ];
    }

    /**
     * Campos do edital, sem os arquivos.
     *
     * @return array{titulo: string, resumo: string, categoria: string, inscricoes_inicio: string, inscricoes_fim: string, publicado: bool}
     */
    public function dadosDoEdital(): array
    {
        return [
            ...$this->safe()->only(['titulo', 'resumo', 'categoria', 'inscricoes_inicio', 'inscricoes_fim']),
            'publicado' => $this->boolean('publicado'),
        ];
    }
}
