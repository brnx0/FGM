<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use Inertia\Inertia;
use Inertia\Response;

class EventoController extends Controller
{
    /** Evento publicado abre mesmo antes da data: pode ser divulgado com antecedência. */
    public function show(Evento $evento): Response
    {
        abort_unless($evento->publicado, 404);

        return Inertia::render('Eventos/Show', [
            'evento' => [
                'titulo' => $evento->titulo,
                'resumo' => $evento->resumo,
                // Já sanitizada ao salvar (App\Support\HtmlSeguro).
                'descricao' => $evento->descricao,
                'dataRealizacao' => $evento->data_realizacao->locale('pt_BR')->translatedFormat('j \d\e F \d\e Y'),
                'local' => $evento->local,
                'imagem' => $evento->imagemUrl(),
            ],
        ]);
    }
}
