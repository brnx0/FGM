<?php

namespace Database\Factories;

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
