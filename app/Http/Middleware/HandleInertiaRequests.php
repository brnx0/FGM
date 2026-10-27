<?php

namespace App\Http\Middleware;

use App\Auth\GovBr\FakeLoginGate;
use App\Enums\TipoCadastro;
use App\Support\Cpf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Inertia\Support\Header;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * A retaguarda tem versão própria: visita Inertia (link ou redirecionamento seguido) que cruza entre
     * ela e o resto do site recebe 409 e vira carregamento completo. Assim cada página carregada fica numa
     * área só, e o que o app.blade.php decide nela (o VLibras e a CSP dele) vale até o próximo carregamento.
     * Envio (POST/PUT/...) para a outra área precisa redirecionar, não renderizar: o Inertia só confere a
     * versão em GET.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        $versao = parent::version($request);

        return $request->is('retaguarda', 'retaguarda/*') ? 'retaguarda:'.$versao : $versao;
    }

    /**
     * Versão trocada (outra área ou novo build): carregamento completo sem desfazer o que a requisição já fez.
     *
     * - Ela já pediu um carregamento completo (saída do gov.br em EncerrarSessao): segue como está.
     * - Ela redirecionou: carrega direto o destino. Repetir a requisição rodaria de novo o que ela fez
     *   (logout, aviso na sessão), e o aviso se perderia.
     * - Ela renderizou uma página: esta já consumiu o pedido de limpar o histórico do navegador (EncerrarSessao,
     *   ao voltar da retaguarda para a home), que volta à sessão para valer no carregamento completo.
     */
    public function onVersionChange(Request $request, Response $response): Response
    {
        if ($response->headers->has(Header::LOCATION)) {
            return $response;
        }

        if ($response instanceof RedirectResponse) {
            return Inertia::location($response->getTargetUrl());
        }

        if ($response instanceof JsonResponse && ($response->getData(true)['clearHistory'] ?? false) === true) {
            Inertia::clearHistory();
        }

        return parent::onVersionChange($request, $response);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user()
                    ? [
                        'nome' => $request->user()->name,
                        'cpfMascarado' => Cpf::mascarar($request->user()->cpf),
                        'ehGestor' => $request->user()->ehGestor(),
                        // Vazio até a última etapa do cadastro; define o menu do portal (um tipo ou os dois).
                        'tiposCadastro' => $request->user()->tipos_cadastro?->map(fn (TipoCadastro $tipo): string => $tipo->value)->all() ?? [],
                    ]
                    : null,
            ],
            'ambienteTeste' => FakeLoginGate::bannerVisivel(),
            'flash' => [
                'erro' => fn () => $request->session()->get('erro'),
                'aviso' => fn () => $request->session()->get('aviso'),
                'sucesso' => fn () => $request->session()->get('sucesso'),
            ],
        ];
    }
}
