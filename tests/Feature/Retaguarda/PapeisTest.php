<?php

namespace Tests\Feature\Retaguarda;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PrimeiroGestorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PapeisTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_admin_com_papel_e_senha(): void
    {
        $this->seed(PrimeiroGestorSeeder::class);

        $admin = User::where('email', 'admin@admin')->firstOrFail();
        $this->assertSame('00000000000', $admin->cpf);
        $this->assertSame('Administrador', $admin->name);
        $this->assertTrue(Hash::check('123456', $admin->password));
        $this->assertTrue($admin->temPapel(Role::ADMINISTRADOR));
        $this->assertTrue($admin->ehGestor());
    }

    public function test_seeder_e_idempotente_e_nao_redefine_senha(): void
    {
        $this->seed(PrimeiroGestorSeeder::class);
        User::where('email', 'admin@admin')->firstOrFail()->update(['password' => 'outra']);

        $this->seed(PrimeiroGestorSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@admin')->count());
        $this->assertSame(1, Role::where('slug', Role::ADMINISTRADOR)->count());
        $admin = User::where('email', 'admin@admin')->firstOrFail();
        $this->assertTrue(Hash::check('outra', $admin->password));
        $this->assertSame(1, $admin->papeis()->count());
    }

    public function test_seeder_nao_roda_em_producao(): void
    {
        $this->app['env'] = 'production';

        $this->seed(PrimeiroGestorSeeder::class);

        $this->assertTrue(User::where('email', 'admin@admin')->doesntExist());
    }

    public function test_proponente_sem_papel_nao_e_gestor(): void
    {
        $this->assertFalse(User::factory()->create()->ehGestor());
    }

    public function test_gestor_inativo_nao_e_gestor(): void
    {
        $this->assertFalse(User::factory()->gestor()->create(['ativo' => false])->ehGestor());
    }

    public function test_email_e_normalizado_e_vazio_vira_null(): void
    {
        $this->assertSame('a@b.com', User::factory()->create(['email' => '  A@B.Com '])->email);
        $this->assertNull(User::factory()->create(['email' => '   '])->email);
    }
}
