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
            // Término só com início, e depois dele (no mesmo dia).
            'hora_inicio' => ['nullable', 'date_format:H:i', 'required_with:hora_fim'],
            'hora_fim' => ['nullable', 'date_format:H:i', 'after:hora_inicio'],
            'local' => ['nullable', 'string', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
            // Pin do mapa: as duas coordenadas ou nenhuma.
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'imagem' => ['nullable', 'image', 'mimes:jpg,png,webp', 'max:5120'],
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
            'hora_inicio.date_format' => 'Informe o horário de início no formato HH:MM.',
            'hora_fim.date_format' => 'Informe o horário de término no formato HH:MM.',
            'hora_inicio.required_with' => 'Informe o horário de início para colocar o de término.',
            'hora_fim.after' => 'O horário de término deve ser depois do horário de início.',
            'latitude.required_with' => 'Marque o local no mapa de novo: faltou uma das coordenadas.',
            'longitude.required_with' => 'Marque o local no mapa de novo: faltou uma das coordenadas.',
            'latitude.between' => 'A marcação está fora do mapa. Marque o local de novo.',
            'longitude.between' => 'A marcação está fora do mapa. Marque o local de novo.',
        ];
    }

    /**
     * Campos do evento, sem a imagem. A descrição é limpa pelo modelo (Evento::descricao).
     *
     * @return array{titulo: string, resumo: string, descricao: ?string, data_realizacao: string, hora_inicio: ?string, hora_fim: ?string, local: ?string, endereco: ?string, latitude: ?float, longitude: ?float, publicado: bool}
     */
    public function dadosDoEvento(): array
    {
        $coordenada = fn (string $campo): ?float => $this->safe()->input($campo) === null ? null : (float) $this->safe()->input($campo);

        return [
            ...$this->safe()->only(['titulo', 'resumo', 'data_realizacao']),
            'descricao' => $this->safe()->input('descricao'),
            'hora_inicio' => $this->safe()->input('hora_inicio'),
            'hora_fim' => $this->safe()->input('hora_fim'),
            'local' => $this->safe()->input('local'),
            'endereco' => $this->safe()->input('endereco'),
            'latitude' => $coordenada('latitude'),
            'longitude' => $coordenada('longitude'),
            'publicado' => $this->boolean('publicado'),
        ];
    }
}
