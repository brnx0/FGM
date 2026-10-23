<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    public function test_home_usa_inertia_com_props_compartilhadas(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Home')
            ->where('auth.user', null)
            ->where('ambienteTeste', false));
    }

    public function test_ambiente_teste_ligado_so_em_staging_com_fake(): void
    {
        config(['govbr.driver' => 'fake']);
        $this->app['env'] = 'staging';
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('ambienteTeste', true));
    }

    public function test_ambiente_teste_desligado_em_staging_com_driver_real(): void
    {
        config(['govbr.driver' => 'govbr']);
        $this->app['env'] = 'staging';
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('ambienteTeste', false));
    }

    public function test_cabecalhos_de_seguranca(): void
    {
        $this->get('/')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_hsts_so_em_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_csp_tem_nonce(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src[^;]*'nonce-[A-Za-z0-9+\/=]+'/", $csp);
    }

    public function test_hsts_via_proxy_confiavel_com_x_forwarded_proto(): void
    {
        $_SERVER['TRUSTED_PROXIES'] = '10.0.0.1';
        $this->refreshApplication();
        $this->withoutVite();

        try {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->withHeader('X-Forwarded-Proto', 'https')
                ->get('/')
                ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        } finally {
            unset($_SERVER['TRUSTED_PROXIES']);
        }
    }

    public function test_x_forwarded_proto_ignorado_sem_proxy_confiavel(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_script_de_preferencias_tem_nonce_da_csp(): void
    {
        $resposta = $this->get('/')->assertOk()->assertSee('alto-contraste', false);

        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", $resposta->headers->get('Content-Security-Policy'), $csp);
        preg_match('/<script nonce="([^"]+)">[^<]*alto-contraste/', $resposta->getContent(), $script);

        $this->assertNotEmpty($csp[1] ?? null, 'CSP sem nonce');
        $this->assertSame($csp[1], $script[1] ?? null, 'Script de preferências sem o nonce da CSP');
    }
}
