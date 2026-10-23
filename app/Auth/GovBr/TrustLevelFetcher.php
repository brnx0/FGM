<?php

namespace App\Auth\GovBr;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta opcional do nível de confiabilidade da conta gov.br.
 * GOVBR_TRUST_LEVEL_URL vazio = não consulta. A URL aceita o marcador {cpf}.
 * Espera uma lista JSON de níveis (itens com "id" ou valores simples) e devolve o maior.
 * Nunca lança exceção: o login não depende deste dado.
 */
class TrustLevelFetcher
{
    public function buscar(string $cpf, string $accessToken): ?string
    {
        $url = (string) config('govbr.trust_level_url');
        if ($url === '') {
            return null;
        }

        try {
            $resposta = Http::acceptJson()
                ->timeout(5)
                ->withToken($accessToken)
                ->get(str_replace('{cpf}', $cpf, $url));

            if (! $resposta->successful()) {
                Log::warning('gov.br: consulta de nível de confiabilidade falhou.', ['status' => $resposta->status()]);

                return null;
            }

            $niveis = collect((array) $resposta->json())
                ->map(fn ($item) => is_array($item) ? ($item['id'] ?? null) : $item)
                ->filter(fn ($nivel) => is_scalar($nivel) && (string) $nivel !== '')
                ->map(fn ($nivel) => (string) $nivel);

            if ($niveis->isEmpty()) {
                Log::warning('gov.br: resposta de nível de confiabilidade sem níveis reconhecíveis.');

                return null;
            }

            return $niveis->sortBy(fn ($n) => $n, SORT_NATURAL)->last();
        } catch (Throwable $e) {
            Log::warning('gov.br: erro ao consultar nível de confiabilidade.', ['erro' => class_basename($e)]);

            return null;
        }
    }
}
