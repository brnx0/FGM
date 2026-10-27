<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsultaCadastroTest extends TestCase
{
    use RefreshDatabase;

    private const string CNPJ = '11222333000181';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $sobrescrever
     * @return array<string, mixed>
     */
    private function respostaBrasilApi(array $sobrescrever = []): array
    {
        return array_merge([
            'cnpj' => self::CNPJ,
            'razao_social' => 'ASSOCIACAO CULTURAL EXEMPLO',
            'nome_fantasia' => 'Casa Exemplo',
            'codigo_natureza_juridica' => 2062,
            'opcao_pelo_mei' => true,
            'porte' => 'MICRO EMPRESA',
            'data_inicio_atividade' => '2015-03-10',
            'ddd_telefone_1' => '7133334444',
            'cep' => '40015000',
            'descricao_tipo_de_logradouro' => 'RUA',
            'logradouro' => 'DAS FLORES',
            'numero' => '120',
            'complemento' => 'SALA 2',
            'bairro' => 'COMERCIO',
            'municipio' => 'SALVADOR',
            'uf' => 'BA',
        ], $sobrescrever);
    }

    private function logar(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_cnpj_mapeia_a_resposta_da_brasilapi(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->respostaBrasilApi())]);
        $this->logar();

        $resposta = $this->getJson(route('consultas.cnpj', self::CNPJ))->assertOk()->assertJsonPath('encontrado', true);

        $dados = $resposta->json('dados');
        $this->assertSame('ASSOCIACAO CULTURAL EXEMPLO', $dados['razao_social']);
        $this->assertSame('Casa Exemplo', $dados['nome_fantasia']);
        $this->assertSame('2062', $dados['natureza_juridica']);
        $this->assertTrue($dados['finalidade_lucrativa']);
        $this->assertTrue($dados['mei']);
        $this->assertSame('micro', $dados['porte']);
        $this->assertSame('2015-03-10', $dados['data_fundacao']);
        $this->assertSame('7133334444', $dados['telefone']);
        $this->assertSame('40015000', $dados['cep']);
        $this->assertSame('RUA DAS FLORES', $dados['logradouro']);
        $this->assertSame('120', $dados['numero']);
        $this->assertFalse($dados['sem_numero']);
        $this->assertSame('SALA 2', $dados['complemento']);
        $this->assertSame('COMERCIO', $dados['bairro']);
        $this->assertSame('SALVADOR', $dados['cidade']);
        $this->assertSame('BA', $dados['uf']);
        $this->assertSame(['codigo' => '2927408', 'nome' => 'Salvador', 'uf' => 'BA'], $dados['municipio']);
        Http::assertSent(fn ($request) => $request->url() === 'https://brasilapi.com.br/api/cnpj/v1/'.self::CNPJ);
    }

    public function test_cnpj_invalido_por_digito_retorna_422(): void
    {
        Http::fake();
        $this->logar();

        $this->getJson(route('consultas.cnpj', '11222333000182'))
            ->assertStatus(422)
            ->assertExactJson(['message' => 'CNPJ inválido.']);
        Http::assertNothingSent();
    }

    public function test_numero_sem_numero_vira_flag(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->respostaBrasilApi(['numero' => 'S/N', 'complemento' => '', 'nome_fantasia' => '']))]);
        $this->logar();

        $this->getJson(route('consultas.cnpj', self::CNPJ))
            ->assertJsonPath('dados.numero', null)
            ->assertJsonPath('dados.sem_numero', true)
            ->assertJsonPath('dados.complemento', null)
            ->assertJsonPath('dados.nome_fantasia', null);
    }

    public function test_natureza_desconhecida_e_porte_outro_viram_nulos(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->respostaBrasilApi([
            'codigo_natureza_juridica' => 9999,
            'porte' => 'DEMAIS',
            'opcao_pelo_mei' => null,
            'ddd_telefone_1' => '',
        ]))]);
        $this->logar();

        $this->getJson(route('consultas.cnpj', self::CNPJ))
            ->assertJsonPath('dados.natureza_juridica', null)
            ->assertJsonPath('dados.porte', null)
            ->assertJsonPath('dados.mei', false)
            ->assertJsonPath('dados.telefone', null);
    }

    public function test_porte_empresa_de_pequeno_porte(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->respostaBrasilApi(['porte' => 'EMPRESA DE PEQUENO PORTE']))]);
        $this->logar();

        $this->getJson(route('consultas.cnpj', self::CNPJ))->assertJsonPath('dados.porte', 'pequeno');
    }

    public function test_cnpj_nao_encontrado_na_brasilapi(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response(['message' => 'não encontrado'], 404)]);
        $this->logar();

        $this->getJson(route('consultas.cnpj', self::CNPJ))->assertOk()->assertExactJson(['encontrado' => false]);
    }

    public function test_erro_500_da_brasilapi_vira_nao_encontrado(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response('erro', 500)]);
        $this->logar();

        $this->getJson(route('consultas.cnpj', self::CNPJ))->assertOk()->assertExactJson(['encontrado' => false]);
    }

    public function test_falha_de_conexao_vira_nao_encontrado(): void
    {
        Http::fake(['brasilapi.com.br/*' => fn () => throw new ConnectionException('timeout')]);
        $this->logar();

        $this->getJson(route('consultas.cnpj', self::CNPJ))->assertOk()->assertExactJson(['encontrado' => false]);
    }

    public function test_cnpj_ja_cadastrado_nao_consulta_a_brasilapi(): void
    {
        Http::fake();
        Organizacao::factory()->create(['cnpj' => self::CNPJ]);
        $this->logar();

        $this->getJson(route('consultas.cnpj', self::CNPJ))->assertOk()->assertExactJson(['jaCadastrado' => true]);
        Http::assertNothingSent();
    }

    public function test_segunda_consulta_do_mesmo_cnpj_usa_o_cache(): void
    {
        Http::fake(['brasilapi.com.br/*' => Http::response($this->respostaBrasilApi())]);
        $this->logar();

        $this->getJson(route('consultas.cnpj', self::CNPJ))->assertJsonPath('encontrado', true);
        $this->getJson(route('consultas.cnpj', self::CNPJ))->assertJsonPath('encontrado', true);

        Http::assertSentCount(1);
    }

    public function test_cep_encontrado(): void
    {
        Http::fake(['viacep.com.br/*' => Http::response([
            'cep' => '40015-000', 'logradouro' => 'Rua das Flores', 'bairro' => 'Comércio',
            'localidade' => 'Salvador', 'uf' => 'BA',
        ])]);
        $this->logar();

        $this->getJson(route('consultas.cep', '40015000'))->assertOk()->assertExactJson([
            'encontrado' => true,
            'dados' => ['logradouro' => 'Rua das Flores', 'bairro' => 'Comércio', 'cidade' => 'Salvador', 'uf' => 'BA'],
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://viacep.com.br/ws/40015000/json/');
    }

    public function test_cep_com_erro_true_nao_e_encontrado(): void
    {
        Http::fake(['viacep.com.br/*' => Http::response(['erro' => true])]);
        $this->logar();

        $this->getJson(route('consultas.cep', '40015000'))->assertOk()->assertExactJson(['encontrado' => false]);
    }

    public function test_cep_com_falha_de_conexao_nao_e_encontrado(): void
    {
        Http::fake(['viacep.com.br/*' => fn () => throw new ConnectionException('timeout')]);
        $this->logar();

        $this->getJson(route('consultas.cep', '40015000'))->assertOk()->assertExactJson(['encontrado' => false]);
    }

    public function test_cep_sem_8_digitos_retorna_422(): void
    {
        Http::fake();
        $this->logar();

        $this->getJson(route('consultas.cep', '4001500'))->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_busca_de_municipios(): void
    {
        $this->logar();

        $nomes = array_column($this->getJson(route('consultas.municipios', ['busca' => 'salv']))->assertOk()->json(), 'nome');
        $this->assertContains('Salvador', $nomes);
    }

    public function test_busca_de_municipios_com_menos_de_2_caracteres_retorna_vazio(): void
    {
        $this->logar();

        $this->getJson(route('consultas.municipios', ['busca' => 's']))->assertOk()->assertExactJson([]);
        $this->getJson(route('consultas.municipios'))->assertOk()->assertExactJson([]);
    }

    public function test_consultas_exigem_login(): void
    {
        $this->get(route('consultas.cnpj', self::CNPJ))->assertRedirect('/');
        $this->get(route('consultas.cep', '40015000'))->assertRedirect('/');
        $this->get(route('consultas.municipios'))->assertRedirect('/');
    }

    public function test_usuario_da_retaguarda_e_redirecionado(): void
    {
        $this->actingAs(User::factory()->gestor()->create());

        $this->get(route('consultas.cnpj', self::CNPJ))->assertRedirect(route('retaguarda.inicio'));
    }
}
