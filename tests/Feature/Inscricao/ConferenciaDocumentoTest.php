<?php

namespace Tests\Feature\Inscricao;

use App\Enums\ChecagemDocumento;
use App\Enums\NaturezaProponente;
use App\Enums\ProvedorIa;
use App\Enums\SituacaoConferencia;
use App\Enums\SituacaoInscricao;
use App\Http\Controllers\InscricaoController;
use App\Http\Controllers\InscricaoDocumentoController;
use App\Http\Controllers\PortalController;
use App\Models\ConexaoIa;
use App\Models\ConfiguracaoIa;
use App\Models\Edital;
use App\Models\EditalChamada;
use App\Models\EditalDocumento;
use App\Models\Inscricao;
use App\Models\InscricaoDocumento;
use App\Models\Organizacao;
use App\Models\User;
use App\Support\DocumentoIa\LancadorDeConferencia;
use App\Support\DocumentoIa\PromptDaConferencia;
use App\Support\EditalIa\AnalisadorGemini;
use App\Support\EditalIa\AnalisadorOpenAi;
use App\Support\EditalIa\AnaliseComRedundancia;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class ConferenciaDocumentoTest extends TestCase
{
    use RefreshDatabase;

    private const string CPF = '52998224725';

    private const string CHAVE = 'sk-teste-ChaveFalsaDaOpenAi-0123456789';

    private const string URL_OPENAI = 'https://api.openai.com/v1/responses';

    private const string CONTEUDO_PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    private User $user;

    private Edital $edital;

    private EditalDocumento $exigido;

    private Inscricao $inscricao;

    /** Lançador que roda o comando na hora (sem processo em segundo plano) e registra o que lançou. */
    private LancadorDeConferencia $lancador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'America/Bahia'));

        ConfiguracaoIa::factory()->create(['provedor_principal' => ProvedorIa::OpenAi]);
        ConexaoIa::factory()->create(['modelo' => 'gpt-5.4', 'chave_api' => self::CHAVE]);

        $this->lancador = new class extends LancadorDeConferencia
        {
            /** @var list<array{int, string}> */
            public array $chamadas = [];

            public function lancar(InscricaoDocumento $documento): void
            {
                $this->chamadas[] = [(int) $documento->getKey(), (string) $documento->execucao];

                Artisan::call('inscricoes:conferir', ['documento' => $documento->getKey(), 'execucao' => $documento->execucao]);
            }
        };
        $this->app->instance(LancadorDeConferencia::class, $this->lancador);

        $this->user = User::factory()->proponente()->create(['name' => 'Maria da Silva', 'cpf' => self::CPF]);
        $this->edital = $this->editalAberto();
        $this->exigido = EditalDocumento::factory()->for($this->edital)->create([
            'nome' => 'Documento de identidade',
            'orientacao' => 'RG ou CNH dentro da validade.',
            'instrucao_ia' => 'Confira se é um RG ou uma CNH em nome do proponente e se está legível.',
            'checagem' => ChecagemDocumento::Rigorosa,
        ]);
        $this->inscricao = Inscricao::factory()->for($this->edital)->for($this->user)->create([
            'edital_chamada_id' => $this->edital->chamadas()->first()->id,
            'documento_cadastro' => self::CPF,
        ]);
    }

    public function test_aprovado(): void
    {
        Http::fake([self::URL_OPENAI => self::respostaDaOpenAi(['aprovado' => true, 'motivo' => 'O documento está legível e em nome do proponente.'])]);

        $this->actingAs($this->user)->post(route('portal.inscricoes.documentos.store', [$this->inscricao, $this->exigido]), ['arquivo' => $this->pdf()])
            ->assertRedirect(route('portal.inscricoes.show', $this->inscricao));

        $documento = InscricaoDocumento::sole();
        $this->assertSame(SituacaoConferencia::Aprovado, $documento->situacao);
        $this->assertNull($documento->motivo);
        $this->assertNull($documento->erro);
        $this->assertSame(ProvedorIa::OpenAi, $documento->provedor);
        $this->assertSame('gpt-5.4', $documento->modelo);
        $this->assertTrue(now()->equalTo($documento->conferido_em));
        $this->assertSame([[(int) $documento->id, $documento->execucao]], $this->lancador->chamadas);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $pedido): bool {
            $conteudo = $pedido['input'][0]['content'];

            return $pedido->url() === self::URL_OPENAI
                && $pedido->hasHeader('Authorization', 'Bearer '.self::CHAVE)
                && $pedido['temperature'] === 0.1
                && $conteudo[0]['type'] === 'input_file'
                && $conteudo[0]['file_data'] === 'data:application/pdf;base64,'.base64_encode(self::CONTEUDO_PDF)
                && $pedido['text']['format']['schema']['required'] === ['aprovado', 'motivo']
                && str_contains($conteudo[1]['text'], 'Documento de identidade')
                && str_contains($conteudo[1]['text'], $this->edital->titulo)
                && str_contains($conteudo[1]['text'], 'Confira se é um RG ou uma CNH em nome do proponente')
                && str_contains($conteudo[1]['text'], 'Maria da Silva')
                && str_contains($conteudo[1]['text'], '529.982.247-25')
                && str_contains($conteudo[1]['text'], NaturezaProponente::PessoaFisica->rotulo());
        });
    }

    public function test_reprovado_grava_o_motivo(): void
    {
        Http::fake([self::URL_OPENAI => self::respostaDaOpenAi(['aprovado' => false, 'motivo' => 'O arquivo é de outra pessoa.'])]);
        $documento = $this->documentoConferindo();

        $this->assertSame(0, $this->conferir($documento));

        $documento->refresh();
        $this->assertSame(SituacaoConferencia::Reprovado, $documento->situacao);
        $this->assertSame('O arquivo é de outra pessoa.', $documento->motivo);
        $this->assertNull($documento->erro);
        $this->assertSame(ProvedorIa::OpenAi, $documento->provedor);
        $this->assertSame('gpt-5.4', $documento->modelo);
        $this->assertTrue(now()->equalTo($documento->conferido_em));
    }

    public function test_motivo_longo_e_cortado_em_1000_bytes(): void
    {
        Http::fake([self::URL_OPENAI => self::respostaDaOpenAi(['aprovado' => false, 'motivo' => str_repeat('ã', 800)])]);
        $documento = $this->documentoConferindo();

        $this->conferir($documento);

        $motivo = (string) $documento->refresh()->motivo;
        $this->assertSame(1000, strlen($motivo));
        $this->assertTrue(mb_check_encoding($motivo, 'UTF-8'));
    }

    public function test_resposta_fora_do_formato_fica_nao_conferido(): void
    {
        Http::fake([self::URL_OPENAI => self::respostaDaOpenAi(['aprovado' => 'sim', 'motivo' => 'Tudo certo.'])]);
        $documento = $this->documentoConferindo();

        $this->assertSame(1, $this->conferir($documento));

        $documento->refresh();
        $this->assertSame(SituacaoConferencia::NaoConferido, $documento->situacao);
        $this->assertSame(InscricaoDocumentoController::NAO_CONFERIDO, $documento->motivo);
        $this->assertStringContainsString('fora do formato esperado', (string) $documento->erro);
    }

    public function test_ia_fora_do_ar_fica_nao_conferido_sem_detalhe_para_o_proponente(): void
    {
        Http::fake([self::URL_OPENAI => Http::response(['error' => ['message' => 'The server had an error.']], 500)]);
        $documento = $this->documentoConferindo();

        $this->assertSame(1, $this->conferir($documento));

        $documento->refresh();
        $this->assertSame(SituacaoConferencia::NaoConferido, $documento->situacao);
        $this->assertSame(InscricaoDocumentoController::NAO_CONFERIDO, $documento->motivo);
        $this->assertStringStartsWith(AnaliseComRedundancia::INDISPONIVEL.' Detalhe: ', (string) $documento->erro);
        $this->assertSame(ProvedorIa::OpenAi, $documento->provedor);
        $this->assertSame('gpt-5.4', $documento->modelo);
        $this->assertTrue(now()->equalTo($documento->conferido_em));

        // O proponente só vê a mensagem genérica; o detalhe técnico fica no banco.
        $resposta = $this->actingAs($this->user)->getJson(route('portal.inscricoes.situacao', $this->inscricao))->assertOk();
        $this->assertSame(InscricaoDocumentoController::NAO_CONFERIDO, $resposta->json('documentos.0.motivo'));
        $this->assertStringNotContainsString('Detalhe', $resposta->getContent());
    }

    public function test_sem_configuracao_de_ia_fica_nao_conferido(): void
    {
        Http::fake();
        ConfiguracaoIa::query()->delete();
        $documento = $this->documentoConferindo();

        $this->assertSame(1, $this->conferir($documento));

        $documento->refresh();
        $this->assertSame(SituacaoConferencia::NaoConferido, $documento->situacao);
        $this->assertSame(InscricaoDocumentoController::NAO_CONFERIDO, $documento->motivo);
        $this->assertSame(AnaliseComRedundancia::SEM_CONFIGURACAO, $documento->erro);
        $this->assertNull($documento->provedor);
        $this->assertNull($documento->modelo);
        $this->assertTrue(now()->equalTo($documento->conferido_em));
        Http::assertNothingSent();
    }

    public function test_execucao_antiga_nao_regrava_o_documento_substituido(): void
    {
        $aoResponder = null;
        Http::fake([self::URL_OPENAI => function () use (&$aoResponder) {
            if ($aoResponder !== null) {
                $aoResponder();
            }

            return self::respostaDaOpenAi(['aprovado' => false, 'motivo' => 'Ilegível.'])();
        }]);
        $documento = $this->documentoConferindo();

        // Execução que não é mais a do documento: nem chama a IA.
        $this->assertSame(1, $this->conferir($documento, 'execucao-antiga'));

        // Já concluído, o mesmo processo repetido não regrava.
        $outro = EditalDocumento::factory()->for($this->edital)->create(['checagem' => ChecagemDocumento::Rigorosa, 'obrigatorio' => false]);
        $concluido = $this->documentoConferindo(['situacao' => SituacaoConferencia::Aprovado, 'edital_documento_id' => $outro->id], 'concluido');
        $this->assertSame(1, $this->conferir($concluido));
        $this->assertSame(SituacaoConferencia::Aprovado, $concluido->refresh()->situacao);

        Http::assertNothingSent();

        // O proponente troca o arquivo enquanto a IA responde: o resultado do arquivo antigo se perde.
        $aoResponder = fn () => $documento->update(['arquivo' => 'inscricoes/novo.pdf', 'execucao' => 'execucao-nova', 'iniciada_em' => now()]);
        $this->assertSame(1, $this->conferir($documento->fresh(), 'execucao-atual'));

        Http::assertSentCount(1);
        $documento->refresh();
        $this->assertSame(SituacaoConferencia::Conferindo, $documento->situacao);
        $this->assertSame('execucao-nova', $documento->execucao);
        $this->assertNull($documento->motivo);
        $this->assertNull($documento->provedor);
        $this->assertNull($documento->conferido_em);
    }

    public function test_documento_apagado_durante_a_conferencia_nao_quebra(): void
    {
        $documento = $this->documentoConferindo();
        Http::fake([self::URL_OPENAI => function () use ($documento) {
            $documento->delete();

            return self::respostaDaOpenAi(['aprovado' => true, 'motivo' => 'Ok.'])();
        }]);

        $this->assertSame(1, $this->conferir($documento));
        $this->assertSame(0, InscricaoDocumento::count());

        // E o apagado antes de o processo começar.
        $this->assertSame(1, Artisan::call('inscricoes:conferir', ['documento' => $documento->id, 'execucao' => 'execucao-atual']));
        $this->assertSame(1, Artisan::call('inscricoes:conferir', ['documento' => 999999]));
    }

    public function test_conferir_de_novo_so_em_reprovado_ou_nao_conferido(): void
    {
        Http::fake([self::URL_OPENAI => self::respostaDaOpenAi(['aprovado' => true, 'motivo' => 'Ok.'])]);
        $url = route('portal.inscricoes.documentos.conferir', [$this->inscricao, $this->exigido]);
        $this->actingAs($this->user);

        foreach ([SituacaoConferencia::Enviado, SituacaoConferencia::Conferindo, SituacaoConferencia::Aprovado] as $situacao) {
            $documento = $this->documentoConferindo(['situacao' => $situacao]);

            $this->post($url)
                ->assertRedirect(route('portal.inscricoes.show', $this->inscricao))
                ->assertSessionHas('aviso', InscricaoDocumentoController::AVISO_CONFERIR_DE_NOVO);

            $this->assertSame('execucao-atual', $documento->fresh()->execucao);
            $this->assertSame($situacao, $documento->fresh()->situacao);
            $documento->delete();
        }
        Http::assertNothingSent();
        $this->assertSame([], $this->lancador->chamadas);

        foreach ([SituacaoConferencia::Reprovado, SituacaoConferencia::NaoConferido] as $situacao) {
            $documento = $this->documentoConferindo([
                'situacao' => $situacao,
                'motivo' => 'Antigo.',
                'erro' => 'Detalhe antigo.',
                'provedor' => ProvedorIa::Gemini,
                'modelo' => 'gemini-2.5-pro',
                'conferido_em' => now()->subHour(),
            ]);

            $this->post($url)
                ->assertRedirect(route('portal.inscricoes.show', $this->inscricao))
                ->assertSessionHas('sucesso');

            $documento->refresh();
            $this->assertSame(SituacaoConferencia::Aprovado, $documento->situacao);
            $this->assertNotSame('execucao-atual', $documento->execucao);
            $this->assertNull($documento->motivo);
            $this->assertNull($documento->erro);
            $this->assertSame(ProvedorIa::OpenAi, $documento->provedor);
            $this->assertSame([(int) $documento->id, $documento->execucao], $this->lancador->chamadas[array_key_last($this->lancador->chamadas)]);
            $documento->delete();
        }
        $this->assertCount(2, $this->lancador->chamadas);
    }

    public function test_conferir_de_novo_segue_as_regras_de_anexar(): void
    {
        Http::fake();
        $url = route('portal.inscricoes.documentos.conferir', [$this->inscricao, $this->exigido]);
        $documento = $this->documentoConferindo(['situacao' => SituacaoConferencia::Reprovado]);

        $this->actingAs(User::factory()->proponente()->create())->post($url)->assertNotFound();

        $this->actingAs($this->user);
        $this->inscricao->update(['situacao' => SituacaoInscricao::Finalizada]);
        $this->post($url)
            ->assertRedirect(route('portal.inscricoes.show', $this->inscricao))
            ->assertSessionHas('erro', InscricaoController::ERRO_REABRIR);

        $this->inscricao->update(['situacao' => SituacaoInscricao::Rascunho]);
        $this->travelTo(Carbon::parse('2026-11-20 10:00', 'America/Bahia'));
        $this->post($url)
            ->assertRedirect(route('portal.editais'))
            ->assertSessionHas('aviso', PortalController::AVISO_INSCRICOES_FECHADAS);

        $this->assertSame(SituacaoConferencia::Reprovado, $documento->fresh()->situacao);
        $this->assertSame([], $this->lancador->chamadas);
        Http::assertNothingSent();
    }

    public function test_conferindo_parado_libera_conferir_de_novo(): void
    {
        Http::fake([self::URL_OPENAI => self::respostaDaOpenAi(['aprovado' => true, 'motivo' => 'Ok.'])]);
        $url = route('portal.inscricoes.documentos.conferir', [$this->inscricao, $this->exigido]);
        $documento = $this->documentoConferindo(['iniciada_em' => now()->subMinutes(5)]);
        $this->actingAs($this->user);

        // Exatamente cinco minutos ainda é conferência em andamento.
        $this->getJson(route('portal.inscricoes.situacao', $this->inscricao))->assertJsonPath('documentos.0.situacao', 'conferindo');
        $this->post($url)->assertSessionHas('aviso', InscricaoDocumentoController::AVISO_CONFERIR_DE_NOVO);
        $this->assertSame([], $this->lancador->chamadas);

        $documento->update(['iniciada_em' => now()->subMinutes(5)->subSecond()]);

        $this->getJson(route('portal.inscricoes.situacao', $this->inscricao))
            ->assertJsonPath('documentos.0.situacao', 'nao_conferido')
            ->assertJsonPath('documentos.0.motivo', InscricaoDocumentoController::NAO_CONFERIDO);
        $this->post($url)->assertSessionHas('sucesso');

        $documento->refresh();
        $this->assertSame(SituacaoConferencia::Aprovado, $documento->situacao);
        $this->assertNotSame('execucao-atual', $documento->execucao);
        $this->assertTrue(now()->equalTo($documento->iniciada_em));
        $this->assertCount(1, $this->lancador->chamadas);
    }

    public function test_conferir_de_novo_respeita_nao_validar(): void
    {
        Http::fake();
        $url = route('portal.inscricoes.documentos.conferir', [$this->inscricao, $this->exigido]);
        $this->exigido->update(['checagem' => ChecagemDocumento::Nenhuma]);
        $this->actingAs($this->user);

        $paradoHaMuito = ['situacao' => SituacaoConferencia::Conferindo, 'iniciada_em' => now()->subMinutes(6)];

        foreach ([['situacao' => SituacaoConferencia::Reprovado], ['situacao' => SituacaoConferencia::NaoConferido], $paradoHaMuito] as $atributos) {
            $documento = $this->documentoConferindo([...$atributos, 'motivo' => 'Antigo.', 'erro' => 'Detalhe antigo.', 'conferido_em' => now()->subHour()]);

            $this->post($url)
                ->assertRedirect(route('portal.inscricoes.show', $this->inscricao))
                ->assertSessionHas('aviso', InscricaoDocumentoController::AVISO_SEM_CONFERENCIA);

            $documento->refresh();
            $this->assertSame(SituacaoConferencia::Enviado, $documento->situacao);
            $this->assertNull($documento->motivo);
            $this->assertNull($documento->erro);
            $this->assertNull($documento->execucao);
            $this->assertNull($documento->iniciada_em);
            $this->assertNull($documento->conferido_em);
            $documento->delete();
        }

        Http::assertNothingSent();
        $this->assertSame([], $this->lancador->chamadas);

        // Já aprovado, não há o que refazer: a recusa de sempre.
        $aprovado = $this->documentoConferindo(['situacao' => SituacaoConferencia::Aprovado]);
        $this->post($url)->assertSessionHas('aviso', InscricaoDocumentoController::AVISO_CONFERIR_DE_NOVO);
        $this->assertSame(SituacaoConferencia::Aprovado, $aprovado->fresh()->situacao);
        Http::assertNothingSent();
    }

    public function test_comando_nao_chama_a_ia_quando_o_documento_passou_para_nao_validar(): void
    {
        Http::fake();
        $documento = $this->documentoConferindo();
        $this->exigido->update(['checagem' => ChecagemDocumento::Nenhuma]);

        $this->assertSame(0, $this->conferir($documento));

        $documento->refresh();
        $this->assertSame(SituacaoConferencia::Enviado, $documento->situacao);
        $this->assertNull($documento->motivo);
        $this->assertNull($documento->erro);
        $this->assertNull($documento->provedor);
        $this->assertNull($documento->execucao);
        $this->assertNull($documento->conferido_em);
        Http::assertNothingSent();

        // Execução que já não é a do documento: nada gravado, também sem IA.
        $outro = EditalDocumento::factory()->for($this->edital)->create(['checagem' => ChecagemDocumento::Nenhuma, 'obrigatorio' => false]);
        $trocado = $this->documentoConferindo(['edital_documento_id' => $outro->id], 'execucao-nova');
        $this->assertSame(1, $this->conferir($trocado, 'execucao-antiga'));
        $this->assertSame(SituacaoConferencia::Conferindo, $trocado->refresh()->situacao);
        Http::assertNothingSent();
    }

    public function test_situacao_em_json_so_para_o_dono(): void
    {
        $opcional = EditalDocumento::factory()->for($this->edital)->create(['checagem' => ChecagemDocumento::Rigorosa, 'obrigatorio' => false]);
        $this->documentoConferindo(['situacao' => SituacaoConferencia::Reprovado, 'motivo' => 'O arquivo está ilegível.', 'erro' => 'detalhe técnico']);
        InscricaoDocumento::factory()->conferindo()->for($this->inscricao)->for($opcional, 'documento')->create(['execucao' => 'outra']);
        $url = route('portal.inscricoes.situacao', $this->inscricao);

        $this->getJson($url)->assertUnauthorized();

        $this->actingAs($this->user)->getJson($url)
            ->assertOk()
            ->assertExactJson([
                'documentos' => [
                    ['id' => (int) $this->exigido->id, 'situacao' => 'reprovado', 'motivo' => 'O arquivo está ilegível.'],
                    ['id' => (int) $opcional->id, 'situacao' => 'conferindo', 'motivo' => null],
                ],
                'podeFinalizar' => false,
                'pendencias' => ['"Documento de identidade" precisa ser aprovado na conferência.', 'Aguarde a conferência de "'.$opcional->nome.'".'],
            ]);

        $this->actingAs(User::factory()->proponente()->create())->getJson($url)->assertNotFound();
        $this->getJson('/portal/inscricoes/abc/situacao')->assertNotFound();
    }

    public function test_chave_da_api_nao_aparece_no_erro_gravado(): void
    {
        Http::fake([self::URL_OPENAI => Http::response(['error' => ['message' => str_repeat('x', 185).' '.self::CHAVE.' is not allowed.']], 400)]);
        $documento = $this->documentoConferindo();

        $this->conferir($documento);

        $documento->refresh();
        $this->assertSame(SituacaoConferencia::NaoConferido, $documento->situacao);
        $this->assertStringContainsString('recusou o pedido (erro 400)', (string) $documento->erro);
        for ($inicio = 0; $inicio <= strlen(self::CHAVE) - 8; $inicio++) {
            $this->assertStringNotContainsString(substr(self::CHAVE, $inicio, 8), (string) $documento->erro);
        }
    }

    public function test_erro_inesperado_grava_mensagem_generica_e_loga_sem_a_chave(): void
    {
        $this->app->instance(AnaliseComRedundancia::class, new class(app(AnalisadorGemini::class), app(AnalisadorOpenAi::class)) extends AnaliseComRedundancia
        {
            public function analisar(array $provedores, ?string $pdfBinario, string $prompt, float $temperatura, array $esquema, ?callable $conferirResposta = null): array
            {
                throw new RuntimeException('Falhou com a chave sk-teste-ChaveFalsaDaOpenAi-0123456789 no meio.');
            }
        });
        Log::spy();
        $documento = $this->documentoConferindo();

        $this->assertSame(1, $this->conferir($documento));

        $documento->refresh();
        $this->assertSame(SituacaoConferencia::NaoConferido, $documento->situacao);
        $this->assertSame(InscricaoDocumentoController::NAO_CONFERIDO, $documento->motivo);
        $this->assertSame('Erro inesperado na conferência.', $documento->erro);
        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $mensagem, array $contexto): bool => ! str_contains($mensagem.json_encode($contexto), self::CHAVE)
            && $contexto['excecao'] === RuntimeException::class
            && $contexto['documento'] === (int) $documento->id);
    }

    public function test_prompt_da_organizacao_sem_instrucao_usa_o_nome_e_a_orientacao_do_documento(): void
    {
        $organizacao = Organizacao::factory()->create(['cnpj' => '11222333000181', 'razao_social' => 'Cultura Viva Ltda']);
        $this->exigido->update(['instrucao_ia' => null]);
        $this->inscricao->update(['organizacao_id' => $organizacao->id, 'documento_cadastro' => '11222333000181', 'natureza' => NaturezaProponente::ComFins]);
        $documento = $this->documentoConferindo();

        $prompt = PromptDaConferencia::montar($documento);

        $this->assertStringContainsString('Documento de identidade', $prompt);
        $this->assertStringContainsString('RG ou CNH dentro da validade.', $prompt);
        $this->assertStringContainsString('Cultura Viva Ltda', $prompt);
        $this->assertStringContainsString('11.222.333/0001-81', $prompt);
        $this->assertStringContainsString(NaturezaProponente::ComFins->rotulo(), $prompt);
        $this->assertStringNotContainsString('Maria da Silva', $prompt);
    }

    private function pdf(): UploadedFile
    {
        $caminho = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($caminho, self::CONTEUDO_PDF);

        return new UploadedFile($caminho, 'rg.pdf', null, null, true);
    }

    /**
     * A resposta da OpenAI (Responses API) com o JSON da conferência.
     *
     * @param  array<string, mixed>  $dados
     */
    private static function respostaDaOpenAi(array $dados): Closure
    {
        return fn () => Http::response([
            'output' => [['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => json_encode($dados)]]]],
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 30, 'total_tokens' => 1230],
        ]);
    }

    /**
     * Documento do exigido na conferência `execucao-atual`, com o PDF no disco.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function documentoConferindo(array $atributos = [], string $execucao = 'execucao-atual'): InscricaoDocumento
    {
        $caminho = "inscricoes/{$this->inscricao->id}/{$execucao}.pdf";
        Storage::disk('local')->put($caminho, self::CONTEUDO_PDF);

        return InscricaoDocumento::factory()->conferindo()->for($this->inscricao)->for($this->exigido, 'documento')->create([
            'arquivo' => $caminho,
            'arquivo_nome' => 'rg.pdf',
            'provedor' => null,
            'modelo' => null,
            'execucao' => $execucao,
            ...$atributos,
        ]);
    }

    /** Roda o comando como o lançador faz e devolve o código de saída. */
    private function conferir(InscricaoDocumento $documento, ?string $execucao = null): int
    {
        return Artisan::call('inscricoes:conferir', ['documento' => $documento->id, 'execucao' => $execucao ?? $documento->execucao]);
    }

    private function editalAberto(): Edital
    {
        $edital = Edital::factory()->create(['slug' => 'pnab', 'inscricoes_inicio' => '2026-11-01', 'inscricoes_fim' => '2026-11-15']);
        EditalChamada::factory()->for($edital)->create(['inicio' => '2026-11-01', 'fim' => '2026-11-15', 'ordem' => 1]);

        return $edital;
    }
}
