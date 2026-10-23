<?php

namespace Tests\Unit;

use App\Support\Cpf;
use PHPUnit\Framework\TestCase;

class CpfTest extends TestCase
{
    public function test_normalizar_mantem_so_digitos_e_completa_zeros(): void
    {
        $this->assertSame('01234567890', Cpf::normalizar('012.345.678-90'));
        $this->assertSame('01234567890', Cpf::normalizar('1234567890'));
    }

    public function test_valido_checa_digitos_verificadores(): void
    {
        $this->assertTrue(Cpf::valido('529.982.247-25'));
        $this->assertFalse(Cpf::valido('529.982.247-24'));
        $this->assertFalse(Cpf::valido('11111111111'));
        $this->assertFalse(Cpf::valido(''));
    }

    public function test_mascarar(): void
    {
        $this->assertSame('***.982.247-**', Cpf::mascarar('52998224725'));
    }
}
