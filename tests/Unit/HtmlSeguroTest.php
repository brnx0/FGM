<?php

namespace Tests\Unit;

use App\Support\HtmlSeguro;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HtmlSeguroTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://portal.fgm.test']);
    }

    public function test_vazio_vira_null(): void
    {
        $this->assertNull(HtmlSeguro::limpar(null));
        $this->assertNull(HtmlSeguro::limpar('   '));
        $this->assertNull(HtmlSeguro::limpar('<p></p>'));
        $this->assertNull(HtmlSeguro::limpar('<script>alert(1)</script>'));
    }

    public function test_remove_script_e_manipuladores_de_evento(): void
    {
        $html = HtmlSeguro::limpar('<p onclick="alert(1)">Olá<script>alert(1)</script></p><img src="/storage/eventos/conteudo/a.png" alt="A" onerror="alert(1)">');

        $this->assertSame('<p>Olá</p><img src="/storage/eventos/conteudo/a.png" alt="A">', $html);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function linksPerigosos(): array
    {
        return [
            'javascript' => ['<p><a href="javascript:alert(1)">clique</a></p>'],
            'javascript com espaços e maiúsculas' => ['<p><a href=" JaVaScRiPt:alert(1)">clique</a></p>'],
            'data' => ['<p><a href="data:text/html;base64,PHNjcmlwdD4=">clique</a></p>'],
            'relativo' => ['<p><a href="/retaguarda">clique</a></p>'],
            'tab entre javascript e dois-pontos' => ['<p><a href="java&#x09;script:alert(1)">clique</a></p>'],
            'javascript em entidade' => ['<p><a href="&#106;avascript:alert(1)">clique</a></p>'],
        ];
    }

    #[DataProvider('linksPerigosos')]
    public function test_link_sem_esquema_permitido_vira_texto(string $entrada): void
    {
        $this->assertSame('<p>clique</p>', HtmlSeguro::limpar($entrada));
    }

    public function test_link_externo_ganha_rel_e_target(): void
    {
        $html = HtmlSeguro::limpar('<p><a href="https://gov.br/x" target="_self" rel="opener">gov.br</a></p>');

        $this->assertSame('<p><a href="https://gov.br/x" rel="noopener noreferrer nofollow" target="_blank">gov.br</a></p>', $html);
    }

    public function test_link_interno_e_mailto_sem_target(): void
    {
        $html = HtmlSeguro::limpar('<p><a href="https://portal.fgm.test/editais/x">edital</a> <a href="mailto:fgm@salvador.ba.gov.br">e-mail</a></p>');

        $this->assertSame(
            '<p><a href="https://portal.fgm.test/editais/x" rel="noopener noreferrer nofollow">edital</a> '
            .'<a href="mailto:fgm@salvador.ba.gov.br" rel="noopener noreferrer nofollow">e-mail</a></p>',
            $html,
        );
    }

    public function test_style_so_alinhamento_e_cor_da_paleta(): void
    {
        $html = HtmlSeguro::limpar(
            '<h2 style="text-align: center; position: fixed">Título</h2>'
            .'<p style="text-align: justify">Texto <span style="color: #004D9F; background: url(x)">azul</span> '
            .'<span style="color: #ff0000">vermelho fora</span> <span style="color: expression(alert(1))">x</span></p>'
            .'<p style="text-align: middle">sem alinhamento</p>'
        );

        $this->assertSame(
            '<h2 style="text-align: center">Título</h2>'
            .'<p style="text-align: justify">Texto <span style="color: #004d9f">azul</span> '
            .'<span>vermelho fora</span> <span>x</span></p>'
            .'<p>sem alinhamento</p>',
            $html,
        );
    }

    public function test_cor_em_rgb_do_navegador_vira_hex_da_paleta(): void
    {
        $this->assertSame(
            '<p><span style="color: #166534">verde</span> <span>fora</span></p>',
            HtmlSeguro::limpar('<p><span style="color: rgb(22, 101, 52);">verde</span> <span style="color: rgb(22, 101, 53)">fora</span></p>'),
        );
    }

    public function test_remove_class_e_atributos_fora_da_lista(): void
    {
        $html = HtmlSeguro::limpar('<p class="x" id="y" data-x="1">Texto</p>');

        $this->assertSame('<p>Texto</p>', $html);
    }

    public function test_mantem_titulos_listas_citacao_e_marcas(): void
    {
        $entrada = '<h2>A</h2><h3>B</h3><h4>C</h4>'
            .'<p><strong>n</strong> <em>i</em> <u>s</u> <s>t</s> <mark>m</mark><br>linha</p>'
            .'<ul><li><p>um</p></li></ul><ol><li><p>dois</p></li></ol>'
            .'<blockquote><p>citação</p></blockquote><hr>';

        $this->assertSame($entrada, HtmlSeguro::limpar($entrada));
    }

    public function test_lista_de_tarefas_do_tiptap(): void
    {
        $html = HtmlSeguro::limpar(
            '<ul data-type="taskList"><li data-checked="true" data-type="taskItem">'
            .'<label><input type="checkbox" checked="checked"><span></span></label><div><p>feito</p></div></li>'
            .'<li data-checked="talvez" data-type="outro"><label><input type="checkbox"><span></span></label><div><p>a fazer</p></div></li></ul>'
        );

        $this->assertSame(
            '<ul data-type="taskList"><li data-checked="true" data-type="taskItem"><p>feito</p></li>'
            .'<li><p>a fazer</p></li></ul>',
            $html,
        );
    }

    public function test_tabela_com_colspan_e_rowspan(): void
    {
        $html = HtmlSeguro::limpar(
            '<table style="min-width: 75px"><colgroup><col style="min-width: 25px"><col></colgroup>'
            .'<tbody><tr><th colspan="2" rowspan="1" onclick="x()"><p>Cabeçalho</p></th></tr>'
            .'<tr><td colspan="1" rowspan="1"><p>a</p></td><td colspan="abc"><p>b</p></td></tr></tbody></table>'
        );

        $this->assertSame(
            '<table><colgroup><col><col></colgroup>'
            .'<tbody><tr><th colspan="2" rowspan="1"><p>Cabeçalho</p></th></tr>'
            .'<tr><td colspan="1" rowspan="1"><p>a</p></td><td><p>b</p></td></tr></tbody></table>',
            $html,
        );
    }

    public function test_imagem_do_proprio_disco_com_alt(): void
    {
        $this->assertSame(
            '<p>x</p><img src="/storage/eventos/conteudo/abc_1-2.webp" alt="Roda de capoeira">',
            HtmlSeguro::limpar('<p>x</p><img src="/storage/eventos/conteudo/abc_1-2.webp" alt="Roda de capoeira" title="t" width="10">'),
        );
    }

    public function test_imagem_com_url_absoluta_do_app_vira_relativa(): void
    {
        $this->assertSame(
            '<img src="/storage/eventos/conteudo/a.png" alt="A">',
            HtmlSeguro::limpar('<img src="https://portal.fgm.test/storage/eventos/conteudo/a.png" alt="A">'),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function imagensRecusadas(): array
    {
        return [
            'sem alt' => ['<p>x</p><img src="/storage/eventos/conteudo/a.png">'],
            'alt vazio' => ['<p>x</p><img src="/storage/eventos/conteudo/a.png" alt="  ">'],
            'domínio externo' => ['<p>x</p><img src="https://evil.test/storage/eventos/conteudo/a.png" alt="A">'],
            'outra pasta do disco' => ['<p>x</p><img src="/storage/eventos/capa.png" alt="A">'],
            'subindo de pasta' => ['<p>x</p><img src="/storage/eventos/conteudo/../../editais/a.png" alt="A">'],
            'data uri' => ['<p>x</p><img src="data:image/png;base64,iVBORw0KGgo=" alt="A">'],
            'javascript' => ['<p>x</p><img src="javascript:alert(1)" alt="A">'],
        ];
    }

    #[DataProvider('imagensRecusadas')]
    public function test_imagem_recusada_e_removida(string $entrada): void
    {
        $this->assertSame('<p>x</p>', HtmlSeguro::limpar($entrada));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function enderecosDoYoutube(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'],
            'youtu.be' => ['https://youtu.be/dQw4w9WgXcQ'],
            'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?controls=0'],
            'nocookie' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'shorts' => ['https://youtube.com/shorts/dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('enderecosDoYoutube')]
    public function test_video_do_youtube_vira_nocookie(string $endereco): void
    {
        $html = HtmlSeguro::limpar(
            '<div data-youtube-video=""><iframe src="'.$endereco.'" width="640" height="480" allow="autoplay" allowfullscreen="true" onload="x()"></iframe></div>'
        );

        $this->assertSame(
            '<div data-youtube-video=""><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" allowfullscreen=""></iframe></div>',
            $html,
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function iframesRecusados(): array
    {
        return [
            'outro domínio' => ['https://evil.test/embed/dQw4w9WgXcQ'],
            'domínio parecido' => ['https://www.youtube.com.evil.test/embed/dQw4w9WgXcQ'],
            'http' => ['http://www.youtube.com/embed/dQw4w9WgXcQ'],
            'id inválido' => ['https://www.youtube.com/embed/abc"onload'],
            'sem esquema' => ['//www.youtube.com/embed/dQw4w9WgXcQ'],
            'id com quebra de linha' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ%0A'],
            'javascript' => ['javascript:alert(1)'],
        ];
    }

    #[DataProvider('iframesRecusados')]
    public function test_iframe_que_nao_e_do_youtube_e_removido(string $endereco): void
    {
        $html = HtmlSeguro::limpar('<p>x</p><div data-youtube-video=""><iframe src="'.$endereco.'"></iframe></div><iframe src="'.$endereco.'"></iframe>');

        $this->assertSame('<p>x</p>', $html);
    }

    public function test_iframe_perde_srcdoc(): void
    {
        $html = HtmlSeguro::limpar(
            '<div data-youtube-video=""><iframe srcdoc="<script>alert(1)</script>" src="https://youtu.be/dQw4w9WgXcQ"></iframe></div>'
        );

        $this->assertSame('<div data-youtube-video=""><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe></div>', $html);
    }

    public function test_imagem_perde_srcset(): void
    {
        $this->assertSame(
            '<img src="/storage/eventos/conteudo/a.png" alt="A">',
            HtmlSeguro::limpar('<img src="/storage/eventos/conteudo/a.png" alt="A" srcset="https://evil.test/a.png 2x" sizes="100vw">'),
        );
    }

    public function test_limpar_e_idempotente(): void
    {
        $limpo = HtmlSeguro::limpar(
            '<h2 style="text-align: center">Programação</h2>'
            .'<p style="text-align: justify">Texto <strong>forte</strong> <em>i</em> <u>u</u> <s>s</s> <mark>m</mark> '
            .'<span style="color: rgb(22, 101, 52);">verde</span> &lt;tag&gt; &amp; "aspas" <a href="https://gov.br/?a=1&amp;b=2">link</a> '
            .'<a href="mailto:fgm@salvador.ba.gov.br">e-mail</a><br>linha</p>'
            .'<ul data-type="taskList"><li data-checked="true" data-type="taskItem"><label><input type="checkbox"></label><div><p>feito</p></div></li></ul>'
            .'<ol><li><p>um</p><ul><li><p>dois</p></li></ul></li></ol><blockquote><p>citação</p></blockquote><hr>'
            .'<table><colgroup><col></colgroup><tbody><tr><th colspan="2" rowspan="1"><p>Cab</p></th></tr><tr><td><p>a</p></td><td><p>b</p></td></tr></tbody></table>'
            .'<img src="/storage/eventos/conteudo/a.png" alt="Roda &quot;de&quot; capoeira">'
            .'<div data-youtube-video=""><iframe allowfullscreen="true" src="https://youtu.be/dQw4w9WgXcQ"></iframe></div>'
        );

        $this->assertNotNull($limpo);
        $this->assertSame($limpo, HtmlSeguro::limpar($limpo));
    }

    public function test_div_fora_do_video_so_mantem_o_conteudo(): void
    {
        $this->assertSame('<p>texto</p>', HtmlSeguro::limpar('<div class="x" style="color: #004d9f"><p>texto</p></div>'));
    }

    public function test_remove_elementos_perigosos_com_conteudo(): void
    {
        $html = HtmlSeguro::limpar(
            '<p>ok</p><style>body{display:none}</style><svg><script>alert(1)</script></svg>'
            .'<object data="x"></object><form action="/x"><input name="a"></form><math><mi>x</mi></math>'
        );

        $this->assertSame('<p>ok</p>', $html);
    }

    public function test_escapa_texto(): void
    {
        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', HtmlSeguro::limpar('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>'));
    }

    public function test_aceita_conteudo_longo(): void
    {
        $paragrafo = '<p>'.str_repeat('a', 900).'</p>';
        $entrada = str_repeat($paragrafo, 200);

        $this->assertSame($entrada, HtmlSeguro::limpar($entrada));
    }
}
