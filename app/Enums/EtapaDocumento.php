<?php

namespace App\Enums;

/** Etapa do edital em que o documento é exigido. */
enum EtapaDocumento: string
{
    case Inscricao = 'inscricao';
    case ValidacaoCotas = 'validacao_cotas';
    case Habilitacao = 'habilitacao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Inscricao => 'Inscrição',
            self::ValidacaoCotas => 'Validação de cotas',
            self::Habilitacao => 'Habilitação',
        };
    }
}
