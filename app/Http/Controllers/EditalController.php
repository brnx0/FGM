<?php

namespace App\Http\Controllers;

use App\Enums\BaseCota;
use App\Enums\EtapaDocumento;
use App\Enums\ModalidadeCota;
use App\Models\Edital;
use App\Models\EditalDocumento;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditalController extends Controller
{
    public function show(Edital $edital): Response
    {
        abort_unless($edital->estaVisivel(), 404);

        $edital->load([
            'chamadas',
            'categorias.itens',
            'documentos' => fn ($consulta) => $consulta->where('etapa', EtapaDocumento::Inscricao->value),
            'documentos.categorias',
        ]);

        $situacao = $edital->situacao();

        return Inertia::render('Editais/Show', [
            'edital' => [
                'titulo' => $edital->titulo,
                'slug' => $edital->slug,
                'categoria' => $edital->categoria,
                'resumo' => $edital->resumo,
                'pdf' => $edital->pdfUrl(),
                'situacao' => $situacao->value,
                'situacaoRotulo' => $situacao->rotulo(),
                'inscricoesInicio' => $edital->inscricoes_inicio->format('d/m/Y'),
                'inscricoesFim' => $edital->inscricoes_fim->format('d/m/Y'),
                'numero' => $edital->numero,
                'tipoRotulo' => $edital->tipo?->rotulo(),
                'objeto' => $edital->objeto,
                'chamadas' => $edital->chamadas->map(fn ($chamada): array => [
                    'nome' => $chamada->nome,
                    'inicio' => $chamada->inicio?->format('d/m/Y'),
                    'fim' => $chamada->fim?->format('d/m/Y'),
                    'situacao' => $chamada->situacao()?->value,
                    'situacaoRotulo' => $chamada->situacao()?->rotulo(),
                ])->all(),
                'naturezas' => $edital->naturezas?->map(fn ($natureza): string => $natureza->rotulo())->values()->all() ?? [],
                'aceitaColetivos' => (bool) $edital->aceita_coletivos,
                'inscricoesPorProponente' => $edital->inscricoes_por_proponente,
                'categorias' => $edital->categorias->map(fn ($categoria): array => [
                    'nome' => $categoria->nome,
                    'descricao' => $categoria->descricao,
                    'valor' => self::formatarValor($categoria->valor_centavos),
                    'vagas' => $categoria->vagas,
                    'itens' => $categoria->itens->map(fn ($item): array => [
                        'nome' => $item->nome,
                        'valor' => self::formatarValor($item->valor_centavos),
                        'vagas' => $item->vagas,
                        'suplentes' => $item->suplentes,
                    ])->all(),
                ])->all(),
                'cotas' => $this->cotasAtivas($edital),
                'documentosInscricao' => $edital->documentos->map(fn (EditalDocumento $documento): array => [
                    'nome' => $documento->nome,
                    'orientacao' => $documento->orientacao,
                    'obrigatorio' => $documento->obrigatorio,
                    'naturezas' => $documento->naturezas?->map(fn ($natureza): string => $natureza->rotulo())->values()->all() ?? [],
                    'categorias' => $documento->todas_categorias ? [] : $documento->categorias->pluck('nome')->all(),
                    'modeloUrl' => filled($documento->modelo)
                        ? route('editais.documentos.modelo', [$edital, $documento])
                        : null,
                    'modeloNome' => $documento->modelo_nome,
                ])->all(),
                'informacoes' => $this->informacoesPreenchidas($edital),
            ],
        ]);
    }

    public function pdf(Edital $edital): StreamedResponse
    {
        abort_unless($edital->estaVisivel() && Storage::disk('local')->exists($edital->pdf), 404);

        return Storage::disk('local')->download($edital->pdf, "edital-{$edital->slug}.pdf");
    }

    /**
     * Base (valor de BaseCota): sobre o que percentual e quantidade valem; null quando o edital não diz (vale como vagas).
     *
     * @return list<array{modalidade: string, percentual: int|float|null, quantidade: int|float|null, base: ?string}>
     */
    private function cotasAtivas(Edital $edital): array
    {
        $ativas = [];
        $base = BaseCota::tryFrom((string) ($edital->cotas['base'] ?? ''))?->value;

        foreach (ModalidadeCota::cases() as $modalidade) {
            $cota = $edital->cotas[$modalidade->value] ?? null;
            if (! is_array($cota) || ! ($cota['ativa'] ?? false)) {
                continue;
            }

            $ativas[] = [
                'modalidade' => $modalidade->rotulo(),
                'percentual' => $cota['percentual'] ?? null,
                'quantidade' => $cota['quantidade'] ?? null,
                'base' => $base,
            ];
        }

        return $ativas;
    }

    /**
     * Só as seções com texto, na ordem de exibição.
     *
     * @return list<array{chave: string, titulo: string, texto: string}>
     */
    private function informacoesPreenchidas(Edital $edital): array
    {
        $secoes = [];

        foreach (Edital::SECOES_INFORMATIVAS as $chave => $titulo) {
            $texto = $edital->informacoes[$chave] ?? null;
            if (is_string($texto) && trim($texto) !== '') {
                $secoes[] = ['chave' => $chave, 'titulo' => $titulo, 'texto' => $texto];
            }
        }

        return $secoes;
    }

    private static function formatarValor(?int $centavos): ?string
    {
        return $centavos === null ? null : 'R$ '.number_format($centavos / 100, 2, ',', '.');
    }
}
