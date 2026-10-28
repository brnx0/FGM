<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\SalvarEventoRequest;
use App\Models\Evento;
use App\Support\ArquivoArmazenado;
use App\Support\SlugUnico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Gestão de eventos na retaguarda. Imagem no disco public, em eventos/. */
class EventoController extends Controller
{
    private const string PASTA = 'eventos';

    public function index(Request $request): Response
    {
        $busca = trim((string) $request->query('busca', ''));
        $publicado = in_array($request->query('publicado'), ['sim', 'nao'], true) ? $request->query('publicado') : null;

        $eventos = Evento::query()
            // Sem a descrição (CLOB): a lista não a mostra.
            ->select(['id', 'slug', 'titulo', 'local', 'data_realizacao', 'publicado', 'imagem'])
            // No Oracle a comparação de texto diferencia maiúsculas; % e _ digitados valem literalmente.
            ->when($busca !== '', fn (Builder $q) => $q->whereRaw(
                "LOWER(titulo) LIKE ? ESCAPE '\\'",
                ['%'.addcslashes(mb_strtolower($busca), '\\%_').'%'],
            ))
            ->when($publicado, fn (Builder $q) => $q->where('publicado', $publicado === 'sim'))
            ->orderByDesc('data_realizacao')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Evento $evento): array => [
                'slug' => $evento->slug,
                'titulo' => $evento->titulo,
                'local' => $evento->local,
                'data' => $evento->data_realizacao->format('d/m/Y'),
                'publicado' => $evento->publicado,
                'temImagem' => filled($evento->imagem),
            ]);

        return Inertia::render('Retaguarda/Eventos/Index', [
            'eventos' => $eventos,
            'filtros' => [
                'busca' => $busca,
                'publicado' => $publicado ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Retaguarda/Eventos/Form', ['evento' => null]);
    }

    public function store(SalvarEventoRequest $request): RedirectResponse
    {
        $evento = new Evento($request->dadosDoEvento());
        $evento->slug = SlugUnico::para(Evento::class, $evento->titulo);

        $novos = [];
        if ($request->hasFile('imagem')) {
            $evento->imagem = ArquivoArmazenado::guardar($request->file('imagem'), 'public', self::PASTA);
            $novos[] = ['public', $evento->imagem];
        }

        ArquivoArmazenado::salvarTrocando($evento, $novos, []);

        return redirect()->route('retaguarda.eventos.index')->with('sucesso', 'Evento salvo.');
    }

    public function edit(Evento $evento): Response
    {
        return Inertia::render('Retaguarda/Eventos/Form', [
            'evento' => [
                'slug' => $evento->slug,
                'titulo' => $evento->titulo,
                'resumo' => $evento->resumo,
                'descricao' => $evento->descricao,
                'data_realizacao' => $evento->data_realizacao->toDateString(),
                'hora_inicio' => $evento->hora_inicio,
                'hora_fim' => $evento->hora_fim,
                'local' => $evento->local,
                'endereco' => $evento->endereco,
                'latitude' => $evento->latitude,
                'longitude' => $evento->longitude,
                'publicado' => $evento->publicado,
                'imagemUrl' => $evento->imagemUrl(),
            ],
        ]);
    }

    /** O slug não muda: links já divulgados continuam valendo. */
    public function update(SalvarEventoRequest $request, Evento $evento): RedirectResponse
    {
        $evento->fill($request->dadosDoEvento());

        $novos = [];
        $antigos = [];
        if ($request->hasFile('imagem')) {
            $antigos[] = ['public', $evento->imagem];
            $evento->imagem = ArquivoArmazenado::guardar($request->file('imagem'), 'public', self::PASTA);
            $novos[] = ['public', $evento->imagem];
        } elseif ($request->boolean('remover_imagem') && $evento->imagem) {
            $antigos[] = ['public', $evento->imagem];
            $evento->imagem = null;
        }

        ArquivoArmazenado::salvarTrocando($evento, $novos, $antigos);

        return redirect()->route('retaguarda.eventos.index')->with('sucesso', 'Evento salvo.');
    }

    /** Exclusão lógica: a imagem fica no disco. */
    public function destroy(Evento $evento): RedirectResponse
    {
        $evento->delete();

        return redirect()->route('retaguarda.eventos.index')->with('sucesso', 'Evento excluído.');
    }

    public function publicacao(Evento $evento): RedirectResponse
    {
        $evento->update(['publicado' => ! $evento->publicado]);

        return back()->with('sucesso', $evento->publicado ? 'Evento publicado.' : 'Evento despublicado.');
    }
}
