<?php

namespace Tests\Feature;

use App\Enums\SituacaoEdital;
use App\Models\Edital;
use App\Models\Evento;
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

    public function test_eventos_realizados_so_publicados_e_passados(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 23:30', 'America/Bahia'));
        Evento::factory()->create(['data_realizacao' => '2026-08-10']);
        Evento::factory()->create(['data_realizacao' => '2026-11-15']);
        Evento::factory()->create(['data_realizacao' => '2026-12-10']);
        Evento::factory()->create(['data_realizacao' => '2026-01-10', 'publicado' => false]);

        $eventos = Evento::realizados()->get();

        $this->assertCount(2, $eventos);
        $this->assertSame('2026-11-15', $eventos->first()->data_realizacao->toDateString());
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

    public function test_evento_no_mesmo_dia_com_hora_aparece_como_realizado(): void
    {
        $this->travelTo(Carbon::parse('2026-11-15 10:00', 'America/Bahia'));
        $evento = Evento::factory()->create(['data_realizacao' => '2026-11-01']);
        DB::table('eventos')->where('id', $evento->id)->update(['data_realizacao' => '2026-11-15 17:40:50']);

        $this->assertCount(1, Evento::realizados()->get());
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
        Evento::realizados($agora);
        $this->assertSame('UTC', $agora->getTimezone()->getName());
    }
}
