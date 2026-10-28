<?php

namespace Tests\Feature\Retaguarda;

use App\Actions\LoginGovBrUser;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** O portal nunca leva à retaguarda: gestor logado é visitante no portal e o gov.br do portal não o deixa entrar. */
class GestorForaDoCadastroTest extends TestCase
{
    use RefreshDatabase;

    private const ERRO = 'A área de cadastro é para proponentes. Você está conectado como usuário da retaguarda.';

    public function test_gestor_que_abre_o_cadastro_volta_a_home(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor)->get('/cadastro')
            ->assertRedirect('/')
            ->assertSessionHas('erro', self::ERRO);

        $this->actingAs($gestor)->get('/cadastro/pessoa-juridica')->assertRedirect('/');
        $this->assertAuthenticatedAs($gestor);
    }

    public function test_gestor_nao_grava_cadastro(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor)->post('/cadastro', ['nome_social' => 'Fulano'])
            ->assertRedirect('/')
            ->assertSessionHas('erro', self::ERRO);

        $this->assertDatabaseCount('pessoas_fisicas', 0);
        $this->assertNull($gestor->fresh()->profile_completed_at);
    }

    public function test_gestor_desativado_usa_o_cadastro_normalmente(): void
    {
        $gestor = User::factory()->gestor()->create(['ativo' => false]);

        $this->actingAs($gestor)->get('/cadastro')->assertOk();
    }

    public function test_proponente_continua_usando_o_cadastro(): void
    {
        $this->actingAs(User::factory()->create())->get('/cadastro')->assertOk();
    }

    public function test_govbr_simulado_pelo_portal_recusa_cpf_de_gestor_sem_entrar_nem_alterar_os_dados(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'fake']);
        $gestor = User::factory()->gestor()->create([
            'cpf' => '52998224725',
            'name' => 'Gestor Original',
            'email' => 'gestor@fgm.test',
        ]);

        $this->post('/auth/govbr/fake', ['cpf' => '52998224725'])
            ->assertRedirect('/')
            ->assertSessionHas('erro', LoginGovBrUser::GESTOR_RECUSADO);

        $this->assertGuest();
        $gestor->refresh();
        $this->assertSame('Gestor Original', $gestor->name);
        $this->assertSame('gestor@fgm.test', $gestor->email);
    }

    public function test_portal_mostra_o_gestor_logado_como_visitante(): void
    {
        $this->actingAs(User::factory()->gestor()->create())->get('/privacidade')
            ->assertInertia(fn ($page) => $page->where('auth.user', null));
    }

    public function test_aviso_chega_a_home(): void
    {
        $this->actingAs(User::factory()->gestor()->create())->followingRedirects()->get('/cadastro')
            ->assertInertia(fn ($page) => $page->component('Home')->where('flash.erro', self::ERRO));
    }
}
