<?php

namespace Tests\Unit;

use App\Auth\GovBr\GovBrClient;
use App\Auth\GovBr\GovBrException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakesGovBr;
use Tests\TestCase;

class GovBrClientTest extends TestCase
{
    use FakesGovBr;

    private function client(): GovBrClient
    {
        return app(GovBrClient::class);
    }

    public function test_authorization_url_tem_pkce_s256(): void
    {
        $url = $this->client()->authorizationUrl('st', 'no', 'verificador');
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        $this->assertStringStartsWith('https://sso.staging.acesso.gov.br/authorize?', $url);
        $this->assertSame('S256', $q['code_challenge_method']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', 'verificador', true)), '+/', '-_'), '='), $q['code_challenge']);
        $this->assertSame(['code', 'teste', 'st', 'no'], [$q['response_type'], $q['client_id'], $q['state'], $q['nonce']]);
        $this->assertSame('openid email profile', $q['scope']);
        $this->assertSame('http://localhost/auth/govbr/callback', $q['redirect_uri']);
    }

    public function test_exchange_code_usa_basic_auth_e_code_verifier(): void
    {
        $this->fakeTokenEndpoint($this->claimsValidos('n'));

        $tokens = $this->client()->exchangeCode('abc', 'verificador');

        $this->assertSame('access-token-teste', $tokens['access_token']);
        $this->assertNotEmpty($tokens['id_token']);
        Http::assertSent(fn ($req) => $req->url() === 'https://sso.staging.acesso.gov.br/token'
            && $req->method() === 'POST'
            && $req->hasHeader('Authorization', 'Basic '.base64_encode('teste:segredo'))
            && $req['grant_type'] === 'authorization_code'
            && $req['code'] === 'abc'
            && $req['redirect_uri'] === 'http://localhost/auth/govbr/callback'
            && $req['code_verifier'] === 'verificador');
    }

    public function test_exchange_code_falha_se_nao_2xx(): void
    {
        Http::fake(['https://sso.staging.acesso.gov.br/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->expectException(GovBrException::class);
        $this->client()->exchangeCode('abc', 'verificador');
    }

    public function test_exchange_code_falha_sem_id_token(): void
    {
        Http::fake(['https://sso.staging.acesso.gov.br/token' => Http::response(['access_token' => 'x'])]);

        $this->expectException(GovBrException::class);
        $this->client()->exchangeCode('abc', 'verificador');
    }

    public function test_identidade_a_partir_do_id_token_valido(): void
    {
        $claims = $this->claimsValidos('n') + ['phone_number' => '71999990000'];
        $claims['sub'] = '529.982.247-25';

        $id = $this->client()->identityFromIdToken($this->idTokenAssinado($claims), 'n');

        $this->assertSame('52998224725', $id->cpf);
        $this->assertSame('Maria Proponente', $id->nome);
        $this->assertSame('maria@example.com', $id->email);
        $this->assertTrue($id->emailVerificado);
        $this->assertSame('71999990000', $id->telefone);
    }

    public function test_aud_em_lista_com_client_id_e_aceito(): void
    {
        $claims = ['aud' => ['outro', 'teste']] + $this->claimsValidos('n');

        $this->assertSame('52998224725', $this->client()->identityFromIdToken($this->idTokenAssinado($claims), 'n')->cpf);
    }

    public function test_exp_dentro_da_tolerancia_de_60s_e_aceito(): void
    {
        $claims = ['exp' => time() - 30] + $this->claimsValidos('n');

        $this->assertSame('52998224725', $this->client()->identityFromIdToken($this->idTokenAssinado($claims), 'n')->cpf);
    }

    public function test_rejeita_nonce_diferente(): void
    {
        $this->expectException(GovBrException::class);
        $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('x')), 'y');
    }

    public function test_rejeita_aud_errado(): void
    {
        $claims = ['aud' => 'outro'] + $this->claimsValidos('n');

        $this->expectException(GovBrException::class);
        $this->client()->identityFromIdToken($this->idTokenAssinado($claims), 'n');
    }

    public static function claimsInvalidos(): array
    {
        return [
            'iss de outro provedor' => [['iss' => 'https://sso.acesso.gov.br/']],
            'expirado além da tolerância' => [['exp' => time() - 120]],
            'sem exp' => [['exp' => null]],
            'sub não é CPF válido' => [['sub' => '12345678900']],
            'sub vazio' => [['sub' => '']],
            'sem nome' => [['name' => null]],
        ];
    }

    #[DataProvider('claimsInvalidos')]
    public function test_rejeita_claims_invalidos(array $alteracoes): void
    {
        $claims = array_filter(array_merge($this->claimsValidos('n'), $alteracoes), fn ($v) => $v !== null);

        $this->expectException(GovBrException::class);
        $this->client()->identityFromIdToken($this->idTokenAssinado($claims), 'n');
    }

    public function test_rejeita_assinatura_de_outra_chave(): void
    {
        $outra = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $token = JWT::encode($this->claimsValidos('n'), $outra, 'RS256', self::GOVBR_KID);

        $this->expectException(GovBrException::class);
        $this->client()->identityFromIdToken($token, 'n');
    }

    public function test_rejeita_alg_none(): void
    {
        $token = JWT::urlsafeB64Encode(json_encode(['alg' => 'none', 'typ' => 'JWT', 'kid' => self::GOVBR_KID]))
            .'.'.JWT::urlsafeB64Encode(json_encode($this->claimsValidos('n'))).'.';

        $this->expectException(GovBrException::class);
        $this->client()->identityFromIdToken($token, 'n');
    }

    public function test_rejeita_hs256(): void
    {
        $token = JWT::encode($this->claimsValidos('n'), str_repeat('k', 64), 'HS256', self::GOVBR_KID);

        $this->expectException(GovBrException::class);
        $this->client()->identityFromIdToken($token, 'n');
    }

    public function test_rejeita_token_malformado(): void
    {
        $this->expectException(GovBrException::class);
        $this->client()->identityFromIdToken('isto-nao-e-um-jwt', 'n');
    }

    public function test_jwks_fica_em_cache(): void
    {
        $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('n')), 'n');
        $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('n')), 'n');

        Http::assertSentCount(1);
    }

    public function test_rotacao_de_chave_recarrega_jwks_uma_vez(): void
    {
        // JWKS com a chave A fica em cache.
        $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('n')), 'n');
        Http::assertSentCount(1);

        // O gov.br passa a assinar com a chave B e publica só ela.
        $this->rotacionarChaveGovBr('chave-b');
        $id = $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('n')), 'n');

        $this->assertSame('52998224725', $id->cpf);
        Http::assertSentCount(2);
    }

    public function test_kid_desconhecido_dentro_do_intervalo_nao_busca_de_novo(): void
    {
        $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('n')), 'n');
        $this->rotacionarChaveGovBr('chave-b');
        $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('n')), 'n');
        Http::assertSentCount(2);

        try {
            $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('n'), 'forjado'), 'n');
            $this->fail('kid desconhecido deveria ser rejeitado.');
        } catch (GovBrException) {
        }

        Http::assertSentCount(2);
    }

    public function test_cache_do_jwks_e_por_base_url(): void
    {
        $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('n')), 'n');

        config(['govbr.base_url' => 'https://sso.acesso.gov.br']);
        Http::fake(['https://sso.acesso.gov.br/jwk' => Http::response($this->govbrJwks)]);
        $claims = ['iss' => 'https://sso.acesso.gov.br/'] + $this->claimsValidos('n');
        $this->client()->identityFromIdToken($this->idTokenAssinado($claims), 'n');

        Http::assertSent(fn ($req) => $req->url() === 'https://sso.acesso.gov.br/jwk');
    }

    public function test_rejeita_token_sem_kid(): void
    {
        $token = JWT::encode($this->claimsValidos('n'), $this->govbrChavePrivada, 'RS256');

        $this->expectException(GovBrException::class);
        $this->client()->identityFromIdToken($token, 'n');
    }

    public function test_jwks_indisponivel_vira_govbr_exception(): void
    {
        $this->govbrJwksStatus = 500;

        $this->expectException(GovBrException::class);
        $this->client()->identityFromIdToken($this->idTokenAssinado($this->claimsValidos('n')), 'n');
    }

    public function test_falha_de_conexao_no_token_vira_govbr_exception(): void
    {
        Http::fake(['https://sso.staging.acesso.gov.br/token' => Http::failedConnection()]);

        $this->expectException(GovBrException::class);
        $this->client()->exchangeCode('abc', 'verificador');
    }

    public function test_logout_url(): void
    {
        $this->assertSame(
            'https://sso.staging.acesso.gov.br/logout?post_logout_redirect_uri='.urlencode('http://localhost/auth/govbr/logout'),
            $this->client()->logoutUrl(),
        );
    }
}
