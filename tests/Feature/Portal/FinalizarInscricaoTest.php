<?php

namespace Tests\Feature\Portal;

use App\Enums\ChecagemDocumento;
use App\Enums\SituacaoConferencia;
use App\Enums\SituacaoInscricao;
use App\Http\Controllers\InscricaoController;
use App\Http\Controllers\PortalController;
use App\Models\Edital;
use App\Models\EditalChamada;
use App\Models\EditalDocumento;
use App\Models\Inscricao;
use App\Models\InscricaoDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinalizarInscricaoTest extends TestCase
{
    use RefreshDatabase;

    private const string CPF = '52998224725';

    private User $user;

    private Edital $edital;

    private Inscricao $inscricao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-10 10:00', 'America/Bahia'));

        $this->user = User::factory()->proponente()->create(['cpf' => self::CPF]);
        $this->edital = $this->editalAberto();
        $this->inscricao = $this->rascunho($this->edital, $this->user);
    }

    public function test_finaliza_com_todos_os_obrigatorios(): void
    {
        $rg = $this->documentoExigido('RG', ChecagemDocumento::Rigorosa);
        $declaracao = $this->documentoExigido('Declaração', ChecagemDocumento::Nenhuma);
        $this->documentoExigido('Portfólio', ChecagemDocumento::Rigorosa, obrigatorio: false);
        $this->enviar($rg, SituacaoConferencia::Aprovado);
        $this->enviar($declaracao, SituacaoConferencia::Enviado);

        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao))
            ->assertRedirect(route('portal.inscricoes.show', $this->inscricao))
            ->assertSessionHas('sucesso');

        $finalizada = $this->inscricao->fresh();
        $this->assertSame(SituacaoInscricao::Finalizada, $finalizada->situacao);
        $this->assertTrue(now()->equalTo($finalizada->finalizada_em));
        $this->assertSame('2026'.str_pad((string) $finalizada->id, 6, '0', STR_PAD_LEFT), $finalizada->numero);
    }

    public function test_nao_finaliza_com_obrigatorio_faltando(): void
    {
        $this->documentoExigido('RG', ChecagemDocumento::Rigorosa);
        $this->documentoExigido('Comprovante', ChecagemDocumento::Nenhuma);
        $this->documentoExigido('Portfólio', ChecagemDocumento::Rigorosa, obrigatorio: false);

        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao))
            ->assertRedirect(route('portal.inscricoes.show', $this->inscricao))
            ->assertSessionHas('erro', InscricaoController::ERRO_PENDENCIAS);

        $this->assertNaoFinalizada();
        $this->get(route('portal.inscricoes.show', $this->inscricao))->assertInertia(fn (Assert $page) => $page
            ->where('pendencias', ['Anexe "RG".', 'Anexe "Comprovante".']));
    }

    public function test_nao_finaliza_com_documento_conferindo(): void
    {
        $rigoroso = $this->documentoExigido('RG', ChecagemDocumento::Rigorosa);
        $sem = $this->documentoExigido('Declaração', ChecagemDocumento::AceitaSemValidar, obrigatorio: false);
        $this->enviar($rigoroso, SituacaoConferencia::Conferindo);
        $this->enviar($sem, SituacaoConferencia::Conferindo);

        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao))
            ->assertSessionHas('erro', InscricaoController::ERRO_PENDENCIAS);

        $this->assertNaoFinalizada();
        $this->assertSame(
            ['Aguarde a conferência de "RG".', 'Aguarde a conferência de "Declaração".'],
            $this->inscricao->fresh()->pendenciasParaFinalizar(),
        );
    }

    public function test_conferindo_parado_ha_mais_de_5_minutos_conta_como_nao_conferido(): void
    {
        $rigoroso = $this->documentoExigido('RG', ChecagemDocumento::Rigorosa);
        $sem = $this->documentoExigido('Declaração', ChecagemDocumento::AceitaSemValidar);
        $this->enviar($rigoroso, SituacaoConferencia::Conferindo, ['iniciada_em' => now()->subMinutes(6)]);
        $this->enviar($sem, SituacaoConferencia::Conferindo, ['iniciada_em' => now()->subMinutes(6)]);

        $this->assertSame(['"RG" precisa ser aprovado na conferência.'], $this->inscricao->pendenciasParaFinalizar());

        $this->enviar($rigoroso, SituacaoConferencia::Aprovado);

        $this->assertSame([], $this->inscricao->pendenciasParaFinalizar());
    }

    public function test_rigoroso_reprovado_ou_nao_conferido_impede_finalizar(): void
    {
        $obrigatorio = $this->documentoExigido('RG', ChecagemDocumento::Rigorosa);
        $opcional = $this->documentoExigido('Portfólio', ChecagemDocumento::Rigorosa, obrigatorio: false);
        $this->enviar($obrigatorio, SituacaoConferencia::Aprovado);

        // Opcional anexado em Rigorosa também precisa ser aprovado.
        foreach ([SituacaoConferencia::Reprovado, SituacaoConferencia::NaoConferido, SituacaoConferencia::Enviado] as $situacao) {
            $this->enviar($opcional, $situacao);

            $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao))
                ->assertSessionHas('erro', InscricaoController::ERRO_PENDENCIAS);
            $this->assertNaoFinalizada();
            $this->assertSame(['"Portfólio" precisa ser aprovado na conferência.'], $this->inscricao->fresh()->pendenciasParaFinalizar());
        }

        $this->enviar($obrigatorio, SituacaoConferencia::Reprovado);
        $this->enviar($opcional, SituacaoConferencia::Aprovado);

        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao))
            ->assertSessionHas('erro', InscricaoController::ERRO_PENDENCIAS);
        $this->assertNaoFinalizada();
        $this->assertSame(['"RG" precisa ser aprovado na conferência.'], $this->inscricao->fresh()->pendenciasParaFinalizar());

        $this->enviar($obrigatorio, SituacaoConferencia::Aprovado);

        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao))->assertSessionHas('sucesso');
        $this->assertSame(SituacaoInscricao::Finalizada, $this->inscricao->fresh()->situacao);
    }

    public function test_aceita_sem_validar_reprovado_nao_impede(): void
    {
        $reprovado = $this->documentoExigido('Declaração', ChecagemDocumento::AceitaSemValidar);
        $naoConferido = $this->documentoExigido('Comprovante', ChecagemDocumento::AceitaSemValidar);
        $semChecagem = $this->documentoExigido('Foto', ChecagemDocumento::Nenhuma);
        $this->enviar($reprovado, SituacaoConferencia::Reprovado);
        $this->enviar($naoConferido, SituacaoConferencia::NaoConferido);
        $this->enviar($semChecagem, SituacaoConferencia::Enviado);

        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao))
            ->assertSessionHas('sucesso');

        $this->assertSame(SituacaoInscricao::Finalizada, $this->inscricao->fresh()->situacao);
    }

    public function test_numero_e_mantido_ao_reabrir_e_finalizar_de_novo(): void
    {
        $outra = $this->rascunho($this->edital, $this->user);
        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao));
        $numero = $this->inscricao->fresh()->numero;
        $this->assertSame('2026'.str_pad((string) $this->inscricao->id, 6, '0', STR_PAD_LEFT), $numero);

        $this->travelTo(Carbon::parse('2026-11-12 15:30', 'America/Bahia'));
        $this->post(route('portal.inscricoes.reabrir', $this->inscricao));
        $this->post(route('portal.inscricoes.finalizar', $this->inscricao))->assertSessionHas('sucesso');

        $refeita = $this->inscricao->fresh();
        $this->assertSame(SituacaoInscricao::Finalizada, $refeita->situacao);
        $this->assertSame($numero, $refeita->numero);
        $this->assertTrue(now()->equalTo($refeita->finalizada_em));

        $this->post(route('portal.inscricoes.finalizar', $outra));
        $this->assertNotSame($numero, $outra->fresh()->numero);
    }

    public function test_finalizar_uma_inscricao_ja_finalizada_nao_muda_nada(): void
    {
        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao));
        $finalizada = $this->inscricao->fresh();

        $this->travelTo(Carbon::parse('2026-11-12 15:30', 'America/Bahia'));
        $this->post(route('portal.inscricoes.finalizar', $this->inscricao))
            ->assertRedirect(route('portal.inscricoes.show', $this->inscricao))
            ->assertSessionMissing('erro');

        $this->assertTrue($finalizada->finalizada_em->equalTo($this->inscricao->fresh()->finalizada_em));
    }

    public function test_reabrir_volta_a_rascunho(): void
    {
        $rg = $this->documentoExigido('RG', ChecagemDocumento::Rigorosa);
        $this->enviar($rg, SituacaoConferencia::Aprovado);
        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $this->inscricao));
        $numero = $this->inscricao->fresh()->numero;

        $this->post(route('portal.inscricoes.reabrir', $this->inscricao))
            ->assertRedirect(route('portal.inscricoes.show', $this->inscricao))
            ->assertSessionHas('sucesso');

        $reaberta = $this->inscricao->fresh();
        $this->assertSame(SituacaoInscricao::Rascunho, $reaberta->situacao);
        $this->assertSame($numero, $reaberta->numero);
        $this->assertNull($reaberta->finalizada_em);
        $this->assertSame(SituacaoConferencia::Aprovado, InscricaoDocumento::sole()->situacao);

        // Reabrir um rascunho não faz nada.
        $this->post(route('portal.inscricoes.reabrir', $this->inscricao))->assertSessionMissing('erro');
        $this->assertSame(SituacaoInscricao::Rascunho, $this->inscricao->fresh()->situacao);
    }

    public function test_depois_do_periodo_nao_finaliza_nem_reabre_e_finalizada_continua(): void
    {
        $finalizada = $this->rascunho($this->edital, $this->user);
        $this->actingAs($this->user)->post(route('portal.inscricoes.finalizar', $finalizada));

        $this->travelTo(Carbon::parse('2026-11-16 09:00', 'America/Bahia'));

        $this->post(route('portal.inscricoes.finalizar', $this->inscricao))
            ->assertRedirect(route('portal.editais'))
            ->assertSessionHas('aviso', PortalController::AVISO_INSCRICOES_FECHADAS);
        $this->assertNaoFinalizada();

        $this->post(route('portal.inscricoes.reabrir', $finalizada))
            ->assertRedirect(route('portal.editais'))
            ->assertSessionHas('aviso', PortalController::AVISO_INSCRICOES_FECHADAS);
        $this->assertSame(SituacaoInscricao::Finalizada, $finalizada->fresh()->situacao);
        $this->assertNotNull($finalizada->fresh()->numero);
    }

    public function test_so_o_dono_finaliza_e_reabre(): void
    {
        $this->actingAs(User::factory()->proponente()->create());

        $this->post(route('portal.inscricoes.finalizar', $this->inscricao))->assertNotFound();
        $this->assertNaoFinalizada();

        $this->inscricao->update(['situacao' => SituacaoInscricao::Finalizada]);
        $this->post(route('portal.inscricoes.reabrir', $this->inscricao))->assertNotFound();
        $this->assertSame(SituacaoInscricao::Finalizada, $this->inscricao->fresh()->situacao);
    }

    public function test_situacao_em_json_diz_se_pode_finalizar(): void
    {
        $rg = $this->documentoExigido('RG', ChecagemDocumento::Rigorosa);
        $this->enviar($rg, SituacaoConferencia::Conferindo);
        $url = route('portal.inscricoes.situacao', $this->inscricao);

        $this->actingAs($this->user)->getJson($url)
            ->assertJsonPath('podeFinalizar', false)
            ->assertJsonPath('pendencias', ['Aguarde a conferência de "RG".']);

        $this->enviar($rg, SituacaoConferencia::Aprovado);

        $this->getJson($url)
            ->assertJsonPath('podeFinalizar', true)
            ->assertJsonPath('pendencias', []);
    }

    public function test_lista_de_editais_mostra_a_situacao_da_minha_inscricao(): void
    {
        $comFinalizada = $this->editalAberto(['slug' => 'a', 'inscricoes_inicio' => '2026-11-04']);
        $comRascunho = $this->editalAberto(['slug' => 'b', 'inscricoes_inicio' => '2026-11-03']);
        $this->editalAberto(['slug' => 'c', 'inscricoes_inicio' => '2026-11-02']);
        $deOutro = $this->editalAberto(['slug' => 'd', 'inscricoes_inicio' => '2026-11-01']);
        $this->rascunho($comFinalizada, $this->user);
        Inscricao::factory()->finalizada()->for($comFinalizada)->for($this->user)->create(['documento_cadastro' => self::CPF]);
        $this->rascunho($comRascunho, $this->user);
        Inscricao::factory()->finalizada()->for($deOutro)->for(User::factory()->proponente()->create())->create();

        // O edital 'pnab' do setUp (rascunho do usuário) é o último: mesma data de início de 'd', id menor.
        $this->actingAs($this->user)->get(route('portal.editais'))->assertInertia(fn (Assert $page) => $page
            ->where('editais.total', 5)
            ->where('editais.data.0.slug', 'a')
            ->where('editais.data.0.minhaInscricao', 'finalizada')
            ->where('editais.data.1.slug', 'b')
            ->where('editais.data.1.minhaInscricao', 'rascunho')
            ->where('editais.data.2.slug', 'c')
            ->where('editais.data.2.minhaInscricao', null)
            ->where('editais.data.3.slug', 'd')
            ->where('editais.data.3.minhaInscricao', null)
            ->where('editais.data.4.slug', 'pnab')
            ->where('editais.data.4.minhaInscricao', 'rascunho'));
    }

    private function assertNaoFinalizada(): void
    {
        $inscricao = $this->inscricao->fresh();

        $this->assertSame(SituacaoInscricao::Rascunho, $inscricao->situacao);
        $this->assertNull($inscricao->numero);
        $this->assertNull($inscricao->finalizada_em);
    }

    private function documentoExigido(string $nome, ChecagemDocumento $checagem, bool $obrigatorio = true): EditalDocumento
    {
        return EditalDocumento::factory()->for($this->edital)->create([
            'nome' => $nome,
            'checagem' => $checagem,
            'obrigatorio' => $obrigatorio,
            'ordem' => EditalDocumento::query()->where('edital_id', $this->edital->id)->count() + 1,
        ]);
    }

    /**
     * Arquivo enviado para o documento exigido (cria ou atualiza o já enviado).
     *
     * @param  array<string, mixed>  $atributos
     */
    private function enviar(EditalDocumento $exigido, SituacaoConferencia $situacao, array $atributos = []): InscricaoDocumento
    {
        $enviado = InscricaoDocumento::query()->firstOrNew([
            'inscricao_id' => $this->inscricao->id,
            'edital_documento_id' => $exigido->id,
        ]);

        $enviado->fill([
            'arquivo' => 'inscricoes/documento.pdf',
            'arquivo_nome' => 'documento.pdf',
            'situacao' => $situacao,
            'iniciada_em' => $situacao === SituacaoConferencia::Conferindo ? now() : null,
            ...$atributos,
        ])->save();

        return $enviado;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function editalAberto(array $atributos = [], string $inicio = '2026-11-01', string $fim = '2026-11-15'): Edital
    {
        $edital = Edital::factory()->create([
            'slug' => 'pnab', 'inscricoes_inicio' => $inicio, 'inscricoes_fim' => $fim, ...$atributos,
        ]);
        EditalChamada::factory()->for($edital)->create(['inicio' => $edital->inscricoes_inicio->toDateString(), 'fim' => $fim, 'ordem' => 1]);

        return $edital;
    }

    private function rascunho(Edital $edital, User $user): Inscricao
    {
        return Inscricao::factory()->for($edital)->for($user)->create([
            'edital_chamada_id' => $edital->chamadas()->first()->id,
            'documento_cadastro' => $user->cpf,
        ]);
    }
}
