<?php

namespace App\Http\Middleware;

use App\Auth\GovBr\FakeLoginGate;
use App\Enums\TipoCadastro;
use App\Support\Cpf;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
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
