<?php

namespace App\Support\EditalIa;

use App\Enums\ProvedorIa;
use App\Support\ClienteIa;
use App\Support\FalhaNaIa;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use SensitiveParameter;

/**
 * O que os analisadores por IA têm em comum: o pedido com tempo de até 5 minutos, a repetição sem a temperatura
 * quando o modelo não a aceita, a leitura da resposta como JSON e as falhas em português (as mensagens da
 * Integração com IA, ver ClienteIa::motivo). A chave vai só no cabeçalho e sai de qualquer mensagem.
 */
abstract class AnalisadorPorHttp implements AnalisadorDeEdital
{
    /** A análise de um PDF grande pode levar minutos. */
    protected const int TEMPO_LIMITE = 300;

    abstract protected function provedor(): ProvedorIa;

    /**
     * Corpo do pedido; sem a parte do arquivo quando o PDF é null e sem temperatura quando ela é null.
     *
     * @param  array<string, mixed>  $esquema
     * @return array<string, mixed>
     */
    abstract protected function corpo(string $modelo, ?string $pdfBinario, string $prompt, ?float $temperatura, array $esquema): array;

    /**
     * @param  array<string, mixed>  $corpo
     */
    abstract protected function enviar(PendingRequest $pedido, string $modelo, #[SensitiveParameter] string $chave, array $corpo): Response;

    /** O provedor recusou o pedido porque o modelo não aceita a temperatura. */
    abstract protected function temperaturaRecusada(Response $resposta): bool;

    /** O texto gerado pelo modelo (o JSON da resposta), ou null se não houver. */
    abstract protected function texto(Response $resposta): mixed;

    /**
     * @return array{entrada: int, saida: int, total: int}
     */
    abstract protected function tokens(Response $resposta): array;

    public function analisar(string $modelo, #[SensitiveParameter] string $chave, ?string $pdfBinario, string $prompt, float $temperatura, array $esquema): array
    {
        $resposta = $this->chamar($modelo, $chave, $this->corpo($modelo, $pdfBinario, $prompt, $temperatura, $esquema));

        if ($resposta->failed() && $this->temperaturaRecusada($resposta)) {
            $resposta = $this->chamar($modelo, $chave, $this->corpo($modelo, $pdfBinario, $prompt, null, $esquema));
        }
        // Um PDF grande pesa três vezes (binário, base64 e o JSON do pedido): solta o que já não serve.
        unset($pdfBinario);

        if ($resposta->failed()) {
            $this->registrarFalha($resposta->status());

            throw new FalhaNaIa($this->motivo($this->semChave($resposta, $chave), $modelo));
        }

        $texto = $this->texto($resposta);

        try {
            $dados = is_string($texto) ? json_decode($texto, true, 512, JSON_THROW_ON_ERROR) : null;
        } catch (JsonException) {
            $dados = null;
        }

        if (! is_array($dados)) {
            $this->registrarFalha('formato inesperado');

            throw new FalhaNaIa("{$this->provedor()->rotulo()} respondeu fora do formato esperado.");
        }

        return ['dados' => $dados, 'tokens' => $this->tokens($resposta)];
    }

    /**
     * @param  array<string, mixed>  $corpo
     *
     * @throws FalhaNaIa
     */
    private function chamar(string $modelo, #[SensitiveParameter] string $chave, array $corpo): Response
    {
        $nome = $this->provedor()->rotulo();

        try {
            return $this->enviar(Http::acceptJson()->connectTimeout(15)->timeout(self::TEMPO_LIMITE), $modelo, $chave, $corpo);
        } catch (ConnectionException $falha) {
            $esgotou = str_contains($falha->getMessage(), 'cURL error 28') || str_contains(strtolower($falha->getMessage()), 'timed out');
            $this->registrarFalha($esgotou ? 'tempo esgotado' : 'sem conexão');

            throw new FalhaNaIa($esgotou
                ? "{$nome} demorou demais para responder e a chamada foi interrompida. Tente de novo."
                : "{$nome} não respondeu a tempo. Confira a conexão do servidor com a internet e tente de novo.");
        }
    }

    private function motivo(Response $resposta, string $modelo): string
    {
        $corpo = strtolower($resposta->body());

        if ($resposta->status() === 413 || ($resposta->status() === 400 && (str_contains($corpo, 'payload size') || str_contains($corpo, 'too large')))) {
            return "{$this->provedor()->rotulo()}: o PDF é grande demais para ser analisado. Envie um arquivo menor.";
        }

        return ClienteIa::motivo($this->provedor(), $resposta, $modelo);
    }

    /**
     * A resposta com a chave trocada por [chave] no corpo. O motivo pode trazer o texto do provedor, cortado: a troca
     * vem antes do corte, para nenhum pedaço da chave sobrar na mensagem gravada.
     */
    private function semChave(Response $resposta, #[SensitiveParameter] string $chave): Response
    {
        $corpo = $resposta->body();
        if ($chave === '' || ! str_contains($corpo, $chave)) {
            return $resposta;
        }

        return new Response($resposta->toPsrResponse()->withBody(Utils::streamFor(str_replace($chave, '[chave]', $corpo))));
    }

    /** Só provedor e status: nunca a chave nem o corpo da resposta. */
    private function registrarFalha(int|string $status): void
    {
        Log::warning('Análise por IA: chamada ao provedor falhou.', ['provedor' => $this->provedor()->value, 'status' => $status]);
    }
}
