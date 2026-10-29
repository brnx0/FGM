<?php

namespace Database\Factories;

use App\Models\Edital;
use App\Models\EditalCriterio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditalCriterio>
 */
class EditalCriterioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edital_id' => Edital::factory(),
            'nome' => fake()->sentence(4),
            'descricao' => fake()->sentence(10),
            'peso' => null,
            'pontos_maximos' => fake()->randomElement([10, 20, 30]),
            'todas_categorias' => true,
            'ordem' => 1,
        ];
    }
}
