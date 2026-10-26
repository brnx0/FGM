<?php

namespace App\Http\Controllers;

use App\Models\Edital;
use App\Support\EventosMock;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    private const LIMITE_EVENTOS = 6;

    public function __invoke(EventosMock $eventos): Response
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
            'eventos' => array_map(function (array $evento): array {
                $data = CarbonImmutable::parse($evento['data_realizacao'])->locale('pt_BR');

                return [
                    'titulo' => $evento['titulo'],
                    'resumo' => $evento['resumo'],
                    // "agosto" e "2026": a home mostra "AGO / 2026".
                    'mes' => $data->translatedFormat('F'),
                    'ano' => $data->format('Y'),
                    'local' => $evento['local'],
                ];
            }, $eventos->realizados(self::LIMITE_EVENTOS)),
        ]);
    }
}
