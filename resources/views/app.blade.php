<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ config('app.name', 'Mapa Cultura') }}</title>

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
    </body>
</html>
