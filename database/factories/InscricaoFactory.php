<?php

namespace Database\Factories;

use App\Enums\NaturezaProponente;
use App\Enums\SituacaoInscricao;
use App\Models\Edital;
use App\Models\Inscricao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscricao>
 */
class InscricaoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'edital_id' => Edital::factory(),
            'user_id' => User::factory(),
            'documento_cadastro' => fake()->numerify('###########'),
            'natureza' => NaturezaProponente::PessoaFisica,
            'situacao' => SituacaoInscricao::Rascunho,
        ];
    }

    public function finalizada(): static
    {
        return $this->state([
            'situacao' => SituacaoInscricao::Finalizada,
            'numero' => fake()->unique()->numerify('INS-########'),
            'finalizada_em' => now(),
        ]);
    }
}
