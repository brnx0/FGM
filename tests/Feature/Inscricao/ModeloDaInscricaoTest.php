<?php

namespace Tests\Feature\Inscricao;

use App\Enums\ChecagemDocumento;
use App\Enums\ModalidadeCota;
use App\Enums\NaturezaProponente;
use App\Enums\SituacaoConferencia;
use App\Models\EditalDocumento;
use App\Models\Inscricao;
use App\Models\InscricaoDocumento;
use App\Models\Organizacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModeloDaInscricaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_documento_existente_e_novo_comecam_aceitando_sem_validar(): void
    {
        $documento = EditalDocumento::factory()->create();

        $this->assertSame(ChecagemDocumento::AceitaSemValidar, $documento->fresh()->checagem);
    }

    public function test_conferencia_parada_ha_mais_de_cinco_minutos_aparece_como_nao_conferida(): void
    {
        $parada = InscricaoDocumento::factory()->conferindo()->create(['iniciada_em' => now()->subMinutes(6)]);
        $recente = InscricaoDocumento::factory()->conferindo()->create(['iniciada_em' => now()->subMinutes(4)]);

        $this->assertSame(SituacaoConferencia::NaoConferido, $parada->situacaoExibida());
        $this->assertSame(SituacaoConferencia::Conferindo, $recente->situacaoExibida());
    }

    public function test_natureza_da_organizacao(): void
    {
        $mei = Organizacao::factory()->make(['mei' => true, 'finalidade_lucrativa' => true]);
        $comFins = Organizacao::factory()->make(['mei' => false, 'finalidade_lucrativa' => true]);
        $semFins = Organizacao::factory()->make(['mei' => false, 'finalidade_lucrativa' => false]);

        $this->assertSame(NaturezaProponente::Mei, NaturezaProponente::daOrganizacao($mei));
        $this->assertSame(NaturezaProponente::ComFins, NaturezaProponente::daOrganizacao($comFins));
        $this->assertSame(NaturezaProponente::SemFins, NaturezaProponente::daOrganizacao($semFins));
    }

    public function test_cotas_da_inscricao_voltam_como_enums(): void
    {
        $inscricao = Inscricao::factory()->create(['cotas' => [ModalidadeCota::Negros, ModalidadeCota::Pcd]]);

        $cotas = $inscricao->fresh()->cotas;

        $this->assertSame([ModalidadeCota::Negros, ModalidadeCota::Pcd], $cotas->all());
    }
}
