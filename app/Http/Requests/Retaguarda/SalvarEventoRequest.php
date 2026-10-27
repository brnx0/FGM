<?php

namespace App\Http\Requests\Retaguarda;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Criação e edição de evento na retaguarda. Sem nova imagem, a atual fica. */
class SalvarEventoRequest extends FormRequest
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
            // Limite antes de sanitizar: o HTML do editor, não o texto visível.
            'descricao' => ['nullable', 'string', 'max:200000'],
            'data_realizacao' => ['required', 'date'],
            'local' => ['nullable', 'string', 'max:255'],
            'imagem' => ['nullable', 'image', 'mimes:jpg,png,webp', 'max:5120'],
            'publicado' => ['boolean'],
            'remover_imagem' => ['boolean'],
        ];
    }

    /**
     * Campos do evento, sem a imagem. A descrição é limpa pelo modelo (Evento::descricao).
     *
     * @return array{titulo: string, resumo: string, descricao: ?string, data_realizacao: string, local: ?string, publicado: bool}
     */
    public function dadosDoEvento(): array
    {
        return [
            ...$this->safe()->only(['titulo', 'resumo', 'data_realizacao']),
            'descricao' => $this->safe()->input('descricao'),
            'local' => $this->safe()->input('local'),
            'publicado' => $this->boolean('publicado'),
        ];
    }
}
