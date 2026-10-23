<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lista_vitrine_e_eventos(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'America/Bahia'));
        Edital::factory()->create([
            'titulo' => 'PNAB Salvador — Ciclo II',
            'slug' => 'pnab-ii',
            'categoria' => 'Fomento',
            'inscricoes_inicio' => '2026-11-01',
            'inscricoes_fim' => '2026-11-15',
        ]);
        Edital::factory()->create(['inscricoes_inicio' => '2026-11-05', 'inscricoes_fim' => '2026-11-30']);
        Edital::factory()->create(['inscricoes_inicio' => '2026-12-01', 'inscricoes_fim' => '2026-12-20']);
        Evento::factory()->create([
            'titulo' => 'Jornada do Patrimônio',
            'data_realizacao' => '2026-08-15',
            'local' => 'Centro Histórico',
        ]);
        Evento::factory()->create(['data_realizacao' => '2026-07-01']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Home')
            ->where('totalAbertos', 2)
            ->has('editais', 3)
            ->has('editais.0', fn (Assert $e) => $e
                ->where('titulo', 'PNAB Salvador — Ciclo II')
                ->where('slug', 'pnab-ii')
                ->where('categoria', 'Fomento')
                ->where('situacao', 'aberto')
                ->where('situacaoRotulo', 'Aberto')
                ->where('inscricoesInicio', '01/11/2026')
                ->where('inscricoesFim', '15/11/2026'))
            ->where('editais.2.situacao', 'em_breve')
            ->where('editais.2.inscricoesInicio', '01/12/2026')
            ->has('eventos', 2)
            ->has('eventos.0', fn (Assert $e) => $e
                ->where('titulo', 'Jornada do Patrimônio')
                ->where('dataRealizacao', 'agosto de 2026')
                ->where('local', 'Centro Histórico')
                ->has('resumo')
                ->where('imagem', null)));
    }

    public function test_home_limita_eventos_a_seis(): void
    {
        Evento::factory()->count(8)->create();

        $this->get('/')->assertInertia(fn (Assert $p) => $p->has('eventos', 6));
    }

    public function test_home_vazia_nao_quebra(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('totalAbertos', 0)->has('editais', 0)->has('eventos', 0));
    }

    public function test_pagina_do_edital_publicado(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'America/Bahia'));
        Edital::factory()->create([
            'titulo' => 'SalCine 2',
            'slug' => 'salcine-2',
            'categoria' => 'Audiovisual',
            'resumo' => 'Apoio ao audiovisual.',
            'inscricoes_inicio' => '2026-11-05',
            'inscricoes_fim' => '2026-11-30',
        ]);

        $this->get('/editais/salcine-2')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Editais/Show')
            ->has('edital', fn (Assert $e) => $e
                ->where('titulo', 'SalCine 2')
                ->where('slug', 'salcine-2')
                ->where('categoria', 'Audiovisual')
                ->where('resumo', 'Apoio ao audiovisual.')
                ->where('situacao', 'aberto')
                ->where('situacaoRotulo', 'Aberto')
                ->where('inscricoesInicio', '05/11/2026')
                ->where('inscricoesFim', '30/11/2026')));
    }

    public function test_edital_despublicado_da_404(): void
    {
        Edital::factory()->create(['slug' => 'oculto', 'publicado' => false]);

        $this->get('/editais/oculto')->assertNotFound();
    }

    public function test_paginas_institucionais_abrem(): void
    {
        foreach ([
            '/a-fgm' => 'Institucional/AFgm',
            '/duvidas' => 'Institucional/Duvidas',
            '/privacidade' => 'Institucional/Privacidade',
            '/termos' => 'Institucional/Termos',
        ] as $url => $componente) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($componente));
        }
    }
}
