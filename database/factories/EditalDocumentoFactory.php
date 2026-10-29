<?php

namespace Database\Factories;

use App\Enums\EtapaDocumento;
use App\Models\Edital;
use App\Models\EditalDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EditalDocumento>
 */
class EditalDocumentoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edital_id' => Edital::factory(),
            'nome' => fake()->words(3, true),
            'orientacao' => fake()->sentence(8),
            'etapa' => EtapaDocumento::Inscricao,
            'obrigatorio' => true,
            'naturezas' => null,
            'todas_categorias' => true,
            'ordem' => 1,
        ];
    }
}
