<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuDoPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_proponente_ve_so_os_editais_publicados_com_pdf(): void
    {
        Edital::factory()->create(['titulo' => 'Edital Publicado', 'slug' => 'publicado']);
        Edital::factory()->create(['titulo' => 'Rascunho da Retaguarda', 'publicado' => false]);
        Edital::factory()->semPdf()->create(['titulo' => 'Sem PDF']);

        $this->actingAs(User::factory()->proponente()->create())->get('/portal/editais')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Editais')
                ->where('editais.total', 1)
                ->where('editais.data.0.titulo', 'Edital Publicado')
                ->where('editais.data.0.pdf', route('editais.pdf', 'publicado')));
    }

    public function test_editais_filtram_por_titulo_e_situacao(): void
    {
        Edital::factory()->create(['titulo' => 'Fomento ao Audiovisual']);
        Edital::factory()->create([
            'titulo' => 'Fomento à Música',
            'inscricoes_inicio' => now()->subDays(40)->toDateString(),
            'inscricoes_fim' => now()->subDays(10)->toDateString(),
        ]);
        $proponente = User::factory()->proponente()->create();

        $this->actingAs($proponente)->get('/portal/editais?busca=AUDIOVISUAL')
            ->assertInertia(fn (Assert $page) => $page
                ->where('editais.total', 1)
                ->where('editais.data.0.titulo', 'Fomento ao Audiovisual')
                ->where('filtros.busca', 'AUDIOVISUAL'));

        $this->actingAs($proponente)->get('/portal/editais?situacao=encerrado')
            ->assertInertia(fn (Assert $page) => $page
                ->where('editais.total', 1)
                ->where('editais.data.0.titulo', 'Fomento à Música'));
    }

    public function test_patrocinador_ve_a_tela_de_projetos(): void
    {
        $this->actingAs(User::factory()->patrocinador()->create())->get('/portal/projetos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Projetos'));
    }

    public function test_com_os_dois_tipos_ve_editais_e_projetos(): void
    {
        $user = User::factory()->proponenteEPatrocinador()->create();

        $this->actingAs($user)->get('/portal/editais')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Editais'));
        $this->actingAs($user)->get('/portal/projetos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Portal/Projetos'));
    }

    public function test_cada_tipo_e_levado_a_propria_tela(): void
    {
        $this->actingAs(User::factory()->patrocinador()->create())->get('/portal/editais')
            ->assertRedirect(route('portal.projetos'));

        $this->actingAs(User::factory()->proponente()->create())->get('/portal/projetos')
            ->assertRedirect(route('portal.editais'));
    }

    public function test_sem_tipo_de_cadastro_volta_para_a_ultima_etapa(): void
    {
        $this->actingAs(User::factory()->cadastroCompleto()->create())->get('/portal/editais')
            ->assertRedirect(route('cadastro.tipo'))
            ->assertSessionHas('aviso', 'Finalize seu cadastro escolhendo o tipo de cadastro.');
    }

    public function test_telas_do_portal_exigem_login(): void
    {
        $this->get('/portal/editais')->assertRedirect('/');
        $this->get('/portal/projetos')->assertRedirect('/');
    }

    public function test_gestor_e_levado_a_retaguarda(): void
    {
        $this->actingAs(User::factory()->gestor()->create())->get('/portal/editais')->assertRedirect('/retaguarda');
    }
}
