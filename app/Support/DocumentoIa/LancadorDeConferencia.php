<?php

namespace App\Support\DocumentoIa;

use App\Models\InscricaoDocumento;
use Illuminate\Support\Facades\Process;

use function Illuminate\Support\php_binary;

/**
 * Inicia a conferência do documento da inscrição num processo separado (`php artisan inscricoes:conferir {id}
 * {execucao}`), sem fila: o comando roda solto em segundo plano e a requisição volta na hora, um processo por
 * documento. Usa o PHP de linha de comando (no PHP-FPM, PHP_BINARY é o php-fpm). Resolvido pelo container, para os
 * testes o trocarem por um que roda o comando na hora ou que só registra.
 */
class LancadorDeConferencia
{
    public function lancar(InscricaoDocumento $documento): void
    {
        Process::path(base_path())->run(sprintf(
            'nohup %s %s inscricoes:conferir %d %s > /dev/null 2>&1 &',
            escapeshellarg(php_binary()),
            escapeshellarg(base_path('artisan')),
            (int) $documento->getKey(),
            escapeshellarg((string) $documento->execucao),
        ))->throw();
    }
}
