<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ config('app.name', 'Mapa Cultura') }}</title>

        {{-- Reaplica as preferências de acessibilidade antes da primeira pintura. --}}
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
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
