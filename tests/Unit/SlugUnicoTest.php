<?php

namespace Tests\Unit;

use App\Models\Edital;
use App\Support\SlugUnico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugUnicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_titulo_longo_repetido_gera_slug_com_sufixo_que_cabe_na_coluna(): void
    {
        $titulo = str_repeat('Programação cultural ', 20);
        Edital::factory()->create(['slug' => SlugUnico::para(Edital::class, $titulo)]);

        $slug = SlugUnico::para(Edital::class, $titulo);

        $this->assertStringEndsWith('-2', $slug);
        $this->assertLessThanOrEqual(255, strlen($slug));
        $this->assertStringNotContainsString('--', $slug);
    }
}
