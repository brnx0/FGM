<?php

namespace Database\Factories;

use App\Enums\TipoCadastro;
use App\Models\PessoaFisica;
use App\Models\Role;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cpf' => fake()->unique()->numerify('###########'),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'email_verified' => true,
            'phone' => null,
            'govbr_trust_level' => null,
            'profile_completed_at' => null,
            'last_login_at' => null,
        ];
    }

    public function cadastroCompleto(): static
    {
        return $this->state(fn () => ['profile_completed_at' => now()]);
    }

    /** Com o cadastro de pessoa física e o perfil completos. */
    public function comPerfil(): static
    {
        return $this->afterCreating(function (User $user): void {
            PessoaFisica::factory()->for($user)->create();
        });
    }

    /** Cadastro finalizado como proponente. */
    public function proponente(): static
    {
        return $this->cadastroCompleto()->state(fn () => ['tipos_cadastro' => [TipoCadastro::Proponente]]);
    }

    /** Cadastro finalizado como patrocinador. */
    public function patrocinador(): static
    {
        return $this->cadastroCompleto()->state(fn () => ['tipos_cadastro' => [TipoCadastro::Patrocinador]]);
    }

    /** Cadastro finalizado com os dois tipos marcados. */
    public function proponenteEPatrocinador(): static
    {
        return $this->cadastroCompleto()->state(fn () => ['tipos_cadastro' => [TipoCadastro::Proponente, TipoCadastro::Patrocinador]]);
    }

    public function gestor(): static
    {
        return $this
            ->state(fn () => [
                'cpf' => $this->cpfValido(),
                'email' => fake()->unique()->userName().'@gestor.test',
                'password' => '123456',
            ])
            ->afterCreating(function (User $user) {
                $papel = Role::firstOrCreate(
                    ['slug' => Role::ADMINISTRADOR],
                    ['nome' => 'Administrador'],
                );
                $user->papeis()->syncWithoutDetaching([$papel->id]);
            });
    }

    private function cpfValido(): string
    {
        do {
            $cpf = fake()->unique()->numerify('###########');
        } while (! Cpf::valido($cpf));

        return $cpf;
    }
}
