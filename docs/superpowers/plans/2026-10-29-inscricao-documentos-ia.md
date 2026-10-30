# Inscrição no edital com documentos e checagem por IA — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O proponente se inscreve num edital aberto escolhendo cadastro, categoria e cotas e envia os documentos exigidos, cada um conferido por IA conforme o modo de checagem que a equipe definiu no documento.

**Architecture:** Duas tabelas novas (`inscricoes`, `inscricao_documentos`) e duas colunas em `edital_documentos` (`checagem`, `instrucao_ia`). A checagem de cada documento roda num processo destacado (`inscricoes:conferir`), no mesmo molde da importação do edital (token `execucao`, linha travada), reaproveitando os analisadores e a redundância de `App\Support\EditalIa`, generalizados para aceitar chamada sem arquivo e conferência de resposta parametrizada. Portal em Inertia com páginas próprias de inscrição; a tela consulta a situação dos documentos por JSON enquanto houver conferência.

**Tech Stack:** Laravel 13, Inertia v2, Vue 3 `<script setup lang="ts">`, Tailwind v4, PHPUnit, Oracle (yajra oci8) e PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-10-29-inscricao-documentos-ia-design.md`

## Global Constraints

- Branch `feat/inscricao`, criada da `master` atual. Não depende da branch dos Períodos: o período de inscrição é `EditalChamada::situacao()` (datas `inicio`/`fim` da chamada, que os Períodos mantêm como a fase de Inscrição).
- PHP roda no container: `docker compose exec -T app php artisan ...`; testes com `docker compose exec -T app php artisan test --compact <arquivo>`; a suíte completa roda sozinha (banco de testes Oracle compartilhado). `npm run build` roda vue-tsc. Nunca rodar Prettier. `vendor/bin/pint --dirty --format agent` antes de cada commit.
- Migrations e SQL portáveis (Oracle e PostgreSQL). VARCHAR2 conta bytes: todo texto livre validado com `App\Rules\TamanhoEmBytes`; textos gravados pela IA cortados com `mb_strcut`. Nada de CLOB em listagens. Ids do Oracle chegam como string: converter com `(int)`. Rotas com id numérico usam `whereNumber`.
- A chave da API nunca sai do servidor (nem props, sessão, log, erro gravado ou URL). Testes só com `Http::fake` e `Http::preventStrayRequests()`; nunca chave real.
- Sem dependências novas. Layout segue o portal (`PortalProponenteLayout`, `FaixaPortal`) e a retaguarda atuais; textos em português do Brasil.
- Arquivo da inscrição: só PDF, até 10 MB (`max:10240`), disco `local`, pasta `inscricoes/{id da inscrição}`.
- Mensagem ao proponente quando a IA não confere: `Não foi possível conferir o documento agora.` (constante `ConferirDocumentoCommand::NAO_CONFERIDO`). Detalhe técnico vai só para a coluna `erro`.
- Commits pequenos, mensagem em português no padrão `feat: ...`/`test: ...`, terminando com `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Não fazer push.

## Review Focus

1. Proponente com a mesma pessoa em duas abas: anexar um documento enquanto a outra aba finaliza. Finalizar deve reconferir tudo com a inscrição travada (`lockForUpdate`) e recusar se algum documento estiver conferindo ou faltando. Teste na Task 9.
2. Conferência que nunca termina (processo morto): documento "conferindo" há mais de 5 minutos aparece como não conferido, libera o "Conferir de novo" e não trava o finalizar para sempre. Teste na Task 8.
3. Troca de escolhas no rascunho (categoria, cota ou cadastro) deixa documentos fora da lista: os registros e os arquivos saem; um processo de conferência antigo não pode regravar o documento apagado nem o substituído (token `execucao`). Testes nas Tasks 6 e 8.
4. Outra pessoa (outro responsável do CNPJ, ou qualquer usuário) tentando abrir, baixar arquivo, anexar ou finalizar a inscrição alheia por URL: 403/404, nunca o arquivo. Teste na Task 6 e na Task 7.
5. Fim do período no meio do uso: depois do fim da chamada, criar, editar, anexar, finalizar e reabrir são recusados com aviso; a inscrição finalizada continua finalizada. Teste na Task 9.

---

### Task 1: Esquema, enums e modelos

**Files:**
- Create: `app/Enums/ChecagemDocumento.php`, `app/Enums/SituacaoInscricao.php`, `app/Enums/SituacaoConferencia.php`
- Create: migrations `add_checagem_to_edital_documentos_table`, `create_inscricoes_table`, `create_inscricao_documentos_table` (via `php artisan make:migration`)
- Create: `app/Models/Inscricao.php`, `app/Models/InscricaoDocumento.php`, factories `InscricaoFactory`, `InscricaoDocumentoFactory`
- Modify: `app/Models/EditalDocumento.php` (fillable + casts), `app/Enums/NaturezaProponente.php`
- Test: `tests/Feature/Inscricao/ModeloDaInscricaoTest.php`

**Interfaces — Produces:**
- `enum ChecagemDocumento: string { Rigorosa='rigorosa'; AceitaSemValidar='aceita_sem_validar'; Nenhuma='nenhuma'; rotulo(): string }` — rótulos `Rigorosa`, `Aceita sem validar`, `Não validar`.
- `enum SituacaoInscricao: string { Rascunho='rascunho'; Finalizada='finalizada'; rotulo() }`.
- `enum SituacaoConferencia: string { Enviado='enviado'; Conferindo='conferindo'; Aprovado='aprovado'; Reprovado='reprovado'; NaoConferido='nao_conferido'; rotulo() }`.
- `edital_documentos.checagem` string(20) not null default `aceita_sem_validar`; `edital_documentos.instrucao_ia` string(2000) nullable. `EditalDocumento` casta `checagem` para o enum.
- `inscricoes`: `edital_id` (fk editais), `edital_chamada_id` (fk nullable, nullOnDelete), `user_id` (fk users), `organizacao_id` (fk nullable), `documento_cadastro` string(14) (CPF ou CNPJ só dígitos), `natureza` string(20), `edital_categoria_id` (fk nullable, nullOnDelete), `cotas` string(100) nullable (JSON lista de `ModalidadeCota`), `situacao` string(20), `numero` string(20) nullable unique, `finalizada_em` datetime nullable, timestamps; índice (`edital_id`, `documento_cadastro`).
- `inscricao_documentos`: `inscricao_id` (fk cascade), `edital_documento_id` (fk cascade), `arquivo` string(255), `arquivo_nome` string(255), `situacao` string(20), `motivo` string(1000) nullable, `erro` string(1000) nullable, `provedor` string(20) nullable, `modelo` string(100) nullable, `execucao` string(36) nullable, `iniciada_em` datetime nullable, `conferido_em` datetime nullable, timestamps; unique (`inscricao_id`, `edital_documento_id`).
- `Inscricao`: casts (`natureza` → `NaturezaProponente`, `cotas` → `AsEnumCollection::of(ModalidadeCota::class)`, `situacao` → enum, `finalizada_em` datetime); relações `edital()`, `chamada()` (BelongsTo `EditalChamada`, `edital_chamada_id`), `user()`, `organizacao()`, `categoria()` (BelongsTo `EditalCategoria`, `edital_categoria_id`), `documentos(): HasMany<InscricaoDocumento>`; constantes `DISCO = 'local'`, `PASTA = 'inscricoes'`.
- `InscricaoDocumento`: casts (`situacao`, `provedor` → `ProvedorIa`, datas); `inscricao()`, `documento()` (BelongsTo `EditalDocumento`, `edital_documento_id`); `const MINUTOS_LIMITE = 5`; `situacaoExibida(?CarbonInterface $agora = null): SituacaoConferencia` (Conferindo há mais de 5 min → NaoConferido); `scopeDaExecucao(Builder, string $execucao)` (execucao igual e situação Conferindo).
- `NaturezaProponente::daOrganizacao(Organizacao $organizacao): self` — `mei` → `Mei`; `finalidade_lucrativa` → `ComFins`; senão `SemFins`.

- [ ] **Step 1: Testes** em `ModeloDaInscricaoTest`:
  - `test_documento_existente_e_novo_comecam_aceitando_sem_validar` — `EditalDocumento::factory()->create()->fresh()->checagem === ChecagemDocumento::AceitaSemValidar`.
  - `test_conferencia_parada_ha_mais_de_cinco_minutos_aparece_como_nao_conferida` — Conferindo com `iniciada_em` = agora − 6 min → `NaoConferido`; − 4 min → `Conferindo`.
  - `test_natureza_da_organizacao` — mei → Mei; com fins → ComFins; sem fins → SemFins.
  - `test_cotas_da_inscricao_voltam_como_enums` — gravar `[negros, pcd]`, ler coleção de `ModalidadeCota`.
- [ ] **Step 2:** Rodar, ver falhar. **Step 3:** Implementar. **Step 4:** Rodar `tests/Feature/Inscricao` e `tests/Feature/Retaguarda/EditaisTest.php`; passar. **Step 5:** Pint e commit `feat: tabelas da inscrição e modo de checagem do documento`.

### Task 2: Retaguarda — modo de checagem e instrução no documento do edital

**Files:**
- Modify: `app/Http/Requests/Retaguarda/SalvarEditalRequest.php` (regras de `documentos.*.checagem` e `documentos.*.instrucao_ia`), `app/Support/Edital/SincronizadorDoEdital.php` (grava os dois campos), `app/Http/Controllers/Retaguarda/EditalController.php` (props do `edit`), `resources/js/types/index.ts`, `resources/js/Components/Retaguarda/Edital/DocumentosEdital.vue`
- Test: `tests/Feature/Retaguarda/EditaisTest.php`

**Interfaces:** `documentos.*.checagem`: `required`, `Rule::enum(ChecagemDocumento::class)`; `documentos.*.instrucao_ia`: `nullable|string|max:2000` + `TamanhoEmBytes(2000)`. Props do documento no `edit` ganham `checagem` (valor) e `instrucao_ia`. Documento novo no formulário começa em `aceita_sem_validar`. A importação (`EditalDaResposta`) não muda: documentos importados ficam no padrão da coluna.

- [ ] **Step 1: Testes** `test_salva_modo_de_checagem_e_instrucao_do_documento` (salva `rigorosa` + texto, `edit` devolve os dois), `test_modo_de_checagem_invalido_e_recusado`, `test_instrucao_acima_de_2000_bytes_e_recusada` (1100 × "ç").
- [ ] **Step 2–4:** falhar, implementar, passar. Na tela: em cada cartão de documento, um `select` "Checagem pela IA" (as três opções com rótulo do enum) e uma área de texto "Instrução para a IA" com ajuda "O que a IA deve conferir neste documento. Não aparece para o proponente." (os botões de gerar entram na Task 4).
- [ ] **Step 5:** Pint, `npm run build`, commit `feat: modo de checagem e instrução para a IA nos documentos do edital`.

### Task 3: Análise por IA reaproveitável (sem arquivo e com conferência da resposta)

**Files:**
- Modify: `app/Support/EditalIa/AnalisadorDeEdital.php`, `AnalisadorPorHttp.php`, `AnalisadorOpenAi.php`, `AnalisadorGemini.php`, `AnaliseComRedundancia.php`, `app/Console/Commands/AnalisarEditalCommand.php`
- Test: `tests/Feature/EditalIa/AnaliseComRedundanciaTest.php`, novos casos em um teste dos analisadores (`tests/Feature/EditalIa/AnalisadoresTest.php` se não existir equivalente)

**Interfaces — Produces:**
- `AnalisadorDeEdital::analisar(string $modelo, string $chave, ?string $pdfBinario, string $prompt, float $temperatura, array $esquema): array` — com `$pdfBinario === null` o corpo não leva a parte do arquivo (OpenAI: só `input_text`; Gemini: só `text`). O nome do arquivo enviado à OpenAI passa de `edital.pdf` para `arquivo.pdf`, que serve aos dois usos.
- `AnaliseComRedundancia::analisar(array $provedores, ?string $pdfBinario, string $prompt, float $temperatura, array $esquema, ?callable $conferirResposta = null): array` — `$conferirResposta(array $dados): void` lança `RespostaInvalida` para trocar de provedor; nulo = sem conferência. `AnalisarEditalCommand` passa `EditalDaResposta::conferirRaiz(...)`.
- Mensagens de tempo esgotado em `AnalisadorPorHttp::chamar` deixam de citar "o edital": `"{$nome} demorou demais para responder e a chamada foi interrompida. Tente de novo."` (ajustar os testes que conferem o texto antigo).

- [ ] **Step 1: Testes** `test_sem_pdf_o_pedido_da_openai_so_leva_o_texto` e `test_sem_pdf_o_pedido_do_gemini_so_leva_o_texto` (`Http::assertSent` inspecionando o corpo), `test_conferencia_da_resposta_que_recusa_passa_para_a_redundancia` (closure que lança `RespostaInvalida` no principal), `test_sem_conferencia_aceita_qualquer_json`.
- [ ] **Step 2–4:** falhar, implementar, rodar `tests/Feature/EditalIa` e `tests/Feature/Retaguarda/ImportacaoEditalTest.php`; tudo passa.
- [ ] **Step 5:** Pint, commit `refactor: análise por IA sem arquivo e com conferência da resposta parametrizada`.

### Task 4: Gerar instruções com IA (um documento ou todos)

**Files:**
- Create: `app/Support/DocumentoIa/GeradorDeInstrucoes.php`, `app/Http/Controllers/Retaguarda/InstrucaoDocumentoController.php`, `app/Http/Requests/Retaguarda/GerarInstrucoesRequest.php`
- Modify: `routes/retaguarda.php`, `resources/js/Components/Retaguarda/Edital/DocumentosEdital.vue`, `resources/js/Pages/Retaguarda/Editais/Form.vue` (contexto do edital para o componente)
- Test: `tests/Feature/Retaguarda/InstrucoesDocumentoTest.php`

**Interfaces:**
- Rota `POST /retaguarda/editais/instrucoes-ia` → `retaguarda.editais.instrucoes-ia`, `throttle:10,1`, responde JSON. Funciona com edital ainda não salvo: o corpo traz o contexto do formulário.
- Corpo: `edital` {`titulo`, `objeto`, `naturezas`: list, `categorias`: list<string>} e `documentos`: list<{`nome`, `orientacao`?, `etapa`, `anexo_referencia`?}> (1 a 60).
- Resposta 200: `{"instrucoes": list<string>}` na mesma ordem; 422 com `{"erro": AnaliseComRedundancia::INDISPONIVEL}` quando a IA falha (sem detalhe técnico).
- `GeradorDeInstrucoes::gerar(array $edital, array $documentos): list<string>` — sem PDF, temperatura 0.2, esquema `{instrucoes: ARRAY de STRING}`, lotes de 15 documentos por chamada; conferência da resposta = mesma quantidade de instruções do lote (senão `RespostaInvalida`). Cada instrução cortada em 2000 bytes (`mb_strcut`). Prompt fixo no código: a instrução diz à IA que vai conferir o PDF enviado pelo proponente o que verificar (tipo de documento, titularidade, legibilidade, validade/datas quando o documento tiver, assinatura quando exigida), em português, objetiva, até 600 caracteres, sem inventar exigência que o nome, a orientação ou o edital não indiquem.
- Tela: botão "Gerar com IA" no cartão (sobrescreve a instrução daquele documento) e "Gerar instruções de todos" no topo da aba (só preenche as vazias). Estado de carregamento e mensagem de erro no próprio componente; nada é gravado até salvar o edital.

- [ ] **Step 1: Testes** `test_gera_instrucoes_na_ordem_dos_documentos`, `test_divide_em_lotes_de_quinze` (20 documentos → 2 pedidos, `Http::assertSentCount(2)`), `test_falha_da_ia_responde_mensagem_sem_detalhe`, `test_resposta_com_quantidade_errada_passa_para_a_redundancia`, `test_quem_nao_e_gestor_nao_gera`.
- [ ] **Step 2–4:** falhar, implementar, passar. **Step 5:** Pint, `npm run build`, commit `feat: gerar instruções dos documentos com IA`.

### Task 5: Regras da inscrição (cadastros, documentos exigidos, período, limite)

**Files:**
- Create: `app/Support/Inscricao/RegrasDaInscricao.php`
- Modify: `app/Models/Inscricao.php`
- Test: `tests/Feature/Inscricao/RegrasDaInscricaoTest.php`

**Interfaces — Produces:**
- `RegrasDaInscricao::cadastros(User $user, Edital $edital): list<array{tipo: 'pessoa_fisica'|'organizacao', id: ?int, nome: string, documento: string, natureza: NaturezaProponente, aceito: bool}>` — a pessoa física do usuário e as organizações em que ele é responsável (`Organizacao::daPessoa` + vínculo `responsavel`); `aceito` = natureza nas `naturezas` do edital (nulas = todas).
- `RegrasDaInscricao::categorias(Edital $edital, NaturezaProponente $natureza): Collection<EditalCategoria>` — categorias cujas `naturezas` são nulas ou contêm a natureza.
- `RegrasDaInscricao::cotasOferecidas(Edital $edital): list<ModalidadeCota>` — `cotas.{modalidade}.ativa === true`.
- `RegrasDaInscricao::chamada(Edital $edital, ?EditalCategoria $categoria): ?EditalChamada` — a da categoria; sem ela, a única do edital; senão null.
- `Inscricao::documentosExigidos(): Collection<EditalDocumento>` — do edital, por `ordem`: `naturezas` nula ou contém a natureza; `todas_categorias` ou ligado à categoria; `modalidade_cota` nula ou nas cotas marcadas. Todas as etapas.
- `Inscricao::noPeriodo(?CarbonInterface $agora = null): bool` — edital `estaVisivel()` e `chamada?->situacao($agora) === SituacaoEdital::Aberto`.
- `RegrasDaInscricao::limiteAtingido(Edital $edital, ?EditalChamada $chamada, string $documentoCadastro, ?int $ignorar = null): bool` — conta inscrições (rascunho + finalizada) do CPF/CNPJ no edital, ou na chamada quando `limite_por_chamada`; `inscricoes_por_proponente` nulo = sem limite.
- Validação de cotas: só as oferecidas; com `cotas.veda_negro_indigena`, negros e indígenas juntos são recusados com `Escolha só uma entre as cotas para pessoas negras e indígenas.`

- [ ] **Step 1: Testes** cobrindo: organização sem vínculo de responsável fica fora; natureza não aceita vem com `aceito=false`; filtro de documentos (natureza, categoria via pivot, cota marcada e não marcada, as três etapas entram); `limiteAtingido` por edital e por chamada; `noPeriodo` antes, durante e depois da chamada; cota vedada.
- [ ] **Step 2–4:** falhar, implementar, passar. **Step 5:** Pint, commit `feat: regras da inscrição no edital`.

### Task 6: Portal — criar a inscrição e editar as escolhas

**Files:**
- Create: `app/Http/Controllers/InscricaoController.php`, `app/Http/Requests/SalvarInscricaoRequest.php`
- Modify: `app/Http/Controllers/PortalController.php` (`inscricao` passa a montar a página da inscrição no edital), `routes/web.php`, `resources/js/types/index.ts`
- Create: `resources/js/Pages/Portal/InscricaoDocumentos.vue` (rascunho de escolhas + lista; documentos completos na Task 10)
- Modify: `resources/js/Pages/Portal/Inscricao.vue`
- Test: `tests/Feature/Portal/InscricaoTest.php`

**Interfaces:**
- Rotas no grupo do portal do proponente (`auth`, `nao-gestor`, `cadastro-completo`, `inertia.encrypt`, `tipo-cadastro:proponente`):
  - `GET /portal/editais/{edital:slug}/inscricao` (`portal.editais.inscricao`, já existe): props `edital` {slug, titulo, categoria, periodo, pdf}, `inscricoes` (do usuário neste edital: id, numero, situacao, cadastro, categoria), `cadastros` (de `RegrasDaInscricao::cadastros` + `inscricaoDeOutro`: situação da inscrição do mesmo CPF/CNPJ feita por outro usuário, ou null), `categorias` (id, nome, naturezas), `cotas` (oferecidas: valor, rótulo), `vedaNegroIndigena`, `limiteAtingido` por cadastro.
  - `POST /portal/editais/{edital:slug}/inscricoes` (`portal.inscricoes.store`) → cria rascunho, redireciona para `portal.inscricoes.show`.
  - `GET /portal/inscricoes/{inscricao}` (`portal.inscricoes.show`, `whereNumber`) → `Portal/InscricaoDocumentos`.
  - `PUT /portal/inscricoes/{inscricao}` (`portal.inscricoes.update`) → troca escolhas no rascunho; apaga `InscricaoDocumento` (e arquivos, via `ArquivoArmazenado`, depois do commit) que deixaram de estar em `documentosExigidos()`.
- `SalvarInscricaoRequest`: `cadastro` = `pessoa_fisica` ou `organizacao:{id}` entre os `aceito`; `categoria` obrigatória se o edital tiver categorias para a natureza, entre elas; `cotas` lista opcional das oferecidas, regra da vedação. Erros: período fechado → redirect para `portal.editais` com `aviso` = `PortalController::AVISO_INSCRICOES_FECHADAS`; limite → erro em `cadastro`: `Este cadastro já atingiu o limite de inscrições deste edital.`
- Acesso: só o `user_id` dono abre, edita e mexe (senão 404). Mutação só em rascunho e `noPeriodo()`; finalizada pede reabrir antes (Task 9).

- [ ] **Step 1: Testes** `test_cria_rascunho_com_as_escolhas`, `test_cadastro_de_natureza_nao_aceita_e_recusado`, `test_organizacao_sem_ser_responsavel_e_recusada`, `test_limite_por_cnpj_conta_inscricao_de_outro_responsavel`, `test_outro_responsavel_ve_a_situacao_da_inscricao_do_cnpj_sem_abrir`, `test_trocar_categoria_descarta_documentos_e_arquivos_fora_da_lista` (Storage::fake('local')), `test_inscricao_de_outro_usuario_da_404`, `test_fora_do_periodo_nao_cria_nem_edita`.
- [ ] **Step 2–4:** falhar, implementar, passar. **Step 5:** Pint, `npm run build`, commit `feat: inscrição no edital com cadastro, categoria e cotas`.

### Task 7: Portal — anexar, remover e baixar documentos

**Files:**
- Create: `app/Http/Controllers/InscricaoDocumentoController.php`, `app/Support/DocumentoIa/LancadorDeConferencia.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Portal/InscricaoDocumentosTest.php`

**Interfaces:**
- `POST /portal/inscricoes/{inscricao}/documentos/{documento}` (`portal.inscricoes.documentos.store`, `throttle:30,1`): `arquivo` `required|file|mimes:pdf|mimetypes:application/pdf|max:10240` (mensagem `Envie um PDF de até 10 MB.`). `{documento}` é `edital_documentos.id` e precisa estar em `documentosExigidos()` (senão 404). Grava em `inscricoes/{id}`, substitui o anterior (apaga o arquivo antigo depois de salvar, `ArquivoArmazenado::salvarTrocando`). Checagem `Nenhuma` → situação `Enviado`; senão `Conferindo`, nova `execucao` (uuid), `iniciada_em = now()`, limpa `motivo`/`erro`, e chama `LancadorDeConferencia::lancar($inscricaoDocumento)` depois do commit.
- `DELETE` mesma URL (`...destroy`): só documento não obrigatório; apaga registro e arquivo.
- `GET /portal/inscricoes/{inscricao}/documentos/{documento}/arquivo` (`...arquivo`): download do arquivo para o dono, `Content-Disposition` com `arquivo_nome`.
- `LancadorDeConferencia::lancar(InscricaoDocumento $documento): void` — igual a `LancadorDeAnalise`, rodando `inscricoes:conferir {id} {execucao}`; resolvido pelo container (os testes trocam por um que roda o comando na hora ou que só registra).
- Tudo só para o dono, em rascunho e no período.

- [ ] **Step 1: Testes** `test_anexa_pdf_e_lanca_a_conferencia` (lançador falso registrando), `test_documento_sem_checagem_fica_enviado_sem_lancar`, `test_anexar_de_novo_substitui_o_arquivo_e_reconfere`, `test_arquivo_que_nao_e_pdf_ou_acima_de_10mb_e_recusado`, `test_documento_fora_da_lista_da_404`, `test_obrigatorio_nao_pode_ser_removido_e_opcional_pode`, `test_outro_usuario_nao_baixa_o_arquivo`.
- [ ] **Step 2–4:** falhar, implementar, passar. **Step 5:** Pint, commit `feat: envio dos documentos da inscrição`.

### Task 8: Conferência do documento pela IA

**Files:**
- Create: `app/Console/Commands/ConferirDocumentoCommand.php`, `app/Support/DocumentoIa/PromptDaConferencia.php`
- Modify: `app/Http/Controllers/InscricaoDocumentoController.php` (conferir de novo), `app/Http/Controllers/InscricaoController.php` (situação JSON), `routes/web.php`
- Test: `tests/Feature/Inscricao/ConferenciaDocumentoTest.php`

**Interfaces:**
- Comando `inscricoes:conferir {documento} {execucao?}` (`#[Signature]`/`#[Description]` como `AnalisarEditalCommand`; `set_time_limit` 300, memória 256M). Provedores de `ConfiguracaoIa::atual()->provedoresDaImportacao()`; sem provedores → não conferido. Lê o PDF do disco; chama `AnaliseComRedundancia::analisar($provedores, $pdf, PromptDaConferencia::montar($documento), 0.1, PromptDaConferencia::esquema(), PromptDaConferencia::conferir(...))`.
- `PromptDaConferencia::esquema()`: `{aprovado: BOOLEAN, motivo: STRING}`, os dois obrigatórios; `conferir(array $dados): void` exige `aprovado` booleano e `motivo` string.
- `PromptDaConferencia::montar(InscricaoDocumento $documento): string` — diz que o PDF foi enviado por um proponente para o documento X do edital Y; dá a instrução (`instrucao_ia`, ou, vazia, o nome e a orientação), o nome/razão social, CPF/CNPJ formatado e a natureza da inscrição; pede reprovar só quando o arquivo claramente não é o documento, é de outra pessoa/CNPJ, está ilegível ou vencido quando a validade for visível; `motivo` em português, uma ou duas frases, dirigido ao proponente, sem dado pessoal além do necessário.
- Gravação com a linha travada e `daExecucao`: aprovado → `Aprovado`, `motivo` null; reprovado → `Reprovado`, `motivo` (cortado em 1000 bytes); `FalhaNaIa`/`RespostaInvalida`/erro → `NaoConferido`, `motivo` = `self::NAO_CONFERIDO`, `erro` = mensagem (sem chave, cortada). Sempre `provedor`, `modelo`, `conferido_em`. Execução que mudou → nada gravado.
- `POST /portal/inscricoes/{inscricao}/documentos/{documento}/conferir` (`...conferir`, `throttle:10,1`): em `Reprovado` ou `NaoConferido` (inclusive o exibido por tempo), nova execução e lança; outras situações → aviso.
- `GET /portal/inscricoes/{inscricao}/situacao` (`portal.inscricoes.situacao`, JSON): `{documentos: list<{id: int (edital_documento_id), situacao: string (situacaoExibida), motivo: ?string}>, podeFinalizar: bool}`.

- [ ] **Step 1: Testes** (com `Http::fake` da OpenAI e lançador que roda o comando na hora): `test_aprovado`, `test_reprovado_grava_o_motivo`, `test_ia_fora_do_ar_fica_nao_conferido_sem_detalhe_para_o_proponente`, `test_sem_configuracao_de_ia_fica_nao_conferido`, `test_execucao_antiga_nao_regrava_o_documento_substituido`, `test_documento_apagado_durante_a_conferencia_nao_quebra`, `test_conferir_de_novo_so_em_reprovado_ou_nao_conferido`, `test_conferindo_parado_libera_conferir_de_novo`, `test_situacao_em_json_so_para_o_dono`, `test_chave_da_api_nao_aparece_no_erro_gravado`.
- [ ] **Step 2–4:** falhar, implementar, passar. **Step 5:** Pint, commit `feat: conferência dos documentos da inscrição pela IA`.

### Task 9: Finalizar, reabrir e situação na lista de editais

**Files:**
- Modify: `app/Http/Controllers/InscricaoController.php`, `app/Models/Inscricao.php`, `app/Http/Controllers/PortalController.php` (`editais`), `routes/web.php`, `resources/js/types/index.ts`, `resources/js/Pages/Portal/Editais.vue`
- Test: `tests/Feature/Portal/FinalizarInscricaoTest.php`

**Interfaces:**
- `Inscricao::pendenciasParaFinalizar(?CarbonInterface $agora = null): list<string>` — para cada exigido: obrigatório sem arquivo → `Anexe "{nome}".`; conferindo (exibido) → `Aguarde a conferência de "{nome}".`; `Rigorosa` sem `Aprovado` → `"{nome}" precisa ser aprovado na conferência.` Opcional anexado em `Rigorosa` também precisa de aprovação.
- `POST /portal/inscricoes/{inscricao}/finalizar` (`portal.inscricoes.finalizar`): em transação com a inscrição travada, no período e sem pendências → `Finalizada`, `finalizada_em = now()`, `numero` gerado na primeira vez = ano + id com 6 dígitos (`2026000123`) e mantido nas seguintes. Com pendências → volta com `erro` e a lista.
- `POST /portal/inscricoes/{inscricao}/reabrir` (`portal.inscricoes.reabrir`): só finalizada e no período → `Rascunho` (número e documentos ficam).
- `PortalController::editais`: cada edital ganha `minhaInscricao`: `'finalizada' | 'rascunho' | null` (finalizada se alguma das do usuário estiver finalizada).

- [ ] **Step 1: Testes** `test_finaliza_com_todos_os_obrigatorios`, `test_nao_finaliza_com_obrigatorio_faltando`, `test_nao_finaliza_com_documento_conferindo`, `test_rigoroso_reprovado_ou_nao_conferido_impede_finalizar`, `test_aceita_sem_validar_reprovado_nao_impede`, `test_numero_e_mantido_ao_reabrir_e_finalizar_de_novo`, `test_reabrir_volta_a_rascunho`, `test_depois_do_periodo_nao_finaliza_nem_reabre_e_finalizada_continua`, `test_lista_de_editais_mostra_a_situacao_da_minha_inscricao`.
- [ ] **Step 2–4:** falhar, implementar, passar. **Step 5:** Pint, `npm run build`, commit `feat: finalizar e reabrir a inscrição`.

### Task 10: Telas da inscrição no portal

**Files:**
- Modify: `resources/js/Pages/Portal/Inscricao.vue`, `resources/js/Pages/Portal/InscricaoDocumentos.vue`, `resources/js/Pages/Portal/Editais.vue`, `resources/js/types/index.ts`
- Test: os de feature já cobrem as props; aqui `npm run build` e conferência no navegador.

**Requisitos de tela** (componentes existentes: `CampoCadastro`, `CampoArquivo`, `SecaoCadastro`, `FaixaPortal`; padrão de consulta periódica de `Pages/Retaguarda/Editais/Index.vue`):
- `Inscricao.vue`: minhas inscrições neste edital (número, situação, cadastro, categoria, link "Continuar" / "Ver"); formulário "Nova inscrição" com cadastro (desabilitado com o motivo quando não aceito, limite atingido ou inscrição de outro responsável — esta mostra "Já existe inscrição deste CNPJ: {situação}"), categoria (filtrada pela natureza escolhida) e cotas (só se houver; respeita a vedação).
- `InscricaoDocumentos.vue`: cabeçalho com edital, número, situação e período; escolhas editáveis no rascunho; documentos agrupados por etapa (Inscrição, Validação de cotas, Habilitação), cada um com obrigatório/opcional, orientação, modelo para baixar, campo de envio (PDF até 10 MB, conferido no navegador antes de enviar), arquivo atual (link de download, remover se opcional) e a situação:
  - Conferindo: "Conferindo…" com indicador; a tela consulta `portal.inscricoes.situacao` a cada 5 s enquanto houver algum.
  - Aprovado: "Conferido". Enviado (sem checagem): "Recebido".
  - Reprovado em `Rigorosa`: erro "Documento recusado: {motivo}" + "Conferir de novo"; em `AceitaSemValidar`: alerta "Atenção: {motivo}. Você pode seguir, e a equipe vai conferir."
  - Não conferido: `Rigorosa` → erro com o motivo e "Conferir de novo"; `AceitaSemValidar` → aviso "Não foi possível conferir agora; a equipe vai conferir."
- Rodapé: "Finalizar inscrição" (habilitado por `podeFinalizar`; com pendências, a lista delas). Finalizada: número, data e hora do envio e "Reabrir para editar", que abre um `<dialog>` com o aviso `Ao reabrir, sua inscrição volta a ser rascunho e só conta se você finalizar de novo até {fim do período}.`
- `Editais.vue`: selo "Inscrição finalizada" / "Inscrição em rascunho" e o botão vira "Ver minha inscrição".
- Acessível como o resto do portal: rótulos, `aria-live` na situação dos documentos, foco no primeiro erro.

- [ ] **Step 1:** Implementar as telas. **Step 2:** `npm run build` sem erros. **Step 3:** Conferir no navegador com um edital aberto de teste: criar inscrição, anexar (com IA fake fora do ar e configurada), finalizar, reabrir. **Step 4:** Commit `feat: telas da inscrição no portal`.

### Task 11: Verificação final

- [ ] Rodar a suíte completa sozinha (`docker compose exec -T app php artisan test --compact`), `npm run build` e Pint; corrigir o que falhar.
- [ ] Migrations no PostgreSQL: subir um `postgres:16` descartável (porta local, ex. 55432), rodar `php artisan migrate` pelo PHP da máquina com `DB_CONNECTION=pgsql`, `migrate:rollback --step=3`, de novo `migrate`; apagar o container. Não tocar em outros bancos.
