<?php

use App\Http\Controllers\Retaguarda\LoginController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::prefix('retaguarda')->name('retaguarda.')->group(function () {
    Route::controller(LoginController::class)->group(function () {
        Route::get('/login', 'create')->name('login');
        // Limitador 'retaguarda-login' (AppServiceProvider): 5/min por e-mail e IP.
        Route::post('/login', 'store')->middleware('throttle:retaguarda-login')->name('login.entrar');
        Route::post('/sair', 'destroy')->name('sair');
    });

    // Segue o fluxo gov.br comum (real ou simulado); o destino vale só para esta tentativa (ver GovBrRedirectController).
    Route::get('/govbr', function (): RedirectResponse {
        return redirect()->route('govbr.redirect', ['destino' => 'retaguarda']);
    })->middleware('throttle:govbr')->name('govbr');

    Route::middleware(['auth', 'gestor'])->group(function () {
        // Provisório: a Task 5 troca por um redirect para retaguarda.editais.index.
        Route::get('/', fn () => Inertia::render('Retaguarda/Inicio'))->name('inicio');
    });
});
