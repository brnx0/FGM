<?php

namespace App\Http\Controllers\Retaguarda;

use App\Enums\NaturezaProponente;
use App\Enums\SituacaoEdital;
use App\Enums\TipoFomento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\SalvarEditalRequest;
use App\Models\Edital;
use App\Models\EditalCategoria;
use App\Models\EditalDocumento;
use App\Support\ArquivoArmazenado;
use App\Support\SlugUnico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Gestão de editais na retaguarda. PDF no disco local (privado), imagem no public, ambos em editais/. */
class EditalController extends Controller
{
    private const string PASTA = 'editais';

    public function index(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));
        $situacao = SituacaoEdital::tryFrom((string) $request->query('situacao', ''));
        $publicado = in_array($request->query('publicado'), ['sim', 'nao'], true) ? $request->query('publicado') : null;

        $editais = Edital::query()
            // Sem a divulgação (CLOB): a lista não precisa do texto.
            ->select(['id', 'slug', 'titulo', 'categoria', 'inscricoes_inicio', 'inscricoes_fim', 'publicado', 'pdf'])
            // No Oracle a comparação de texto diferencia maiúsculas; % e _ digitados valem literalmente.
            ->when($busca !== '', fn (Builder $q) => $q->whereRaw(
                "LOWER(titulo) LIKE ? ESCAPE '\\'",
                ['%'.addcslashes(mb_strtolower($busca), '\\%_').'%'],
            ))
            ->when($situacao, fn (Builder $q) => $q->naSituacao($situacao))
            ->when($publicado, fn (Builder $q) => $q->where('publicado', $publicado === 'sim'))
            ->orderByDesc('inscricoes_inicio')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (Edital $edital): array {
                $situacao = $edital->situacao();

                return [
                    'slug' => $edital->slug,
                    'titulo' => $edital->titulo,
                    'categoria' => $edital->categoria,
                    'periodo' => $edital->inscricoes_inicio->format('d/m/Y').' a '.$edital->inscricoes_fim->format('d/m/Y'),
                    'situacao' => $situacao->value,
                    'situacaoRotulo' => $situacao->rotulo(),
                    'publicado' => $edital->publicado,
                    'temPdf' => filled($edital->pdf),
                ];
            });

        return Inertia::render('Retaguarda/Editais/Index', [
            'editais' => $editais,
            'filtros' => [
                'busca' => $busca,
                'situacao' => $situacao?->value ?? '',
                'publicado' => $publicado ?? '',
            ],
            'situacoes' => array_map(
                fn (SituacaoEdital $s): array => ['valor' => $s->value, 'rotulo' => $s->rotulo()],
                SituacaoEdital::cases(),
            ),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Retaguarda/Editais/Form', [
            'edital' => null,
            'tiposFomento' => $this->tiposFomento(),
            'naturezas' => $this->naturezas(),
        ]);
    }

    public function store(SalvarEditalRequest $request): RedirectResponse
    {
        $edital = new Edital($request->dadosDoEdital());
        $edital->slug = SlugUnico::para(Edital::class, $edital->titulo);

        $novos = [];
        if ($request->hasFile('pdf')) {
            $edital->pdf = ArquivoArmazenado::guardar($request->file('pdf'), 'local', self::PASTA);
            $novos[] = ['local', $edital->pdf];
        }
        if ($request->hasFile('imagem')) {
            $edital->imagem = ArquivoArmazenado::guardar($request->file('imagem'), 'public', self::PASTA);
            $novos[] = ['public', $edital->imagem];
        }

        $this->salvarComCategorias($edital, $request, $novos, []);

        return redirect()->route('retaguarda.editais.index')->with('sucesso', 'Edital salvo.');
    }

    public function edit(Edital $edital): Response
    {
        return Inertia::render('Retaguarda/Editais/Form', [
            'edital' => [
                'slug' => $edital->slug,
                'titulo' => $edital->titulo,
                'categoria' => $edital->categoria,
                'tipo_fomento' => $edital->tipo_fomento?->value,
                'divulgacao' => $edital->divulgacao,
                'inscricoes_inicio' => $edital->inscricoes_inicio->toDateString(),
                'inscricoes_fim' => $edital->inscricoes_fim->toDateString(),
                'vigencia_inicio' => $edital->vigencia_inicio?->toDateString(),
                'vigencia_fim' => $edital->vigencia_fim?->toDateString(),
                'publicado' => $edital->publicado,
                'pdfUrl' => $edital->pdf ? route('retaguarda.editais.pdf', $edital) : null,
                'pdfNome' => $edital->pdf ? $this->nomeDoPdf($edital) : null,
                'imagemUrl' => $edital->imagemUrl(),
                'categorias' => $edital->categorias->map(fn (EditalCategoria $categoria): array => [
                    'id' => (int) $categoria->id,
                    'nome' => $categoria->nome,
                    'valor' => $categoria->valor_centavos / 100,
                    'vagas' => $categoria->vagas,
                    'naturezas' => $categoria->naturezas->map(fn (NaturezaProponente $natureza): string => $natureza->value)->values()->all(),
                    'cotas' => collect(EditalCategoria::COTAS)->mapWithKeys(fn (string $cota): array => [$cota => [
                        'valor' => $categoria->{"cota_{$cota}_valor"} === null ? null : (int) $categoria->{"cota_{$cota}_valor"},
                        'unidade' => $categoria->{"cota_{$cota}_unidade"}?->value,
                    ]])->all(),
                ])->all(),
                'documentos' => $this->documentosParaEdicao($edital),
            ],
            'tiposFomento' => $this->tiposFomento(),
            'naturezas' => $this->naturezas(),
        ]);
    }

    /** O slug não muda: links já divulgados continuam valendo. */
    public function update(SalvarEditalRequest $request, Edital $edital): RedirectResponse
    {
        $edital->fill($request->dadosDoEdital());

        $novos = [];
        $antigos = [];
        if ($request->hasFile('pdf')) {
            $antigos[] = ['local', $edital->pdf];
            $edital->pdf = ArquivoArmazenado::guardar($request->file('pdf'), 'local', self::PASTA);
            $novos[] = ['local', $edital->pdf];
        }

        if ($request->hasFile('imagem')) {
            $antigos[] = ['public', $edital->imagem];
            $edital->imagem = ArquivoArmazenado::guardar($request->file('imagem'), 'public', self::PASTA);
            $novos[] = ['public', $edital->imagem];
        } elseif ($request->boolean('remover_imagem') && $edital->imagem) {
            $antigos[] = ['public', $edital->imagem];
            $edital->imagem = null;
        }

        $this->salvarComCategorias($edital, $request, $novos, $antigos);

        return redirect()->route('retaguarda.editais.index')->with('sucesso', 'Edital salvo.');
    }

    /** Exclusão lógica: os arquivos ficam no disco. */
    public function destroy(Edital $edital): RedirectResponse
    {
        $edital->delete();

        return redirect()->route('retaguarda.editais.index')->with('sucesso', 'Edital excluído.');
    }

    public function publicacao(Edital $edital): RedirectResponse
    {
        if (! $edital->publicado && blank($edital->pdf)) {
            return back()->with('erro', 'Envie o PDF do edital para publicar.');
        }

        $edital->update(['publicado' => ! $edital->publicado]);

        return back()->with('sucesso', $edital->publicado ? 'Edital publicado.' : 'Edital despublicado.');
    }

    /** PDF para conferência do gestor, publicado ou não (a rota do portal só entrega os visíveis). */
    public function pdf(Edital $edital): StreamedResponse
    {
        abort_unless(filled($edital->pdf) && Storage::disk('local')->exists($edital->pdf), 404);

        return Storage::disk('local')->download($edital->pdf, $this->nomeDoPdf($edital));
    }

    /**
     * Salva o edital e sincroniza categorias e documentos numa transação; só depois apaga os arquivos substituídos.
     * Se algo falhar, os arquivos recém-gravados são descartados e os antigos ficam.
     *
     * @param  list<array{string, ?string}>  $novos
     * @param  list<array{string, ?string}>  $antigos
     */
    private function salvarComCategorias(Edital $edital, SalvarEditalRequest $request, array $novos, array $antigos): void
    {
        try {
            DB::transaction(function () use ($edital, $request, $novos): void {
                ArquivoArmazenado::salvarTrocando($edital, $novos, []);
                $categoriasPorPosicao = $this->sincronizarCategorias($edital, $request->categoriasParaSalvar());
                $this->sincronizarDocumentos($edital, $request->documentosParaSalvar(), $categoriasPorPosicao);
            });
        } catch (Throwable $erro) {
            foreach ($novos as [$disco, $caminho]) {
                ArquivoArmazenado::apagar($disco, $caminho);
            }

            throw $erro;
        }

        foreach ($antigos as [$disco, $caminho]) {
            ArquivoArmazenado::apagar($disco, $caminho);
        }
    }

    /**
     * Atualiza as categorias deste edital que vieram com id, cria as novas e apaga as que não vieram.
     * Id de outro edital (ou inexistente) vale como categoria nova.
     *
     * @param  list<array{id: ?int, atributos: array<string, mixed>}>  $categorias
     * @return list<int> id de cada categoria, na posição em que veio no envio
     */
    private function sincronizarCategorias(Edital $edital, array $categorias): array
    {
        $existentes = $edital->categorias()->get()->keyBy(fn (EditalCategoria $categoria): int => (int) $categoria->id);
        $mantidos = [];

        foreach ($categorias as ['id' => $id, 'atributos' => $atributos]) {
            $existente = $id !== null ? $existentes->get($id) : null;

            if ($existente !== null && ! in_array($id, $mantidos, true)) {
                $existente->update($atributos);
                $mantidos[] = $id;
            } else {
                $mantidos[] = (int) $edital->categorias()->create($atributos)->id;
            }
        }

        $existentes->except($mantidos)->each(fn (EditalCategoria $categoria): ?bool => $categoria->delete());

        return $mantidos;
    }

    /**
     * Mesmas regras das categorias: atualiza os que vieram com id deste edital, cria os novos e apaga os ausentes.
     * As categorias de cada documento chegam como posições e são traduzidas pelo mapa posição → id.
     *
     * @param  list<array{id: ?int, atributos: array<string, mixed>, categorias: list<int>}>  $documentos
     * @param  list<int>  $categoriasPorPosicao
     */
    private function sincronizarDocumentos(Edital $edital, array $documentos, array $categoriasPorPosicao): void
    {
        $existentes = $edital->documentos()->get()->keyBy(fn (EditalDocumento $documento): int => (int) $documento->id);
        $mantidos = [];

        foreach ($documentos as ['id' => $id, 'atributos' => $atributos, 'categorias' => $posicoes]) {
            $existente = $id !== null ? $existentes->get($id) : null;

            if ($existente !== null && ! in_array($id, $mantidos, true)) {
                $existente->update($atributos);
                $documento = $existente;
            } else {
                $documento = $edital->documentos()->create($atributos);
            }

            $mantidos[] = (int) $documento->id;
            $documento->categorias()->sync(array_map(fn (int $posicao): int => $categoriasPorPosicao[$posicao], $posicoes));
        }

        $existentes->except($mantidos)->each(fn (EditalDocumento $documento): ?bool => $documento->delete());
    }

    /**
     * Posições das categorias são as da lista `categorias` enviada no mesmo edit.
     *
     * @return list<array{id: int, nome: string, orientacao: ?string, obrigatorio: bool, naturezas: list<string>, todas_categorias: bool, categorias: list<int>, tamanho_max_mb: int}>
     */
    private function documentosParaEdicao(Edital $edital): array
    {
        $posicaoPorCategoria = $edital->categorias->values()->mapWithKeys(
            fn (EditalCategoria $categoria, int $posicao): array => [(int) $categoria->id => $posicao],
        );

        return $edital->documentos()->with('categorias')->get()->map(fn (EditalDocumento $documento): array => [
            'id' => (int) $documento->id,
            'nome' => $documento->nome,
            'orientacao' => $documento->orientacao,
            'obrigatorio' => $documento->obrigatorio,
            'naturezas' => $documento->naturezas->map(fn (NaturezaProponente $natureza): string => $natureza->value)->values()->all(),
            'todas_categorias' => $documento->todas_categorias,
            'categorias' => $documento->categorias
                ->map(fn (EditalCategoria $categoria): ?int => $posicaoPorCategoria->get((int) $categoria->id))
                ->filter(fn (?int $posicao): bool => $posicao !== null)
                ->sort()
                ->values()
                ->all(),
            'tamanho_max_mb' => $documento->tamanho_max_mb,
        ])->all();
    }

    /**
     * @return list<array{valor: string, rotulo: string}>
     */
    private function naturezas(): array
    {
        return array_map(
            fn (NaturezaProponente $natureza): array => ['valor' => $natureza->value, 'rotulo' => $natureza->rotulo()],
            NaturezaProponente::cases(),
        );
    }

    /**
     * @return list<array{valor: string, rotulo: string}>
     */
    private function tiposFomento(): array
    {
        return array_map(
            fn (TipoFomento $tipo): array => ['valor' => $tipo->value, 'rotulo' => $tipo->rotulo()],
            TipoFomento::cases(),
        );
    }

    private function nomeDoPdf(Edital $edital): string
    {
        return "edital-{$edital->slug}.pdf";
    }
}
