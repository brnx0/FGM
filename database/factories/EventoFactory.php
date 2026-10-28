<?php

namespace Database\Factories;

use App\Models\Evento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Evento>
 */
class EventoFactory extends Factory
{
    public function definition(): array
    {
        $titulo = fake()->unique()->sentence(3);

        return [
            'titulo' => $titulo,
            'slug' => Str::slug($titulo).'-'.fake()->unique()->numberBetween(1, 999999),
            'resumo' => fake()->sentence(12),
            // Ainda vai acontecer: o evento padrão aparece na agenda da home.
            'data_realizacao' => now()->addMonths(2)->toDateString(),
            'local' => 'Salvador/BA',
            'imagem' => null,
            'publicado' => true,
        ];
    }
}
