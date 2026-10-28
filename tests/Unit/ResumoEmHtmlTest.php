<?php

namespace Tests\Unit;

use App\Support\ResumoEmHtml;
use PHPUnit\Framework\TestCase;

class ResumoEmHtmlTest extends TestCase
{
    public function test_envolve_o_texto_em_paragrafo_e_escapa_o_html(): void
    {
        $this->assertSame('<p>Apoio &amp; fomento &lt;b&gt;</p>', ResumoEmHtml::converter('Apoio & fomento <b>'));
    }

    public function test_resumo_vazio_ou_nulo_nao_vira_paragrafo(): void
    {
        $this->assertNull(ResumoEmHtml::converter(null));
        $this->assertNull(ResumoEmHtml::converter('  '));
    }
}
