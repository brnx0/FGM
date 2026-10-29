<?php

namespace App\Support\EditalIa;

use App\Models\ImportacaoEdital;
use Illuminate\Support\Facades\Process;

use function Illuminate\Support\php_binary;

/**
 * Inicia a análise da importação num processo separado (`php artisan editais:analisar {id} {execucao}`), sem fila:
 * o comando roda solto em segundo plano e a requisição volta na hora. Usa o PHP de linha de comando (no PHP-FPM,
 * PHP_BINARY é o php-fpm). Resolvido pelo container, para os testes o trocarem por um que roda o comando na hora.
 */
class LancadorDeAnalise
{
    public function lancar(ImportacaoEdital $importacao): void
    {
        Process::path(base_path())->run(sprintf(
            'nohup %s %s editais:analisar %d %s > /dev/null 2>&1 &',
            escapeshellarg(php_binary()),
            escapeshellarg(base_path('artisan')),
            (int) $importacao->getKey(),
            escapeshellarg((string) $importacao->execucao),
        ))->throw();
    }
}
