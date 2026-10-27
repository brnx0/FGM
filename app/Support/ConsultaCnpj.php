<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Consulta de CNPJ na BrasilAPI, mapeada para os campos do formulário de organização.
 * Nunca lança exceção: qualquer falha equivale a "não encontrado" e o usuário preenche à mão.
 */
final class ConsultaCnpj
{
    private const string URL = 'https://brasilapi.com.br/api/cnpj/v1/';

    /**
     * @return array{
     *     razao_social: ?string, nome_fantasia: ?string, natureza_juridica: ?string, finalidade_lucrativa: ?bool,
     *     mei: bool, porte: ?string, data_fundacao: ?string, telefone: ?string, cep: ?string, logradouro: ?string,
     *     numero: ?string, sem_numero: bool, complemento: ?string, bairro: ?string, cidade: ?string, uf: ?string,
     *     municipio: ?array{codigo: string, nome: string, uf: string}
     * }|null
     */
    public static function buscar(string $cnpj): ?array
    {
        $chave = 'consulta-cnpj:'.$cnpj;

        /** @var array<string, mixed>|null $emCache */
        $emCache = Cache::get($chave);
        if ($emCache !== null) {
            return $emCache;
        }

        try {
            $resposta = Http::acceptJson()->timeout(5)->get(self::URL.$cnpj);

            if (! $resposta->successful()) {
                return null;
            }

            /** @var mixed $json */
            $json = json_decode($resposta->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (ConnectionException|RequestException|JsonException) {
            return null;
        }

        if (! is_array($json)) {
            return null;
        }

        $dados = self::mapear($json);
        Cache::put($chave, $dados, now()->addDay());

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private static function mapear(array $json): array
    {
        $codigoNatureza = isset($json['codigo_natureza_juridica'])
            ? str_pad((string) $json['codigo_natureza_juridica'], 4, '0', STR_PAD_LEFT)
            : null;
        $naturezaConhecida = $codigoNatureza !== null && array_key_exists($codigoNatureza, NaturezasJuridicas::TODAS);

        $numero = self::texto($json['numero'] ?? null);
        $semNumero = $numero !== null && in_array(strtoupper($numero), ['S/N', 'SN', 'S N'], true);

        $telefone = preg_replace('/\D/', '', (string) ($json['ddd_telefone_1'] ?? ''));

        $cidade = self::texto($json['municipio'] ?? null);
        $uf = self::texto($json['uf'] ?? null);

        return [
            'razao_social' => self::texto($json['razao_social'] ?? null),
            'nome_fantasia' => self::texto($json['nome_fantasia'] ?? null),
            'natureza_juridica' => $naturezaConhecida ? $codigoNatureza : null,
            'finalidade_lucrativa' => $naturezaConhecida ? NaturezasJuridicas::sugereFinalidadeLucrativa($codigoNatureza) : null,
            'mei' => ($json['opcao_pelo_mei'] ?? null) === true,
            'porte' => match (strtoupper((string) ($json['porte'] ?? ''))) {
                'MICRO EMPRESA' => 'micro',
                'EMPRESA DE PEQUENO PORTE' => 'pequeno',
                default => null,
            },
            'data_fundacao' => self::data($json['data_inicio_atividade'] ?? null),
            'telefone' => $telefone === '' || $telefone === null ? null : $telefone,
            'cep' => self::texto(preg_replace('/\D/', '', (string) ($json['cep'] ?? ''))),
            'logradouro' => self::texto(trim(($json['descricao_tipo_de_logradouro'] ?? '').' '.($json['logradouro'] ?? ''))),
            'numero' => $semNumero ? null : $numero,
            'sem_numero' => $semNumero,
            'complemento' => self::texto($json['complemento'] ?? null),
            'bairro' => self::texto($json['bairro'] ?? null),
            'cidade' => $cidade,
            'uf' => $uf,
            'municipio' => self::municipio($cidade, $uf),
        ];
    }

    /**
     * Nome exato (sem acento e sem diferença de caixa) dentro da UF; a busca por prefixo não basta.
     *
     * @return array{codigo: string, nome: string, uf: string}|null
     */
    private static function municipio(?string $nome, ?string $uf): ?array
    {
        if ($nome === null || $uf === null) {
            return null;
        }

        $alvo = self::semAcento($nome);
        foreach (Municipios::buscar($nome, 100) as $municipio) {
            if ($municipio['uf'] === strtoupper($uf) && self::semAcento($municipio['nome']) === $alvo) {
                return $municipio;
            }
        }

        return null;
    }

    private static function semAcento(string $texto): string
    {
        $convertido = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $texto);

        return trim($convertido === false ? mb_strtolower($texto) : $convertido);
    }

    private static function texto(mixed $valor): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private static function data(mixed $valor): ?string
    {
        $texto = self::texto($valor);
        if ($texto === null || preg_match('/^\d{4}-\d{2}-\d{2}/', $texto) !== 1) {
            return null;
        }

        return substr($texto, 0, 10);
    }
}
