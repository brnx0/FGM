<?php

namespace App\Http\Controllers\Retaguarda;

use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\GerarInstrucoesRequest;
use App\Support\DocumentoIa\GeradorDeInstrucoes;
use App\Support\EditalIa\AnaliseComRedundancia;
use App\Support\FalhaNaIa;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Instruções da IA para os documentos do edital ("Gerar com IA" num documento, "Gerar instruções de todos" na aba).
 * Responde JSON e não grava nada: as instruções voltam ao formulário e só ficam quando a equipe salva o edital.
 */
class InstrucaoDocumentoController extends Controller
{
    public function __invoke(GerarInstrucoesRequest $request, GeradorDeInstrucoes $gerador): JsonResponse
    {
        try {
            $instrucoes = $gerador->gerar($request->edital(), $request->documentos());
        } catch (FalhaNaIa $falha) {
            // A equipe vê só a mensagem geral; o motivo de cada provedor (sem a chave, ver FalhaNaIa) fica para o administrador.
            Log::warning('Instruções por IA: a geração falhou.', ['motivo' => $falha->getMessage()]);

            return response()->json(['erro' => AnaliseComRedundancia::INDISPONIVEL], 422);
        }

        return response()->json(['instrucoes' => $instrucoes]);
    }
}
