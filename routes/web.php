<?php

use App\Http\Controllers\Auth\FakeGovBrController;
use App\Http\Controllers\Auth\GovBrController;
use App\Http\Controllers\Auth\GovBrRedirectController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\CadastroController;
use App\Http\Controllers\ConsultaCadastroController;
use App\Http\Controllers\EditalController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrganizacaoController;
use App\Http\Controllers\PaginaController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/editais/{edital:slug}', [EditalController::class, 'show'])->name('editais.show');
Route::get('/editais/{edital:slug}/pdf', [EditalController::class, 'pdf'])->name('editais.pdf');
Route::get('/eventos/{evento:slug}', [EventoController::class, 'show'])->name('eventos.show');

Route::controller(PaginaController::class)->group(function () {
    Route::get('/a-fgm', 'aFgm')->name('paginas.a-fgm');
    Route::get('/duvidas', 'duvidas')->name('paginas.duvidas');
    Route::get('/privacidade', 'privacidade')->name('paginas.privacidade');
    Route::get('/termos', 'termos')->name('paginas.termos');
});

Route::middleware(['auth', 'nao-gestor'])->controller(CadastroController::class)->group(function () {
    Route::get('/cadastro', 'completar')->name('cadastro');
    Route::post('/cadastro', 'salvar')->name('cadastro.salvar');
});

Route::middleware(['auth', 'nao-gestor', 'cadastro-completo'])->controller(CadastroController::class)->group(function () {
    Route::get('/cadastro/organizacao', 'organizacao')->name('cadastro.organizacao');
});

Route::middleware(['auth', 'nao-gestor', 'cadastro-completo'])->controller(OrganizacaoController::class)->group(function () {
    Route::get('/organizacoes', 'index')->name('organizacoes.index');
    Route::get('/cadastro/pessoa-juridica', 'create')->name('organizacoes.create');
    Route::post('/organizacoes', 'store')->name('organizacoes.store');
    Route::get('/organizacoes/{organizacao}/editar', 'edit')->name('organizacoes.edit');
    Route::put('/organizacoes/{organizacao}', 'update')->name('organizacoes.update');
});

// Limitador 'consultas' (AppServiceProvider): 30/min por usuário.
Route::middleware(['auth', 'nao-gestor', 'throttle:consultas'])->controller(ConsultaCadastroController::class)->group(function () {
    Route::get('/cadastro/consultas/cnpj/{cnpj}', 'cnpj')->name('consultas.cnpj');
    Route::get('/cadastro/consultas/cep/{cep}', 'cep')->name('consultas.cep');
    Route::get('/cadastro/consultas/municipios', 'municipios')->name('consultas.municipios');
});

Route::post('/logout', LogoutController::class)->name('logout');

// Limitador 'govbr' (AppServiceProvider): 10/min por rota e por IP.
Route::middleware('throttle:govbr')->group(function () {
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

require __DIR__.'/retaguarda.php';
