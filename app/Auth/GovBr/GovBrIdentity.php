<?php

namespace App\Auth\GovBr;

/** Identidade devolvida pelo gov.br (ou pelo login simulado). O CPF chega já normalizado. */
final readonly class GovBrIdentity
{
    public function __construct(
        public string $cpf,
        public string $nome,
        public ?string $email,
        public bool $emailVerificado,
        public ?string $telefone,
        public ?string $nivelConfiabilidade = null,
    ) {}
}
