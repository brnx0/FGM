<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ config('app.name', 'FGM') }}</title>

        {{-- Reaplica as preferências de acessibilidade e o tema antes da primeira pintura. --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function () {
                var html = document.documentElement;
                try {
                    var fonte = localStorage.getItem('a11yFonte');
                    if (fonte === '90' || fonte === '115') {
                        html.style.fontSize = fonte + '%';
                    }
                    if (localStorage.getItem('a11yContraste') === '1') {
                        html.classList.add('alto-contraste');
                    }
                } catch (e) {}

                // Modo noturno: escolha salva ('claro' | 'escuro') ou, sem escolha, o sistema.
                var tema = null;
                try {
                    tema = localStorage.getItem('tema');
                } catch (e) {}
                var sistemaEscuro = !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                if (tema === 'escuro' || (tema !== 'claro' && sistemaEscuro)) {
                    html.classList.add('escuro');
                }
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
        @inertiaHead
    </head>
    <body>
        @inertia

        {{-- VLibras (Libras): fora da retaguarda e só se ligado em Retaguarda > Portal > VLibras; a mesma
             decisão libera os domínios dele na CSP (SecurityHeaders / App\Support\VLibras). --}}
        @isset($vlibras)
            <script nonce="{{ Vite::cspNonce() }}">
                (function () {
                    var raiz = @js(\App\Support\VLibras::RAIZ);
                    var carregador = document.createElement('script');
                    // Assíncrono: se o vlibras.gov.br estiver lento ou fora do ar, o portal não espera por ele.
                    carregador.async = true;
                    carregador.src = raiz + '/vlibras-plugin.js';
                    // O load dispara logo após o carregador executar, antes de ele desenhar o botão por
                    // conta própria; o Widget aplica avatar e lado e já desenha.
                    carregador.onload = function () {
                        new window.VLibras.Widget({
                            rootPath: raiz,
                            avatar: @js($vlibras->avatar),
                            position: @js($vlibras->lado),
                        });
                    };
                    document.body.appendChild(carregador);
                })();
            </script>
        @endisset
    </body>
</html>
