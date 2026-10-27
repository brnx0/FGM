<?php

namespace Database\Factories;

use App\Models\Organizacao;
use App\Models\RepresentanteLegal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepresentanteLegal>
 */
class RepresentanteLegalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organizacao_id' => Organizacao::factory(),
            'ordem' => 1,
            'cpf' => fake()->unique()->numerify('###########'),
            'nome' => fake()->name(),
        ];
    }
}
