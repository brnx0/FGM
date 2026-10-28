<?php

namespace App\Http\Requests\Retaguarda;

use App\Enums\TipoFomento;
use App\Models\Edital;
use App\Support\HtmlSeguro;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'categoria' => ['required', 'string', 'max:255'],
            'tipo_fomento' => ['required', Rule::enum(TipoFomento::class)],
            // Limite antes de sanitizar: o HTML do editor, não o texto visível.
            'divulgacao' => ['required', 'string', 'max:200000'],
            'inscricoes_inicio' => ['required', 'date'],
            'inscricoes_fim' => ['required', 'date', 'after_or_equal:inscricoes_inicio'],
            'vigencia_inicio' => ['required', 'date'],
            'vigencia_fim' => ['nullable', 'date', 'after_or_equal:vigencia_inicio'],
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
            'divulgacao.required' => 'Este campo é obrigatório.',
            'tipo_fomento.enum' => 'Selecione uma opção da lista.',
            'vigencia_fim.after_or_equal' => 'O fim da vigência não pode ser antes do início.',
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
                $divulgacao = $this->input('divulgacao');

                // Parágrafos vazios e espaços não são conteúdo.
                if (is_string($divulgacao) && ! $validator->errors()->has('divulgacao')
                    && preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(strip_tags(HtmlSeguro::limpar($divulgacao)))) === '') {
                    $validator->errors()->add('divulgacao', 'Este campo é obrigatório.');
                }
            },
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
     * A divulgação é limpa pelo modelo (Edital::divulgacao).
     *
     * @return array{titulo: string, categoria: string, tipo_fomento: string, divulgacao: string, inscricoes_inicio: string, inscricoes_fim: string, vigencia_inicio: string, vigencia_fim: ?string, publicado: bool}
     */
    public function dadosDoEdital(): array
    {
        return [
            ...$this->safe()->only(['titulo', 'categoria', 'tipo_fomento', 'divulgacao', 'inscricoes_inicio', 'inscricoes_fim', 'vigencia_inicio']),
            'vigencia_fim' => $this->safe()->input('vigencia_fim'),
            'publicado' => $this->boolean('publicado'),
        ];
    }
}
