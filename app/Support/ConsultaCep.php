<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use JsonException;

/** Consulta de CEP no ViaCEP. Nunca lança exceção: falha equivale a "não encontrado". */
final class ConsultaCep
{
    private const string URL = 'https://viacep.com.br/ws/';

    /** @return array{logradouro: ?string, bairro: ?string, cidade: ?string, uf: ?string}|null */
    public static function buscar(string $cep): ?array
    {
        try {
            $resposta = Http::acceptJson()->timeout(3)->get(self::URL.$cep.'/json/');

            if (! $resposta->successful()) {
                return null;
            }

            /** @var mixed $json */
            $json = json_decode($resposta->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (ConnectionException|RequestException|JsonException) {
            return null;
        }

        if (! is_array($json) || isset($json['erro'])) {
            return null;
        }

        return [
            'logradouro' => self::texto($json['logradouro'] ?? null),
            'bairro' => self::texto($json['bairro'] ?? null),
            'cidade' => self::texto($json['localidade'] ?? null),
            'uf' => self::texto($json['uf'] ?? null),
        ];
    }

    private static function texto(mixed $valor): ?string
    {
        $texto = is_scalar($valor) ? trim((string) $valor) : '';

        return $texto === '' ? null : $texto;
    }
}
