<?php

namespace Tests\Feature\Retaguarda;

use App\Models\User;
use Database\Seeders\PrimeiroGestorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_tela_de_login_abre(): void
    {
        $this->get('/retaguarda/login')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Retaguarda/Login'));
    }

    public function test_entra_com_email_e_senha(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->post('/retaguarda/login', ['email' => $gestor->email, 'senha' => '123456'])
            ->assertRedirect('/retaguarda');

        $this->assertAuthenticatedAs($gestor);
        $this->assertNotNull($gestor->fresh()->last_login_at);
    }

    public function test_email_com_maiusculas_e_espacos_entra(): void
    {
        $this->seed(PrimeiroGestorSeeder::class);

        $this->post('/retaguarda/login', ['email' => '  Admin@Admin ', 'senha' => '123456'])
            ->assertRedirect('/retaguarda');

        $this->assertAuthenticatedAs(User::where('email', 'admin@admin')->firstOrFail());
    }

    public function test_senha_errada_mensagem_generica(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->post('/retaguarda/login', ['email' => $gestor->email, 'senha' => 'errada'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.']);

        $this->assertGuest();
    }

    public function test_usuario_sem_papel_nao_entra(): void
    {
        $proponente = User::factory()->create(['password' => '123456']);

        $this->post('/retaguarda/login', ['email' => $proponente->email, 'senha' => '123456'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.']);

        $this->assertGuest();
    }

    public function test_gestor_inativo_nao_entra(): void
    {
        $gestor = User::factory()->gestor()->create(['ativo' => false]);

        $this->post('/retaguarda/login', ['email' => $gestor->email, 'senha' => '123456'])
            ->assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.']);

        $this->assertGuest();
    }

    public function test_limite_de_tentativas(): void
    {
        $gestor = User::factory()->gestor()->create();

        for ($tentativa = 1; $tentativa <= 5; $tentativa++) {
            $this->post('/retaguarda/login', ['email' => $gestor->email, 'senha' => 'errada'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/retaguarda/login', ['email' => $gestor->email, 'senha' => '123456'])
            ->assertTooManyRequests();

        $this->assertGuest();
    }

    public function test_visitante_vai_para_login(): void
    {
        $this->get('/retaguarda')->assertRedirect('/retaguarda/login');
    }

    public function test_visitante_do_portal_continua_indo_para_a_home(): void
    {
        $this->get('/cadastro')->assertRedirect('/');
    }

    public function test_proponente_logado_e_barrado_sem_perder_sessao(): void
    {
        $proponente = User::factory()->create();

        $this->actingAs($proponente)
            ->get('/retaguarda')
            ->assertRedirect('/retaguarda/login')
            ->assertSessionHas('erro', 'Você não tem acesso à retaguarda.');

        $this->assertAuthenticatedAs($proponente);
    }

    public function test_gestor_desativado_com_sessao_aberta_e_expulso(): void
    {
        $gestor = User::factory()->gestor()->create();
        $this->actingAs($gestor)->get('/retaguarda')->assertOk();

        $gestor->update(['ativo' => false]);

        $this->get('/retaguarda')
            ->assertRedirect('/retaguarda/login')
            ->assertSessionHas('erro', 'Você não tem acesso à retaguarda.');

        $this->assertGuest();
    }

    public function test_gestor_ve_a_retaguarda_e_e_marcado_como_gestor(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor)
            ->get('/retaguarda')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Retaguarda/Inicio')
                ->where('auth.user.ehGestor', true));
    }

    public function test_sair_encerra_sessao(): void
    {
        config(['govbr.driver' => 'fake']);
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor)
            ->post('/retaguarda/sair')
            ->assertRedirect('/retaguarda/login');

        $this->assertGuest();
    }
}
