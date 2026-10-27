<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\RepresentanteLegal;
use App\Models\User;
use App\Models\VinculoOrganizacao;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizacaoModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cnpj_e_gravado_normalizado(): void
    {
        $organizacao = Organizacao::factory()->create(['cnpj' => '12.abc.345/01de-35']);

        $this->assertSame('12ABC34501DE35', $organizacao->fresh()->cnpj);
    }

    public function test_da_pessoa_traz_so_organizacoes_com_vinculo_do_cpf(): void
    {
        $usuario = User::factory()->create();
        $minha = Organizacao::factory()->comResponsavel($usuario)->create();
        Organizacao::factory()->create();

        $ids = $usuario->organizacoes()->pluck('organizacoes.id')->all();

        $this->assertSame([$minha->id], $ids);
        $this->assertTrue($minha->temVinculo($usuario));
    }

    public function test_vinculo_vale_antes_do_usuario_existir(): void
    {
        $organizacao = Organizacao::factory()->create();
        VinculoOrganizacao::factory()->create([
            'organizacao_id' => $organizacao->id,
            'cpf' => '52998224725',
        ]);

        $usuario = User::factory()->create(['cpf' => '52998224725']);

        $this->assertSame(1, $usuario->organizacoes()->count());
    }

    public function test_eh_responsavel_so_para_o_vinculo_marcado(): void
    {
        $responsavel = User::factory()->create();
        $comum = User::factory()->create();
        $organizacao = Organizacao::factory()->comResponsavel($responsavel)->create();
        VinculoOrganizacao::factory()->create([
            'organizacao_id' => $organizacao->id,
            'cpf' => $comum->cpf,
            'responsavel' => false,
        ]);

        $this->assertTrue($organizacao->ehResponsavel($responsavel));
        $this->assertFalse($organizacao->ehResponsavel($comum));
        $this->assertTrue($organizacao->temVinculo($comum));
    }

    public function test_cnpj_unico(): void
    {
        $organizacao = Organizacao::factory()->create();

        $this->expectException(QueryException::class);

        Organizacao::factory()->create(['cnpj' => $organizacao->cnpj]);
    }

    public function test_representantes_vem_na_ordem(): void
    {
        $organizacao = Organizacao::factory()->create();
        RepresentanteLegal::factory()->create(['organizacao_id' => $organizacao->id, 'ordem' => 2, 'nome' => 'Segundo']);
        RepresentanteLegal::factory()->create(['organizacao_id' => $organizacao->id, 'ordem' => 1, 'nome' => 'Primeiro']);

        $this->assertSame(['Primeiro', 'Segundo'], $organizacao->representantes->pluck('nome')->all());
    }
}
