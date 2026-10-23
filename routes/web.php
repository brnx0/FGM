<?php

use App\Http\Controllers\Auth\FakeGovBrController;
use App\Http\Controllers\Auth\GovBrController;
use App\Http\Controllers\Auth\GovBrRedirectController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CadastroController;
use App\Http\Controllers\EditalController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PaginaController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/editais/{edital:slug}', [EditalController::class, 'show'])->name('editais.show');

Route::controller(PaginaController::class)->group(function () {
    Route::get('/a-fgm', 'aFgm')->name('paginas.a-fgm');
    Route::get('/duvidas', 'duvidas')->name('paginas.duvidas');
    Route::get('/privacidade', 'privacidade')->name('paginas.privacidade');
    Route::get('/termos', 'termos')->name('paginas.termos');
});

Route::get('/cadastro', [CadastroController::class, 'completar'])->middleware('auth')->name('cadastro');
Route::post('/logout', LogoutController::class)->name('logout');

Route::middleware('throttle:10,1')->group(function () {
    Route::get('/auth/govbr/redirect', GovBrRedirectController::class)->name('govbr.redirect');

    Route::controller(GovBrController::class)->group(function () {
        Route::get('/auth/govbr/callback', 'callback')->name('govbr.callback');
        Route::get('/auth/govbr/logout', 'logout')->name('govbr.logout');
    });

    Route::controller(FakeGovBrController::class)->group(function () {
        Route::get('/auth/govbr/fake', 'create')->name('govbr.fake');
        Route::post('/auth/govbr/fake', 'store');
    });
});
