<?php

namespace Database\Factories;

use App\Enums\AvatarVLibras;
use App\Enums\LadoVLibras;
use App\Models\ConfiguracaoVLibras;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracaoVLibras>
 */
class ConfiguracaoVLibrasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ativo' => true,
            'lado' => LadoVLibras::Direita,
            'avatar' => AvatarVLibras::Icaro,
        ];
    }

    /** Widget fora do portal. */
    public function desligado(): static
    {
        return $this->state(['ativo' => false]);
    }
}
