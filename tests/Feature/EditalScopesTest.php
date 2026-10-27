<?php

namespace Tests\Feature;

use App\Enums\SituacaoEdital;
use App\Models\Edital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EditalScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_vitrine_e_contagem(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 23:30', 'America/Bahia'));
        Edital::factory()->create(['inscricoes_inicio' => '2026-11-01', 'inscricoes_fim' => '2026-11-15']);
        Edital::factory()->create(['inscricoes_inicio' => '2026-12-01', 'inscricoes_fim' => '2026-12-20']);
        Edital::factory()->create(['inscricoes_inicio' => '2026-10-01', 'inscricoes_fim' => '2026-10-20']);
        Edital::factory()->create(['publicado' => false, 'inscricoes_inicio' => '2026-11-01', 'inscricoes_fim' => '2026-11-30']);

        $this->assertCount(2, Edital::naVitrine()->get());
        $this->assertSame(1, Edital::contarAbertos());
    }

    public function test_vitrine_e_contagem_ignoram_sem_pdf_e_excluidos(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'America/Bahia'));
        $dias = ['inscricoes_inicio' => '2026-11-01', 'inscricoes_fim' => '2026-11-30'];
        Edital::factory()->create($dias);
        Edital::factory()->semPdf()->create($dias);
        Edital::factory()->create($dias)->delete();

        $this->assertCount(1, Edital::naVitrine()->get());
        $this->assertSame(1, Edital::contarAbertos());
    }

    public function test_sem_editais_publicados_contagem_zero(): void
    {
        $this->assertSame(0, Edital::contarAbertos());
        $this->assertCount(0, Edital::naVitrine()->get());
    }

    public function test_slug_e_route_key_e_publicado_e_bool(): void
    {
        $edital = Edital::factory()->create(['publicado' => true])->fresh();

        $this->assertSame('slug', $edital->getRouteKeyName());
        $this->assertTrue($edital->publicado);
    }

    public function test_contagem_concorda_com_situacao_quando_data_guarda_hora(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00', 'America/Bahia'));
        $edital = Edital::factory()->create(['inscricoes_inicio' => '2026-11-01', 'inscricoes_fim' => '2026-11-30']);
        // Query builder: grava a hora de verdade (sem mutator/cast do model).
        DB::table('editais')->where('id', $edital->id)->update([
            'inscricoes_inicio' => '2026-11-15 17:40:50',
            'inscricoes_fim' => '2026-11-15 17:40:50',
        ]);

        $abertos = Edital::naVitrine()->get()
            ->filter(fn (Edital $e) => $e->situacao() === SituacaoEdital::Aberto)->count();

        $this->assertSame(1, $abertos);
        $this->assertSame($abertos, Edital::contarAbertos());
    }

    public function test_escrita_normaliza_para_inicio_do_dia_e_nao_muda_o_carbon_do_chamador(): void
    {
        $inicio = Carbon::parse('2026-11-15 17:40:50', 'America/Bahia');
        $edital = Edital::factory()->create(['inscricoes_inicio' => $inicio, 'inscricoes_fim' => '2026-11-20']);

        $this->assertSame('17:40:50', $inicio->format('H:i:s'));
        $bruto = DB::table('editais')->where('id', $edital->id)->value('inscricoes_inicio');
        $this->assertStringEndsWith('00:00:00', (string) $bruto);

        $agora = Carbon::parse('2026-11-15 23:30', 'UTC');
        Edital::contarAbertos($agora);
        $this->assertSame('UTC', $agora->getTimezone()->getName());
    }
}
