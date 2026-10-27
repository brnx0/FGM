<?php

namespace Tests\Feature;

use App\Models\Organizacao;
use App\Models\User;
use App\Models\VinculoOrganizacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VinculoOrganizacaoTest extends TestCase
{
    use RefreshDatabase;

    private const string CPF_VALIDO = '52998224725';

    private function usuario(): User
    {
        return User::factory()->cadastroCompleto()->create();
    }

    /** Organização com o usuário como responsável. */
    private function organizacaoDo(User $responsavel): Organizacao
    {
        return Organizacao::factory()->comResponsavel($responsavel)->create();
    }

    /** Vínculo comum (não responsável) de um usuário na organização. */
    private function vincular(Organizacao $organizacao, User $user): VinculoOrganizacao
    {
        return VinculoOrganizacao::factory()->create([
            'organizacao_id' => $organizacao->id,
            'cpf' => $user->cpf,
            'nome' => $user->name,
        ]);
    }

    public function test_vinculado_antes_do_primeiro_login_ve_a_organizacao_depois_de_completar_o_cadastro(): void
    {
        $organizacao = $this->organizacaoDo($this->usuario());
        VinculoOrganizacao::factory()->create(['organizacao_id' => $organizacao->id, 'cpf' => self::CPF_VALIDO, 'nome' => 'Ana']);

        $ana = User::factory()->cadastroCompleto()->create(['cpf' => self::CPF_VALIDO]);

        $this->actingAs($ana)->get(route('organizacoes.index'))->assertInertia(fn (Assert $page) => $page
            ->has('organizacoes', 1)
            ->where('organizacoes.0.id', $organizacao->id)
            ->where('organizacoes.0.responsavel', false));
    }

    public function test_index_marca_responsavel_e_lista_so_as_vinculadas(): void
    {
        $user = $this->usuario();
        $minha = $this->organizacaoDo($user);
        $outra = $this->organizacaoDo($this->usuario());
        $this->vincular($outra, $user);
        Organizacao::factory()->create();

        $this->actingAs($user)->get(route('organizacoes.index'))->assertInertia(fn (Assert $page) => $page
            ->has('organizacoes', 2)
            ->where('organizacoes', fn ($lista) => collect($lista)->firstWhere('id', $minha->id)['responsavel'] === true
                && collect($lista)->firstWhere('id', $outra->id)['responsavel'] === false));
    }

    public function test_pagina_de_pessoas_mostra_vinculos_com_cpf_mascarado_e_nome_do_usuario(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);
        User::factory()->cadastroCompleto()->create(['cpf' => self::CPF_VALIDO, 'name' => 'Nome do Usuário']);
        VinculoOrganizacao::factory()->create(['organizacao_id' => $organizacao->id, 'cpf' => self::CPF_VALIDO, 'nome' => 'Nome digitado']);
        VinculoOrganizacao::factory()->create(['organizacao_id' => $organizacao->id, 'cpf' => '11144477735', 'nome' => 'Sem conta']);

        $this->actingAs($responsavel)->get(route('organizacoes.vinculos', $organizacao))->assertInertia(fn (Assert $page) => $page
            ->component('Organizacoes/Vinculos')
            ->where('organizacao.id', $organizacao->id)
            ->where('podeGerenciar', true)
            ->has('vinculos', 3)
            ->where('vinculos', fn ($lista) => collect($lista)->contains(fn ($v) => $v['nome'] === 'Nome do Usuário' && $v['cpf'] === '***.982.247-**' && ! $v['responsavel'] && ! $v['ehVoce'])
                && collect($lista)->contains(fn ($v) => $v['nome'] === 'Sem conta')
                && collect($lista)->contains(fn ($v) => $v['responsavel'] && $v['ehVoce'])));
    }

    public function test_pagina_de_pessoas_ordena_pelo_nome_exibido(): void
    {
        $responsavel = User::factory()->cadastroCompleto()->create(['name' => 'Marta Responsavel']);
        $organizacao = $this->organizacaoDo($responsavel);
        User::factory()->cadastroCompleto()->create(['cpf' => self::CPF_VALIDO, 'name' => 'Ana Gov']);
        VinculoOrganizacao::factory()->create(['organizacao_id' => $organizacao->id, 'cpf' => self::CPF_VALIDO, 'nome' => 'Zélia digitada']);
        VinculoOrganizacao::factory()->create(['organizacao_id' => $organizacao->id, 'cpf' => '11144477735', 'nome' => 'Bruno']);

        $this->actingAs($responsavel)->get(route('organizacoes.vinculos', $organizacao))->assertInertia(fn (Assert $page) => $page
            ->where('vinculos', fn ($lista) => collect($lista)->pluck('nome')->values()->all() === ['Ana Gov', 'Bruno', 'Marta Responsavel']));
    }

    public function test_vinculado_nao_responsavel_ve_a_pagina_sem_poder_gerenciar(): void
    {
        $organizacao = $this->organizacaoDo($this->usuario());
        $membro = $this->usuario();
        $this->vincular($organizacao, $membro);

        $this->actingAs($membro)->get(route('organizacoes.vinculos', $organizacao))
            ->assertInertia(fn (Assert $page) => $page->where('podeGerenciar', false));
    }

    public function test_nao_vinculado_nao_ve_a_pagina_de_pessoas(): void
    {
        $organizacao = $this->organizacaoDo($this->usuario());

        $this->actingAs($this->usuario())->get(route('organizacoes.vinculos', $organizacao))->assertForbidden();
    }

    public function test_responsavel_vincula_cpf_valido_com_mascara(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);

        $this->actingAs($responsavel)
            ->post(route('organizacoes.vinculos.store', $organizacao), ['cpf' => '529.982.247-25', 'nome' => 'Ana Silva'])
            ->assertRedirect(route('organizacoes.vinculos', $organizacao))
            ->assertSessionHas('sucesso', 'Pessoa vinculada.');

        $this->assertDatabaseHas('organizacao_vinculos', [
            'organizacao_id' => $organizacao->id,
            'cpf' => self::CPF_VALIDO,
            'nome' => 'Ana Silva',
            'responsavel' => false,
        ]);
    }

    public function test_cpf_invalido_e_recusado(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);

        $this->actingAs($responsavel)
            ->post(route('organizacoes.vinculos.store', $organizacao), ['cpf' => '111.111.111-11', 'nome' => 'Ana'])
            ->assertSessionHasErrors(['cpf' => 'CPF inválido. Confira os números.']);

        $this->assertDatabaseCount('organizacao_vinculos', 1);
    }

    public function test_cpf_e_nome_sao_obrigatorios(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);

        $this->actingAs($responsavel)
            ->post(route('organizacoes.vinculos.store', $organizacao), [])
            ->assertSessionHasErrors(['cpf' => 'Este campo é obrigatório.', 'nome' => 'Este campo é obrigatório.']);
    }

    public function test_cpf_ja_vinculado_e_recusado(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);
        VinculoOrganizacao::factory()->create(['organizacao_id' => $organizacao->id, 'cpf' => self::CPF_VALIDO]);

        $this->actingAs($responsavel)
            ->post(route('organizacoes.vinculos.store', $organizacao), ['cpf' => '529.982.247-25', 'nome' => 'Ana'])
            ->assertSessionHasErrors(['cpf' => 'Esta pessoa já está vinculada.']);
    }

    public function test_mesmo_cpf_pode_estar_em_outra_organizacao(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);
        VinculoOrganizacao::factory()->create(['organizacao_id' => $this->organizacaoDo($this->usuario())->id, 'cpf' => self::CPF_VALIDO]);

        $this->actingAs($responsavel)
            ->post(route('organizacoes.vinculos.store', $organizacao), ['cpf' => self::CPF_VALIDO, 'nome' => 'Ana'])
            ->assertSessionHasNoErrors();
    }

    public function test_limite_de_20_pessoas_vinculadas(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);
        VinculoOrganizacao::factory()->count(Organizacao::LIMITE_VINCULOS - 1)->create(['organizacao_id' => $organizacao->id]);

        $this->actingAs($responsavel)
            ->post(route('organizacoes.vinculos.store', $organizacao), ['cpf' => self::CPF_VALIDO, 'nome' => 'Ana'])
            ->assertSessionHasErrors(['cpf' => 'Limite de 20 pessoas vinculadas.']);

        $this->assertDatabaseCount('organizacao_vinculos', Organizacao::LIMITE_VINCULOS);
    }

    public function test_nao_responsavel_nao_vincula(): void
    {
        $organizacao = $this->organizacaoDo($this->usuario());
        $membro = $this->usuario();
        $this->vincular($organizacao, $membro);

        $this->actingAs($membro)
            ->post(route('organizacoes.vinculos.store', $organizacao), ['cpf' => self::CPF_VALIDO, 'nome' => 'Ana'])
            ->assertForbidden();

        $this->assertDatabaseMissing('organizacao_vinculos', ['cpf' => self::CPF_VALIDO]);
    }

    public function test_responsavel_desvincula_outra_pessoa(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);
        $vinculo = $this->vincular($organizacao, $this->usuario());

        $this->actingAs($responsavel)
            ->delete(route('organizacoes.vinculos.destroy', [$organizacao, $vinculo]))
            ->assertRedirect(route('organizacoes.vinculos', $organizacao))
            ->assertSessionHas('sucesso', 'Pessoa desvinculada.');

        $this->assertModelMissing($vinculo);
    }

    public function test_responsavel_nao_se_desvincula(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);
        $proprio = $organizacao->vinculos()->where('cpf', $responsavel->cpf)->firstOrFail();

        $this->actingAs($responsavel)
            ->delete(route('organizacoes.vinculos.destroy', [$organizacao, $proprio]))
            ->assertForbidden();

        $this->assertModelExists($proprio);
    }

    public function test_vinculado_sai_da_organizacao(): void
    {
        $organizacao = $this->organizacaoDo($this->usuario());
        $membro = $this->usuario();
        $vinculo = $this->vincular($organizacao, $membro);

        $this->actingAs($membro)
            ->delete(route('organizacoes.vinculos.destroy', [$organizacao, $vinculo]))
            ->assertRedirect(route('organizacoes.index'))
            ->assertSessionHas('sucesso', 'Você saiu da organização.');

        $this->assertModelMissing($vinculo);
    }

    public function test_vinculado_nao_remove_outro(): void
    {
        $organizacao = $this->organizacaoDo($this->usuario());
        $membro = $this->usuario();
        $this->vincular($organizacao, $membro);
        $outro = $this->vincular($organizacao, $this->usuario());

        $this->actingAs($membro)
            ->delete(route('organizacoes.vinculos.destroy', [$organizacao, $outro]))
            ->assertForbidden();

        $this->assertModelExists($outro);
    }

    public function test_nao_vinculado_nao_remove_ninguem(): void
    {
        $organizacao = $this->organizacaoDo($this->usuario());
        $vinculo = $this->vincular($organizacao, $this->usuario());

        $this->actingAs($this->usuario())
            ->delete(route('organizacoes.vinculos.destroy', [$organizacao, $vinculo]))
            ->assertForbidden();
    }

    public function test_vinculo_de_outra_organizacao_da_404(): void
    {
        $responsavel = $this->usuario();
        $organizacao = $this->organizacaoDo($responsavel);
        $deOutra = $this->vincular($this->organizacaoDo($this->usuario()), $this->usuario());

        $this->actingAs($responsavel)
            ->delete(route('organizacoes.vinculos.destroy', [$organizacao, $deOutra]))
            ->assertNotFound();

        $this->assertModelExists($deOutra);
    }

    public function test_id_nao_numerico_da_404(): void
    {
        $this->actingAs($this->usuario())->get('/organizacoes/abc/pessoas')->assertNotFound();
    }
}
