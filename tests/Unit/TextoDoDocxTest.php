<?php

namespace Tests\Unit;

use App\Support\EditalIa\TextoDoDocx;
use App\Support\FalhaNaIa;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class TextoDoDocxTest extends TestCase
{
    private const string W = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';

    private function docx(string $corpo, ?string $prefixo = null, string $arquivo = 'word/document.xml'): string
    {
        $xml = ($prefixo ?? '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
            .'<w:document '.self::W.'><w:body>'.$corpo.'</w:body></w:document>';

        $caminho = tempnam(sys_get_temp_dir(), 'teste');
        $zip = new ZipArchive;
        $zip->open($caminho, ZipArchive::OVERWRITE);
        $zip->addFromString($arquivo, $xml);
        $zip->close();
        $binario = file_get_contents($caminho);
        unlink($caminho);

        return $binario;
    }

    public function test_paragrafos_na_ordem_com_tabulacao_e_quebra(): void
    {
        $corpo = '<w:p><w:r><w:t>Primeiro</w:t></w:r><w:r><w:tab/></w:r><w:r><w:t>depois</w:t></w:r></w:p>'
            .'<w:p><w:r><w:t>Linha A</w:t><w:br/><w:t>Linha B</w:t></w:r></w:p>'
            .'<w:p/><w:p/><w:p><w:r><w:t>Fim</w:t></w:r></w:p>';

        $this->assertSame("Primeiro\tdepois\nLinha A\nLinha B\n\nFim", TextoDoDocx::extrair($this->docx($corpo)));
    }

    public function test_tabela_vira_uma_linha_por_linha_com_celulas_separadas(): void
    {
        $celula = fn (string ...$paragrafos): string => '<w:tc>'.implode('', array_map(
            fn (string $p): string => '<w:p><w:r><w:t>'.$p.'</w:t></w:r></w:p>',
            $paragrafos,
        )).'</w:tc>';
        $corpo = '<w:p><w:r><w:t>Antes</w:t></w:r></w:p>'
            .'<w:tbl><w:tr>'.$celula('Nome').$celula('Valor').'</w:tr>'
            .'<w:tr>'.$celula('Ana', 'Maria').$celula('10').'</w:tr></w:tbl>'
            .'<w:p><w:r><w:t>Depois</w:t></w:r></w:p>';

        $this->assertSame("Antes\nNome | Valor\nAna Maria | 10\nDepois", TextoDoDocx::extrair($this->docx($corpo)));
    }

    public function test_caixas_de_selecao_viram_marcadores(): void
    {
        $antiga = fn (string $estado): string => '<w:r><w:fldChar w:fldCharType="begin"><w:ffData><w:checkBox>'.$estado.'</w:checkBox></w:ffData></w:fldChar></w:r>';
        $corpo = '<w:p><w:r><w:t>☐ Sim ☒ Não ☑ Talvez</w:t></w:r></w:p>'
            .'<w:p>'.$antiga('<w:default w:val="0"/><w:checked/>').'<w:r><w:t> marcada</w:t></w:r></w:p>'
            .'<w:p>'.$antiga('<w:default w:val="0"/>').'<w:r><w:t> desmarcada</w:t></w:r></w:p>'
            .'<w:p>'.$antiga('<w:default w:val="1"/>').'<w:r><w:t> padrão</w:t></w:r></w:p>';

        $this->assertSame(
            "[ ] Sim [x] Não [x] Talvez\n[x] marcada\n[ ] desmarcada\n[x] padrão",
            TextoDoDocx::extrair($this->docx($corpo)),
        );
    }

    public function test_conteudo_de_controle_de_conteudo_entra(): void
    {
        $corpo = '<w:sdt><w:sdtPr><w:alias w:val="oculto"/></w:sdtPr><w:sdtContent><w:p><w:r><w:t>Bloco</w:t></w:r></w:p></w:sdtContent></w:sdt>'
            .'<w:p><w:sdt><w:sdtContent><w:r><w:t>Em linha</w:t></w:r></w:sdtContent></w:sdt></w:p>';

        $this->assertSame("Bloco\nEm linha", TextoDoDocx::extrair($this->docx($corpo)));
    }

    public function test_arquivo_que_nao_e_zip_lanca_a_falha(): void
    {
        $this->expectException(FalhaNaIa::class);
        $this->expectExceptionMessage(TextoDoDocx::ERRO_ARQUIVO);

        TextoDoDocx::extrair('isto não é um zip');
    }

    public function test_zip_sem_document_xml_lanca_a_falha(): void
    {
        $this->expectException(FalhaNaIa::class);
        $this->expectExceptionMessage(TextoDoDocx::ERRO_ARQUIVO);

        TextoDoDocx::extrair($this->docx('<w:p/>', null, 'word/outro.xml'));
    }

    public function test_entidade_externa_nao_e_expandida(): void
    {
        $prefixo = '<?xml version="1.0"?><!DOCTYPE d [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>';
        $binario = $this->docx('<w:p><w:r><w:t>&xxe;</w:t></w:r></w:p>', $prefixo);

        try {
            $texto = TextoDoDocx::extrair($binario);
        } catch (FalhaNaIa $falha) {
            $this->assertSame(TextoDoDocx::ERRO_ARQUIVO, $falha->getMessage());

            return;
        }

        $this->assertStringNotContainsString('root:', $texto);
    }
}
