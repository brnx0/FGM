<?php

namespace Database\Factories;

use App\Enums\ProvedorIa;
use App\Models\ConfiguracaoIa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracaoIa>
 */
class ConfiguracaoIaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ativo' => true,
            'provedor_principal' => ProvedorIa::OpenAi,
            'provedor_redundancia' => null,
        ];
    }

    /** Google Gemini como redundância da OpenAI. */
    public function comRedundancia(): static
    {
        return $this->state(['provedor_redundancia' => ProvedorIa::Gemini]);
    }
}
