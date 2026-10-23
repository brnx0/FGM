<?php

namespace Database\Factories;

use App\Models\Edital;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Edital>
 */
class EditalFactory extends Factory
{
    public function definition(): array
    {
        $titulo = fake()->unique()->sentence(3);

        return [
            'titulo' => $titulo,
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 999999),
            'resumo' => fake()->sentence(12),
            'categoria' => fake()->randomElement(['Fomento', 'Audiovisual', 'Rede municipal']),
            'inscricoes_inicio' => now()->subDays(5)->toDateString(),
            'inscricoes_fim' => now()->addDays(20)->toDateString(),
            'imagem' => null,
            'publicado' => true,
        ];
    }
}
