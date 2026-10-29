<?php

namespace App\Http\Requests\Retaguarda;

use App\Enums\EtapaDocumento;
use App\Enums\NaturezaProponente;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Gerar com IA" e "Gerar instruções de todos" (aba Documentos do edital). Vem do formulário, que pode ainda não estar
 * salvo: traz o contexto do edital e os documentos como estão na tela, sem ids. Os limites de texto são os do
 * SalvarEditalRequest; nada disso é gravado.
 */
class GerarInstrucoesRequest extends FormRequest
{
    /** Teto de documentos por pedido (a IA recebe em lotes, ver GeradorDeInstrucoes). */
    public const int MAXIMO_DOCUMENTOS = 60;

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
            'edital' => ['required', 'array'],
            'edital.titulo' => ['nullable', 'string', 'max:255'],
            'edital.objeto' => ['nullable', 'string', 'max:1000'],
            'edital.naturezas' => ['nullable', 'array', 'list'],
            'edital.naturezas.*' => ['distinct', Rule::enum(NaturezaProponente::class)],
            'edital.categorias' => ['nullable', 'array', 'list', 'max:'.SalvarEditalRequest::MAXIMO_ITENS],
            'edital.categorias.*' => ['string', 'max:255'],

            'documentos' => ['required', 'array', 'list', 'max:'.self::MAXIMO_DOCUMENTOS],
            'documentos.*.nome' => ['required', 'string', 'max:255'],
            'documentos.*.orientacao' => ['nullable', 'string', 'max:1000'],
            'documentos.*.etapa' => ['required', Rule::enum(EtapaDocumento::class)],
            'documentos.*.anexo_referencia' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{titulo: string|null, objeto: string|null, naturezas: list<string>, categorias: list<string>}
     */
    public function edital(): array
    {
        return [
            'titulo' => $this->validated('edital.titulo'),
            'objeto' => $this->validated('edital.objeto'),
            'naturezas' => $this->validated('edital.naturezas') ?? [],
            'categorias' => $this->validated('edital.categorias') ?? [],
        ];
    }

    /**
     * @return list<array{nome: string, orientacao: string|null, etapa: string, anexo_referencia: string|null}>
     */
    public function documentos(): array
    {
        return array_map(fn (array $documento): array => [
            'nome' => $documento['nome'],
            'orientacao' => $documento['orientacao'] ?? null,
            'etapa' => $documento['etapa'],
            'anexo_referencia' => $documento['anexo_referencia'] ?? null,
        ], $this->validated('documentos'));
    }
}
