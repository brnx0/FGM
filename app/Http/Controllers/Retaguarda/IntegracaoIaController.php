<?php

namespace App\Http\Controllers\Retaguarda;

use App\Enums\ProvedorIa;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\SalvarIntegracaoIaRequest;
use App\Http\Requests\Retaguarda\SalvarRedundanciaIaRequest;
use App\Models\ConexaoIa;
use App\Models\ConfiguracaoIa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Integração com IA: chaves e modelos dos provedores das validações automáticas, com o principal e a redundância. A
 * importação de edital usa os mesmos provedores.
 */
class IntegracaoIaController extends Controller
{
    public function edit(): Response
    {
        $configuracao = ConfiguracaoIa::atual();
        $conexoes = ConexaoIa::query()->get()->keyBy(fn (ConexaoIa $conexao): string => $conexao->provedor->value);

        return Inertia::render('Retaguarda/IntegracaoIa', [
            'configuracao' => [
                'ativo' => $configuracao->ativo,
                'principal' => $configuracao->provedor_principal?->value,
                'redundancia' => $configuracao->provedor_redundancia?->value,
            ],
            // A chave nunca vem para a tela: só a máscara, para o gestor saber qual está guardada.
            'provedores' => array_map(fn (ProvedorIa $provedor): array => [
                'valor' => $provedor->value,
                'rotulo' => $provedor->rotulo(),
                'ajudaChave' => $provedor->ajudaChave(),
                'modelo' => $conexoes->get($provedor->value)?->modelo,
                'chave' => $conexoes->get($provedor->value)?->chaveMascarada(),
            ], ProvedorIa::cases()),
        ]);
    }

    public function update(SalvarIntegracaoIaRequest $request): RedirectResponse
    {
        $provedor = $request->provedor();
        $configuracao = ConfiguracaoIa::atual();
        $anterior = $configuracao->provedor_principal;
        // Promover a redundância troca os dois de lugar: o principal anterior tem chave e modelo, então segue de reserva.
        $trocaDeLugar = $anterior !== null && $configuracao->provedor_redundancia === $provedor;

        DB::transaction(function () use ($request, $provedor, $configuracao, $anterior, $trocaDeLugar): void {
            ConexaoIa::salvar($provedor, $request->validated('modelo'), $request->chaveNova());

            $configuracao->fill([
                'ativo' => $request->boolean('ativo'),
                'provedor_principal' => $provedor,
                'provedor_redundancia' => $trocaDeLugar ? $anterior : $configuracao->provedor_redundancia,
            ])->save();
        });

        $mensagem = 'Configuração da IA salva.';
        if ($trocaDeLugar) {
            $mensagem .= " {$provedor->rotulo()} passou a ser o principal e {$anterior->rotulo()}, a redundância.";
        }

        return redirect()->route('retaguarda.integracao-ia.edit')->with('sucesso', $mensagem);
    }

    public function redundancia(SalvarRedundanciaIaRequest $request): RedirectResponse
    {
        $provedor = $request->provedor();

        DB::transaction(function () use ($request, $provedor): void {
            if ($provedor !== null) {
                ConexaoIa::salvar($provedor, $request->validated('modelo'), $request->chaveNova());
            }

            ConfiguracaoIa::atual()->fill(['provedor_redundancia' => $provedor])->save();
        });

        return redirect()->route('retaguarda.integracao-ia.edit')
            ->with('sucesso', $provedor === null ? 'Redundância retirada.' : 'Redundância salva.');
    }
}
