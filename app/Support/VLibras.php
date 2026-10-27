<?php

namespace App\Support;

use App\Models\ConfiguracaoVLibras;
use Illuminate\Http\Request;

/**
 * VLibras, o tradutor de português para Libras do governo federal: em que páginas aparece e o que a CSP libera.
 *
 * Tudo o que o widget carrega sai de RAIZ (carregador, aplicativo, imagens, fontes e o intérprete em iframe);
 * os arquivos respondem 302 para o repositório oficial no jsDelivr, de onde o aplicativo importa os demais
 * módulos. A telemetria (PostHog, via cdn.jsdelivr.net/npm e us.i.posthog.com) fica bloqueada de propósito:
 * o widget a carrega dentro de try/catch e segue funcionando (fica um aviso no console fora de localhost).
 */
final class VLibras
{
    public const string RAIZ = 'https://vlibras.gov.br/app';

    /** Só o caminho do widget: o resto do vlibras.gov.br (a documentação traz jQuery) fica de fora. */
    private const string APP = self::RAIZ.'/';

    /**
     * Repositórios da organização do VLibras no jsDelivr. Após um redirecionamento a CSP compara só o domínio;
     * nos imports diretos do aplicativo (e no /npm do PostHog) o caminho é conferido. O prefixo libera qualquer
     * arquivo, versão ou commit desses repositórios (inclusive de forks, que o jsDelivr serve pelo caminho
     * original): fixar a versão (vlibras-portal@v7.12.2/app/) fecharia isso, mas o widget pararia a cada
     * lançamento do VLibras até a constante ser atualizada.
     */
    private const string CDN = 'https://cdn.jsdelivr.net/gh/spbgovbr-vlibras/';

    /**
     * Fontes acrescentadas à CSP, por diretiva, nas páginas que exibem o widget.
     *
     * @var array<string, list<string>>
     */
    public const array CSP = [
        'script-src' => [self::APP, self::CDN],
        'img-src' => [self::APP, self::CDN],
        'font-src' => [self::APP, self::CDN],
        'connect-src' => [
            self::APP,
            'https://traducao2.vlibras.gov.br',
            'https://dicionario2.vlibras.gov.br',
            'https://repositorio.vlibras.gov.br',
            'https://pt.wiktionary.org',
        ],
        'frame-src' => [self::APP],
    ];

    /**
     * Configuração do widget para a página desta requisição, ou null quando ela não o exibe: na retaguarda
     * (inclusive o login), desligado, fora de GET (envios redirecionam) ou em visita Inertia, que responde
     * JSON sem o app.blade.php (o widget entra no carregamento completo e permanece nas visitas seguintes,
     * ver HandleInertiaRequests::version).
     */
    public static function paraExibir(Request $request): ?ConfiguracaoVLibras
    {
        if (! $request->isMethod('GET') || $request->inertia() || $request->is('retaguarda', 'retaguarda/*')) {
            return null;
        }

        $configuracao = ConfiguracaoVLibras::atual();

        return $configuracao->ativo ? $configuracao : null;
    }
}
