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
# o container do app já roda "php artisan storage:link" ao subir (imagens públicas em /storage; PDFs dos editais ficam privados)
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

## Cadastro no portal

- Proponentes cadastram organizações (pessoas jurídicas) em "Minhas organizações", com representantes legais e vínculo de pessoas por CPF.
- O servidor consulta a BrasilAPI (CNPJ) e o ViaCEP (CEP); o servidor precisa de saída HTTPS para `brasilapi.com.br` e `viacep.com.br`; sem acesso a elas, o formulário é preenchido à mão.

## Retaguarda

Área de gestão em `/retaguarda` (login em `/retaguarda/login`): editais, eventos e usuários (`/retaguarda/usuarios`). Só entra usuário da retaguarda **ativo com pelo menos um papel** (hoje, `Administrador`).

- Acesso inicial: `admin@admin` / `123456`, criado pelo `PrimeiroGestorSeeder`. O seeder cria o papel `Administrador` em todo ambiente; o usuário `admin@admin` só fora de produção (`APP_ENV=production` cria só o papel). É idempotente (o admin é reconhecido pelo CPF de preenchimento, mesmo depois de trocar o e-mail) e não redefine a senha. Troque a senha após o primeiro acesso.
- Cada usuário da retaguarda troca a própria senha em "Trocar senha", na barra superior.
- O admin inicial tem CPF de preenchimento (`00000000000`) e entra só por e-mail e senha. Para entrar pelo gov.br, o usuário da retaguarda precisa estar cadastrado na tela de usuários com o seu CPF real, que deve ser válido.
- Usuários da retaguarda (ativos, com papel) não usam a área de cadastro do portal (`/cadastro`); o portal mostra "Ir para a retaguarda" a eles.
- Ao cadastrar um usuário com o CPF de um proponente, a tela pede confirmação: ele passa a ser usuário da retaguarda e deixa de acessar o cadastro do portal.
- Limites de envio do PHP exigidos (já definidos em `docker/php/Dockerfile`; em outro servidor, configure no `php.ini`): `upload_max_filesize=20M` (PDF do edital até 20 MB, imagem até 5 MB) e `post_max_size=32M` (PDF + imagem num mesmo envio). O cadastro de proponente mantém o limite próprio de 2 MB por arquivo.
- Imagens (editais, eventos e logos de organizações) ficam no disco `public`, servidas por `/storage` (`php artisan storage:link`, já executado ao subir o container do app). Os PDFs dos editais ficam no disco `local` (privado) e são entregues por `/editais/{slug}/pdf`, que responde 404 para edital excluído, despublicado ou sem PDF.

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
