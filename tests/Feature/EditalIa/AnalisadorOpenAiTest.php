<?php

namespace Tests\Feature\EditalIa;

use App\Support\EditalIa\AnalisadorOpenAi;
use App\Support\EditalIa\EsquemaDaResposta;
use App\Support\EditalIa\EsquemaOpenAi;
use App\Support\FalhaNaIa;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalisadorOpenAiTest extends TestCase
{
    private const string CHAVE = 'sk-teste-ChaveFalsaDeTeste-0123456789-x4f2';

    private const string URL_ANALISE = 'https://api.openai.com/v1/responses';

    private const array ESQUEMA = ['type' => 'OBJECT', 'properties' => ['titulo' => ['type' => 'STRING', 'nullable' => true]]];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @return array{dados: array<mixed>, tokens: array{entrada: int, saida: int, total: int}}
     */
    private function analisar(?string $pdfBinario = '%PDF-1.4 conteúdo'): array
    {
        return app(AnalisadorOpenAi::class)->analisar('gpt-5.4', self::CHAVE, $pdfBinario, 'Leia o edital.', 0.1, self::ESQUEMA);
    }

    private function falhaAoAnalisar(): FalhaNaIa
    {
        try {
            $this->analisar();
        } catch (FalhaNaIa $falha) {
            return $falha;
        }

        $this->fail('A análise deveria ter falhado.');
    }

    /**
     * @return array<string, mixed>
     */
    private static function corpo(string $texto): array
    {
        return [
            'output' => [
                ['type' => 'reasoning', 'summary' => []],
                ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => $texto]]],
            ],
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 300, 'total_tokens' => 1500],
        ];
    }

    public function test_analisar_envia_o_pdf_e_o_esquema_strict_e_devolve_dados_e_tokens(): void
    {
        Http::fake([self::URL_ANALISE => Http::response(self::corpo('{"titulo": "Edital 007/2026"}'))]);

        $resultado = $this->analisar();

        $this->assertSame(['dados' => ['titulo' => 'Edital 007/2026'], 'tokens' => ['entrada' => 1200, 'saida' => 300, 'total' => 1500]], $resultado);
        Http::assertSent(fn (Request $pedido): bool => $pedido->method() === 'POST'
            && $pedido->url() === self::URL_ANALISE
            && $pedido->header('Authorization') === ['Bearer '.self::CHAVE]
            && ! str_contains($pedido->url(), self::CHAVE)
            && $pedido['model'] === 'gpt-5.4'
            && $pedido['store'] === false
            && $pedido['temperature'] === 0.1
            && $pedido['input'] === [[
                'role' => 'user',
                'content' => [
                    ['type' => 'input_file', 'filename' => 'arquivo.pdf', 'file_data' => 'data:application/pdf;base64,'.base64_encode('%PDF-1.4 conteúdo')],
                    ['type' => 'input_text', 'text' => 'Leia o edital.'],
                ],
            ]]
            && $pedido['text'] === ['format' => ['type' => 'json_schema', 'name' => 'edital', 'strict' => true, 'schema' => EsquemaOpenAi::de(self::ESQUEMA)]]);
    }

    public function test_sem_pdf_o_pedido_da_openai_so_leva_o_texto(): void
    {
        Http::fake([self::URL_ANALISE => Http::response(self::corpo('{"titulo": "Edital 007/2026"}'))]);

        $resultado = $this->analisar(null);

        $this->assertSame(['titulo' => 'Edital 007/2026'], $resultado['dados']);
        Http::assertSent(fn (Request $pedido): bool => $pedido['input'] === [[
            'role' => 'user',
            'content' => [['type' => 'input_text', 'text' => 'Leia o edital.']],
        ]]);
    }

    public function test_temperatura_recusada_repete_uma_vez_sem_ela(): void
    {
        Http::fake([self::URL_ANALISE => Http::sequence()
            ->push(['error' => ['message' => "Unsupported parameter: 'temperature' is not supported with this model.", 'type' => 'invalid_request_error', 'param' => 'temperature', 'code' => 'unsupported_parameter']], 400)
            ->push(self::corpo('{"titulo": "Edital 007/2026"}'))]);

        $this->assertSame(['titulo' => 'Edital 007/2026'], $this->analisar()['dados']);
        Http::assertSentCount(2);
        $pedidos = Http::recorded()->map(fn (array $par): array => $par[0]->data());
        $this->assertSame(0.1, $pedidos[0]['temperature']);
        $this->assertArrayNotHasKey('temperature', $pedidos[1]);
    }

    public function test_temperatura_recusada_de_novo_nao_repete_uma_terceira_vez(): void
    {
        Http::fake([self::URL_ANALISE => Http::response(['error' => ['message' => 'Unsupported value.', 'param' => 'temperature']], 400)]);

        $this->assertSame('OpenAI recusou o pedido (erro 400): Unsupported value.', $this->falhaAoAnalisar()->getMessage());
        Http::assertSentCount(2);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function respostasForaDoFormato(): array
    {
        return [
            'texto que não é JSON' => [self::corpo('Não consegui ler o edital.')],
            'sem mensagem do modelo' => [['output' => [['type' => 'reasoning', 'summary' => []]], 'usage' => []]],
            'recusa do modelo' => [['output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'Não posso ajudar.']]]]]],
        ];
    }

    /**
     * @param  array<string, mixed>  $corpo
     */
    #[DataProvider('respostasForaDoFormato')]
    public function test_resposta_fora_do_formato_falha_com_mensagem_de_formato(array $corpo): void
    {
        Http::fake([self::URL_ANALISE => Http::response($corpo)]);

        $this->assertSame('OpenAI respondeu fora do formato esperado.', $this->falhaAoAnalisar()->getMessage());
    }

    /**
     * @return array<string, array{int, array<string, mixed>, string}>
     */
    public static function respostasDeErro(): array
    {
        return [
            '401' => [401, ['error' => ['message' => 'Incorrect API key provided: '.self::CHAVE.'.', 'type' => 'invalid_request_error', 'code' => 'invalid_api_key']], 'OpenAI recusou a chave da API. Confira se ela foi copiada inteira e se ainda está ativa.'],
            '400 que repete a chave' => [400, ['error' => ['message' => 'Bad request for '.self::CHAVE.'.', 'param' => 'input']], 'OpenAI recusou o pedido (erro 400): Bad request for [chave].'],
            '429' => [429, ['error' => ['message' => 'Rate limit reached.', 'type' => 'requests']], 'OpenAI: limite de uso ou cota da conta esgotados. Confira o plano e o saldo da conta.'],
            '500' => [500, ['error' => ['message' => 'The server had an error.']], 'OpenAI está com instabilidade. Tente de novo em alguns minutos.'],
            '409' => [409, [], 'OpenAI recusou o pedido (erro 409).'],
        ];
    }

    /**
     * @param  array<string, mixed>  $corpo
     */
    #[DataProvider('respostasDeErro')]
    public function test_erro_do_provedor_vira_mensagem_em_portugues_sem_a_chave_nem_no_log(int $status, array $corpo, string $mensagem): void
    {
        Http::fake([self::URL_ANALISE => Http::response($corpo, $status)]);
        Log::spy();

        $this->assertSame($mensagem, $this->falhaAoAnalisar()->getMessage());
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $texto, array $contexto): bool => $contexto === ['provedor' => 'openai', 'status' => $status]
            && ! str_contains($texto, self::CHAVE));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function detalhesComAChave(): array
    {
        return [
            'chave no começo' => [self::CHAVE.' is not allowed for this request.'],
            'chave cruzando o corte de 200 caracteres' => [str_repeat('x', 185).' '.self::CHAVE.' is not allowed.'],
        ];
    }

    #[DataProvider('detalhesComAChave')]
    public function test_nenhum_pedaco_da_chave_sobra_no_motivo_cortado(string $detalhe): void
    {
        Http::fake([self::URL_ANALISE => Http::response(['error' => ['message' => $detalhe]], 400)]);

        $mensagem = $this->falhaAoAnalisar()->getMessage();

        $this->assertStringStartsWith('OpenAI recusou o pedido (erro 400): ', $mensagem);
        for ($inicio = 0; $inicio <= strlen(self::CHAVE) - 8; $inicio++) {
            $this->assertStringNotContainsString(substr(self::CHAVE, $inicio, 8), $mensagem);
        }
    }

    public function test_tempo_esgotado_vira_mensagem_de_interrupcao(): void
    {
        Http::fake([self::URL_ANALISE => Http::failedConnection('cURL error 28: Operation timed out after 300000 milliseconds')]);

        $this->assertSame('OpenAI demorou demais para responder e a chamada foi interrompida. Tente de novo.', $this->falhaAoAnalisar()->getMessage());
    }

    public function test_esquema_vira_json_schema_strict(): void
    {
        $esquema = EsquemaOpenAi::de([
            'type' => 'OBJECT',
            'properties' => [
                'titulo' => ['type' => 'STRING', 'nullable' => true, 'description' => 'Título.'],
                'tipo' => ['type' => 'STRING', 'enum' => ['edital', 'chamamento'], 'nullable' => true],
                'bloco' => ['type' => 'STRING', 'enum' => ['identificacao']],
                'chamadas' => ['type' => 'ARRAY', 'items' => [
                    'type' => 'OBJECT',
                    'properties' => ['nome' => ['type' => 'STRING'], 'vagas' => ['type' => 'INTEGER', 'nullable' => true]],
                    'required' => ['nome'],
                ]],
            ],
            'required' => ['chamadas'],
        ]);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'titulo' => ['type' => ['string', 'null'], 'description' => 'Título.'],
                'tipo' => ['type' => ['string', 'null'], 'enum' => ['edital', 'chamamento', null]],
                'bloco' => ['type' => 'string', 'enum' => ['identificacao']],
                'chamadas' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'properties' => ['nome' => ['type' => 'string'], 'vagas' => ['type' => ['integer', 'null']]],
                    'required' => ['nome', 'vagas'],
                    'additionalProperties' => false,
                ]],
            ],
            'required' => ['titulo', 'tipo', 'bloco', 'chamadas'],
            'additionalProperties' => false,
        ], $esquema);
    }

    public function test_esquema_da_importacao_traduzido_tem_todo_objeto_fechado_e_com_todas_as_chaves_obrigatorias(): void
    {
        $objetos = 0;
        $conferir = function (array $esquema) use (&$conferir, &$objetos): void {
            $this->assertArrayNotHasKey('nullable', $esquema);
            if ($esquema['type'] === 'object') {
                $objetos++;
                $this->assertFalse($esquema['additionalProperties']);
                $this->assertSame(array_keys($esquema['properties']), $esquema['required']);
            }
            foreach ([...($esquema['properties'] ?? []), ...(isset($esquema['items']) ? [$esquema['items']] : [])] as $filho) {
                $conferir($filho);
            }
        };

        $conferir(EsquemaOpenAi::de(EsquemaDaResposta::esquema()));

        $this->assertGreaterThan(20, $objetos);
    }
}
