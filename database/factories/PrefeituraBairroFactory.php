<?php

namespace Database\Factories;

use App\Models\PrefeituraBairro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * As dez oficiais já vêm da migration: as da factory usam números e nomes fora delas.
 *
 * @extends Factory<PrefeituraBairro>
 */
class PrefeituraBairroFactory extends Factory
{
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(11, 99);

        return [
            'numero' => $numero,
            'nome' => "Prefeitura-Bairro de teste {$numero}",
            'codigo' => "teste_{$numero}",
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(['ativo' => false]);
    }
}
