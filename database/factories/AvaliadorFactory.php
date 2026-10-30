<?php

namespace Database\Factories;

use App\Models\Avaliador;
use App\Support\Cpf;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Avaliador>
 */
class AvaliadorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => fake()->name(),
            'data_nascimento' => fake()->dateTimeBetween('-70 years', '-20 years')->format('Y-m-d'),
            'cpf' => $this->cpfValido(),
            'email' => fake()->unique()->safeEmail(),
            'ativo' => true,
        ];
    }

    public function inativo(): static
    {
        return $this->state(['ativo' => false]);
    }

    private function cpfValido(): string
    {
        do {
            $cpf = fake()->unique()->numerify('###########');
        } while (! Cpf::valido($cpf));

        return $cpf;
    }
}
