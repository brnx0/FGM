<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
}
