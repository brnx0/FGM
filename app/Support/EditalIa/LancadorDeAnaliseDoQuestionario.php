<?php

namespace App\Support\EditalIa;

use App\Models\ImportacaoQuestionario;
use Illuminate\Support\Facades\Process;

use function Illuminate\Support\php_binary;

/**
 * Inicia a análise da importação do questionário num processo separado
 * (`php artisan questionarios:analisar {id} {execucao}`), sem fila, como LancadorDeAnalise. Resolvido pelo container,
 * para os testes o trocarem por um que roda o comando na hora.
 */
class LancadorDeAnaliseDoQuestionario
{
    public function lancar(ImportacaoQuestionario $importacao): void
    {
        Process::path(base_path())->run(sprintf(
            'nohup %s %s questionarios:analisar %d %s > /dev/null 2>&1 &',
            escapeshellarg(php_binary()),
            escapeshellarg(base_path('artisan')),
            (int) $importacao->getKey(),
            escapeshellarg((string) $importacao->execucao),
        ))->throw();
    }
}
