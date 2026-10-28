<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class EventoController extends Controller
{
    /** Evento publicado abre mesmo antes da data: pode ser divulgado com antecedência. */
    public function show(Evento $evento): Response
    {
        abort_unless($evento->publicado, 404);

        return Inertia::render('Eventos/Show', ['evento' => $this->dados($evento)]);
    }

    /** Os mesmos dados da página, em JSON, para o modal dos cards da página inicial (que não lê a descrição). */
    public function detalhes(Evento $evento): JsonResponse
    {
        abort_unless($evento->publicado, 404);

        return response()->json($this->dados($evento));
    }

    /**
     * @return array{titulo: string, resumo: string, descricao: ?string, dataRealizacao: string, diaSemana: string, horario: ?string, local: ?string, endereco: ?string, latitude: ?float, longitude: ?float, imagem: ?string, url: string}
     */
    private function dados(Evento $evento): array
    {
        $data = $evento->data_realizacao->locale('pt_BR');

        return [
            'titulo' => $evento->titulo,
            'resumo' => $evento->resumo,
            // Já sanitizada ao salvar (App\Support\HtmlSeguro).
            'descricao' => $evento->descricao,
            'dataRealizacao' => $data->translatedFormat('j \d\e F \d\e Y'),
            'diaSemana' => $data->translatedFormat('l'),
            'horario' => $evento->horarioPorExtenso(),
            'local' => $evento->local,
            'endereco' => $evento->endereco,
            // Pin marcado no mapa pela retaguarda; sem ele, nada de mapa.
            'latitude' => $evento->temLocalizacao() ? $evento->latitude : null,
            'longitude' => $evento->temLocalizacao() ? $evento->longitude : null,
            'imagem' => $evento->imagemUrl(),
            'url' => route('eventos.show', $evento),
        ];
    }
}
