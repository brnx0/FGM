<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Support\ConsultaEndereco;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Coordenadas do endereço do evento para o pin do mapa (JSON, chamado pelo formulário de evento). */
class LocalizacaoEventoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $parametro = $request->query('endereco', '');
        $endereco = is_string($parametro) ? trim($parametro) : '';

        if (mb_strlen($endereco) < 3 || mb_strlen($endereco) > 255) {
            return response()->json(['message' => 'Informe o endereço ou o local do evento.'], 422);
        }

        $dados = ConsultaEndereco::buscar($endereco);

        return response()->json($dados === null ? ['encontrado' => false] : ['encontrado' => true, 'dados' => $dados]);
    }
}
