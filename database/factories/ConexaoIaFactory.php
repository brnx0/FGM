<?php

namespace Database\Factories;

use App\Enums\ProvedorIa;
use App\Models\ConexaoIa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ConexaoIa>
 */
class ConexaoIaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provedor' => ProvedorIa::OpenAi,
            'modelo' => 'gpt-5.4',
            'chave_api' => 'sk-teste-'.Str::random(48),
        ];
    }

    /** Google Gemini. A chave de teste foge do formato real (AIza…), que os scanners de segredo do GitHub barram. */
    public function gemini(): static
    {
        return $this->state([
            'provedor' => ProvedorIa::Gemini,
            'modelo' => 'gemini-3.8-flash',
            'chave_api' => 'gemini-teste-'.Str::random(30),
        ]);
    }
}
