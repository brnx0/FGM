<?php

namespace Tests\Unit;

use App\Support\Cnpj;
use PHPUnit\Framework\TestCase;

class CnpjTest extends TestCase
{
    public function test_normalizar_remove_pontuacao_e_poe_em_maiusculas(): void
    {
        $this->assertSame('12ABC34501DE35', Cnpj::normalizar('12.abc.345/01de-35'));
    }

    public function test_valido_aceita_cnpj_numerico_e_alfanumerico(): void
    {
        $this->assertTrue(Cnpj::valido('11.222.333/0001-81'));
        $this->assertTrue(Cnpj::valido('12.ABC.345/01DE-35'));
        $this->assertTrue(Cnpj::valido('12.abc.345/01de-35'));
    }

    public function test_valido_recusa_digito_verificador_errado(): void
    {
        $this->assertFalse(Cnpj::valido('11.222.333/0001-82'));
        $this->assertFalse(Cnpj::valido('12.ABC.345/01DE-36'));
    }

    public function test_valido_recusa_formatos_invalidos(): void
    {
        $this->assertFalse(Cnpj::valido('00000000000000'));
        $this->assertFalse(Cnpj::valido('11222333000'));
        $this->assertFalse(Cnpj::valido('1A.222.333/0001-8B'));
    }

    public function test_formatar_aplica_a_mascara(): void
    {
        $this->assertSame('11.222.333/0001-81', Cnpj::formatar('11222333000181'));
        $this->assertSame('12.ABC.345/01DE-35', Cnpj::formatar('12ABC34501DE35'));
    }
}
