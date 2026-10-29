# Edital importado do PDF com IA · Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O edital ganha o cadastro estruturado do que a inscrição, a validação e a homologação usam (mais seções informativas), e pode ser importado de um PDF por um modelo de IA (Gemini), configurado numa tela própria.

**Architecture:** Colunas novas em `editais` e tabelas filhas (chamadas, categorias e itens, documentos, requisitos, critérios), sincronizadas ao salvar o edital como já é o padrão do projeto. A importação grava um registro `importacoes_edital`, lança um comando `php artisan` em segundo plano (sem fila) que chama o Gemini pelo cliente HTTP do Laravel com o PDF e um esquema de resposta fixo, e transforma a resposta num edital não publicado.

**Tech Stack:** Laravel 13, Inertia v2, Vue 3 + TypeScript strict, Tailwind v4, Oracle 23ai (`yajra/laravel-oci8`), PHPUnit. API REST do Gemini (`generativelanguage.googleapis.com`).

**Spec:** `docs/superpowers/specs/2026-10-28-edital-importacao-ia-design.md`

## Global Constraints

- Seguir `CLAUDE.md` (Laravel Boost): arquivos via `php artisan make:* --no-interaction`; `vendor/bin/pint --dirty --format agent` antes de cada commit; testes no escopo da tarefa; **sem dependências novas** (HTTP do Laravel, criptografia do Laravel).
- Comandos PHP rodam **no container**: `docker compose exec -T app php artisan ...`. Front: `npm run build` no host (`vue-tsc`).
- Oracle: `''` vira `NULL`; ids voltam como string; JSON e texto longo em `longText` (CLOB) **fora das listagens**; VARCHAR2 em bytes (cortar com `mb_strcut` o que vem de fora); nada de `migrate:fresh` (só `migrate`); 12 editais reais no banco de dev devem continuar abrindo.
- Retaguarda: grupo `['auth', 'gestor']` de `routes/retaguarda.php`; mesmo visual (abas laterais `AbasFormulario`, `CampoCadastro`, modo noturno). Frontend em TypeScript strict, estilo dos vizinhos (aspas simples, 4 espaços — **não rodar Prettier**).
- **A chave da API nunca sai do servidor** em texto: nem em props do Inertia, nem em log, nem em mensagem de erro. Chamar o Gemini com o cabeçalho `x-goog-api-key` (nunca na URL).
- Testes nunca chamam o Gemini de verdade: `Http::fake` / `Http::preventStrayRequests()`.
- Textos em português do Brasil; mensagens de validação no padrão existente (`'Este campo é obrigatório.'`).
- Commits só com os arquivos da tarefa, mensagem em português terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Não fazer push (branch local).

## Review Focus

1. **Edital incompleto vs. publicação:** edital importado sem título, datas ou categorias salva como rascunho; publicar sem título, chamada com datas, natureza ou categoria é recusado com a lista do que falta; editais antigos continuam publicados e na vitrine. Testes nas Tasks 1 e 2.
2. **Vazamento da chave:** a chave não aparece na resposta Inertia, no JSON de "testar conexão", nem no erro gravado na importação quando o Gemini devolve 400/403. Testes nas Tasks 4 e 5.
3. **Resposta da IA fora do formato ou parcial:** JSON inválido, campos faltando, tipos errados, datas impossíveis, naturezas desconhecidas → importação falha sem criar edital, ou (para campos opcionais inválidos) ignora o campo e vira ponto de atenção — nunca 500. Teste na Task 5.
4. **Processo que morre:** importação "analisando" há mais de 10 minutos aparece como erro com "Tentar de novo"; tentar de novo reusa o registro e não duplica edital. Teste na Task 5.
5. **Datas derivadas das chamadas:** salvar com duas chamadas grava `inscricoes_inicio` = menor início e `inscricoes_fim` = maior fim; a situação na vitrine segue isso. Teste na Task 2.

---

### Task 1: Modelo do edital — banco, modelos e dados existentes

**Files:**
- Create: migrations `add_estrutura_to_editais_table`, `create_edital_chamadas_table`, `create_edital_categorias_table`, `create_edital_categoria_itens_table`, `create_edital_documentos_table` (+ pivô `edital_documento_categoria`), `create_edital_requisitos_table` (+ pivô `edital_requisito_documento`), `create_edital_criterios_table` (+ pivô `edital_criterio_categoria`); enums `app/Enums/NaturezaProponente.php`, `TipoEdital.php`, `EtapaDocumento.php`, `ModalidadeCota.php`, `BaseCota.php`, `AlcanceCota.php`; modelos `EditalChamada`, `EditalCategoria`, `EditalCategoriaItem`, `EditalDocumento`, `EditalRequisito`, `EditalCriterio` com factories
- Modify: `app/Models/Edital.php`, `database/factories/EditalFactory.php`
- Test: `tests/Feature/EditalEstruturaTest.php`

**Interfaces:**
- Produces:
  - `editais`: passam a aceitar nulo `titulo`, `categoria`, `resumo`, `inscricoes_inicio`, `inscricoes_fim` (rascunho incompleto). Colunas novas, todas nulas: `numero` string(30), `tipo` string(20) (`TipoEdital`: `Edital='edital'` "Edital", `Chamamento='chamamento'` "Chamamento Público"), `objeto` string(1000), `dom_data` date, `dom_numero` string(20), `naturezas` string(200) (lista de `NaturezaProponente` serializada, cast `AsEnumCollection`), `aceita_coletivos` boolean, `inscricoes_por_proponente` integer, `limite_por_chamada` boolean, `cotas` longText (JSON `{negros|indigenas|pcd: {ativa: bool, percentual: ?num, quantidade: ?num}, base: ?BaseCota, alcance: ?AlcanceCota, veda_negro_indigena: ?bool, heteroidentificacao: ?bool}`), `nota_maxima` integer, `nota_corte` decimal(5,2), `pontuacao_extra` longText (JSON lista `{grupo, pontos_por_pessoa, maximo}` + `{teto, conta_para_corte}`), `informacoes` longText (JSON objeto com as chaves `recursos_pagamento`, `vedacoes`, `etapas_prazos`, `contrapartida_execucao`, `prestacao_contas`, `base_legal`, `anexos`, `contatos`, `outras_regras` → texto), `forma_inscricao_original` string(1000).
  - Enums: `NaturezaProponente` (`PessoaFisica='pessoa_fisica'` "Pessoa Física", `Mei='mei'` "MEI", `SemFins='sem_fins'` "Pessoa Jurídica sem fins lucrativos", `ComFins='com_fins'` "Pessoa Jurídica com fins lucrativos"); `EtapaDocumento` (`Inscricao='inscricao'` "Inscrição", `ValidacaoCotas='validacao_cotas'` "Validação de cotas", `Habilitacao='habilitacao'` "Habilitação"); `ModalidadeCota` (`Negros='negros'` "Pessoas negras", `Indigenas='indigenas'` "Pessoas indígenas", `Pcd='pcd'` "Pessoas com deficiência"); `BaseCota` (`Vagas='vagas'`, `Recursos='recursos'`); `AlcanceCota` (`Edital='edital'` "Todo o edital", `Categoria='categoria'` "Por categoria", `Chamada='chamada'` "Por chamada"). Todos com `rotulo(): string`.
  - Tabelas filhas (todas com `edital_id` FK cascade, `ordem` integer, timestamps): `edital_chamadas` (`nome` string(100), `inicio` date, `fim` date); `edital_categorias` (`nome` string(255), `descricao` string(2000) null, `valor_centavos` bigint null, `vagas` integer null, `naturezas` string(200) null, `edital_chamada_id` FK null nullOnDelete); `edital_categoria_itens` (FK `edital_categoria_id` cascade, `nome` string(255), `valor_centavos` bigint null, `vagas` integer null, `suplentes` integer null); `edital_documentos` (`nome` string(255), `orientacao` string(1000) null, `etapa` string(20), `obrigatorio` boolean default true, `naturezas` string(200) null — nulo = todas, `todas_categorias` boolean default true, `modalidade_cota` string(20) null, `anexo_referencia` string(255) null, `modelo` string(255) null, `modelo_nome` string(255) null) + pivô `edital_documento_categoria`; `edital_requisitos` (`descricao` string(1000)) + pivô `edital_requisito_documento`; `edital_criterios` (`nome` string(500), `descricao` string(2000) null, `peso` decimal(6,2) null, `pontos_maximos` decimal(6,2) null, `todas_categorias` boolean default true) + pivô `edital_criterio_categoria`.
  - `Edital`: relações `chamadas`, `categorias` (com `itens`), `documentos`, `requisitos`, `criterios` ordenadas por `ordem`; casts dos campos novos; `informacoes`/`cotas`/`pontuacao_extra` como `array`; `pendenciasParaPublicar(): list<string>` — mensagens exatas: `Informe o título do edital.`, `Envie o PDF do edital.`, `Cadastre ao menos uma chamada com início e fim das inscrições.`, `Marque ao menos uma natureza que pode se inscrever.`, `Cadastre ao menos uma categoria.`; `situacao()` e escopos da vitrine continuam usando `inscricoes_inicio/fim` e tratam edital sem datas como fora da vitrine.
  - Migration de dados: cada edital existente ganha uma chamada `Inscrições` com as datas atuais; nada mais muda neles.

- [ ] **Step 1: Testes que falham** — editais antigos: depois da migration cada um tem 1 chamada com as mesmas datas e continua na vitrine; edital com só `titulo` nulo e sem datas pode ser criado (rascunho) e não aparece na vitrine nem na contagem de abertos; `pendenciasParaPublicar` devolve as 5 mensagens exatas conforme o que falta e lista vazia quando completo; relações ordenadas; casts (naturezas como coleção de enum, cotas/informações como array).
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.** Ajustar código existente que supõe `titulo`/datas não nulos (Home, Portal, listagem da retaguarda) para não quebrar com nulo.
- [ ] **Step 4:** `migrate` no container; rodar o teste novo e a suíte inteira — PASS.
- [ ] **Step 5: Commit** `feat: estrutura do edital para inscrição, validação e homologação`

---

### Task 2: Salvar o edital — validação, sincronização e publicação

**Files:**
- Modify: `app/Http/Requests/Retaguarda/SalvarEditalRequest.php`, `app/Http/Controllers/Retaguarda/EditalController.php`, `routes/web.php` (download do arquivo-modelo no portal), `routes/retaguarda.php` (download na retaguarda)
- Create: `app/Http/Controllers/DocumentoModeloController.php`
- Test: `tests/Feature/Retaguarda/EditaisTest.php`, novo `tests/Feature/Retaguarda/EditalEstruturaSalvarTest.php`, novo `tests/Feature/DocumentoModeloTest.php`

**Interfaces:**
- Consumes: Task 1.
- Produces:
  - Request: todos os campos da Task 1 opcionais (rascunho incompleto); `titulo` max 255; listas `chamadas.*` (`id`, `nome` required, `inicio` required date, `fim` required date ≥ início), `categorias.*` (`id`, `nome` required, `descricao`, `valor` numeric ≥ 0.01 opcional, `vagas` integer ≥ 1 opcional, `naturezas.*` enum, `chamada` = posição na lista `chamadas` opcional, `itens.*` com `nome` required, `valor`, `vagas`, `suplentes` ≥ 0), `documentos.*` (`nome` required, `orientacao`, `etapa` required enum, `obrigatorio` bool, `naturezas.*`, `todas_categorias` bool, `categorias.*` = posições, `modalidade_cota` enum opcional, `anexo_referencia`, `modelo` file pdf/doc/docx/xls/xlsx/odt/ods ≤ 10 MB, `remover_modelo` bool), `requisitos.*` (`descricao` required, `documentos.*` = posições), `criterios.*` (`nome` required, `descricao`, `peso` ≥ 0, `pontos_maximos` ≥ 0, `todas_categorias`, `categorias.*` = posições), `cotas.*`, `pontuacao_extra.*`, `informacoes.*` (cada texto max 20000). Listas com no máximo 50 itens; um marcador `com_listas=1` diz que as listas vieram (sem o marcador, as listas não são tocadas; com ele, lista ausente = apagar todas). Mensagens em português, atributos legíveis (sem chaves técnicas).
  - Controller: `store`/`update` gravam o edital e sincronizam as listas por id numa transação (ids de outro edital viram itens novos; posições traduzidas para ids no mesmo envio), arquivo-modelo com o ciclo de vida já usado (`ArquivoArmazenado`: novos descartados se a transação falha, antigos apagados depois do commit). Depois de salvar, `inscricoes_inicio`/`inscricoes_fim` = menor início e maior fim das chamadas (nulos sem chamadas). `publicacao`: se `pendenciasParaPublicar()` não vazio, volta com `erro` = as mensagens unidas por espaço; senão publica. `edit` envia todos os blocos + listas de enums (`naturezas`, `etapas`, `modalidadesCota`, `basesCota`, `alcancesCota`, `tiposEdital`) + `pendencias`. A listagem da retaguarda segue sem CLOB.
  - Rotas de download do arquivo-modelo: portal `GET /editais/{edital:slug}/documentos/{documento}/modelo` → `editais.documentos.modelo` (404 se não visível, se o documento não for do edital ou sem modelo); retaguarda `GET /retaguarda/editais/{edital}/documentos/{documento}/modelo` → `retaguarda.editais.documentos.modelo`; ambas com `scopeBindings()`.

- [ ] **Step 1: Testes que falham** — salvar cada bloco e ler de volta; editar preservando ids; remover itens; posições de categoria em documento/critério e de documento em requisito traduzidas para ids; datas do edital derivadas de duas chamadas (Review Focus 5); fim antes do início recusado na chamada; rascunho só com título salva; sem `com_listas` as listas ficam intactas; publicar incompleto recusa com as mensagens exatas, completo publica (Review Focus 1); arquivo-modelo: enviar, manter sem reenviar, trocar apaga o antigo, remover; downloads (portal sem login só de edital visível; 404 nos casos); mensagens sem chave técnica.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4:** rodar os testes da task e `EditaisTest` — PASS.
- [ ] **Step 5: Commit** `feat: salvar e publicar o edital com os blocos estruturados`

---

### Task 3: Formulário do edital na retaguarda

**Files:**
- Modify: `resources/js/Pages/Retaguarda/Editais/Form.vue`, `resources/js/types/index.ts`
- Create: `resources/js/Components/Retaguarda/listaEditavel.ts` (sanfona reutilizável: chave estável, subir/descer, remover com `<dialog>`, erros por índice), `resources/js/Components/Retaguarda/Edital/ChamadasEdital.vue`, `CategoriasEdital.vue` (com itens), `DocumentosEdital.vue`, `RequisitosEdital.vue`, `CriteriosEdital.vue`, `CotasEdital.vue`, `InformacoesEdital.vue`

**Interfaces:**
- Consumes: props e contrato do envio da Task 2.
- Produces: formulário em abas laterais: **Identificação** (número, tipo, título, objeto, publicação no DOM, categoria da vitrine, resumo), **Inscrição** (chamadas, forma de inscrição original), **Participação** (naturezas, coletivos, inscrições por proponente, limite por chamada), **Categorias** (com itens), **Cotas** (modalidades com percentual/quantidade, base, alcance, vedação negro+indígena, heteroidentificação), **Documentos**, **Homologação** (requisitos), **Avaliação** (nota máxima, nota de corte, critérios, pontuação extra), **Informações** (as 9 seções de texto), **Arquivos**, **Publicação** (situação, pendências da Task 2 listadas antes do botão Publicar). Envio com `forceFormData`, `queryStringArrayFormat: 'indices'` e o marcador `com_listas: true`. Cada aba marca erros (`campos` com prefixos `chamadas.*` etc.); campos numéricos não aceitam negativos; valores em reais formatados.

- [ ] **Step 1: Implementar.**
- [ ] **Step 2:** `npm run build` — OK.
- [ ] **Step 3: Conferir no navegador** (`http://127.0.0.1:8000/retaguarda/editais`, `admin@admin` / `123456`, servidor lento): cadastrar um edital de teste com todos os blocos, salvar, reabrir, reordenar e remover itens, tentar publicar incompleto e ver as pendências; editais antigos abrem sem erro; modo noturno; notebook 1366×768. Excluir o edital de teste.
- [ ] **Step 4: Commit** `feat: formulário do edital com os blocos estruturados`

---

### Task 4: Configurações da IA

**Files:**
- Create: migration `create_configuracoes_ia_table`; `app/Models/ConfiguracaoIa.php`; `app/Support/EditalIa/PromptModelo.php`; `app/Support/EditalIa/ClienteGemini.php`; `app/Http/Controllers/Retaguarda/ConfiguracaoIaController.php`; `app/Http/Requests/Retaguarda/SalvarConfiguracaoIaRequest.php`; `resources/js/Pages/Retaguarda/ConfiguracaoIa.vue`
- Modify: `routes/retaguarda.php`, `resources/js/Components/Retaguarda/MenuRetaguarda.vue`, `resources/js/types/index.ts`
- Test: `tests/Feature/Retaguarda/ConfiguracaoIaTest.php`

**Interfaces:**
- Produces:
  - Tabela `configuracoes_ia` (registro único): `ativa` boolean default false, `provedor` string(20) default `'gemini'`, `chave` longText null (cast `encrypted`), `modelo` string(100) default `'gemini-2.5-pro'`, `temperatura` decimal(3,2) default 0.10, `tamanho_maximo_mb` integer default 20, `prompt` longText null (nulo = usa o modelo), timestamps. `ConfiguracaoIa::atual(): self` (cria com os padrões se não houver), `chaveFinal(): ?string` (últimos 4 caracteres), `promptEfetivo(): string`.
  - `PromptModelo::editavel(): string` (o prompt modelo, spec §6) e `PromptModelo::fixo(): string` (parte fixa, spec §6: responder só no formato, não inventar, só o edital do cabeçalho principal, apontar divergências com os itens). O fixo nunca aparece na tela.
  - `ClienteGemini` (via container, trocável em testes): `testar(ConfiguracaoIa $config): void` (GET `https://generativelanguage.googleapis.com/v1beta/models/{modelo}` com `x-goog-api-key`; lança `FalhaDoProvedor`) e `analisar(ConfiguracaoIa $config, string $pdfBinario, string $prompt, array $esquema): array{dados: array, tokens: array{entrada: int, saida: int, total: int}}` (POST `.../models/{modelo}:generateContent` com `contents[0].parts` = `inline_data` `application/pdf` em base64 + texto do prompt; `generationConfig` com `temperature`, `responseMimeType: 'application/json'`, `responseSchema`; timeout HTTP de 300 s; lê `candidates[0].content.parts[0].text` como JSON e `usageMetadata`). `App\Support\EditalIa\FalhaDoProvedor` (exception) com `mensagem` em português conforme o status: 400/403 com chave → `A chave da API foi recusada pelo Google. Confira a chave nas configurações.`; 404 → `O modelo informado não existe ou não está disponível para esta chave.`; 429 → `O limite de uso da API foi atingido. Tente de novo mais tarde.`; 5xx → `O serviço do Google está indisponível no momento. Tente de novo mais tarde.`; tempo esgotado → `A análise demorou demais e foi interrompida. Tente de novo.`; resposta sem JSON válido → `A resposta da IA veio fora do formato esperado.`; outros → `Não foi possível falar com o serviço de IA (código {status}).` Nunca inclui a chave.
  - Rotas (gestor): `GET /retaguarda/configuracoes-ia` → `retaguarda.ia.edit`; `PUT` → `retaguarda.ia.update` (chave só é trocada quando o campo `chave` vem preenchido); `POST /retaguarda/configuracoes-ia/teste` → `retaguarda.ia.testar` (JSON `{ok: bool, mensagem}`); `POST /retaguarda/configuracoes-ia/prompt-modelo` → `retaguarda.ia.restaurar` (zera o `prompt`). Props do edit: `config {ativa, provedor, modelo, temperatura, tamanhoMaximoMb, prompt (efetivo editável), temChave, chaveFinal}`, `provedores`, `promptModelo` — **nunca a chave**.
  - Tela `Retaguarda/ConfiguracaoIa.vue`: os campos da spec §5, "Chave cadastrada, terminada em …XXXX" com "Trocar chave" (input `type=password`, `autocomplete="off"`), "Testar conexão" com o resultado na tela, prompt em `textarea` grande com "Restaurar o prompt modelo" (confirmação); item **Configurações da IA** no menu da retaguarda.

- [ ] **Step 1: Testes que falham** — padrões ao abrir pela primeira vez; salvar chave grava criptografado (valor no banco ≠ chave) e a prop não contém a chave (Review Focus 2: `assertDontSee` da chave no JSON da página); salvar sem chave mantém a anterior; temperatura fora de 0–1 e tamanho fora de 1–20 recusados; restaurar prompt; testar conexão com `Http::fake` 200 → ok, 403 → mensagem exata sem a chave, sem chave cadastrada → `Cadastre a chave da API antes de testar.`; o pedido sai com `x-goog-api-key` e sem a chave na URL (`Http::assertSent`); proponente sem acesso.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.**
- [ ] **Step 4:** `migrate`; rodar o teste — PASS; `npm run build` — OK; conferir a tela no navegador (sem chave real: testar conexão deve mostrar o erro de chave).
- [ ] **Step 5: Commit** `feat: configurações da IA na retaguarda`

---

### Task 5: Importação — análise em segundo plano e criação do edital

**Files:**
- Create: migration `create_importacoes_edital_table`; `app/Enums/SituacaoImportacao.php`; `app/Models/ImportacaoEdital.php`; `app/Support/EditalIa/EsquemaDaResposta.php`; `app/Support/EditalIa/EditalDaResposta.php`; `app/Support/EditalIa/LancadorDeAnalise.php`; `app/Console/Commands/AnalisarEditalCommand.php`; `app/Http/Controllers/Retaguarda/ImportacaoEditalController.php`; `app/Http/Requests/Retaguarda/ImportarEditalRequest.php`
- Modify: `routes/retaguarda.php`
- Test: `tests/Feature/Retaguarda/ImportacaoEditalTest.php`, `tests/Unit/EditalDaRespostaTest.php`

**Interfaces:**
- Consumes: Tasks 1, 2 (sincronização reaproveitável: extrair do controller para um serviço se necessário), 4 (`ClienteGemini`, `ConfiguracaoIa`, `PromptModelo`).
- Produces:
  - Tabela `importacoes_edital`: `user_id` FK, `edital_id` FK null nullOnDelete, `situacao` string(20) (`SituacaoImportacao`: `Analisando='analisando'`, `Concluida='concluida'`, `Erro='erro'`), `pdf` string(255), `pdf_nome` string(255), `modelo` string(100), `tokens_entrada`/`tokens_saida`/`tokens_total` integer null, `erro` string(1000) null, `resposta` longText null (JSON recebido), `confiabilidade` longText null (JSON `{geral: int, blocos: {bloco: {nota: int, justificativa: string}}}`), `atencao` longText null (JSON lista `{bloco, tipo: 'nao_encontrado'|'divergencia'|'incerta', texto, itens: string[]}`), `conferida_em` timestamp null, `iniciada_em` timestamp, timestamps. `ImportacaoEdital::situacaoExibida(): SituacaoImportacao` — `analisando` com `iniciada_em` há mais de 10 minutos vira `Erro` com `A análise não terminou. Tente de novo.`
  - `EsquemaDaResposta::esquema(): array` — o `responseSchema` do Gemini (subconjunto OpenAPI: `type`, `properties`, `items`, `enum`, `nullable`, `required`) com os blocos da spec §7 (identificação, chamadas, participação, categorias com itens, cotas, documentos com `etapa`/`naturezas`/`categorias` por **nome** de categoria/`modalidade_cota`/`anexo_referencia`, requisitos com documentos por nome, avaliação com critérios e pontuação extra, informações com as 9 chaves) + `confiabilidade` (nota 0–100 e justificativa por bloco: `identificacao`, `inscricao`, `participacao`, `categorias`, `cotas`, `documentos`, `homologacao`, `avaliacao`, `informacoes`) + `atencao`. Datas `YYYY-MM-DD`, valores em reais como número.
  - `EditalDaResposta::criar(array $dados, ImportacaoEdital $importacao): Edital` — valida e normaliza a resposta: campos obrigatórios do esquema ausentes ou com tipo errado na raiz → lança `RespostaInvalida` (importação falha); item de lista inválido (data impossível, natureza desconhecida, valor negativo, fim antes do início) → descartado e acrescentado como ponto de atenção `incerta` com o texto do que foi descartado; liga documentos/critérios a categorias e requisitos a documentos pelo nome (sem par → "todas" e ponto de atenção); corta strings nos limites em bytes; cria o edital **não publicado** (título ausente → `Edital importado em dd/mm/aaaa`), copia o PDF para o PDF do edital, slug único; geral = média arredondada das notas dos blocos.
  - `LancadorDeAnalise::lancar(ImportacaoEdital $importacao): void` — inicia `php artisan editais:analisar {id}` desacoplado (`nohup … > /dev/null 2>&1 &` via `Process`), resolvido pelo container; nos testes, trocado por um fake que roda o comando na hora (`Artisan::call`).
  - Comando `editais:analisar {importacao}`: `set_time_limit(600)`; marca `iniciada_em`; chama `ClienteGemini::analisar` com `PromptModelo::fixo()` + `ConfiguracaoIa::promptEfetivo()` e o esquema; sucesso → `EditalDaResposta::criar` numa transação, grava tokens, resposta, confiabilidade, atenção, `concluida`; `FalhaDoProvedor`/`RespostaInvalida` → `erro` com a mensagem em português (nunca a chave), sem edital; qualquer outra exceção → `erro` com `Erro inesperado na análise.` e log sem a chave.
  - Rotas (gestor, só com `ConfiguracaoIa::atual()->ativa` e chave; senão 404 / botão oculto): `POST /retaguarda/editais/importacoes` → `retaguarda.editais.importacoes.store` (`pdf` required file pdf ≤ `tamanho_maximo_mb` → grava, lança, redirect para a lista com `Análise iniciada. Você pode continuar usando o sistema.`); `GET /retaguarda/editais/importacoes/{importacao}` → JSON `{situacao, erro, editalUrl}` para a tela consultar; `POST .../{importacao}/nova-tentativa` → `retaguarda.editais.importacoes.tentar` (só em erro; reusa o registro, zera erro, relança); `DELETE .../{importacao}` → descarta (só em erro; apaga registro e PDF); `POST /retaguarda/editais/{edital}/importacao/conferida` → marca `conferida_em`.

- [ ] **Step 1: Testes que falham** (com `Http::fake` e o lançador fake): PDF válido + resposta completa → edital não publicado com blocos, PDF anexado, tokens, confiabilidade (geral = média), atenção; resposta sem título → título provisório; JSON inválido / raiz sem blocos / 403 / 429 / 500 / timeout → importação em erro com a mensagem exata e **nenhum** edital; o erro gravado não contém a chave (Review Focus 2); item inválido descartado vira atenção (Review Focus 3); documento com categoria por nome inexistente vira "todas" + atenção; PDF acima do limite recusado antes de chamar a IA; importação desligada ou sem chave → 404; "analisando" há 11 minutos aparece como erro; tentar de novo reusa o registro e cria um só edital (Review Focus 4); descartar apaga o PDF; marcar conferida; `EditalDaRespostaTest` cobre a normalização sem HTTP.
- [ ] **Step 2: Rodar e ver falhar.**
- [ ] **Step 3: Implementar.** O texto do prompt modelo segue a spec §6.
- [ ] **Step 4:** `migrate`; rodar os testes — PASS; conferir no container que `LancadorDeAnalise` real inicia o comando e devolve na hora (com a IA desligada o comando termina em erro de chave — suficiente para ver o processo rodar e gravar o erro).
- [ ] **Step 5: Commit** `feat: importação do edital do PDF com IA em segundo plano`

---

### Task 6: Telas da importação

**Files:**
- Modify: `resources/js/Pages/Retaguarda/Editais/Index.vue`, `resources/js/Pages/Retaguarda/Editais/Form.vue`, `app/Http/Controllers/Retaguarda/EditalController.php` (props), `resources/js/types/index.ts`
- Create: `resources/js/Components/Retaguarda/Edital/ImportarEdital.vue`, `resources/js/Components/Retaguarda/Edital/QuadroImportacao.vue`
- Test: `tests/Feature/Retaguarda/EditaisTest.php` (props)

**Interfaces:**
- Consumes: rotas e modelo da Task 5.
- Produces:
  - `EditalController@index` envia `importacaoAtiva: bool` e `importacoes: list<{id, pdfNome, situacao, erro, editalUrl, enviadaEm}>` (as analisando e com erro, mais as concluídas das últimas 24 h; sem CLOB); `edit` envia `importacao: ?{confiabilidade, atencao, conferida, modelo, tokensTotal, importadaEm}` quando o edital veio de uma importação não conferida.
  - Lista de editais: botão **Importar do PDF** (só com `importacaoAtiva`) abrindo `<dialog>` com escolha do PDF (tamanho conferido no navegador), envio; painel de importações com estado (Analisando com indicador, Concluída com link "Abrir edital", Erro com a mensagem, **Tentar de novo** e **Descartar**); enquanto houver alguma "analisando", a página consulta a rota JSON a cada 10 s e atualiza só o painel.
  - Formulário do edital: `QuadroImportacao` no topo — "Importado pela IA", confiabilidade geral (número grande) e por bloco (barra + nota + justificativa ao passar/abrir), lista de pontos de atenção agrupados por bloco com os itens citados e link para a aba correspondente, botão **Já conferi**. Cores semânticas (alta ≥ 80, média 50–79, baixa < 50) com texto, não só cor.

- [ ] **Step 1: Teste das props** (`importacaoAtiva`, `importacoes`, `importacao` no edit) — falha, implementar, passa.
- [ ] **Step 2: Implementar as telas.**
- [ ] **Step 3:** `npm run build` — OK; conferir no navegador com uma importação simulada (criar pelo tinker um registro concluído ligado a um edital de teste e um em erro): painel, tentar de novo, descartar, quadro no edital, "Já conferi"; modo noturno; notebook. Apagar os dados de teste.
- [ ] **Step 4: Commit** `feat: telas da importação do edital por IA`

---

### Task 7: Página do edital no portal

**Files:**
- Modify: `app/Http/Controllers/EditalController.php`, `resources/js/Pages/Editais/Show.vue`, `resources/js/types/index.ts`
- Test: `tests/Feature/PortalTest.php`

**Interfaces:**
- Consumes: modelo da Task 1, rota de download da Task 2.
- Produces: `show` envia, além do que já envia: `numero`, `tipo`, `objeto`, `chamadas` (nome, início, fim, situação de cada uma), `naturezas` (rótulos), `aceitaColetivos`, `inscricoesPorProponente`, `categorias` (nome, descrição, valor formatado, vagas, itens), `cotas` (modalidades ativas com percentual/quantidade), `documentosInscricao` (só etapa Inscrição: nome, orientação, obrigatório, naturezas, categorias, link do modelo), `informacoes` (só as seções preenchidas, em texto com quebras de linha preservadas). `Show.vue` mostra em seções na ordem: objeto, inscrições, quem pode participar, categorias, cotas, documentos para a inscrição, informações (seções recolhíveis). Edital antigo sem os blocos mostra só o que tem.

- [ ] **Step 1: Testes que falham** — props novas para um edital completo; edital antigo sem blocos abre sem erro e sem seções vazias; só documentos da etapa Inscrição; seções informativas vazias não vêm.
- [ ] **Step 2: Implementar.**
- [ ] **Step 3:** rodar `PortalTest` — PASS; `npm run build` — OK; conferir no navegador (edital completo de teste e um antigo), modo noturno, celular.
- [ ] **Step 4: Commit** `feat: página do edital no portal com os blocos estruturados`
