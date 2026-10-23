<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_logout_encerra_sessao(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }
}
