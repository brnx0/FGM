<?php

namespace Database\Factories;

use App\Models\Edital;
use App\Models\EditalCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditalCategoria>
 */
class EditalCategoriaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edital_id' => Edital::factory(),
            'nome' => fake()->words(3, true),
            'descricao' => fake()->sentence(10),
            'valor_centavos' => fake()->numberBetween(10, 200) * 100_000,
            'vagas' => fake()->numberBetween(1, 20),
            'ordem' => 1,
        ];
    }
}
