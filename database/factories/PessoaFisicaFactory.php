<?php

namespace Database\Factories;

use App\Enums\IdentidadeGenero;
use App\Enums\RacaCor;
use App\Enums\Territorio;
use App\Models\PessoaFisica;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PessoaFisica>
 */
class PessoaFisicaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->cadastroCompleto(),
            'nome_social' => null,
            'nome_artistico' => null,
            'foto_perfil' => null,
            'pais_origem' => 'Brasil',
            'rg' => fake()->numerify('########'),
            'documento_cpf' => 'cadastros/cpf.pdf',
            'documento_rg' => 'cadastros/rg.pdf',
            'documento_passaporte' => null,
            'documento_crnm' => null,
            'documento_outros' => null,
            'drt' => null,
            'cultura_principal_renda' => false,
            'ja_foi_avaliador' => false,
            'interesse_avaliador' => false,
            'representa_coletivo' => false,
            'telefone' => fake()->numerify('719########'),
            'telefone_adicional' => null,
            'email' => fake()->safeEmail(),
            'identidade_genero' => fake()->randomElement(IdentidadeGenero::cases()),
            'raca_cor' => fake()->randomElement(RacaCor::cases()),
            'pcd' => false,
            'cep' => fake()->numerify('40######'),
            'logradouro' => fake()->streetName(),
            'numero' => fake()->buildingNumber(),
            'bairro' => 'Centro',
            'complemento' => null,
            'cidade' => 'Salvador',
            'estado' => 'BA',
            'territorio' => fake()->randomElement(Territorio::cases()),
            'comprovante_residencia' => null,
        ];
    }
}
