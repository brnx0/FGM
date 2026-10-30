<?php

namespace App\Support\EditalIa;

use App\Support\FalhaNaIa;
use SensitiveParameter;

/**
 * Análise por um provedor de IA, com ou sem um PDF (o do edital, um documento da inscrição) junto do prompt: a mesma
 * entrada e a mesma saída para todos os provedores.
 */
interface AnalisadorDeEdital
{
    /**
     * Manda o prompt (e o PDF, se houver) e devolve a resposta (JSON no formato do esquema) e os tokens gastos.
     *
     * @param  string|null  $pdfBinario  Sem PDF (null), o pedido leva só o texto do prompt.
     * @param  array<string, mixed>  $esquema  Esquema da resposta no formato de EsquemaDaResposta (o do Gemini).
     * @return array{dados: array<mixed>, tokens: array{entrada: int, saida: int, total: int}}
     *
     * @throws FalhaNaIa com o motivo em português, sem a chave
     */
    public function analisar(string $modelo, #[SensitiveParameter] string $chave, ?string $pdfBinario, string $prompt, float $temperatura, array $esquema): array;
}
