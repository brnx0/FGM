<?php

namespace Database\Factories;

use App\Models\Edital;
use App\Models\EditalChamada;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditalChamada>
 */
class EditalChamadaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edital_id' => Edital::factory(),
            'nome' => 'Chamada '.fake()->numberBetween(1, 9),
            'inicio' => now()->subDays(5)->toDateString(),
            'fim' => now()->addDays(20)->toDateString(),
            'ordem' => 1,
        ];
    }
}
