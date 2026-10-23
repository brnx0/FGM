<?php

namespace App\Auth\GovBr;

use App\Support\Cpf;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente OIDC do Login Único gov.br (Authorization Code + PKCE S256).
 * Roteiro: https://roteiro-tecnico.acesso.gov.br/iniciarintegracao.html
 */
class GovBrClient
{
    private const LEEWAY_SEGUNDOS = 60;

    public function authorizationUrl(string $state, string $nonce, string $codeVerifier): string
    {
        return $this->endpoint('authorize').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('govbr.client_id'),
            'scope' => config('govbr.scopes'),
            'redirect_uri' => config('govbr.redirect_uri'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => JWT::urlsafeB64Encode(hash('sha256', $codeVerifier, true)),
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{id_token: string, access_token: ?string} */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        try {
            $resposta = Http::asForm()
                ->acceptJson()
                ->timeout(10)
                ->withBasicAuth((string) config('govbr.client_id'), (string) config('govbr.client_secret'))
                ->post($this->endpoint('token'), [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => config('govbr.redirect_uri'),
                    'code_verifier' => $codeVerifier,
                ]);
        } catch (ConnectionException) {
            throw new GovBrException('Falha de conexão com o endpoint token do gov.br.');
        }

        if (! $resposta->successful()) {
            throw new GovBrException("Endpoint token do gov.br respondeu HTTP {$resposta->status()}.");
        }

        $idToken = $resposta->json('id_token');
        if (! is_string($idToken) || $idToken === '') {
            throw new GovBrException('Resposta do endpoint token do gov.br sem id_token.');
        }

        $accessToken = $resposta->json('access_token');

        return ['id_token' => $idToken, 'access_token' => is_string($accessToken) ? $accessToken : null];
    }

    public function identityFromIdToken(string $idToken, string $nonce): GovBrIdentity
    {
        $claims = $this->decodificar($idToken);

        $issEsperado = rtrim((string) config('govbr.base_url'), '/');
        if (! is_string($claims->iss ?? null) || rtrim($claims->iss, '/') !== $issEsperado) {
            throw new GovBrException('id_token com iss inesperado.');
        }

        $aud = $claims->aud ?? null;
        $clientId = (string) config('govbr.client_id');
        if (! ($aud === $clientId || (is_array($aud) && in_array($clientId, $aud, true)))) {
            throw new GovBrException('id_token com aud inesperado.');
        }

        if (! isset($claims->exp)) {
            throw new GovBrException('id_token sem exp.');
        }

        if (! is_string($claims->nonce ?? null) || ! hash_equals($nonce, $claims->nonce)) {
            throw new GovBrException('id_token com nonce inválido.');
        }

        $sub = is_scalar($claims->sub ?? null) ? (string) $claims->sub : '';
        if ($sub === '' || ! Cpf::valido($sub)) {
            throw new GovBrException('id_token com sub que não é um CPF válido.');
        }

        $nome = is_string($claims->name ?? null) ? trim($claims->name) : '';
        if ($nome === '') {
            throw new GovBrException('id_token sem name.');
        }

        return new GovBrIdentity(
            cpf: Cpf::normalizar($sub),
            nome: $nome,
            email: is_string($claims->email ?? null) && $claims->email !== '' ? $claims->email : null,
            emailVerificado: ($claims->email_verified ?? false) === true,
            telefone: is_string($claims->phone_number ?? null) && $claims->phone_number !== '' ? $claims->phone_number : null,
        );
    }

    public function logoutUrl(): string
    {
        return $this->endpoint('logout').'?'.http_build_query([
            'post_logout_redirect_uri' => config('govbr.logout_redirect_uri'),
        ]);
    }

    /** Verifica assinatura (só RS256, chaves do JWKS), exp/iat/nbf com tolerância de 60s. */
    private function decodificar(string $idToken): object
    {
        $partes = explode('.', $idToken);
        $cabecalho = count($partes) === 3 ? json_decode(JWT::urlsafeB64Decode($partes[0]), true) : null;
        if (! is_array($cabecalho) || ($cabecalho['alg'] ?? null) !== 'RS256') {
            throw new GovBrException('id_token malformado ou com algoritmo diferente de RS256.');
        }

        $leewayAnterior = JWT::$leeway;
        JWT::$leeway = self::LEEWAY_SEGUNDOS;

        try {
            return JWT::decode($idToken, $this->chavesPublicas());
        } catch (GovBrException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GovBrException('id_token inválido: '.$e->getMessage());
        } finally {
            JWT::$leeway = $leewayAnterior;
        }
    }

    /** @return array<string, Key> */
    private function chavesPublicas(): array
    {
        try {
            $jwks = Cache::remember('govbr.jwks', now()->addHour(), function () {
                $jwks = Http::acceptJson()->timeout(10)->get($this->endpoint('jwks'))->throw()->json();
                if (! is_array($jwks) || ! is_array($jwks['keys'] ?? null)) {
                    throw new GovBrException('JWKS do gov.br sem "keys".');
                }

                return $jwks;
            });

            // Chaves sem "alg" são tratadas como RS256; o cabeçalho do token já foi restrito a RS256.
            return JWK::parseKeySet($jwks, 'RS256');
        } catch (GovBrException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GovBrException('Falha ao obter o JWKS do gov.br: '.class_basename($e));
        }
    }

    private function endpoint(string $nome): string
    {
        return rtrim((string) config('govbr.base_url'), '/').config("govbr.endpoints.{$nome}");
    }
}
