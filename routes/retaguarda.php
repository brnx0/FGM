<?php

use App\Http\Controllers\Retaguarda\ConteudoImagemController;
use App\Http\Controllers\Retaguarda\EditalController;
use App\Http\Controllers\Retaguarda\EventoController;
use App\Http\Controllers\Retaguarda\LoginController;
use App\Http\Controllers\Retaguarda\MinhaSenhaController;
use App\Http\Controllers\Retaguarda\PerfilController;
use App\Http\Controllers\Retaguarda\UsuarioController;
use App\Http\Controllers\Retaguarda\VLibrasController;
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
        Route::get('/', function (): RedirectResponse {
            // Mantém avisos (ex.: gestor barrado no cadastro) através deste redirecionamento.
            session()->reflash();

            return to_route('retaguarda.editais.index');
        })->name('inicio');

        Route::resource('editais', EditalController::class)
            ->except('show')
            ->parameters(['editais' => 'edital']);
        Route::patch('/editais/{edital}/publicacao', [EditalController::class, 'publicacao'])->name('editais.publicacao');
        Route::get('/editais/{edital}/pdf', [EditalController::class, 'pdf'])->name('editais.pdf');

        // Imagens do editor de texto rico (eventos e editais): responde JSON com a URL.
        Route::post('/conteudo/imagem', ConteudoImagemController::class)
            ->middleware('throttle:30,1')
            ->name('conteudo.imagem');
        Route::resource('eventos', EventoController::class)->except('show');
        Route::patch('/eventos/{evento}/publicacao', [EventoController::class, 'publicacao'])->name('eventos.publicacao');

        // VLibras (tradutor de Libras) nas páginas fora da retaguarda.
        Route::get('/vlibras', [VLibrasController::class, 'edit'])->name('vlibras.edit');
        Route::put('/vlibras', [VLibrasController::class, 'update'])->name('vlibras.update');

        Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
        Route::put('/perfil', [PerfilController::class, 'update'])->middleware('throttle:6,1')->name('perfil.update');

        Route::get('/minha-senha', [MinhaSenhaController::class, 'edit'])->name('senha.edit');
        Route::put('/minha-senha', [MinhaSenhaController::class, 'update'])->middleware('throttle:6,1')->name('senha.update');

        // Usuário não se exclui, só se desativa.
        Route::resource('usuarios', UsuarioController::class)
            ->only(['index', 'create', 'store', 'edit', 'update'])
            ->parameters(['usuarios' => 'user']);
        Route::patch('/usuarios/{user}/ativo', [UsuarioController::class, 'ativo'])->name('usuarios.ativo');
    });
});
