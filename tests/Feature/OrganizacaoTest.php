<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\RepresentanteLegal;
use App\Models\User;
use App\Models\VinculoOrganizacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganizacaoTest extends TestCase
{
    use RefreshDatabase;

    private const string CNPJ = '11222333000181';

    private const string CPF_REPRESENTANTE = '52998224725';

    private const string OUTRO_CPF_REPRESENTANTE = '11144477735';

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function dadosValidos(array $alteracoes = []): array
    {
        return [
            'cnpj' => '11.222.333/0001-81',
            'razao_social' => 'Associação Cultural Axé Ltda',
            'natureza_juridica' => '2062',
            'municipio_ibge' => '2927408',
            'representantes' => [
                ['cpf' => '529.982.247-25', 'nome' => 'Maria Representante'],
            ],
            ...$alteracoes,
        ];
    }

    /** PNG de 1x1 pixel; o ambiente de testes não tem a extensão GD. */
    private function logoPng(string $nome): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    }

    private function usuario(): User
    {
        return User::factory()->cadastroCompleto()->create();
    }

    public function test_store_cria_organizacao_representantes_e_vinculo_responsavel(): void
    {
        $user = $this->usuario();

        $this->actingAs($user)->post('/organizacoes', $this->dadosValidos([
            'nome_fantasia' => 'Axé',
            'mei' => true,
            'finalidade_lucrativa' => false,
            'telefone' => '(71) 99876-5432',
            'cep' => '40000-000',
            'representantes' => [
                ['cpf' => '529.982.247-25', 'nome' => 'Maria', 'origem' => 'procuracao', 'tipo_documento' => 'rg'],
                ['cpf' => self::OUTRO_CPF_REPRESENTANTE, 'nome' => 'João'],
            ],
        ]))->assertRedirect(route('organizacoes.index'))
            ->assertSessionHas('sucesso', 'Pessoa Jurídica salva.');

        $organizacao = Organizacao::sole();
        $this->assertSame(self::CNPJ, $organizacao->cnpj);
        $this->assertTrue($organizacao->mei);
        $this->assertFalse($organizacao->finalidade_lucrativa);
        $this->assertSame('71998765432', $organizacao->telefone);
        $this->assertSame('40000000', $organizacao->cep);

        $representantes = $organizacao->representantes;
        $this->assertCount(2, $representantes);
        $this->assertSame(self::CPF_REPRESENTANTE, $representantes[0]->cpf);
        $this->assertSame(self::OUTRO_CPF_REPRESENTANTE, $representantes[1]->cpf);
        $this->assertLessThan($representantes[1]->ordem, $representantes[0]->ordem);

        $vinculo = VinculoOrganizacao::sole();
        $this->assertEquals($organizacao->id, $vinculo->organizacao_id);
        $this->assertSame($user->cpf, $vinculo->cpf);
        $this->assertSame($user->name, $vinculo->nome);
        $this->assertTrue($vinculo->responsavel);
    }

    public function test_cnpj_alfanumerico_em_minusculas_e_aceito_e_normalizado(): void
    {
        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos(['cnpj' => '12.abc.345/01de-35']))
            ->assertSessionHasNoErrors();

        $this->assertSame('12ABC34501DE35', Organizacao::sole()->cnpj);
    }

    public function test_cnpj_duplicado_recusa_sem_criar_nada(): void
    {
        Organizacao::factory()->create(['cnpj' => self::CNPJ]);

        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos())
            ->assertSessionHasErrors(['cnpj' => 'Esta Pessoa Jurídica já está cadastrada. Peça ao responsável por ela para vincular você.']);

        $this->assertDatabaseCount('organizacoes', 1);
        $this->assertDatabaseCount('representantes_legais', 0);
        $this->assertDatabaseCount('organizacao_vinculos', 0);
    }

    public function test_cnpj_invalido(): void
    {
        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos(['cnpj' => '11.222.333/0001-82']))
            ->assertSessionHasErrors(['cnpj' => 'CNPJ inválido. Confira os números e letras.']);

        $this->assertDatabaseCount('organizacoes', 0);
    }

    public function test_exige_pelo_menos_um_representante(): void
    {
        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos(['representantes' => []]))
            ->assertSessionHasErrors(['representantes' => 'Informe pelo menos um representante legal.']);

        $this->assertDatabaseCount('organizacoes', 0);
    }

    public function test_cpf_de_representante_repetido(): void
    {
        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos([
            'representantes' => [
                ['cpf' => '529.982.247-25', 'nome' => 'Maria'],
                ['cpf' => '52998224725', 'nome' => 'Maria de novo'],
            ],
        ]))->assertSessionHasErrors([
            'representantes.0.cpf' => 'Este CPF já está em outro representante.',
            'representantes.1.cpf' => 'Este CPF já está em outro representante.',
        ]);
    }

    public function test_cpf_de_representante_invalido(): void
    {
        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos([
            'representantes' => [['cpf' => '111.111.111-11', 'nome' => 'Maria']],
        ]))->assertSessionHasErrors('representantes.0.cpf');
    }

    public function test_origem_outro_exige_descricao(): void
    {
        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos([
            'representantes' => [['cpf' => self::CPF_REPRESENTANTE, 'nome' => 'Maria', 'origem' => 'outro']],
        ]))->assertSessionHasErrors('representantes.0.origem_descricao');

        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos([
            'representantes' => [['cpf' => self::CPF_REPRESENTANTE, 'nome' => 'Maria', 'origem' => 'outro', 'origem_descricao' => 'Termo de posse']],
        ]))->assertSessionHasNoErrors();
    }

    public function test_sem_numero_grava_numero_nulo(): void
    {
        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos(['numero' => '123', 'sem_numero' => true]))
            ->assertSessionHasNoErrors();

        $organizacao = Organizacao::sole();
        $this->assertNull($organizacao->numero);
        $this->assertTrue($organizacao->sem_numero);
    }

    public function test_campos_opcionais_vazios_gravam_nulo(): void
    {
        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos([
            'nome_fantasia' => '',
            'finalidade_lucrativa' => '',
            'inscricao_municipal' => '',
            'porte' => '',
            'data_fundacao' => '',
            'telefone' => '',
            'cep' => '',
            'logradouro' => '',
            'uf' => '',
        ]))->assertSessionHasNoErrors();

        $organizacao = Organizacao::sole();
        foreach (['nome_fantasia', 'finalidade_lucrativa', 'inscricao_municipal', 'porte', 'data_fundacao', 'telefone', 'cep', 'logradouro', 'uf', 'logo'] as $campo) {
            $this->assertNull($organizacao->{$campo}, $campo);
        }
        $this->assertFalse($organizacao->mei);
        $this->assertFalse($organizacao->sem_numero);
    }

    public function test_logo_e_gravada_no_disco_public(): void
    {
        Storage::fake('public');

        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos([
            'logo' => $this->logoPng('logo.png'),
        ]))->assertSessionHasNoErrors();

        $organizacao = Organizacao::sole();
        $this->assertStringStartsWith("organizacoes/{$organizacao->id}/", $organizacao->logo);
        Storage::disk('public')->assertExists($organizacao->logo);
    }

    public function test_logo_invalida_e_recusada(): void
    {
        Storage::fake('public');

        $this->actingAs($this->usuario())->post('/organizacoes', $this->dadosValidos([
            'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors('logo');
    }

    public function test_update_troca_a_logo_e_apaga_a_antiga(): void
    {
        Storage::fake('public');
        $user = $this->usuario();
        $organizacao = Organizacao::factory()->comResponsavel($user)->create();
        $this->actingAs($user)->post("/organizacoes/{$organizacao->id}", [
            '_method' => 'PUT',
            ...$this->dadosValidos(['logo' => $this->logoPng('a.png')]),
        ])->assertSessionHasNoErrors();
        $antiga = $organizacao->fresh()->logo;
        Storage::disk('public')->assertExists($antiga);

        $this->actingAs($user)->post("/organizacoes/{$organizacao->id}", [
            '_method' => 'PUT',
            ...$this->dadosValidos(['logo' => $this->logoPng('b.png')]),
        ])->assertSessionHasNoErrors();

        $nova = $organizacao->fresh()->logo;
        $this->assertNotSame($antiga, $nova);
        Storage::disk('public')->assertExists($nova);
        Storage::disk('public')->assertMissing($antiga);
    }

    public function test_update_por_vinculado_nao_responsavel_substitui_representantes(): void
    {
        $user = $this->usuario();
        $organizacao = Organizacao::factory()->create();
        VinculoOrganizacao::factory()->create(['organizacao_id' => $organizacao->id, 'cpf' => $user->cpf, 'nome' => $user->name, 'responsavel' => false]);
        RepresentanteLegal::factory()->create(['organizacao_id' => $organizacao->id, 'cpf' => self::OUTRO_CPF_REPRESENTANTE]);

        $this->actingAs($user)->put("/organizacoes/{$organizacao->id}", $this->dadosValidos(['razao_social' => 'Novo Nome']))
            ->assertRedirect(route('organizacoes.index'))
            ->assertSessionHas('sucesso', 'Pessoa Jurídica salva.');

        $this->assertSame('Novo Nome', $organizacao->fresh()->razao_social);
        $this->assertSame([self::CPF_REPRESENTANTE], $organizacao->representantes()->pluck('cpf')->all());
    }

    public function test_update_ignora_o_cnpj_enviado(): void
    {
        $user = $this->usuario();
        $organizacao = Organizacao::factory()->comResponsavel($user)->create(['cnpj' => self::CNPJ]);

        $this->actingAs($user)->put("/organizacoes/{$organizacao->id}", $this->dadosValidos(['cnpj' => '12ABC34501DE35']))
            ->assertSessionHasNoErrors();

        $this->assertSame(self::CNPJ, $organizacao->fresh()->cnpj);
    }

    public function test_update_e_edit_por_nao_vinculado_dao_403(): void
    {
        $organizacao = Organizacao::factory()->create();
        $user = $this->usuario();

        $this->actingAs($user)->put("/organizacoes/{$organizacao->id}", $this->dadosValidos())->assertForbidden();
        $this->actingAs($user)->get("/organizacoes/{$organizacao->id}/editar")->assertForbidden();
    }

    public function test_create_entrega_opcoes_e_representante_inicial(): void
    {
        $user = User::factory()->cadastroCompleto()->create(['cpf' => self::CPF_REPRESENTANTE, 'name' => 'Maria Gov']);

        $this->actingAs($user)->get('/cadastro/pessoa-juridica')->assertInertia(fn (Assert $page) => $page
            ->component('Organizacoes/Form')
            ->where('organizacao', null)
            ->where('representanteInicial', ['cpf' => '529.982.247-25', 'nome' => 'Maria Gov'])
            ->where('naturezas.0', fn ($natureza) => str_contains($natureza['rotulo'], ' · ') && isset($natureza['valor']))
            ->has('opcoes.portes')
            ->has('opcoes.tiposDocumento')
            ->has('opcoes.origens')
            ->has('opcoes.estados'));
    }

    public function test_edit_entrega_a_organizacao(): void
    {
        $user = $this->usuario();
        $organizacao = Organizacao::factory()->comResponsavel($user)->create(['cnpj' => self::CNPJ, 'municipio_ibge' => '2927408']);
        RepresentanteLegal::factory()->create(['organizacao_id' => $organizacao->id]);

        $this->actingAs($user)->get("/organizacoes/{$organizacao->id}/editar")->assertInertia(fn (Assert $page) => $page
            ->component('Organizacoes/Form')
            ->where('organizacao.cnpj', '11.222.333/0001-81')
            ->where('organizacao.municipio.codigo', '2927408')
            ->where('organizacao.logoUrl', null)
            ->has('organizacao.representantes', 1)
            ->where('representanteInicial', null));
    }

    public function test_create_sem_pessoa_fisica_completa_vai_para_o_cadastro(): void
    {
        $this->actingAs(User::factory()->create())->get('/cadastro/pessoa-juridica')->assertRedirect('/cadastro');
    }

    public function test_gestor_da_retaguarda_e_redirecionado(): void
    {
        $this->actingAs(User::factory()->gestor()->create())->get('/cadastro/pessoa-juridica')->assertRedirect('/retaguarda');
    }

    public function test_index_lista_so_organizacoes_vinculadas(): void
    {
        $user = $this->usuario();
        Organizacao::factory()->comResponsavel($user)->create(['razao_social' => 'Zeta', 'nome_fantasia' => null, 'cnpj' => self::CNPJ]);
        Organizacao::factory()->comResponsavel($user)->create(['razao_social' => 'Beta Ltda', 'nome_fantasia' => 'Alfa']);
        Organizacao::factory()->create(['razao_social' => 'De outra pessoa']);

        $this->actingAs($user)->get('/organizacoes')->assertInertia(fn (Assert $page) => $page
            ->component('Organizacoes/Index')
            ->has('organizacoes', 2)
            ->where('organizacoes.0.nome', 'Alfa')
            ->where('organizacoes.1.nome', 'Zeta')
            ->where('organizacoes.1.cnpj', '11.222.333/0001-81')
            ->where('organizacoes.1.responsavel', true)
            ->where('organizacoes.1.logoUrl', null));
    }
}
