<?php

namespace App\Support\EditalIa;

use App\Enums\ProvedorIa;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SensitiveParameter;

/**
 * Análise do edital pela Responses API da OpenAI: o PDF vai como arquivo na própria chamada (input_file em base64) e
 * a resposta fica presa ao esquema (saída estruturada, json_schema strict, ver EsquemaOpenAi). store: false para a
 * OpenAI não guardar o edital no histórico da conta. A chave vai só no cabeçalho Authorization.
 */
class AnalisadorOpenAi extends AnalisadorPorHttp
{
    private const string URL_RESPOSTAS = 'https://api.openai.com/v1/responses';

    protected function provedor(): ProvedorIa
    {
        return ProvedorIa::OpenAi;
    }

    protected function corpo(string $modelo, string $pdfBinario, string $prompt, ?float $temperatura, array $esquema): array
    {
        return array_filter([
            'model' => $modelo,
            'store' => false,
            'input' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_file', 'filename' => 'edital.pdf', 'file_data' => 'data:application/pdf;base64,'.base64_encode($pdfBinario)],
                    ['type' => 'input_text', 'text' => $prompt],
                ],
            ]],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'edital', 'strict' => true, 'schema' => EsquemaOpenAi::de($esquema)]],
            'temperature' => $temperatura,
        ], fn (mixed $valor): bool => $valor !== null);
    }

    protected function enviar(PendingRequest $pedido, string $modelo, #[SensitiveParameter] string $chave, array $corpo): Response
    {
        return $pedido->withToken($chave)->post(self::URL_RESPOSTAS, $corpo);
    }

    /** Modelos de raciocínio recusam a temperatura com 400 e param: temperature. */
    protected function temperaturaRecusada(Response $resposta): bool
    {
        return $resposta->status() === 400 && $resposta->json('error.param') === 'temperature';
    }

    /** Os textos de saída (output_text) das mensagens do modelo, juntos. */
    protected function texto(Response $resposta): mixed
    {
        $textos = [];

        foreach ((array) $resposta->json('output', []) as $item) {
            if (! is_array($item) || ($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ((array) ($item['content'] ?? []) as $parte) {
                if (is_array($parte) && ($parte['type'] ?? null) === 'output_text' && is_string($parte['text'] ?? null)) {
                    $textos[] = $parte['text'];
                }
            }
        }

        return $textos === [] ? null : implode('', $textos);
    }

    protected function tokens(Response $resposta): array
    {
        return [
            'entrada' => (int) $resposta->json('usage.input_tokens', 0),
            'saida' => (int) $resposta->json('usage.output_tokens', 0),
            'total' => (int) $resposta->json('usage.total_tokens', 0),
        ];
    }
}
