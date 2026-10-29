<?php

namespace App\Enums;

/** Situação de uma importação de edital por IA. */
enum SituacaoImportacao: string
{
    case Analisando = 'analisando';
    case Concluida = 'concluida';
    case Erro = 'erro';

    public function rotulo(): string
    {
        return match ($this) {
            self::Analisando => 'Analisando',
            self::Concluida => 'Concluída',
            self::Erro => 'Erro',
        };
    }
}
