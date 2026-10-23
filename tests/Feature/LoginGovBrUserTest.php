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
}
