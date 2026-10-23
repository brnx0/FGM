<?php

namespace App\Auth\GovBr;

use RuntimeException;

/**
 * Decide se o login gov.br simulado pode existir no ambiente atual.
 */
class FakeLoginGate
{
    public static function permitido(): bool
    {
        return config('govbr.driver') === 'fake' && app()->environment(['local', 'staging']);
    }

    public static function bannerVisivel(): bool
    {
        return self::permitido() && app()->environment('staging');
    }

    public function assertNaoEmProducao(): void
    {
        if (app()->environment('production') && config('govbr.driver') === 'fake') {
            throw new RuntimeException('GOVBR_DRIVER=fake não é permitido em produção.');
        }
    }
}
