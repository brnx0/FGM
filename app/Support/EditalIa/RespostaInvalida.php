<?php

namespace App\Support\EditalIa;

use RuntimeException;

/** A resposta da IA não tem os blocos do esquema (ausentes ou com tipo errado na raiz): a importação falha. */
class RespostaInvalida extends RuntimeException
{
    public const string MENSAGEM = 'A resposta da IA veio fora do formato esperado.';

    public function __construct()
    {
        parent::__construct(self::MENSAGEM);
    }
}
