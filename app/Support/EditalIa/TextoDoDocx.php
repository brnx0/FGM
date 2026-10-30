<?php

namespace App\Support\EditalIa;

use App\Support\FalhaNaIa;
use DOMDocument;
use DOMElement;
use DOMNode;
use ZipArchive;

/**
 * Extrai o texto de um arquivo Word (.docx) na ordem do documento, para alimentar a IA.
 * Parágrafos viram linhas, linhas de tabela viram uma linha com as células separadas por " | " e as caixas de
 * seleção viram "[ ]" ou "[x]".
 */
final class TextoDoDocx
{
    public const string ERRO_ARQUIVO = 'Não foi possível ler o arquivo Word. Confira se é um .docx válido e envie de novo.';

    public const int MAXIMO_CARACTERES = 300000;

    private const string NAMESPACE_WORD = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const int MAXIMO_BYTES_DO_XML = 52428800;

    public static function extrair(string $binario): string
    {
        $xml = self::lerDocumentXml($binario);

        if (stripos($xml, '<!DOCTYPE') !== false) {
            throw new FalhaNaIa(self::ERRO_ARQUIVO);
        }

        $documento = new DOMDocument;
        $anteriorLibxml = libxml_use_internal_errors(true);

        try {
            $carregou = $documento->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anteriorLibxml);
        }

        $corpo = $carregou && $documento->documentElement !== null
            ? self::filhoDoWord($documento->documentElement, 'body')
            : null;

        if ($corpo === null) {
            throw new FalhaNaIa(self::ERRO_ARQUIVO);
        }

        $linhas = [];
        self::coletarBlocos($corpo, $linhas);

        return self::juntar($linhas);
    }

    private static function lerDocumentXml(string $binario): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'docx');

        if ($caminho === false) {
            throw new FalhaNaIa(self::ERRO_ARQUIVO);
        }

        try {
            file_put_contents($caminho, $binario);

            $zip = new ZipArchive;

            if ($zip->open($caminho) !== true) {
                throw new FalhaNaIa(self::ERRO_ARQUIVO);
            }

            try {
                $estatistica = $zip->statName('word/document.xml');

                if ($estatistica === false || $estatistica['size'] > self::MAXIMO_BYTES_DO_XML) {
                    throw new FalhaNaIa(self::ERRO_ARQUIVO);
                }

                $conteudo = $zip->getFromName('word/document.xml');
            } finally {
                $zip->close();
            }

            if (! is_string($conteudo) || $conteudo === '') {
                throw new FalhaNaIa(self::ERRO_ARQUIVO);
            }

            return $conteudo;
        } finally {
            if (is_file($caminho)) {
                @unlink($caminho);
            }
        }
    }

    /**
     * @param  array<int, string>  $linhas
     */
    private static function coletarBlocos(DOMNode $pai, array &$linhas): void
    {
        foreach ($pai->childNodes as $filho) {
            if (! $filho instanceof DOMElement || $filho->namespaceURI !== self::NAMESPACE_WORD) {
                continue;
            }

            switch ($filho->localName) {
                case 'p':
                    $linhas[] = self::textoDoParagrafo($filho);
                    break;
                case 'tbl':
                    foreach (self::linhasDaTabela($filho) as $linha) {
                        $linhas[] = $linha;
                    }
                    break;
                case 'sdt':
                    $conteudo = self::filhoDoWord($filho, 'sdtContent');
                    if ($conteudo !== null) {
                        self::coletarBlocos($conteudo, $linhas);
                    }
                    break;
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private static function linhasDaTabela(DOMElement $tabela): array
    {
        $linhas = [];

        foreach (self::filhosDoWord($tabela, ['tr', 'sdt']) as $filho) {
            if ($filho->localName === 'tr') {
                $linhas[] = self::textoDaLinha($filho);

                continue;
            }

            $conteudo = self::filhoDoWord($filho, 'sdtContent');

            if ($conteudo !== null) {
                foreach (self::filhosDoWord($conteudo, ['tr']) as $linha) {
                    $linhas[] = self::textoDaLinha($linha);
                }
            }
        }

        return $linhas;
    }

    private static function textoDaLinha(DOMElement $linha): string
    {
        $celulas = [];

        foreach (self::filhosDoWord($linha, ['tc', 'sdt']) as $filho) {
            if ($filho->localName === 'tc') {
                $celulas[] = self::textoDaCelula($filho);

                continue;
            }

            $conteudo = self::filhoDoWord($filho, 'sdtContent');

            if ($conteudo !== null) {
                foreach (self::filhosDoWord($conteudo, ['tc']) as $celula) {
                    $celulas[] = self::textoDaCelula($celula);
                }
            }
        }

        return implode(' | ', $celulas);
    }

    private static function textoDaCelula(DOMElement $celula): string
    {
        $linhas = [];
        self::coletarBlocos($celula, $linhas);

        $partes = array_filter(array_map('trim', $linhas), fn (string $linha): bool => $linha !== '');

        return implode(' ', $partes);
    }

    private static function textoDoParagrafo(DOMElement $paragrafo): string
    {
        $texto = '';
        self::acumularTexto($paragrafo, $texto);

        return $texto;
    }

    private static function acumularTexto(DOMNode $no, string &$texto): void
    {
        foreach ($no->childNodes as $filho) {
            if (! $filho instanceof DOMElement || $filho->namespaceURI !== self::NAMESPACE_WORD) {
                continue;
            }

            switch ($filho->localName) {
                case 't':
                    $texto .= strtr($filho->textContent, ['☐' => '[ ]', '☒' => '[x]', '☑' => '[x]']);
                    break;
                case 'tab':
                    $texto .= "\t";
                    break;
                case 'br':
                case 'cr':
                    $texto .= "\n";
                    break;
                case 'checkBox':
                    $texto .= self::caixaAntigaMarcada($filho) ? '[x]' : '[ ]';
                    break;
                case 'pPr':
                case 'rPr':
                case 'sdtPr':
                    break;
                default:
                    self::acumularTexto($filho, $texto);
            }
        }
    }

    private static function caixaAntigaMarcada(DOMElement $caixa): bool
    {
        foreach (['checked', 'default'] as $nome) {
            $elemento = self::filhoDoWord($caixa, $nome);

            if ($elemento !== null) {
                $valor = strtolower($elemento->getAttributeNS(self::NAMESPACE_WORD, 'val'));

                return ! in_array($valor, ['0', 'false', 'off'], true);
            }
        }

        return false;
    }

    private static function filhoDoWord(DOMNode $pai, string $nome): ?DOMElement
    {
        return self::filhosDoWord($pai, [$nome])[0] ?? null;
    }

    /**
     * @param  array<int, string>  $nomes
     * @return array<int, DOMElement>
     */
    private static function filhosDoWord(DOMNode $pai, array $nomes): array
    {
        $achados = [];

        foreach ($pai->childNodes as $filho) {
            if ($filho instanceof DOMElement && $filho->namespaceURI === self::NAMESPACE_WORD && in_array($filho->localName, $nomes, true)) {
                $achados[] = $filho;
            }
        }

        return $achados;
    }

    /**
     * @param  array<int, string>  $linhas
     */
    private static function juntar(array $linhas): string
    {
        $saida = [];
        $anteriorVazia = true;

        foreach ($linhas as $linha) {
            $linha = rtrim($linha);
            $vazia = $linha === '';

            if ($vazia && $anteriorVazia) {
                continue;
            }

            $saida[] = $linha;
            $anteriorVazia = $vazia;
        }

        return mb_substr(rtrim(implode("\n", $saida)), 0, self::MAXIMO_CARACTERES);
    }
}
