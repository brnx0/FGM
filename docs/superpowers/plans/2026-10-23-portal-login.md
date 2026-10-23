# Mapa Cultura — Portal público + Login gov.br · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar o portal público da FGM (home modelo A, página simples de edital, páginas institucionais) e o login de proponente/preposto via gov.br (real por OIDC+PKCE e simulado), redirecionando para "Complete seu cadastro".

**Architecture:** Laravel 13 monolito com Inertia.js + Vue 3 (rotas e sessão no servidor, páginas em Vue). Oracle via `yajra/laravel-oci8`. Login gov.br isolado em `app/Auth/GovBr/` com dois caminhos (real e simulado) que produzem o mesmo `GovBrIdentity` e convergem na action `LoginGovBrUser`. Ambiente de desenvolvimento em Docker (PHP 8.4 + oci8 e Oracle 23ai Free). Vite/Node rodam no host.

**Tech Stack:** PHP 8.4.27+, Laravel ^13.38, inertiajs/inertia-laravel ^2, yajra/laravel-oci8 ^13, firebase/php-jwt ^6, Vue 3, @inertiajs/vue3 ^2, Tailwind CSS v4, @fontsource-variable/inter, PHPUnit, Oracle Database 23ai Free (`gvenzl/oracle-free:23-slim`).

**Spec:** `docs/superpowers/specs/2026-10-23-portal-login-design.md`

## Global Constraints

- `laravel/framework` **^13.38** (mínimo absoluto 13.30.0); PHP **>= 8.4.27** no container e no host.
- Banco **Oracle** em todos os ambientes, inclusive testes (sem SQLite). Esquema só via migrations do Laravel.
- Timezone da aplicação `America/Bahia`, locale `pt_BR`.
- Rotas gov.br fixas: `GET /auth/govbr/redirect`, `GET /auth/govbr/callback`, `GET /auth/govbr/logout`, `POST /logout`.
- `.env` gov.br: `GOVBR_DRIVER` (`govbr`|`fake`), `GOVBR_CLIENT_ID`, `GOVBR_CLIENT_SECRET`, `GOVBR_REDIRECT_URI`, `GOVBR_LOGOUT_REDIRECT_URI`, `GOVBR_BASE_URL` (staging `https://sso.staging.acesso.gov.br`, produção `https://sso.acesso.gov.br`), `GOVBR_SCOPES` (default `openid email profile`), `GOVBR_TRUST_LEVEL_URL` (opcional; vazio = não consulta).
- Login simulado só com `APP_ENV` em `local` ou `staging`. Em `production` → 404, e a aplicação **não sobe** se `GOVBR_DRIVER=fake`.
- Faixa de aviso em todas as páginas quando `APP_ENV=staging` e `GOVBR_DRIVER=fake`, com o texto exato: **"Ambiente de teste — login simulado, não use dados reais"**.
- Cores: ação `#004D9F`, escuro/pressionado `#0C427B`, profundo `#0A2747`, claros `#D6EAFF` / `#ECF6FF`, parchment `#F5F5F7`, texto `#1D1D1F`, hairline `#E0E0E0`, botão gov.br `#1351B4`. **Nada de preto** (sem `#000` em fundos).
- Tipografia Inter (self-hosted via `@fontsource-variable/inter`, sem Google Fonts). Títulos peso 800, `letter-spacing: -0.025em`. Corpo 17px/400, line-height 1.44.
- Regras Apple do `DESIGN.md`: botões pílula, cards com raio 18px e borda hairline, sem sombras e sem degradês decorativos, `transform: scale(.95)` ao pressionar, alvos de toque ≥ 44px, layout funcional a partir de 360px.
- Textos da interface em português do Brasil. CPF nunca exibido inteiro: máscara `***.982.247-**`.
- Sem senha para proponentes. Usuário identificado pelo CPF (string de 11 dígitos, nunca inteiro).
- Todos os comandos PHP rodam no container: `docker compose exec app <cmd>`.

## Review Focus

1. **CPF com zero à esquerda** (`01234567890`) ou vindo formatado: deve continuar com 11 dígitos, como string, e casar com o usuário existente. Teste na Task 6.
2. **Usuário cancela no gov.br ou volta com `state` inválido** (aba antiga, duplo clique no retorno): deve voltar para a home com mensagem amigável, sem erro 500 e sem logar. Teste na Task 8.
3. **Mesmo CPF entra de novo com nome ou e-mail diferente, ou sem e-mail**: deve atualizar o mesmo registro, sem duplicar e sem quebrar com `email` nulo. Teste na Task 6.
4. **Último dia de inscrição** (`inscricoes_fim` = hoje, 23:30 em Salvador): o edital continua "Aberto" e conta no número da faixa. Teste na Task 4.
5. **Nenhum edital ou evento publicado**: a home carrega com estados vazios amigáveis e o contador mostra 0. Teste na Task 5.

---

## Estrutura de arquivos

```
docker-compose.yml
docker/php/Dockerfile
docker/oracle/init/01-test-user.sql
DESIGN.md                                  (cópia de C:\Users\Breno\Desktop\DESIGN-apple.md)
README.md
config/govbr.php
app/Enums/SituacaoEdital.php
app/Models/{User,Edital,Evento}.php
app/Support/Cpf.php
app/Auth/GovBr/GovBrIdentity.php           DTO comum (real e simulado)
app/Auth/GovBr/GovBrClient.php             OIDC real (URLs, troca de código, validação do id_token)
app/Auth/GovBr/TrustLevelFetcher.php       consulta opcional de nível (nunca lança exceção)
app/Auth/GovBr/FakeLoginGate.php           regra única de "simulado permitido?"
app/Actions/LoginGovBrUser.php             upsert + login + caminho pós-login
app/Http/Controllers/{HomeController,EditalController,CadastroController,PaginaController}.php
app/Http/Controllers/Auth/{GovBrController,FakeGovBrController,LogoutController}.php
app/Http/Middleware/{HandleInertiaRequests,SecurityHeaders}.php
database/migrations/…_create_editais_table.php, …_create_eventos_table.php, (users ajustada)
database/factories/{Edital,Evento}Factory.php
database/seeders/{DatabaseSeeder,EditalSeeder,EventoSeeder}.php
resources/css/app.css                      tokens (@theme) + base
resources/js/app.js
resources/js/Layouts/PortalLayout.vue
resources/js/Components/{SiteHeader,SiteFooter,TestEnvBanner,GovBrButton,EditalCard,EventoCard,FlashMessage}.vue
resources/js/Pages/{Home,Editais/Show,Cadastro/Completar,Auth/FakeLogin}.vue
resources/js/Pages/Institucional/{AFgm,Duvidas,Privacidade,Termos}.vue
tests/Support/FakesGovBr.php
tests/Feature/… e tests/Unit/… (por task)
```

---

### Task 1: Esqueleto Laravel, git e DESIGN.md

**Files:**
- Create: projeto Laravel na raiz `E:\Dev\Mapa_Cultura`, `.gitignore` (acrescentar `.superpowers/`), `DESIGN.md`
- Modify: `config/app.php` (timezone/locale via `.env`), `.env.example`

**Interfaces:**
- Produces: projeto Laravel ^13.38 em `E:\Dev\Mapa_Cultura`, com repositório git na branch `main`.

- [ ] **Step 1:** `git init -b main` na raiz. Crie o projeto com `composer create-project laravel/laravel _skeleton "^13.0"` (a raiz não está vazia, por causa de `docs/` e `.superpowers/`). Mova o conteúdo de `_skeleton` para a raiz e apague `_skeleton`.
- [ ] **Step 2:** Rode `composer require laravel/framework:^13.38 -W`. Esperado: `composer show laravel/framework` mostra `13.38.x` ou maior.
- [ ] **Step 3:** No `.env.example` e no `.env`, defina `APP_NAME="Mapa Cultura"`, `APP_TIMEZONE=America/Bahia`, `APP_LOCALE=pt_BR`, `APP_FALLBACK_LOCALE=pt_BR`, `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. Faça `config/app.php` ler `APP_TIMEZONE` (o default do skeleton é `UTC` fixo).
- [ ] **Step 4:** Copie `C:\Users\Breno\Desktop\DESIGN-apple.md` para `DESIGN.md`. No topo, acrescente uma seção "Adaptações Mapa Cultura" listando as substituições da seção 8 do spec (Inter peso 800, azul `#004D9F` no lugar de `#0066cc`, exceção gov.br `#1351B4`, nada de preto). Acrescente `.superpowers/` ao `.gitignore`.
- [ ] **Step 5:** Rode `php artisan --version`. Esperado: `Laravel Framework 13.38.x` ou maior.
- [ ] **Step 6:** Commit: `chore: esqueleto Laravel 13, DESIGN.md e spec`. Inclua também `docs/`.

---

### Task 2: Docker (PHP 8.4 + oci8, Oracle 23ai Free) e conexão Oracle

**Files:**
- Create: `docker/php/Dockerfile`, `docker-compose.yml`, `docker/oracle/init/01-test-user.sql`, `tests/Feature/OracleConnectionTest.php`
- Modify: `config/database.php` (via `vendor:publish` do yajra → `config/oracle.php`), `.env.example`, `phpunit.xml`

**Interfaces:**
- Produces: conexão `oracle` como `DB_CONNECTION` padrão. Serviço `app` (porta 8000, código montado em `/var/www/html`) e serviço `oracle` (porta 1521, service `FREEPDB1`). Usuários: `mapacultura` (dev) e `mapacultura_test` (testes).

- [ ] **Step 1: Dockerfile** (`docker/php/Dockerfile`). Base `php:8.4-cli` (bookworm). Instale `unzip`, `libaio1`, `git`, Composer, Oracle Instant Client 23 (pacotes *basic* e *sdk* Linux x64, de `download.oracle.com/otn_software/linux/instantclient/`, em `/opt/oracle/instantclient`, com `LD_LIBRARY_PATH` configurado) e a extensão via `echo "instantclient,/opt/oracle/instantclient" | pecl install oci8` + `docker-php-ext-enable oci8`. Instale também `pdo_oci` se o yajra exigir (confira o `composer.json` dele). `WORKDIR /var/www/html`. `CMD php artisan serve --host=0.0.0.0 --port=8000`.
- [ ] **Step 2: docker-compose.yml.**
  - `oracle`: `gvenzl/oracle-free:23-slim`, env `ORACLE_PASSWORD`, `APP_USER=mapacultura`, `APP_USER_PASSWORD`. Volume nomeado para os dados e `./docker/oracle/init:/container-entrypoint-initdb.d`. Porta `1521:1521`. Healthcheck `healthcheck.sh`.
  - `app`: build `docker/php`, volume `.:/var/www/html`, porta `8000:8000`, `depends_on: oracle: condition: service_healthy`.
- [ ] **Step 3:** `01-test-user.sql` cria o usuário `mapacultura_test`, com a mesma senha do `APP_USER_PASSWORD` documentada no README, em `FREEPDB1`, com `CREATE SESSION, CREATE TABLE, CREATE SEQUENCE, CREATE TRIGGER, CREATE VIEW` e `QUOTA UNLIMITED ON USERS`.
- [ ] **Step 4:** Suba com `docker compose up -d --build`. Rode `docker compose exec app php -v` e `docker compose exec app php -m`. Esperado: PHP `8.4.27` ou maior, e `oci8` na lista.
- [ ] **Step 5:** Rode `docker compose exec app composer require yajra/laravel-oci8:^13` e depois `php artisan vendor:publish --tag=oracle`. No `.env`: `DB_CONNECTION=oracle`, `DB_HOST=oracle`, `DB_PORT=1521`, `DB_SERVICE_NAME=FREEPDB1`, `DB_USERNAME=mapacultura`. Em `phpunit.xml`: `DB_CONNECTION=oracle`, `DB_USERNAME=mapacultura_test` e remova o override de SQLite.
- [ ] **Step 6: Teste que falha primeiro.**

```php
public function test_conecta_no_oracle(): void
{
    $this->assertSame('oracle', DB::connection()->getDriverName());
    $this->assertSame(1, (int) DB::selectOne('select 1 as ok from dual')->ok);
}
```

- [ ] **Step 7:** Rode `docker compose exec app php artisan migrate` e depois `docker compose exec app php artisan test`. Esperado: as migrations padrão rodam no Oracle e todos os testes passam.
- [ ] **Step 8:** Commit: `chore: docker com PHP 8.4+oci8 e Oracle 23ai Free`.

---

### Task 3: Inertia + Vue + tokens visuais + layout + cabeçalhos de segurança

**Files:**
- Create: `config/govbr.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `app/Http/Middleware/SecurityHeaders.php`, `resources/views/app.blade.php`, `resources/js/app.js`, `resources/css/app.css`, `resources/js/Layouts/PortalLayout.vue`, `resources/js/Components/{SiteHeader,SiteFooter,TestEnvBanner,GovBrButton,FlashMessage}.vue`, `resources/js/Pages/Home.vue` (provisória), `tests/Feature/LayoutTest.php`
- Modify: `bootstrap/app.php`, `routes/web.php`, `package.json`, `vite.config.js`

**Interfaces:**
- Produces:
  - `config('govbr.driver')`, `config('govbr.base_url')`, `config('govbr.client_id')`, `config('govbr.client_secret')`, `config('govbr.redirect_uri')`, `config('govbr.logout_redirect_uri')`, `config('govbr.scopes')`, `config('govbr.trust_level_url')`. Também `config('govbr.endpoints')` = `['authorize'=>'/authorize','token'=>'/token','jwks'=>'/jwk','logout'=>'/logout']`, relativos ao `base_url`, e `config('govbr.fake_accounts')` = `[['cpf'=>'52998224725','nome'=>'Maria Proponente Teste','email'=>'maria.teste@example.com'], ['cpf'=>'11144477735','nome'=>'João Preposto Teste','email'=>'joao.teste@example.com']]`.
  - Props Inertia compartilhadas: `auth.user` (`{nome, cpfMascarado}` ou `null`), `ambienteTeste` (bool) e `flash.erro` (string ou null).
  - `PortalLayout.vue` envolve todas as páginas: `TestEnvBanner` (se `ambienteTeste`) → `SiteHeader` → `<slot/>` → `SiteFooter`.
  - `GovBrButton.vue` (prop `size: 'sm'|'lg'`) faz GET em `/auth/govbr/redirect`.

- [ ] **Step 1: Testes que falham.**

```php
public function test_home_usa_inertia_com_props_compartilhadas(): void
{
    $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Home')
        ->where('auth.user', null)
        ->where('ambienteTeste', false));
}

public function test_ambiente_teste_ligado_so_em_staging_com_fake(): void
{
    config(['govbr.driver' => 'fake']);
    $this->app['env'] = 'staging';
    $this->get('/')->assertInertia(fn (Assert $p) => $p->where('ambienteTeste', true));
}

public function test_cabecalhos_de_seguranca(): void
{
    $this->get('/')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy');
}
```

- [ ] **Step 2:** Rode `docker compose exec app php artisan test --filter=LayoutTest`. Esperado: FAIL.
- [ ] **Step 3: Backend.**
  - Rode `composer require inertiajs/inertia-laravel:^2` e `php artisan inertia:middleware`.
  - Em `bootstrap/app.php`, registre `HandleInertiaRequests` e `SecurityHeaders` no grupo `web`, e faça visitantes não logados serem redirecionados para `/`.
  - A CSP permite `'self'`. Em `local`, permite também o host do Vite (`http://localhost:5173` e `ws:`).
  - HSTS (`max-age=31536000; includeSubDomains`) só em requisições HTTPS.
  - Fora de `local`, chame `URL::forceScheme('https')` e use `SESSION_SECURE_COOKIE=true`.
  - Rota `/` provisória → `Inertia::render('Home')`.
- [ ] **Step 4: Frontend.**
  - Rode `npm i vue @vitejs/plugin-vue @inertiajs/vue3 @fontsource-variable/inter`. O Tailwind v4 já vem no skeleton.
  - Em `app.css`, crie os tokens em `@theme`: as cores da seção Global Constraints (`--color-acao`, `--color-acao-escuro`, `--color-profundo`, `--color-nevoa`, `--color-parchment`, `--color-tinta`, `--color-hairline`, `--color-govbr`), a fonte `--font-sans: 'Inter Variable'`, os raios `pill`/`card` (18px)/`sm` (8px) e o corpo em 17px.
  - `SiteHeader`: barra branca translúcida e fixa, com `backdrop-filter: blur(20px)`. Marca "Mapa Cultura" em `#004D9F` peso 800. Links Editais (`/#editais`) · Eventos (`/#eventos`) · A FGM · Dúvidas. Deslogado mostra `GovBrButton sm`. Logado mostra o nome e um botão "Sair" (form POST `/logout` com CSRF). Abaixo de 834px vira menu hambúrguer.
  - `SiteFooter`: fundo parchment, com "Fundação Gregório de Mattos", "Ladeira da Barroquinha, 02 — Salvador/BA" e links do portal, Privacidade e Termos.
  - `TestEnvBanner`: faixa fixa no topo, fundo `#FEF3C7` e texto `#78350F`, com o texto exato do spec.
  - `FlashMessage`: mostra `flash.erro`.
  - Aviso de cookies discreto no rodapé, só informativo e sem botão de aceite (spec §10: só há o cookie de sessão essencial). Texto: "Usamos apenas cookies essenciais para manter você conectado." e link para "Privacidade".
- [ ] **Step 5:** Rode os testes. Esperado: PASS. Rode `npm run build`. Esperado: build sem erros.
- [ ] **Step 6:** Commit: `feat: base Inertia/Vue, tokens visuais, layout e cabeçalhos de segurança`.

---

### Task 4: Domínio de editais e eventos

**Files:**
- Create: `app/Enums/SituacaoEdital.php`, `app/Models/Edital.php`, `app/Models/Evento.php`, migrations `create_editais_table`/`create_eventos_table`, `database/factories/{Edital,Evento}Factory.php`, `database/seeders/{EditalSeeder,EventoSeeder}.php`, `tests/Unit/SituacaoEditalTest.php`, `tests/Feature/EditalScopesTest.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces:
  - `enum SituacaoEdital: string { case EmBreve = 'em_breve'; case Aberto = 'aberto'; case Encerrado = 'encerrado'; public function rotulo(): string }`. Rótulos: "Em breve", "Aberto", "Encerrado".
  - `Edital` (tabela `editais`): `titulo`, `slug` (único), `resumo`, `categoria`, `inscricoes_inicio` (date), `inscricoes_fim` (date), `imagem` (nullable), `publicado` (bool). Métodos: `situacao(?CarbonInterface $agora = null): SituacaoEdital`, `scopePublicados($q)`, `scopeNaVitrine($q, ?CarbonInterface $agora = null)` (publicados com `inscricoes_fim >= hoje`, ordenados por `inscricoes_inicio`) e `static contarAbertos(?CarbonInterface $agora = null): int`. Usa `slug` como route key.
  - `Evento` (tabela `eventos`): `titulo`, `slug` (único), `resumo`, `data_realizacao` (date), `local` (nullable), `imagem`, `publicado` (bool). Métodos: `scopeRealizados($q, ?CarbonInterface $agora = null)` (publicados com `data_realizacao <= hoje`, mais recentes primeiro).

- [ ] **Step 1: Testes que falham.**

```php
// SituacaoEditalTest (data provider; "agora" em America/Bahia)
// inicio 2026-11-01, fim 2026-11-15:
//   2026-10-31 10:00 → EmBreve
//   2026-11-01 00:00 → Aberto
//   2026-11-15 23:30 → Aberto        (Review Focus #4)
//   2026-11-16 00:00 → Encerrado

// EditalScopesTest
public function test_vitrine_e_contagem(): void
{
    $this->travelTo(Carbon::parse('2026-11-15 23:30', 'America/Bahia'));
    Edital::factory()->create(['inscricoes_inicio' => '2026-11-01', 'inscricoes_fim' => '2026-11-15']); // aberto, último dia
    Edital::factory()->create(['inscricoes_inicio' => '2026-12-01', 'inscricoes_fim' => '2026-12-20']); // em breve
    Edital::factory()->create(['inscricoes_inicio' => '2026-10-01', 'inscricoes_fim' => '2026-10-20']); // encerrado
    Edital::factory()->create(['publicado' => false, 'inscricoes_inicio' => '2026-11-01', 'inscricoes_fim' => '2026-11-30']);

    $this->assertCount(2, Edital::naVitrine()->get());
    $this->assertSame(1, Edital::contarAbertos());
}

public function test_eventos_realizados_so_publicados_e_passados(): void { /* 1 passado publicado, 1 futuro, 1 despublicado → count 1 */ }
```

- [ ] **Step 2:** Rode `php artisan test --filter='SituacaoEdital|EditalScopes'`. Esperado: FAIL.
- [ ] **Step 3:** Implemente as migrations, os models, o enum e as factories. `publicado` é `boolean` e o yajra gera `NUMBER(1)`, então faça cast para `bool`. Faça cast de datas como `date`. Compare datas no fuso `config('app.timezone')`: o "hoje" vem de `now()->toDateString()`.
- [ ] **Step 4:** Seeders com conteúdo baseado no site da FGM, com datas relativas a `now()` para a vitrine sempre ter dados.
  - **Editais:** "PNAB Salvador — Ciclo II" (aberto, Fomento), "SalCine 2" (aberto, Audiovisual), "Edital Pontos de Cultura" (em breve, Rede municipal), "Lei Paulo Gustavo" (encerrado).
  - **Eventos realizados:** "Jornada do Patrimônio Cultural" (ago/2026), "Curto Circuito das Artes" (2025), "Mostra Escolas Criativas" (2025), "Circuito #Reconectar".
  - **Imagens:** `null` por enquanto. O card mostra um placeholder com o fundo `--color-nevoa`.
  - O `DatabaseSeeder` chama os dois seeders.
- [ ] **Step 5:** Rode os testes. Esperado: PASS. Rode `php artisan migrate:fresh --seed`. Esperado: sem erro.
- [ ] **Step 6:** Commit: `feat: editais e eventos com situação calculada por datas`.

---

### Task 5: Home (modelo A), página do edital e páginas institucionais

**Files:**
- Create: `app/Http/Controllers/{HomeController,EditalController,PaginaController}.php`, `resources/js/Components/{EditalCard,EventoCard}.vue`, `resources/js/Pages/Editais/Show.vue`, `resources/js/Pages/Institucional/{AFgm,Duvidas,Privacidade,Termos}.vue`, `tests/Feature/PortalTest.php`
- Modify: `resources/js/Pages/Home.vue`, `routes/web.php`

**Interfaces:**
- Consumes: `Edital::naVitrine()`, `Edital::contarAbertos()`, `Evento::realizados()`, `SituacaoEdital::rotulo()` (Task 4) e `PortalLayout`/`GovBrButton` (Task 3).
- Produces:
  - **Rotas:** `GET /` (`home`), `GET /editais/{edital:slug}` (`editais.show`), `GET /a-fgm`, `GET /duvidas`, `GET /privacidade`, `GET /termos`.
  - **Props da Home:**
    - `totalAbertos: int`
    - `editais: [{titulo, slug, categoria, situacao, situacaoRotulo, inscricoesFim: 'dd/mm/aaaa'}]`
    - `eventos: [{titulo, resumo, dataRealizacao: 'mês de aaaa', local, imagem}]` (máximo 6)

- [ ] **Step 1: Testes que falham.**

```php
public function test_home_lista_vitrine_e_eventos(): void { /* cria 2 abertos + 1 em breve + 2 eventos → component('Home'), totalAbertos 2, has('editais', 3), has('eventos', 2) */ }

public function test_home_vazia_nao_quebra(): void   // Review Focus #5
{
    $this->get('/')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->where('totalAbertos', 0)->has('editais', 0)->has('eventos', 0));
}

public function test_edital_despublicado_da_404(): void { /* publicado=false → get /editais/{slug} assertNotFound */ }

public function test_paginas_institucionais_abrem(): void { /* /a-fgm, /duvidas, /privacidade, /termos → assertOk */ }
```

- [ ] **Step 2:** Rode `php artisan test --filter=PortalTest`. Esperado: FAIL.
- [ ] **Step 3:** Implemente os controllers e as rotas. Monte as props com arrays explícitos, sem serializar o model inteiro.
- [ ] **Step 4: `Home.vue`.** Siga o mockup aprovado em `.superpowers/brainstorm/*/content/portal-home-azul-prefeitura.html`, opção A. As faixas ocupam toda a largura e não têm raio.
  - **Abertura (branco):** h1 "Sua cultura começa aqui." (~56px, peso 800, encolhendo até 30px no celular). Subtítulo "Inscreva-se nos editais da Fundação Gregório de Mattos usando sua conta gov.br." Botões `GovBrButton lg` e um pill contornado "Ver editais" (`#editais`). Se o usuário estiver logado, o botão gov.br vira "Continuar meu cadastro" (`/cadastro`).
  - **Editais (`id="editais"`, fundo `#004D9F`, texto branco):** número grande `totalAbertos` e o texto "editais com inscrições abertas." (singular "edital com inscrições abertas." quando for 1). Logo abaixo, a grade de `EditalCard`.
  - **`EditalCard`:** card branco, raio 18px. Rótulo da situação + "até dd/mm/aaaa", título, categoria e o link "Saiba mais ›" para `/editais/{slug}`.
  - **Vitrine vazia:** mostra "Nenhum edital com inscrições abertas no momento. Volte em breve."
  - **"Já aconteceu." (`id="eventos"`, fundo `#ECF6FF`):** subtítulo "O que a cultura de Salvador viveu nos últimos meses." e a grade de `EventoCard` (foto ou placeholder, título, data). Sem eventos, a seção é omitida.
  - **Grades:** 3 colunas no desktop, 2 abaixo de 1068px e 1 abaixo de 640px.
- [ ] **Step 5: `Editais/Show.vue`.** Mostra título, situação, período de inscrição, categoria e resumo, mais um aviso destacado "Documentos e inscrição em breve." e o `GovBrButton`. **Páginas institucionais:** texto provisório baseado no site da FGM, cada uma com o selo "Texto provisório — a validar". A página de Privacidade cita só o cookie de sessão essencial e os dados vindos do gov.br (CPF, nome, e-mail, telefone).
- [ ] **Step 6:** Rode os testes. Esperado: PASS. Com `npm run dev` + `docker compose up`, confira `http://localhost:8000` no navegador em 1280px e 375px contra o mockup: sem preto, Inter peso 800 nos títulos, faixa azul `#004D9F`.
- [ ] **Step 7:** Commit: `feat: home do portal, página de edital e páginas institucionais`.

---

### Task 6: Usuários gov.br, CPF, login e "Complete seu cadastro"

**Files:**
- Create: `app/Support/Cpf.php`, `app/Auth/GovBr/GovBrIdentity.php`, `app/Actions/LoginGovBrUser.php`, `app/Http/Controllers/CadastroController.php`, `app/Http/Controllers/Auth/LogoutController.php`, `resources/js/Pages/Cadastro/Completar.vue`, `tests/Unit/CpfTest.php`, `tests/Feature/LoginGovBrUserTest.php`, `tests/Feature/CadastroTest.php`
- Modify: migration padrão `create_users_table` (ainda não há produção, então edite direto), `app/Models/User.php`, `database/factories/UserFactory.php`, `routes/web.php`

**Interfaces:**
- Produces:
  - `Cpf::normalizar(string $cpf): string` devolve só dígitos, completando zeros à esquerda até 11. `Cpf::valido(string $cpf): bool` faz a checagem dos dígitos verificadores e rejeita sequências repetidas. `Cpf::mascarar(string $cpf): string` devolve `***.982.247-**`.
  - `final readonly class GovBrIdentity { __construct(public string $cpf, public string $nome, public ?string $email, public bool $emailVerificado, public ?string $telefone, public ?string $nivelConfiabilidade = null) }`. O CPF chega já normalizado.
  - `LoginGovBrUser::handle(GovBrIdentity $id): User` faz upsert pelo CPF, chama `Auth::login`, `session()->regenerate()` e atualiza `last_login_at`. `LoginGovBrUser::destinoPosLogin(User $u): string` devolve `route('cadastro')` se `profile_completed_at` for null, senão `route('home')`.
  - **Tabela `users`:** `id`, `cpf` (`char(11)` único), `name`, `email` (nullable, **sem** índice único), `email_verified` (bool), `phone` (nullable), `govbr_trust_level` (nullable), `profile_completed_at` (nullable), `last_login_at` (nullable), timestamps. Sem `password` e sem `remember_token`. Remova também a tabela `password_reset_tokens` da migration padrão.
  - **Rotas:** `GET /cadastro` (`cadastro`, middleware `auth`) e `POST /logout` (`logout`).
  - `LogoutController` encerra a sessão local. Se `config('govbr.driver') === 'govbr'`, redireciona para `GovBrClient::logoutUrl()` (Task 8; até lá, redirecione para `/` e deixe um `// Task 8` no ponto). Se o driver for `fake`, redireciona para `/`.

- [ ] **Step 1: Testes que falham.**

```php
// CpfTest
$this->assertSame('01234567890', Cpf::normalizar('012.345.678-90'));
$this->assertSame('01234567890', Cpf::normalizar('1234567890'));      // perdeu o zero → recompõe (Review Focus #1)
$this->assertTrue(Cpf::valido('529.982.247-25'));
$this->assertFalse(Cpf::valido('11111111111'));
$this->assertSame('***.982.247-**', Cpf::mascarar('52998224725'));

// LoginGovBrUserTest
public function test_primeiro_acesso_cria_usuario_e_loga(): void { /* handle(identity) → assertAuthenticatedAs, users count 1, cpf '52998224725' */ }
public function test_reentrada_atualiza_sem_duplicar_e_aceita_email_nulo(): void  // Review Focus #3
{ /* cria com nome A/email X; handle com nome B/email null → count 1, name B, email null */ }
public function test_cpf_com_zero_a_esquerda_casa_usuario_existente(): void { /* user cpf '01234567890'; handle(new GovBrIdentity(Cpf::normalizar('1234567890'), …)) → count 1 */ }
public function test_destino_pos_login_e_cadastro(): void { /* profile_completed_at null → route('cadastro') */ }

// CadastroTest
public function test_cadastro_exige_login(): void { $this->get('/cadastro')->assertRedirect('/'); }
public function test_cadastro_mostra_nome_e_cpf_mascarado(): void { /* actingAs → component('Cadastro/Completar')->where('auth.user.cpfMascarado', '***.982.247-**') */ }
public function test_logout_encerra_sessao(): void { /* actingAs; post('/logout') → assertGuest, assertRedirect('/') */ }
```

- [ ] **Step 2:** Rode `php artisan test --filter='Cpf|LoginGovBrUser|Cadastro'`. Esperado: FAIL.
- [ ] **Step 3:** Implemente com as assinaturas acima e atualize `HandleInertiaRequests` para preencher `auth.user`. `Completar.vue` mostra "Olá, {nome}", o CPF mascarado, o título "Complete seu cadastro" e o texto "Em breve você poderá completar seus dados de proponente ou preposto aqui."
- [ ] **Step 4:** Rode `php artisan migrate:fresh --seed` e `php artisan test`. Esperado: tudo PASS.
- [ ] **Step 5:** Commit: `feat: usuário gov.br por CPF, pós-login e página Complete seu cadastro`.

---

### Task 7: Login gov.br simulado (local e homologação)

**Files:**
- Create: `app/Auth/GovBr/FakeLoginGate.php`, `app/Http/Controllers/Auth/FakeGovBrController.php`, `resources/js/Pages/Auth/FakeLogin.vue`, `tests/Feature/FakeGovBrLoginTest.php`
- Modify: `app/Providers/AppServiceProvider.php`, `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php` (passar a usar `FakeLoginGate`)

**Interfaces:**
- Consumes: `GovBrIdentity`, `LoginGovBrUser`, `Cpf` (Task 6) e `config('govbr.fake_accounts')` (Task 3).
- Produces:
  - `FakeLoginGate::permitido(): bool` devolve true quando `config('govbr.driver') === 'fake'` e o ambiente está em `['local','staging']`. `FakeLoginGate::bannerVisivel(): bool` devolve true quando o login é permitido e o ambiente é `staging`.
  - `FakeLoginGate::assertNaoEmProducao(): void` lança `RuntimeException('GOVBR_DRIVER=fake não é permitido em produção.')` quando o ambiente é `production` e o driver é `fake`. É chamado no `boot()` do `AppServiceProvider`.
  - **Rotas:** `GET /auth/govbr/fake` (`govbr.fake`) e `POST /auth/govbr/fake`, com `throttle:10,1`. As duas respondem 404 quando `!permitido()`. A rota `GET /auth/govbr/redirect` (`govbr.redirect`) é criada aqui e redireciona para `govbr.fake` quando o driver é fake. A Task 8 acrescenta o ramo real.

- [ ] **Step 1: Testes que falham.**

```php
public function test_login_simulado_em_local(): void
{
    config(['govbr.driver' => 'fake']);
    $this->get('/auth/govbr/redirect')->assertRedirect('/auth/govbr/fake');
    $this->post('/auth/govbr/fake', ['cpf' => '529.982.247-25', 'nome' => 'Maria Teste', 'email' => ''])
        ->assertRedirect('/cadastro');
    $this->assertAuthenticated();
    $this->assertSame('52998224725', auth()->user()->cpf);
}

public function test_cpf_invalido_volta_com_erro(): void { /* cpf '123' → assertSessionHasErrors('cpf'), assertGuest */ }

public function test_simulado_404_em_producao(): void
{
    config(['govbr.driver' => 'fake']); $this->app['env'] = 'production';
    $this->get('/auth/govbr/fake')->assertNotFound();
    $this->post('/auth/govbr/fake', ['cpf' => '52998224725', 'nome' => 'X'])->assertNotFound();
}

public function test_boot_falha_em_producao_com_fake(): void
{
    config(['govbr.driver' => 'fake']); $this->app['env'] = 'production';
    $this->expectException(RuntimeException::class);
    app(FakeLoginGate::class)->assertNaoEmProducao();
}

public function test_staging_mostra_faixa_de_teste(): void
{ /* driver fake, env staging → get('/') ->where('ambienteTeste', true); env local → false */ }
```

- [ ] **Step 2:** Rode `php artisan test --filter=FakeGovBrLoginTest`. Esperado: FAIL.
- [ ] **Step 3:** Implemente as interfaces acima.
  - **Validação do POST:** `cpf` obrigatório e válido por `Cpf::valido` (mensagem "CPF inválido."), `nome` obrigatório com no máximo 120 caracteres, `email` opcional em formato de e-mail.
  - O POST monta um `GovBrIdentity` com `emailVerificado: false` e redireciona para `LoginGovBrUser::destinoPosLogin()`.
- [ ] **Step 4: `FakeLogin.vue`.** O cabeçalho imita a ideia do gov.br sem copiar a marca: título "gov.br (simulado)" e o aviso fixo "Login simulado para testes — nenhum dado é enviado ao gov.br."
  - Lista `fake_accounts` como botões de um clique, que enviam o form.
  - Logo abaixo, um form livre com CPF, Nome e E-mail.
  - Mostra os erros de validação.
- [ ] **Step 5:** Rode os testes. Esperado: PASS. Teste manual com `.env` `GOVBR_DRIVER=fake`: Entrar → escolher Maria → cai em "Complete seu cadastro" → Sair volta para a home.
- [ ] **Step 6:** Commit: `feat: login gov.br simulado para local e homologação`.

---

### Task 8: Login gov.br real (OIDC + PKCE)

**Files:**
- Create: `app/Auth/GovBr/GovBrClient.php`, `app/Auth/GovBr/TrustLevelFetcher.php`, `app/Http/Controllers/Auth/GovBrController.php`, `tests/Support/FakesGovBr.php`, `tests/Unit/GovBrClientTest.php`, `tests/Feature/GovBrLoginTest.php`
- Modify: `routes/web.php`, `app/Http/Controllers/Auth/LogoutController.php`

**Interfaces:**
- Consumes: `config('govbr.*')` (Task 3), `GovBrIdentity`, `LoginGovBrUser`, `Cpf` (Task 6).
- Produces:
  - `GovBrClient::authorizationUrl(string $state, string $nonce, string $codeVerifier): string` monta a URL com `response_type=code`, `client_id`, `scope`, `redirect_uri`, `state`, `nonce`, `code_challenge` (base64url sem padding de `sha256($codeVerifier)` binário) e `code_challenge_method=S256`.
  - `GovBrClient::exchangeCode(string $code, string $codeVerifier): array` faz POST form no endpoint `token` com Basic auth `client_id:client_secret` e `grant_type=authorization_code`, `code`, `redirect_uri`, `code_verifier`. Devolve `['id_token'=>…, 'access_token'=>…]`. Lança `GovBrException` se a resposta não for 2xx ou se faltar o `id_token`.
  - `GovBrClient::identityFromIdToken(string $idToken, string $nonce): GovBrIdentity` valida o token com `firebase/php-jwt` e JWKS buscado do endpoint `jwks` (cache de 1h com `Cache::remember('govbr.jwks', …)`). Confere `iss` (compara `rtrim(…, '/')` com `base_url`), `aud === client_id`, `exp` (leeway de 60s) e `nonce`. Mapeia `sub`→cpf (via `Cpf::normalizar`), `name`, `email`, `email_verified` e `phone_number`. Lança `GovBrException` se algo não bater.
  - `GovBrClient::logoutUrl(): string` devolve `{base}{logout}?post_logout_redirect_uri={logout_redirect_uri}`.
  - `TrustLevelFetcher::buscar(string $cpf, string $accessToken): ?string` devolve `null` quando `trust_level_url` está vazio ou em qualquer erro (com log de aviso). Nunca lança exceção.
  - **Rotas** (`throttle:10,1`): `GET /auth/govbr/redirect` (ramo real), `GET /auth/govbr/callback` (`govbr.callback`) e `GET /auth/govbr/logout` (`govbr.logout`, redireciona para `/`).
  - **Sessão:** guarde `govbr.state`, `govbr.nonce` e `govbr.verifier`, consumidos com `session()->pull`. O `state` e o `nonce` saem de `Str::random(40)` e o verifier de `Str::random(64)`.

- [ ] **Step 1: Helper de teste `tests/Support/FakesGovBr.php`** (trait):
  - Gera um par RSA com `openssl_pkey_new` e publica o JWK em `Http::fake([config('govbr.base_url').'/jwk' => …])`.
  - `fakeTokenEndpoint(array $claims)` responde ao `/token` com um `id_token` assinado em RS256 com `kid`.
  - `claimsValidos(string $nonce): array` traz `iss` (base com `/`), `aud` (client_id), `sub` `'52998224725'`, `name`, `email`, `email_verified` true, `exp` e `nonce`.
  - `setUp` define `govbr.driver=govbr`, `client_id=teste`, `client_secret=segredo` e `base_url=https://sso.staging.acesso.gov.br`.
- [ ] **Step 2: Testes que falham.**

```php
// GovBrClientTest
public function test_authorization_url_tem_pkce_s256(): void
{
    $url = app(GovBrClient::class)->authorizationUrl('st', 'no', 'verificador');
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    $this->assertSame('S256', $q['code_challenge_method']);
    $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', 'verificador', true)), '+/', '-_'), '='), $q['code_challenge']);
    $this->assertSame(['code','teste','st','no'], [$q['response_type'],$q['client_id'],$q['state'],$q['nonce']]);
}
public function test_rejeita_nonce_diferente(): void { /* token com nonce 'x', valida com 'y' → GovBrException */ }
public function test_rejeita_aud_errado(): void { /* aud 'outro' → GovBrException */ }

// GovBrLoginTest
public function test_fluxo_completo(): void
{
    $r = $this->get('/auth/govbr/redirect');
    $r->assertRedirectContains('https://sso.staging.acesso.gov.br/authorize');
    $this->fakeTokenEndpoint($this->claimsValidos(session('govbr.nonce')));
    $this->get('/auth/govbr/callback?code=abc&state='.session('govbr.state'))->assertRedirect('/cadastro');
    $this->assertAuthenticated();
    Http::assertSent(fn ($req) => str_ends_with($req->url(), '/token') && $req['code_verifier'] !== null);
}
public function test_usuario_cancelou_no_govbr(): void   // Review Focus #2
{
    $this->get('/auth/govbr/redirect');
    $this->get('/auth/govbr/callback?error=access_denied&state='.session('govbr.state'))
        ->assertRedirect('/')->assertSessionHas('erro', 'Não foi possível entrar com o gov.br. Tente novamente.');
    $this->assertGuest();
}
public function test_state_invalido_ou_reusado(): void     // Review Focus #2
{ /* callback com state errado → redirect '/', erro, guest; e segundo callback com state já consumido → idem */ }
public function test_logout_redireciona_para_govbr(): void
{ /* actingAs; post('/logout') → assertRedirect(GovBrClient::logoutUrl()), assertGuest */ }
public function test_nivel_confiabilidade_nao_bloqueia(): void
{ /* trust_level_url definido e Http::fake devolvendo 500 → login OK, govbr_trust_level null */ }
```

- [ ] **Step 3:** Rode `composer require firebase/php-jwt:^6`, depois `php artisan test --filter=GovBr`. Esperado: FAIL.
- [ ] **Step 4:** Implemente o client, o fetcher e o controller.
  - **Callback:** se houver `error` ou se o `state` não bater com `pull('govbr.state')`, redireciona para `/` com `flash erro` igual ao texto acima. Em seguida, `exchangeCode` → `identityFromIdToken` → `TrustLevelFetcher::buscar` → `LoginGovBrUser::handle` → `destinoPosLogin`. Qualquer `GovBrException` vira o mesmo erro amigável, com `report()`.
  - **Logout:** substitua o marcador `// Task 8` do `LogoutController` pelo redirect para `logoutUrl()`.
  - Antes de fechar, confira os caminhos de `config('govbr.endpoints')` contra o Roteiro (https://roteiro-tecnico.acesso.gov.br/) e ajuste os defaults se diferirem. Registre o que conferiu no README.
- [ ] **Step 5:** Rode `php artisan test`. Esperado: tudo PASS.
- [ ] **Step 6:** Commit: `feat: login gov.br real com OIDC + PKCE`.

---

### Task 9: Fechamento — auditoria, README e verificação no navegador

**Files:**
- Create: `README.md`
- Modify: `.env.example` (todas as variáveis gov.br comentadas)

- [ ] **Step 1:** Rode `docker compose exec app composer audit` e `npm audit --omit=dev`. Esperado: nenhuma vulnerabilidade. Se houver alguma, atualize a dependência e rode os testes de novo.
- [ ] **Step 2: README** (pt-BR), com estas seções:
  - **Como subir:** `docker compose up -d --build`, `docker compose exec app composer install`, `php artisan key:generate`, `php artisan migrate --seed`, `npm i`, `npm run dev`.
  - **Testes:** `docker compose exec app php artisan test`.
  - **Pedido de credenciais gov.br:** URL de retorno `https://fgm.techpulseglobal.com.br/auth/govbr/callback` e URL de logout `https://fgm.techpulseglobal.com.br/auth/govbr/logout`. Explicar que a homologação não exige domínio oficial, mas a produção exige (Portaria SGD/MGI nº 7.076/2024).
  - **Como trocar do simulado para o real:** `GOVBR_DRIVER=govbr` + credenciais. Avisar que `GOVBR_DRIVER=fake` é bloqueado em produção.
  - **Variáveis de homologação:** `APP_ENV=staging`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`.
- [ ] **Step 2b:** Confira no `.env.example` que `APP_DEBUG=false` é o padrão sugerido para staging/produção e que o `config/govbr.php` lê todas as variáveis da seção Global Constraints.
- [ ] **Step 3: Verificação no navegador** (`npm run build`, app no Docker), em 1280px e 375px:
  - A home bate com o mockup A: nada de preto, faixa `#004D9F`, títulos Inter 800.
  - "Saiba mais" abre o edital.
  - O fluxo simulado leva a "Complete seu cadastro" e Sair volta para a home.
  - Com `APP_ENV=staging`, a faixa de aviso aparece em todas as páginas.
  - O console do navegador não tem erros de CSP.
- [ ] **Step 4:** Rode `docker compose exec app php artisan test`. Esperado: tudo PASS.
- [ ] **Step 5:** Commit: `docs: README, auditoria de dependências e verificação final`.
