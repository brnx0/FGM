<?php

namespace App\Database;

use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Schema\OracleBuilder;

class OracleConnection extends Oci8Connection
{
    public function getSchemaBuilder(): OracleBuilder
    {
        $builder = parent::getSchemaBuilder();
        $builder->ctxDdlPreferences = new OracleSemTextoPreferences($this);

        return $builder;
    }
}
