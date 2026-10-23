<?php

namespace App\Auth\GovBr;

use RuntimeException;

/**
 * Falha na integração com o gov.br. A mensagem vai para o log (report()),
 * portanto nunca deve conter tokens, códigos de autorização ou o client secret.
 */
class GovBrException extends RuntimeException {}
