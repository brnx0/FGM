<?php

namespace Database\Factories;

use App\Enums\NaturezaProponente;
use App\Models\Edital;
use App\Models\EditalCategoria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditalCategoria>
 */
class EditalCategoriaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'edital_id' => Edital::factory(),
            'ordem' => 1,
            'nome' => 'Categoria '.fake()->unique()->numberBetween(1, 9999),
            'valor_centavos' => 5000000,
            'vagas' => 10,
            'naturezas' => [NaturezaProponente::PessoaFisica],
        ];
    }
}
