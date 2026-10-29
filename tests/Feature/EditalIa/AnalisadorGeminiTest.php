<?php

namespace Tests\Feature\EditalIa;

use App\Support\EditalIa\AnalisadorGemini;
use App\Support\FalhaNaIa;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalisadorGeminiTest extends TestCase
{
    private const string CHAVE = 'AIzaChaveFalsaDeTeste-0123456789-x4f2';

    private const string URL_ANALISE = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent';

    private const array ESQUEMA = ['type' => 'OBJECT', 'properties' => ['titulo' => ['type' => 'STRING']]];

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
        return app(AnalisadorGemini::class)->analisar('gemini-2.5-pro', self::CHAVE, $pdfBinario, 'Leia o edital.', 0.1, self::ESQUEMA);
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
            'candidates' => [['content' => ['parts' => [['text' => $texto]]]]],
            'usageMetadata' => ['promptTokenCount' => 1200, 'candidatesTokenCount' => 300, 'totalTokenCount' => 1500],
        ];
    }

    public function test_analisar_envia_o_pdf_e_o_esquema_e_devolve_dados_e_tokens(): void
    {
        Http::fake([self::URL_ANALISE => Http::response(self::corpo('{"titulo": "Edital 007/2026"}'))]);

        $resultado = $this->analisar();

        $this->assertSame(['dados' => ['titulo' => 'Edital 007/2026'], 'tokens' => ['entrada' => 1200, 'saida' => 300, 'total' => 1500]], $resultado);
        Http::assertSent(fn (Request $pedido): bool => $pedido->method() === 'POST'
            && $pedido->url() === self::URL_ANALISE
            && $pedido->header('x-goog-api-key') === [self::CHAVE]
            && $pedido['contents'][0]['parts'] === [
                ['inline_data' => ['mime_type' => 'application/pdf', 'data' => base64_encode('%PDF-1.4 conteúdo')]],
                ['text' => 'Leia o edital.'],
            ]
            && $pedido['generationConfig'] === ['temperature' => 0.1, 'responseMimeType' => 'application/json', 'responseSchema' => self::ESQUEMA]);
    }

    public function test_sem_pdf_o_pedido_do_gemini_so_leva_o_texto(): void
    {
        Http::fake([self::URL_ANALISE => Http::response(self::corpo('{"titulo": "Edital 007/2026"}'))]);

        $resultado = $this->analisar(null);

        $this->assertSame(['titulo' => 'Edital 007/2026'], $resultado['dados']);
        Http::assertSent(fn (Request $pedido): bool => $pedido['contents'] === [['parts' => [['text' => 'Leia o edital.']]]]);
    }

    public function test_temperatura_recusada_repete_uma_vez_sem_ela(): void
    {
        Http::fake([self::URL_ANALISE => Http::sequence()
            ->push(['error' => ['code' => 400, 'message' => 'temperature is not supported for this model.', 'status' => 'INVALID_ARGUMENT']], 400)
            ->push(self::corpo('{"titulo": "Edital 007/2026"}'))]);

        $this->assertSame(['titulo' => 'Edital 007/2026'], $this->analisar()['dados']);
        Http::assertSentCount(2);
        $pedidos = Http::recorded()->map(fn (array $par): array => $par[0]['generationConfig']);
        $this->assertSame(0.1, $pedidos[0]['temperature']);
        $this->assertArrayNotHasKey('temperature', $pedidos[1]);
    }

    public function test_resposta_sem_json_valido_falha_com_mensagem_de_formato(): void
    {
        Http::fake([self::URL_ANALISE => Http::response(self::corpo('Não consegui ler o edital.'))]);

        $this->assertSame('Google Gemini respondeu fora do formato esperado.', $this->falhaAoAnalisar()->getMessage());
    }

    /**
     * @return array<string, array{int, array<string, mixed>, string}>
     */
    public static function respostasDeErro(): array
    {
        return [
            '400 de chave inválida' => [400, ['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'API key not valid.', 'details' => [['reason' => 'API_KEY_INVALID']]]], 'Google Gemini recusou a chave da API. Confira se ela foi copiada inteira e se ainda está ativa.'],
            '400 de outro motivo, que repete a chave' => [400, ['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'Invalid schema for '.self::CHAVE.'.']], 'Google Gemini recusou o pedido (erro 400): Invalid schema for [chave].'],
            '400 de pedido grande demais' => [400, ['error' => ['status' => 'INVALID_ARGUMENT', 'message' => 'Request payload size exceeds the limit: 20971520 bytes.']], 'Google Gemini: o PDF é grande demais para ser analisado. Envie um arquivo menor.'],
            '413' => [413, [], 'Google Gemini: o PDF é grande demais para ser analisado. Envie um arquivo menor.'],
            '403' => [403, ['error' => ['status' => 'PERMISSION_DENIED']], 'Google Gemini recusou o acesso desta chave. Confira as permissões dela e o projeto no painel do provedor.'],
            '404' => [404, ['error' => ['status' => 'NOT_FOUND']], 'O modelo gemini-2.5-pro não existe ou não está liberado para esta chave.'],
            '429' => [429, ['error' => ['status' => 'RESOURCE_EXHAUSTED']], 'Google Gemini: limite de uso ou cota da conta esgotados. Confira o plano e o saldo da conta.'],
            '503' => [503, ['error' => ['status' => 'UNAVAILABLE']], 'Google Gemini está com instabilidade. Tente de novo em alguns minutos.'],
            '409' => [409, [], 'Google Gemini recusou o pedido (erro 409).'],
        ];
    }

    /**
     * @param  array<string, mixed>  $corpo
     */
    #[DataProvider('respostasDeErro')]
    public function test_erro_do_provedor_vira_mensagem_em_portugues_sem_a_chave(int $status, array $corpo, string $mensagem): void
    {
        Http::fake([self::URL_ANALISE => Http::response($corpo, $status)]);
        Log::spy();

        $this->assertSame($mensagem, $this->falhaAoAnalisar()->getMessage());
        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $texto, array $contexto): bool => $contexto === ['provedor' => 'gemini', 'status' => $status]);
    }

    public function test_tempo_esgotado_vira_mensagem_de_interrupcao(): void
    {
        Http::fake([self::URL_ANALISE => Http::failedConnection('cURL error 28: Operation timed out after 300000 milliseconds')]);

        $this->assertSame('Google Gemini demorou demais para responder e a chamada foi interrompida. Tente de novo.', $this->falhaAoAnalisar()->getMessage());
    }

    public function test_sem_conexao_vira_mensagem_de_conexao(): void
    {
        Http::fake([self::URL_ANALISE => Http::failedConnection()]);

        $this->assertSame('Google Gemini não respondeu a tempo. Confira a conexão do servidor com a internet e tente de novo.', $this->falhaAoAnalisar()->getMessage());
    }
}
