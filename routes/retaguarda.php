<?php

use App\Http\Controllers\Retaguarda\EditalController;
use App\Http\Controllers\Retaguarda\LoginController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

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
        Route::get('/', fn (): RedirectResponse => to_route('retaguarda.editais.index'))->name('inicio');

        Route::resource('editais', EditalController::class)
            ->except('show')
            ->parameters(['editais' => 'edital']);
        Route::patch('/editais/{edital}/publicacao', [EditalController::class, 'publicacao'])->name('editais.publicacao');
        Route::get('/editais/{edital}/pdf', [EditalController::class, 'pdf'])->name('editais.pdf');
    });
});
