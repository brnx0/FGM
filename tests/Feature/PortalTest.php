<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\Evento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
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
        Evento::factory()->create(['titulo' => 'Jornada do Patrimônio', 'data_realizacao' => '2026-08-15', 'local' => 'Centro Histórico']);
        Evento::factory()->create(['titulo' => 'Dois de Julho', 'data_realizacao' => '2026-07-01']);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Home')
            ->where('totalAbertos', 2)
            ->has('editais', 3)
            ->has('editais.0', fn (Assert $e) => $e
                ->where('titulo', 'PNAB Salvador — Ciclo II')
                ->where('slug', 'pnab-ii')
                ->where('pdf', route('editais.pdf', 'pnab-ii'))
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
                ->where('mes', 'agosto')
                ->where('ano', '2026')
                ->where('local', 'Centro Histórico')
                ->where('imagem', null)
                ->has('resumo')));
    }

    public function test_home_limita_eventos_a_seis(): void
    {
        foreach (range(1, 8) as $mes) {
            Evento::factory()->create(['titulo' => "Evento {$mes}", 'data_realizacao' => sprintf('2025-%02d-10', $mes)]);
        }

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->has('eventos', 6)
            ->where('eventos.0.titulo', 'Evento 8'));
    }

    public function test_evento_futuro_despublicado_e_excluido_ficam_de_fora(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'America/Bahia'));
        Evento::factory()->create(['titulo' => 'Aparece', 'data_realizacao' => '2026-11-10']);
        Evento::factory()->create(['data_realizacao' => '2026-11-11']);
        Evento::factory()->create(['data_realizacao' => '2026-09-01', 'publicado' => false]);
        Evento::factory()->create(['data_realizacao' => '2026-09-02'])->delete();

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->has('eventos', 1)
            ->where('eventos.0.titulo', 'Aparece'));
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
                ->where('inscricoesFim', '30/11/2026')
                ->where('pdf', route('editais.pdf', 'salcine-2'))));
    }

    public function test_baixa_o_pdf_do_edital(): void
    {
        Storage::fake('local')->put('editais/teste.pdf', '%PDF-1.4');
        Edital::factory()->create(['slug' => 'com-pdf', 'pdf' => 'editais/teste.pdf']);

        $this->get('/editais/com-pdf/pdf')
            ->assertOk()
            ->assertDownload('edital-com-pdf.pdf')
            ->assertStreamedContent('%PDF-1.4');
    }

    public function test_edital_sem_pdf_da_404_na_pagina(): void
    {
        Edital::factory()->semPdf()->create(['slug' => 'sem-pdf-pagina']);

        $this->get('/editais/sem-pdf-pagina')->assertNotFound();
    }

    public function test_pdf_ausente_no_disco_da_404(): void
    {
        Storage::fake('local');
        Edital::factory()->create(['slug' => 'arquivo-sumiu', 'pdf' => 'editais/inexistente.pdf']);

        $this->get('/editais/arquivo-sumiu/pdf')->assertNotFound();
    }

    public function test_edital_sem_pdf_nao_aparece_no_portal(): void
    {
        Edital::factory()->semPdf()->create(['slug' => 'sem-pdf']);

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('totalAbertos', 0)->has('editais', 0));
        $this->get('/editais/sem-pdf/pdf')->assertNotFound();
    }

    public function test_edital_excluido_da_404_na_pagina_e_no_pdf(): void
    {
        Storage::fake('local')->put('editais/teste.pdf', '%PDF-1.4');
        Edital::factory()->create(['slug' => 'excluido'])->delete();

        $this->get('/editais/excluido')->assertNotFound();
        $this->get('/editais/excluido/pdf')->assertNotFound();
    }

    public function test_pdf_de_edital_despublicado_da_404(): void
    {
        Storage::fake('local')->put('editais/teste.pdf', '%PDF-1.4');
        Edital::factory()->create(['slug' => 'oculto-pdf', 'publicado' => false]);

        $this->get('/editais/oculto-pdf/pdf')->assertNotFound();
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
