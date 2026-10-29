# Importação do edital usando a Integração com IA · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Juntar a importação do edital por IA (branch local `feat/edital-importacao-ia`) à `master` atual, usando a Integração com IA do Daniel para provedor, modelo e chave, com OpenAI e Gemini, redundância e a seção "3. Importação de editais" na tela dele.

**Architecture:** Merge da branch da importação na branch `feat/edital-importacao-ia-integrada` (que parte da `master`), mantendo a configuração do Daniel e retirando a nossa. A análise passa por uma interface de cliente por provedor (Gemini e OpenAI) chamada por um orquestrador que tenta o principal e depois a redundância. Os parâmetros da importação ficam em colunas novas da `configuracoes_ia` do Daniel.

**Tech Stack:** Laravel 13, Inertia v2, Vue 3 + TypeScript strict, Tailwind v4, Oracle (`yajra/laravel-oci8`), PHPUnit. APIs REST do Gemini (`generativelanguage.googleapis.com`) e da OpenAI (`api.openai.com/v1/responses`).

**Spec:** `docs/superpowers/specs/2026-10-29-importacao-edital-integracao-ia-design.md` (base: `2026-10-28-edital-importacao-ia-design.md`, que entra na branch pelo merge da Task 1).

## Global Constraints

- Seguir `CLAUDE.md`: `php artisan make:* --no-interaction`; `vendor/bin/pint --dirty --format agent` antes de cada commit; **sem dependências novas** (cliente HTTP do Laravel).
- Comandos PHP **no container**: `docker compose exec -T app php artisan ...`. Front: `npm run build` no host. A suíte completa roda **sozinha** (o banco de testes Oracle é compartilhado; nunca duas execuções ao mesmo tempo).
- Oracle: `''` vira `NULL`; ids voltam como string; CLOB fora das listagens; VARCHAR2 em bytes; nada de `migrate:fresh`.
- **Não mudar o comportamento das seções 1 e 2 da tela do Daniel** nem os testes dele (`tests/Feature/Retaguarda/IntegracaoIaTest.php`, `ConexaoIaTest.php`), exceto acrescentar.
- **A chave nunca sai do servidor**: só no cabeçalho (`x-goog-api-key` no Gemini, `Authorization: Bearer` na OpenAI), nunca em URL, props, sessão, log ou erro gravado. Seguir o padrão do Daniel (`#[SensitiveParameter]`, `FalhaNaIa` com motivo em português).
- Testes nunca chamam provedores de verdade: `Http::fake` + `Http::preventStrayRequests()`.
- Frontend TS strict, estilo dos vizinhos (aspas simples, 4 espaços — **não rodar Prettier**), visual da tela do Daniel.
- Commits só com os arquivos da tarefa, mensagem em português terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Não fazer push.

## Review Focus

1. **Banco do Daniel (já rodou os ajustes antigos)** e **banco limpo** chegam ao mesmo estado só com `migrate`. Teste na Task 1.
2. **Redundância:** principal falha (chave recusada, 429, 5xx, tempo, resposta fora do formato) → redundância responde → edital criado e a importação registra o provedor que respondeu; os dois falham → erro com os dois motivos, sem edital. Teste na Task 2.
3. **Esquema na OpenAI:** a resposta estruturada da OpenAI exige todos os campos em `required` e `additionalProperties: false`; o esquema traduzido é aceito e uma resposta da OpenAI no formato dela vira o mesmo edital que a do Gemini. Teste na Task 2.
4. **Sem configuração:** sem provedor principal com chave e modelo, o envio é recusado antes de qualquer chamada, com a mensagem da spec; o botão continua visível. Teste na Task 3.
5. **Chave:** nenhum dos caminhos novos (seção 3, erros dos dois provedores, log) expõe a chave. Testes nas Tasks 2 e 3.

---

### Task 1: Junção das branches e migrations

**Files:**
- Merge: `git merge feat/edital-importacao-ia` na branch atual (`feat/edital-importacao-ia-integrada`).
- Remove (da nossa parte): `database/migrations/2026_10_28_181955_create_configuracoes_ia_table.php`, nosso `ConfiguracaoIaController` + `SalvarConfiguracaoIaRequest` + `resources/js/Pages/Retaguarda/ConfiguracaoIa.vue` + rotas `retaguarda.ia.*` + item "Configurações da IA" do menu + `tests/Feature/Retaguarda/ConfiguracaoIaTest.php` (as partes que testam o que sai).
- Keep (do Daniel): `app/Models/ConfiguracaoIa.php`, `ConexaoIa.php`, `ClienteIa.php`, `FalhaNaIa.php`, a migration `2026_10_28_170128_create_integracao_ia_tables.php` e a tela dele.
- Create: migration `limpar_ajustes_antigos_do_edital` (data anterior a `2026_10_28_172124`); migration `add_importacao_to_configuracoes_ia_table`.
- Modify: migration de criação de `importacoes_edital` (nunca enviada: pode ser editada) para ter `provedor` string(20) null e `modelo` já existente; `app/Models/ConfiguracaoIa.php` (do Daniel) ganha os campos da importação; o código da importação (`AnalisarEditalCommand`, `ImportacaoEditalController`, `ImportarEditalRequest`, `EditalController`, `ClienteGemini`) passa a ler provedor/modelo/chave de `ConexaoIa` do provedor principal e prompt/temperatura/tamanho da `ConfiguracaoIa` do Daniel.
- Test: `tests/Feature/Retaguarda/ImportacaoEditalTest.php`, `tests/Feature/LimpezaAjustesAntigosTest.php` (novo).

**Interfaces:**
- Produces:
  - Conflitos resolvidos: `app/Models/ConfiguracaoIa.php` fica o do Daniel + os acréscimos abaixo; `bootstrap/app.php`, `routes/retaguarda.php`, `routes/web.php`, `MenuRetaguarda.vue`, `types/index.ts`, `Editais/Show.vue`, `HomeController.php`, `PortalTest.php`, eventos — juntar os dois lados (nada do Daniel se perde; da nossa parte sai só o que a spec manda retirar).
  - Migration de limpeza (`up`, idempotente): para cada uma das tabelas `edital_documento_categoria`, `edital_documentos`, `edital_categorias`, se existir **com o formato antigo** (detectar por uma coluna que só o antigo tem, ex.: `edital_categorias.cota_negros_valor` / `edital_documentos.tamanho_max_mb` sem `etapa`), apagar; se `editais.divulgacao` existir: criar `resumo` (text) se não existir, preencher com `trim(html_entity_decode(strip_tags(divulgacao)))`, e apagar `tipo_fomento`, `vigencia_inicio`, `vigencia_fim`, `divulgacao`; apagar da tabela `migrations` as 4 linhas `2026_10_27_225850_add_dados_gerais_to_editais_table`, `2026_10_27_231628_create_edital_categorias_table`, `2026_10_27_235924_create_edital_documentos_table`, `2026_10_27_235932_create_edital_documento_categoria_table`. Em banco limpo não faz nada. `down` vazio (documentado).
  - Migration `add_importacao_to_configuracoes_ia_table`: `importacao_prompt` longText null (nulo = prompt modelo), `importacao_temperatura` decimal(3,2) default 0.10, `importacao_tamanho_maximo_mb` integer default 14.
  - `ConfiguracaoIa` (Daniel) ganha: os 3 campos no fillable/casts, `promptDaImportacao(): string` (o salvo ou `PromptModelo::editavel()`), `temperaturaDaImportacao(): float`, `tamanhoMaximoPdfMb(): int` (`min(salvo, 14)`), e `provedoresDaImportacao(): list<array{provedor: ProvedorIa, conexao: ConexaoIa}>` — principal e, se houver, redundância, só os que têm `ConexaoIa` com modelo e chave legível (chave ilegível = sem chave, como no padrão do Daniel). **Não depende de `ativo`** (spec §5.3).
  - Nesta task a importação continua chamando só o Gemini (o primeiro provedor da lista que for Gemini); OpenAI e redundância entram na Task 2.

- [ ] **Step 1:** `git merge feat/edital-importacao-ia` e resolver os conflitos conforme acima; retirar a nossa configuração.
- [ ] **Step 2: Testes que falham** em `LimpezaAjustesAntigosTest`: banco com as tabelas e colunas antigas criadas à mão (simulando o do Daniel, dentro do teste) → depois de rodar a migration de limpeza, as tabelas antigas não existem, `resumo` tem o texto sem tags, as colunas antigas saíram, as 4 linhas sumiram de `migrations`; rodar de novo não falha; banco sem nada antigo → nada muda. Em `ImportacaoEditalTest`: a importação usa chave e modelo da `ConexaoIa` do principal Gemini e o prompt/temperatura/tamanho da `ConfiguracaoIa` do Daniel; `importacao_tamanho_maximo_mb` 20 vale 14.
- [ ] **Step 3: Implementar** e adaptar os testes da importação ao novo lugar da configuração.
- [ ] **Step 4:** no container, `migrate` no banco de dev (que está no estado da `master`, com os ajustes antigos aplicados: é o caso do Review Focus 1 na prática) e conferir que termina sem erro e que `editais.resumo` voltou; rodar a **suíte completa sozinha** — PASS; `npm run build` — OK.
- [ ] **Step 5: Commit** do merge e um commit `feat: importação do edital usa a integração com IA do Daniel`.

---

### Task 2: Análise com OpenAI e Gemini, com redundância

**Files:**
- Create: `app/Support/EditalIa/AnalisadorDeEdital.php` (interface), `app/Support/EditalIa/AnalisadorGemini.php` (a partir do `ClienteGemini` atual), `app/Support/EditalIa/AnalisadorOpenAi.php`, `app/Support/EditalIa/AnaliseComRedundancia.php`, `app/Support/EditalIa/EsquemaOpenAi.php`
- Modify: `app/Console/Commands/AnalisarEditalCommand.php`, `app/Models/ImportacaoEdital.php`, `app/Support/EditalIa/EsquemaDaResposta.php` (se precisar de ajustes comuns)
- Remove: `app/Support/EditalIa/ClienteGemini.php` e `FalhaDoProvedor.php` (substituídos; mensagens migram para `FalhaNaIa`)
- Test: `tests/Feature/EditalIa/AnalisadorGeminiTest.php`, `AnalisadorOpenAiTest.php`, `AnaliseComRedundanciaTest.php`, `tests/Feature/Retaguarda/ImportacaoEditalTest.php`

**Interfaces:**
- Produces:
  - `interface AnalisadorDeEdital { public function analisar(string $modelo, #[SensitiveParameter] string $chave, string $pdfBinario, string $prompt, float $temperatura, array $esquema): array{dados: array, tokens: array{entrada: int, saida: int, total: int}} }` — lança `FalhaNaIa` com o motivo em português.
  - `AnalisadorGemini`: o pedido atual (`generateContent`, `inline_data` `application/pdf`, `responseMimeType`, `responseSchema`), timeout 300 s.
  - `AnalisadorOpenAi`: `POST https://api.openai.com/v1/responses`, cabeçalho `Authorization: Bearer`, `store: false`, `input: [{role: 'user', content: [{type: 'input_file', filename: 'edital.pdf', file_data: 'data:application/pdf;base64,…'}, {type: 'input_text', text: prompt}]}]`, `text: {format: {type: 'json_schema', name: 'edital', strict: true, schema: EsquemaOpenAi::de($esquema)}}`, `temperature`; lê o texto de saída (`output[]` com `type: message` → `content[]` com `type: output_text`) como JSON e `usage.{input_tokens, output_tokens, total_tokens}`; timeout 300 s. Erros → `FalhaNaIa` com as mesmas mensagens do padrão do Daniel (chave recusada 401, 429 limite, 5xx indisponível, tempo, fora do formato, código genérico).
  - `EsquemaOpenAi::de(array $esquemaGemini): array` — converte o subconjunto usado (`type` em maiúsculas → minúsculas, `nullable: true` → `type: [tipo, 'null']`, `enum` mantido com `null` acrescentado quando anulável), põe **todas** as propriedades em `required` e `additionalProperties: false` em todo objeto.
  - **Temperatura recusada:** se a resposta de erro indicar que o parâmetro `temperature` não é aceito pelo modelo (400 com `param: 'temperature'` na OpenAI; mensagem equivalente no Gemini), repetir **uma vez** no mesmo provedor sem a temperatura antes de lançar.
  - `AnaliseComRedundancia::analisar(list<array{provedor, conexao}> $provedores, …): array{dados, tokens, provedor: ProvedorIa, modelo: string, falhas: list<array{provedor, motivo}>}` — tenta na ordem; `FalhaNaIa` passa ao próximo; todos falham → lança `FalhaNaIa` com `Nenhum provedor respondeu. {Rótulo do principal}: {motivo}. {Rótulo da redundância}: {motivo}.`; sem provedores → `A integração com IA não está configurada. Configure o provedor em Parametrização › Integração com IA.`
  - Comando: usa `ConfiguracaoIa::provedoresDaImportacao()` + `AnaliseComRedundancia`; grava `provedor` e `modelo` que responderam; os pontos de atenção ganham, quando o principal falhou e a redundância respondeu, um item `{bloco: 'identificacao', tipo: 'incerta', texto: 'Analisado pelo provedor de redundância ({rótulo}) porque o principal falhou: {motivo}.'}`. Prazo para "travada": **15 minutos** (`ImportacaoEdital::situacaoExibida`, `abrirNovaTentativa`, mensagens).

- [ ] **Step 1: Testes que falham:** Gemini (pedido e leitura atuais, reaproveitar os testes do `ClienteGemini`); OpenAI (URL, cabeçalho Bearer, `store: false`, arquivo em base64, `json_schema` strict, leitura de `output_text` e `usage`; 401/429/5xx/tempo/fora do formato → mensagens; chave nunca no corpo do erro gravado nem no log); `EsquemaOpenAi` (todo objeto com `additionalProperties: false` e todas as chaves em `required`; anuláveis com `null` no tipo); temperatura recusada → repete sem e responde; redundância (Review Focus 2): principal falha → redundância responde, registra provedor; os dois falham → mensagem com os dois motivos; sem provedores → mensagem de configuração; importação ponta a ponta com principal OpenAI e com principal Gemini criando o mesmo edital a partir da mesma resposta (Review Focus 3); 15 minutos.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4:** rodar os testes da task e a suíte completa sozinha — PASS.
- [ ] **Step 5: Commit** `feat: análise do edital pela OpenAI e pelo Gemini, com redundância`

---

### Task 3: Seção "Importação de editais" na tela do Daniel e importação sempre disponível

**Files:**
- Create: `app/Http/Requests/Retaguarda/SalvarImportacaoIaRequest.php`
- Modify: `app/Http/Controllers/Retaguarda/IntegracaoIaController.php` (nova ação `importacao` e props), `routes/retaguarda.php`, `resources/js/Pages/Retaguarda/IntegracaoIa.vue`, `resources/js/types/index.ts`, `app/Http/Controllers/Retaguarda/EditalController.php` (`importacaoAtiva` → sempre true; `importacaoConfigurada`), `resources/js/Pages/Retaguarda/Editais/Index.vue`, `resources/js/Components/Retaguarda/Edital/ImportarEdital.vue`, `app/Http/Requests/Retaguarda/ImportarEditalRequest.php` / `ImportacaoEditalController.php` (recusa sem configuração)
- Test: `tests/Feature/Retaguarda/IntegracaoIaTest.php` (acrescentar), `tests/Feature/Retaguarda/ImportacaoEditalTest.php`

**Interfaces:**
- Consumes: Task 1 (`ConfiguracaoIa` com os campos da importação), Task 2 (mensagem de configuração).
- Produces:
  - Rota `PUT /retaguarda/integracao-ia/importacao` → `retaguarda.integracao-ia.importacao` (grupo gestor, `throttle` como as do Daniel): `prompt` required string max 20000 (igual ao prompt modelo → grava null), `temperatura` required numeric 0–1, `tamanho_maximo_mb` required integer 1–14; mensagens no padrão do Daniel; volta com `Configuração da importação salva.` Rota `PUT /retaguarda/integracao-ia/importacao/prompt-modelo` → restaura (prompt null) com `Prompt modelo restaurado.`
  - `edit` do Daniel ganha a prop `importacao: {prompt, promptModelo, temperatura, tamanhoMaximoMb, configurada: bool}` (sem nada da chave).
  - `IntegracaoIa.vue`: seção **"3. Importação de editais"** depois da redundância, no mesmo visual das seções 1 e 2: texto curto explicando que a importação usa o provedor principal e a redundância acima e fica sempre disponível; aviso "Configure o provedor principal acima para importar editais." quando `configurada` é false; campos temperatura, tamanho máximo do PDF (até 14 MB), prompt (`textarea` grande) com "Restaurar o prompt modelo" (confirmação em `<dialog>`); botão "Salvar importação". Formulário próprio; nada muda nas seções 1 e 2.
  - Lista de editais: o botão **"Importar com IA"** aparece **sempre** (`importacaoAtiva` deixa de existir ou vale true); sem configuração, o envio é recusado antes de qualquer chamada com `A integração com IA não está configurada. Configure o provedor em Parametrização › Integração com IA.` e a janela mostra o link para `/retaguarda/integracao-ia`. "Tentar de novo" também sempre visível (com a mesma recusa).
  - Remover do formulário/props do edital qualquer referência à tela antiga de Configurações da IA.

- [ ] **Step 1: Testes que falham:** salvar a seção 3 (valores gravados; prompt igual ao modelo vira null; temperatura e tamanho fora da faixa recusados); restaurar; props `importacao` sem chave (`assertDontSee` da chave cadastrada numa `ConexaoIa`); as seções 1 e 2 seguem funcionando (testes do Daniel passam sem alteração); importar sem configuração → recusa com a mensagem exata e nenhuma chamada HTTP (Review Focus 4); com configuração e `ativo` desligado → aceita (a importação não depende de `ativo`); lista de editais sempre com o botão.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4:** rodar os testes da task e a suíte completa sozinha — PASS; `npm run build` — OK; conferir no navegador (`http://127.0.0.1:8000/retaguarda/integracao-ia` e a lista de editais, `admin@admin` / `123456`, servidor lento): seção 3, salvar, restaurar, aviso sem provedor, botão e recusa na lista; modo noturno; notebook. Sem chave real.
- [ ] **Step 5: Commit** `feat: seção de importação de editais na integração com IA`

---

### Task 4: Banco de desenvolvimento e conferência final

**Files:** nenhum código (só dados do banco de dev).

- [ ] **Step 1:** no container, conferir `migrate:status` sem pendências na branch.
- [ ] **Step 2:** restaurar a estrutura do edital a partir de `storage/app/backup-feat-edital-ia.json` (container): para os 14 editais existentes, preencher as colunas novas e recriar chamadas, categorias, itens, documentos (com pivôs), requisitos e critérios com os valores do backup, sem duplicar o que a migration de dados já criou (chamadas). Não restaurar `configuracoes_ia` nem `importacoes_edital` do backup (formato antigo). Fazer por um script de uma vez no tinker, dentro de transação, e conferir contagens contra o backup.
- [ ] **Step 3:** conferir no navegador: lista de editais, um edital com blocos abre e salva sem mudar nada, página pública de um edital publicado, tela de Integração com IA com as 3 seções.
- [ ] **Step 4:** relatar o que foi restaurado e as contagens. Sem commit.
