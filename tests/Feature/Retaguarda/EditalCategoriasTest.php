<?php

namespace Tests\Feature\Retaguarda;

use App\Enums\NaturezaProponente;
use App\Enums\UnidadeCota;
use App\Models\Edital;
use App\Models\EditalCategoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EditalCategoriasTest extends TestCase
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
            'nome' => 'Categoria 1 – Curta-Metragem',
            'valor' => '50000.50',
            'vagas' => '10',
            'naturezas' => ['pessoa_fisica', 'mei'],
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
            ...$alteracoes,
        ];
    }

    public function test_vagas_e_cota_negativas_sao_recusadas(): void
    {
        $this->post('/retaguarda/editais', $this->dados([
            'categorias' => [
                $this->categoria(['vagas' => '-5']),
                $this->categoria(['cotas' => ['negros' => ['valor' => '-2', 'unidade' => 'vagas']]]),
            ],
        ]))->assertSessionHasErrors(['categorias.0.vagas', 'categorias.1.cotas.negros.valor']);

        $this->assertSame(0, Edital::count());
    }

    public function test_cria_edital_com_categorias_em_ordem(): void
    {
        $resposta = $this->post('/retaguarda/editais', $this->dados([
            'categorias' => [
                $this->categoria([
                    'cotas' => [
                        'negros' => ['valor' => '2', 'unidade' => 'vagas'],
                        'indigenas' => ['valor' => '', 'unidade' => ''],
                        'pcd' => ['valor' => '10', 'unidade' => 'percentual'],
                    ],
                ]),
                $this->categoria(['nome' => 'Categoria 2', 'valor' => '1000', 'vagas' => '5', 'naturezas' => ['sem_fins']]),
            ],
        ]));

        $resposta->assertSessionHasNoErrors()->assertRedirect(route('retaguarda.editais.index'));

        $categorias = Edital::firstOrFail()->categorias;
        $this->assertCount(2, $categorias);
        $this->assertSame([1, 2], $categorias->pluck('ordem')->map(fn ($ordem) => (int) $ordem)->all());

        $primeira = $categorias[0];
        $this->assertSame('Categoria 1 – Curta-Metragem', $primeira->nome);
        $this->assertSame(5000050, $primeira->valor_centavos);
        $this->assertSame(10, $primeira->vagas);
        $this->assertEquals([NaturezaProponente::PessoaFisica, NaturezaProponente::Mei], $primeira->naturezas->all());
        $this->assertSame(2, (int) $primeira->cota_negros_valor);
        $this->assertSame(UnidadeCota::Vagas, $primeira->cota_negros_unidade);
        $this->assertNull($primeira->cota_indigenas_valor);
        $this->assertNull($primeira->cota_indigenas_unidade);
        $this->assertSame(UnidadeCota::Percentual, $primeira->cota_pcd_unidade);

        $this->assertSame(100000, $categorias[1]->valor_centavos);
        $this->assertSame('Categoria 2', $categorias[1]->nome);
    }

    public function test_percentual_arredonda_vagas_reservadas_para_cima(): void
    {
        $categoria = EditalCategoria::factory()->make([
            'vagas' => 5,
            'cota_negros_valor' => 30,
            'cota_negros_unidade' => UnidadeCota::Percentual,
            'cota_pcd_valor' => 1,
            'cota_pcd_unidade' => UnidadeCota::Vagas,
        ]);

        $this->assertSame(2, $categoria->vagasReservadas('negros'));
        $this->assertSame(1, $categoria->vagasReservadas('pcd'));
        $this->assertSame(0, $categoria->vagasReservadas('indigenas'));
        $this->assertSame(3, $categoria->totalReservado());
    }

    public function test_edicao_atualiza_remove_e_adiciona_categorias(): void
    {
        $edital = Edital::factory()->create();
        $mantida = EditalCategoria::factory()->for($edital)->create(['ordem' => 1, 'nome' => 'Mantida']);
        $removida = EditalCategoria::factory()->for($edital)->create(['ordem' => 2, 'nome' => 'Removida']);

        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados([
            'categorias' => [
                $this->categoria(['nome' => 'Nova', 'naturezas' => ['com_fins']]),
                $this->categoria(['id' => (string) $mantida->id, 'nome' => 'Mantida editada', 'vagas' => '20']),
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('edital_categorias', ['id' => $removida->id]);

        $categorias = $edital->refresh()->categorias;
        $this->assertCount(2, $categorias);
        $this->assertSame('Nova', $categorias[0]->nome);
        $this->assertSame(1, (int) $categorias[0]->ordem);
        $this->assertSame((int) $mantida->id, (int) $categorias[1]->id);
        $this->assertSame('Mantida editada', $categorias[1]->nome);
        $this->assertSame(20, $categorias[1]->vagas);
        $this->assertSame(2, (int) $categorias[1]->ordem);
    }

    public function test_id_de_categoria_de_outro_edital_e_ignorado(): void
    {
        $outroEdital = Edital::factory()->create();
        $alheia = EditalCategoria::factory()->for($outroEdital)->create(['nome' => 'Alheia']);
        $edital = Edital::factory()->create();

        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados([
            'categorias' => [$this->categoria(['id' => (string) $alheia->id, 'nome' => 'Invasora'])],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Alheia', $alheia->refresh()->nome);
        $this->assertSame((int) $outroEdital->id, (int) $alheia->edital_id);
        $this->assertCount(1, $outroEdital->categorias);
        $this->assertSame(['Invasora'], $edital->categorias()->pluck('nome')->all());
    }

    public function test_sem_categorias_apaga_as_existentes_e_continua_salvando_e_publicando(): void
    {
        $edital = Edital::factory()->create(['publicado' => false]);
        EditalCategoria::factory()->for($edital)->create();

        $this->put("/retaguarda/editais/{$edital->slug}", $this->dados(['publicado' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $edital->categorias()->count());
        $this->assertTrue($edital->refresh()->publicado);
    }

    public function test_cria_edital_sem_categorias(): void
    {
        $this->post('/retaguarda/editais', $this->dados())->assertSessionHasNoErrors();

        $this->assertSame(0, Edital::firstOrFail()->categorias()->count());
    }

    public function test_natureza_e_obrigatoria_mesmo_ausente(): void
    {
        $categoria = $this->categoria();
        unset($categoria['naturezas']);

        $this->post('/retaguarda/editais', $this->dados(['categorias' => [$categoria]]))
            ->assertSessionHasErrors(['categorias.0.naturezas' => 'Marque ao menos uma natureza.']);

        $this->post('/retaguarda/editais', $this->dados(['categorias' => [$this->categoria(['naturezas' => []])]]))
            ->assertSessionHasErrors(['categorias.0.naturezas' => 'Marque ao menos uma natureza.']);

        $this->assertSame(0, Edital::count());
    }

    public function test_vagas_zero_e_recusado(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['categorias' => [$this->categoria(['vagas' => '0'])]]))
            ->assertSessionHasErrors('categorias.0.vagas');
    }

    public function test_percentual_acima_de_cem_e_recusado(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['categorias' => [
            $this->categoria(['cotas' => ['pcd' => ['valor' => '101', 'unidade' => 'percentual']]]),
        ]]))->assertSessionHasErrors('categorias.0.cotas.pcd.valor');
    }

    public function test_unidade_e_obrigatoria_quando_ha_valor_da_cota(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['categorias' => [
            $this->categoria(['cotas' => ['pcd' => ['valor' => '1', 'unidade' => '']]]),
        ]]))->assertSessionHasErrors('categorias.0.cotas.pcd.unidade');
    }

    public function test_cotas_nao_podem_somar_mais_que_as_vagas(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['categorias' => [
            $this->categoria([
                'vagas' => '5',
                'cotas' => [
                    'negros' => ['valor' => '30', 'unidade' => 'percentual'],
                    'indigenas' => ['valor' => '2', 'unidade' => 'vagas'],
                    'pcd' => ['valor' => '2', 'unidade' => 'vagas'],
                ],
            ]),
        ]]))->assertSessionHasErrors(['categorias.0.cotas' => 'As cotas somam mais vagas do que a categoria tem.']);

        $this->assertSame(0, Edital::count());
    }

    public function test_cotas_que_cabem_nas_vagas_sao_aceitas(): void
    {
        $this->post('/retaguarda/editais', $this->dados(['categorias' => [
            $this->categoria([
                'vagas' => '5',
                'cotas' => [
                    'negros' => ['valor' => '30', 'unidade' => 'percentual'],
                    'indigenas' => ['valor' => '2', 'unidade' => 'vagas'],
                    'pcd' => ['valor' => '1', 'unidade' => 'vagas'],
                ],
            ]),
        ]]))->assertSessionHasNoErrors();
    }

    public function test_edicao_envia_as_categorias_e_as_naturezas(): void
    {
        $edital = Edital::factory()->create();
        $categoria = EditalCategoria::factory()->for($edital)->create([
            'nome' => 'Curta',
            'valor_centavos' => 5000050,
            'vagas' => 10,
            'naturezas' => [NaturezaProponente::PessoaFisica, NaturezaProponente::Mei],
            'cota_negros_valor' => 20,
            'cota_negros_unidade' => UnidadeCota::Percentual,
        ]);

        $this->get("/retaguarda/editais/{$edital->slug}/edit")->assertInertia(fn (Assert $page) => $page
            ->has('naturezas', 4)
            ->where('naturezas.0', ['valor' => 'pessoa_fisica', 'rotulo' => 'Pessoa Física'])
            ->has('edital.categorias', 1)
            ->where('edital.categorias.0.id', (int) $categoria->id)
            ->where('edital.categorias.0.nome', 'Curta')
            ->where('edital.categorias.0.valor', 50000.5)
            ->where('edital.categorias.0.vagas', 10)
            ->where('edital.categorias.0.naturezas', ['pessoa_fisica', 'mei'])
            ->where('edital.categorias.0.cotas.negros', ['valor' => 20, 'unidade' => 'percentual'])
            ->where('edital.categorias.0.cotas.indigenas', ['valor' => null, 'unidade' => null])
            ->where('edital.categorias.0.cotas.pcd', ['valor' => null, 'unidade' => null]));

        $this->get('/retaguarda/editais/create')->assertInertia(fn (Assert $page) => $page->has('naturezas', 4));
    }
}
