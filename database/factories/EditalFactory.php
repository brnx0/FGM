<?php

namespace Database\Factories;

use App\Enums\TipoFomento;
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
            'categoria' => fake()->randomElement(['Fomento', 'Audiovisual', 'Rede municipal']),
            'tipo_fomento' => fake()->randomElement(TipoFomento::cases()),
            'divulgacao' => '<p>'.fake()->sentence(12).'</p>',
            'inscricoes_inicio' => now()->subDays(5)->toDateString(),
            'vigencia_inicio' => now()->subDays(5)->toDateString(),
            'vigencia_fim' => null,
            'inscricoes_fim' => now()->addDays(20)->toDateString(),
            'imagem' => null,
            'pdf' => 'editais/teste.pdf',
            'publicado' => true,
        ];
    }

    public function semPdf(): static
    {
        return $this->state(['pdf' => null]);
    }
}
