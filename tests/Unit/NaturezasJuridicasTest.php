<?php

namespace Tests\Unit;

use App\Support\NaturezasJuridicas;
use PHPUnit\Framework\TestCase;

class NaturezasJuridicasTest extends TestCase
{
    public function test_lista_contem_sociedade_empresaria_limitada(): void
    {
        $this->assertSame('Sociedade Empresária Limitada', NaturezasJuridicas::TODAS['2062']);
    }

    public function test_sugere_finalidade_lucrativa_apenas_para_grupo_2(): void
    {
        $this->assertTrue(NaturezasJuridicas::sugereFinalidadeLucrativa('2062'));
        $this->assertFalse(NaturezasJuridicas::sugereFinalidadeLucrativa('3999'));
        $this->assertFalse(NaturezasJuridicas::sugereFinalidadeLucrativa('1031'));
    }
}
