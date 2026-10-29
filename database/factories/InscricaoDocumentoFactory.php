<?php

namespace Database\Factories;

use App\Enums\ProvedorIa;
use App\Enums\SituacaoConferencia;
use App\Models\EditalDocumento;
use App\Models\Inscricao;
use App\Models\InscricaoDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InscricaoDocumento>
 */
class InscricaoDocumentoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inscricao_id' => Inscricao::factory(),
            'edital_documento_id' => EditalDocumento::factory(),
            'arquivo' => Inscricao::PASTA.'/'.fake()->uuid().'.pdf',
            'arquivo_nome' => 'documento.pdf',
            'situacao' => SituacaoConferencia::Enviado,
        ];
    }

    public function conferindo(): static
    {
        return $this->state([
            'situacao' => SituacaoConferencia::Conferindo,
            'provedor' => ProvedorIa::Gemini,
            'modelo' => 'gemini-2.5-pro',
            'iniciada_em' => now(),
            'execucao' => fake()->uuid(),
        ]);
    }
}
