<?php

namespace App\Models\Concerns;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * O Oracle DATE guarda hora. Colunas "dia" são gravadas sempre à meia-noite e
 * consultadas em intervalo semiaberto, para que dados com hora não mudem o resultado.
 */
trait UsaDiaCivil
{
    /** Início de "hoje" no fuso da aplicação (America/Bahia). Nunca altera o Carbon recebido. */
    protected static function hoje(?CarbonInterface $agora = null): CarbonImmutable
    {
        $agora = $agora === null ? CarbonImmutable::now() : CarbonImmutable::instance($agora);

        return $agora->setTimezone(config('app.timezone'))->startOfDay();
    }

    /** Valor a gravar: só a data (Y-m-d, meia-noite), no calendário em que veio. */
    protected static function diaParaGravar(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $data = $valor instanceof DateTimeInterface
            ? CarbonImmutable::instance($valor)
            : CarbonImmutable::parse((string) $valor);

        return $data->toDateString();
    }
}
