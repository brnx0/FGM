<?php

namespace Database\Factories;

use App\Enums\NaturezaProponente;
use App\Models\Edital;
use App\Models\EditalDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditalDocumento>
 */
class EditalDocumentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'edital_id' => Edital::factory(),
            'ordem' => 1,
            'nome' => 'Documento '.fake()->unique()->numberBetween(1, 9999),
            'orientacao' => null,
            'obrigatorio' => true,
            'naturezas' => [NaturezaProponente::PessoaFisica],
            'todas_categorias' => true,
            'tamanho_max_mb' => 2,
        ];
    }
}
