<?php

namespace Tests\Feature\Retaguarda;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesGovBr;
use Tests\TestCase;

class GovBrRetaguardaTest extends TestCase
{
    use FakesGovBr;
    use RefreshDatabase;

    private const CPF = '52998224725';

    private const SEM_ACESSO = 'Seu CPF não tem acesso à retaguarda.';

    private function usarGovBrSimulado(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'fake']);
    }

    public function test_govbr_simulado_entra_gestor_pelo_cpf(): void
    {
        $this->usarGovBrSimulado();
        $gestor = User::factory()->gestor()->create(['cpf' => self::CPF]);

        $this->get('/retaguarda/govbr')->assertRedirect('/auth/govbr/redirect?destino=retaguarda');
        $this->get('/auth/govbr/redirect?destino=retaguarda')->assertRedirect('/auth/govbr/fake');
        $this->post('/auth/govbr/fake', ['cpf' => self::CPF])->assertRedirect('/retaguarda');

        $this->assertAuthenticatedAs($gestor);
        $this->assertTrue(session('retaguarda.via_govbr'));
        $this->assertNull(session('govbr.destino'));
        $this->assertNotNull($gestor->fresh()->last_login_at);
    }

    public function test_govbr_cpf_sem_papel_e_recusado_sem_criar_usuario(): void
    {
        $this->usarGovBrSimulado();
        $this->get('/auth/govbr/redirect?destino=retaguarda');
        $this->post('/auth/govbr/fake', ['cpf' => self::CPF])
            ->assertRedirect('/retaguarda/login')
            ->assertSessionHas('erro', self::SEM_ACESSO);

        $this->assertDatabaseMissing('users', ['cpf' => self::CPF]);
        $this->assertGuest();
    }

    public function test_govbr_usuario_sem_papel_existente_e_recusado(): void
    {
        $this->usarGovBrSimulado();
        User::factory()->create(['cpf' => self::CPF]);

        $this->get('/auth/govbr/redirect?destino=retaguarda');
        $this->post('/auth/govbr/fake', ['cpf' => self::CPF])
            ->assertRedirect('/retaguarda/login')
            ->assertSessionHas('erro', self::SEM_ACESSO);

        $this->assertGuest();
    }

    public function test_govbr_nao_sobrescreve_nome_do_gestor(): void
    {
        $this->usarGovBrSimulado();
        $gestor = User::factory()->gestor()->create(['cpf' => self::CPF, 'name' => 'Fulana Gestora']);
        $email = $gestor->email;

        $this->get('/auth/govbr/redirect?destino=retaguarda');
        $this->post('/auth/govbr/fake', ['cpf' => self::CPF])->assertRedirect('/retaguarda');

        $gestor->refresh();
        $this->assertSame('Fulana Gestora', $gestor->name);
        $this->assertSame($email, $gestor->email);
    }

    public function test_govbr_gestor_inativo_e_recusado(): void
    {
        $this->usarGovBrSimulado();
        User::factory()->gestor()->create(['cpf' => self::CPF, 'ativo' => false]);

        $this->get('/auth/govbr/redirect?destino=retaguarda');
        $this->post('/auth/govbr/fake', ['cpf' => self::CPF])
            ->assertRedirect('/retaguarda/login')
            ->assertSessionHas('erro', self::SEM_ACESSO);

        $this->assertGuest();
    }

    public function test_tentativa_abandonada_na_retaguarda_nao_desvia_login_simulado_do_portal(): void
    {
        $this->usarGovBrSimulado();

        $this->get('/retaguarda/govbr');
        $this->get('/auth/govbr/redirect?destino=retaguarda');
        $this->get('/auth/govbr/redirect')->assertRedirect('/auth/govbr/fake');
        $this->post('/auth/govbr/fake', ['cpf' => self::CPF])->assertRedirect('/cadastro');

        $this->assertDatabaseHas('users', ['cpf' => self::CPF]);
    }

    public function test_tentativa_abandonada_na_retaguarda_nao_desvia_callback_real_do_portal(): void
    {
        $this->setUpFakesGovBr();

        $this->get('/auth/govbr/redirect?destino=retaguarda');
        $this->get('/auth/govbr/redirect');
        $this->fakeTokenEndpoint($this->claimsValidos(session('govbr.nonce')));

        $this->get('/auth/govbr/callback?code=abc&state='.session('govbr.state'))->assertRedirect('/cadastro');

        $this->assertDatabaseHas('users', ['cpf' => self::CPF]);
    }

    public function test_destino_e_consumido_e_o_proponente_seguinte_nao_e_desviado(): void
    {
        $this->usarGovBrSimulado();

        $this->get('/auth/govbr/redirect?destino=retaguarda');
        $this->post('/auth/govbr/fake', ['cpf' => self::CPF])->assertRedirect('/retaguarda/login');
        $this->assertNull(session('govbr.destino'));

        $this->post('/auth/govbr/fake', ['cpf' => self::CPF])->assertRedirect('/cadastro');
    }

    public function test_govbr_real_callback_com_destino_retaguarda(): void
    {
        $this->setUpFakesGovBr();
        $gestor = User::factory()->gestor()->create(['cpf' => self::CPF, 'name' => 'Fulana Gestora']);

        $this->get('/retaguarda/govbr')->assertRedirect('/auth/govbr/redirect?destino=retaguarda');
        $this->get('/auth/govbr/redirect?destino=retaguarda')->assertRedirectContains(self::GOVBR_BASE.'/authorize');
        $this->fakeTokenEndpoint($this->claimsValidos(session('govbr.nonce')));

        $this->get('/auth/govbr/callback?code=abc&state='.session('govbr.state'))->assertRedirect('/retaguarda');

        $this->assertAuthenticatedAs($gestor);
        $this->assertTrue(session('retaguarda.via_govbr'));
        $this->assertSame('Fulana Gestora', $gestor->fresh()->name);
    }

    public function test_govbr_real_cpf_sem_acesso_volta_ao_login_da_retaguarda(): void
    {
        $this->setUpFakesGovBr();

        $this->get('/auth/govbr/redirect?destino=retaguarda');
        $this->fakeTokenEndpoint($this->claimsValidos(session('govbr.nonce')));

        $this->get('/auth/govbr/callback?code=abc&state='.session('govbr.state'))
            ->assertRedirect('/retaguarda/login')
            ->assertSessionHas('erro', self::SEM_ACESSO);

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_govbr_real_falha_vai_ao_login_da_retaguarda_mesmo_autenticado(): void
    {
        $this->setUpFakesGovBr();

        $this->actingAs(User::factory()->create());
        $this->get('/auth/govbr/redirect?destino=retaguarda');

        $this->get('/auth/govbr/callback?error=access_denied&state='.session('govbr.state'))
            ->assertRedirect('/retaguarda/login')
            ->assertSessionHas('erro', 'Não foi possível entrar com o gov.br. Tente novamente.');

        $this->assertNull(session('govbr.destino'));
    }

    public function test_fluxo_do_proponente_nao_muda(): void
    {
        $this->setUpFakesGovBr();

        $this->get('/auth/govbr/redirect');
        $this->fakeTokenEndpoint($this->claimsValidos(session('govbr.nonce')));

        $this->get('/auth/govbr/callback?code=abc&state='.session('govbr.state'))->assertRedirect('/cadastro');

        $this->assertAuthenticated();
        $this->assertNull(session('retaguarda.via_govbr'));
    }
}
