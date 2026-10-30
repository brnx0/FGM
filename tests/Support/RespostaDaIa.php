<?php

namespace Tests\Support;

use App\Support\EditalIa\EsquemaDaResposta;

/** Resposta da IA no formato de EsquemaDaResposta, com todos os blocos preenchidos. */
trait RespostaDaIa
{
    /**
     * @param  array<string, mixed>  $alteracoes  Blocos da raiz a trocar.
     * @return array<string, mixed>
     */
    protected function respostaCompleta(array $alteracoes = []): array
    {
        return [
            'identificacao' => [
                'numero' => '007/2026',
                'tipo' => 'chamamento',
                'titulo' => 'Salvador Circula',
                'objeto' => 'Seleção de espetáculos para circulação.',
                'resumo' => 'Circulação de espetáculos pelos bairros.',
                'categoria' => 'Artes cênicas',
                'dom_data' => '2026-10-01',
                'dom_numero' => '1234',
            ],
            'inscricao' => [
                'chamadas' => [
                    [
                        'nome' => 'Chamada 1', 'inicio' => '2026-11-01', 'fim' => '2026-11-30',
                        'validacao_inicio' => '2026-12-05', 'validacao_fim' => '2026-12-12',
                        'homologacao_inicio' => '2026-12-20', 'homologacao_fim' => '2026-12-22',
                    ],
                    ['nome' => 'Chamada 2', 'inicio' => '2027-01-10', 'fim' => '2027-02-10'],
                ],
                'forma_inscricao_original' => 'Formulário on-line',
            ],
            'participacao' => [
                'naturezas' => ['pessoa_fisica', 'mei'],
                'aceita_coletivos' => true,
                'inscricoes_por_proponente' => 2,
                'limite_por_chamada' => false,
            ],
            'categorias' => [
                [
                    'nome' => 'Teatro', 'descricao' => 'Espetáculos de teatro.', 'valor' => 15000.5, 'vagas' => 10,
                    'naturezas' => null, 'chamada' => 'Chamada 1',
                    'itens' => [['nome' => 'Teatro Gregório de Mattos', 'valor' => 5000, 'vagas' => 2, 'suplentes' => 1]],
                ],
                ['nome' => 'Dança', 'descricao' => null, 'valor' => null, 'vagas' => null, 'naturezas' => ['pessoa_fisica'], 'chamada' => 'chamada 2', 'itens' => []],
            ],
            'cotas' => [
                'negros' => ['ativa' => true, 'percentual' => 25, 'quantidade' => null],
                'indigenas' => ['ativa' => true, 'percentual' => 10, 'quantidade' => null],
                'pcd' => ['ativa' => false, 'percentual' => null, 'quantidade' => null],
                'base' => 'vagas',
                'alcance' => 'edital',
                'veda_negro_indigena' => true,
                'heteroidentificacao' => true,
            ],
            'documentos' => [
                [
                    'nome' => 'Portfólio', 'orientacao' => 'Até 10 páginas.', 'etapa' => 'inscricao', 'obrigatorio' => true,
                    'naturezas' => null, 'categorias' => ['dança'], 'modalidade_cota' => null,
                ],
                [
                    'nome' => 'Autodeclaração étnico-racial', 'orientacao' => null, 'etapa' => 'validacao_cotas', 'obrigatorio' => true,
                    'naturezas' => ['pessoa_fisica'], 'categorias' => [], 'modalidade_cota' => 'negros',
                ],
            ],
            'requisitos' => [
                ['descricao' => 'Domicílio em Salvador há pelo menos 2 anos', 'documentos' => ['Portfólio']],
            ],
            'avaliacao' => [
                'nota_maxima' => 100,
                'nota_corte' => 60.5,
                'criterios' => [
                    ['nome' => 'Mérito artístico', 'descricao' => null, 'peso' => 2, 'pontos_maximos' => 40, 'categorias' => ['Teatro']],
                ],
                'pontuacao_extra' => [
                    'grupos' => [['grupo' => 'Mulheres', 'pontos_por_pessoa' => 1, 'maximo' => 5]],
                    'teto' => 10,
                    'conta_para_corte' => false,
                ],
            ],
            'informacoes' => [
                'vedacoes' => 'É vedada a participação de servidores da FGM.',
                'contatos' => null,
            ],
            'confiabilidade' => array_map(
                fn (): array => ['nota' => 80, 'justificativa' => 'Texto claro.'],
                EsquemaDaResposta::BLOCOS,
            ),
            'atencao' => [
                ['bloco' => 'inscricao', 'tipo' => 'divergencia', 'texto' => 'O item 5.1 diz 01 a 30/11 e o 11.1 dá outro prazo.', 'itens' => ['item 5.1', 'item 11.1']],
            ],
            ...$alteracoes,
        ];
    }
}
