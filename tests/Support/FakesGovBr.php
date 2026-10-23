<?php

namespace Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Simula o provedor gov.br: um par RSA gerado no teste, o JWKS publicado em /jwk
 * e um /token que devolve id_token assinado em RS256.
 */
trait FakesGovBr
{
    protected const GOVBR_BASE = 'https://sso.staging.acesso.gov.br';

    protected const GOVBR_KID = 'chave-teste';

    /** @var \OpenSSLAsymmetricKey */
    protected $govbrChavePrivada;

    protected function setUpFakesGovBr(): void
    {
        config([
            'govbr.driver' => 'govbr',
            'govbr.client_id' => 'teste',
            'govbr.client_secret' => 'segredo',
            'govbr.base_url' => self::GOVBR_BASE,
            'govbr.redirect_uri' => 'http://localhost/auth/govbr/callback',
            'govbr.logout_redirect_uri' => 'http://localhost/auth/govbr/logout',
            'govbr.trust_level_url' => null,
        ]);

        $this->govbrChavePrivada = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $rsa = openssl_pkey_get_details($this->govbrChavePrivada)['rsa'];

        Http::preventStrayRequests();
        Http::fake([
            self::GOVBR_BASE.'/jwk' => Http::response(['keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => self::GOVBR_KID,
                'n' => JWT::urlsafeB64Encode($rsa['n']),
                'e' => JWT::urlsafeB64Encode($rsa['e']),
            ]]]),
        ]);
    }

    protected function idTokenAssinado(array $claims): string
    {
        return JWT::encode($claims, $this->govbrChavePrivada, 'RS256', self::GOVBR_KID);
    }

    protected function fakeTokenEndpoint(array $claims): void
    {
        Http::fake([
            self::GOVBR_BASE.'/token' => Http::response([
                'access_token' => 'access-token-teste',
                'id_token' => $this->idTokenAssinado($claims),
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ]),
        ]);
    }

    protected function claimsValidos(string $nonce): array
    {
        return [
            'iss' => self::GOVBR_BASE.'/',
            'aud' => 'teste',
            'sub' => '52998224725',
            'name' => 'Maria Proponente',
            'email' => 'maria@example.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => $nonce,
        ];
    }
}
