<?php

namespace App\Http\Requests\Retaguarda;

use App\Enums\NaturezaProponente;
use App\Enums\TipoFomento;
use App\Enums\UnidadeCota;
use App\Models\Edital;
use App\Models\EditalCategoria;
use App\Support\HtmlSeguro;
use Closure;
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
            ...$this->regrasDasCategorias(),
            ...$this->regrasDosDocumentos(),
        ];
    }

    /**
     * Cada posição em `categorias` aponta para o array `categorias` do mesmo envio (conferido em after()).
     *
     * @return array<string, array<mixed>>
     */
    private function regrasDosDocumentos(): array
    {
        return [
            'documentos' => ['nullable', 'array', 'max:50'],
            'documentos.*.id' => ['nullable', 'integer'],
            'documentos.*.nome' => ['required', 'string', 'max:255'],
            'documentos.*.orientacao' => ['nullable', 'string', 'max:500'],
            'documentos.*.obrigatorio' => ['boolean'],
            // Sem nenhuma marcada o campo nem chega no envio: ausente vale como vazio.
            'documentos.*.naturezas' => ['required', 'array', 'min:1', $this->semRepetidos()],
            'documentos.*.naturezas.*' => [Rule::enum(NaturezaProponente::class)],
            'documentos.*.todas_categorias' => ['boolean'],
            'documentos.*.categorias' => ['nullable', 'array', $this->semRepetidos()],
            'documentos.*.categorias.*' => ['integer', 'min:0'],
            'documentos.*.tamanho_max_mb' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function regrasDasCategorias(): array
    {
        $regras = [
            'categorias' => ['nullable', 'array', 'max:30'],
            'categorias.*.id' => ['nullable', 'integer'],
            'categorias.*.nome' => ['required', 'string', 'max:255'],
            'categorias.*.valor' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'categorias.*.vagas' => ['required', 'integer', 'min:1', 'max:10000'],
            // Sem nenhuma marcada o campo nem chega no envio: ausente vale como vazio.
            'categorias.*.naturezas' => ['required', 'array', 'min:1', $this->semRepetidos()],
            'categorias.*.naturezas.*' => [Rule::enum(NaturezaProponente::class)],
        ];

        foreach (EditalCategoria::COTAS as $cota) {
            $regras["categorias.*.cotas.{$cota}.valor"] = ['nullable', 'integer', 'min:1'];
            $regras["categorias.*.cotas.{$cota}.unidade"] = ["required_with:categorias.*.cotas.{$cota}.valor", 'nullable', Rule::enum(UnidadeCota::class)];

            // Percentual não passa de 100; a regra depende da unidade de cada categoria.
            foreach (array_keys((array) $this->input('categorias', [])) as $indice) {
                if ($this->input("categorias.{$indice}.cotas.{$cota}.unidade") === UnidadeCota::Percentual->value) {
                    $regras["categorias.{$indice}.cotas.{$cota}.valor"] = ['nullable', 'integer', 'min:1', 'max:100'];
                }
            }
        }

        return $regras;
    }

    /**
     * Repetição dentro de cada lista. A regra `distinct` com curinga compara entre todos os itens
     * (duas categorias com a mesma natureza seriam recusadas).
     */
    private function semRepetidos(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falhar): void {
            if (is_array($valor) && count($valor) !== count(array_unique($valor))) {
                $falhar('O campo :attribute tem um valor repetido.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tipo_fomento' => 'tipo de fomento',
            'vigencia_inicio' => 'início da vigência',
            'vigencia_fim' => 'fim da vigência',
            'divulgacao' => 'divulgação',
            'categorias.*.nome' => 'nome da categoria',
            'categorias.*.valor' => 'valor por proposta',
            'categorias.*.vagas' => 'vagas',
            'categorias.*.naturezas' => 'naturezas',
            'categorias.*.cotas.*.valor' => 'reserva de cotas',
            'categorias.*.cotas.*.unidade' => 'unidade da cota',
            'documentos.*.nome' => 'nome do documento',
            'documentos.*.orientacao' => 'orientação',
            'documentos.*.naturezas' => 'naturezas',
            'documentos.*.categorias' => 'categorias',
            'documentos.*.tamanho_max_mb' => 'tamanho máximo',
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
            'categorias.*.naturezas.required' => 'Marque ao menos uma natureza.',
            'categorias.*.naturezas.min' => 'Marque ao menos uma natureza.',
            'documentos.*.naturezas.required' => 'Marque ao menos uma natureza.',
            'documentos.*.naturezas.min' => 'Marque ao menos uma natureza.',
            'documentos.*.categorias.*.integer' => 'Categoria inválida.',
            'documentos.*.categorias.*.min' => 'Categoria inválida.',
        ];
    }

    /** HTML sem imagem, vídeo, linha ou tabela e só com espaços (inclusive &nbsp;) no texto. */
    private function soTemEspacos(string $html): bool
    {
        if (preg_match('/<(img|iframe|hr|table)\b/i', $html) === 1) {
            return false;
        }

        return preg_replace('/[\s\x{00A0}]+/u', '', html_entity_decode(strip_tags($html))) === '';
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

                if (! is_string($divulgacao) || $validator->errors()->has('divulgacao')) {
                    return;
                }

                // Parágrafos vazios e espaços não são conteúdo; imagem e vídeo são.
                $limpo = HtmlSeguro::limpar($divulgacao);

                if ($limpo === null || $this->soTemEspacos($limpo)) {
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
            function (Validator $validator): void {
                foreach (array_keys((array) $this->input('categorias', [])) as $indice) {
                    // Só confere a soma quando vagas e cotas daquela categoria passaram nas regras.
                    $comErro = array_filter(
                        $validator->errors()->keys(),
                        fn (string $chave): bool => str_starts_with($chave, "categorias.{$indice}."),
                    );

                    if ($comErro !== []) {
                        continue;
                    }

                    $categoria = new EditalCategoria($this->atributosDaCategoria((array) $this->input("categorias.{$indice}")));

                    if ($categoria->totalReservado() > $categoria->vagas) {
                        $validator->errors()->add("categorias.{$indice}.cotas", 'As cotas somam mais vagas do que a categoria tem.');
                    }
                }
            },
            function (Validator $validator): void {
                $totalDeCategorias = count((array) $this->input('categorias', []));

                foreach ((array) $this->input('documentos', []) as $indice => $documento) {
                    if (! is_array($documento) || $this->documentoValeParaTodas($documento)) {
                        continue;
                    }

                    $posicoes = (array) ($documento['categorias'] ?? []);

                    if ($posicoes === []) {
                        $validator->errors()->add("documentos.{$indice}.categorias", 'Escolha ao menos uma categoria ou marque todas.');
                    }

                    foreach ($posicoes as $chave => $posicao) {
                        // Posição malformada já tem erro das regras; aqui só a que não existe na lista.
                        if (! $validator->errors()->has("documentos.{$indice}.categorias.{$chave}")
                            && (int) $posicao >= $totalDeCategorias) {
                            $validator->errors()->add("documentos.{$indice}.categorias.{$chave}", 'Categoria inválida.');
                        }
                    }
                }
            },
        ];
    }

    /**
     * Ausente vale como "todas" (o padrão do documento).
     *
     * @param  array<string, mixed>  $documento
     */
    private function documentoValeParaTodas(array $documento): bool
    {
        return filter_var($documento['todas_categorias'] ?? true, FILTER_VALIDATE_BOOLEAN);
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

    /**
     * Categorias na ordem enviada, prontas para gravar; `id` é o da categoria já salva (se veio).
     *
     * @return list<array{id: ?int, atributos: array<string, mixed>}>
     */
    public function categoriasParaSalvar(): array
    {
        $categorias = [];

        // A validação monta os itens na ordem das regras (os com id primeiro): a posição enviada manda.
        $validadas = (array) $this->validated('categorias', []);
        ksort($validadas, SORT_NUMERIC);

        foreach (array_values($validadas) as $posicao => $categoria) {
            $categorias[] = [
                'id' => filled($categoria['id'] ?? null) ? (int) $categoria['id'] : null,
                'atributos' => [...$this->atributosDaCategoria($categoria), 'ordem' => $posicao + 1],
            ];
        }

        return $categorias;
    }

    /**
     * Documentos na ordem enviada, prontos para gravar. `categorias` são posições no array `categorias` enviado.
     *
     * @return list<array{id: ?int, atributos: array<string, mixed>, categorias: list<int>}>
     */
    public function documentosParaSalvar(): array
    {
        $documentos = [];

        $validados = (array) $this->validated('documentos', []);
        ksort($validados, SORT_NUMERIC);

        foreach (array_values($validados) as $posicao => $documento) {
            $todas = $this->documentoValeParaTodas($documento);

            $documentos[] = [
                'id' => filled($documento['id'] ?? null) ? (int) $documento['id'] : null,
                'atributos' => [
                    'ordem' => $posicao + 1,
                    'nome' => $documento['nome'],
                    'orientacao' => $documento['orientacao'] ?? null,
                    'obrigatorio' => filter_var($documento['obrigatorio'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'naturezas' => array_map(NaturezaProponente::from(...), array_values((array) $documento['naturezas'])),
                    'todas_categorias' => $todas,
                    'tamanho_max_mb' => (int) $documento['tamanho_max_mb'],
                ],
                'categorias' => $todas ? [] : array_values(array_map(intval(...), (array) ($documento['categorias'] ?? []))),
            ];
        }

        return $documentos;
    }

    /**
     * Atributos do modelo a partir do item enviado (valor em reais vira centavos).
     *
     * @param  array<string, mixed>  $categoria
     * @return array<string, mixed>
     */
    private function atributosDaCategoria(array $categoria): array
    {
        $atributos = [
            'nome' => $categoria['nome'] ?? null,
            'valor_centavos' => (int) round((float) ($categoria['valor'] ?? 0) * 100),
            'vagas' => (int) ($categoria['vagas'] ?? 0),
            'naturezas' => array_map(NaturezaProponente::from(...), array_values((array) ($categoria['naturezas'] ?? []))),
        ];

        foreach (EditalCategoria::COTAS as $cota) {
            $valor = $categoria['cotas'][$cota]['valor'] ?? null;
            $unidade = $categoria['cotas'][$cota]['unidade'] ?? null;
            $informada = filled($valor) && filled($unidade);

            $atributos["cota_{$cota}_valor"] = $informada ? (int) $valor : null;
            $atributos["cota_{$cota}_unidade"] = $informada ? UnidadeCota::from($unidade) : null;
        }

        return $atributos;
    }
}
