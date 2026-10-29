<?php

namespace Tests\Feature;

use App\Models\Edital;
use App\Models\EditalDocumento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentoModeloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function documentoComModelo(Edital $edital): EditalDocumento
    {
        Storage::disk('local')->put('editais/modelos/m.docx', 'conteúdo');

        return EditalDocumento::factory()->for($edital)->create(['modelo' => 'editais/modelos/m.docx', 'modelo_nome' => 'Declaracao.docx']);
    }

    public function test_visitante_baixa_modelo_de_edital_visivel(): void
    {
        $edital = Edital::factory()->create();
        $documento = $this->documentoComModelo($edital);

        $this->get(route('editais.documentos.modelo', [$edital, $documento]))
            ->assertOk()
            ->assertDownload('Declaracao.docx');
    }

    public function test_edital_nao_visivel_documento_de_outro_edital_ou_sem_modelo_da_404(): void
    {
        $despublicado = Edital::factory()->create(['publicado' => false]);
        $documento = $this->documentoComModelo($despublicado);
        $this->get("/editais/{$despublicado->slug}/documentos/{$documento->id}/modelo")->assertNotFound();

        $visivel = Edital::factory()->create();
        $this->get("/editais/{$visivel->slug}/documentos/{$documento->id}/modelo")->assertNotFound();

        $semModelo = EditalDocumento::factory()->for($visivel)->create(['modelo' => null]);
        $this->get("/editais/{$visivel->slug}/documentos/{$semModelo->id}/modelo")->assertNotFound();

        $arquivoSumiu = EditalDocumento::factory()->for($visivel)->create(['modelo' => 'editais/modelos/sumiu.pdf']);
        $this->get("/editais/{$visivel->slug}/documentos/{$arquivoSumiu->id}/modelo")->assertNotFound();
    }

    public function test_documento_nao_numerico_da_404_sem_chegar_ao_banco(): void
    {
        $edital = Edital::factory()->create();

        $this->get("/editais/{$edital->slug}/documentos/abc/modelo")->assertNotFound();
    }
}
