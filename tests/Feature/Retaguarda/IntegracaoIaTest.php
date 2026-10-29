<?php

namespace Tests\Feature\Retaguarda;

use App\Enums\ProvedorIa;
use App\Models\ConexaoIa;
use App\Models\ConfiguracaoIa;
use App\Models\User;
use App\Support\EditalIa\PromptModelo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IntegracaoIaTest extends TestCase
{
    use RefreshDatabase;

    private const string URL = '/retaguarda/integracao-ia';

    private const string URL_REDUNDANCIA = '/retaguarda/integracao-ia/redundancia';

    private const string URL_IMPORTACAO = '/retaguarda/integracao-ia/importacao';

    // Fora do formato real das chaves, que os scanners de segredo do GitHub barram.
    private const string CHAVE_OPENAI = 'sk-teste-0123456789abcdefghijH2UA';

    private const string CHAVE_GEMINI = 'gemini-teste-0123456789abcdefXyZ9';

    private const string AJUDA_OPENAI = 'Crie a chave em platform.openai.com/api-keys.';

    private const string AJUDA_GEMINI = 'Crie a chave em aistudio.google.com/apikey, num projeto com faturamento ativo: no plano gratuito, o Google pode usar o que for enviado.';

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function principal(array $alteracoes = []): array
    {
        return ['provedor' => 'openai', 'modelo' => 'gpt-5.4', 'chave_api' => self::CHAVE_OPENAI, 'ativo' => true, ...$alteracoes];
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function redundancia(array $alteracoes = []): array
    {
        return ['provedor' => 'gemini', 'modelo' => 'gemini-3.8-flash', 'chave_api' => self::CHAVE_GEMINI, ...$alteracoes];
    }

    public function test_tela_mostra_os_provedores_sem_configuracao_antes_da_primeira_gravacao(): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->get(self::URL);

        $resposta->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Retaguarda/IntegracaoIa')
            ->where('configuracao', ['ativo' => false, 'principal' => null, 'redundancia' => null])
            ->where('provedores', [
                ['valor' => 'openai', 'rotulo' => 'OpenAI', 'ajudaChave' => self::AJUDA_OPENAI, 'modelo' => null, 'chave' => null],
                ['valor' => 'gemini', 'rotulo' => 'Google Gemini', 'ajudaChave' => self::AJUDA_GEMINI, 'modelo' => null, 'chave' => null],
            ]));
    }

    public function test_tela_mostra_a_configuracao_salva_e_so_a_mascara_das_chaves(): void
    {
        ConexaoIa::factory()->create(['chave_api' => self::CHAVE_OPENAI]);
        ConexaoIa::factory()->gemini()->create(['chave_api' => self::CHAVE_GEMINI]);
        ConfiguracaoIa::factory()->comRedundancia()->create();
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->get(self::URL);

        $resposta->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('configuracao', ['ativo' => true, 'principal' => 'openai', 'redundancia' => 'gemini'])
            ->where('provedores', [
                ['valor' => 'openai', 'rotulo' => 'OpenAI', 'ajudaChave' => self::AJUDA_OPENAI, 'modelo' => 'gpt-5.4', 'chave' => 'sk-…H2UA'],
                ['valor' => 'gemini', 'rotulo' => 'Google Gemini', 'ajudaChave' => self::AJUDA_GEMINI, 'modelo' => 'gemini-3.8-flash', 'chave' => 'gem…XyZ9'],
            ]));

        $props = json_encode($resposta->viewData('page')['props'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString(self::CHAVE_OPENAI, $props);
        $this->assertStringNotContainsString(self::CHAVE_GEMINI, $props);
        foreach (DB::table('conexoes_ia')->pluck('chave_api') as $cifrada) {
            $this->assertStringNotContainsString($cifrada, $props);
        }
    }

    public function test_salva_o_provedor_principal_com_a_chave_cifrada_no_banco(): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, $this->principal());

        $resposta->assertSessionHasNoErrors()
            ->assertRedirect(self::URL)
            ->assertSessionHas('sucesso', 'Configuração da IA salva.');

        $configuracao = ConfiguracaoIa::sole();
        $this->assertTrue($configuracao->ativo);
        $this->assertSame(ProvedorIa::OpenAi, $configuracao->provedor_principal);
        $this->assertNull($configuracao->provedor_redundancia);

        $conexao = ConexaoIa::sole();
        $this->assertSame(ProvedorIa::OpenAi, $conexao->provedor);
        $this->assertSame('gpt-5.4', $conexao->modelo);
        $this->assertSame(self::CHAVE_OPENAI, $conexao->chaveGuardada());

        $gravada = DB::table('conexoes_ia')->value('chave_api');
        $this->assertStringNotContainsString(self::CHAVE_OPENAI, $gravada);
        $this->assertSame(self::CHAVE_OPENAI, Crypt::decryptString($gravada));
    }

    public function test_chave_do_tamanho_maximo_cabe_cifrada_na_coluna(): void
    {
        $chave = 'sk-teste-'.str_repeat('a', 291);
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, $this->principal(['chave_api' => $chave]));

        $resposta->assertSessionHasNoErrors()->assertRedirect(self::URL);
        $this->assertSame($chave, ConexaoIa::sole()->chaveGuardada());
    }

    public function test_chave_em_branco_mantem_a_guardada_e_troca_o_modelo(): void
    {
        ConexaoIa::factory()->create(['chave_api' => self::CHAVE_OPENAI]);
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, $this->principal(['modelo' => 'gpt-5.4-mini', 'chave_api' => '']));

        $resposta->assertSessionHasNoErrors()->assertRedirect(self::URL);
        $conexao = ConexaoIa::sole();
        $this->assertSame('gpt-5.4-mini', $conexao->modelo);
        $this->assertSame(self::CHAVE_OPENAI, $conexao->chaveGuardada());
    }

    public function test_exige_a_chave_quando_o_provedor_ainda_nao_tem_uma(): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, $this->principal(['chave_api' => '']));

        $resposta->assertSessionHasErrors(['chave_api' => 'Informe a chave da API.']);
        $this->assertSame(0, ConexaoIa::count());
        $this->assertSame(0, ConfiguracaoIa::count());
    }

    public function test_chave_digitada_nao_volta_para_a_sessao_quando_a_validacao_falha(): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, $this->principal(['modelo' => '../modelo']));

        $resposta->assertSessionHasErrors(['modelo' => 'Escolha um modelo da lista.'])
            ->assertSessionHas('_old_input.modelo', '../modelo')
            ->assertSessionMissing('_old_input.chave_api');
    }

    public function test_recusa_envio_sem_os_campos(): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, []);

        $resposta->assertSessionHasErrors([
            'provedor' => 'Este campo é obrigatório.',
            'modelo' => 'Este campo é obrigatório.',
            'ativo' => 'Este campo é obrigatório.',
        ]);
        $this->assertSame(0, ConfiguracaoIa::count());
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function valoresInvalidos(): array
    {
        return [
            'provedor fora da lista' => [['provedor' => 'anthropic'], 'provedor', 'Selecione uma opção da lista.'],
            'modelo com barra' => [['modelo' => '../models/gpt-5.4'], 'modelo', 'Escolha um modelo da lista.'],
            'chave com espaço' => [['chave_api' => 'sk-teste-chave com espaco-0123456789'], 'chave_api', 'A chave não pode ter espaços.'],
            'chave curta' => [['chave_api' => 'sk-curta'], 'chave_api', 'A chave parece incompleta. Copie a chave inteira.'],
            'chave longa demais' => [['chave_api' => 'sk-teste-'.str_repeat('a', 292)], 'chave_api', 'A chave passa do tamanho esperado. Confira se colou só a chave.'],
            'ativo fora de sim ou não' => [['ativo' => 'talvez'], 'ativo', 'Valor inválido.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     */
    #[DataProvider('valoresInvalidos')]
    public function test_recusa_valor_invalido_com_a_mensagem_do_campo(array $alteracoes, string $campo, string $mensagem): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, $this->principal($alteracoes));

        $resposta->assertSessionHasErrors([$campo => $mensagem]);
        $this->assertSame(0, ConexaoIa::count());
    }

    public function test_trocar_o_principal_mantem_a_chave_de_cada_provedor(): void
    {
        ConexaoIa::factory()->create(['chave_api' => self::CHAVE_OPENAI]);
        ConexaoIa::factory()->gemini()->create(['chave_api' => self::CHAVE_GEMINI]);
        ConfiguracaoIa::factory()->create(['provedor_principal' => ProvedorIa::Gemini]);
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, $this->principal(['chave_api' => '']));

        $resposta->assertSessionHasNoErrors()->assertRedirect(self::URL);
        $this->assertSame(ProvedorIa::OpenAi, ConfiguracaoIa::sole()->provedor_principal);
        $this->assertSame(self::CHAVE_OPENAI, ConexaoIa::doProvedor(ProvedorIa::OpenAi)?->chaveGuardada());
        $this->assertSame(self::CHAVE_GEMINI, ConexaoIa::doProvedor(ProvedorIa::Gemini)?->chaveGuardada());
    }

    public function test_promover_a_redundancia_troca_os_dois_de_lugar(): void
    {
        ConexaoIa::factory()->create();
        ConexaoIa::factory()->gemini()->create();
        ConfiguracaoIa::factory()->comRedundancia()->create();
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, $this->principal(['provedor' => 'gemini', 'modelo' => 'gemini-3.8-flash', 'chave_api' => '']));

        $resposta->assertSessionHasNoErrors()
            ->assertRedirect(self::URL)
            ->assertSessionHas('sucesso', 'Configuração da IA salva. Google Gemini passou a ser o principal e OpenAI, a redundância.');
        $configuracao = ConfiguracaoIa::sole();
        $this->assertSame(ProvedorIa::Gemini, $configuracao->provedor_principal);
        $this->assertSame(ProvedorIa::OpenAi, $configuracao->provedor_redundancia);
    }

    public function test_salva_a_redundancia_com_modelo_e_chave_proprios(): void
    {
        ConexaoIa::factory()->create(['chave_api' => self::CHAVE_OPENAI]);
        ConfiguracaoIa::factory()->create();
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL_REDUNDANCIA, $this->redundancia());

        $resposta->assertSessionHasNoErrors()
            ->assertRedirect(self::URL)
            ->assertSessionHas('sucesso', 'Redundância salva.');

        $configuracao = ConfiguracaoIa::sole();
        $this->assertSame(ProvedorIa::OpenAi, $configuracao->provedor_principal);
        $this->assertSame(ProvedorIa::Gemini, $configuracao->provedor_redundancia);

        $gemini = ConexaoIa::doProvedor(ProvedorIa::Gemini);
        $this->assertSame('gemini-3.8-flash', $gemini?->modelo);
        $this->assertSame(self::CHAVE_GEMINI, $gemini?->chaveGuardada());
        $this->assertSame(self::CHAVE_OPENAI, ConexaoIa::doProvedor(ProvedorIa::OpenAi)?->chaveGuardada());
    }

    public function test_redundancia_exige_o_principal_salvo_antes(): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL_REDUNDANCIA, $this->redundancia());

        $resposta->assertSessionHasErrors(['provedor' => 'Salve o provedor principal antes da redundância.']);
        $this->assertSame(0, ConexaoIa::count());
        $this->assertSame(0, ConfiguracaoIa::count());
    }

    public function test_redundancia_recusa_o_proprio_principal(): void
    {
        ConexaoIa::factory()->create();
        ConfiguracaoIa::factory()->create();
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL_REDUNDANCIA, $this->redundancia(['provedor' => 'openai', 'modelo' => 'gpt-5.4', 'chave_api' => '']));

        $resposta->assertSessionHasErrors(['provedor' => 'Escolha um provedor diferente do principal.']);
        $this->assertNull(ConfiguracaoIa::sole()->provedor_redundancia);
    }

    public function test_retirar_a_redundancia_mantem_a_chave_do_provedor(): void
    {
        ConexaoIa::factory()->create();
        ConexaoIa::factory()->gemini()->create(['chave_api' => self::CHAVE_GEMINI]);
        ConfiguracaoIa::factory()->comRedundancia()->create();
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL_REDUNDANCIA, ['provedor' => '', 'modelo' => 'gemini-3.8-flash', 'chave_api' => '']);

        $resposta->assertSessionHasNoErrors()
            ->assertRedirect(self::URL)
            ->assertSessionHas('sucesso', 'Redundância retirada.');
        $this->assertNull(ConfiguracaoIa::sole()->provedor_redundancia);
        $this->assertSame(self::CHAVE_GEMINI, ConexaoIa::doProvedor(ProvedorIa::Gemini)?->chaveGuardada());
    }

    public function test_chave_que_nao_decifra_mais_aparece_como_provedor_sem_chave(): void
    {
        $conexao = ConexaoIa::factory()->create();
        DB::table('conexoes_ia')->where('id', $conexao->id)->update(['chave_api' => 'cifrada-com-outra-app-key']);
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->get(self::URL);

        $resposta->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('provedores.0.modelo', 'gpt-5.4')
            ->where('provedores.0.chave', null));
    }

    public function test_chave_que_nao_decifra_mais_precisa_ser_informada_de_novo(): void
    {
        $conexao = ConexaoIa::factory()->create();
        DB::table('conexoes_ia')->where('id', $conexao->id)->update(['chave_api' => 'cifrada-com-outra-app-key']);
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL, $this->principal(['chave_api' => '']));

        $resposta->assertSessionHasErrors(['chave_api' => 'Informe a chave da API.']);
        $this->assertSame(0, ConfiguracaoIa::count());
    }

    public function test_proponente_nao_acessa(): void
    {
        $this->actingAs(User::factory()->proponente()->create());

        $this->get(self::URL)->assertRedirect('/retaguarda/login');
        $this->put(self::URL, $this->principal())->assertRedirect('/retaguarda/login');
        $this->put(self::URL_REDUNDANCIA, $this->redundancia())->assertRedirect('/retaguarda/login');

        $this->assertSame(0, ConexaoIa::count());
        $this->assertSame(0, ConfiguracaoIa::count());
    }

    public function test_visitante_vai_para_login(): void
    {
        $this->get(self::URL)->assertRedirect('/retaguarda/login');
    }

    public function test_menu_aponta_para_a_integracao_com_ia(): void
    {
        $menu = file_get_contents(resource_path('js/Components/Retaguarda/MenuRetaguarda.vue'));

        $this->assertStringContainsString(route('retaguarda.integracao-ia.edit', absolute: false), $menu);
        $this->assertStringContainsString("'Integração com IA'", $menu);
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function importacao(array $alteracoes = []): array
    {
        return ['prompt' => 'Leia o edital e preencha o cadastro.', 'temperatura' => 0.35, 'tamanho_maximo_mb' => 8, ...$alteracoes];
    }

    public function test_tela_traz_a_importacao_com_o_prompt_modelo_e_os_padroes_antes_da_primeira_gravacao(): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $this->get(self::URL)->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('importacao', [
                'prompt' => PromptModelo::editavel(),
                'promptModelo' => PromptModelo::editavel(),
                'temperatura' => 0.1,
                'tamanhoMaximoMb' => 14,
                'configurada' => false,
            ]));
    }

    public function test_importacao_configurada_com_o_principal_e_sem_a_chave_na_resposta(): void
    {
        ConexaoIa::factory()->create(['chave_api' => self::CHAVE_OPENAI]);
        ConfiguracaoIa::factory()->create(['ativo' => false, 'importacao_prompt' => 'Prompt da equipe.', 'importacao_temperatura' => 0.4]);
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->get(self::URL);

        $resposta->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('importacao.prompt', 'Prompt da equipe.')
            ->where('importacao.temperatura', 0.4)
            ->where('importacao.configurada', true));
        $resposta->assertDontSee(self::CHAVE_OPENAI, false);
        $resposta->assertDontSee(DB::table('conexoes_ia')->value('chave_api'), false);
    }

    public function test_salva_a_importacao_sem_mexer_na_conexao(): void
    {
        ConexaoIa::factory()->create(['chave_api' => self::CHAVE_OPENAI]);
        ConfiguracaoIa::factory()->create();
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL_IMPORTACAO, $this->importacao());

        $resposta->assertSessionHasNoErrors()
            ->assertRedirect(self::URL)
            ->assertSessionHas('sucesso', 'Configuração da importação salva.');

        $configuracao = ConfiguracaoIa::sole();
        $this->assertSame('Leia o edital e preencha o cadastro.', $configuracao->importacao_prompt);
        $this->assertSame(0.35, $configuracao->importacao_temperatura);
        $this->assertSame(8, $configuracao->importacao_tamanho_maximo_mb);
        $this->assertTrue($configuracao->ativo);
        $this->assertSame(ProvedorIa::OpenAi, $configuracao->provedor_principal);
        $this->assertSame(self::CHAVE_OPENAI, ConexaoIa::sole()->chaveGuardada());
    }

    public function test_salva_a_importacao_antes_de_qualquer_provedor(): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $this->put(self::URL_IMPORTACAO, $this->importacao())->assertSessionHasNoErrors();

        $configuracao = ConfiguracaoIa::sole();
        $this->assertFalse($configuracao->ativo);
        $this->assertNull($configuracao->provedor_principal);
        $this->assertSame(8, $configuracao->importacao_tamanho_maximo_mb);
    }

    public function test_prompt_igual_ao_modelo_grava_nulo_para_acompanhar_o_modelo(): void
    {
        ConfiguracaoIa::factory()->create(['importacao_prompt' => 'Prompt da equipe.']);
        $this->actingAs(User::factory()->gestor()->create());

        // Como vem do textarea: quebras de linha do Windows e espaço sobrando no fim.
        $prompt = str_replace("\n", "\r\n", PromptModelo::editavel())."\r\n  ";
        $this->put(self::URL_IMPORTACAO, $this->importacao(['prompt' => $prompt]))->assertSessionHasNoErrors();

        $this->assertNull(ConfiguracaoIa::sole()->importacao_prompt);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function importacaoInvalida(): array
    {
        return [
            'sem prompt' => [['prompt' => ''], 'prompt', 'Este campo é obrigatório.'],
            'prompt longo demais' => [['prompt' => str_repeat('a', 20001)], 'prompt', 'Use no máximo 20000 caracteres.'],
            'sem temperatura' => [['temperatura' => ''], 'temperatura', 'Este campo é obrigatório.'],
            'temperatura em texto' => [['temperatura' => 'baixa'], 'temperatura', 'Informe um número.'],
            'temperatura acima de 1' => [['temperatura' => 1.5], 'temperatura', 'Informe um valor entre 0 e 1.'],
            'temperatura negativa' => [['temperatura' => -0.1], 'temperatura', 'Informe um valor entre 0 e 1.'],
            'tamanho com casas decimais' => [['tamanho_maximo_mb' => 2.5], 'tamanho_maximo_mb', 'Informe um número inteiro.'],
            'tamanho zero' => [['tamanho_maximo_mb' => 0], 'tamanho_maximo_mb', 'Informe um valor entre 1 e 14.'],
            'tamanho acima do teto' => [['tamanho_maximo_mb' => 15], 'tamanho_maximo_mb', 'Informe um valor entre 1 e 14.'],
        ];
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     */
    #[DataProvider('importacaoInvalida')]
    public function test_recusa_importacao_invalida_com_a_mensagem_do_campo(array $alteracoes, string $campo, string $mensagem): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $resposta = $this->put(self::URL_IMPORTACAO, $this->importacao($alteracoes));

        $resposta->assertSessionHasErrors([$campo => $mensagem]);
        $this->assertSame(0, ConfiguracaoIa::count());
    }

    public function test_restaura_o_prompt_modelo(): void
    {
        ConfiguracaoIa::factory()->create(['importacao_prompt' => 'Prompt da equipe.', 'importacao_tamanho_maximo_mb' => 5]);
        $this->actingAs(User::factory()->gestor()->create());

        $this->put(self::URL_IMPORTACAO.'/prompt-modelo')
            ->assertRedirect(self::URL)
            ->assertSessionHas('sucesso', 'Prompt modelo restaurado.');

        $configuracao = ConfiguracaoIa::sole();
        $this->assertNull($configuracao->importacao_prompt);
        $this->assertSame(PromptModelo::editavel(), $configuracao->promptDaImportacao());
        $this->assertSame(5, $configuracao->importacao_tamanho_maximo_mb);
    }

    public function test_proponente_nao_altera_a_importacao(): void
    {
        $this->actingAs(User::factory()->proponente()->create());

        $this->put(self::URL_IMPORTACAO, $this->importacao())->assertRedirect('/retaguarda/login');
        $this->put(self::URL_IMPORTACAO.'/prompt-modelo')->assertRedirect('/retaguarda/login');

        $this->assertSame(0, ConfiguracaoIa::count());
    }
}
