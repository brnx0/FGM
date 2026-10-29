<?php

namespace Database\Factories;

use App\Models\AreaAtuacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * As 31 do levantamento já vêm da migration: as da factory usam nomes fora delas.
 *
 * @extends Factory<AreaAtuacao>
 */
class AreaAtuacaoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => 'Área de teste '.fake()->unique()->numberBetween(1, 9999),
            'ativo' => true,
        ];
    }

    public function inativa(): static
    {
        return $this->state(['ativo' => false]);
    }
}
