<?php

namespace Tests\Feature\EditalIa;

use App\Enums\ProvedorIa;
use App\Models\ConexaoIa;
use App\Support\EditalIa\AnaliseComRedundancia;
use App\Support\FalhaNaIa;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;
use Tests\Support\RespostaDaIa;
use Tests\TestCase;

class AnaliseComRedundanciaTest extends TestCase
{
    use RespostaDaIa;

    private const string URL_OPENAI = 'https://api.openai.com/v1/responses';

    private const string URL_GEMINI = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent';

    private const array ESQUEMA = ['type' => 'OBJECT', 'properties' => ['titulo' => ['type' => 'STRING']]];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * @return list<array{provedor: ProvedorIa, conexao: ConexaoIa}>
     */
    private static function openAiEGemini(): array
    {
        return [
            ['provedor' => ProvedorIa::OpenAi, 'conexao' => new ConexaoIa(['provedor' => ProvedorIa::OpenAi, 'modelo' => 'gpt-5.4', 'chave_api' => 'sk-teste-chave'])],
            ['provedor' => ProvedorIa::Gemini, 'conexao' => new ConexaoIa(['provedor' => ProvedorIa::Gemini, 'modelo' => 'gemini-2.5-pro', 'chave_api' => 'gemini-teste-chave'])],
        ];
    }

    /**
     * @param  list<array{provedor: ProvedorIa, conexao: ConexaoIa}>  $provedores
     * @return array<string, mixed>
     */
    private function analisar(array $provedores): array
    {
        return app(AnaliseComRedundancia::class)->analisar($provedores, '%PDF-1.4 conteúdo', 'Leia o edital.', 0.1, self::ESQUEMA);
    }

    /**
     * @param  list<array{provedor: ProvedorIa, conexao: ConexaoIa}>  $provedores
     */
    private function falhaAoAnalisar(array $provedores): FalhaNaIa
    {
        try {
            $this->analisar($provedores);
        } catch (FalhaNaIa $falha) {
            return $falha;
        }

        $this->fail('A análise deveria ter falhado.');
    }

    /**
     * @param  array<mixed>  $dados
     */
    private static function respostaDoGemini(array $dados): PromiseInterface
    {
        return Http::response([
            'candidates' => [['content' => ['parts' => [['text' => json_encode($dados)]]]]],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'totalTokenCount' => 15],
        ]);
    }

    /**
     * @param  array<mixed>  $dados
     */
    private static function respostaDaOpenAi(array $dados): PromiseInterface
    {
        return Http::response([
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($dados)]]]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5, 'total_tokens' => 15],
        ]);
    }

    public function test_principal_que_responde_dispensa_a_redundancia(): void
    {
        Http::fake([self::URL_OPENAI => self::respostaDaOpenAi($this->respostaCompleta())]);

        $resultado = $this->analisar(self::openAiEGemini());

        $this->assertSame(ProvedorIa::OpenAi, $resultado['provedor']);
        $this->assertSame('gpt-5.4', $resultado['modelo']);
        $this->assertSame([], $resultado['falhas']);
        Http::assertSentCount(1);
    }

    public function test_principal_que_falha_passa_para_a_redundancia_e_registra_a_falha(): void
    {
        Http::fake([
            self::URL_OPENAI => Http::response(['error' => ['message' => 'Rate limit reached.']], 429),
            self::URL_GEMINI => self::respostaDoGemini($this->respostaCompleta()),
        ]);

        $resultado = $this->analisar(self::openAiEGemini());

        $this->assertSame([
            'dados' => $this->respostaCompleta(),
            'tokens' => ['entrada' => 10, 'saida' => 5, 'total' => 15],
            'provedor' => ProvedorIa::Gemini,
            'modelo' => 'gemini-2.5-pro',
            'falhas' => [['provedor' => ProvedorIa::OpenAi, 'motivo' => 'OpenAI: limite de uso ou cota da conta esgotados. Confira o plano e o saldo da conta.']],
        ], $resultado);
        Http::assertSentCount(2);
    }

    public function test_principal_com_json_sem_os_blocos_da_raiz_passa_para_a_redundancia(): void
    {
        Http::fake([
            self::URL_OPENAI => self::respostaDaOpenAi(['titulo' => 'Salvador Circula']),
            self::URL_GEMINI => self::respostaDoGemini($this->respostaCompleta()),
        ]);

        $resultado = $this->analisar(self::openAiEGemini());

        $this->assertSame(ProvedorIa::Gemini, $resultado['provedor']);
        $this->assertSame($this->respostaCompleta(), $resultado['dados']);
        $this->assertSame([['provedor' => ProvedorIa::OpenAi, 'motivo' => 'OpenAI respondeu fora do formato esperado.']], $resultado['falhas']);
    }

    public function test_os_dois_falhando_a_mensagem_traz_o_motivo_de_cada_um(): void
    {
        Http::fake([
            self::URL_OPENAI => Http::response(['error' => ['message' => 'Incorrect API key provided.']], 401),
            self::URL_GEMINI => Http::response('erro', 503),
        ]);

        $this->assertSame(
            'Serviço de IA indisponível no momento. Caso persista, contate o administrador. Detalhe: OpenAI: OpenAI recusou a chave da API. Confira se ela foi copiada inteira e se ainda está ativa. '
            .'Google Gemini: Google Gemini está com instabilidade. Tente de novo em alguns minutos.',
            $this->falhaAoAnalisar(self::openAiEGemini())->getMessage(),
        );
    }

    public function test_so_o_principal_falhando_a_mensagem_e_a_dele(): void
    {
        Http::fake([self::URL_GEMINI => Http::response('erro', 503)]);

        $this->assertSame(
            'Serviço de IA indisponível no momento. Caso persista, contate o administrador. Detalhe: Google Gemini está com instabilidade. Tente de novo em alguns minutos.',
            $this->falhaAoAnalisar([self::openAiEGemini()[1]])->getMessage(),
        );
    }

    public function test_sem_provedores_pede_a_configuracao(): void
    {
        Http::fake();

        $this->assertSame(
            'Serviço de IA indisponível no momento. Caso persista, contate o administrador.',
            $this->falhaAoAnalisar([])->getMessage(),
        );
        Http::assertNothingSent();
    }
}
