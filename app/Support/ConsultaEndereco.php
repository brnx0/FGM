<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Coordenadas de um endereço no Nominatim (OpenStreetMap), para o pin do mapa dos eventos.
 * Nunca lança exceção: falha equivale a "não encontrado". Só o que foi encontrado fica em cache,
 * e a política do Nominatim pede um User-Agent que identifique o site.
 */
final class ConsultaEndereco
{
    private const string URL = 'https://nominatim.openstreetmap.org/search';

    /** Salvador como preferência (não limita a busca): "Teatro Gregório de Mattos" acha o daqui. */
    private const string VIEWBOX_SALVADOR = '-38.70,-12.75,-38.30,-13.02';

    /** @return array{latitude: float, longitude: float, descricao: ?string}|null */
    public static function buscar(string $endereco): ?array
    {
        $chave = 'consulta-endereco:'.sha1(mb_strtolower(preg_replace('/\s+/', ' ', trim($endereco)) ?? ''));

        /** @var array{latitude: float, longitude: float, descricao: ?string}|null $emCache */
        $emCache = Cache::get($chave);
        if ($emCache !== null) {
            return $emCache;
        }

        try {
            $resposta = Http::acceptJson()
                ->timeout(5)
                ->withUserAgent(sprintf('%s (%s)', config('app.name'), config('app.url')))
                ->get(self::URL, [
                    'q' => $endereco,
                    'format' => 'jsonv2',
                    'limit' => 1,
                    'countrycodes' => 'br',
                    'accept-language' => 'pt-BR',
                    'viewbox' => self::VIEWBOX_SALVADOR,
                    'bounded' => 0,
                ]);

            if (! $resposta->successful()) {
                return null;
            }

            /** @var mixed $json */
            $json = json_decode($resposta->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (ConnectionException|RequestException|JsonException) {
            return null;
        }

        $lugar = is_array($json) ? ($json[0] ?? null) : null;
        if (! is_array($lugar) || ! is_numeric($lugar['lat'] ?? null) || ! is_numeric($lugar['lon'] ?? null)) {
            return null;
        }

        $dados = [
            'latitude' => (float) $lugar['lat'],
            'longitude' => (float) $lugar['lon'],
            'descricao' => is_string($lugar['display_name'] ?? null) ? $lugar['display_name'] : null,
        ];

        Cache::put($chave, $dados, now()->addDays(30));

        return $dados;
    }
}
