<?php

namespace Tests\Feature;

use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventoPaginaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_do_evento_publicado(): void
    {
        Evento::factory()->create([
            'titulo' => 'Jornada do Patrimônio',
            'slug' => 'jornada-do-patrimonio',
            'resumo' => 'Visitas guiadas.',
            'descricao' => '<h2>Programação</h2><p>Visitas às 9h.</p>',
            'data_realizacao' => '2026-08-15',
            'hora_inicio' => '09:00',
            'hora_fim' => '17:30',
            'local' => 'Centro Histórico',
            'endereco' => 'Largo do Pelourinho, Salvador/BA',
            'latitude' => -12.9718,
            'longitude' => -38.5089,
            'imagem' => 'eventos/capa.png',
        ]);

        $this->get('/eventos/jornada-do-patrimonio')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Eventos/Show')
                ->has('evento', fn (Assert $e) => $e
                    ->where('titulo', 'Jornada do Patrimônio')
                    ->where('resumo', 'Visitas guiadas.')
                    ->where('descricao', '<h2>Programação</h2><p>Visitas às 9h.</p>')
                    ->where('dataRealizacao', '15 de agosto de 2026')
                    ->where('diaSemana', 'sábado')
                    ->where('horario', '9h às 17h30')
                    ->where('local', 'Centro Histórico')
                    ->where('endereco', 'Largo do Pelourinho, Salvador/BA')
                    ->where('latitude', -12.9718)
                    ->where('longitude', -38.5089)
                    ->where('imagem', Storage::disk('public')->url('eventos/capa.png'))
                    ->where('url', route('eventos.show', 'jornada-do-patrimonio'))));
    }

    public function test_detalhes_em_json_para_o_modal_da_pagina_inicial(): void
    {
        Evento::factory()->create([
            'titulo' => 'Capoeira Viva',
            'slug' => 'capoeira-viva',
            'descricao' => '<p>Rodas abertas.</p>',
            'data_realizacao' => '2026-09-06',
            'hora_inicio' => '19:00',
            'local' => 'Barroquinha',
            'latitude' => -12.9747,
            'longitude' => -38.5102,
        ]);

        $this->getJson('/eventos/capoeira-viva/detalhes')
            ->assertOk()
            ->assertJson([
                'titulo' => 'Capoeira Viva',
                'descricao' => '<p>Rodas abertas.</p>',
                'dataRealizacao' => '6 de setembro de 2026',
                'diaSemana' => 'domingo',
                'horario' => 'A partir das 19h',
                'endereco' => null,
                'latitude' => -12.9747,
                'longitude' => -38.5102,
                'url' => route('eventos.show', 'capoeira-viva'),
            ]);
    }

    public function test_detalhes_de_evento_despublicado_ou_excluido_da_404(): void
    {
        $despublicado = Evento::factory()->create(['publicado' => false]);
        $excluido = Evento::factory()->create();
        $excluido->delete();

        $this->getJson("/eventos/{$despublicado->slug}/detalhes")->assertNotFound();
        $this->getJson("/eventos/{$excluido->slug}/detalhes")->assertNotFound();
    }

    #[DataProvider('horarios')]
    public function test_horario_por_extenso(?string $inicio, ?string $fim, ?string $esperado): void
    {
        $evento = Evento::factory()->create(['hora_inicio' => $inicio, 'hora_fim' => $fim]);

        $this->getJson("/eventos/{$evento->slug}/detalhes")->assertJsonPath('horario', $esperado);
    }

    /** @return array<string, array{?string, ?string, ?string}> */
    public static function horarios(): array
    {
        return [
            'início e término' => ['19:00', '22:30', '19h às 22h30'],
            'só início' => ['09:05', null, 'A partir das 9h05'],
            'sem horário' => [null, null, null],
        ];
    }

    public function test_evento_futuro_publicado_tambem_abre(): void
    {
        $this->travelTo(Carbon::parse('2026-10-26 10:00', 'America/Bahia'));
        $evento = Evento::factory()->create(['data_realizacao' => '2026-12-01', 'descricao' => null]);

        $this->get("/eventos/{$evento->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('evento.descricao', null)
                ->where('evento.imagem', null)
                ->where('evento.dataRealizacao', '1 de dezembro de 2026'));
    }

    public function test_evento_despublicado_da_404(): void
    {
        $evento = Evento::factory()->create(['publicado' => false]);

        $this->get("/eventos/{$evento->slug}")->assertNotFound();
    }

    public function test_evento_excluido_da_404(): void
    {
        $evento = Evento::factory()->create();
        $evento->delete();

        $this->get("/eventos/{$evento->slug}")->assertNotFound();
    }

    public function test_home_nao_le_a_descricao_dos_eventos(): void
    {
        Evento::factory()->create(['descricao' => '<p>Longa</p>']);
        DB::enableQueryLog();

        $this->get('/')->assertOk();

        $consultas = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => stripos($sql, 'eventos') !== false);
        $this->assertNotEmpty($consultas);
        foreach ($consultas as $sql) {
            $this->assertStringNotContainsStringIgnoringCase('descricao', $sql);
            // Sem o comentário de dica do Oracle (/*+ FIRST_ROWS(n) */), nenhum "*": colunas explícitas.
            $this->assertStringNotContainsString('*', preg_replace('#/\*.*?\*/#', '', $sql));
        }
    }

    public function test_home_traz_slug_de_cada_evento(): void
    {
        Evento::factory()->create(['slug' => 'capoeira-viva']);

        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('eventos.0.slug', 'capoeira-viva'));
    }
}
