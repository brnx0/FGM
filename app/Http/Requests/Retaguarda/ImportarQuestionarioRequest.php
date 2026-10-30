<?php

namespace App\Http\Requests\Retaguarda;

use App\Models\ConfiguracaoIa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;
use ZipArchive;

/**
 * Espelho do formulário de inscrição (PDF ou Word .docx) para a IA montar o questionário, até o tamanho máximo fixo
 * da importação (14 MB). O conteúdo é conferido em after(): o finfo pode ver um .docx como application/zip, então a
 * regra mimes não serve para ele.
 */
class ImportarQuestionarioRequest extends FormRequest
{
    private const string ERRO_TIPO = 'O arquivo precisa ser PDF ou Word (.docx).';

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
            'arquivo' => ['required', 'file', 'extensions:pdf,docx', 'max:'.($this->tamanhoMaximoMb() * 1024)],
            'substituir' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'arquivo.required' => 'Este campo é obrigatório.',
            'arquivo.file' => self::ERRO_TIPO,
            'arquivo.extensions' => self::ERRO_TIPO,
            'arquivo.max' => "O arquivo deve ter no máximo {$this->tamanhoMaximoMb()} MB.",
            'arquivo.uploaded' => 'Não foi possível receber o arquivo. Confira o tamanho e tente de novo.',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $arquivo = $this->file('arquivo');
                if ($validator->errors()->has('arquivo') || ! $arquivo instanceof UploadedFile) {
                    return;
                }

                $valido = $this->tipoDoArquivo() === 'docx'
                    ? self::docxValido($arquivo)
                    : $arquivo->getMimeType() === 'application/pdf';

                if (! $valido) {
                    $validator->errors()->add('arquivo', self::ERRO_TIPO);
                }
            },
        ];
    }

    /** pdf ou docx, pela extensão do nome enviado (já conferida nas regras). */
    public function tipoDoArquivo(): string
    {
        return strtolower((string) $this->file('arquivo')?->getClientOriginalExtension()) === 'docx' ? 'docx' : 'pdf';
    }

    /** Um zip que abre e tem o corpo do documento do Word. */
    private static function docxValido(UploadedFile $arquivo): bool
    {
        $zip = new ZipArchive;
        if ($zip->open((string) $arquivo->getRealPath(), ZipArchive::RDONLY) !== true) {
            return false;
        }

        $temCorpo = $zip->locateName('word/document.xml') !== false;
        $zip->close();

        return $temCorpo;
    }

    private function tamanhoMaximoMb(): int
    {
        return ConfiguracaoIa::atual()->tamanhoMaximoPdfMb();
    }
}
