<?php

namespace App\Support\EditalIa;

use App\Support\FalhaNaIa;
use SensitiveParameter;

/** Análise do PDF de um edital por um provedor de IA: a mesma entrada e a mesma saída para todos os provedores. */
interface AnalisadorDeEdital
{
    /**
     * Manda o PDF e o prompt e devolve a resposta (JSON no formato do esquema) e os tokens gastos.
     *
     * @param  array<string, mixed>  $esquema  Esquema da resposta no formato de EsquemaDaResposta (o do Gemini).
     * @return array{dados: array<mixed>, tokens: array{entrada: int, saida: int, total: int}}
     *
     * @throws FalhaNaIa com o motivo em português, sem a chave
     */
    public function analisar(string $modelo, #[SensitiveParameter] string $chave, string $pdfBinario, string $prompt, float $temperatura, array $esquema): array;
}
