<?php

namespace Tests\Feature\Retaguarda;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestorForaDoCadastroTest extends TestCase
{
    use RefreshDatabase;

    private const ERRO = 'A área de cadastro é para proponentes. Você está conectado como usuário da retaguarda.';

    public function test_gestor_e_redirecionado_ao_abrir_o_cadastro(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor)->get('/cadastro')
            ->assertRedirect('/retaguarda')
            ->assertSessionHas('erro', self::ERRO);

        $this->actingAs($gestor)->get('/cadastro/pessoa-juridica')->assertRedirect('/retaguarda');
    }

    public function test_gestor_nao_grava_cadastro(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor)->post('/cadastro', ['nome_social' => 'Fulano'])
            ->assertRedirect('/retaguarda')
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

    public function test_govbr_pelo_portal_com_cpf_de_gestor_vai_para_a_retaguarda(): void
    {
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->app['env'] = 'local';
        config(['govbr.driver' => 'fake']);
        $gestor = User::factory()->gestor()->create([
            'cpf' => '52998224725',
            'name' => 'Gestor Original',
            'email' => 'gestor@fgm.test',
        ]);

        $this->post('/auth/govbr/fake', ['cpf' => '52998224725'])->assertRedirect('/retaguarda');

        $this->assertAuthenticatedAs($gestor);
        $gestor->refresh();
        $this->assertSame('Gestor Original', $gestor->name);
        $this->assertSame('gestor@fgm.test', $gestor->email);
    }

    public function test_home_informa_que_o_usuario_e_gestor(): void
    {
        $this->actingAs(User::factory()->gestor()->create())->get('/')
            ->assertInertia(fn ($page) => $page->where('auth.user.ehGestor', true));
    }

    public function test_aviso_chega_ate_a_listagem_da_retaguarda(): void
    {
        $this->actingAs(User::factory()->gestor()->create())->followingRedirects()->get('/cadastro')
            ->assertInertia(fn ($page) => $page->where('flash.erro', self::ERRO));
    }
}
