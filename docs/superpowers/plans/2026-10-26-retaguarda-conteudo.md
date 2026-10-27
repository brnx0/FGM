# Retaguarda — login de gestores e gestão do conteúdo · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Área `/retaguarda` onde gestores com papel entram (e-mail+senha ou gov.br) e gerenciam editais (com PDF), eventos e gestores exibidos no portal.

**Architecture:** Mesmo app Laravel/Inertia, mesmo guard `web` e sessão. Papéis em tabelas próprias (`roles`, `role_user`), middleware `gestor` protege `/retaguarda/*`. O fluxo gov.br existente ganha um "destino retaguarda" guardado na sessão. Editais e eventos (de volta ao banco) com arquivos no disco `public`.

**Tech Stack:** Laravel 13.38, Inertia v2, Vue 3 + TypeScript strict, Tailwind v4, Oracle 23ai (yajra/laravel-oci8), PHPUnit, Docker.

**Spec:** `docs/superpowers/specs/2026-10-26-retaguarda-conteudo-design.md`

## Global Constraints

- Seguir `CLAUDE.md` (Laravel Boost): `php artisan make:* --no-interaction` para criar arquivos; `vendor/bin/pint --dirty --format agent` antes de cada commit; testes com `php artisan test --compact <arquivo|--filter>`; **nenhuma dependência nova** (composer/npm). Ler `.ai/rules/index.md` se existir.
- Todo comando PHP no container: `docker compose exec app ...`; npm no host. O app é servido pelo container em http://127.0.0.1:8000 (nunca `php artisan serve` no Windows).
- Frontend em TypeScript (`<script setup lang="ts">`, props tipadas, sem `any`); `npm run build` roda `vue-tsc` e deve passar. Componentes Vue com raiz única.
- Identidade visual = a já aplicada no portal (tokens de `resources/css/app.css`, fontes, modo noturno, alto contraste, A-/A/A+). Sem cores novas fora dos tokens.
- Textos em pt-BR. CPF sempre string de 11 dígitos, mascarado na interface (`Cpf::mascarar`).
- Só entra na retaguarda usuário **ativo com pelo menos um papel**. Papel único: nome `Administrador`, slug `administrador`.
- Primeiro gestor (seeder): e-mail `admin@admin`, senha `123456`, nome `Administrador`, CPF `00000000000`, papel administrador. O seeder **não roda com `APP_ENV=production`**; é idempotente e não redefine a senha.
- `users.cpf` continua NOT NULL; gestor cadastrado pela tela exige CPF válido (`Cpf::valido`) e único.
- Imagens no disco `public`. **PDF do edital no disco `local` (privado)**, entregue pela rota `GET /editais/{edital:slug}/pdf` (`editais.pdf`), que dá 404 para edital excluído, despublicado ou sem PDF — ajuste ao spec §10: só assim "o PDF de um edital excluído retorna 404" (spec §12.5) se cumpre, pois arquivo em `public` ficaria acessível por `/storage/...`. PDF: só `pdf`, até 20 MB (`max:20480`). Imagem: `jpg,png,webp`, até 5 MB (`max:5120`).
- Edital publicado exige PDF; o portal também filtra editais sem PDF.
- Editais e eventos com soft delete; slug gerado do título, único, nunca reaproveitado.
- Datas "dia" seguem `App\Models\Concerns\UsaDiaCivil`.

## Review Focus

1. **Proponente logado (sem papel) abre `/retaguarda`** → vai para `/retaguarda/login` com "Você não tem acesso à retaguarda.", sem 500 e sem sair da sessão de proponente. Teste na Task 2.
2. **Gestor desativado com sessão já aberta** → na próxima requisição à retaguarda é deslogado e mandado ao login. Teste na Task 2.
3. **Editar edital sem reenviar o PDF/imagem** → os arquivos atuais são mantidos. Teste na Task 5.
4. **Arquivo que não é PDF com extensão `.pdf`** → recusado ("O arquivo precisa ser um PDF."). Teste na Task 5.
5. **E-mail com maiúsculas/espaços no login** (`  Admin@Admin `) → entra igual a `admin@admin`. Teste na Task 2.

---

## Estrutura de arquivos

```
database/migrations/…_create_roles_table.php            roles + role_user
database/migrations/…_add_gestor_columns_to_users.php   password, ativo, índice único em email
database/migrations/…_add_pdf_and_soft_deletes_to_editais.php
database/migrations/…_create_eventos_table.php
database/seeders/PrimeiroGestorSeeder.php
database/seeders/EventoSeeder.php                        (dados de app/Support/EventosMock.php)
database/seeders/arquivos/edital-modelo.pdf              (movido de public/documentos/)
app/Models/Role.php, app/Models/Evento.php
app/Http/Middleware/GarantirGestor.php                   alias 'gestor'
app/Actions/EntrarNaRetaguardaPorGovBr.php
app/Http/Controllers/Retaguarda/{LoginController,EditalController,EventoController,GestorController}.php
app/Http/Requests/Retaguarda/{SalvarEditalRequest,SalvarEventoRequest,SalvarGestorRequest}.php
resources/js/Layouts/RetaguardaLayout.vue
resources/js/Components/Retaguarda/{MenuRetaguarda,TabelaPaginada,ConfirmarExclusao}.vue
resources/js/Pages/Retaguarda/Login.vue
resources/js/Pages/Retaguarda/Editais/{Index,Form}.vue
resources/js/Pages/Retaguarda/Eventos/{Index,Form}.vue
resources/js/Pages/Retaguarda/Gestores/{Index,Form}.vue
routes/retaguarda.php                                    (carregado de routes/web.php)
tests/Feature/Retaguarda/{PapeisTest,LoginTest,GovBrRetaguardaTest,EditaisTest,EventosTest,GestoresTest}.php
```

---

### Task 1: Papéis, colunas de gestor e primeiro administrador

**Files:**
- Create: migrations `create_roles_table`, `add_gestor_columns_to_users_table`; `app/Models/Role.php`; `database/seeders/PrimeiroGestorSeeder.php`; `tests/Feature/Retaguarda/PapeisTest.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Produces:
  - Tabela `roles` (`id`, `nome` string, `slug` string único, timestamps) e `role_user` (`role_id`, `user_id`, PK composta, FKs com cascade).
  - `users`: `password` string nullable, `ativo` boolean default true, índice único em `email` (Oracle aceita vários NULL).
  - `Role::ADMINISTRADOR = 'administrador'`.
  - `User::papeis(): BelongsToMany<Role>`, `User::temPapel(string $slug): bool`, `User::ehGestor(): bool` (ativo **e** tem ao menos um papel). `password` com cast `hashed` e em `$hidden`; `ativo` cast bool. Mutator de `email`: `trim` + minúsculas, vazio → null.
  - `UserFactory::gestor()` (state): e-mail único, senha `'123456'`, CPF válido, papel administrador anexado (`afterCreating`).
  - `PrimeiroGestorSeeder::run(): void`.

- [ ] **Step 1: Testes que falham** (`PapeisTest`)

```php
public function test_seeder_cria_admin_com_papel_e_senha(): void
{
    $this->seed(PrimeiroGestorSeeder::class);

    $admin = User::where('email', 'admin@admin')->firstOrFail();
    $this->assertSame('00000000000', $admin->cpf);
    $this->assertSame('Administrador', $admin->name);
    $this->assertTrue(Hash::check('123456', $admin->password));
    $this->assertTrue($admin->temPapel(Role::ADMINISTRADOR));
    $this->assertTrue($admin->ehGestor());
}

public function test_seeder_e_idempotente_e_nao_redefine_senha(): void
{ /* seed; troca senha para 'outra'; seed de novo → 1 admin, 1 role, Hash::check('outra') */ }

public function test_seeder_nao_roda_em_producao(): void
{ /* $this->app['env']='production'; seed → User::where('email','admin@admin')->doesntExist() */ }

public function test_proponente_sem_papel_nao_e_gestor(): void
{ /* User::factory()->create() → ehGestor() false */ }

public function test_gestor_inativo_nao_e_gestor(): void
{ /* User::factory()->gestor()->create(['ativo'=>false]) → false */ }
```

- [ ] **Step 2:** `docker compose exec app php artisan test --compact tests/Feature/Retaguarda/PapeisTest.php` → FAIL.
- [ ] **Step 3:** Implemente as interfaces acima (`make:migration`, `make:model Role`, `make:seeder PrimeiroGestorSeeder`). O seeder sai cedo com `$this->command?->warn(...)` quando `app()->environment('production')`. `DatabaseSeeder` chama `PrimeiroGestorSeeder` antes dos demais. Use `firstOrCreate` por e-mail (não toca na senha de quem já existe).
- [ ] **Step 4:** Rode o teste → PASS. Rode `php artisan migrate` (dev) e `php artisan db:seed --class=PrimeiroGestorSeeder`.
- [ ] **Step 5:** Pint + commit `feat: papéis, colunas de gestor e primeiro administrador`.

---

### Task 2: Login da retaguarda por e-mail e senha, middleware e layout

**Files:**
- Create: `app/Http/Middleware/GarantirGestor.php`, `app/Http/Controllers/Retaguarda/LoginController.php`, `routes/retaguarda.php`, `resources/js/Layouts/RetaguardaLayout.vue`, `resources/js/Components/Retaguarda/MenuRetaguarda.vue`, `resources/js/Pages/Retaguarda/Login.vue`, `tests/Feature/Retaguarda/LoginTest.php`
- Modify: `bootstrap/app.php` (alias `gestor`), `routes/web.php` (`require __DIR__.'/retaguarda.php';`), `app/Providers/AppServiceProvider.php` (limitador `retaguarda-login`), `resources/js/types/` (props da retaguarda)

**Interfaces:**
- Consumes: `User::ehGestor()` (Task 1).
- Produces:
  - Rotas (prefixo `/retaguarda`, nomes `retaguarda.*`): `GET /retaguarda/login` (`retaguarda.login`), `POST /retaguarda/login` (`retaguarda.login.entrar`, `throttle:retaguarda-login`), `POST /retaguarda/sair` (`retaguarda.sair`), grupo `middleware(['auth','gestor'])` com `GET /retaguarda` (`retaguarda.inicio`) → redireciona para `retaguarda.editais.index` (até a Task 5 existir, para uma página Inertia `Retaguarda/Inicio` provisória — remova quando a Task 5 entrar).
  - `GarantirGestor`: visitante → redirect `retaguarda.login`; logado sem `ehGestor()` → redirect `retaguarda.login` com flash `erro` "Você não tem acesso à retaguarda." (não desloga o proponente); gestor que ficou inativo/sem papel **depois** de logado → `Auth::logout()`, invalida sessão, mesmo redirect e erro.
  - Login por senha: valida `email` (required, email) e `senha` (required); busca pelo e-mail normalizado; falha genérica "E-mail ou senha inválidos." (também para usuário sem papel/inativo/sem senha); sucesso → `Auth::login`, `session()->regenerate()`, `last_login_at`, redirect `retaguarda.inicio`.
  - Limitador `retaguarda-login`: 5/min por `email|ip`.
  - `retaguarda.sair`: logout + invalidate + regenerateToken; se `config('govbr.driver')==='govbr'` e a sessão marcou `retaguarda.via_govbr`, `redirect()->away($govbr->logoutUrl())`; senão `retaguarda.login`.
  - Shared prop extra: `auth.user.ehGestor: bool`.
  - `RetaguardaLayout.vue`: barra institucional + controles de acessibilidade já existentes, menu lateral (`MenuRetaguarda`: Editais, Eventos, Gestores — links por nome de rota), topo com nome do gestor e botão Sair, menu recolhível < 1024px, `FlashMessage` para `erro`/`sucesso`.

- [ ] **Step 1: Testes que falham** (`LoginTest`)

```php
test_tela_de_login_abre()                      // get('/retaguarda/login') → component('Retaguarda/Login')
test_entra_com_email_e_senha()                 // gestor; post → redirect('/retaguarda'); assertAuthenticatedAs
test_email_com_maiusculas_e_espacos_entra()    // Review Focus 5: '  Admin@Admin ' + '123456' após seed → autenticado
test_senha_errada_mensagem_generica()          // assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.']); assertGuest
test_usuario_sem_papel_nao_entra()             // proponente com senha → mesma mensagem genérica; guest
test_gestor_inativo_nao_entra()
test_limite_de_tentativas()                    // 6ª tentativa → 429
test_visitante_vai_para_login()                // get('/retaguarda') → redirect('/retaguarda/login')
test_proponente_logado_e_barrado_sem_perder_sessao() // Review Focus 1: actingAs(proponente) → redirect login + erro; assertAuthenticated
test_gestor_desativado_com_sessao_aberta_e_expulso() // Review Focus 2: actingAs(gestor); update ativo=false; get('/retaguarda') → redirect login; assertGuest
test_sair_encerra_sessao()
```

- [ ] **Step 2:** Rode → FAIL.
- [ ] **Step 3:** Implemente as interfaces. `Login.vue`: formulário e-mail/senha (`useForm`), botão "Entrar com gov.br" apontando para `retaguarda.govbr` (rota criada na Task 3 — por ora `href="/retaguarda/govbr"`), mesma identidade do portal.
- [ ] **Step 4:** Rode → PASS; suíte inteira (`php artisan test --compact`) → PASS; `npm run build` → OK.
- [ ] **Step 5:** Pint + commit `feat: login da retaguarda com e-mail e senha`.

---

### Task 3: Entrar na retaguarda pelo gov.br (real e simulado)

**Files:**
- Create: `app/Actions/EntrarNaRetaguardaPorGovBr.php`, `tests/Feature/Retaguarda/GovBrRetaguardaTest.php`
- Modify: `routes/retaguarda.php`, `app/Http/Controllers/Auth/GovBrController.php`, `app/Http/Controllers/Auth/FakeGovBrController.php`

**Interfaces:**
- Consumes: `GovBrIdentity`, fluxo de `GovBrRedirectController` (sessão `govbr.state/nonce/verifier`), `FakeLoginGate`, `User::ehGestor()`.
- Produces:
  - `GET /retaguarda/govbr` (`retaguarda.govbr`, `throttle:govbr`): grava `session(['govbr.destino' => 'retaguarda'])` e redireciona para `govbr.redirect`.
  - `EntrarNaRetaguardaPorGovBr::handle(string $cpf): ?User` — busca por CPF; se não existir ou `!ehGestor()` → `null` (não cria usuário, não loga); senão `Auth::login`, `session()->regenerate()`, `last_login_at`, `session(['retaguarda.via_govbr' => true])` e **não** altera nome/e-mail.
  - Em `GovBrController::callback` e `FakeGovBrController::store`: `session()->pull('govbr.destino')`; quando `'retaguarda'`, usar a action acima — sucesso → `retaguarda.inicio`; `null` → `retaguarda.login` com `erro` "Seu CPF não tem acesso à retaguarda."; falha do gov.br → `retaguarda.login` com o erro atual. Sem destino, o fluxo do proponente fica idêntico.

- [ ] **Step 1: Testes que falham** (reaproveite `tests/Support/FakesGovBr` e o padrão de `FakeGovBrLoginTest`)

```php
test_govbr_simulado_entra_gestor_pelo_cpf()        // gestor com cpf '52998224725' (conta fake); get('/retaguarda/govbr') → fake; post cpf → redirect('/retaguarda')
test_govbr_cpf_sem_papel_e_recusado_sem_criar_usuario() // cpf da conta fake sem user → redirect login + erro; User::count() inalterado; assertGuest
test_govbr_nao_sobrescreve_nome_do_gestor()        // gestor name 'Fulana Gestora' → continua igual após login
test_govbr_real_callback_com_destino_retaguarda()  // driver govbr, fluxo completo com FakesGovBr → '/retaguarda'
test_fluxo_do_proponente_nao_muda()                // sem destino → '/cadastro' como hoje
```

- [ ] **Step 2:** Rode → FAIL.
- [ ] **Step 3:** Implemente. Atualize o `href` do botão em `Login.vue` para `route` `retaguarda.govbr`.
- [ ] **Step 4:** `php artisan test --compact` (suíte inteira, incluindo `GovBrLoginTest` e `FakeGovBrLoginTest`) → PASS.
- [ ] **Step 5:** Pint + commit `feat: entrada na retaguarda pelo gov.br`.

---

### Task 4: PDF por edital e soft delete — lado do portal

**Files:**
- Create: migration `add_pdf_and_soft_deletes_to_editais_table`; `database/seeders/arquivos/edital-modelo.pdf` (`git mv` de `public/documentos/edital-modelo.pdf`)
- Modify: `app/Models/Edital.php`, `database/factories/EditalFactory.php`, `database/seeders/EditalSeeder.php`, `app/Http/Controllers/HomeController.php`, `app/Http/Controllers/EditalController.php`, `resources/js/Components/EditaisCarrossel.vue`, `resources/js/Pages/Editais/Show.vue`, `resources/js/types/`, `tests/Feature/PortalTest.php`, `tests/Feature/EditalScopesTest.php`, `README.md` + `docker/` (garantir `php artisan storage:link`)

**Interfaces:**
- Produces:
  - `editais.pdf` string nullable (caminho no disco `local`), `deleted_at`. `Edital` usa `SoftDeletes`; `Edital::pdfUrl(): ?string` (`route('editais.pdf', $this)` quando há PDF), `Edital::imagemUrl(): ?string` (`Storage::disk('public')->url(...)`).
  - Rota `GET /editais/{edital:slug}/pdf` (`editais.pdf`, `EditalController@pdf`): `abort_unless($edital->publicado && $edital->pdf && Storage::disk('local')->exists($edital->pdf), 404)`; `Storage::disk('local')->download($edital->pdf, "edital-{$edital->slug}.pdf")`.
  - `naVitrine` e `contarAbertos` passam a exigir `pdf` não nulo (além de publicado).
  - Props da home: cada edital ganha `pdf: string` (URL da rota). Página do edital ganha `pdf: string|null`. `EditaisCarrossel.vue` usa `edital.pdf` (remove `PDF_MODELO`).
  - `EditalFactory` com `pdf` preenchido por padrão (`'editais/teste.pdf'`) e state `semPdf()`.
  - `EditalSeeder` copia `database/seeders/arquivos/edital-modelo.pdf` para o disco `local` em `editais/{slug}.pdf` e grava o caminho.
  - Página pública e PDF de edital excluído (soft) → 404 (route model binding não traz excluídos).

- [ ] **Step 1: Testes que falham**

```php
// PortalTest
test_home_traz_o_pdf_de_cada_edital()          // editais.0.pdf === route('editais.pdf', 'pnab-ii')
test_baixa_o_pdf_do_edital()                   // Storage::fake('local')->put('editais/teste.pdf', '%PDF-1.4'); get pdf → 200, download 'edital-{slug}.pdf'
test_edital_sem_pdf_nao_aparece_no_portal()    // semPdf() publicado → has('editais',0), totalAbertos 0
test_edital_excluido_da_404_na_pagina_e_no_pdf() // delete(); get('/editais/{slug}') e '/editais/{slug}/pdf' → 404
test_pdf_de_edital_despublicado_da_404()
// EditalScopesTest: ajuste os existentes para a factory com PDF (já é padrão)
```

- [ ] **Step 2:** Rode → FAIL.
- [ ] **Step 3:** Implemente. `docker compose exec app php artisan storage:link` e documente no README (seção "Como subir").
- [ ] **Step 4:** Suíte → PASS; `npm run build` → OK; `php artisan migrate --seed` no dev e confira no navegador que o botão do edital baixa `/editais/{slug}/pdf`.
- [ ] **Step 5:** Pint + commit `feat: cada edital com seu PDF no portal`.

---

### Task 5: Retaguarda — gestão de editais

**Files:**
- Create: `app/Http/Controllers/Retaguarda/EditalController.php`, `app/Http/Requests/Retaguarda/SalvarEditalRequest.php`, `resources/js/Pages/Retaguarda/Editais/{Index,Form}.vue`, `resources/js/Components/Retaguarda/{TabelaPaginada,ConfirmarExclusao}.vue`, `tests/Feature/Retaguarda/EditaisTest.php`
- Modify: `routes/retaguarda.php` (resource `editais` exceto `show`, nomes `retaguarda.editais.*`; `PATCH /retaguarda/editais/{edital}/publicacao` → `retaguarda.editais.publicacao`), `GET /retaguarda` → `retaguarda.editais.index` (remova a página provisória da Task 2)

**Interfaces:**
- Consumes: modelo da Task 4, layout da Task 2.
- Produces:
  - `index`: filtros `busca` (título, case-insensitive), `situacao` (`em_breve|aberto|encerrado`), `publicado` (`sim|nao`); 15 por página; cada linha: título, categoria, período, situação, publicado, tem PDF.
  - `SalvarEditalRequest`: `titulo` required max 255; `resumo` required; `categoria` required max 255; `inscricoes_inicio` required date; `inscricoes_fim` required date `after_or_equal:inscricoes_inicio`; `imagem` nullable `image|mimes:jpg,png,webp|max:5120`; `pdf` nullable `file|mimes:pdf|mimetypes:application/pdf|max:20480` com mensagem "O arquivo precisa ser um PDF."; `publicado` boolean; regra: `publicado=true` exige PDF (novo ou já existente) → erro em `pdf`: "Envie o PDF do edital para publicar."; `remover_imagem` boolean.
  - Slug: `Str::slug($titulo)`, com sufixo `-2`, `-3`… considerando **inclusive** registros soft-deleted; slug não muda ao editar o título.
  - PDF em `editais/` no disco `local`, imagem em `editais/` no disco `public`; troca apaga o antigo; sem novo upload mantém o atual.
  - `destroy`: soft delete (arquivos ficam). `publicacao`: alterna publicado respeitando a regra do PDF.
  - Flash `sucesso`: "Edital salvo.", "Edital excluído.", "Edital publicado."/"Edital despublicado.".

- [ ] **Step 1: Testes que falham** (`Storage::fake('public')`, `actingAs(User::factory()->gestor()->create())`)

```php
test_lista_com_busca_e_filtros()
test_cria_edital_com_pdf_e_imagem()                    // arquivos gravados em editais/, slug gerado
test_nao_publica_sem_pdf()                             // assertSessionHasErrors(['pdf' => 'Envie o PDF do edital para publicar.'])
test_arquivo_falso_com_extensao_pdf_e_recusado()       // Review Focus 4: UploadedFile::fake()->createWithContent('edital.pdf', 'texto qualquer') → erro 'O arquivo precisa ser um PDF.'
test_editar_sem_reenviar_mantem_arquivos()             // Review Focus 3
test_trocar_pdf_apaga_o_antigo()
test_slug_unico_mesmo_com_excluido()                   // título igual a um soft-deleted → slug '-2'
test_exclui_logicamente()                              // assertSoftDeleted
test_alterna_publicacao()
test_proponente_nao_acessa()                           // redirect login
```

- [ ] **Step 2:** Rode → FAIL.
- [ ] **Step 3:** Implemente. Telas: `Index.vue` (busca, filtros, tabela paginada, ações editar/publicar/excluir com `ConfirmarExclusao`), `Form.vue` (create/edit no mesmo componente; mostra nome do PDF atual com link e prévia da imagem; checkbox "Remover imagem"). `useForm` com `forceFormData` para uploads (`_method: put` na edição).
- [ ] **Step 4:** Suíte → PASS; `npm run build` → OK.
- [ ] **Step 5:** Pint + commit `feat: gestão de editais na retaguarda`.

---

### Task 6: Eventos de volta ao banco (lado do portal)

**Files:**
- Create: migration `create_eventos_table`, `app/Models/Evento.php`, `database/factories/EventoFactory.php`, `database/seeders/EventoSeeder.php`
- Delete: `app/Support/EventosMock.php`, `tests/Unit/EventosMockTest.php`
- Modify: `app/Http/Controllers/HomeController.php`, `database/seeders/DatabaseSeeder.php`, `tests/Feature/PortalTest.php`, `resources/js/types/`

**Interfaces:**
- Produces:
  - `eventos`: `id`, `titulo`, `slug` único, `resumo` text, `data_realizacao` date, `local` nullable, `imagem` nullable, `publicado` bool default true, timestamps, `deleted_at`; índice (`publicado`, `data_realizacao`).
  - `Evento` (SoftDeletes, `UsaDiaCivil`): `scopeRealizados(Builder $q, ?CarbonInterface $agora = null)` — publicado e `data_realizacao < amanhã`, ordem decrescente; `imagemUrl(): ?string`; mutator de `data_realizacao` via `diaParaGravar`.
  - `EventoSeeder`: os 11 registros hoje em `EventosMock::exemplos()` (mesmos títulos, resumos, datas, locais e publicado; o "Festival Boca de Brasa" com `now()->addMonth()`), `updateOrCreate` por slug.
  - Home: `Evento::realizados()->limit(6)`; props idênticas às de hoje (`titulo`, `resumo`, `mes`, `ano`, `local`) + `imagem: string|null`.

- [ ] **Step 1: Testes que falham** (`PortalTest`: troque `usarEventos([...])` por `Evento::factory()`)

```php
test_home_lista_vitrine_e_eventos()   // mesmas asserções de hoje para eventos.0 (mes 'agosto', ano '2026', local)
test_home_limita_eventos_a_seis()     // 8 eventos → 6, o mais recente primeiro
test_evento_futuro_despublicado_e_excluido_ficam_de_fora()
test_home_vazia_nao_quebra()
```

- [ ] **Step 2:** Rode → FAIL.
- [ ] **Step 3:** Implemente; rode `php artisan migrate` e `db:seed --class=EventoSeeder` no dev.
- [ ] **Step 4:** Suíte → PASS; confira a home no navegador (6 eventos no carrossel, sem mudança visual).
- [ ] **Step 5:** Pint + commit `feat: eventos de volta ao banco`.

---

### Task 7: Retaguarda — gestão de eventos

**Files:**
- Create: `app/Http/Controllers/Retaguarda/EventoController.php`, `app/Http/Requests/Retaguarda/SalvarEventoRequest.php`, `resources/js/Pages/Retaguarda/Eventos/{Index,Form}.vue`, `tests/Feature/Retaguarda/EventosTest.php`
- Modify: `routes/retaguarda.php` (resource `eventos` exceto `show`, `retaguarda.eventos.*`; `PATCH …/eventos/{evento}/publicacao`)

**Interfaces:**
- Consumes: `Evento` (Task 6), `TabelaPaginada`/`ConfirmarExclusao`/layout (Tasks 2 e 5), mesma lógica de slug e arquivos da Task 5 (extraia para um trait/serviço compartilhado `App\Support\GeraSlugUnico` e `App\Support\ArquivoPublico` se repetir mais que poucas linhas).
- Produces: listagem com `busca` e filtro `publicado`, 15 por página; `SalvarEventoRequest`: `titulo` required max 255, `resumo` required, `data_realizacao` required date, `local` nullable max 255, `imagem` como em editais, `publicado` boolean, `remover_imagem` boolean. Flash: "Evento salvo.", "Evento excluído.", "Evento publicado."/"Evento despublicado.".

- [ ] **Step 1: Testes que falham**

```php
test_lista_com_busca_e_filtro()
test_cria_evento_com_imagem()
test_editar_sem_reenviar_mantem_imagem()
test_remover_imagem()
test_exclui_logicamente()
test_evento_criado_aparece_na_home()   // publicado, data passada → home has eventos.0.titulo
```

- [ ] **Step 2:** Rode → FAIL. **Step 3:** Implemente. **Step 4:** Suíte → PASS; `npm run build` → OK.
- [ ] **Step 5:** Pint + commit `feat: gestão de eventos na retaguarda`.

---

### Task 8: Retaguarda — gestão de gestores

**Files:**
- Create: `app/Http/Controllers/Retaguarda/GestorController.php`, `app/Http/Requests/Retaguarda/SalvarGestorRequest.php`, `resources/js/Pages/Retaguarda/Gestores/{Index,Form}.vue`, `tests/Feature/Retaguarda/GestoresTest.php`
- Modify: `routes/retaguarda.php` (`index, create, store, edit, update` + `PATCH …/gestores/{user}/ativo` → `retaguarda.gestores.ativo`)

**Interfaces:**
- Consumes: `User`, `Role`, `Cpf`.
- Produces:
  - `index`: só usuários com papel; colunas nome, e-mail, CPF mascarado, papel(is), ativo; busca por nome/e-mail.
  - `SalvarGestorRequest`: `name` required max 255; `email` required email max 255 único (ignorando o próprio na edição; comparado normalizado); `cpf` required, `Cpf::valido`, único (normalizado, ignorando o próprio); `senha` required na criação / nullable na edição, `min:8`, `confirmed`; `papeis` required array de slugs existentes (hoje só `administrador`).
  - Se o CPF já pertence a um proponente sem papel: **reaproveita** esse usuário (atribui papel, e-mail e senha) em vez de dar erro de duplicado *(decisão do plano — evita dois registros para a mesma pessoa)*.
  - Regras: não pode desativar a si mesmo nem remover o próprio papel administrador; não pode desativar o último administrador ativo — erro "É preciso manter ao menos um administrador ativo.". Sem exclusão.
  - Flash: "Gestor salvo.", "Gestor ativado."/"Gestor desativado.".

- [ ] **Step 1: Testes que falham**

```php
test_lista_so_quem_tem_papel()
test_cria_gestor_com_senha_e_papel()          // depois consegue entrar em /retaguarda/login
test_cpf_invalido_e_recusado()
test_cpf_de_proponente_vira_gestor_sem_duplicar()
test_edicao_sem_senha_mantem_senha()
test_nao_desativa_a_si_mesmo()
test_nao_desativa_o_ultimo_administrador()
test_desativado_nao_entra()
```

- [ ] **Step 2:** Rode → FAIL. **Step 3:** Implemente. **Step 4:** Suíte → PASS; `npm run build` → OK.
- [ ] **Step 5:** Pint + commit `feat: gestão de gestores na retaguarda`.

---

### Task 9: Fechamento — README, verificação no navegador

**Files:**
- Modify: `README.md`, `.env.example` (se algo novo), `docs/superpowers/specs/…` não muda

- [ ] **Step 1:** README: seção "Retaguarda" — endereço `/retaguarda`, acesso inicial `admin@admin` / `123456` (só local e homologação; o seeder não roda em produção), entrada pelo gov.br exige CPF cadastrado como gestor, `storage:link`.
- [ ] **Step 2:** `docker compose exec app php artisan migrate:fresh --seed` no dev, `npm run build`, e no navegador (1280 e 375, claro/escuro/alto contraste): entrar com admin@admin; criar edital com PDF e publicar; ver na home e baixar o PDF; criar evento e ver no carrossel; cadastrar gestor e entrar com ele; proponente logado barrado em `/retaguarda`. Console sem erros de CSP.
- [ ] **Step 3:** `php artisan test --compact` → tudo PASS; `composer audit` e `npm audit --omit=dev` limpos.
- [ ] **Step 4:** Commit `docs: retaguarda no README e verificação final`.
