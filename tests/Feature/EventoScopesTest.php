<?php

namespace Tests\Feature;

use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventoScopesTest extends TestCase
{
    use RefreshDatabase;

    public function test_proximos_usa_o_dia_de_salvador_e_nao_o_do_utc(): void
    {
        // 01:30 UTC de 15/11 ainda é noite de 14/11 em Salvador: o evento de 14/11 continua na agenda.
        $agora = Carbon::parse('2026-11-15 01:30', 'UTC');
        Evento::factory()->create(['titulo' => 'Ontem em Salvador', 'data_realizacao' => '2026-11-13']);
        Evento::factory()->create(['titulo' => 'Amanhã em Salvador', 'data_realizacao' => '2026-11-15']);
        Evento::factory()->create(['titulo' => 'Hoje em Salvador', 'data_realizacao' => '2026-11-14']);

        $this->assertSame(['Hoje em Salvador', 'Amanhã em Salvador'], Evento::proximos($agora)->pluck('titulo')->all());
        $this->assertSame('2026-11-15 01:30', $agora->format('Y-m-d H:i'));
    }

    public function test_descricao_e_limpa_em_qualquer_gravacao(): void
    {
        $evento = Evento::create([
            'titulo' => 'Direto no modelo',
            'slug' => 'direto-no-modelo',
            'resumo' => 'Resumo.',
            'descricao' => '<p>a</p><script>x</script>',
            'data_realizacao' => '2026-09-06',
        ]);

        $this->assertSame('<p>a</p>', $evento->refresh()->descricao);

        $evento->update(['descricao' => '<p onclick="x()">b</p>']);
        $this->assertSame('<p>b</p>', $evento->refresh()->descricao);
    }
}
