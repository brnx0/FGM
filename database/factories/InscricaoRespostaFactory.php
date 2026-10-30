<?php

namespace Database\Factories;

use App\Models\EditalPergunta;
use App\Models\Inscricao;
use App\Models\InscricaoResposta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InscricaoResposta>
 */
class InscricaoRespostaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inscricao_id' => Inscricao::factory(),
            'edital_pergunta_id' => EditalPergunta::factory(),
            'texto' => fake()->sentence(),
            'opcoes' => null,
            'outro' => null,
        ];
    }

    /**
     * @param  list<string>  $chaves
     */
    public function opcoes(array $chaves, ?string $outro = null): static
    {
        return $this->state(['texto' => null, 'opcoes' => $chaves, 'outro' => $outro]);
    }
}
