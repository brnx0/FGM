<?php

use App\Http\Middleware\BloquearGestorNoCadastro;
use App\Http\Middleware\GarantirGestor;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'gestor' => GarantirGestor::class,
            'nao-gestor' => BloquearGestorNoCadastro::class,
        ]);

        // Visitante da retaguarda vai ao login dela; no portal do proponente, à home.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('retaguarda', 'retaguarda/*') ? route('retaguarda.login') : '/'
        );

        // Proxies confiáveis (TRUSTED_PROXIES) são aplicados no AppServiceProvider,
        // a partir de config('app.trusted_proxies'), para funcionar com config:cache.
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
