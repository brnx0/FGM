<?php

namespace Tests\Feature;

use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventoScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_realizados_usa_o_dia_de_salvador_e_nao_o_do_utc(): void
    {
        // 01:30 UTC de 15/11 ainda é noite de 14/11 em Salvador.
        $agora = Carbon::parse('2026-11-15 01:30', 'UTC');
        Evento::factory()->create(['titulo' => 'Hoje em Salvador', 'data_realizacao' => '2026-11-14']);
        Evento::factory()->create(['titulo' => 'Amanhã em Salvador', 'data_realizacao' => '2026-11-15']);

        $this->assertSame(['Hoje em Salvador'], Evento::realizados($agora)->pluck('titulo')->all());
        $this->assertSame('2026-11-15 01:30', $agora->format('Y-m-d H:i'));
    }
}
