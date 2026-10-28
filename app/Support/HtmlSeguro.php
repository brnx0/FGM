<?php

namespace App\Support;

use Dom\Element;
use Dom\HTMLDocument;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Limpa o HTML do editor de texto rico (descrição dos eventos e divulgação dos editais) antes de gravar.
 *
 * Allowlist explícita: só os elementos e atributos que o editor produz, com valores conferidos
 * um a um. Imagens só do próprio disco (eventos/conteudo) e com texto alternativo; vídeo só do
 * YouTube, sempre pelo domínio youtube-nocookie.com.
 */
final class HtmlSeguro
{
    /** Cores do editor (tokens do portal e verde/amarelo escuros), todas AA sobre branco. */
    public const array CORES = ['#1d1d1f', '#004d9f', '#0a2747', '#8a1c1c', '#166534', '#805b00'];

    public const string PASTA_IMAGENS = '/storage/eventos/conteudo/';

    private const array ALINHAMENTOS = ['left', 'center', 'right', 'justify'];

    private const array ELEMENTOS_ALINHAVEIS = ['p', 'h2', 'h3', 'h4'];

    /** Bytes; a validação já limita a 200 mil caracteres. Acima disso o sanitizador cortaria o texto. */
    private const int TAMANHO_MAXIMO = 1_000_000;

    private static ?HtmlSanitizer $sanitizador = null;

    public static function limpar(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $documento = HTMLDocument::createFromString(
            '<!DOCTYPE html><html><body>'.self::sanitizador()->sanitize($html).'</body></html>',
            LIBXML_NOERROR,
        );
        $corpo = $documento->body;

        self::ajustarEstrutura($corpo);

        if (trim($corpo->textContent ?? '') === '' && $corpo->querySelector('img, iframe, hr, table') === null) {
            return null;
        }

        return $corpo->innerHTML;
    }

    /** Endereço de incorporação youtube-nocookie para um link do YouTube, ou null se não for um vídeo válido. */
    public static function videoDoYoutube(string $endereco): ?string
    {
        $partes = parse_url(trim($endereco));

        if (! is_array($partes) || ($partes['scheme'] ?? '') !== 'https' || isset($partes['user']) || isset($partes['port'])) {
            return null;
        }

        $host = strtolower($partes['host'] ?? '');
        $caminho = $partes['path'] ?? '';
        parse_str($partes['query'] ?? '', $parametros);

        $id = match (true) {
            $host === 'youtu.be' => ltrim($caminho, '/'),
            in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true) && $caminho === '/watch' => $parametros['v'] ?? null,
            in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true)
                && preg_match('#^/(?:embed|shorts)/([^/]+)\z#', $caminho, $m) === 1 => $m[1],
            default => null,
        };

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}\z/', $id) === 1
            ? "https://www.youtube-nocookie.com/embed/{$id}"
            : null;
    }

    private static function sanitizador(): HtmlSanitizer
    {
        return self::$sanitizador ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowElement('p', ['style'])
                ->allowElement('br')
                ->allowElement('h2', ['style'])
                ->allowElement('h3', ['style'])
                ->allowElement('h4', ['style'])
                ->allowElement('strong')
                ->allowElement('b')
                ->allowElement('em')
                ->allowElement('i')
                ->allowElement('u')
                ->allowElement('s')
                ->allowElement('mark')
                ->allowElement('span', ['style'])
                ->allowElement('ul', ['data-type'])
                ->allowElement('ol')
                ->allowElement('li', ['data-type', 'data-checked'])
                ->allowElement('blockquote')
                ->allowElement('hr')
                ->allowElement('a', ['href'])
                ->allowElement('img', ['src', 'alt'])
                ->allowElement('table')
                ->allowElement('thead')
                ->allowElement('tbody')
                ->allowElement('tr')
                ->allowElement('th', ['colspan', 'rowspan'])
                ->allowElement('td', ['colspan', 'rowspan'])
                ->allowElement('colgroup')
                ->allowElement('col')
                ->allowElement('div', ['data-youtube-video'])
                ->allowElement('iframe', ['src', 'allowfullscreen'])
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowMediaSchemes(['http', 'https'])
                ->allowRelativeMedias()
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->withAttributeSanitizer(self::valoresDosAtributos())
                ->withMaxInputLength(self::TAMANHO_MAXIMO),
        );
    }

    /** Confere o valor de cada atributo permitido; null remove o atributo. */
    private static function valoresDosAtributos(): AttributeSanitizerInterface
    {
        return new class implements AttributeSanitizerInterface
        {
            public function getSupportedElements(): ?array
            {
                return null;
            }

            public function getSupportedAttributes(): ?array
            {
                return ['style', 'data-type', 'data-checked', 'data-youtube-video', 'colspan', 'rowspan', 'src', 'allowfullscreen'];
            }

            public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
            {
                return match ($attribute) {
                    'style' => HtmlSeguro::estilo($element, $value),
                    'data-type' => match ([$element, $value]) {
                        ['ul', 'taskList'], ['li', 'taskItem'] => $value,
                        default => null,
                    },
                    'data-checked' => $element === 'li' && in_array($value, ['true', 'false'], true) ? $value : null,
                    'data-youtube-video', 'allowfullscreen' => '',
                    'colspan', 'rowspan' => preg_match('/^[1-9]\d?\z/', $value) === 1 ? $value : null,
                    'src' => match ($element) {
                        'img' => HtmlSeguro::imagemDoDisco($value),
                        'iframe' => HtmlSeguro::videoDoYoutube($value),
                        default => null,
                    },
                    default => null,
                };
            }
        };
    }

    /**
     * Só text-align (parágrafos e títulos) e color da paleta (trechos de texto), reescritos no formato canônico.
     *
     * @internal Usado pelo sanitizador de atributos.
     */
    public static function estilo(string $element, string $valor): ?string
    {
        $declaracoes = [];

        foreach (explode(';', strtolower($valor)) as $declaracao) {
            [$propriedade, $conteudo] = array_map('trim', explode(':', $declaracao, 2)) + [1 => ''];

            if ($propriedade === 'text-align' && in_array($element, self::ELEMENTOS_ALINHAVEIS, true) && in_array($conteudo, self::ALINHAMENTOS, true)) {
                $declaracoes['text-align'] = "text-align: {$conteudo}";
            } elseif ($propriedade === 'color' && $element === 'span' && in_array($cor = self::corEmHex($conteudo), self::CORES, true)) {
                $declaracoes['color'] = "color: {$cor}";
            }
        }

        return $declaracoes === [] ? null : implode('; ', $declaracoes);
    }

    /** O Tiptap serializa a cor pelo DOM do navegador, que a escreve como rgb(r, g, b). */
    private static function corEmHex(string $cor): string
    {
        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)\z/', $cor, $m) !== 1) {
            return $cor;
        }

        return sprintf('#%02x%02x%02x', min(255, (int) $m[1]), min(255, (int) $m[2]), min(255, (int) $m[3]));
    }

    /**
     * Caminho relativo da imagem se ela está em eventos/conteudo do disco public (aceita a URL absoluta do APP_URL).
     *
     * @internal Usado pelo sanitizador de atributos.
     */
    public static function imagemDoDisco(string $endereco): ?string
    {
        $endereco = trim($endereco);
        $base = rtrim((string) config('app.url'), '/');

        if ($base !== '' && str_starts_with($endereco, $base.'/')) {
            $endereco = substr($endereco, strlen($base));
        }

        $padrao = '#^'.preg_quote(self::PASTA_IMAGENS, '#').'[A-Za-z0-9_-]+\.(?:jpe?g|png|webp)\z#';

        return preg_match($padrao, $endereco) === 1 ? $endereco : null;
    }

    /** Regras que dependem do elemento inteiro, não de um atributo só. */
    private static function ajustarEstrutura(Element $corpo): void
    {
        foreach ($corpo->querySelectorAll('img') as $imagem) {
            if (! $imagem->hasAttribute('src') || trim($imagem->getAttribute('alt') ?? '') === '') {
                $imagem->remove();
            }
        }

        foreach ($corpo->querySelectorAll('iframe') as $video) {
            $envoltorio = $video->parentElement;

            if (! $video->hasAttribute('src') || $envoltorio?->localName !== 'div' || ! $envoltorio->hasAttribute('data-youtube-video')) {
                $video->remove();
            }
        }

        // O div só existe como envoltório do vídeo (os iframes restantes são filhos diretos dele): fica só com o iframe.
        // Os demais são desembrulhados.
        foreach ($corpo->querySelectorAll('div') as $div) {
            $video = $div->hasAttribute('data-youtube-video') ? $div->querySelector('iframe') : null;

            if ($video !== null) {
                $div->replaceChildren($video);
            } elseif ($div->hasAttribute('data-youtube-video')) {
                $div->remove();
            } else {
                $div->replaceWith(...iterator_to_array($div->childNodes));
            }
        }

        $hostDoPortal = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        foreach ($corpo->querySelectorAll('a') as $link) {
            $destino = $link->getAttribute('href');

            if ($destino === null || trim($destino) === '') {
                $link->replaceWith(...iterator_to_array($link->childNodes));

                continue;
            }

            $host = strtolower((string) parse_url($destino, PHP_URL_HOST));
            if (preg_match('#^https?:#i', $destino) === 1 && $host !== $hostDoPortal) {
                $link->setAttribute('target', '_blank');
            }
        }
    }
}
