<?php

namespace Tests\Feature\Retaguarda;

use App\Models\Edital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class EditaisTest extends TestCase
{
    use RefreshDatabase;

    /** PNG de 1×1 pixel (o container não tem GD para UploadedFile::fake()->image()). */
    private const string PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
        $this->actingAs(User::factory()->gestor()->create());
    }

    /**
     * Arquivo enviado como num upload real: o tipo vem do conteúdo (finfo), não da extensão.
     * UploadedFile::fake() informa o tipo pela extensão e não serviria para o arquivo falso.
     */
    private function arquivoEnviado(string $nome, string $conteudo): UploadedFile
    {
        $caminho = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($caminho, $conteudo);

        return new UploadedFile($caminho, $nome, null, null, true);
    }

    private function pdf(string $nome = 'edital.pdf'): UploadedFile
    {
        return $this->arquivoEnviado($nome, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    private function imagem(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('capa.png', base64_decode(self::PNG));
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function dados(array $alteracoes = []): array
    {
        return [
            'titulo' => 'Prêmio Cultura Viva',
            'resumo' => 'Apoio a pontos de cultura.',
            'categoria' => 'Fomento',
            'inscricoes_inicio' => '2026-11-01',
            'inscricoes_fim' => '2026-11-30',
            'publicado' => '0',
            ...$alteracoes,
        ];
    }

    public function test_lista_com_busca_e_filtros(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'America/Bahia'));
        Edital::factory()->create(['titulo' => 'Prêmio Cultura Viva', 'inscricoes_inicio' => '2026-11-01', 'inscricoes_fim' => '2026-11-30']);
        Edital::factory()->create(['titulo' => 'Edital de Audiovisual', 'inscricoes_inicio' => '2026-12-01', 'inscricoes_fim' => '2026-12-20']);
        Edital::factory()->semPdf()->create(['titulo' => 'Rede de Leitura', 'publicado' => false, 'inscricoes_inicio' => '2026-10-01', 'inscricoes_fim' => '2026-11-09']);
        Edital::factory()->create(['titulo' => 'Cultura excluída'])->delete();

        $this->get('/retaguarda')->assertRedirect('/retaguarda/editais');

        $this->get('/retaguarda/editais')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Retaguarda/Editais/Index')
                ->has('editais.data', 3)
                ->where('editais.per_page', 15));

        $this->get('/retaguarda/editais?busca=CULTURA')
            ->assertInertia(fn (Assert $p) => $p
                ->has('editais.data', 1)
                ->where('editais.data.0.titulo', 'Prêmio Cultura Viva')
                ->where('editais.data.0.periodo', '01/11/2026 a 30/11/2026')
                ->where('editais.data.0.situacao', 'aberto')
                ->where('editais.data.0.publicado', true)
                ->where('editais.data.0.temPdf', true)
                ->where('filtros.busca', 'CULTURA'));

        $this->get('/retaguarda/editais?situacao=em_breve')
            ->assertInertia(fn (Assert $p) => $p->has('editais.data', 1)->where('editais.data.0.titulo', 'Edital de Audiovisual'));

        $this->get('/retaguarda/editais?situacao=encerrado')
            ->assertInertia(fn (Assert $p) => $p->has('editais.data', 1)->where('editais.data.0.titulo', 'Rede de Leitura'));

        $this->get('/retaguarda/editais?situacao=aberto')
            ->assertInertia(fn (Assert $p) => $p->has('editais.data', 1)->where('editais.data.0.titulo', 'Prêmio Cultura Viva'));

        $this->get('/retaguarda/editais?publicado=nao')
            ->assertInertia(fn (Assert $p) => $p
                ->has('editais.data', 1)
                ->where('editais.data.0.titulo', 'Rede de Leitura')
                ->where('editais.data.0.temPdf', false));

        $this->get('/retaguarda/editais?publicado=sim')
            ->assertInertia(fn (Assert $p) => $p->has('editais.data', 2));
    }

    public function test_cria_edital_com_pdf_e_imagem(): void
    {
        $this->post('/retaguarda/editais', $this->dados([
            'publicado' => '1',
            'pdf' => $this->pdf(),
            'imagem' => $this->imagem(),
        ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/retaguarda/editais')
            ->assertSessionHas('sucesso', 'Edital salvo.');

        $edital = Edital::sole();
        $this->assertSame('premio-cultura-viva', $edital->slug);
        $this->assertTrue($edital->publicado);
        $this->assertSame('2026-11-30', $edital->inscricoes_fim->toDateString());
        $this->assertStringStartsWith('editais/', $edital->pdf);
        $this->assertStringStartsWith('editais/', $edital->imagem);
        Storage::disk('local')->assertExists($edital->pdf);
        Storage::disk('public')->assertExists($edital->imagem);
    }

    public function test_nao_publica_sem_pdf(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['publicado' => '1']))
            ->assertSessionHasErrors(['pdf' => 'Envie o PDF do edital para publicar.']);

        $this->assertSame(0, Edital::count());

        // Rascunho sem PDF pode ser salvo.
        $this->post('/retaguarda/editais', $this->dados())->assertSessionHasNoErrors();
        $this->assertNull(Edital::sole()->pdf);
    }

    public function test_arquivo_falso_com_extensao_pdf_e_recusado(): void
    {
        $this->post('/retaguarda/editais', $this->dados([
            'pdf' => $this->arquivoEnviado('edital.pdf', 'texto qualquer'),
        ]))->assertSessionHasErrors(['pdf' => 'O arquivo precisa ser um PDF.']);

        $this->assertSame(0, Edital::count());
    }

    public function test_editar_sem_reenviar_mantem_arquivos(): void
    {
        $edital = Edital::factory()->create(['pdf' => 'editais/atual.pdf', 'imagem' => 'editais/atual.png', 'publicado' => true]);
        Storage::disk('local')->put('editais/atual.pdf', 'pdf');
        Storage::disk('public')->put('editais/atual.png', 'png');

        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados(['titulo' => 'Título novo', 'publicado' => '1']))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/retaguarda/editais')
            ->assertSessionHas('sucesso', 'Edital salvo.');

        $edital->refresh();
        $this->assertSame('Título novo', $edital->titulo);
        $this->assertSame('editais/atual.pdf', $edital->pdf);
        $this->assertSame('editais/atual.png', $edital->imagem);
        Storage::disk('local')->assertExists('editais/atual.pdf');
        Storage::disk('public')->assertExists('editais/atual.png');
    }

    public function test_slug_nao_muda_ao_editar_titulo(): void
    {
        $edital = Edital::factory()->create(['slug' => 'slug-original']);

        $this->put('/retaguarda/editais/slug-original', $this->dados(['titulo' => 'Outro título']))->assertSessionHasNoErrors();

        $this->assertSame('slug-original', $edital->refresh()->slug);
    }

    public function test_trocar_pdf_apaga_o_antigo(): void
    {
        $edital = Edital::factory()->create(['pdf' => 'editais/antigo.pdf']);
        Storage::disk('local')->put('editais/antigo.pdf', 'pdf');

        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados(['pdf' => $this->pdf('novo.pdf')]))
            ->assertSessionHasNoErrors();

        $edital->refresh();
        $this->assertNotSame('editais/antigo.pdf', $edital->pdf);
        Storage::disk('local')->assertMissing('editais/antigo.pdf');
        Storage::disk('local')->assertExists($edital->pdf);
    }

    public function test_falha_ao_salvar_mantem_o_arquivo_antigo_e_descarta_o_novo(): void
    {
        $edital = Edital::factory()->create(['pdf' => 'editais/antigo.pdf']);
        Storage::disk('local')->put('editais/antigo.pdf', 'pdf');
        Edital::saving(fn () => false);

        $this->withoutExceptionHandling();

        try {
            $this->put("/retaguarda/editais/{$edital->slug}", $this->dados(['pdf' => $this->pdf('novo.pdf')]));
            $this->fail('A falha ao salvar deveria interromper a requisição.');
        } catch (RuntimeException) {
            // esperado
        }

        $this->assertSame('editais/antigo.pdf', $edital->refresh()->pdf);
        Storage::disk('local')->assertExists('editais/antigo.pdf');
        $this->assertCount(1, Storage::disk('local')->allFiles('editais'));
    }

    public function test_remover_imagem_apaga_o_arquivo(): void
    {
        $edital = Edital::factory()->create(['imagem' => 'editais/capa.png']);
        Storage::disk('public')->put('editais/capa.png', 'png');

        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados(['remover_imagem' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertNull($edital->refresh()->imagem);
        Storage::disk('public')->assertMissing('editais/capa.png');
    }

    public function test_slug_unico_mesmo_com_excluido(): void
    {
        Edital::factory()->create(['titulo' => 'Prêmio Cultura Viva', 'slug' => 'premio-cultura-viva'])->delete();

        $this->post('/retaguarda/editais', $this->dados())->assertSessionHasNoErrors();
        $this->post('/retaguarda/editais', $this->dados())->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            ['premio-cultura-viva-2', 'premio-cultura-viva-3'],
            Edital::pluck('slug')->all(),
        );
    }

    public function test_exclui_logicamente(): void
    {
        $edital = Edital::factory()->create(['pdf' => 'editais/fica.pdf']);
        Storage::disk('local')->put('editais/fica.pdf', 'pdf');

        $this->delete("/retaguarda/editais/{$edital->slug}")
            ->assertRedirect('/retaguarda/editais')
            ->assertSessionHas('sucesso', 'Edital excluído.');

        $this->assertSoftDeleted($edital);
        Storage::disk('local')->assertExists('editais/fica.pdf');
    }

    public function test_alterna_publicacao(): void
    {
        $edital = Edital::factory()->create(['publicado' => false]);

        $this->patch("/retaguarda/editais/{$edital->slug}/publicacao")
            ->assertSessionHas('sucesso', 'Edital publicado.');
        $this->assertTrue($edital->refresh()->publicado);

        $this->patch("/retaguarda/editais/{$edital->slug}/publicacao")
            ->assertSessionHas('sucesso', 'Edital despublicado.');
        $this->assertFalse($edital->refresh()->publicado);

        $semPdf = Edital::factory()->semPdf()->create(['publicado' => false]);

        $this->patch("/retaguarda/editais/{$semPdf->slug}/publicacao")
            ->assertSessionHas('erro', 'Envie o PDF do edital para publicar.');
        $this->assertFalse($semPdf->refresh()->publicado);
    }

    public function test_gestor_baixa_pdf_de_edital_despublicado(): void
    {
        $edital = Edital::factory()->create(['pdf' => 'editais/rascunho.pdf', 'publicado' => false]);
        Storage::disk('local')->put('editais/rascunho.pdf', 'pdf');

        $this->get("/retaguarda/editais/{$edital->slug}/pdf")->assertOk()->assertDownload("edital-{$edital->slug}.pdf");
        $this->get("/editais/{$edital->slug}/pdf")->assertNotFound();
    }

    public function test_proponente_nao_acessa(): void
    {
        $edital = Edital::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get('/retaguarda/editais')->assertRedirect('/retaguarda/login');
        $this->post('/retaguarda/editais', $this->dados())->assertRedirect('/retaguarda/login');
        $this->delete("/retaguarda/editais/{$edital->slug}")->assertRedirect('/retaguarda/login');

        $this->assertSame(1, Edital::count());
    }
}
