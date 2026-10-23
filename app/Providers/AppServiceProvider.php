<?php

namespace App\Providers;

use App\Database\OracleConnection;
use Illuminate\Database\Connection;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Reaproveita o resolver do yajra (PDO e variáveis de sessão já aplicadas) e
        // troca só a classe da conexão, para o schema builder tolerar bancos sem Oracle Text.
        $yajra = Connection::getResolver('oracle');

        Connection::resolverFor('oracle', function ($pdo, $database, $prefix, $config) use ($yajra) {
            $base = $yajra($pdo, $database, $prefix, $config);

            return new OracleConnection($base->getPdo(), $database, $prefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Atrás de um proxy que termina o TLS, honra X-Forwarded-Proto; sem isso
        // isSecure() fica falso e o HSTS (SecurityHeaders) nunca é enviado.
        $proxies = trim((string) config('app.trusted_proxies'));
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Staging e produção rodam só em HTTPS. 'testing' fica de fora para os
        // testes seguirem com URLs http://localhost.
        if (! $this->app->environment(['local', 'testing'])) {
            URL::forceScheme('https');
            config(['session.secure' => true]);
        }
    }
}
