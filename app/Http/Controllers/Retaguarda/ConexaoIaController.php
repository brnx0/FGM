<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\ChamarProvedorIaRequest;
use App\Support\ClienteIa;
use App\Support\FalhaNaIa;
use Illuminate\Http\JsonResponse;

/**
 * "Buscar" modelos e "Testar conexão" da Integração com IA. O servidor fala com o provedor (o navegador não pode, pela CSP)
 * e responde JSON; falha do provedor volta com 200 e ok: false, com o motivo para mostrar ao gestor.
 */
class ConexaoIaController extends Controller
{
    public function modelos(ChamarProvedorIaRequest $request): JsonResponse
    {
        try {
            $modelos = ClienteIa::modelos($request->provedor(), $request->chave());
        } catch (FalhaNaIa $falha) {
            return response()->json(['ok' => false, 'mensagem' => $falha->getMessage()]);
        }

        return response()->json(['ok' => true, 'modelos' => $modelos]);
    }

    public function teste(ChamarProvedorIaRequest $request): JsonResponse
    {
        $provedor = $request->provedor();
        $modelo = $request->validated('modelo');

        try {
            $segundos = ClienteIa::testar($provedor, $request->chave(), $modelo);
        } catch (FalhaNaIa $falha) {
            return response()->json(['ok' => false, 'mensagem' => $falha->getMessage()]);
        }

        return response()->json([
            'ok' => true,
            'mensagem' => sprintf('Conexão funcionando: %s respondeu em %s s com o modelo %s.', $provedor->rotulo(), number_format($segundos, 1, ',', '.'), $modelo),
        ]);
    }
}
