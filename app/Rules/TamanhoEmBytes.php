<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Texto que cabe numa coluna VARCHAR2 do Oracle medida em bytes: em UTF-8 cada letra acentuada ocupa 2 bytes, e o
 * texto dentro do limite de caracteres (regra max) ainda pode estourar a coluna. Acima do limite de caracteres quem
 * avisa é a regra max; esta só cuida do que passa por ela.
 */
class TamanhoEmBytes implements ValidationRule
{
    public function __construct(private int $bytes) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) > $this->bytes || strlen($value) <= $this->bytes) {
            return;
        }

        $fail("Texto longo demais: use no máximo {$this->bytes} caracteres sem acento (o limite conta acentos como mais de um).");
    }
}
