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

    public function test_pagina_tem_skip_link_e_barra_institucional(): void
    {
        $this->get('/')->assertOk()->assertSee('alto-contraste', false);
    }
}
