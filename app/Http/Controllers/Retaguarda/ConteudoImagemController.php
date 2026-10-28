<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Support\ArquivoArmazenado;
use App\Support\HtmlSeguro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Imagem inserida no editor de texto rico (eventos e editais). Devolve a URL relativa, a única que HtmlSeguro aceita.
 * Imagens que deixam de ser usadas (ou de registros não salvos) ficam no disco.
 */
class ConteudoImagemController extends Controller
{
    /** Pasta do disco public; HtmlSeguro::PASTA_IMAGENS é o endereço dela. */
    private const string PASTA = 'eventos/conteudo';

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'imagem' => ['required', 'image', 'mimes:jpg,png,webp', 'max:5120'],
        ]);

        $caminho = ArquivoArmazenado::guardar($request->file('imagem'), 'public', self::PASTA);

        return response()->json(['url' => HtmlSeguro::PASTA_IMAGENS.basename($caminho)]);
    }
}
