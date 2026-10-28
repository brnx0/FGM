<?php

namespace Tests\Feature\Retaguarda;

use App\Enums\NaturezaProponente;
use App\Models\Edital;
use App\Models\EditalCategoria;
use App\Models\EditalDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EditalDocumentosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->gestor()->create());
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function categoria(array $alteracoes = []): array
    {
        return [
            'nome' => 'Categoria',
            'valor' => '1000',
            'vagas' => '10',
            'naturezas' => ['pessoa_fisica'],
            ...$alteracoes,
        ];
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function documento(array $alteracoes = []): array
    {
        return [
            'nome' => 'Estatuto Social',
            'orientacao' => 'Em PDF.',
            'obrigatorio' => '1',
            'naturezas' => ['sem_fins'],
            'todas_categorias' => '1',
            'tamanho_max_mb' => '2',
            ...$alteracoes,
        ];
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
            'inscricoes_inicio' => '2026-11-01',
            'inscricoes_fim' => '2026-11-30',
            'publicado' => '0',
            'categorias' => [
                $this->categoria(['nome' => 'Primeira']),
                $this->categoria(['nome' => 'Segunda']),
            ],
            ...$alteracoes,
        ];
    }

    /**
     * @param  iterable<mixed>  $ids
     * @return list<int>
     */
    private function inteiros(iterable $ids): array
    {
        return array_map(intval(...), [...$ids]);
    }

    public function test_cria_edital_com_documentos_e_pivo(): void
    {
        $this->post('/retaguarda/editais', $this->dados([
            'documentos' => [
                $this->documento(['nome' => 'Para todas']),
                $this->documento(['nome' => 'So segunda', 'todas_categorias' => '0', 'categorias' => ['1'], 'tamanho_max_mb' => '5']),
                $this->documento(['nome' => 'Opcional', 'obrigatorio' => '0', 'orientacao' => '']),
            ],
        ]))->assertSessionHasNoErrors();

        $edital = Edital::firstOrFail();
        $documentos = $edital->documentos;

        $this->assertSame(['Para todas', 'So segunda', 'Opcional'], $documentos->pluck('nome')->all());
        $this->assertSame([1, 2, 3], $this->inteiros($documentos->pluck('ordem')));
        $this->assertTrue($documentos[0]->todas_categorias);
        $this->assertTrue($documentos[0]->obrigatorio);
        $this->assertSame(2, $documentos[0]->tamanho_max_mb);
        $this->assertFalse($documentos[1]->todas_categorias);
        $this->assertSame(5, $documentos[1]->tamanho_max_mb);
        $this->assertFalse($documentos[2]->obrigatorio);
        $this->assertNull($documentos[2]->orientacao);
        $this->assertSame([NaturezaProponente::SemFinsLucrativos], $documentos[0]->naturezas->all());

        $segunda = $edital->categorias[1];
        $this->assertSame([(int) $segunda->id], $this->inteiros($documentos[1]->categorias->pluck('id')));
        $this->assertSame(0, $documentos[0]->categorias()->count());
    }

    public function test_edicao_reordena_e_mantem_a_ligacao_pelo_id(): void
    {
        $edital = Edital::factory()->create();
        $a = EditalCategoria::factory()->for($edital)->create(['ordem' => 1, 'nome' => 'A']);
        $b = EditalCategoria::factory()->for($edital)->create(['ordem' => 2, 'nome' => 'B']);
        $documentoB = EditalDocumento::factory()->for($edital)->create(['ordem' => 1, 'nome' => 'Doc B', 'todas_categorias' => false]);
        $documentoB->categorias()->attach($b->id);
        $outro = EditalDocumento::factory()->for($edital)->create(['ordem' => 2, 'nome' => 'Outro']);

        // Categorias invertidas (B na posição 0) e documentos invertidos.
        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados([
            'categorias' => [
                $this->categoria(['id' => (string) $b->id, 'nome' => 'B']),
                $this->categoria(['id' => (string) $a->id, 'nome' => 'A']),
            ],
            'documentos' => [
                $this->documento(['id' => (string) $outro->id, 'nome' => 'Outro']),
                $this->documento(['id' => (string) $documentoB->id, 'nome' => 'Doc B', 'todas_categorias' => '0', 'categorias' => ['0']]),
            ],
        ]))->assertSessionHasNoErrors();

        $documentos = $edital->refresh()->documentos;
        $this->assertSame(['Outro', 'Doc B'], $documentos->pluck('nome')->all());
        $this->assertSame((int) $documentoB->id, (int) $documentos[1]->id);
        $this->assertSame([(int) $b->id], $this->inteiros($documentos[1]->categorias->pluck('id')));
    }

    public function test_ausencia_de_documentos_apaga_todos(): void
    {
        $edital = Edital::factory()->create();
        EditalDocumento::factory()->for($edital)->create();

        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados())->assertSessionHasNoErrors();

        $this->assertSame(0, $edital->documentos()->count());
    }

    public function test_documento_ausente_do_envio_e_apagado(): void
    {
        $edital = Edital::factory()->create();
        $mantido = EditalDocumento::factory()->for($edital)->create(['ordem' => 1]);
        $removido = EditalDocumento::factory()->for($edital)->create(['ordem' => 2]);

        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados([
            'documentos' => [$this->documento(['id' => (string) $mantido->id, 'nome' => 'Mantido'])],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('edital_documentos', ['id' => $removido->id]);
        $this->assertSame(['Mantido'], $edital->documentos()->pluck('nome')->all());
    }

    public function test_id_de_documento_de_outro_edital_nao_altera_o_outro(): void
    {
        $outroEdital = Edital::factory()->create();
        $alheio = EditalDocumento::factory()->for($outroEdital)->create(['nome' => 'Intocado']);
        $edital = Edital::factory()->create();

        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados([
            'documentos' => [$this->documento(['id' => (string) $alheio->id, 'nome' => 'Invasor'])],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Intocado', $alheio->refresh()->nome);
        $this->assertSame((int) $outroEdital->id, (int) $alheio->edital_id);
        $this->assertSame(['Invasor'], $edital->documentos()->pluck('nome')->all());
    }

    public function test_remover_a_categoria_ligada_recusa_o_documento_que_ficaria_sem_categoria(): void
    {
        $edital = Edital::factory()->create();
        $a = EditalCategoria::factory()->for($edital)->create(['ordem' => 1]);
        $documento = EditalDocumento::factory()->for($edital)->create(['todas_categorias' => false]);
        $documento->categorias()->attach($a->id);

        // O formulário enviaria o documento sem posições, pois a categoria ligada foi removida.
        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados([
            'documentos' => [$this->documento(['id' => (string) $documento->id, 'todas_categorias' => '0'])],
        ]))->assertSessionHasErrors(['documentos.0.categorias' => 'Escolha ao menos uma categoria ou marque todas.']);

        $this->assertSame(1, $documento->categorias()->count());
    }

    public function test_posicao_de_categoria_inexistente_e_recusada(): void
    {
        $this->post('/retaguarda/editais', $this->dados([
            'documentos' => [$this->documento(['todas_categorias' => '0', 'categorias' => ['5']])],
        ]))->assertSessionHasErrors(['documentos.0.categorias.0' => 'Categoria inválida.']);

        $this->assertSame(0, Edital::count());
    }

    public function test_documento_sem_natureza_e_recusado(): void
    {
        $this->post('/retaguarda/editais', $this->dados([
            'documentos' => [$this->documento(['naturezas' => []])],
        ]))->assertSessionHasErrors(['documentos.0.naturezas' => 'Marque ao menos uma natureza.']);
    }

    public function test_tamanho_maximo_fora_de_1_a_20_e_recusado(): void
    {
        foreach (['0', '21'] as $tamanho) {
            $this->post('/retaguarda/editais', $this->dados([
                'documentos' => [$this->documento(['tamanho_max_mb' => $tamanho])],
            ]))->assertSessionHasErrors('documentos.0.tamanho_max_mb');
        }

        $this->post('/retaguarda/editais', $this->dados([
            'documentos' => [$this->documento(['tamanho_max_mb' => '20'])],
        ]))->assertSessionHasNoErrors();
    }

    public function test_edit_envia_documentos_com_posicoes_das_categorias(): void
    {
        $edital = Edital::factory()->create();
        EditalCategoria::factory()->for($edital)->create(['ordem' => 1]);
        $b = EditalCategoria::factory()->for($edital)->create(['ordem' => 2]);
        $documento = EditalDocumento::factory()->for($edital)->create([
            'nome' => 'Roteiro',
            'orientacao' => 'Em PDF.',
            'obrigatorio' => false,
            'naturezas' => [NaturezaProponente::Mei, NaturezaProponente::PessoaFisica],
            'todas_categorias' => false,
            'tamanho_max_mb' => 7,
        ]);
        $documento->categorias()->attach($b->id);

        $this->get("/retaguarda/editais/{$edital->slug}/edit")
            ->assertInertia(fn (Assert $page) => $page
                ->where('edital.documentos', [[
                    'id' => (int) $documento->id,
                    'nome' => 'Roteiro',
                    'orientacao' => 'Em PDF.',
                    'obrigatorio' => false,
                    'naturezas' => ['mei', 'pessoa_fisica'],
                    'todas_categorias' => false,
                    'categorias' => [1],
                    'tamanho_max_mb' => 7,
                ]]));
    }

    public function test_vale_para_categoria_e_natureza(): void
    {
        $edital = Edital::factory()->create();
        $a = EditalCategoria::factory()->for($edital)->create(['ordem' => 1]);
        $b = EditalCategoria::factory()->for($edital)->create(['ordem' => 2]);

        $todas = EditalDocumento::factory()->for($edital)->create(['naturezas' => [NaturezaProponente::Mei]]);
        $this->assertTrue($todas->valeParaCategoria($a));
        $this->assertTrue($todas->valeParaCategoria($b));
        $this->assertTrue($todas->valeParaNatureza(NaturezaProponente::Mei));
        $this->assertFalse($todas->valeParaNatureza(NaturezaProponente::PessoaFisica));

        $so = EditalDocumento::factory()->for($edital)->create(['todas_categorias' => false]);
        $so->categorias()->attach($a->id);
        $this->assertTrue($so->valeParaCategoria($a));
        $this->assertFalse($so->valeParaCategoria($b));
    }
}
