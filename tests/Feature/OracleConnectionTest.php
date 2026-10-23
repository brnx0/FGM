<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OracleConnectionTest extends TestCase
{
    public function test_conecta_no_oracle(): void
    {
        $this->assertSame('oracle', DB::connection()->getDriverName());
        $this->assertSame(1, (int) DB::selectOne('select 1 as ok from dual')->ok);
    }
}
