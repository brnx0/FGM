<?php

namespace App\Http\Controllers;

use App\Models\Edital;
use App\Models\Evento;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const LIMITE_EVENTOS = 6;

    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'totalAbertos' => Edital::contarAbertos(),
            'editais' => Edital::naVitrine()->get()->map(fn (Edital $edital) => [
                'titulo' => $edital->titulo,
                'slug' => $edital->slug,
                'categoria' => $edital->categoria,
                'situacao' => $edital->situacao()->value,
                'situacaoRotulo' => $edital->situacao()->rotulo(),
                'inscricoesInicio' => $edital->inscricoes_inicio->format('d/m/Y'),
                'inscricoesFim' => $edital->inscricoes_fim->format('d/m/Y'),
            ])->all(),
            'eventos' => Evento::realizados()->limit(self::LIMITE_EVENTOS)->get()->map(fn (Evento $evento) => [
                'titulo' => $evento->titulo,
                'resumo' => $evento->resumo,
                // "agosto de 2026"
                'dataRealizacao' => $evento->data_realizacao->locale('pt_BR')->translatedFormat('F \d\e Y'),
                'local' => $evento->local,
                'imagem' => $evento->imagem,
            ])->all(),
        ]);
    }
}
