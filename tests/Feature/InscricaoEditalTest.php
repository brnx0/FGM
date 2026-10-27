<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InscricaoEditalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'America/Bahia'));
    }

    public function test_proponente_abre_a_inscricao_de_edital_aberto(): void
    {
        Edital::factory()->create([
            'titulo' => 'PNAB Salvador — Ciclo II',
            'slug' => 'pnab-ii',
            'categoria' => 'Fomento',
            'inscricoes_inicio' => '2026-11-01',
            'inscricoes_fim' => '2026-11-15',
        ]);

        $this->actingAs(User::factory()->proponente()->create())->get('/portal/editais/pnab-ii/inscricao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Portal/Inscricao')
                ->where('edital.titulo', 'PNAB Salvador — Ciclo II')
                ->where('edital.categoria', 'Fomento')
                ->where('edital.periodo', '01/11/2026 a 15/11/2026')
                ->where('edital.pdf', route('editais.pdf', 'pnab-ii')));
    }

    #[DataProvider('foraDoPeriodo')]
    public function test_edital_fora_do_periodo_volta_para_os_editais_com_aviso(string $inicio, string $fim): void
    {
        Edital::factory()->create(['slug' => 'fechado', 'inscricoes_inicio' => $inicio, 'inscricoes_fim' => $fim]);

        $this->actingAs(User::factory()->proponente()->create())->get('/portal/editais/fechado/inscricao')
            ->assertRedirect(route('portal.editais'))
            ->assertSessionHas('aviso', 'As inscrições deste edital não estão abertas.');
    }

    /** @return array<string, array{string, string}> */
    public static function foraDoPeriodo(): array
    {
        return [
            'em breve (abre amanhã)' => ['2026-11-11', '2026-11-30'],
            'encerrado (fechou ontem)' => ['2026-10-01', '2026-11-09'],
        ];
    }

    /** @param array<string, mixed> $atributos */
    #[DataProvider('foraDoPortal')]
    public function test_edital_que_o_portal_nao_mostra_responde_404(array $atributos): void
    {
        Edital::factory()->create(['slug' => 'oculto', ...$atributos]);

        $this->actingAs(User::factory()->proponente()->create())->get('/portal/editais/oculto/inscricao')
            ->assertNotFound();
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function foraDoPortal(): array
    {
        return [
            'rascunho da retaguarda' => [['publicado' => false]],
            'sem PDF' => [['pdf' => null]],
        ];
    }

    public function test_patrocinador_e_levado_aos_projetos(): void
    {
        Edital::factory()->create(['slug' => 'aberto']);

        $this->actingAs(User::factory()->patrocinador()->create())->get('/portal/editais/aberto/inscricao')
            ->assertRedirect(route('portal.projetos'));
    }

    public function test_inscricao_exige_login(): void
    {
        Edital::factory()->create(['slug' => 'aberto']);

        $this->get('/portal/editais/aberto/inscricao')->assertRedirect('/');
    }
}
