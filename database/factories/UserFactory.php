<?php

namespace Database\Factories;

use App\Models\User;
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
}
