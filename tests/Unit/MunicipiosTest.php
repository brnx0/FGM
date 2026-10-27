<?php

namespace Tests\Unit;

use App\Support\Municipios;
use PHPUnit\Framework\TestCase;

class MunicipiosTest extends TestCase
{
    public function test_por_codigo_devolve_o_municipio(): void
    {
        $this->assertSame(
            ['codigo' => '2927408', 'nome' => 'Salvador', 'uf' => 'BA'],
            Municipios::porCodigo('2927408'),
        );
        $this->assertNull(Municipios::porCodigo('9999999'));
    }

    public function test_existe(): void
    {
        $this->assertTrue(Municipios::existe('2927408'));
        $this->assertFalse(Municipios::existe('9999999'));
    }

    public function test_buscar_prioriza_quem_comeca_com_o_termo(): void
    {
        $this->assertSame('Salvador', Municipios::buscar('salva')[0]['nome']);
    }

    public function test_buscar_ignora_acento_e_caixa(): void
    {
        $this->assertContains(
            ['codigo' => '3550308', 'nome' => 'São Paulo', 'uf' => 'SP'],
            Municipios::buscar('sao paulo'),
        );
    }

    public function test_buscar_respeita_o_limite(): void
    {
        $this->assertCount(20, Municipios::buscar('a', 20));
    }
}
