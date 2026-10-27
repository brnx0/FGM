<?php

namespace Tests\Feature;

use App\Enums\TipoCadastro;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TipoCadastroTest extends TestCase
{
    use RefreshDatabase;

    public function test_etapa_mostra_as_opcoes_e_o_tipo_atual(): void
    {
        $user = User::factory()->patrocinador()->create();

        $this->actingAs($user)->get('/cadastro/tipo')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cadastro/Tipo')
                ->where('tipoAtual', 'patrocinador')
                ->where('opcoes', [
                    ['valor' => 'proponente', 'rotulo' => 'Proponente'],
                    ['valor' => 'patrocinador', 'rotulo' => 'Patrocinador'],
                ])
                ->where('auth.user.tipoCadastro', 'patrocinador'));
    }

    public function test_etapa_exige_pessoa_fisica_completa(): void
    {
        $user = User::factory()->create(['profile_completed_at' => null]);

        $this->actingAs($user)->get('/cadastro/tipo')->assertRedirect(route('cadastro'));
        $this->actingAs($user)->post('/cadastro/tipo', ['tipo_cadastro' => 'proponente'])->assertRedirect(route('cadastro'));

        $this->assertNull($user->fresh()->tipo_cadastro);
    }

    public function test_etapa_exige_login(): void
    {
        $this->get('/cadastro/tipo')->assertRedirect('/');
    }

    public function test_gestor_nao_escolhe_tipo_de_cadastro(): void
    {
        $gestor = User::factory()->gestor()->create();

        $this->actingAs($gestor)->post('/cadastro/tipo', ['tipo_cadastro' => 'proponente'])->assertRedirect('/retaguarda');

        $this->assertNull($gestor->fresh()->tipo_cadastro);
    }

    public function test_finalizar_como_proponente_abre_os_editais(): void
    {
        $user = User::factory()->cadastroCompleto()->create();

        $this->actingAs($user)->post('/cadastro/tipo', ['tipo_cadastro' => 'proponente'])
            ->assertRedirect(route('portal.editais'))
            ->assertSessionHas('sucesso', 'Cadastro finalizado.');

        $this->assertSame(TipoCadastro::Proponente, $user->fresh()->tipo_cadastro);
    }

    public function test_finalizar_como_patrocinador_abre_os_projetos(): void
    {
        $user = User::factory()->cadastroCompleto()->create();

        $this->actingAs($user)->post('/cadastro/tipo', ['tipo_cadastro' => 'patrocinador'])
            ->assertRedirect(route('portal.projetos'))
            ->assertSessionHas('sucesso', 'Cadastro finalizado.');

        $this->assertSame(TipoCadastro::Patrocinador, $user->fresh()->tipo_cadastro);
    }

    public function test_tipo_pode_ser_trocado_depois(): void
    {
        $user = User::factory()->proponente()->create();

        $this->actingAs($user)->post('/cadastro/tipo', ['tipo_cadastro' => 'patrocinador'])->assertRedirect(route('portal.projetos'));

        $this->assertSame(TipoCadastro::Patrocinador, $user->fresh()->tipo_cadastro);
    }

    public function test_recusa_tipo_vazio_ou_invalido(): void
    {
        $user = User::factory()->cadastroCompleto()->create();

        $this->actingAs($user)->post('/cadastro/tipo', ['tipo_cadastro' => ''])
            ->assertSessionHasErrors(['tipo_cadastro' => 'Selecione o tipo de cadastro.']);
        $this->actingAs($user)->post('/cadastro/tipo', ['tipo_cadastro' => 'gestor'])
            ->assertSessionHasErrors(['tipo_cadastro' => 'Selecione o tipo de cadastro.']);

        $this->assertNull($user->fresh()->tipo_cadastro);
    }
}
