# Mapa Cultura

Portal da Fundação Gregório de Mattos (FGM), Salvador/BA: vitrine de editais e eventos culturais, com entrada pelo login único gov.br (OIDC + PKCE). Proponentes não têm senha; o usuário é identificado pelo CPF.

Stack: Laravel 13, Inertia + Vue 3, Vite, Oracle 23ai Free (Docker).

## Como subir

Requisitos: Docker (no Windows, Docker/WSL com pelo menos 2 GB de RAM livres para o Oracle Free; 4 GB recomendados), Node e npm no host.

```bash
cp .env.example .env            # defina DB_PASSWORD e ORACLE_PASSWORD
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
npm i
npm run dev                     # ou "npm run build" para o bundle de produção
```

O app responde em http://localhost:8000. As portas 8000 e 1521 ficam presas a `127.0.0.1`, ou seja, não são expostas à rede.

No dia a dia, `composer run dev` sobe os containers e o Vite. **Não rode `php artisan serve` no host**: o PHP do Windows não tem a extensão `oci8`, e a aplicação quebra com `Undefined constant "Yajra\Pdo\OCI_DEFAULT"`. O servidor PHP é o do container (porta 8000).

### Banco de dados

- Oracle 23ai Free (`gvenzl/oracle-free:23-slim`), service `FREEPDB1`, `DB_SERVER_VERSION=12c`.
- `DB_PASSWORD` é repassada ao Oracle como `APP_USER_PASSWORD`. O script `docker/oracle/init/01-test-user.sh` cria também o usuário `mapacultura_test` com a mesma senha, usado pelos testes. Nenhuma senha é versionada.
- O Oracle Free não tem o Oracle Text, que o driver padrão assume. Por isso o app traz `App\Database\OracleConnection`.
- Todo comando PHP roda no container: `docker compose exec app ...`.

## Testes

```bash
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

## Login gov.br

Rotas: `GET /auth/govbr/redirect`, `GET /auth/govbr/callback`, `GET /auth/govbr/logout` (retorno do logout do gov.br) e `POST /logout`.

O `id_token` é validado com o JWKS do gov.br (cache de 1 h, só RS256), `iss`, `aud`, `exp` (tolerância de 60 s) e `nonce`; o `sub` precisa ser um CPF válido. O limite é de 10 requisições por minuto por rota e por IP.

### Pedido de credenciais ao gov.br

Ao solicitar a credencial, informe:

| Campo | Valor |
| --- | --- |
| URL de retorno | `https://fgm.techpulseglobal.com.br/auth/govbr/callback` |
| URL de logout | `https://fgm.techpulseglobal.com.br/auth/govbr/logout` |

A homologação não exige domínio oficial. A produção exige (Portaria SGD/MGI nº 7.076/2024).

### Trocar do login simulado para o real

O padrão local é `GOVBR_DRIVER=fake`: uma tela que imita o login do gov.br, com o CPF de uma conta de teste e uma senha já preenchidos (a senha não é conferida nem enviada). As contas de teste ficam em `fake_accounts`, no `config/govbr.php`. Para o login real:

```env
GOVBR_DRIVER=govbr
GOVBR_BASE_URL=https://sso.staging.acesso.gov.br   # produção: https://sso.acesso.gov.br
GOVBR_CLIENT_ID=...
GOVBR_CLIENT_SECRET=...
GOVBR_REDIRECT_URI=https://fgm.techpulseglobal.com.br/auth/govbr/callback
GOVBR_LOGOUT_REDIRECT_URI=https://fgm.techpulseglobal.com.br/auth/govbr/logout
GOVBR_SCOPES="openid email profile"
```

`GOVBR_DRIVER=fake` só funciona com `APP_ENV` em `local` ou `staging`. Em `production` a aplicação não sobe com o driver simulado, e as rotas do login simulado respondem 404.

`GOVBR_TRUST_LEVEL_URL` é opcional (vazio = não consulta); aceita o marcador `{cpf}` e é chamada com o `access_token` como Bearer. Uma falha nela não impede o login. O Roteiro marca essa API de níveis como obsoleta; a forma atual é a claim `reliability_info` do `id_token` (escopos `govbr_confiabilidades`), que esta versão ainda não lê.

Roteiro conferido em 2026-10-23 (https://roteiro-tecnico.acesso.gov.br/iniciarintegracao.html): `GET {base}/authorize`, `POST {base}/token` (form, Basic `client_id:client_secret`), `{base}/jwk` e `{base}/logout?post_logout_redirect_uri=…`. Os defaults de `config/govbr.php` seguem esses caminhos.

## Homologação (staging)

```env
APP_ENV=staging
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=<IP do proxy que termina o TLS, ou *>
```

- Fora de `local`, o app força HTTPS e cookie de sessão seguro.
- `TRUSTED_PROXIES` é necessário atrás de proxy reverso para honrar `X-Forwarded-Proto` (HSTS, URLs https). Vazio = nenhum.
- Com `APP_ENV=staging` e `GOVBR_DRIVER=fake`, todas as páginas mostram a faixa "Ambiente de teste — login simulado, não use dados reais".
- Mantenha `APP_DEBUG=false` em staging e produção.

## Segurança e auditoria

`composer audit` e `npm audit --omit=dev` devem ficar sem vulnerabilidades. O app envia CSP com nonce por requisição e demais cabeçalhos de segurança (`SecurityHeaders`).
