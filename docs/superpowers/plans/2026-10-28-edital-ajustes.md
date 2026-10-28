# Ajustes no edital: dados gerais e categorias · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O edital ganha tipo de fomento, vigência, Divulgação em texto rico (no lugar do resumo) e categorias de inscrição com valor, vagas, naturezas e cotas.

**Architecture:** Colunas novas em `editais` e uma tabela `edital_categorias` (filha do edital, sincronizada por id ao salvar o edital). A Divulgação segue o padrão da descrição do evento: mutator com `HtmlSeguro::limpar` e `EditorTextoRico` no formulário. O formulário do edital já está em abas laterais (`AbasFormulario`); entra a aba Categorias.

**Tech Stack:** Laravel 13, Inertia v2, Vue 3 + TypeScript strict, Tailwind v4, Oracle 23ai (`yajra/laravel-oci8`), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-10-28-edital-ajustes-design.md`

## Global Constraints

- Seguir `CLAUDE.md` (Laravel Boost): arquivos via `php artisan make:* --no-interaction`; `vendor/bin/pint --dirty --format agent` antes de cada commit; `php artisan test --compact` no escopo da tarefa; **sem dependências novas**.
- Comandos PHP rodam **no container** (`docker compose exec -T app php artisan ...`); o PHP do host não tem oci8. Front: `npm run build` no host (roda `vue-tsc`).
- Oracle: `''` vira `NULL`; dias com `App\Models\Concerns\UsaDiaCivil`; texto longo (CLOB) fora das listagens; nada de `migrate:fresh` no banco de dev (só `migrate`).
- Frontend em TypeScript strict; tipos em `resources/js/types/index.ts`; estilo dos arquivos vizinhos (aspas simples, 4 espaços, linhas longas — **não rodar Prettier**).
- Textos em português do Brasil; mensagens de validação no padrão dos requests da retaguarda (`'Este campo é obrigatório.'`).
- Commits só com os arquivos da própria tarefa (nunca `git add -A`), mensagem em português terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Não fazer push.
- Identidade visual atual (tokens de `resources/css/app.css`, modo noturno, alto contraste); sem preto.

## Review Focus

1. **Editais já cadastrados** depois da migration: abrem na retaguarda e no portal sem erro, com a Divulgação = resumo antigo em `<p>` (escapado) e a vigência = início das inscrições; ao salvar, o tipo de fomento é exigido. Teste na Task 1.
2. **Divulgação maliciosa ou vazia:** `<script>`, `onerror`, `javascript:` removidos; `<p></p>` ou só espaços recusado como vazio. Teste na Task 1.
3. **Categorias editadas e salvas de novo:** ids preservados (as inscrições do projeto 3 vão apontar para eles), removidas apagadas, ordem respeitada; categoria de outro edital no payload ignorada. Teste na Task 3.
4. **Cotas acima do total de vagas** (somando vagas e percentuais arredondados para cima) recusadas com mensagem na categoria certa. Teste na Task 3.
5. **Erros dentro da aba Categorias** (`categorias.N.campo`) contam no marcador da aba e levam o foco ao campo. Conferência na Task 4.

---

### Task 1: Dados gerais — banco, modelo, validação e portal

**Files:**
- Create: migration `add_dados_gerais_to_editais_table`; `app/Enums/TipoFomento.php`
- Modify: `app/Models/Edital.php`, `app/Http/Requests/Retaguarda/SalvarEditalRequest.php`, `app/Http/Controllers/Retaguarda/EditalController.php` (props do edit), `app/Http/Controllers/EditalController.php` (show), `database/factories/EditalFactory.php`, `database/seeders/EditalSeeder.php`
- Test: `tests/Feature/Retaguarda/EditaisTest.php`, `tests/Feature/PortalTest.php`, novo `tests/Feature/EditalMigracaoDivulgacaoTest.php` se preciso

**Interfaces:**
- Produces:
  - Colunas em `editais`: `tipo_fomento` (string 10, nullable), `vigencia_inicio` (date, nullable no banco), `vigencia_fim` (date, nullable), `divulgacao` (longText/CLOB, nullable no banco). A coluna `resumo` é removida.
  - Migration `up`: adiciona as colunas; para cada edital existente (inclusive excluídos), `divulgacao = '<p>'.e(resumo).'</p>'` e `vigencia_inicio = inscricoes_inicio`; depois remove `resumo`. `down`: recria `resumo` (text, nullable) com `strip_tags(divulgacao)` e remove as novas.
  - `App\Enums\TipoFomento: string` — `Direto = 'direto'` (rótulo "Direto"), `Indireto = 'indireto'` (rótulo "Indireto"), método `rotulo(): string` como `App\Enums\IdentidadeGenero`.
  - `Edital`: fillable com `tipo_fomento`, `vigencia_inicio`, `vigencia_fim`, `divulgacao` (sem `resumo`); casts `tipo_fomento` → `TipoFomento`, vigências → `date`; mutators das vigências com `diaParaGravar`; mutator de `divulgacao` com `HtmlSeguro::limpar` (como `Evento::descricao`).
  - `SalvarEditalRequest`: `tipo_fomento` required + `Rule::enum(TipoFomento::class)`; `vigencia_inicio` required date; `vigencia_fim` nullable date `after_or_equal:vigencia_inicio`; `divulgacao` required string max:200000, e no `after()` recusa quando `trim(html_entity_decode(strip_tags(HtmlSeguro::limpar($valor))))` é vazio (mensagem `Este campo é obrigatório.` na chave `divulgacao`). Mensagens: `vigencia_fim.after_or_equal` = `O fim da vigência não pode ser antes do início.`; `tipo_fomento` inválido = `Selecione uma opção da lista.` `dadosDoEdital()` passa a devolver os campos novos em vez de `resumo`.
  - Retaguarda `edit` envia `tipo_fomento` (string ou null), `vigencia_inicio`/`vigencia_fim` (`Y-m-d` ou null), `divulgacao`; e a lista `tiposFomento: list<{valor, rotulo}>` em `create` e `edit`. A listagem (`index`) continua sem carregar a coluna CLOB.
  - Portal `EditalController@show`: troca `resumo` por `divulgacao` (HTML limpo).
  - Factory: `tipo_fomento` aleatório, `vigencia_inicio` = `inscricoes_inicio`, `divulgacao` = `<p>` + frase. Seeder: os textos atuais viram `divulgacao` em `<p>`, `tipo_fomento` Direto, vigência = início das inscrições; mantém a regra de só preencher registros novos.

- [ ] **Step 1: Testes que falham** — salvar com os campos novos grava tudo; `tipo_fomento` ausente/inválido; `vigencia_fim` antes do início recusado com a mensagem exata; `vigencia_fim` vazio aceito (null); Divulgação com `<script>alert(1)</script><p onclick="x">Oi</p>` grava `<p>Oi</p>`; Divulgação `<p></p>` e `<p> </p>` recusadas; edit envia os campos novos e `tiposFomento`; página pública do edital envia `divulgacao` e não envia `resumo`; teste da migração: criar um edital com `resumo` via `DB::table` numa tabela no estado antigo é complicado — em vez disso, testar a função de conversão (se extraída) ou rodar a migration `up` sobre um registro inserido antes dela com `Artisan::call('migrate:rollback', ['--step' => 1])` + insert + `migrate` (escolher o que for viável e estável no Oracle; registrar no relatório).
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar** (migration via `make:migration`, enum, modelo, request, controllers, factory, seeder; ajustar testes existentes que usam `resumo` de edital).
- [ ] **Step 4:** `php artisan migrate` no container; rodar `tests/Feature/Retaguarda/EditaisTest.php`, `tests/Feature/PortalTest.php` e o teste novo — PASS.
- [ ] **Step 5: Commit** `feat: tipo de fomento, vigência e divulgação em texto rico no edital`

---

### Task 2: Dados gerais — telas

**Files:**
- Modify: `resources/js/Pages/Retaguarda/Editais/Form.vue`, `resources/js/Pages/Editais/Show.vue`, `resources/js/types/index.ts`

**Interfaces:**
- Consumes: props da Task 1 (`tipo_fomento`, `vigencia_inicio`, `vigencia_fim`, `divulgacao`, `tiposFomento`; portal `edital.divulgacao`).

Comportamento (textos exatos da spec, seção 5):
- Aba **Dados gerais**, nesta ordem: **Título do edital** (rótulo novo), Categoria, **Tipo de fomento** (`select` com "Selecione…" + opções de `tiposFomento`), **Início da vigência** (date, ajuda `Validade administrativa, não a janela de inscrição.`), **Fim da vigência** (date, `min` = início, ajuda `Opcional. Sem data, o edital fica vigente por prazo indeterminado.`), **Divulgação** (`EditorTextoRico` carregado com `defineAsyncComponent`, como em `Retaguarda/Eventos/Form.vue`, rótulo "Divulgação", ajuda "Texto informativo do edital, exibido na página dele no portal.").
- `abas[0].campos` passa a ser `['titulo', 'categoria', 'tipo_fomento', 'vigencia_inicio', 'vigencia_fim', 'divulgacao']`.
- Portal `Editais/Show.vue`: troca o parágrafo do resumo por `<div class="conteudo-rico ..." v-html="edital.divulgacao">` como em `Eventos/Show.vue` (o HTML já vem limpo do servidor).
- Tipos: `EditalDetalhe` troca `resumo` por `divulgacao: string`; o tipo do formulário da retaguarda ganha os campos novos e `tiposFomento`.

- [ ] **Step 1: Implementar.**
- [ ] **Step 2:** `npm run build` — OK.
- [ ] **Step 3: Conferir no navegador** (`http://127.0.0.1:8000/retaguarda/editais/create` logado como `admin@admin` / `123456`, servidor lento: 3–13 s por página): campos na ordem, editor rico funcionando, erro de vigência, salvar um edital de teste e ver a Divulgação formatada em `/editais/{slug}`; modo noturno. Apagar o edital de teste depois (exclusão lógica pela própria tela).
- [ ] **Step 4: Commit** `feat: dados gerais do edital com vigência, tipo de fomento e divulgação`

---

### Task 3: Categorias — banco, modelo e validação

**Files:**
- Create: migration `create_edital_categorias_table`; `app/Models/EditalCategoria.php` (+ factory); `app/Enums/NaturezaProponente.php`, `app/Enums/UnidadeCota.php`
- Modify: `app/Models/Edital.php`, `app/Http/Requests/Retaguarda/SalvarEditalRequest.php`, `app/Http/Controllers/Retaguarda/EditalController.php`
- Test: `tests/Feature/Retaguarda/EditalCategoriasTest.php`

**Interfaces:**
- Produces:
  - `edital_categorias`: `id`, `edital_id` (FK cascade), `ordem` (int), `nome` (string 255), `valor_centavos` (bigInteger), `vagas` (integer), `naturezas` (string 200 — JSON com valores de `NaturezaProponente`, como `users.tipos_cadastro`), e para cada cota (`negros`, `indigenas`, `pcd`): `cota_{x}_valor` (integer, nullable) e `cota_{x}_unidade` (string 10, nullable); timestamps.
  - `NaturezaProponente: string` — `PessoaFisica = 'pessoa_fisica'` "Pessoa Física", `Mei = 'mei'` "Pessoa Jurídica – MEI", `SemFinsLucrativos = 'sem_fins'` "Pessoa Jurídica sem fins lucrativos", `ComFinsLucrativos = 'com_fins'` "Pessoa Jurídica com fins lucrativos"; `rotulo()`.
  - `UnidadeCota: string` — `Vagas = 'vagas'` "vagas", `Percentual = 'percentual'` "%".
  - `EditalCategoria`: casts (`naturezas` → `AsEnumCollection::of(NaturezaProponente::class)` ou o cast já usado em `User::tipos_cadastro` — seguir o mesmo), `COTAS = ['negros', 'indigenas', 'pcd']`, `vagasReservadas(string $cota): int` (vagas → o valor; percentual → `(int) ceil($vagas * $valor / 100)`; sem cota → 0), `totalReservado(): int`.
  - `Edital::categorias(): HasMany` ordenado por `ordem`.
  - Request: `categorias` nullable array max:30; cada item: `id` nullable integer; `nome` required max:255; `valor` required numeric min:0.01 max:999999999 (reais, com centavos; o controller converte para centavos com `round($valor * 100)`); `vagas` required integer min:1 max:10000; `naturezas` required array min:1, `naturezas.*` `Rule::enum(NaturezaProponente::class)` distinct; para cada cota `cotas.{x}.valor` nullable integer min:1 (percentual max:100) e `cotas.{x}.unidade` required_with valor + `Rule::enum(UnidadeCota::class)`. No `after()`: soma das vagas reservadas (com o arredondamento para cima) > `vagas` → erro em `categorias.N.cotas` = `As cotas somam mais vagas do que a categoria tem.` Mensagens: `categorias.*.naturezas.required` = `Marque ao menos uma natureza.`
  - Controller `store`/`update`: numa transação com o salvamento do edital, sincroniza por id — atualiza as que vêm com `id` **desta** categoria do edital (ids de outro edital são ignorados e tratados como novas), cria as sem `id`, apaga as que não vieram; `ordem` = posição no array (a partir de 1).
  - `edit` envia `categorias: list<{id, nome, valor (reais, number), vagas, naturezas: string[], cotas: {negros|indigenas|pcd: {valor: number|null, unidade: 'vagas'|'percentual'|null}}}>`; `create`/`edit` enviam `naturezas: list<{valor, rotulo}>`.

- [ ] **Step 1: Testes que falham** — criar edital com 2 categorias (valores, vagas, naturezas, cotas em vagas e em percentual) e conferir no banco (`valor_centavos`, `ordem`); `vagasReservadas` com percentual arredonda para cima (30% de 5 = 2); editar: muda uma, remove outra, adiciona nova → id da editada preservado, removida apagada, ordem nova; id de categoria de outro edital no payload não altera a do outro edital; sem natureza → mensagem exata; vagas 0 recusado; percentual 101 recusado; cotas somando mais que as vagas → mensagem exata em `categorias.0.cotas`; edit envia `categorias` no formato acima; edital sem categorias continua salvando e publicando.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4:** `php artisan migrate`; rodar `tests/Feature/Retaguarda/EditalCategoriasTest.php` e `tests/Feature/Retaguarda/EditaisTest.php` — PASS.
- [ ] **Step 5: Commit** `feat: categorias de inscrição do edital`

---

### Task 4: Categorias — tela

**Files:**
- Create: `resources/js/Components/Retaguarda/CategoriasEdital.vue` (se o `Form.vue` passar de ~450 linhas, também `CartaoCategoria.vue`)
- Modify: `resources/js/Pages/Retaguarda/Editais/Form.vue`, `resources/js/Components/Retaguarda/AbasFormulario.vue`, `resources/js/types/index.ts`

**Interfaces:**
- Consumes: props e chaves de erro da Task 3.

Comportamento:
- Nova aba **Categorias** entre "Inscrições" e "Arquivos", título da seção "Categorias de inscrição", texto: "As opções que o proponente escolhe ao se inscrever. Cada categoria tem valor, vagas, quem pode concorrer e reserva de cotas."
- Lista de cartões "Categoria N": Nome; Valor por proposta (máscara de reais `R$ 150.000,00`, envia número); Vagas; Naturezas (caixas de marcação com os rótulos de `naturezas`); **Reserva de cotas** com três linhas (Pessoas negras, Pessoas indígenas, Pessoas com deficiência), cada uma com valor numérico + seletor "vagas"/"%"; ao lado de cada cota em percentual, "= N vagas" (arredondado para cima, mesma conta do servidor); abaixo, "Total reservado: N de M vagas", em vermelho quando passar.
- Botões: "Adicionar categoria" (foco no nome da nova), "Remover" (confirmação, foco no cartão anterior ou no botão adicionar), "Subir"/"Descer" para reordenar (foco acompanha o cartão). Estado vazio: "Nenhuma categoria cadastrada. O edital pode ser publicado sem categorias; a inscrição pelo portal vai exigir ao menos uma."
- Chaves estáveis para os cartões (não o índice) e erros `categorias.N.*` mapeados para o cartão certo; ao remover, limpar os erros de `categorias.*`.
- **`AbasFormulario`:** um item de `campos` terminado em `.*` (ex.: `'categorias.*'`) conta todos os erros cujo nome começa com o prefixo; a aba Categorias usa `campos: ['categorias', 'categorias.*']`. `mostrarPrimeiroErro` continua funcionando (o primeiro `[aria-invalid="true"]` do painel).
- Envio: `categorias` vai no mesmo `useForm` (com `forceFormData` os arrays aninhados já viram `categorias[0][nome]` etc.; conferir que `naturezas` vazio e cotas nulas chegam certos ao servidor — se o FormData perder arrays vazios, enviar `naturezas` como lista e tratar a ausência como vazio no request).

- [ ] **Step 1: Implementar.**
- [ ] **Step 2:** `npm run build` — OK.
- [ ] **Step 3: Conferir no navegador:** criar edital de teste com 2 categorias (uma com cota em %, outra em vagas), salvar, reabrir e ver tudo preservado; reordenar; cotas acima do total → marcador na aba Categorias e foco no campo; 375 px e modo noturno. Apagar o edital de teste depois.
- [ ] **Step 4:** rodar `tests/Feature/Retaguarda/EditalCategoriasTest.php` — PASS.
- [ ] **Step 5: Commit** `feat: aba de categorias no formulário do edital`

---

### Task 5: Fechamento

- [ ] **Step 1:** `docker compose exec -T app php artisan test --compact` — todos passando.
- [ ] **Step 2:** `npm run build` — OK; `vendor/bin/pint --dirty --format agent` sem pendências.
