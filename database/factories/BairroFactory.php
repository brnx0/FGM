<?php

namespace Database\Factories;

use App\Models\Bairro;
use App\Models\PrefeituraBairro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bairro>
 */
class BairroFactory extends Factory
{
    public function definition(): array
    {
        return [
            'prefeitura_bairro_id' => PrefeituraBairro::factory(),
            'nome' => 'Bairro de teste '.fake()->unique()->numberBetween(1, 99999),
        ];
    }
}
