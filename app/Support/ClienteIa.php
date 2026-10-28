<?php

namespace App\Support;

use App\Enums\ProvedorIa;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Chamadas aos provedores de IA da tela de integração (Retaguarda > Parametrização > Integração com IA): lista os modelos
 * e faz um teste curto de conexão. Toda falha vira FalhaNaIa, com o motivo já em português. A chave vai só no cabeçalho:
 * nunca na URL, no log ou na mensagem.
 */
final class ClienteIa
{
    /** Identificador de modelo aceito. Entra no caminho da URL do Gemini, então nada de barra. */
    public const string FORMATO_MODELO = '/^[A-Za-z0-9][A-Za-z0-9._:-]*$/';

    private const string URL_OPENAI = 'https://api.openai.com/v1';

    private const string URL_GEMINI = 'https://generativelanguage.googleapis.com/v1beta';

    /** Curta, para o teste gastar o mínimo e responder rápido. */
    private const string PERGUNTA_DO_TESTE = 'Responda apenas: OK';

    /**
     * Teto da resposta do teste (o mínimo que a OpenAI aceita). Modelo que raciocina pode gastar tudo pensando e voltar
     * incompleto: ainda conta como conexão funcionando, porque o provedor aceitou chave, modelo e cota.
     */
    private const int TOKENS_DO_TESTE = 16;

    /**
     * Famílias que não servem para ler texto e documentos (embeddings, voz, imagem, vídeo, busca…). Filtro por exclusão,
     * para um modelo novo de texto aparecer sozinho na lista.
     */
    private const string FORA_DA_LISTA_OPENAI = '/embedding|tts|whisper|dall-e|davinci|babbage|moderation|image|audio|realtime|transcribe|sora|search|computer-use|deep-research|instruct/i';

    private const string FORA_DA_LISTA_GEMINI = '/embedding|aqa|tts|image|live|native-audio|computer-use/i';

    /**
     * Modelos que servem para ler texto e documentos: na OpenAI, os mais novos primeiro; no Gemini, na ordem da própria lista.
     *
     * @return list<string>
     *
     * @throws FalhaNaIa
     */
    public static function modelos(ProvedorIa $provedor, #[SensitiveParameter] string $chave): array
    {
        $resposta = self::enviar($provedor, $chave, 'modelos', fn (PendingRequest $pedido): Response => match ($provedor) {
            ProvedorIa::OpenAi => $pedido->get('models'),
            ProvedorIa::Gemini => $pedido->get('models', ['pageSize' => 1000]),
        });

        $modelos = match ($provedor) {
            ProvedorIa::OpenAi => self::modelosDaOpenAi($resposta),
            ProvedorIa::Gemini => self::modelosDoGemini($resposta),
        };

        return array_values(array_filter($modelos, fn (string $modelo): bool => preg_match(self::FORMATO_MODELO, $modelo) === 1));
    }

    /**
     * Pergunta curta ao modelo, que confere de uma vez a chave, o modelo e a cota da conta.
     *
     * @return float segundos até a resposta
     *
     * @throws FalhaNaIa
     */
    public static function testar(ProvedorIa $provedor, #[SensitiveParameter] string $chave, string $modelo): float
    {
        if (preg_match(self::FORMATO_MODELO, $modelo) !== 1) {
            throw new FalhaNaIa('Escolha um modelo da lista.');
        }

        $inicio = hrtime(true);

        self::enviar($provedor, $chave, 'teste', fn (PendingRequest $pedido): Response => match ($provedor) {
            // store: false para a OpenAI não guardar o teste no histórico da conta.
            ProvedorIa::OpenAi => $pedido->post('responses', [
                'model' => $modelo,
                'input' => self::PERGUNTA_DO_TESTE,
                'max_output_tokens' => self::TOKENS_DO_TESTE,
                'store' => false,
            ]),
            ProvedorIa::Gemini => $pedido->post('models/'.rawurlencode($modelo).':generateContent', [
                'contents' => [['parts' => [['text' => self::PERGUNTA_DO_TESTE]]]],
                'generationConfig' => ['maxOutputTokens' => self::TOKENS_DO_TESTE],
            ]),
        }, $modelo);

        return (hrtime(true) - $inicio) / 1e9;
    }

    /**
     * @param  callable(PendingRequest): Response  $chamada
     *
     * @throws FalhaNaIa
     */
    private static function enviar(ProvedorIa $provedor, #[SensitiveParameter] string $chave, string $operacao, callable $chamada, ?string $modelo = null): Response
    {
        try {
            $resposta = $chamada(self::pedido($provedor, $chave));
        } catch (ConnectionException) {
            self::registrarFalha($provedor, $operacao, 'sem resposta');

            throw new FalhaNaIa("{$provedor->rotulo()} não respondeu a tempo. Confira a conexão do servidor com a internet e tente de novo.");
        }

        if ($resposta->failed()) {
            self::registrarFalha($provedor, $operacao, $resposta->status());

            throw new FalhaNaIa(self::motivo($provedor, $resposta, $modelo));
        }

        return $resposta;
    }

    private static function pedido(ProvedorIa $provedor, #[SensitiveParameter] string $chave): PendingRequest
    {
        // Abaixo dos 30 s do PHP: o gestor espera a resposta na tela.
        $pedido = Http::acceptJson()->connectTimeout(5)->timeout(20);

        return match ($provedor) {
            ProvedorIa::OpenAi => $pedido->baseUrl(self::URL_OPENAI)->withToken($chave),
            // Pelo cabeçalho, e não por ?key=, para a chave não aparecer em URL nenhuma (log de proxy, por exemplo).
            ProvedorIa::Gemini => $pedido->baseUrl(self::URL_GEMINI)->withHeaders(['x-goog-api-key' => $chave]),
        };
    }

    private static function motivo(ProvedorIa $provedor, Response $resposta, ?string $modelo): string
    {
        $nome = $provedor->rotulo();
        $status = $resposta->status();

        if ($status === 401 || self::chaveRecusadaPeloGemini($resposta)) {
            return "{$nome} recusou a chave da API. Confira se ela foi copiada inteira e se ainda está ativa.";
        }

        if ($status === 403) {
            return "{$nome} recusou o acesso desta chave. Confira as permissões dela e o projeto no painel do provedor.";
        }

        if ($status === 404 && $modelo !== null) {
            return "O modelo {$modelo} não existe ou não está liberado para esta chave.";
        }

        if ($status === 429) {
            return "{$nome}: limite de uso ou cota da conta esgotados. Confira o plano e o saldo da conta.";
        }

        if ($status >= 500) {
            return "{$nome} está com instabilidade. Tente de novo em alguns minutos.";
        }

        $detalhe = $resposta->json('error.message');

        return "{$nome} recusou o pedido (erro {$status})".(is_string($detalhe) && trim($detalhe) !== '' ? ': '.Str::limit(trim($detalhe), 200) : '.');
    }

    /** O Gemini responde chave inválida com 400 e o motivo API_KEY_INVALID, não com 401. */
    private static function chaveRecusadaPeloGemini(Response $resposta): bool
    {
        $detalhes = $resposta->json('error.details');

        return $resposta->status() === 400
            && is_array($detalhes)
            && in_array('API_KEY_INVALID', array_column(array_filter($detalhes, 'is_array'), 'reason'), true);
    }

    /**
     * @return list<string>
     *
     * @throws FalhaNaIa
     */
    private static function modelosDaOpenAi(Response $resposta): array
    {
        $modelos = $resposta->json('data');
        if (! is_array($modelos)) {
            throw self::formatoInesperado(ProvedorIa::OpenAi);
        }

        return collect($modelos)
            ->filter(fn (mixed $modelo): bool => is_array($modelo)
                && is_string($modelo['id'] ?? null)
                && preg_match(self::FORA_DA_LISTA_OPENAI, $modelo['id']) !== 1)
            ->sortByDesc(fn (array $modelo): int => (int) ($modelo['created'] ?? 0))
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     *
     * @throws FalhaNaIa
     */
    private static function modelosDoGemini(Response $resposta): array
    {
        $modelos = $resposta->json('models');
        if (! is_array($modelos)) {
            throw self::formatoInesperado(ProvedorIa::Gemini);
        }

        return collect($modelos)
            ->filter(fn (mixed $modelo): bool => is_array($modelo)
                && is_string($modelo['name'] ?? null)
                && in_array('generateContent', (array) ($modelo['supportedGenerationMethods'] ?? []), true)
                && preg_match(self::FORA_DA_LISTA_GEMINI, $modelo['name']) !== 1)
            ->map(fn (array $modelo): string => Str::after($modelo['name'], 'models/'))
            ->values()
            ->all();
    }

    private static function formatoInesperado(ProvedorIa $provedor): FalhaNaIa
    {
        self::registrarFalha($provedor, 'modelos', 'formato inesperado');

        return new FalhaNaIa("{$provedor->rotulo()} respondeu num formato inesperado. Tente de novo mais tarde.");
    }

    /** Só provedor, operação e status: nunca a chave nem o corpo da resposta. */
    private static function registrarFalha(ProvedorIa $provedor, string $operacao, int|string $status): void
    {
        Log::warning('Integração com IA: chamada ao provedor falhou.', ['provedor' => $provedor->value, 'operacao' => $operacao, 'status' => $status]);
    }
}
