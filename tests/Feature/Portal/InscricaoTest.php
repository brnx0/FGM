<?php

namespace Tests\Feature\Portal;

use App\Enums\EtapaDocumento;
use App\Enums\ModalidadeCota;
use App\Enums\NaturezaProponente;
use App\Enums\SituacaoInscricao;
use App\Enums\TipoPergunta;
use App\Http\Controllers\InscricaoController;
use App\Http\Controllers\PortalController;
use App\Models\ConfiguracaoIa;
use App\Models\Edital;
use App\Models\EditalCategoria;
use App\Models\EditalChamada;
use App\Models\EditalDocumento;
use App\Models\EditalPergunta;
use App\Models\EditalQuestionarioSecao;
use App\Models\Inscricao;
use App\Models\InscricaoDocumento;
use App\Models\InscricaoResposta;
use App\Models\Organizacao;
use App\Models\PessoaFisica;
use App\Models\User;
use App\Models\VinculoOrganizacao;
use App\Support\Inscricao\PassosDaInscricao;
use App\Support\Inscricao\RegrasDaInscricao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InscricaoTest extends TestCase
{
    use RefreshDatabase;

    private const string CPF = '52998224725';

    private const string CNPJ = '11222333000181';

    private const string LIMITE_ATINGIDO = 'Este cadastro já atingiu o limite de inscrições deste edital.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'America/Bahia'));

        // Validação por IA ligada (a chave da Integração com IA); os testes com ela desligada a desligam.
        ConfiguracaoIa::factory()->create();
    }

    public function test_cria_rascunho_com_as_escolhas(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto(['cotas' => ['negros' => ['ativa' => true], 'pcd' => ['ativa' => true]]]);
        $categoria = EditalCategoria::factory()->for($edital)->create();
        $organizacao = Organizacao::factory()->comResponsavel($user)->create([
            'cnpj' => self::CNPJ, 'mei' => false, 'finalidade_lucrativa' => true,
        ]);

        $resposta = $this->actingAs($user)->post('/portal/editais/pnab/inscricoes', [
            'cadastro' => "organizacao:{$organizacao->id}",
            'categoria' => $categoria->id,
            'cotas' => ['negros', 'pcd'],
        ]);

        $inscricao = Inscricao::sole();
        $resposta->assertRedirect(route('portal.inscricoes.show', $inscricao));
        $this->assertSame((int) $user->id, (int) $inscricao->user_id);
        $this->assertSame((int) $edital->id, (int) $inscricao->edital_id);
        $this->assertSame((int) $edital->chamadas()->sole()->id, (int) $inscricao->edital_chamada_id);
        $this->assertSame((int) $organizacao->id, (int) $inscricao->organizacao_id);
        $this->assertSame(self::CNPJ, $inscricao->documento_cadastro);
        $this->assertSame(NaturezaProponente::ComFins, $inscricao->natureza);
        $this->assertSame((int) $categoria->id, (int) $inscricao->edital_categoria_id);
        $this->assertSame([ModalidadeCota::Negros, ModalidadeCota::Pcd], $inscricao->cotas->all());
        $this->assertSame(SituacaoInscricao::Rascunho, $inscricao->situacao);
        $this->assertNull($inscricao->numero);
    }

    public function test_categoria_em_todas_as_chamadas_inscreve_na_chamada_aberta(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto();
        $aberta = $edital->chamadas()->sole();
        // Uma chamada encerrada antes da aberta e outra depois: vale a aberta, não a primeira.
        EditalChamada::factory()->for($edital)->create(['inicio' => '2026-10-01', 'fim' => '2026-10-15', 'ordem' => 0]);
        EditalChamada::factory()->for($edital)->create(['inicio' => '2026-12-01', 'fim' => '2026-12-15', 'ordem' => 2]);
        $categoria = EditalCategoria::factory()->for($edital)->create(['edital_chamada_id' => null]);

        $this->actingAs($user)->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica', 'categoria' => $categoria->id])
            ->assertRedirect(route('portal.inscricoes.show', Inscricao::sole()));

        $this->assertSame((int) $aberta->id, (int) Inscricao::sole()->edital_chamada_id);
    }

    public function test_inscricao_de_edital_sem_datas_abre_sem_o_periodo(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto();
        $inscricao = $this->rascunho($edital, $user);
        $edital->update(['inscricoes_inicio' => null, 'inscricoes_fim' => null]);

        $this->actingAs($user)->get(route('portal.inscricoes.show', $inscricao))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Portal/InscricaoPassos')->where('edital.periodo', null));
    }

    public function test_pessoa_fisica_se_inscreve_sem_categoria_nem_cota_quando_o_edital_nao_tem(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $this->editalAberto();

        $this->actingAs($user)->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica'])
            ->assertRedirect(route('portal.inscricoes.show', Inscricao::sole()));

        $inscricao = Inscricao::sole();
        $this->assertNull($inscricao->organizacao_id);
        $this->assertSame(self::CPF, $inscricao->documento_cadastro);
        $this->assertSame(NaturezaProponente::PessoaFisica, $inscricao->natureza);
        $this->assertNull($inscricao->edital_categoria_id);
        $this->assertSame([], $inscricao->cotas->all());
    }

    public function test_cadastro_de_natureza_nao_aceita_e_recusado(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create();
        $this->editalAberto(['naturezas' => [NaturezaProponente::PessoaFisica]]);
        $organizacao = Organizacao::factory()->comResponsavel($user)->create(['mei' => false, 'finalidade_lucrativa' => true]);

        $this->actingAs($user)->post('/portal/editais/pnab/inscricoes', ['cadastro' => "organizacao:{$organizacao->id}"])
            ->assertSessionHasErrors(['cadastro' => 'Este cadastro não pode se inscrever neste edital.']);

        $this->assertSame(0, Inscricao::count());
    }

    public function test_organizacao_sem_ser_responsavel_e_recusada(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $this->editalAberto();
        $organizacao = Organizacao::factory()->create();
        VinculoOrganizacao::factory()->for($organizacao)->create(['cpf' => self::CPF, 'responsavel' => false]);

        $this->actingAs($user)->post('/portal/editais/pnab/inscricoes', ['cadastro' => "organizacao:{$organizacao->id}"])
            ->assertSessionHasErrors(['cadastro' => 'Escolha um dos seus cadastros.']);

        $this->assertSame(0, Inscricao::count());
    }

    public function test_limite_por_cnpj_conta_inscricao_de_outro_responsavel(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $outro = User::factory()->proponente()->comPerfil()->create();
        $edital = $this->editalAberto(['inscricoes_por_proponente' => 1]);
        $organizacao = Organizacao::factory()->comResponsavel($user)->create(['cnpj' => self::CNPJ]);
        Inscricao::factory()->for($edital)->for($outro)->create(['documento_cadastro' => self::CNPJ]);

        $this->actingAs($user)->post('/portal/editais/pnab/inscricoes', ['cadastro' => "organizacao:{$organizacao->id}"])
            ->assertSessionHasErrors(['cadastro' => self::LIMITE_ATINGIDO]);

        $this->assertSame(1, Inscricao::count());

        // A pessoa física do mesmo usuário é outro cadastro e continua livre.
        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica'])->assertSessionHasNoErrors();
        $this->assertSame(2, Inscricao::count());
    }

    public function test_trocar_para_cadastro_que_ja_atingiu_o_limite_e_recusado(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $outro = User::factory()->proponente()->comPerfil()->create();
        $edital = $this->editalAberto(['inscricoes_por_proponente' => 1]);
        $organizacao = Organizacao::factory()->comResponsavel($user)->create(['cnpj' => self::CNPJ]);
        Inscricao::factory()->for($edital)->for($outro)->create(['documento_cadastro' => self::CNPJ]);
        $inscricao = $this->rascunho($edital, $user);

        $this->actingAs($user)->put(route('portal.inscricoes.update', $inscricao), ['cadastro' => "organizacao:{$organizacao->id}"])
            ->assertSessionHasErrors(['cadastro' => self::LIMITE_ATINGIDO]);

        $this->assertNull($inscricao->fresh()->organizacao_id);

        // Salvar o mesmo cadastro de novo não conta a inscrição contra si mesma.
        $this->put(route('portal.inscricoes.update', $inscricao), ['cadastro' => 'pessoa_fisica'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('portal.inscricoes.show', [$inscricao, 'passo' => 'documentos']));
    }

    public function test_outro_responsavel_ve_a_situacao_da_inscricao_do_cnpj_sem_abrir(): void
    {
        $dono = User::factory()->proponente()->comPerfil()->create();
        $outro = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto();
        Organizacao::factory()->comResponsavel($outro)->create(['cnpj' => self::CNPJ]);
        $inscricao = Inscricao::factory()->for($edital)->for($dono)->finalizada()->create(['documento_cadastro' => self::CNPJ]);

        $this->actingAs($outro)->get('/portal/editais/pnab/inscricao')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('inscricoes', 0)
            ->where('cadastros.0.inscricaoDeOutro', null)
            ->where('cadastros.1.inscricaoDeOutro', 'finalizada'));

        $this->get(route('portal.inscricoes.show', $inscricao))->assertNotFound();
    }

    public function test_pagina_da_inscricao_traz_as_opcoes_e_as_inscricoes_do_usuario(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF, 'name' => 'Maria Souza']);
        $edital = $this->editalAberto([
            'inscricoes_por_proponente' => 1,
            'cotas' => [
                'negros' => ['ativa' => true], 'indigenas' => ['ativa' => true], 'pcd' => ['ativa' => false],
                'veda_negro_indigena' => true,
            ],
        ]);
        $livre = EditalCategoria::factory()->for($edital)->create(['nome' => 'Livre', 'naturezas' => null, 'ordem' => 1]);
        EditalCategoria::factory()->for($edital)->create(['nome' => 'Só MEI', 'naturezas' => [NaturezaProponente::Mei], 'ordem' => 2]);
        Organizacao::factory()->comResponsavel($user)->create([
            'cnpj' => self::CNPJ, 'razao_social' => 'Coletivo Alfa', 'mei' => false, 'finalidade_lucrativa' => false,
        ]);
        $inscricao = $this->rascunho($edital, $user, ['edital_categoria_id' => $livre->id]);

        $this->actingAs($user)->get('/portal/editais/pnab/inscricao')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Portal/Inscricao')
            ->where('edital.slug', 'pnab')
            ->has('inscricoes', 1)
            ->where('inscricoes.0', [
                'id' => (int) $inscricao->id,
                'numero' => null,
                'situacao' => 'rascunho',
                'situacaoRotulo' => 'Rascunho',
                'cadastro' => 'Maria Souza',
                'categoria' => 'Livre',
            ])
            ->has('cadastros', 2)
            ->where('cadastros.0', [
                'chave' => 'pessoa_fisica',
                'nome' => 'Maria Souza',
                'documento' => '529.982.247-25',
                'natureza' => 'pessoa_fisica',
                'naturezaRotulo' => 'Pessoa Física',
                'aceito' => true,
                'limiteAtingido' => true,
                'inscricaoDeOutro' => null,
            ])
            ->where('cadastros.1.nome', 'Coletivo Alfa')
            ->where('cadastros.1.documento', '11.222.333/0001-81')
            ->where('cadastros.1.natureza', 'sem_fins')
            ->where('cadastros.1.limiteAtingido', false)
            ->has('categorias', 2)
            ->where('categorias.0', ['id' => (int) $livre->id, 'nome' => 'Livre', 'naturezas' => []])
            ->where('categorias.1.naturezas', ['mei'])
            ->where('cotas', [
                ['valor' => 'negros', 'rotulo' => 'Pessoas negras'],
                ['valor' => 'indigenas', 'rotulo' => 'Pessoas indígenas'],
            ])
            ->where('vedaNegroIndigena', true));
    }

    public function test_limite_por_chamada_so_trava_o_cadastro_quando_todas_as_chamadas_estao_cheias(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto(['inscricoes_por_proponente' => 1, 'limite_por_chamada' => true]);
        $segunda = EditalChamada::factory()->for($edital)->create(['inicio' => '2026-11-01', 'fim' => '2026-11-15', 'ordem' => 2]);
        $this->rascunho($edital, $user);

        $this->actingAs($user)->get('/portal/editais/pnab/inscricao')->assertInertia(fn (Assert $p) => $p
            ->where('cadastros.0.limiteAtingido', false));

        $this->rascunho($edital, $user, ['edital_chamada_id' => $segunda->id]);

        $this->get('/portal/editais/pnab/inscricao')->assertInertia(fn (Assert $p) => $p
            ->where('cadastros.0.limiteAtingido', true));
    }

    public function test_categoria_e_obrigatoria_e_precisa_ser_do_edital_e_da_natureza(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create();
        $edital = $this->editalAberto();
        $soMei = EditalCategoria::factory()->for($edital)->create(['naturezas' => [NaturezaProponente::Mei]]);
        $livre = EditalCategoria::factory()->for($edital)->create(['naturezas' => null]);
        $deOutroEdital = EditalCategoria::factory()->create();

        $this->actingAs($user);
        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica'])
            ->assertSessionHasErrors(['categoria' => 'Escolha a categoria.']);
        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica', 'categoria' => $soMei->id])
            ->assertSessionHasErrors('categoria');
        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica', 'categoria' => $deOutroEdital->id])
            ->assertSessionHasErrors('categoria');
        $this->assertSame(0, Inscricao::count());

        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica', 'categoria' => $livre->id])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Inscricao::count());
    }

    public function test_cotas_nao_oferecidas_ou_vedadas_sao_recusadas(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create();
        $this->editalAberto(['cotas' => [
            'negros' => ['ativa' => true], 'indigenas' => ['ativa' => true], 'pcd' => ['ativa' => false],
            'veda_negro_indigena' => true,
        ]]);

        $this->actingAs($user);
        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica', 'cotas' => ['pcd']])
            ->assertSessionHasErrors(['cotas' => RegrasDaInscricao::ERRO_COTA_NAO_OFERECIDA]);
        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica', 'cotas' => ['qualquer']])
            ->assertSessionHasErrors(['cotas' => RegrasDaInscricao::ERRO_COTA_NAO_OFERECIDA]);
        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica', 'cotas' => ['negros', 'indigenas']])
            ->assertSessionHasErrors(['cotas' => RegrasDaInscricao::ERRO_NEGRO_E_INDIGENA]);
        $this->assertSame(0, Inscricao::count());
    }

    public function test_abre_a_inscricao_com_a_lista_de_documentos_exigidos(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto();
        $categoria = EditalCategoria::factory()->for($edital)->create(['nome' => 'Livre']);
        $outraCategoria = EditalCategoria::factory()->for($edital)->create();
        EditalDocumento::factory()->for($edital)->create([
            'nome' => 'Ficha de inscrição', 'orientacao' => 'Preencha tudo.', 'obrigatorio' => true, 'ordem' => 1,
            'instrucao_ia' => 'Confira a assinatura.',
        ]);
        EditalDocumento::factory()->for($edital)->create(['nome' => 'Laudo', 'etapa' => EtapaDocumento::Habilitacao, 'obrigatorio' => false, 'ordem' => 2]);
        EditalDocumento::factory()->for($edital)->create(['nome' => 'Contrato social', 'naturezas' => [NaturezaProponente::Mei], 'ordem' => 3]);
        EditalDocumento::factory()->for($edital)->create(['nome' => 'De outra categoria', 'todas_categorias' => false, 'ordem' => 4])
            ->categorias()->attach($outraCategoria);
        $inscricao = $this->rascunho($edital, $user, ['edital_categoria_id' => $categoria->id]);

        $resposta = $this->actingAs($user)->get(route('portal.inscricoes.show', $inscricao));

        // Sem respostas, vai um objeto vazio, não uma lista.
        $this->assertStringContainsString('"respostas":{}', (string) json_encode($resposta->viewData('page')['props']));
        $resposta->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Portal/InscricaoPassos')
            ->where('edital.slug', 'pnab')
            ->where('inscricao.id', (int) $inscricao->id)
            ->where('inscricao.situacao', 'rascunho')
            ->where('inscricao.cadastro', $user->name)
            ->where('inscricao.cadastroChave', 'pessoa_fisica')
            ->where('inscricao.categoriaId', (int) $categoria->id)
            ->where('inscricao.cotas', [])
            ->where('inscricao.fimDoPeriodo', '15/11/2026')
            ->where('podeEditar', true)
            ->has('documentos', 2)
            ->where('documentos.0', [
                'id' => (int) EditalDocumento::where('nome', 'Ficha de inscrição')->value('id'),
                'nome' => 'Ficha de inscrição',
                'orientacao' => 'Preencha tudo.',
                'etapa' => 'inscricao',
                'etapaRotulo' => 'Inscrição',
                'obrigatorio' => true,
                'modeloUrl' => null,
                'modeloNome' => null,
                'checagem' => 'aceita_sem_validar',
                'enviado' => null,
            ])
            ->where('documentos.1.nome', 'Laudo')
            ->where('documentos.1.etapa', 'habilitacao')
            ->where('documentos.1.obrigatorio', false)
            ->has('cadastros', 1)
            ->has('categorias', 2));
    }

    public function test_trocar_categoria_descarta_documentos_e_arquivos_fora_da_lista(): void
    {
        Storage::fake('local');
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto();
        $a = EditalCategoria::factory()->for($edital)->create(['ordem' => 1]);
        $b = EditalCategoria::factory()->for($edital)->create(['ordem' => 2]);
        $daA = EditalDocumento::factory()->for($edital)->create(['todas_categorias' => false, 'ordem' => 1]);
        $daA->categorias()->attach($a);
        $geral = EditalDocumento::factory()->for($edital)->create(['ordem' => 2]);
        $inscricao = $this->rascunho($edital, $user, ['edital_categoria_id' => $a->id]);
        foreach ([$daA, $geral] as $documento) {
            $caminho = "inscricoes/{$inscricao->id}/{$documento->id}.pdf";
            Storage::disk('local')->put($caminho, 'pdf');
            InscricaoDocumento::factory()->for($inscricao)->for($documento, 'documento')->create(['arquivo' => $caminho]);
        }

        $this->actingAs($user)->put(route('portal.inscricoes.update', $inscricao), ['cadastro' => 'pessoa_fisica', 'categoria' => $b->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('portal.inscricoes.show', [$inscricao, 'passo' => 'documentos']));

        $this->assertSame((int) $b->id, (int) $inscricao->fresh()->edital_categoria_id);
        $restante = InscricaoDocumento::sole();
        $this->assertSame((int) $geral->id, (int) $restante->edital_documento_id);
        Storage::disk('local')->assertMissing("inscricoes/{$inscricao->id}/{$daA->id}.pdf");
        Storage::disk('local')->assertExists("inscricoes/{$inscricao->id}/{$geral->id}.pdf");
    }

    public function test_finalizar_entre_a_leitura_e_a_gravacao_nao_troca_as_escolhas_nem_descarta_documentos(): void
    {
        Storage::fake('local');
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto(['cotas' => ['pcd' => ['ativa' => true]]]);
        $a = EditalCategoria::factory()->for($edital)->create(['ordem' => 1]);
        $b = EditalCategoria::factory()->for($edital)->create(['ordem' => 2]);
        $daA = EditalDocumento::factory()->for($edital)->create(['todas_categorias' => false, 'ordem' => 1]);
        $daA->categorias()->attach($a);
        $inscricao = $this->rascunho($edital, $user, ['edital_categoria_id' => $a->id]);
        $caminho = "inscricoes/{$inscricao->id}/{$daA->id}.pdf";
        Storage::disk('local')->put($caminho, 'pdf');
        InscricaoDocumento::factory()->for($inscricao)->for($daA, 'documento')->create(['arquivo' => $caminho]);

        // Outra aba finaliza logo depois de a requisição ler o rascunho (a leitura da rota), antes da transação.
        $finalizou = false;
        Inscricao::retrieved(function (Inscricao $lida) use (&$finalizou): void {
            if ($finalizou) {
                return;
            }
            $finalizou = true;
            Inscricao::query()->whereKey($lida->id)->update([
                'situacao' => SituacaoInscricao::Finalizada->value, 'numero' => 'INS-00000001', 'finalizada_em' => now(),
            ]);
        });

        $this->actingAs($user)->put(route('portal.inscricoes.update', $inscricao), [
            'cadastro' => 'pessoa_fisica', 'categoria' => $b->id, 'cotas' => ['pcd'],
        ])
            ->assertRedirect(route('portal.inscricoes.show', $inscricao))
            ->assertSessionHas('erro', InscricaoController::ERRO_REABRIR);

        $this->assertTrue($finalizou);
        $inscricao->refresh();
        $this->assertSame(SituacaoInscricao::Finalizada, $inscricao->situacao);
        $this->assertSame((int) $a->id, (int) $inscricao->edital_categoria_id);
        $this->assertEmpty($inscricao->cotas);
        $this->assertSame(1, InscricaoDocumento::count());
        Storage::disk('local')->assertExists($caminho);
    }

    public function test_trocar_para_categoria_de_chamada_encerrada_nao_grava_nem_descarta_documentos(): void
    {
        Storage::fake('local');
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto();
        $encerrada = EditalChamada::factory()->for($edital)->create(['inicio' => '2026-10-01', 'fim' => '2026-11-05', 'ordem' => 2]);
        $a = EditalCategoria::factory()->for($edital)->create(['edital_chamada_id' => $edital->chamadas()->first()->id, 'ordem' => 1]);
        $b = EditalCategoria::factory()->for($edital)->create(['edital_chamada_id' => $encerrada->id, 'ordem' => 2]);
        $daA = EditalDocumento::factory()->for($edital)->create(['todas_categorias' => false, 'ordem' => 1]);
        $daA->categorias()->attach($a);
        $inscricao = $this->rascunho($edital, $user, ['edital_categoria_id' => $a->id]);
        $caminho = "inscricoes/{$inscricao->id}/{$daA->id}.pdf";
        Storage::disk('local')->put($caminho, 'pdf');
        InscricaoDocumento::factory()->for($inscricao)->for($daA, 'documento')->create(['arquivo' => $caminho]);

        $this->actingAs($user)->put(route('portal.inscricoes.update', $inscricao), ['cadastro' => 'pessoa_fisica', 'categoria' => $b->id])
            ->assertRedirect(route('portal.editais'))
            ->assertSessionHas('aviso', PortalController::AVISO_INSCRICOES_FECHADAS);

        $this->assertSame((int) $a->id, (int) $inscricao->fresh()->edital_categoria_id);
        $this->assertSame(1, InscricaoDocumento::count());
        Storage::disk('local')->assertExists($caminho);
    }

    public function test_trocar_a_cota_e_o_cadastro_do_rascunho(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto(['cotas' => ['negros' => ['ativa' => true], 'pcd' => ['ativa' => true]]]);
        $organizacao = Organizacao::factory()->comResponsavel($user)->create(['cnpj' => self::CNPJ, 'mei' => true]);
        $inscricao = $this->rascunho($edital, $user);

        $this->actingAs($user)->put(route('portal.inscricoes.update', $inscricao), [
            'cadastro' => "organizacao:{$organizacao->id}", 'cotas' => ['pcd'],
        ])->assertSessionHasNoErrors();

        $inscricao->refresh();
        $this->assertSame((int) $organizacao->id, (int) $inscricao->organizacao_id);
        $this->assertSame(self::CNPJ, $inscricao->documento_cadastro);
        $this->assertSame(NaturezaProponente::Mei, $inscricao->natureza);
        $this->assertSame([ModalidadeCota::Pcd], $inscricao->cotas->all());
    }

    public function test_inscricao_finalizada_nao_troca_as_escolhas(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto(['cotas' => ['pcd' => ['ativa' => true]]]);
        $inscricao = Inscricao::factory()->for($edital)->for($user)->finalizada()->create([
            'edital_chamada_id' => $edital->chamadas()->sole()->id, 'documento_cadastro' => self::CPF,
        ]);

        $this->actingAs($user)->put(route('portal.inscricoes.update', $inscricao), ['cadastro' => 'pessoa_fisica', 'cotas' => ['pcd']])
            ->assertRedirect(route('portal.inscricoes.show', $inscricao))
            ->assertSessionHas('erro', 'Reabra a inscrição para alterar as escolhas.');

        $this->assertEmpty($inscricao->fresh()->cotas);
    }

    public function test_inscricao_de_outro_usuario_da_404(): void
    {
        $dono = User::factory()->proponente()->comPerfil()->create();
        $outro = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto();
        $inscricao = $this->rascunho($edital, $dono);

        $this->actingAs($outro);
        $this->get(route('portal.inscricoes.show', $inscricao))->assertNotFound();
        $this->put(route('portal.inscricoes.update', $inscricao), ['cadastro' => 'pessoa_fisica'])->assertNotFound();

        $this->assertSame((int) $dono->id, (int) $inscricao->fresh()->user_id);
        $this->assertSame($dono->cpf, $inscricao->fresh()->documento_cadastro);
    }

    public function test_inscricao_inexistente_ou_id_nao_numerico_da_404(): void
    {
        $this->actingAs(User::factory()->proponente()->comPerfil()->create());

        $this->get('/portal/inscricoes/999999')->assertNotFound();
        $this->get('/portal/inscricoes/abc')->assertNotFound();
    }

    public function test_fora_do_periodo_nao_cria_nem_edita(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $encerrado = $this->editalAberto(['slug' => 'encerrado'], '2026-10-01', '2026-11-09');
        $categoria = EditalCategoria::factory()->for($encerrado)->create();
        $inscricao = $this->rascunho($encerrado, $user);

        $this->actingAs($user);
        $this->post('/portal/editais/encerrado/inscricoes', ['cadastro' => 'pessoa_fisica', 'categoria' => $categoria->id])
            ->assertRedirect(route('portal.editais'))
            ->assertSessionHas('aviso', PortalController::AVISO_INSCRICOES_FECHADAS);
        $this->assertSame(1, Inscricao::count());

        $this->put(route('portal.inscricoes.update', $inscricao), ['cadastro' => 'pessoa_fisica', 'categoria' => $categoria->id])
            ->assertRedirect(route('portal.editais'))
            ->assertSessionHas('aviso', PortalController::AVISO_INSCRICOES_FECHADAS);
        $this->assertNull($inscricao->fresh()->edital_categoria_id);
    }

    public function test_nao_cria_em_edital_que_o_portal_nao_mostra(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create();
        $this->editalAberto(['publicado' => false]);

        $this->actingAs($user)->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica'])->assertNotFound();
        $this->assertSame(0, Inscricao::count());
    }

    public function test_patrocinador_e_visitante_nao_criam_nem_abrem(): void
    {
        $edital = $this->editalAberto();
        $dono = User::factory()->proponente()->comPerfil()->create();
        $inscricao = $this->rascunho($edital, $dono);

        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica'])->assertRedirect('/');
        $this->get(route('portal.inscricoes.show', $inscricao))->assertRedirect('/');

        $this->actingAs(User::factory()->patrocinador()->create(['cpf' => self::CPF]));
        $this->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica'])->assertRedirect(route('portal.projetos'));
        $this->get(route('portal.inscricoes.show', $inscricao))->assertRedirect(route('portal.projetos'));
        $this->assertSame(1, Inscricao::count());
    }

    public function test_perfil_incompleto_nao_cria_inscricao_e_leva_ao_cadastro(): void
    {
        $user = User::factory()->proponente()->create(['cpf' => self::CPF]);
        PessoaFisica::factory()->perfilIncompleto()->for($user)->create();
        $this->editalAberto();

        $this->actingAs($user)->post('/portal/editais/pnab/inscricoes', ['cadastro' => 'pessoa_fisica'])
            ->assertRedirect(route('cadastro'))
            ->assertSessionHas('aviso', InscricaoController::AVISO_PERFIL_INCOMPLETO);

        $this->assertSame(0, Inscricao::count());
    }

    public function test_rascunho_com_perfil_incompleto_abre_com_o_aviso(): void
    {
        $user = User::factory()->proponente()->create(['cpf' => self::CPF]);
        PessoaFisica::factory()->perfilIncompleto()->for($user)->create();
        $inscricao = $this->rascunho($this->editalAberto(), $user);

        $this->actingAs($user)->get(route('portal.inscricoes.show', $inscricao))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Portal/InscricaoPassos')
            ->where('perfilIncompleto', true)
            ->where('podeEditar', true));
    }

    public function test_show_traz_passos_secoes_respostas_e_o_passo_atual(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto();
        $teatro = EditalCategoria::factory()->for($edital)->create(['ordem' => 1]);
        $danca = EditalCategoria::factory()->for($edital)->create(['ordem' => 2]);
        $proposta = EditalQuestionarioSecao::factory()->for($edital)->create(['titulo' => 'Proposta', 'descricao' => 'Sobre o projeto.', 'ordem' => 1]);
        $soDaDanca = EditalQuestionarioSecao::factory()->for($edital)->create(['titulo' => 'Dança', 'ordem' => 2]);
        $linguagem = EditalPergunta::factory()->for($edital)->escolha(TipoPergunta::EscolhaUnica, ['Teatro', 'Circo'], comOutro: true)->create([
            'edital_questionario_secao_id' => $proposta->id, 'enunciado' => 'Linguagem?', 'ajuda' => 'A principal.', 'ordem' => 1,
        ]);
        $circo = EditalPergunta::factory()->for($edital)->condicionadaA($linguagem, ['o2'])->create([
            'edital_questionario_secao_id' => $proposta->id, 'enunciado' => 'Qual lona?', 'ordem' => 2,
        ]);
        $acordo = EditalPergunta::factory()->for($edital)->declaracao()->create([
            'edital_questionario_secao_id' => $proposta->id, 'enunciado' => 'Declaro.', 'obrigatoria' => false, 'ordem' => 3,
        ]);
        $resumo = EditalPergunta::factory()->for($edital)->create([
            'edital_questionario_secao_id' => $proposta->id, 'enunciado' => 'Resumo', 'obrigatoria' => false, 'limite_caracteres' => 500, 'ordem' => 4,
        ]);
        $daDanca = EditalPergunta::factory()->for($edital)->create(['edital_questionario_secao_id' => $soDaDanca->id, 'todas_categorias' => false]);
        $daDanca->categorias()->attach($danca);
        $inscricao = $this->rascunho($edital, $user, ['edital_categoria_id' => $teatro->id]);
        InscricaoResposta::factory()->create(['inscricao_id' => $inscricao->id, 'edital_pergunta_id' => $resumo->id, 'texto' => 'Uma peça.']);
        $secao = PassosDaInscricao::idDaSecao((int) $proposta->id);

        $this->actingAs($user)->get(route('portal.inscricoes.show', [$inscricao, 'passo' => 'revisao']))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Portal/InscricaoPassos')
            ->where('passos', [
                ['id' => 'escolhas', 'rotulo' => 'Escolhas', 'completo' => true],
                ['id' => $secao, 'rotulo' => 'Proposta', 'completo' => false],
                ['id' => 'documentos', 'rotulo' => 'Documentos', 'completo' => true],
                ['id' => 'revisao', 'rotulo' => 'Revisão e envio', 'completo' => false],
            ])
            ->where('passoAtual', $secao)
            ->where('perfilIncompleto', false)
            ->where('pendencias', ['Responda as perguntas obrigatórias de "Proposta".'])
            ->has('secoes', 1)
            ->where('secoes.0.id', (int) $proposta->id)
            ->where('secoes.0.titulo', 'Proposta')
            ->where('secoes.0.descricao', 'Sobre o projeto.')
            ->where('secoes.0.perguntas', [
                [
                    'id' => (int) $linguagem->id, 'enunciado' => 'Linguagem?', 'ajuda' => 'A principal.', 'tipo' => 'escolha_unica',
                    'obrigatoria' => true, 'limite' => null,
                    'opcoes' => [
                        ['chave' => 'o1', 'texto' => 'Teatro', 'outro' => false],
                        ['chave' => 'o2', 'texto' => 'Circo', 'outro' => false],
                        ['chave' => 'o3', 'texto' => 'Outro', 'outro' => true],
                    ],
                    'condicao' => null,
                ],
                [
                    'id' => (int) $circo->id, 'enunciado' => 'Qual lona?', 'ajuda' => null, 'tipo' => 'texto_curto',
                    'obrigatoria' => true, 'limite' => 255, 'opcoes' => [],
                    'condicao' => ['pergunta' => (int) $linguagem->id, 'opcoes' => ['o2']],
                ],
                [
                    'id' => (int) $acordo->id, 'enunciado' => 'Declaro.', 'ajuda' => null, 'tipo' => 'declaracao',
                    'obrigatoria' => false, 'limite' => null, 'opcoes' => [], 'condicao' => null,
                ],
                [
                    'id' => (int) $resumo->id, 'enunciado' => 'Resumo', 'ajuda' => null, 'tipo' => 'texto_curto',
                    'obrigatoria' => false, 'limite' => 500, 'opcoes' => [], 'condicao' => null,
                ],
            ])
            ->where('respostas', [(int) $resumo->id => ['texto' => 'Uma peça.', 'opcoes' => [], 'outro' => null]]));

        // Sem pedido, abre o primeiro incompleto; com a seção respondida, a revisão pedida abre.
        $this->get(route('portal.inscricoes.show', $inscricao))->assertInertia(fn (Assert $p) => $p->where('passoAtual', $secao));
        InscricaoResposta::factory()->create([
            'inscricao_id' => $inscricao->id, 'edital_pergunta_id' => $linguagem->id, 'texto' => null, 'opcoes' => ['o1'],
        ]);
        $this->get(route('portal.inscricoes.show', [$inscricao, 'passo' => 'revisao']))->assertInertia(fn (Assert $p) => $p
            ->where('passoAtual', 'revisao')
            ->where('passos.1.completo', true));

        // Finalizada abre na revisão, mesmo pedindo outro passo.
        $inscricao->update(['situacao' => SituacaoInscricao::Finalizada]);
        $this->get(route('portal.inscricoes.show', [$inscricao, 'passo' => 'escolhas']))->assertInertia(fn (Assert $p) => $p->where('passoAtual', 'revisao'));
    }

    public function test_salvar_escolhas_avanca_para_o_passo_seguinte(): void
    {
        $user = User::factory()->proponente()->comPerfil()->create(['cpf' => self::CPF]);
        $edital = $this->editalAberto();
        $teatro = EditalCategoria::factory()->for($edital)->create(['ordem' => 1]);
        $danca = EditalCategoria::factory()->for($edital)->create(['ordem' => 2]);
        $proposta = EditalQuestionarioSecao::factory()->for($edital)->create(['ordem' => 1]);
        $daDanca = EditalPergunta::factory()->for($edital)->create(['edital_questionario_secao_id' => $proposta->id, 'todas_categorias' => false]);
        $daDanca->categorias()->attach($danca);
        $inscricao = $this->rascunho($edital, $user, ['edital_categoria_id' => $danca->id]);

        $this->actingAs($user)->put(route('portal.inscricoes.update', $inscricao), ['cadastro' => 'pessoa_fisica', 'categoria' => $danca->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('portal.inscricoes.show', [$inscricao, 'passo' => PassosDaInscricao::idDaSecao((int) $proposta->id)]));

        // No teatro a seção não tem pergunta: o passo seguinte é o dos documentos.
        $this->put(route('portal.inscricoes.update', $inscricao), ['cadastro' => 'pessoa_fisica', 'categoria' => $teatro->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('portal.inscricoes.show', [$inscricao, 'passo' => 'documentos']));
    }

    public function test_pagina_de_inscricao_do_edital_avisa_perfil_incompleto(): void
    {
        $incompleto = User::factory()->proponente()->create(['cpf' => self::CPF]);
        PessoaFisica::factory()->perfilIncompleto()->for($incompleto)->create();
        $completo = User::factory()->proponente()->comPerfil()->create();
        $this->editalAberto();

        $this->actingAs($incompleto)->get('/portal/editais/pnab/inscricao')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('perfilIncompleto', true));
        $this->actingAs($completo)->get('/portal/editais/pnab/inscricao')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('perfilIncompleto', false));
    }

    /**
     * Edital publicado com uma chamada aberta de 01/11 a 15/11/2026 (hoje é 10/11), salvo se $inicio e $fim disserem outra.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function editalAberto(array $atributos = [], string $inicio = '2026-11-01', string $fim = '2026-11-15'): Edital
    {
        $edital = Edital::factory()->create([
            'slug' => 'pnab', 'inscricoes_inicio' => $inicio, 'inscricoes_fim' => $fim, ...$atributos,
        ]);
        EditalChamada::factory()->for($edital)->create(['inicio' => $inicio, 'fim' => $fim, 'ordem' => 1]);

        return $edital;
    }

    /**
     * Rascunho da pessoa física do usuário, na primeira chamada do edital.
     *
     * @param  array<string, mixed>  $atributos
     */
    private function rascunho(Edital $edital, User $user, array $atributos = []): Inscricao
    {
        return Inscricao::factory()->for($edital)->for($user)->create([
            'edital_chamada_id' => $edital->chamadas()->first()->id,
            'documento_cadastro' => $user->cpf,
            ...$atributos,
        ]);
    }
}
