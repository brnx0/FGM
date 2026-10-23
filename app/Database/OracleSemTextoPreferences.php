<?php

namespace App\Database;

use Yajra\Oci8\Schema\OraclePreferences;

/**
 * O yajra/laravel-oci8 consulta ctx_user_preferences (Oracle Text) em todo
 * dropAllTables(), o que quebra migrate:fresh e RefreshDatabase em bancos sem
 * CTXSYS (como a imagem Oracle Free usada aqui). Só limpa se o Oracle Text existir.
 */
class OracleSemTextoPreferences extends OraclePreferences
{
    public function dropAllPreferences(): void
    {
        if ($this->checkIfCtxsysIsEnabled()) {
            parent::dropAllPreferences();
        }
    }
}
