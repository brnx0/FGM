<?php

namespace App\Support\EditalIa;

use App\Enums\ProvedorIa;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SensitiveParameter;

/**
 * Análise do edital pela API REST do Google Gemini (generateContent, sem SDK): o PDF vai em inline_data e a resposta
 * fica presa ao esquema (responseSchema). A chave vai só no cabeçalho x-goog-api-key, nunca na URL.
 */
class AnalisadorGemini extends AnalisadorPorHttp
{
    private const string URL_MODELOS = 'https://generativelanguage.googleapis.com/v1beta/models/';

    protected function provedor(): ProvedorIa
    {
        return ProvedorIa::Gemini;
    }

    protected function corpo(string $modelo, string $pdfBinario, string $prompt, ?float $temperatura, array $esquema): array
    {
        return [
            'contents' => [[
                'parts' => [
                    ['inline_data' => ['mime_type' => 'application/pdf', 'data' => base64_encode($pdfBinario)]],
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => array_filter([
                'temperature' => $temperatura,
                'responseMimeType' => 'application/json',
                'responseSchema' => $esquema,
            ], fn (mixed $valor): bool => $valor !== null),
        ];
    }

    protected function enviar(PendingRequest $pedido, string $modelo, #[SensitiveParameter] string $chave, array $corpo): Response
    {
        return $pedido->withHeaders(['x-goog-api-key' => $chave])
            ->post(self::URL_MODELOS.rawurlencode($modelo).':generateContent', $corpo);
    }

    protected function temperaturaRecusada(Response $resposta): bool
    {
        $mensagem = $resposta->json('error.message');

        return $resposta->status() === 400 && is_string($mensagem) && str_contains(strtolower($mensagem), 'temperature');
    }

    protected function texto(Response $resposta): mixed
    {
        return $resposta->json('candidates.0.content.parts.0.text');
    }

    protected function tokens(Response $resposta): array
    {
        return [
            'entrada' => (int) $resposta->json('usageMetadata.promptTokenCount', 0),
            'saida' => (int) $resposta->json('usageMetadata.candidatesTokenCount', 0),
            'total' => (int) $resposta->json('usageMetadata.totalTokenCount', 0),
        ];
    }
}
