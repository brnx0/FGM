<?php

namespace Tests\Feature;

use App\Enums\IdentidadeGenero;
use App\Enums\RacaCor;
use App\Enums\Territorio;
use App\Enums\TipoCadastro;
use App\Models\PessoaFisica;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CadastroTest extends TestCase
{
    use RefreshDatabase;

    public function test_cadastro_exige_login(): void
    {
        $this->get('/cadastro')->assertRedirect('/');
    }

    public function test_cadastro_mostra_nome_e_cpf_mascarado(): void
    {
        $user = User::factory()->create(['cpf' => '52998224725', 'name' => 'Maria Souza']);

        $this->actingAs($user)->get('/cadastro')->assertInertia(fn (Assert $page) => $page
            ->component('Cadastro/Completar')
            ->where('auth.user.nome', 'Maria Souza')
            ->where('auth.user.cpfMascarado', '***.982.247-**'));
    }

    public function test_formulario_traz_os_dados_do_govbr(): void
    {
        $user = User::factory()->create([
            'cpf' => '52998224725',
            'name' => 'Maria Souza',
            'email' => 'maria@example.com',
            'phone' => '(71) 99876-5432',
        ]);

        $this->actingAs($user)->get('/cadastro')->assertInertia(fn (Assert $page) => $page
            ->component('Cadastro/Completar')
            ->where('govbr', [
                'nome' => 'Maria Souza',
                'cpf' => '529.982.247-25',
                'email' => 'maria@example.com',
                'telefone' => '71998765432',
            ])
            ->where('cadastro', null));
    }

    public function test_formulario_traz_o_cadastro_ja_salvo_sem_os_caminhos_dos_arquivos(): void
    {
        $cadastro = PessoaFisica::factory()->create(['rg' => '1234567', 'territorio' => Territorio::Cajazeiras]);

        $this->actingAs($cadastro->user)->get('/cadastro')->assertInertia(fn (Assert $page) => $page
            ->where('cadastro.rg', '1234567')
            ->where('cadastro.territorio', 'cajazeiras')
            ->where('cadastro.arquivos.documento_cpf', true)
            ->where('cadastro.arquivos.foto_perfil', false)
            ->missing('cadastro.documento_cpf'));
    }

    public function test_salvar_cadastro_exige_login(): void
    {
        $this->post('/cadastro', $this->dadosValidos())->assertRedirect('/');

        $this->assertDatabaseCount('pessoas_fisicas', 0);
    }

    public function test_salva_o_cadastro_de_pessoa_fisica_e_os_arquivos(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email' => 'maria@example.com', 'phone' => '71998765432']);

        $this->actingAs($user)->post('/cadastro', $this->dadosValidos([
            'nome_artistico' => 'Maria do Samba',
            'telefone_adicional' => '(71) 3333-4444',
            'pcd' => true,
        ]))->assertRedirect(route('cadastro.organizacao'))->assertSessionHas('sucesso');

        $cadastro = $user->pessoaFisica()->sole();
        $this->assertSame('Maria do Samba', $cadastro->nome_artistico);
        $this->assertSame('7133334444', $cadastro->telefone_adicional);
        $this->assertSame('40020000', $cadastro->cep);
        $this->assertSame(Territorio::CentroBrotas, $cadastro->territorio);
        $this->assertTrue($cadastro->pcd);
        $this->assertFalse($cadastro->representa_coletivo);
        Storage::disk('local')->assertExists([$cadastro->documento_cpf, $cadastro->documento_rg]);
        $this->assertNull($cadastro->foto_perfil);
        $this->assertNotNull($user->fresh()->profile_completed_at);
    }

    public function test_email_e_telefone_do_govbr_nao_podem_ser_trocados_pelo_formulario(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email' => 'maria@example.com', 'phone' => '71998765432']);

        $this->actingAs($user)->post('/cadastro', $this->dadosValidos([
            'email' => 'outro@example.com',
            'telefone' => '(11) 91111-2222',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('maria@example.com', $user->pessoaFisica()->sole()->email);
        $this->assertSame('71998765432', $user->pessoaFisica()->sole()->telefone);
    }

    public function test_sem_email_e_telefone_do_govbr_eles_sao_pedidos_no_formulario(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['email' => null, 'phone' => null]);

        $this->actingAs($user)->post('/cadastro', $this->dadosValidos())
            ->assertSessionHasErrors(['email' => 'Este campo é obrigatório.', 'telefone' => 'Este campo é obrigatório.']);

        $this->actingAs($user)->post('/cadastro', $this->dadosValidos([
            'email' => 'maria@example.com',
            'telefone' => '(71) 99876-5432',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('maria@example.com', $user->pessoaFisica()->sole()->email);
        $this->assertSame('71998765432', $user->pessoaFisica()->sole()->telefone);
    }

    public function test_campos_obrigatorios_do_cadastro(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/cadastro', [])->assertSessionHasErrors(array_fill_keys([
            'pais_origem', 'rg', 'documento_cpf', 'documento_rg', 'identidade_genero', 'raca_cor',
            'cep', 'logradouro', 'bairro', 'cidade', 'estado', 'territorio',
        ], 'Este campo é obrigatório.'));

        $this->assertDatabaseCount('pessoas_fisicas', 0);
        $this->assertNull($user->fresh()->profile_completed_at);
    }

    /** @param array<string, mixed> $dados */
    #[DataProvider('dadosInvalidos')]
    public function test_recusa_dados_invalidos(array $dados, string $campo, string $mensagem): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['phone' => '71998765432']);

        $this->actingAs($user)->post('/cadastro', $this->dadosValidos($dados))
            ->assertSessionHasErrors([$campo => $mensagem]);
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function dadosInvalidos(): array
    {
        return [
            'arquivo acima de 2 MB' => [
                ['documento_cpf' => UploadedFile::fake()->create('cpf.pdf', 2049, 'application/pdf')],
                'documento_cpf', 'O arquivo deve ter no máximo 2 MB.',
            ],
            'documento que não é PDF nem imagem' => [
                ['documento_rg' => UploadedFile::fake()->create('rg.docx', 10)],
                'documento_rg', 'Envie um PDF ou uma imagem JPG ou PNG.',
            ],
            'foto que não é imagem' => [
                ['foto_perfil' => UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf')],
                'foto_perfil', 'Envie uma imagem JPG, PNG ou GIF.',
            ],
            'país fora da lista' => [['pais_origem' => 'Atlântida'], 'pais_origem', 'Selecione uma opção da lista.'],
            'território fora da lista' => [['territorio' => 'centro'], 'territorio', 'Selecione uma opção da lista.'],
            'estado fora da lista' => [['estado' => 'XX'], 'estado', 'Selecione uma opção da lista.'],
            'CEP incompleto' => [['cep' => '40020-00'], 'cep', 'Digite os 8 números do CEP.'],
            'telefone adicional sem DDD' => [['telefone_adicional' => '9999-8888'], 'telefone_adicional', 'Digite o telefone com DDD.'],
        ];
    }

    public function test_cadastro_salvo_nao_exige_reenviar_documentos_e_troca_o_arquivo_substituido(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cadastros/cpf-antigo.pdf', 'antigo');
        Storage::disk('local')->put('cadastros/rg-antigo.pdf', 'antigo');
        $cadastro = PessoaFisica::factory()->create([
            'documento_cpf' => 'cadastros/cpf-antigo.pdf',
            'documento_rg' => 'cadastros/rg-antigo.pdf',
        ]);

        $this->actingAs($cadastro->user)->post('/cadastro', [
            ...$this->dadosValidos(['rg' => '7654321', 'telefone' => '(71) 99876-5432']),
            'documento_cpf' => UploadedFile::fake()->create('cpf-novo.pdf', 10, 'application/pdf'),
            'documento_rg' => null,
        ])->assertSessionHasNoErrors();

        $cadastro->refresh();
        $this->assertSame('7654321', $cadastro->rg);
        $this->assertSame('cadastros/rg-antigo.pdf', $cadastro->documento_rg);
        Storage::disk('local')->assertMissing('cadastros/cpf-antigo.pdf');
        Storage::disk('local')->assertExists([$cadastro->documento_cpf, 'cadastros/rg-antigo.pdf']);
        $this->assertDatabaseCount('pessoas_fisicas', 1);
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     * @return array<string, mixed>
     */
    private function dadosValidos(array $alteracoes = []): array
    {
        return [
            'pais_origem' => 'Brasil',
            'rg' => '1234567',
            'documento_cpf' => UploadedFile::fake()->create('cpf.pdf', 100, 'application/pdf'),
            'documento_rg' => UploadedFile::fake()->create('rg.jpg', 100, 'image/jpeg'),
            'identidade_genero' => IdentidadeGenero::MulherCis->value,
            'raca_cor' => RacaCor::Preta->value,
            'cep' => '40020-000',
            'logradouro' => 'Rua Chile',
            'numero' => '10',
            'bairro' => 'Centro',
            'cidade' => 'Salvador',
            'estado' => 'BA',
            'territorio' => Territorio::CentroBrotas->value,
            ...$alteracoes,
        ];
    }

    public function test_tipos_de_cadastro_exigem_login(): void
    {
        $this->get('/cadastro/pessoa-juridica')->assertRedirect('/');
    }

    public function test_rota_antiga_de_pessoa_fisica_nao_existe(): void
    {
        $this->actingAs(User::factory()->create())->get('/cadastro/pessoa-fisica')->assertNotFound();
    }

    public function test_primeiro_salvamento_segue_para_a_etapa_da_organizacao(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['profile_completed_at' => null, 'email' => 'maria@example.com', 'phone' => '71998765432']);

        $this->actingAs($user)->post('/cadastro', $this->dadosValidos())
            ->assertRedirect(route('cadastro.organizacao'))
            ->assertSessionHas('sucesso', 'Dados salvos. Você também pode cadastrar uma Pessoa Jurídica.');
    }

    public function test_aviso_do_middleware_chega_a_tela_de_dados(): void
    {
        $user = User::factory()->create(['profile_completed_at' => null]);

        $this->actingAs($user)->followingRedirects()->get('/cadastro/organizacao')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cadastro/Completar')
                ->where('flash.aviso', 'Complete seus dados de Pessoa Física antes de cadastrar uma Pessoa Jurídica.'));
    }

    public function test_primeiro_salvamento_mostra_o_sucesso_na_etapa_da_organizacao(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['profile_completed_at' => null, 'email' => 'maria@example.com', 'phone' => '71998765432']);

        $this->actingAs($user)->followingRedirects()->post('/cadastro', $this->dadosValidos())
            ->assertInertia(fn (Assert $page) => $page
                ->component('Cadastro/Organizacao')
                ->where('flash.sucesso', 'Dados salvos. Você também pode cadastrar uma Pessoa Jurídica.'));
    }

    public function test_salvamento_com_cadastro_finalizado_volta_para_o_cadastro(): void
    {
        Storage::fake('local');
        $cadastro = PessoaFisica::factory()->create();
        $cadastro->user->forceFill(['profile_completed_at' => now(), 'tipos_cadastro' => [TipoCadastro::Proponente], 'email' => 'maria@example.com', 'phone' => '71998765432'])->save();

        $this->actingAs($cadastro->user)->post('/cadastro', [
            ...$this->dadosValidos(),
            'documento_cpf' => null,
            'documento_rg' => null,
        ])->assertRedirect(route('cadastro'))->assertSessionMissing('sucesso');
    }

    public function test_salvamento_sem_tipo_de_cadastro_segue_para_a_etapa_da_organizacao(): void
    {
        Storage::fake('local');
        $cadastro = PessoaFisica::factory()->create();
        $cadastro->user->forceFill(['profile_completed_at' => now(), 'email' => 'maria@example.com', 'phone' => '71998765432'])->save();

        $this->actingAs($cadastro->user)->post('/cadastro', [
            ...$this->dadosValidos(),
            'documento_cpf' => null,
            'documento_rg' => null,
        ])->assertRedirect(route('cadastro.organizacao'));
    }

    public function test_etapa_da_organizacao_abre_com_a_pessoa_fisica_completa(): void
    {
        $user = User::factory()->create(['profile_completed_at' => now()]);

        $this->actingAs($user)->get('/cadastro/organizacao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Cadastro/Organizacao'));
    }

    public function test_etapa_da_organizacao_exige_pessoa_fisica_completa(): void
    {
        $user = User::factory()->create(['profile_completed_at' => null]);

        $this->actingAs($user)->get('/cadastro/organizacao')
            ->assertRedirect(route('cadastro'))
            ->assertSessionHas('aviso', 'Complete seus dados de Pessoa Física antes de cadastrar uma Pessoa Jurídica.');
    }

    public function test_etapa_da_organizacao_exige_login(): void
    {
        $this->get('/cadastro/organizacao')->assertRedirect('/');
    }

    public function test_etapa_da_organizacao_bloqueia_usuario_da_retaguarda(): void
    {
        $gestor = User::factory()->gestor()->create(['profile_completed_at' => now()]);

        $this->actingAs($gestor)->get('/cadastro/organizacao')
            ->assertRedirect('/retaguarda')
            ->assertSessionHas('erro');
    }

    public function test_logout_encerra_sessao(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
