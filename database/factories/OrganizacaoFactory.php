<?php

namespace Database\Factories;

use App\Models\Organizacao;
use App\Models\User;
use App\Models\VinculoOrganizacao;
use App\Support\Cnpj;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organizacao>
 */
class OrganizacaoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cnpj' => $this->cnpjValido(),
            'razao_social' => fake()->company(),
            'nome_fantasia' => null,
            'natureza_juridica' => '2062',
            'mei' => false,
            'finalidade_lucrativa' => true,
            'municipio_ibge' => '2927408',
            'cidade' => 'Salvador',
            'uf' => 'BA',
        ];
    }

    /** Organização com o usuário como responsável (vínculo marcado). */
    public function comResponsavel(User $user): static
    {
        return $this->afterCreating(function (Organizacao $organizacao) use ($user) {
            VinculoOrganizacao::factory()->create([
                'organizacao_id' => $organizacao->id,
                'cpf' => $user->cpf,
                'nome' => $user->name,
                'responsavel' => true,
            ]);
        });
    }

    private function cnpjValido(): string
    {
        do {
            $base = fake()->unique()->numerify('############');
            $cnpj = $base.$this->digitoVerificador($base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
            $cnpj .= $this->digitoVerificador($cnpj, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
        } while (! Cnpj::valido($cnpj));

        return $cnpj;
    }

    /** @param  list<int>  $pesos */
    private function digitoVerificador(string $base, array $pesos): int
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (int) $base[$i] * $peso;
        }

        $resto = $soma % 11;

        return $resto < 2 ? 0 : 11 - $resto;
    }
}
