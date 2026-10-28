<?php

namespace Tests\Feature\Retaguarda;

use App\Enums\TipoFomento;
use App\Models\Edital;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            'tipo_fomento' => 'direto',
            'divulgacao' => '<p>Apoio a pontos de cultura.</p>',
            'categoria' => 'Fomento',
            'vigencia_inicio' => '2026-11-01',
            'vigencia_fim' => '2027-10-31',
            'inscricoes_inicio' => '2026-11-01',
            'inscricoes_fim' => '2026-11-30',
            'publicado' => '0',
            ...$alteracoes,
        ];
    }

    public function test_grava_tipo_de_fomento_vigencia_e_divulgacao(): void
    {
        $this->post('/retaguarda/editais', $this->dados())->assertSessionHasNoErrors();

        $edital = Edital::sole();
        $this->assertSame(TipoFomento::Direto, $edital->tipo_fomento);
        $this->assertSame('2026-11-01', $edital->vigencia_inicio->toDateString());
        $this->assertSame('2027-10-31', $edital->vigencia_fim->toDateString());
        $this->assertSame('<p>Apoio a pontos de cultura.</p>', $edital->divulgacao);
    }

    public function test_tipo_de_fomento_e_obrigatorio_e_da_lista(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['tipo_fomento' => '']))
            ->assertSessionHasErrors('tipo_fomento');
        $this->post('/retaguarda/editais', $this->dados(['tipo_fomento' => 'outro']))
            ->assertSessionHasErrors(['tipo_fomento' => 'Selecione uma opção da lista.']);

        $this->assertSame(0, Edital::count());
    }

    public function test_fim_da_vigencia_nao_pode_ser_antes_do_inicio(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['vigencia_inicio' => '2026-11-01', 'vigencia_fim' => '2026-10-31']))
            ->assertSessionHasErrors(['vigencia_fim' => 'O fim da vigência não pode ser antes do início.']);
    }

    public function test_fim_da_vigencia_pode_ficar_vazio(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['vigencia_fim' => '']))->assertSessionHasNoErrors();

        $this->assertNull(Edital::sole()->vigencia_fim);
    }

    public function test_inicio_da_vigencia_e_obrigatorio(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['vigencia_inicio' => '']))
            ->assertSessionHasErrors('vigencia_inicio');
    }

    public function test_divulgacao_e_limpa_ao_gravar(): void
    {
        $this->post('/retaguarda/editais', $this->dados([
            'divulgacao' => '<script>alert(1)</script><p onclick="x">Oi</p>',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('<p>Oi</p>', Edital::sole()->divulgacao);
    }

    public function test_divulgacao_vazia_e_recusada(): void
    {
        foreach (['', '<p></p>', '<p> </p>', '<p>&nbsp;</p>'] as $vazia) {
            $this->post('/retaguarda/editais', $this->dados(['divulgacao' => $vazia]))
                ->assertSessionHasErrors(['divulgacao' => 'Este campo é obrigatório.']);
        }

        $this->assertSame(0, Edital::count());
    }

    public function test_divulgacao_so_com_imagem_e_aceita(): void
    {
        $this->post('/retaguarda/editais', $this->dados([
            'divulgacao' => '<img src="/storage/eventos/conteudo/a.png" alt="Cartaz">',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('<img src="/storage/eventos/conteudo/a.png" alt="Cartaz">', Edital::sole()->divulgacao);
    }

    public function test_mensagens_usam_nomes_legiveis_dos_campos(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['vigencia_inicio' => '']))
            ->assertSessionHasErrors(['vigencia_inicio' => 'O campo início da vigência é obrigatório.']);

        $this->post('/retaguarda/editais', $this->dados([
            'categorias' => [['nome' => 'A', 'valor' => '10', 'vagas' => '0', 'naturezas' => ['sem_fins']]],
        ]))->assertSessionHasErrors(['categorias.0.vagas' => 'O campo vagas deve ser ao menos 1.']);
    }

    public function test_edicao_envia_os_campos_novos_e_as_opcoes_de_fomento(): void
    {
        $edital = Edital::factory()->create([
            'tipo_fomento' => TipoFomento::Indireto,
            'vigencia_inicio' => '2026-11-01',
            'vigencia_fim' => null,
            'divulgacao' => '<p>Texto.</p>',
        ]);

        $opcoes = [['valor' => 'direto', 'rotulo' => 'Direto'], ['valor' => 'indireto', 'rotulo' => 'Indireto']];

        $this->get("/retaguarda/editais/{$edital->slug}/edit")->assertInertia(fn (Assert $p) => $p
            ->component('Retaguarda/Editais/Form')
            ->where('edital.tipo_fomento', 'indireto')
            ->where('edital.vigencia_inicio', '2026-11-01')
            ->where('edital.vigencia_fim', null)
            ->where('edital.divulgacao', '<p>Texto.</p>')
            ->missing('edital.resumo')
            ->where('tiposFomento', $opcoes));

        $this->get('/retaguarda/editais/create')->assertInertia(fn (Assert $p) => $p
            ->where('tiposFomento', $opcoes));
    }

    public function test_lista_nao_carrega_a_divulgacao(): void
    {
        Edital::factory()->create();
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas): void {
            $consultas[] = strtolower($consulta->sql);
        });

        $this->get('/retaguarda/editais')->assertOk();

        $selects = array_filter($consultas, fn (string $sql): bool => str_contains($sql, 'from "editais"') && str_starts_with($sql, 'select') && ! str_contains($sql, 'count('));
        $this->assertNotEmpty($selects);
        foreach ($selects as $sql) {
            $this->assertStringNotContainsString('divulgacao', $sql);
            $this->assertStringNotContainsString('select *', $sql);
        }
    }

    public function test_home_e_portal_nao_carregam_a_divulgacao(): void
    {
        Edital::factory()->create();
        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas): void {
            $consultas[] = strtolower($consulta->sql);
        });

        $this->get('/')->assertOk();
        auth()->logout();
        $this->actingAs(User::factory()->proponente()->create())->get('/portal/editais')->assertOk();

        $selects = array_filter($consultas, fn (string $sql): bool => str_contains($sql, 'from "editais"') && str_starts_with($sql, 'select') && ! str_contains($sql, 'count('));
        $this->assertGreaterThanOrEqual(2, count($selects));
        foreach ($selects as $sql) {
            $this->assertStringNotContainsString('divulgacao', $sql);
            $this->assertStringNotContainsString('select *', $sql);
        }
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

    public function test_pdf_que_o_php_recusou_mostra_aviso_de_tamanho(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'upl');
        $pdfGrandeDemais = new UploadedFile($caminho, 'edital.pdf', null, UPLOAD_ERR_INI_SIZE, true);

        $this->post('/retaguarda/editais', $this->dados(['pdf' => $pdfGrandeDemais]))
            ->assertSessionHasErrors(['pdf' => 'Não foi possível enviar o arquivo. Ele pode ser maior que o permitido.']);
    }

    public function test_envio_acima_do_limite_do_php_volta_ao_formulario_com_aviso(): void
    {
        $this->call('POST', '/retaguarda/editais', server: [
            'CONTENT_LENGTH' => (string) (1024 ** 3),
            'HTTP_X_INERTIA' => 'true',
            'HTTP_REFERER' => url('/retaguarda/editais/create'),
        ])
            ->assertRedirect('/retaguarda/editais/create')
            ->assertSessionHasErrors(['pdf' => 'Os arquivos enviados passam do tamanho máximo permitido.']);
    }

    public function test_envio_acima_do_limite_do_php_no_portal_volta_com_aviso_geral(): void
    {
        $this->call('POST', '/cadastro', server: [
            'CONTENT_LENGTH' => (string) (1024 ** 3),
            'HTTP_X_INERTIA' => 'true',
            'HTTP_REFERER' => url('/cadastro/pessoa-fisica'),
        ])
            ->assertRedirect('/cadastro/pessoa-fisica')
            ->assertSessionHas('erro', 'Os arquivos enviados passam do tamanho máximo permitido.');
    }
}
