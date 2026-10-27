<?php

namespace Database\Factories;

use App\Models\Organizacao;
use App\Models\VinculoOrganizacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VinculoOrganizacao>
 */
class VinculoOrganizacaoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organizacao_id' => Organizacao::factory(),
            'cpf' => fake()->unique()->numerify('###########'),
            'nome' => fake()->name(),
            'responsavel' => false,
        ];
    }
}
