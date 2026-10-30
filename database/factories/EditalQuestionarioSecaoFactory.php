<?php

namespace Database\Factories;

use App\Models\Edital;
use App\Models\EditalQuestionarioSecao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditalQuestionarioSecao>
 */
class EditalQuestionarioSecaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edital_id' => Edital::factory(),
            'titulo' => fake()->words(3, true),
            'descricao' => null,
            'ordem' => 1,
        ];
    }
}
