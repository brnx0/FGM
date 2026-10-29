<?php

namespace Database\Factories;

use App\Models\EditalCategoria;
use App\Models\EditalCategoriaItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditalCategoriaItem>
 */
class EditalCategoriaItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edital_categoria_id' => EditalCategoria::factory(),
            'nome' => fake()->words(2, true),
            'valor_centavos' => fake()->numberBetween(5, 50) * 100_000,
            'vagas' => 1,
            'suplentes' => null,
            'ordem' => 1,
        ];
    }
}
