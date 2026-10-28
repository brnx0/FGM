<?php

namespace App\Http\Controllers;

use App\Models\Edital;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditalController extends Controller
{
    public function show(Edital $edital): Response
    {
        abort_unless($edital->estaVisivel(), 404);

        $situacao = $edital->situacao();

        return Inertia::render('Editais/Show', [
            'edital' => [
                'titulo' => $edital->titulo,
                'slug' => $edital->slug,
                'categoria' => $edital->categoria,
                'divulgacao' => $edital->divulgacao,
                'pdf' => $edital->pdfUrl(),
                'situacao' => $situacao->value,
                'situacaoRotulo' => $situacao->rotulo(),
                'inscricoesInicio' => $edital->inscricoes_inicio->format('d/m/Y'),
                'inscricoesFim' => $edital->inscricoes_fim->format('d/m/Y'),
            ],
        ]);
    }

    public function pdf(Edital $edital): StreamedResponse
    {
        abort_unless($edital->estaVisivel() && Storage::disk('local')->exists($edital->pdf), 404);

        return Storage::disk('local')->download($edital->pdf, "edital-{$edital->slug}.pdf");
    }
}
