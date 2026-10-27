# Cadastro de pessoa jurídica e Minhas organizações · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Depois da pessoa física, a pessoa pode cadastrar organizações (PJ), preenchidas pelos dados públicos do CNPJ, e gerenciá-las em "Minhas organizações", com acesso por vínculo de CPF.

**Architecture:** Três tabelas novas (`organizacoes`, `representantes_legais`, `organizacao_vinculos`), com acesso decidido por uma policy que olha o CPF do usuário nos vínculos. Consultas de CNPJ (BrasilAPI) e CEP (ViaCEP) passam pelo nosso servidor, em classes isoladas e rotas JSON com limite. Telas Inertia/Vue no layout do proponente, reaproveitando `CampoCadastro`, `SecaoCadastro`, `FaixaPortal` e `EtapasCadastro`.

**Tech Stack:** Laravel 13, Inertia v2, Vue 3 + TypeScript strict, Tailwind v4, Oracle 23ai (`yajra/laravel-oci8`), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-27-cadastro-pessoa-juridica-design.md`

## Global Constraints

- Seguir `CLAUDE.md` (Laravel Boost): arquivos via `php artisan make:* --no-interaction`; `vendor/bin/pint --dirty --format agent` antes de cada commit; `php artisan test --compact` no escopo da tarefa; **sem dependências novas** (nem composer nem npm).
- Comandos PHP rodam **no container** (`docker compose exec app php artisan ...`); o PHP do host não tem oci8.
- Oracle: `''` é gravado como `NULL`; colunas de dia usam `App\Models\Concerns\UsaDiaCivil`; nada de `migrate:fresh` no banco de desenvolvimento (só `migrate`).
- Rotas de pessoa logada no portal com `auth` + `nao-gestor`. Rotas de organização também exigem a pessoa física completa (middleware `cadastro-completo`, Task 4).
- Frontend em TypeScript strict (`vue-tsc` roda no `npm run build`); tipos em `resources/js/types/index.ts`.
- Textos em português do Brasil; mensagens de validação no padrão do `SalvarPessoaFisicaRequest` (`'Este campo é obrigatório.'`, `'Selecione uma opção da lista.'`).
- CPF: `App\Support\Cpf` (string de 11 dígitos). CNPJ: `App\Support\Cnpj` (Task 1), 14 caracteres maiúsculos sem máscara.
- Commits só com os arquivos da própria tarefa (nada de `git add -A`), terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Identidade visual atual do portal (tokens de `resources/css/app.css`, modo noturno, alto contraste); sem preto.

## Review Focus

1. **CNPJ alfanumérico digitado em minúsculas ou com máscara** (`12.abc.345/01de-35`) — deve ser aceito e gravado `12ABC34501DE35`. Teste na Task 1 e na Task 5.
2. **Troca do id na URL** (`/organizacoes/{outra}/editar`, `PUT`, vínculos) por quem não está vinculado — 403, sem vazar dados. Testes nas Tasks 5 e 7.
3. **BrasilAPI/ViaCEP fora do ar, lentas ou respondendo 404/500** — a rota responde `{ "encontrado": false }` em vez de erro 500, e o formulário segue manual. Testes na Task 3.
4. **Campos opcionais vazios e "Sem número"** — `''` vira `NULL`; com "Sem número" marcado o número é gravado `NULL` mesmo se veio preenchido. Teste na Task 5.
5. **Pessoa vinculada antes do primeiro login** — ao entrar pela primeira vez pelo gov.br e completar a pessoa física, vê a organização em Minhas organizações. Teste na Task 7.

---

### Task 1: CNPJ e listas oficiais

**Files:**
- Create: `app/Support/Cnpj.php`, `app/Support/NaturezasJuridicas.php`, `app/Support/Municipios.php`, `database/data/municipios.json`
- Test: `tests/Unit/CnpjTest.php`, `tests/Unit/NaturezasJuridicasTest.php`, `tests/Unit/MunicipiosTest.php`

**Interfaces:**
- Produces:
  - `Cnpj::normalizar(string $cnpj): string` — remove `.`, `/`, `-` e espaços, maiúsculas.
  - `Cnpj::valido(string $cnpj): bool` — 12 primeiros caracteres `[0-9A-Z]`, 2 últimos dígitos; valor de cada caractere = `ord($c) - 48`; DV1 com pesos `5,4,3,2,9,8,7,6,5,4,3,2`, DV2 com `6,5,4,3,2,9,8,7,6,5,4,3,2`; `resto = soma % 11`, `dv = resto < 2 ? 0 : 11 - resto`. Recusa 14 caracteres iguais.
  - `Cnpj::formatar(string $cnpj): string` — `12ABC34501DE35` → `12.ABC.345/01DE-35`.
  - `NaturezasJuridicas::TODAS: array<string, string>` (código de 4 dígitos => descrição), `NaturezasJuridicas::sugereFinalidadeLucrativa(string $codigo): bool` (primeiro dígito `2` → true; demais → false).
  - `Municipios::buscar(string $termo, int $limite = 20): list<array{codigo: string, nome: string, uf: string}>` (sem acento e sem caixa; começa-com antes de contém), `Municipios::porCodigo(string $codigo): ?array{codigo: string, nome: string, uf: string}`, `Municipios::existe(string $codigo): bool`.

- [ ] **Step 1: Baixar as listas oficiais (nunca de memória)**
  - Municípios: `https://servicodados.ibge.gov.br/api/v1/localidades/municipios` → gravar em `database/data/municipios.json` como lista de `{"codigo":"2927408","nome":"Salvador","uf":"BA"}` (código IBGE de 7 dígitos, UF de `microrregiao.mesorregiao.UF.sigla`; alguns municípios novos vêm com `microrregiao: null` — usar `regiao-imediata.regiao-intermediaria.UF.sigla`). Esperado: 5.570 ou 5.571 itens.
  - Naturezas jurídicas: tabela oficial da Receita/CONCLA (Tabela de Natureza Jurídica 2021, `https://concla.ibge.gov.br/estrutura/natjur-estrutura/natureza-juridica/natureza-juridica-2021`), copiada para a constante `TODAS` (só códigos ativos; `2062` => `Sociedade Empresária Limitada`).
  - Se a rede estiver bloqueada, parar e reportar `BLOCKED` — não inventar os dados.

- [ ] **Step 2: Testes que falham**
  - `CnpjTest`: `valido('11.222.333/0001-81')` true; `valido('12.ABC.345/01DE-35')` true; `valido('12.abc.345/01de-35')` true; `valido('11.222.333/0001-82')` false; `valido('12.ABC.345/01DE-36')` false; `valido('00000000000000')` false; `valido('11222333000')` false; `valido('1A.222.333/0001-8B')` false (DV com letra); `normalizar('12.abc.345/01de-35') === '12ABC34501DE35'`; `formatar('11222333000181') === '11.222.333/0001-81'`.
  - `NaturezasJuridicasTest`: `TODAS['2062']` existe; `sugereFinalidadeLucrativa('2062')` true; `sugereFinalidadeLucrativa('3999')` false; `sugereFinalidadeLucrativa('1031')` false.
  - `MunicipiosTest`: `porCodigo('2927408')` = Salvador/BA; `buscar('salva')[0]['nome'] === 'Salvador'`; `buscar('sao paulo')` contém São Paulo/SP; `existe('9999999')` false; `count(buscar('a', 20)) === 20`.

- [ ] **Step 3: Implementar as três classes** (`Municipios` lê o JSON uma vez por requisição, guardado em propriedade estática).
- [ ] **Step 4: Rodar** `php artisan test --compact tests/Unit/CnpjTest.php tests/Unit/NaturezasJuridicasTest.php tests/Unit/MunicipiosTest.php` — PASS.
- [ ] **Step 5: Commit** `feat: CNPJ alfanumérico e listas oficiais de municípios e naturezas jurídicas`

---

### Task 2: Tabelas e models da organização

**Files:**
- Create: migrations `create_organizacoes_table`, `create_representantes_legais_table`, `create_organizacao_vinculos_table`; models `Organizacao`, `RepresentanteLegal`, `VinculoOrganizacao`; enums `App\Enums\PorteOrganizacao`, `TipoDocumentoIdentidade`, `OrigemRepresentacao`; factories das três models
- Modify: `app/Models/User.php`
- Test: `tests/Feature/OrganizacaoModelTest.php`

**Interfaces:**
- Consumes: `Cnpj`, `Cpf`.
- Produces:
  - `organizacoes`: `id`, `cnpj` (string 14, único), `razao_social`, `nome_fantasia?`, `natureza_juridica` (string 4), `mei` (bool, padrão false), `finalidade_lucrativa?` (bool), `municipio_ibge` (string 7), `inscricao_municipal?` (30), `inscricao_estadual?` (30), `porte?`, `data_fundacao?` (date), `telefone?` (20), `logo?`, `cep?` (8), `logradouro?`, `numero?` (20), `sem_numero` (bool, padrão false), `complemento?` (120), `bairro?` (120), `cidade?` (120), `uf?` (2), timestamps.
  - `representantes_legais`: `id`, `organizacao_id` (cascade), `ordem` (int), `cpf` (11), `nome`, `tipo_documento?`, `numero_documento?` (30), `cargo?` (120), `telefone?` (20), `email?`, `origem?`, `origem_descricao?` (255), timestamps.
  - `organizacao_vinculos`: `id`, `organizacao_id` (cascade), `cpf` (11, indexado), `nome`, `responsavel` (bool, padrão false), timestamps, único (`organizacao_id`, `cpf`).
  - Enums (valor => rótulo via `rotulo(): string`, como `IdentidadeGenero`): `PorteOrganizacao`: `micro` Micro, `pequeno` Pequeno, `medio` Médio, `grande` Grande porte. `TipoDocumentoIdentidade`: `rg` RG, `cnh` CNH, `passaporte` Passaporte, `rne_crnm` RNE/CRNM, `outro` Outro. `OrigemRepresentacao`: `contrato_social` Contrato social, `procuracao` Procuração, `ata_eleicao` Ata de eleição, `outro` Outro.
  - `Organizacao`: `representantes(): HasMany` (ordenado por `ordem`), `vinculos(): HasMany`, `scopeDaPessoa(Builder $q, User $user)` (existe vínculo com `$user->cpf`), `temVinculo(User $user): bool`, `ehResponsavel(User $user): bool`, `logoUrl(): ?string` (URL relativa do disco `public`), mutator de `cnpj` com `Cnpj::normalizar`, `data_fundacao` com `UsaDiaCivil`, casts dos enums e booleanos.
  - `Organizacao::LIMITE_REPRESENTANTES = 10`, `Organizacao::LIMITE_VINCULOS = 20`.
  - `User::organizacoes(): Builder` → `Organizacao::daPessoa($this)`.

- [ ] **Step 1: Testes que falham** — `test_cnpj_e_gravado_normalizado` (cria com `12.abc.345/01de-35`, lê `12ABC34501DE35`); `test_da_pessoa_traz_so_organizacoes_com_vinculo_do_cpf` (duas organizações, vínculo do CPF do usuário só em uma); `test_vinculo_vale_antes_do_usuario_existir` (vínculo com CPF `52998224725`, depois cria o `User` com esse CPF → `organizacoes()->count() === 1`); `test_eh_responsavel_so_para_o_vinculo_marcado`; `test_cnpj_unico` (segunda inserção lança `UniqueConstraintViolationException`); `test_representantes_vem_na_ordem`.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Migrations, enums, models e factories** (factory de `Organizacao` gera CNPJ numérico válido calculando o DV com `Cnpj`; state `comResponsavel(User $user)`).
- [ ] **Step 4:** `php artisan migrate` no container; rodar o teste — PASS.
- [ ] **Step 5: Commit** `feat: tabelas de organizações, representantes legais e vínculos`

---

### Task 3: Consultas de CNPJ, CEP e municípios

**Files:**
- Create: `app/Support/ConsultaCnpj.php`, `app/Support/ConsultaCep.php`, `app/Http/Controllers/ConsultaCadastroController.php`
- Modify: `routes/web.php`, `app/Providers/AppServiceProvider.php` (limitador `consultas`)
- Test: `tests/Feature/ConsultaCadastroTest.php`

**Interfaces:**
- Consumes: `Cnpj`, `NaturezasJuridicas`, `Municipios`, `Organizacao`.
- Produces:
  - `ConsultaCnpj::buscar(string $cnpj): ?array` — `GET https://brasilapi.com.br/api/cnpj/v1/{cnpj}`, `timeout(5)`, cache `consulta-cnpj:{cnpj}` por 1 dia (só respostas encontradas). Retorna `null` em 404, erro, timeout ou JSON inválido. Mapeia para: `razao_social`, `nome_fantasia`, `natureza_juridica` (`codigo_natureza_juridica` como string de 4 dígitos, só se existir em `NaturezasJuridicas::TODAS`), `finalidade_lucrativa` (sugestão pela natureza), `mei` (`opcao_pelo_mei === true`), `porte` (`MICRO EMPRESA`→`micro`, `EMPRESA DE PEQUENO PORTE`→`pequeno`, outro→`null`), `data_fundacao` (`data_inicio_atividade`, `Y-m-d`), `telefone` (só dígitos de `ddd_telefone_1`, ou `null`), `cep`, `logradouro` (`descricao_tipo_de_logradouro` + `logradouro`), `numero` (`null` e `sem_numero: true` quando vier `S/N`), `complemento`, `bairro`, `cidade`, `uf`, `municipio` (`{codigo, nome, uf}` achado por `Municipios::buscar` com nome+UF da sede, ou `null`). Strings vazias viram `null`.
  - `ConsultaCep::buscar(string $cep): ?array` — `GET https://viacep.com.br/ws/{cep}/json/`, `timeout(3)`; `null` em erro ou `{"erro": true}`. Retorna `logradouro`, `bairro`, `cidade`, `uf`.
  - Rotas (grupo `auth` + `nao-gestor` + `throttle:consultas`), nomes `consultas.cnpj`, `consultas.cep`, `consultas.municipios`:
    - `GET /cadastro/consultas/cnpj/{cnpj}` → CNPJ inválido: 422 `{"message": "CNPJ inválido."}`; já cadastrado: `{"jaCadastrado": true}` **sem chamar a BrasilAPI**; não encontrado: `{"encontrado": false}`; encontrado: `{"encontrado": true, "dados": {...}}`.
    - `GET /cadastro/consultas/cep/{cep}` → `{"encontrado": false}` ou `{"encontrado": true, "dados": {...}}`; CEP sem 8 dígitos: 422.
    - `GET /cadastro/consultas/municipios?busca=` → lista de `Municipios::buscar` (busca com menos de 2 caracteres → `[]`).
  - Limitador `consultas`: 30/min por `user()->id`.

- [ ] **Step 1: Testes que falham** (com `Http::fake` e `Http::preventStrayRequests()`): mapeamento completo de uma resposta da BrasilAPI (fixture inline com `codigo_natureza_juridica: 2062`, `porte: "MICRO EMPRESA"`, `opcao_pelo_mei: true`, município Salvador/BA); `S/N` vira `sem_numero`; BrasilAPI 404 → `encontrado: false`; BrasilAPI 500 e `ConnectionException` → `encontrado: false`; CNPJ já cadastrado → `jaCadastrado: true` e `Http::assertNothingSent()`; CNPJ inválido → 422; segunda consulta do mesmo CNPJ não chama a API (cache); ViaCEP ok, `{"erro": true}` e falha de conexão; municípios `busca=salv` contém Salvador; sem login → redireciona; usuário da retaguarda → redireciona (como `/cadastro`).
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4: Rodar** `php artisan test --compact tests/Feature/ConsultaCadastroTest.php` — PASS.
- [ ] **Step 5: Commit** `feat: consulta de CNPJ, CEP e municípios para o cadastro`

---

### Task 4: Etapa da organização depois da pessoa física

**Files:**
- Create: `app/Http/Middleware/ExigirCadastroCompleto.php` (alias `cadastro-completo` em `bootstrap/app.php`), `resources/js/Pages/Cadastro/Organizacao.vue`
- Modify: `app/Http/Controllers/CadastroController.php`, `routes/web.php`, `resources/js/Components/EtapasCadastro.vue`, `resources/js/Pages/Cadastro/Completar.vue` (só o `:atual` das etapas, se mudar), `resources/js/types/index.ts`
- Delete: `resources/js/Pages/Cadastro/Formulario.vue`, `resources/js/Components/IconeTipoCadastro.vue` (se não tiver outro uso), rota `cadastro.pessoa-fisica` e o método `pessoaFisica`
- Test: `tests/Feature/CadastroTest.php`

**Interfaces:**
- Produces:
  - Middleware `cadastro-completo`: sem `profile_completed_at` → `redirect()->route('cadastro')` com `aviso` = `Complete seus dados de pessoa física antes de cadastrar uma organização.`
  - `GET /cadastro/organizacao` (nome `cadastro.organizacao`, `auth`+`nao-gestor`+`cadastro-completo`) → `Cadastro/Organizacao`, sem props próprias. A página tem duas ações: **Cadastrar organização** (link para `/cadastro/pessoa-juridica`; até a Task 5 essa rota ainda mostra o placeholder, depois vira `organizacoes.create`) e **Não represento uma organização** (link para `/` — a home mostra o flash de sucesso que veio do salvar).
  - `CadastroController::salvar`: se `profile_completed_at` era `null` antes de salvar → `redirect()->route('cadastro.organizacao')->with('sucesso', 'Dados salvos. Você também pode cadastrar uma organização.')`; senão, o redirect atual para `home`.
  - `EtapasCadastro`: `['Conta gov.br', 'Seus dados', 'Organização (opcional)', 'Inscrição nos editais']`, `sm:grid-cols-2 lg:grid-cols-4`; `Completar` usa `atual=2`, `Organizacao` e o formulário da Task 6 usam `atual=3`.

- [ ] **Step 1: Testes que falham** — `test_primeiro_salvamento_segue_para_a_etapa_da_organizacao`; `test_salvamentos_seguintes_voltam_para_a_home`; `test_etapa_da_organizacao_exige_pessoa_fisica_completa`; `test_etapa_da_organizacao_bloqueia_usuario_da_retaguarda`; `test_rota_antiga_de_pessoa_fisica_nao_existe` (`/cadastro/pessoa-fisica` → 404). Ajustar testes existentes que esperam o redirect para `home` no primeiro salvamento.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar backend e a página** `Cadastro/Organizacao.vue` (FaixaPortal com `Etapa 3 de 4 · Organização`, título "Você representa uma organização?", texto "Associações, produtoras, MEIs e empresas também podem se inscrever nos editais. Você pode cadastrar agora ou depois, em Minhas organizações.", os dois botões).
- [ ] **Step 4: Rodar** `php artisan test --compact tests/Feature/CadastroTest.php` — PASS; `npm run build` — OK.
- [ ] **Step 5: Commit** `feat: etapa opcional da organização depois da pessoa física`

---

### Task 5: Salvar organização (backend)

**Files:**
- Create: `app/Http/Requests/SalvarOrganizacaoRequest.php`, `app/Http/Controllers/OrganizacaoController.php`, `app/Policies/OrganizacaoPolicy.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/OrganizacaoTest.php`

**Interfaces:**
- Consumes: Tasks 1–2; `App\Support\ArquivoArmazenado` (guardar/salvarTrocando) para a logo.
- Produces:
  - Rotas (`auth`+`nao-gestor`+`cadastro-completo`): `GET /cadastro/pessoa-juridica` `organizacoes.create`; `POST /organizacoes` `organizacoes.store`; `GET /organizacoes/{organizacao}/editar` `organizacoes.edit`; `PUT /organizacoes/{organizacao}` `organizacoes.update` (multipart via `_method`); `GET /organizacoes` `organizacoes.index` (Task 7).
  - `OrganizacaoPolicy`: `update(User, Organizacao)` = `temVinculo`; `gerenciarVinculos(User, Organizacao)` = `ehResponsavel`. Não vinculado → **403**.
  - Validação (`SalvarOrganizacaoRequest`):
    - `cnpj`: só no `store`; obrigatório, `Cnpj::valido`, único em `organizacoes.cnpj` (normalizado). Mensagens: `CNPJ inválido. Confira os números e letras.` e `Esta organização já está cadastrada. Peça ao responsável por ela para vincular você.` No `update` o CNPJ enviado é ignorado.
    - `razao_social` obrigatório, máx 255; `nome_fantasia` máx 255; `natureza_juridica` obrigatório, `in` das chaves de `NaturezasJuridicas::TODAS`; `mei` boolean; `finalidade_lucrativa` nullable boolean; `municipio_ibge` obrigatório e `Municipios::existe`; `inscricao_municipal`/`inscricao_estadual` máx 30; `porte` enum nullable; `data_fundacao` data `before_or_equal:today`; `telefone` `digits_between:10,11`; `logo` `image`, `mimes:jpg,jpeg,png,webp`, `max:5120`.
    - Endereço todo opcional: `cep` `digits:8`, `logradouro` 255, `numero` 20, `sem_numero` boolean, `complemento` 120, `bairro` 120, `cidade` 120, `uf` `in` de `Estados::TODOS`.
    - `representantes`: array obrigatório, `min:1`, `max:10` (mensagem `Informe pelo menos um representante legal.`); cada item: `cpf` obrigatório, `Cpf::valido`, `distinct` (`Este CPF já está em outro representante.`); `nome` obrigatório 255; `tipo_documento`, `origem` enums nullable; `numero_documento` 30; `cargo` 120; `telefone` `digits_between:10,11`; `email` email 255; `origem_descricao` obrigatório se `origem = outro`, máx 255.
    - `prepareForValidation`: tira máscara de `cnpj`, `cep`, `telefone`, dos CPFs e telefones dos representantes.
  - `store`: numa transação, cria a organização, os representantes (`ordem` = posição) e o vínculo do usuário com `responsavel = true` e `nome = user->name`; logo em `organizacoes/{id}` no disco `public`. Redireciona para `organizacoes.index` com `sucesso` = `Organização salva.`
  - `update`: atualiza os campos, substitui os representantes (apaga e recria na transação), troca a logo com `salvarTrocando`; mesmo redirect.
  - Com `sem_numero` true, `numero` é gravado `null`.
  - `create`/`edit` renderizam `Organizacoes/Form` com props: `organizacao` (null no create; no edit, campos + `cnpj` formatado + `logoUrl` + `municipio` `{codigo,nome,uf}` + `representantes`), `naturezas` (`list<{valor, rotulo}>` com rótulo `"2062 · Sociedade Empresária Limitada"`), `opcoes` (`portes`, `tiposDocumento`, `origens`, `estados`), `representanteInicial` (`{cpf: formatado, nome}` do usuário; só no create).

- [ ] **Step 1: Testes que falham** — store válido cria organização, representantes e vínculo responsável; CNPJ alfanumérico em minúsculas é aceito e normalizado; CNPJ duplicado dá a mensagem exata e não cria nada; CNPJ inválido; sem representante → mensagem exata; CPF de representante repetido; `origem = outro` sem descrição; `sem_numero` zera `numero`; campos opcionais vazios gravam `null`; logo gravada no disco `public` (`Storage::fake('public')`) e trocada no update apaga a antiga; update por vinculado não responsável funciona; update por não vinculado → 403; edit por não vinculado → 403; update ignora `cnpj` enviado; `create` sem pessoa física completa → redireciona para `/cadastro`; usuário da retaguarda → redireciona.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** (com uma página Vue mínima `Organizacoes/Form.vue` só com `<div />` se preciso para o `assertInertia`; a tela real é a Task 6).
- [ ] **Step 4: Rodar** `php artisan test --compact tests/Feature/OrganizacaoTest.php` — PASS.
- [ ] **Step 5: Commit** `feat: cadastro e edição de organização com representantes legais`

---

### Task 6: Formulário da organização (tela)

**Files:**
- Create/Modify: `resources/js/Pages/Organizacoes/Form.vue`, componentes novos em `resources/js/Components/Organizacao/` se o arquivo passar de ~400 linhas (sugestão: `CampoBusca.vue` para natureza jurídica e município, `CartaoRepresentante.vue`)
- Modify: `resources/js/types/index.ts`

**Interfaces:**
- Consumes: props da Task 5; rotas JSON da Task 3.

Comportamento (textos exatos do design, seção 5):
- FaixaPortal: `Etapa 3 de 4 · Organização` e `EtapasCadastro :atual="3"` só no create; no edit, título "Editar organização" sem etapas, com link "Voltar para Minhas organizações".
- **Bloco 1 — Dados institucionais** (`SecaoCadastro` 1). CNPJ com máscara que aceita letras nas 12 primeiras posições (maiúsculas ao digitar) e ajuda `Aceita o CNPJ numérico e o alfanumérico (IN RFB 2.229/2024).`; no edit, só leitura. Ao sair do campo com CNPJ válido de 14 caracteres: chama `/cadastro/consultas/cnpj/{cnpj}`, mostra "Buscando os dados públicos do CNPJ…" (`aria-live="polite"`); `jaCadastrado` → erro no campo com a mensagem de duplicado; `encontrado` → preenche os campos **vazios ou ainda não editados** e mostra `Dados preenchidos com as informações públicas da Receita Federal. Confira antes de salvar.`; não encontrado/erro → nada, segue manual.
- Natureza jurídica: lista com busca por código ou descrição; ao escolher, sugere `finalidade_lucrativa` (Sim/Não, select com "Selecione…") com a ajuda `Sugerida pela natureza jurídica. Confira e ajuste se necessário.`
- MEI: caixa com a ajuda do design. Município: busca em `/cadastro/consultas/municipios` a partir de 2 letras, mostra `Nome – UF`. Porte: select. Inscrições com as máscaras `000000-0` e `00.000.00-0` (só ajudam a digitar). Data de fundação `type="date"` com `max` = hoje. Telefone: mesma máscara do `Completar.vue`. Logo: `CampoArquivo` (jpg, png, webp, 5 MB) com prévia da atual no edit.
- **Bloco 2 — Endereço da sede**: CEP com máscara; ao completar 8 dígitos chama `/cadastro/consultas/cep/{cep}` e preenche logradouro, bairro, cidade, UF (todos editáveis); "Sem número" desabilita e limpa o número.
- **Bloco 3 — Representantes legais**: texto do design; cartões `Representante N de M` com os campos da seção 5.3; o primeiro vem com `representanteInicial` no create; botões "Adicionar representante" (até 10) e "Remover" (desabilitado quando há só 1); `origem = outro` mostra "Descreva a origem". Ao adicionar, foco no CPF do novo cartão; ao remover, foco no cartão anterior.
- Envio com `useForm` + `forceFormData`; erros embaixo de cada campo (chaves `representantes.N.campo`); botão "Salvar organização" com estado de envio.
- Acessibilidade: rótulos ligados, `aria-describedby` via `CampoCadastro`, listas com busca como combobox ARIA (`role="combobox"`, `aria-expanded`, `aria-activedescendant`, setas/Enter/Esc).

- [ ] **Step 1: Implementar a página e os componentes.**
- [ ] **Step 2:** `npm run build` — OK (sem erros do `vue-tsc`).
- [ ] **Step 3: Conferir no navegador** (`http://127.0.0.1:8000`, login simulado do gov.br): cadastrar com CNPJ numérico real (consulta preenche), com CNPJ alfanumérico `12.ABC.345/01DE-35` (consulta não acha, segue manual), adicionar/remover representantes, erro de validação, modo noturno e largura de 375 px.
- [ ] **Step 4: Rodar** `php artisan test --compact tests/Feature/OrganizacaoTest.php` — PASS.
- [ ] **Step 5: Commit** `feat: formulário da organização com dados públicos do CNPJ e CEP`

---

### Task 7: Minhas organizações e vínculos

**Files:**
- Create: `app/Http/Controllers/VinculoOrganizacaoController.php`, `resources/js/Pages/Organizacoes/Index.vue`, `resources/js/Pages/Organizacoes/Vinculos.vue`
- Modify: `app/Http/Controllers/OrganizacaoController.php` (`index`), `routes/web.php`, `resources/js/Layouts/PortalProponenteLayout.vue`, `resources/js/Components/RodapeHome.vue` e `resources/js/Pages/Home.vue` (link "Minhas organizações" ao lado de "Meu cadastro" para quem não é da retaguarda), `resources/js/types/index.ts`
- Test: `tests/Feature/VinculoOrganizacaoTest.php`, `tests/Feature/OrganizacaoTest.php`

**Interfaces:**
- Consumes: `Organizacao`, `OrganizacaoPolicy`, `Cpf`.
- Produces:
  - `GET /organizacoes` (`organizacoes.index`) → `Organizacoes/Index` com `organizacoes: list<{id, nome (fantasia ou razão social), cnpj formatado, logoUrl, responsavel: bool}>` ordenada por nome. Estado vazio: "Você ainda não está vinculado a nenhuma organização." + "Cadastrar organização".
  - `GET /organizacoes/{organizacao}/pessoas` (`organizacoes.vinculos`, policy `update`) → `Organizacoes/Vinculos` com `organizacao {id, nome}`, `podeGerenciar: bool`, `vinculos: list<{id, nome, cpf mascarado com Cpf::mascarar, responsavel, ehVoce}>`. O nome mostrado é o do `User` com aquele CPF quando existir, senão o nome do vínculo.
  - `POST /organizacoes/{organizacao}/pessoas` (`organizacoes.vinculos.store`, policy `gerenciarVinculos`): `cpf` obrigatório + `Cpf::valido` + não vinculado ainda (`Esta pessoa já está vinculada.`), `nome` obrigatório 255; limite 20 (`Limite de 20 pessoas vinculadas.`); `sucesso` = `Pessoa vinculada.`
  - `DELETE /organizacoes/{organizacao}/pessoas/{vinculo}` (`organizacoes.vinculos.destroy`): responsável remove qualquer vínculo menos o próprio; vinculado não responsável só remove o próprio ("Sair da organização" → redireciona para `organizacoes.index` com `Você saiu da organização.`); responsável nunca é removido (403). Vínculo de outra organização → 404 (`scopeBindings`). `sucesso` = `Pessoa desvinculada.`
  - Topo do `PortalProponenteLayout`: navegação com "Meu cadastro" (`/cadastro`) e "Minhas organizações" (`/organizacoes`), com `aria-current`, cabendo em 375 px.

- [ ] **Step 1: Testes que falham** — index lista só as organizações vinculadas, com `responsavel` certo; vinculado antes do primeiro login vê a organização depois de completar a pessoa física; responsável vincula CPF válido; CPF inválido e repetido; limite de 20; não responsável não vincula (403); não vinculado não vê a página de pessoas (403); responsável desvincula outro; responsável não se desvincula (403); vinculado sai da organização; vinculado não remove outro (403); vínculo de outra organização → 404.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar backend, páginas e navegação** (confirmação antes de desvincular/sair, como na retaguarda).
- [ ] **Step 4: Rodar** `php artisan test --compact tests/Feature/VinculoOrganizacaoTest.php tests/Feature/OrganizacaoTest.php` — PASS; `npm run build` — OK; conferir no navegador em 375 px e no modo noturno.
- [ ] **Step 5: Commit** `feat: Minhas organizações e vínculo de pessoas por CPF`

---

### Task 8: Fechamento

- [ ] **Step 1:** `docker compose exec app php artisan test --compact` — todos passando.
- [ ] **Step 2:** `npm run build` — OK.
- [ ] **Step 3:** `vendor/bin/pint --dirty --format agent` sem alterações pendentes.
- [ ] **Step 4:** README: na seção do cadastro, uma linha sobre Minhas organizações e sobre as consultas externas (BrasilAPI e ViaCEP, que o servidor precisa alcançar; sem elas, preenchimento manual). Commit `docs: cadastro de organizações no README`.
