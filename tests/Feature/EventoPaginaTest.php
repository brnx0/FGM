<?php

namespace Tests\Feature;

use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
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
            'local' => 'Centro Histórico',
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
                    ->where('local', 'Centro Histórico')
                    ->where('imagem', Storage::disk('public')->url('eventos/capa.png'))));
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
