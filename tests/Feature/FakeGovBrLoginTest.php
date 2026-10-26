<?php

namespace Tests\Feature;

use App\Auth\GovBr\FakeLoginGate;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class FakeGovBrLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // O env é trocado em runtime (deixa de ser 'testing'), o que reativa o CSRF.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_login_simulado_em_local(): void
    {
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'fake']);

        $this->get('/auth/govbr/redirect')->assertRedirect('/auth/govbr/fake');
        $this->post('/auth/govbr/fake', ['cpf' => '529.982.247-25'])
            ->assertRedirect('/cadastro');

        $this->assertAuthenticated();
        $this->assertSame('52998224725', auth()->user()->cpf);
    }

    public function test_login_simulado_devolve_nome_e_email_da_conta_de_teste(): void
    {
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'fake', 'govbr.fake_accounts' => [
            ['cpf' => '11144477735', 'nome' => 'João Preposto Teste', 'email' => 'joao.teste@example.com'],
        ]]);

        $this->post('/auth/govbr/fake', ['cpf' => '111.444.777-35'])->assertRedirect('/cadastro');

        $this->assertDatabaseHas('users', [
            'cpf' => '11144477735',
            'name' => 'João Preposto Teste',
            'email' => 'joao.teste@example.com',
        ]);
    }

    public function test_formulario_lista_contas_simuladas(): void
    {
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'fake']);

        $this->get('/auth/govbr/fake')->assertInertia(fn (Assert $p) => $p
            ->component('Auth/FakeLogin')
            ->has('contas', 2));
    }

    public function test_cpf_invalido_volta_com_erro(): void
    {
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'fake']);

        $this->post('/auth/govbr/fake', ['cpf' => '123'])
            ->assertSessionHasErrors('cpf');
        $this->assertGuest();
    }

    public function test_cpf_com_mais_de_11_digitos_e_rejeitado(): void
    {
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'fake']);

        $this->post('/auth/govbr/fake', ['cpf' => '5299822472500'])
            ->assertSessionHasErrors('cpf');
        $this->assertGuest();
    }

    public function test_cpf_valido_fora_das_contas_de_teste_e_recusado(): void
    {
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'fake', 'govbr.fake_accounts' => [
            ['cpf' => '52998224725', 'nome' => 'Maria Proponente Teste', 'email' => null],
        ]]);

        $this->post('/auth/govbr/fake', ['cpf' => '111.444.777-35'])
            ->assertSessionHasErrors(['cpf' => 'Este CPF não está entre as contas de teste.']);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_simulado_404_em_producao(): void
    {
        config(['govbr.driver' => 'fake']);
        $this->app['env'] = 'production';

        $this->get('/auth/govbr/fake')->assertNotFound();
        $this->post('/auth/govbr/fake', ['cpf' => '52998224725'])->assertNotFound();
        $this->assertGuest();
    }

    public function test_simulado_404_com_driver_govbr(): void
    {
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'govbr']);

        $this->get('/auth/govbr/fake')->assertNotFound();
        // Com driver govbr, o início do fluxo vai para o gov.br real, nunca para o simulado.
        $this->get('/auth/govbr/redirect')->assertRedirectContains(config('govbr.base_url').'/authorize');
    }

    public function test_boot_falha_em_producao_com_fake(): void
    {
        config(['govbr.driver' => 'fake']);
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GOVBR_DRIVER=fake não é permitido em produção.');
        app(FakeLoginGate::class)->assertNaoEmProducao();
    }

    public function test_producao_com_driver_govbr_nao_falha(): void
    {
        config(['govbr.driver' => 'govbr']);
        $this->app['env'] = 'production';

        app(FakeLoginGate::class)->assertNaoEmProducao();
        $this->assertFalse(FakeLoginGate::permitido());
    }

    public function test_staging_mostra_faixa_de_teste(): void
    {
        config(['govbr.driver' => 'fake']);

        $this->app['env'] = 'staging';
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('ambienteTeste', true));

        $this->app['env'] = 'local';
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('ambienteTeste', false));
    }
}
