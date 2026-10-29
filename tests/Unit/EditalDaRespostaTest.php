<?php

namespace Tests\Unit;

use App\Models\Edital;
use App\Models\ImportacaoEdital;
use App\Support\EditalIa\EditalDaResposta;
use App\Support\EditalIa\RespostaInvalida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RespostaDaIa;
use Tests\TestCase;

class EditalDaRespostaTest extends TestCase
{
    use RefreshDatabase;
    use RespostaDaIa;

    private ImportacaoEdital $importacao;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->importacao = ImportacaoEdital::factory()->create();
        Storage::disk('local')->put($this->importacao->pdf, '%PDF-1.4');
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function criar(array $dados): Edital
    {
        return (new EditalDaResposta)->criar($dados, $this->importacao);
    }

    /**
     * Textos dos pontos de atenção acrescentados pelo sistema (tipo "incerta").
     *
     * @return list<string>
     */
    private function avisos(): array
    {
        return array_values(array_map(
            fn (array $ponto): string => $ponto['texto'],
            array_filter($this->importacao->atencao, fn (array $ponto): bool => $ponto['tipo'] === 'incerta'),
        ));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function raizesInvalidas(): array
    {
        return [
            'sem categorias' => [['categorias' => null]],
            'categorias como texto' => [['categorias' => 'Teatro']],
            'documentos como objeto' => [['documentos' => ['nome' => 'Portfólio']]],
            'identificação como texto' => [['identificacao' => 'Salvador Circula']],
            'sem confiabilidade' => [['confiabilidade' => null]],
        ];
    }

    #[DataProvider('raizesInvalidas')]
    public function test_bloco_da_raiz_ausente_ou_com_tipo_errado_e_resposta_invalida(array $alteracao): void
    {
        $dados = $this->respostaCompleta($alteracao);

        try {
            $this->criar($dados);
            $this->fail('A resposta deveria ser recusada.');
        } catch (RespostaInvalida $invalida) {
            $this->assertSame('A resposta da IA veio fora do formato esperado.', $invalida->getMessage());
        }

        $this->assertSame(0, Edital::withTrashed()->count());
        $this->assertSame([$this->importacao->pdf], Storage::disk('local')->allFiles());
    }

    public function test_itens_invalidos_sao_descartados_e_viram_pontos_de_atencao(): void
    {
        $dados = $this->respostaCompleta();
        $dados['inscricao']['chamadas'][] = ['nome' => 'Chamada 3', 'inicio' => '2026-02-30', 'fim' => '2026-03-10'];
        $dados['inscricao']['chamadas'][] = ['nome' => 'Chamada 4', 'inicio' => '2026-05-10', 'fim' => '2026-05-01'];
        $dados['categorias'][] = ['nome' => 'Circo', 'valor' => -100];
        $dados['categorias'][] = ['nome' => 'Música', 'naturezas' => ['pessoa_juridica']];
        $dados['categorias'][0]['itens'][] = ['nome' => 'Espaço X', 'vagas' => -1];
        $dados['documentos'][] = ['nome' => 'Certidão', 'etapa' => 'assinatura'];
        $dados['documentos'][] = ['nome' => 'Declaração', 'etapa' => 'inscricao', 'modalidade_cota' => 'idosos'];
        $dados['requisitos'][] = ['descricao' => '   '];
        $dados['avaliacao']['criterios'][] = ['nome' => 'Impacto', 'peso' => -2];

        $edital = $this->criar($dados);

        $this->assertSame(['Chamada 1', 'Chamada 2'], $edital->chamadas->pluck('nome')->all());
        $this->assertSame(['Teatro', 'Dança'], $edital->categorias->pluck('nome')->all());
        $this->assertSame(['Teatro Gregório de Mattos'], $edital->categorias->first()->itens->pluck('nome')->all());
        $this->assertSame(['Portfólio', 'Autodeclaração étnico-racial'], $edital->documentos->pluck('nome')->all());
        $this->assertCount(1, $edital->requisitos);
        $this->assertSame(['Mérito artístico'], $edital->criterios->pluck('nome')->all());
        $this->assertSame([
            'Item "Chamada 3" de "Chamadas" descartado: data de início impossível.',
            'Item "Chamada 4" de "Chamadas" descartado: fim antes do início.',
            'Item "Espaço X" da categoria "Teatro" descartado: quantidade negativa.',
            'Item "Circo" de "Categorias" descartado: valor negativo.',
            'Item "Música" de "Categorias" descartado: natureza desconhecida "pessoa_juridica".',
            'Item "Certidão" de "Documentos" descartado: etapa desconhecida.',
            'Item "Declaração" de "Documentos" descartado: modalidade de cota desconhecida.',
            'Item de "Requisitos" descartado: sem descrição.',
            'Item "Impacto" de "Critérios" descartado: pontuação negativa.',
        ], $this->avisos());
        $this->assertSame('categorias', $this->importacao->atencao[3]['bloco']);
    }

    public function test_campos_avulsos_invalidos_ficam_vazios_com_ponto_de_atencao(): void
    {
        $dados = $this->respostaCompleta();
        $dados['identificacao']['tipo'] = 'portaria';
        $dados['identificacao']['dom_data'] = '01/10/2026';
        $dados['participacao']['naturezas'] = ['pessoa_fisica', 'cooperativa'];
        $dados['cotas']['base'] = 'pessoas';
        $dados['avaliacao']['nota_corte'] = 1500;

        $edital = $this->criar($dados);

        $this->assertNull($edital->tipo);
        $this->assertNull($edital->dom_data);
        $this->assertSame(['pessoa_fisica'], $edital->naturezas->map->value->all());
        $this->assertNull($edital->cotas['base']);
        $this->assertNull($edital->nota_corte);
        $this->assertSame([
            'Valor desconhecido em "tipo do edital" descartado: "portaria".',
            'Data impossível em "data de publicação no DOM" descartada: "01/10/2026".',
            'Natureza desconhecida descartada: "cooperativa".',
            'Valor desconhecido em "base da reserva" descartado: "pessoas".',
            'Valor inválido em "nota de corte" descartado: "1500".',
        ], $this->avisos());
    }

    public function test_referencias_por_nome_sem_par_viram_todas_e_ponto_de_atencao(): void
    {
        $dados = $this->respostaCompleta();
        $dados['documentos'][0]['categorias'] = ['Literatura'];
        $dados['avaliacao']['criterios'][0]['categorias'] = ['Teatro', 'Artes visuais'];
        $dados['requisitos'][0]['documentos'] = ['Portfólio', 'Certidão negativa'];
        $dados['categorias'][1]['chamada'] = 'Chamada extra';

        $edital = $this->criar($dados);

        $documento = $edital->documentos->first();
        $this->assertTrue($documento->todas_categorias);
        $this->assertCount(0, $documento->categorias);
        $criterio = $edital->criterios->sole();
        $this->assertFalse($criterio->todas_categorias);
        $this->assertSame(['Teatro'], $criterio->categorias->pluck('nome')->all());
        $this->assertSame(['Portfólio'], $edital->requisitos->sole()->documentos->pluck('nome')->all());
        $this->assertNull($edital->categorias[1]->edital_chamada_id);
        $this->assertSame([
            'A categoria "Dança" cita a chamada "Chamada extra", que não está na lista de chamadas; ficou sem chamada.',
            'O documento "Portfólio" cita a categoria "Literatura", que não está na lista de categorias.',
            'O documento "Portfólio" ficou valendo para todas as categorias.',
            'O requisito "Domicílio em Salvador há pelo menos 2 anos" cita o documento "Certidão negativa", que não está na lista de documentos; ficou sem esse documento.',
            'O critério "Mérito artístico" cita a categoria "Artes visuais", que não está na lista de categorias.',
        ], $this->avisos());
    }

    public function test_nomes_longos_cortados_ainda_se_encontram_pelas_referencias(): void
    {
        $chamada = 'Chamada '.str_repeat('a', 150);
        $categoria = 'Categoria '.str_repeat('b', 300);
        $dados = $this->respostaCompleta();
        $dados['inscricao']['chamadas'][0]['nome'] = $chamada;
        $dados['categorias'][0]['nome'] = $categoria;
        $dados['categorias'][0]['chamada'] = $chamada;
        $dados['documentos'][0]['categorias'] = [$categoria];
        $dados['avaliacao']['criterios'][0]['categorias'] = [$categoria];

        $edital = $this->criar($dados);

        $this->assertSame((int) $edital->chamadas->first()->id, (int) $edital->categorias[0]->edital_chamada_id);
        $this->assertSame([$edital->categorias[0]->nome], $edital->documentos->first()->categorias->pluck('nome')->all());
        $this->assertSame([], $this->avisos());
    }

    public function test_textos_sao_cortados_no_limite_em_bytes(): void
    {
        $dados = $this->respostaCompleta();
        $dados['identificacao']['titulo'] = str_repeat('ç', 200);
        $dados['identificacao']['numero'] = str_repeat('Nº', 20);
        $dados['inscricao']['chamadas'][0]['nome'] = str_repeat('é', 80);

        $edital = $this->criar($dados);

        $this->assertSame(str_repeat('ç', 127), $edital->titulo);
        $this->assertSame(30, strlen($edital->numero));
        $this->assertTrue(mb_check_encoding($edital->numero, 'UTF-8'));
        $this->assertSame(str_repeat('é', 50), $edital->chamadas->first()->nome);
    }

    public function test_confiabilidade_geral_e_a_media_arredondada_das_notas_dos_blocos(): void
    {
        $dados = $this->respostaCompleta();
        $dados['confiabilidade'] = [
            'identificacao' => ['nota' => 90, 'justificativa' => 'Claro.'],
            'inscricao' => ['nota' => 71, 'justificativa' => 'Prazos divergentes.'],
            'cotas' => ['nota' => 150, 'justificativa' => 'Fora da escala.'],
            'avaliacao' => ['nota' => 'alta', 'justificativa' => 'Sem número.'],
        ];

        $this->criar($dados);

        // (90 + 71 + 100) / 3 = 87
        $this->assertSame(87, $this->importacao->confiabilidade['geral']);
        $this->assertSame(['identificacao', 'inscricao', 'cotas'], array_keys($this->importacao->confiabilidade['blocos']));
        $this->assertSame(100, $this->importacao->confiabilidade['blocos']['cotas']['nota']);
    }

    public function test_pontos_de_atencao_da_ia_sao_normalizados(): void
    {
        $this->criar($this->respostaCompleta(['atencao' => [
            ['bloco' => 'outro', 'tipo' => 'palpite', 'texto' => 'Valor da categoria ilegível.', 'itens' => ['item 3.2', 7]],
            ['bloco' => 'cotas', 'tipo' => 'nao_encontrado', 'texto' => '', 'itens' => []],
        ]]));

        $this->assertSame([
            ['bloco' => null, 'tipo' => 'incerta', 'texto' => 'Valor da categoria ilegível.', 'itens' => ['item 3.2', '7']],
        ], $this->importacao->atencao);
    }
}
