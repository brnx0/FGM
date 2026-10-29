<?php

namespace Tests\Feature\Inscricao;

use App\Enums\EtapaDocumento;
use App\Enums\ModalidadeCota;
use App\Enums\NaturezaProponente;
use App\Models\Edital;
use App\Models\EditalCategoria;
use App\Models\EditalChamada;
use App\Models\EditalDocumento;
use App\Models\Inscricao;
use App\Models\Organizacao;
use App\Models\User;
use App\Models\VinculoOrganizacao;
use App\Support\Inscricao\RegrasDaInscricao;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegrasDaInscricaoTest extends TestCase
{
    use RefreshDatabase;

    private const string CPF = '52998224725';

    private const string CNPJ = '11222333000181';

    public function test_cadastros_trazem_a_pessoa_fisica_e_so_as_organizacoes_em_que_o_usuario_e_responsavel(): void
    {
        $user = User::factory()->create(['cpf' => self::CPF, 'name' => 'Maria Souza']);
        $edital = Edital::factory()->create(['naturezas' => null]);
        $responsavel = Organizacao::factory()->comResponsavel($user)->create([
            'cnpj' => self::CNPJ, 'razao_social' => 'Coletivo Alfa', 'mei' => false, 'finalidade_lucrativa' => false,
        ]);
        $soVinculada = Organizacao::factory()->create();
        VinculoOrganizacao::factory()->for($soVinculada)->create(['cpf' => self::CPF, 'responsavel' => false]);
        VinculoOrganizacao::factory()->for($soVinculada)->create(['responsavel' => true]);

        $cadastros = RegrasDaInscricao::cadastros($user, $edital);

        $this->assertSame([
            [
                'tipo' => 'pessoa_fisica', 'id' => null, 'nome' => 'Maria Souza', 'documento' => self::CPF,
                'natureza' => NaturezaProponente::PessoaFisica, 'aceito' => true,
            ],
            [
                'tipo' => 'organizacao', 'id' => (int) $responsavel->id, 'nome' => 'Coletivo Alfa', 'documento' => self::CNPJ,
                'natureza' => NaturezaProponente::SemFins, 'aceito' => true,
            ],
        ], $cadastros);
    }

    public function test_cadastro_de_natureza_que_o_edital_nao_aceita_vem_recusado(): void
    {
        $user = User::factory()->create();
        $edital = Edital::factory()->create(['naturezas' => [NaturezaProponente::PessoaFisica]]);
        Organizacao::factory()->comResponsavel($user)->create(['mei' => false, 'finalidade_lucrativa' => true]);

        $cadastros = RegrasDaInscricao::cadastros($user, $edital);

        $this->assertSame(['pessoa_fisica' => true, 'organizacao' => false], array_column($cadastros, 'aceito', 'tipo'));
    }

    public function test_categorias_sao_as_que_aceitam_a_natureza(): void
    {
        $edital = Edital::factory()->create();
        EditalCategoria::factory()->for($edital)->create(['nome' => 'Livre', 'naturezas' => null, 'ordem' => 1]);
        EditalCategoria::factory()->for($edital)->create(['nome' => 'Só MEI', 'naturezas' => [NaturezaProponente::Mei], 'ordem' => 2]);
        EditalCategoria::factory()->for($edital)->create([
            'nome' => 'Pessoas', 'naturezas' => [NaturezaProponente::PessoaFisica, NaturezaProponente::Mei], 'ordem' => 3,
        ]);

        $categorias = RegrasDaInscricao::categorias($edital, NaturezaProponente::PessoaFisica);

        $this->assertSame(['Livre', 'Pessoas'], $categorias->pluck('nome')->all());
    }

    public function test_cotas_oferecidas_sao_so_as_ativas(): void
    {
        $edital = Edital::factory()->create(['cotas' => [
            'negros' => ['ativa' => true],
            'indigenas' => ['ativa' => false],
            'pcd' => ['ativa' => true],
        ]]);

        $this->assertSame([ModalidadeCota::Negros, ModalidadeCota::Pcd], RegrasDaInscricao::cotasOferecidas($edital));
    }

    public function test_inscricao_fica_na_chamada_da_categoria(): void
    {
        $edital = Edital::factory()->create();
        EditalChamada::factory()->for($edital)->create(['ordem' => 1]);
        $segunda = EditalChamada::factory()->for($edital)->create(['ordem' => 2]);
        $categoria = EditalCategoria::factory()->for($edital)->create(['edital_chamada_id' => $segunda->id]);

        $this->assertTrue($segunda->is(RegrasDaInscricao::chamada($edital, $categoria)));
        $this->assertNull(RegrasDaInscricao::chamada($edital, null));
    }

    public function test_categoria_sem_chamada_usa_a_unica_chamada_do_edital(): void
    {
        $edital = Edital::factory()->create();
        $unica = EditalChamada::factory()->for($edital)->create();
        $categoria = EditalCategoria::factory()->for($edital)->create(['edital_chamada_id' => null]);

        $this->assertTrue($unica->is(RegrasDaInscricao::chamada($edital, $categoria)));
    }

    public function test_documentos_exigidos_seguem_natureza_categoria_e_cotas_marcadas_em_todas_as_etapas(): void
    {
        $edital = Edital::factory()->create();
        $escolhida = EditalCategoria::factory()->for($edital)->create();
        $outra = EditalCategoria::factory()->for($edital)->create();
        $this->documento($edital, 'Ficha de inscrição', 1);
        $this->documento($edital, 'Contrato social', 2, ['naturezas' => [NaturezaProponente::SemFins, NaturezaProponente::ComFins]]);
        $this->documento($edital, 'Portfólio da categoria', 3, ['todas_categorias' => false])->categorias()->attach($escolhida);
        $this->documento($edital, 'Portfólio de outra categoria', 4, ['todas_categorias' => false])->categorias()->attach($outra);
        $this->documento($edital, 'Autodeclaração étnico-racial', 5, [
            'etapa' => EtapaDocumento::ValidacaoCotas, 'modalidade_cota' => ModalidadeCota::Negros,
        ]);
        $this->documento($edital, 'Laudo médico', 6, ['etapa' => EtapaDocumento::ValidacaoCotas, 'modalidade_cota' => ModalidadeCota::Pcd]);
        $this->documento($edital, 'Certidão negativa', 7, [
            'etapa' => EtapaDocumento::Habilitacao, 'naturezas' => [NaturezaProponente::PessoaFisica],
        ]);
        $inscricao = Inscricao::factory()->for($edital)->create([
            'natureza' => NaturezaProponente::PessoaFisica,
            'edital_categoria_id' => $escolhida->id,
            'cotas' => [ModalidadeCota::Negros],
        ]);

        $documentos = $inscricao->documentosExigidos();

        $this->assertSame(
            ['Ficha de inscrição', 'Portfólio da categoria', 'Autodeclaração étnico-racial', 'Certidão negativa'],
            $documentos->pluck('nome')->all(),
        );
    }

    public function test_sem_cota_marcada_os_documentos_de_cota_ficam_fora(): void
    {
        $edital = Edital::factory()->create();
        $this->documento($edital, 'Ficha de inscrição', 1);
        $this->documento($edital, 'Autodeclaração étnico-racial', 2, ['modalidade_cota' => ModalidadeCota::Negros]);
        $inscricao = Inscricao::factory()->for($edital)->create(['cotas' => null]);

        $documentos = $inscricao->documentosExigidos();

        $this->assertSame(['Ficha de inscrição'], $documentos->pluck('nome')->all());
    }

    public function test_limite_soma_rascunhos_e_finalizadas_do_cadastro_no_edital(): void
    {
        $edital = Edital::factory()->create(['inscricoes_por_proponente' => 2, 'limite_por_chamada' => false]);
        $primeira = EditalChamada::factory()->for($edital)->create(['ordem' => 1]);
        $segunda = EditalChamada::factory()->for($edital)->create(['ordem' => 2]);
        $rascunho = Inscricao::factory()->for($edital)->create(['documento_cadastro' => self::CPF, 'edital_chamada_id' => $primeira->id]);
        Inscricao::factory()->for($edital)->finalizada()->create(['documento_cadastro' => self::CPF, 'edital_chamada_id' => $segunda->id]);
        Inscricao::factory()->create(['documento_cadastro' => self::CNPJ]);
        Inscricao::factory()->for($edital)->create(['documento_cadastro' => self::CNPJ, 'edital_chamada_id' => $primeira->id]);

        $this->assertTrue(RegrasDaInscricao::limiteAtingido($edital, $primeira, self::CPF));
        $this->assertFalse(RegrasDaInscricao::limiteAtingido($edital, $primeira, self::CNPJ));
        $this->assertFalse(RegrasDaInscricao::limiteAtingido($edital, $primeira, self::CPF, (int) $rascunho->id));
    }

    public function test_limite_por_chamada_conta_so_as_inscricoes_da_chamada(): void
    {
        $edital = Edital::factory()->create(['inscricoes_por_proponente' => 1, 'limite_por_chamada' => true]);
        $primeira = EditalChamada::factory()->for($edital)->create(['ordem' => 1]);
        $segunda = EditalChamada::factory()->for($edital)->create(['ordem' => 2]);
        Inscricao::factory()->for($edital)->create(['documento_cadastro' => self::CPF, 'edital_chamada_id' => $primeira->id]);

        $this->assertTrue(RegrasDaInscricao::limiteAtingido($edital, $primeira, self::CPF));
        $this->assertFalse(RegrasDaInscricao::limiteAtingido($edital, $segunda, self::CPF));
    }

    public function test_edital_sem_limite_aceita_quantas_inscricoes_o_cadastro_quiser(): void
    {
        $edital = Edital::factory()->create(['inscricoes_por_proponente' => null]);
        Inscricao::factory()->for($edital)->count(3)->create(['documento_cadastro' => self::CPF]);

        $this->assertFalse(RegrasDaInscricao::limiteAtingido($edital, null, self::CPF));
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function momentosDaChamada(): array
    {
        return [
            'véspera do início' => ['2026-11-09 23:00:00', false],
            'primeiro dia' => ['2026-11-10 00:01:00', true],
            'último dia' => ['2026-11-20 23:59:00', true],
            'dia seguinte ao fim' => ['2026-11-21 00:01:00', false],
        ];
    }

    #[DataProvider('momentosDaChamada')]
    public function test_inscricao_esta_no_periodo_so_entre_o_inicio_e_o_fim_da_chamada(string $agora, bool $noPeriodo): void
    {
        $edital = Edital::factory()->create();
        $chamada = EditalChamada::factory()->for($edital)->create(['inicio' => '2026-11-10', 'fim' => '2026-11-20']);
        $inscricao = Inscricao::factory()->for($edital)->create(['edital_chamada_id' => $chamada->id]);

        $this->assertSame($noPeriodo, $inscricao->noPeriodo(CarbonImmutable::parse($agora, config('app.timezone'))));
    }

    public function test_inscricao_de_edital_fora_do_portal_nao_esta_no_periodo(): void
    {
        $edital = Edital::factory()->create(['publicado' => false]);
        $chamada = EditalChamada::factory()->for($edital)->create(['inicio' => '2026-11-10', 'fim' => '2026-11-20']);
        $inscricao = Inscricao::factory()->for($edital)->create(['edital_chamada_id' => $chamada->id]);

        $this->assertFalse($inscricao->noPeriodo(CarbonImmutable::parse('2026-11-15 12:00:00', config('app.timezone'))));
    }

    public function test_negros_e_indigenas_juntos_sao_recusados_quando_o_edital_veda(): void
    {
        $edital = Edital::factory()->create(['cotas' => $this->cotasDeNegrosEIndigenas(veda: true)]);

        $erro = RegrasDaInscricao::erroNasCotas($edital, [ModalidadeCota::Negros, ModalidadeCota::Indigenas]);

        $this->assertSame('Escolha só uma entre as cotas para pessoas negras e indígenas.', $erro);
    }

    public function test_negros_e_indigenas_juntos_valem_quando_o_edital_nao_veda(): void
    {
        $edital = Edital::factory()->create(['cotas' => $this->cotasDeNegrosEIndigenas(veda: null)]);

        $this->assertNull(RegrasDaInscricao::erroNasCotas($edital, [ModalidadeCota::Negros, ModalidadeCota::Indigenas]));
    }

    public function test_cota_que_o_edital_nao_oferece_e_recusada(): void
    {
        $edital = Edital::factory()->create(['cotas' => $this->cotasDeNegrosEIndigenas(veda: false)]);

        $erro = RegrasDaInscricao::erroNasCotas($edital, [ModalidadeCota::Pcd]);

        $this->assertSame('Escolha só entre as cotas que o edital oferece.', $erro);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function documento(Edital $edital, string $nome, int $ordem, array $atributos = []): EditalDocumento
    {
        return EditalDocumento::factory()->for($edital)->create(['nome' => $nome, 'ordem' => $ordem, ...$atributos]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cotasDeNegrosEIndigenas(?bool $veda): array
    {
        return [
            'negros' => ['ativa' => true],
            'indigenas' => ['ativa' => true],
            'pcd' => ['ativa' => false],
            'veda_negro_indigena' => $veda,
        ];
    }
}
