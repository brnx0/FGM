<?php

return [

    /*
    | Driver de autenticação: 'govbr' (OIDC real) ou 'fake' (login simulado,
    | permitido só em local/staging).
    */
    'driver' => env('GOVBR_DRIVER', 'govbr'),

    // Staging: https://sso.staging.acesso.gov.br · Produção: https://sso.acesso.gov.br
    'base_url' => rtrim((string) env('GOVBR_BASE_URL', 'https://sso.staging.acesso.gov.br'), '/'),

    'client_id' => env('GOVBR_CLIENT_ID'),
    'client_secret' => env('GOVBR_CLIENT_SECRET'),
    'redirect_uri' => env('GOVBR_REDIRECT_URI'),
    'logout_redirect_uri' => env('GOVBR_LOGOUT_REDIRECT_URI'),
    'scopes' => env('GOVBR_SCOPES', 'openid email profile'),

    // Opcional; vazio = não consulta o nível de confiabilidade.
    'trust_level_url' => env('GOVBR_TRUST_LEVEL_URL'),

    // Relativos ao base_url.
    'endpoints' => [
        'authorize' => '/authorize',
        'token' => '/token',
        'jwks' => '/jwk',
        'logout' => '/logout',
    ],

    // Contas do login simulado: o CPF digitado escolhe a conta, e o login devolve o nome e o e-mail dela.
    'fake_accounts' => [
        ['cpf' => '52998224725', 'nome' => 'Maria Proponente Teste', 'email' => 'maria.teste@example.com'],
        ['cpf' => '11144477735', 'nome' => 'João Preposto Teste', 'email' => 'joao.teste@example.com'],
    ],

    // Conta do login simulado para a retaguarda, aceita só no ambiente local: o primeiro administrador do
    // PrimeiroGestorSeeder, de CPF fictício. Em staging a retaguarda segue sem entrada por conta de teste.
    'fake_accounts_retaguarda' => [
        ['cpf' => '00000000000', 'nome' => 'Administrador', 'email' => 'admin@admin'],
    ],

];
