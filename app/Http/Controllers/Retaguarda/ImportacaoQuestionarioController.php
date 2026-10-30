<?php

namespace App\Http\Controllers\Retaguarda;

use App\Enums\ProvedorIa;
use App\Enums\SituacaoImportacao;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retaguarda\ImportarQuestionarioRequest;
use App\Models\ConexaoIa;
use App\Models\ConfiguracaoIa;
use App\Models\Edital;
use App\Models\ImportacaoEdital;
use App\Models\ImportacaoQuestionario;
use App\Support\ArquivoArmazenado;
use App\Support\EditalIa\AnaliseComRedundancia;
use App\Support\EditalIa\LancadorDeAnaliseDoQuestionario;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Importação do questionário do edital a partir do espelho do formulário (PDF ou Word .docx) com IA. A análise roda
 * em segundo plano (LancadorDeAnaliseDoQuestionario) e, concluída, substitui o questionário do edital; a tela consulta
 * a situação. Enviar só com o provedor principal configurado na Integração com IA, com modelo e chave.
 */
class ImportacaoQuestionarioController extends Controller implements HasMiddleware
{
    public const string ERRO_SEM_CATEGORIA = 'Cadastre ao menos uma categoria e salve o edital antes de importar o questionário.';

    public const string ERRO_EM_ANDAMENTO = 'Já há uma importação do questionário em análise para este edital.';

    public const string ERRO_CONFIRMAR = 'Confirme que o questionário atual será substituído.';

    /** Atributo do pedido com o provedor principal da importação, guardado pelo middleware. */
    private const string PRINCIPAL = 'importacao.principal';

    /**
     * @return list<Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next): Response {
                $provedores = ConfiguracaoIa::atual()->provedoresDaImportacao();

                // Recusa antes de guardar o arquivo e de qualquer chamada.
                if ($provedores === []) {
                    return back()->withErrors(['configuracao' => AnaliseComRedundancia::SEM_CONFIGURACAO]);
                }

                $request->attributes->set(self::PRINCIPAL, $provedores[0]);

                return $next($request);
            }, only: ['store']),
        ];
    }

    /**
     * Confere e cria a importação com a linha do edital travada (a mesma trava da finalização da inscrição): de dois
     * envios ao mesmo tempo, só um fica em análise. As recusas vêm antes de guardar o arquivo.
     */
    public function store(ImportarQuestionarioRequest $request, Edital $edital, LancadorDeAnaliseDoQuestionario $lancador): RedirectResponse
    {
        $arquivo = $request->file('arquivo');
        $caminho = null;

        try {
            $resultado = DB::transaction(function () use ($request, $edital, $arquivo, &$caminho): ImportacaoQuestionario|string {
                Edital::query()->whereKey($edital->getKey())->lockForUpdate()->first();

                $recusa = $this->recusa($request, $edital);
                if ($recusa !== null) {
                    return $recusa;
                }

                // A análise perdida (mais de 15 minutos) sai da execução: o processo dela, se ainda viver, não grava.
                ImportacaoQuestionario::query()->where('edital_id', $edital->getKey())->semTermino()->update([
                    'situacao' => SituacaoImportacao::Erro->value,
                    'erro' => ImportacaoEdital::ERRO_SEM_TERMINO,
                ]);

                $caminho = ArquivoArmazenado::guardar($arquivo, ImportacaoQuestionario::DISCO, ImportacaoQuestionario::PASTA);

                return ImportacaoQuestionario::create([
                    'user_id' => $request->user()->getKey(),
                    'edital_id' => $edital->getKey(),
                    'situacao' => SituacaoImportacao::Analisando,
                    'arquivo' => $caminho,
                    'arquivo_nome' => mb_strcut($arquivo->getClientOriginalName(), 0, 255, 'UTF-8'),
                    'tipo_arquivo' => $request->tipoDoArquivo(),
                    'substituir' => $request->boolean('substituir'),
                    ...$this->principal($request),
                    'iniciada_em' => now(),
                    'execucao' => ImportacaoEdital::novaExecucao(),
                ]);
            });
        } catch (Throwable $erro) {
            ArquivoArmazenado::apagar(ImportacaoQuestionario::DISCO, $caminho);

            throw $erro;
        }

        if (is_string($resultado)) {
            return back()->withErrors(['arquivo' => $resultado]);
        }

        return $this->lancar($resultado, $lancador);
    }

    /** O motivo para não importar agora, ou null. */
    private function recusa(ImportarQuestionarioRequest $request, Edital $edital): ?string
    {
        return match (true) {
            $edital->questionarioTravado() => Edital::AVISO_QUESTIONARIO_TRAVADO,
            ! $edital->categorias()->exists() => self::ERRO_SEM_CATEGORIA,
            ImportacaoQuestionario::query()->where('edital_id', $edital->getKey())->emAndamento()->exists() => self::ERRO_EM_ANDAMENTO,
            $edital->secoesDoQuestionario()->exists() && ! $request->boolean('substituir') => self::ERRO_CONFIRMAR,
            default => null,
        };
    }

    /** Inicia a análise; se o processo nem começar, a importação já fica com erro. */
    private function lancar(ImportacaoQuestionario $importacao, LancadorDeAnaliseDoQuestionario $lancador): RedirectResponse
    {
        try {
            $lancador->lancar($importacao);
        } catch (Throwable $erro) {
            Log::error('Importação de questionário: não foi possível iniciar a análise.', [
                'importacao' => (int) $importacao->getKey(),
                'excecao' => $erro::class,
            ]);
            $importacao->update(['situacao' => SituacaoImportacao::Erro, 'erro' => ImportacaoEditalController::ERRO_AO_INICIAR]);

            return back()->with('erro', ImportacaoEditalController::ERRO_AO_INICIAR);
        }

        return back()->with('sucesso', ImportacaoEditalController::MENSAGEM_INICIADA);
    }

    /** Situação para a tela consultar até a análise terminar (consulta repetida: sem as colunas CLOB). */
    public function show(int $importacao): JsonResponse
    {
        $importacao = ImportacaoQuestionario::query()
            ->select(['id', 'situacao', 'erro', 'iniciada_em'])
            ->findOrFail($importacao);

        return response()->json([
            'situacao' => $importacao->situacaoExibida()->value,
            'erro' => $importacao->erroExibido(),
        ]);
    }

    /** Tira da aba Questionário o quadro "Importado pela IA" da última importação concluída. */
    public function conferida(Edital $edital): RedirectResponse
    {
        $importacao = ImportacaoQuestionario::query()
            ->select(['id'])
            ->where('edital_id', $edital->getKey())
            ->where('situacao', SituacaoImportacao::Concluida->value)
            ->latest('id')
            ->firstOrFail();

        $importacao->update(['conferida_em' => now()]);

        return back()->with('sucesso', 'Importação marcada como conferida.');
    }

    /**
     * O provedor principal e o modelo previstos para a análise (o middleware já conferiu que há um); quem respondeu de
     * fato é gravado pelo comando.
     *
     * @return array{provedor: ProvedorIa, modelo: string}
     */
    private function principal(Request $request): array
    {
        /** @var array{provedor: ProvedorIa, conexao: ConexaoIa} $principal */
        $principal = $request->attributes->get(self::PRINCIPAL);

        return ['provedor' => $principal['provedor'], 'modelo' => (string) $principal['conexao']->modelo];
    }
}
