<?php

namespace App\Http\Controllers;

use App\Enums\SituacaoEdital;
use App\Models\Edital;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Telas do menu do portal depois do cadastro finalizado: Editais e inscrição (proponente) e Projetos (patrocinador). */
class PortalController extends Controller
{
    public const AVISO_INSCRICOES_FECHADAS = 'As inscrições deste edital não estão abertas.';

    /** Só os editais em fase de inscrição, entre os publicados com PDF (os mesmos que o site mostra). */
    public function editais(): Response
    {
        $editais = Edital::visiveis()
            ->paraListagem()
            ->naSituacao(SituacaoEdital::Aberto)
            ->orderByDesc('inscricoes_inicio')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(function (Edital $edital): array {
                $situacao = $edital->situacao();

                return [
                    'slug' => $edital->slug,
                    'titulo' => $edital->titulo,
                    'categoria' => $edital->categoria,
                    'periodo' => $edital->inscricoes_inicio->format('d/m/Y').' a '.$edital->inscricoes_fim->format('d/m/Y'),
                    'situacao' => $situacao->value,
                    'situacaoRotulo' => $situacao->rotulo(),
                    'pdf' => $edital->pdfUrl(),
                    'imagem' => $edital->imagemUrl(),
                ];
            });

        return Inertia::render('Portal/Editais', ['editais' => $editais]);
    }

    /** Início da inscrição num edital aberto. O envio dos documentos ainda não existe: a tela mostra o edital e o aviso. */
    public function inscricao(Edital $edital): Response|RedirectResponse
    {
        abort_unless($edital->estaVisivel(), 404);

        if ($edital->situacao() !== SituacaoEdital::Aberto) {
            return redirect()->route('portal.editais')->with('aviso', self::AVISO_INSCRICOES_FECHADAS);
        }

        return Inertia::render('Portal/Inscricao', [
            'edital' => [
                'titulo' => $edital->titulo,
                'categoria' => $edital->categoria,
                'periodo' => $edital->inscricoes_inicio->format('d/m/Y').' a '.$edital->inscricoes_fim->format('d/m/Y'),
                'pdf' => $edital->pdfUrl(),
            ],
        ]);
    }

    /** Ainda não há envio de projetos pelos proponentes: a tela mostra só o estado vazio. */
    public function projetos(): Response
    {
        return Inertia::render('Portal/Projetos');
    }
}
