<?php

namespace App\Support\EditalIa;

use App\Support\FalhaNaIa;
use SensitiveParameter;

/**
 * Análise por um provedor de IA, com ou sem um arquivo (o PDF do edital, o PDF ou a imagem de um documento da
 * inscrição) junto do prompt: a mesma entrada e a mesma saída para todos os provedores.
 */
interface AnalisadorDeEdital
{
    /**
     * Manda o prompt (e o arquivo, se houver) e devolve a resposta (JSON no formato do esquema) e os tokens gastos.
     *
     * @param  string|null  $arquivoBinario  Sem arquivo (null), o pedido leva só o texto do prompt.
     * @param  array<string, mixed>  $esquema  Esquema da resposta no formato de EsquemaDaResposta (o do Gemini).
     * @param  string  $tipoDoArquivo  application/pdf, image/jpeg ou image/png.
     * @return array{dados: array<mixed>, tokens: array{entrada: int, saida: int, total: int}}
     *
     * @throws FalhaNaIa com o motivo em português, sem a chave
     */
    public function analisar(string $modelo, #[SensitiveParameter] string $chave, ?string $arquivoBinario, string $prompt, float $temperatura, array $esquema, string $tipoDoArquivo = 'application/pdf'): array;
}
