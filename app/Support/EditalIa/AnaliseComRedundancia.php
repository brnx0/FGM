<?php

namespace App\Support\EditalIa;

use App\Enums\ProvedorIa;
use App\Models\ConexaoIa;
use App\Support\FalhaNaIa;

/**
 * Análise por IA pelos provedores dados (os da importação, ConfiguracaoIa::provedoresDaImportacao), na ordem: o
 * principal e, se ele falhar (chave recusada, limite, serviço fora, tempo esgotado, resposta fora do formato, inclusive
 * a que a conferência de quem chama recusar), a redundância. Na importação do edital, a conferência é a dos blocos da
 * raiz (EditalDaResposta::conferirRaiz); campos faltando dentro dos blocos não trocam de provedor: viram pontos de
 * atenção. Resolvida pelo container, para os testes poderem trocar os analisadores.
 */
class AnaliseComRedundancia
{
    /** O que a equipe vê quando a IA não responde ou não está configurada; o detalhe técnico vai depois, para o administrador. */
    public const string INDISPONIVEL = 'Serviço de IA indisponível no momento. Caso persista, contate o administrador.';

    public const string SEM_CONFIGURACAO = self::INDISPONIVEL;

    public function __construct(private AnalisadorGemini $gemini, private AnalisadorOpenAi $openAi) {}

    /**
     * @param  list<array{provedor: ProvedorIa, conexao: ConexaoIa}>  $provedores
     * @param  string|null  $pdfBinario  Sem PDF (null), o pedido leva só o texto do prompt.
     * @param  array<string, mixed>  $esquema  No formato de EsquemaDaResposta.
     * @param  (callable(array<mixed>): void)|null  $conferirResposta  Lança RespostaInvalida para a resposta passar ao
     *                                                                 provedor seguinte; null aceita qualquer JSON.
     * @return array{dados: array<mixed>, tokens: array{entrada: int, saida: int, total: int}, provedor: ProvedorIa, modelo: string, falhas: list<array{provedor: ProvedorIa, motivo: string}>}
     *
     * @throws FalhaNaIa sem provedores, ou com o motivo de cada um quando nenhum responde
     */
    public function analisar(array $provedores, ?string $pdfBinario, string $prompt, float $temperatura, array $esquema, ?callable $conferirResposta = null): array
    {
        if ($provedores === []) {
            throw new FalhaNaIa(self::SEM_CONFIGURACAO);
        }

        $falhas = [];

        foreach ($provedores as ['provedor' => $provedor, 'conexao' => $conexao]) {
            $modelo = (string) $conexao->modelo;

            try {
                $resultado = $this->analisador($provedor)
                    ->analisar($modelo, (string) $conexao->chaveGuardada(), $pdfBinario, $prompt, $temperatura, $esquema);
                if ($conferirResposta !== null) {
                    $conferirResposta($resultado['dados']);
                }
            } catch (FalhaNaIa $falha) {
                $falhas[] = ['provedor' => $provedor, 'motivo' => $falha->getMessage()];

                continue;
            } catch (RespostaInvalida) {
                $falhas[] = ['provedor' => $provedor, 'motivo' => "{$provedor->rotulo()} respondeu fora do formato esperado."];

                continue;
            }

            return [...$resultado, 'provedor' => $provedor, 'modelo' => $modelo, 'falhas' => $falhas];
        }

        throw new FalhaNaIa(self::INDISPONIVEL.' Detalhe: '.(count($falhas) === 1
            ? $falhas[0]['motivo']
            : implode(' ', array_map(
                fn (array $falha): string => "{$falha['provedor']->rotulo()}: ".rtrim($falha['motivo'], '.').'.',
                $falhas,
            ))));
    }

    private function analisador(ProvedorIa $provedor): AnalisadorDeEdital
    {
        return match ($provedor) {
            ProvedorIa::Gemini => $this->gemini,
            ProvedorIa::OpenAi => $this->openAi,
        };
    }
}
