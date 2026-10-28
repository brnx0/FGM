<?php

namespace App\Http\Controllers\Retaguarda;

use App\Enums\SituacaoEdital;
use App\Enums\TipoFomento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\SalvarEditalRequest;
use App\Models\Edital;
use App\Support\ArquivoArmazenado;
use App\Support\SlugUnico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        ArquivoArmazenado::salvarTrocando($edital, $novos, []);

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
            ],
            'tiposFomento' => $this->tiposFomento(),
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

        ArquivoArmazenado::salvarTrocando($edital, $novos, $antigos);

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
