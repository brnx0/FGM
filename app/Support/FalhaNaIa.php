<?php

namespace App\Support;

use RuntimeException;

/**
 * Falha numa chamada a provedor de IA (ver ClienteIa). A mensagem vai para a tela do gestor, já em português,
 * e por isso nunca contém a chave da API.
 */
class FalhaNaIa extends RuntimeException {}
