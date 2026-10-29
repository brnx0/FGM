<?php

namespace Database\Factories;

use App\Models\Edital;
use App\Models\EditalRequisito;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditalRequisito>
 */
class EditalRequisitoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edital_id' => Edital::factory(),
            'descricao' => fake()->sentence(8),
            'ordem' => 1,
        ];
    }
}
