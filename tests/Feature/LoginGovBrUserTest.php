<?php

namespace Tests\Feature;

use App\Actions\LoginGovBrUser;
use App\Auth\GovBr\GovBrIdentity;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginGovBrUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_primeiro_acesso_cria_usuario_e_loga(): void
    {
        $user = app(LoginGovBrUser::class)->handle(
            new GovBrIdentity('52998224725', 'Maria Souza', 'maria@example.com', true, '71999990000', '2'),
        );

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertSame('52998224725', $user->fresh()->cpf);
        $this->assertTrue($user->fresh()->email_verified);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_reentrada_atualiza_sem_duplicar_e_aceita_email_nulo(): void
    {
        $acao = app(LoginGovBrUser::class);
        $acao->handle(new GovBrIdentity('52998224725', 'Nome A', 'x@example.com', true, null));
        $acao->handle(new GovBrIdentity('52998224725', 'Nome B', null, false, null));

        $this->assertSame(1, User::count());
        $user = User::first();
        $this->assertSame('Nome B', $user->name);
        $this->assertNull($user->email);
    }

    public function test_email_de_outro_usuario_nao_derruba_o_login(): void
    {
        $outro = User::factory()->create(['cpf' => '11144477735', 'email' => 'ana@exemplo.com']);

        $user = app(LoginGovBrUser::class)->handle(
            new GovBrIdentity('52998224725', 'Ana', 'Ana@Exemplo.com ', true, null),
        );

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->email);
        $this->assertSame('ana@exemplo.com', $outro->fresh()->email);
    }

    public function test_cpf_com_zero_a_esquerda_casa_usuario_existente(): void
    {
        User::factory()->create(['cpf' => '01234567890']);

        app(LoginGovBrUser::class)->handle(
            new GovBrIdentity(Cpf::normalizar('1234567890'), 'Fulano', null, false, null),
        );

        $this->assertSame(1, User::count());
    }

    public function test_destino_pos_login_e_cadastro(): void
    {
        $acao = app(LoginGovBrUser::class);
        $user = User::factory()->create(['profile_completed_at' => null]);
        $this->assertSame(route('cadastro'), $acao->destinoPosLogin($user));

        $user->forceFill(['profile_completed_at' => now()])->save();
        $this->assertSame(route('home'), $acao->destinoPosLogin($user));
    }

    public function test_dados_do_govbr_acima_do_limite_nao_estouram_a_coluna(): void
    {
        $user = app(LoginGovBrUser::class)->handle(new GovBrIdentity(
            '52998224725', str_repeat('N', 400), str_repeat('e', 250).'@example.com', true, null,
        ));

        $this->assertSame(255, mb_strlen($user->fresh()->name));
        $this->assertNull($user->fresh()->email);
    }

    public function test_usuario_com_papel_inativo_nao_tem_nome_e_email_sobrescritos(): void
    {
        $user = User::factory()->gestor()->create([
            'cpf' => '52998224725',
            'name' => 'Nome da Retaguarda',
            'email' => 'retaguarda@fgm.test',
            'ativo' => false,
        ]);

        app(LoginGovBrUser::class)->handle(
            new GovBrIdentity('52998224725', 'Nome do gov.br', 'govbr@example.com', true, null),
        );

        $user->refresh();
        $this->assertSame('Nome da Retaguarda', $user->name);
        $this->assertSame('retaguarda@fgm.test', $user->email);
    }
}
