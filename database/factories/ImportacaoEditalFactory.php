<?php

namespace Database\Factories;

use App\Enums\ProvedorIa;
use App\Enums\SituacaoImportacao;
use App\Models\Edital;
use App\Models\ImportacaoEdital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportacaoEdital>
 */
class ImportacaoEditalFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->gestor(),
            'situacao' => SituacaoImportacao::Analisando,
            'pdf' => ImportacaoEdital::PASTA.'/'.fake()->uuid().'.pdf',
            'pdf_nome' => 'edital.pdf',
            'provedor' => ProvedorIa::Gemini,
            'modelo' => 'gemini-2.5-pro',
            'iniciada_em' => now(),
            'execucao' => ImportacaoEdital::novaExecucao(),
        ];
    }

    public function comErro(string $mensagem = 'Google Gemini está com instabilidade. Tente de novo em alguns minutos.'): static
    {
        return $this->state(['situacao' => SituacaoImportacao::Erro, 'erro' => $mensagem]);
    }

    public function concluida(): static
    {
        return $this->state([
            'situacao' => SituacaoImportacao::Concluida,
            'edital_id' => Edital::factory(),
            'confiabilidade' => ['geral' => 80, 'blocos' => []],
            'atencao' => [],
        ]);
    }
}
