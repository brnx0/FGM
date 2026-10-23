<?php

namespace App\Http\Controllers;

use App\Models\Edital;
use Inertia\Inertia;
use Inertia\Response;

class EditalController extends Controller
{
    public function show(Edital $edital): Response
    {
        abort_unless($edital->publicado, 404);

        $situacao = $edital->situacao();

        return Inertia::render('Editais/Show', [
            'edital' => [
                'titulo' => $edital->titulo,
                'slug' => $edital->slug,
                'categoria' => $edital->categoria,
                'resumo' => $edital->resumo,
                'situacao' => $situacao->value,
                'situacaoRotulo' => $situacao->rotulo(),
                'inscricoesInicio' => $edital->inscricoes_inicio->format('d/m/Y'),
                'inscricoesFim' => $edital->inscricoes_fim->format('d/m/Y'),
            ],
        ]);
    }
}
