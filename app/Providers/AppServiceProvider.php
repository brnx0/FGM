<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Staging e produção rodam só em HTTPS. 'testing' fica de fora para os
        // testes seguirem com URLs http://localhost.
        if (! $this->app->environment(['local', 'testing'])) {
            URL::forceScheme('https');
            config(['session.secure' => true]);
        }
    }
}
