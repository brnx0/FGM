# Períodos do edital e importação por IA sem configuração na tela — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tirar da tela a seção 3 da Integração com IA (valores fixos no código) e dar a cada chamada do edital as fases Validação e Homologação, além da Inscrição, editáveis na aba "Períodos" e preenchidas pela importação por IA.

**Architecture:** A Inscrição continua sendo `edital_chamadas.inicio`/`fim`; Validação e Homologação viram quatro colunas nulas novas em `edital_chamadas`. O fluxo de gravação é o de hoje (formulário → `SalvarEditalRequest` → `SincronizadorDoEdital`; importação → `EditalDaResposta` → `SincronizadorDoEdital`). A seção 3 sai apagando a migration que criou as colunas dela.

**Tech Stack:** Laravel 13, PHPUnit, Inertia v2, Vue 3 `lang="ts"` (vue-tsc no build), Tailwind v4, Oracle (e compatível com PostgreSQL).

**Spec:** `docs/superpowers/specs/2026-10-29-edital-periodos-e-importacao-interna-design.md`

## Global Constraints

- PHP roda no container: `docker compose exec -T app php artisan ...`; Pint: `docker compose exec -T app vendor/bin/pint --dirty --format agent`.
- O container monta `E:\Dev\Mapa_Cultura`: a execução acontece nessa pasta, com a branch `feat/edital-periodos` em checkout (a pasta é compartilhada com outra sessão; confirmar com o usuário que ela está livre antes de trocar de branch).
- A suíte completa roda sozinha (banco de testes Oracle compartilhado), nunca duas ao mesmo tempo.
- Nunca rodar Prettier. Sem dependências novas. Nada de `migrate:fresh`/`migrate:rollback` no banco de dev sem o usuário pedir.
- Migrations novas portáveis (Oracle e PostgreSQL): só Schema builder, sem SQL de dicionário do Oracle.
- Oracle: `''` vira NULL, ids voltam como string (comparar com `(int)`).
- `npm run build` (vue-tsc) precisa passar.
- Textos de tela e mensagens em português, no tom das telas vizinhas.
- Valores fixos da importação: temperatura `0.1`, PDF até `14` MB (`ConfiguracaoIa::TAMANHO_MAXIMO_PDF_MB`).

## Review Focus

1. Edital salvo antes desta mudança (chamadas sem as colunas novas preenchidas) abre e salva sem erro; as fases novas vêm vazias.
2. Importação antiga, com resposta guardada sem os campos novos, ainda é aplicada sem erro (fases vazias).
3. Validação ou Homologação com só uma das datas é recusada no formulário, com o erro na aba Períodos.
4. Publicar um edital sem Validação e Homologação continua permitido; sem Inscrição continua bloqueado.
5. Banco que já rodou a migration apagada (colunas `importacao_*` sobrando) continua salvando a Integração com IA.

---

### Task 1: Seção 3 da Integração com IA sai da tela

**Files:**
- Delete: `database/migrations/2026_10_28_204911_add_importacao_to_configuracoes_ia_table.php`
- Delete: `app/Http/Requests/Retaguarda/SalvarImportacaoIaRequest.php`
- Modify: `app/Models/ConfiguracaoIa.php` (fillable, `$attributes`, casts, docblock, métodos da importação)
- Modify: `app/Http/Controllers/Retaguarda/IntegracaoIaController.php` (prop `importacao`, métodos `importacao` e `restaurarPrompt`)
- Modify: `routes/retaguarda.php:114-115` (as duas rotas da seção)
- Modify: `resources/js/Pages/Retaguarda/IntegracaoIa.vue` (seção 3 e o que só ela usa) e os tipos dela em `resources/js/types/index.ts`, se houver
- Modify: `app/Console/Commands/AnalisarEditalCommand.php:91-92`, `app/Http/Requests/Retaguarda/ImportarEditalRequest.php:43-45`, `app/Http/Controllers/Retaguarda/EditalController.php:94` (só se a assinatura mudar)
- Test: `tests/Feature/Retaguarda/IntegracaoIaTest.php`, `tests/Feature/Retaguarda/ImportacaoEditalTest.php`, `tests/Feature/Retaguarda/EditaisTest.php`

**Interfaces:**
- Produces: `ConfiguracaoIa::TEMPERATURA_DA_IMPORTACAO = 0.1` (const float) e os métodos `promptDaImportacao(): string` (sempre `PromptModelo::editavel()`), `temperaturaDaImportacao(): float`, `tamanhoMaximoPdfMb(): int` (sempre `TAMANHO_MAXIMO_PDF_MB`). Manter esses nomes evita mexer em quem os chama.

- [ ] **Step 1: Ajustar os testes**
  - Em `IntegracaoIaTest`: apagar os testes da seção 3 (linhas ~354-486: `importacao()`, `test_tela_traz_a_importacao_...`, `test_importacao_configurada_...`, `test_salva_a_importacao_...` (2), `test_prompt_igual_ao_modelo_...`, `importacaoInvalida` + `test_recusa_importacao_invalida_...`, `test_restaura_o_prompt_modelo`, `test_proponente_nao_altera_a_importacao`) e a const `URL_IMPORTACAO`. Acrescentar:
    ```php
    public function test_tela_nao_traz_mais_a_importacao(): void
    {
        // gestor logado como nos testes vizinhos
        $this->get('/retaguarda/integracao-ia')->assertInertia(fn (Assert $page) => $page->missing('importacao'));
    }

    public function test_rotas_da_importacao_nao_existem_mais(): void
    {
        // gestor logado
        $this->put('/retaguarda/integracao-ia/importacao', [])->assertNotFound();
        $this->put('/retaguarda/integracao-ia/importacao/prompt-modelo')->assertNotFound();
    }
    ```
    (`assertNotFound` ou `assertMethodNotAllowed`, conforme o que a rota vizinha `PUT /retaguarda/integracao-ia` provocar; escolher o que o Laravel devolver de fato e documentar no teste.)
  - Em `ImportacaoEditalTest`: o `setUp` (linha ~72) e o teste da linha ~439 deixam de gravar `importacao_tamanho_maximo_mb`. O teste do tamanho máximo passa a enviar um PDF de 15 MB (`UploadedFile::fake()->create('edital.pdf', 15 * 1024, 'application/pdf')`) e espera o erro `O PDF deve ter no máximo 14 MB.`. O teste da linha ~204 (prompt e temperatura da equipe) passa a afirmar que o prompt enviado contém `PromptModelo::fixo()` e `PromptModelo::editavel()` e que a temperatura é `0.1`.
  - Em `EditaisTest` (linha ~316): o teste que grava `importacao_tamanho_maximo_mb => 20` passa a só afirmar `tamanhoMaximoPdfMb === 14`, sem gravar nada.

- [ ] **Step 2: Rodar e ver falhar**
  Run: `docker compose exec -T app php artisan test --compact tests/Feature/Retaguarda/IntegracaoIaTest.php tests/Feature/Retaguarda/ImportacaoEditalTest.php tests/Feature/Retaguarda/EditaisTest.php`
  Expected: FAIL nos dois testes novos de `IntegracaoIaTest` (a prop e as rotas ainda existem).

- [ ] **Step 3: Implementar**
  Apagar a migration e o request; tirar as colunas de `ConfiguracaoIa` (fillable, `$attributes`, casts) e reescrever o docblock da classe; os três métodos devolvem os valores fixos; tirar do controller a prop `importacao`, os dois métodos e o `use` de `PromptModelo`/`SalvarImportacaoIaRequest`; tirar as duas rotas; tirar a seção 3 da página Vue e as props/tipos que só ela usava. Atualizar o docblock de `PromptModelo` (deixa de dizer que a equipe muda o prompt na tela).

- [ ] **Step 4: Rodar e ver passar**
  Run: o mesmo comando do Step 2, mais `npm run build`.
  Expected: PASS; build sem erro.

- [ ] **Step 5: Commit**
  `git add -A app database routes resources tests && git commit -m "refactor: parâmetros da importação por IA fixos no código, sem a seção 3 na tela"`

---

### Task 2: Fases Validação e Homologação na chamada (banco e gravação)

**Files:**
- Create: migration `add_fases_to_edital_chamadas_table` (`php artisan make:migration add_fases_to_edital_chamadas_table --table=edital_chamadas --no-interaction`)
- Modify: `app/Models/EditalChamada.php` (fillable, casts, setters de dia civil, docblock)
- Modify: `database/factories/EditalChamadaFactory.php` (só se precisar de estado com fases)
- Modify: `app/Http/Requests/Retaguarda/SalvarEditalRequest.php:72-76`, mensagens (~212) e atributos (~232-235)
- Modify: `app/Support/Edital/SincronizadorDoEdital.php:32` (docblock) e `:62-68`
- Modify: `app/Http/Controllers/Retaguarda/EditalController.php:168-173` (props de edição)
- Test: `tests/Feature/Retaguarda/EditalEstruturaSalvarTest.php`

**Interfaces:**
- Produces: colunas `validacao_inicio`, `validacao_fim`, `homologacao_inicio`, `homologacao_fim` (date, nullable) em `edital_chamadas`; chaves de mesmo nome em cada item de `chamadas` no request, no sincronizador e nas props de edição (`string|null`, `Y-m-d`).

- [ ] **Step 1: Testes que falham** (em `EditalEstruturaSalvarTest`, seguindo os helpers do arquivo)
  - `test_salva_e_reabre_as_fases_de_cada_chamada`: envia uma chamada com `validacao_inicio 2026-12-01`, `validacao_fim 2026-12-10`, `homologacao_inicio 2026-12-15`, `homologacao_fim 2026-12-20`; afirma as quatro datas no banco e nas props de edição (`edital.chamadas.0.validacao_inicio` = `'2026-12-01'` etc.).
  - `test_fases_sem_datas_ficam_vazias`: chamada só com a Inscrição; as quatro colunas ficam `null`.
  - `test_fase_com_so_uma_data_e_recusada` (DataProvider com os 4 casos: só `validacao_inicio`, só `validacao_fim`, só `homologacao_inicio`, só `homologacao_fim`): erro na chave da data que falta (ex.: `chamadas.0.validacao_fim`) com a mensagem `Preencha também o fim da validação.` / `Preencha também o início da validação.` (idem "homologação").
  - `test_fim_da_fase_antes_do_inicio_e_recusado`: `validacao_fim` antes de `validacao_inicio` → erro `chamadas.0.validacao_fim` com `O fim deve ser igual ou posterior ao início.` (mesma mensagem da Inscrição).
  - `test_publica_sem_validacao_e_homologacao`: edital com Inscrição completa e fases vazias publica sem pendência.

- [ ] **Step 2: Rodar e ver falhar**
  Run: `docker compose exec -T app php artisan test --compact tests/Feature/Retaguarda/EditalEstruturaSalvarTest.php`
  Expected: FAIL nos testes novos.

- [ ] **Step 3: Implementar**
  Migration com as quatro colunas `date()->nullable()` e `down` com `dropColumn`. Model: fillable, casts `date`, setters `Attribute::set(fn ($v) => self::diaParaGravar($v))` como os de `inicio`/`fim`; docblock: "a chamada tem a Inscrição (`inicio`/`fim`), a Validação e a Homologação". Request: `nullable|date` + `required_with:` a outra data da fase + `after_or_equal:` o início da fase, com mensagens e atributos (`início da validação`, `fim da validação`, `início da homologação`, `fim da homologação`). Sincronizador: grava as quatro chaves (`?? null`). Controller: devolve as quatro como `toDateString()` ou `null`. `pendenciasParaPublicar()` não muda.

- [ ] **Step 4: Rodar e ver passar**
  Run: o comando do Step 2 e `docker compose exec -T app php artisan test --compact tests/Feature/EditalEstruturaTest.php`
  Expected: PASS.

- [ ] **Step 5: Commit**
  `git commit -m "feat: fases de validação e homologação em cada chamada do edital"`

---

### Task 3: Aba Períodos no formulário do edital

**Files:**
- Modify: `resources/js/types/index.ts:515-520` (`ChamadaFormulario`) e `:589-594` (`ChamadaEditavel`)
- Modify: `resources/js/Components/Retaguarda/Edital/ChamadasEdital.vue`
- Modify: `resources/js/Pages/Retaguarda/Editais/Form.vue` (`chamadasSalvas` ~57, `DEFINICAO_ABAS` ~204, `paraEnvio` ~343, rótulo da aba no template)

**Interfaces:**
- Consumes: as quatro chaves da Task 2 nas props e no envio.

- [ ] **Step 1: Implementar**
  - Tipos: `ChamadaFormulario` ganha as quatro como `string | null`; `ChamadaEditavel` ganha as quatro como `string` (vazio = sem data), como `inicio`/`fim`.
  - `Form.vue`: a aba `inscricao` passa a ter o rótulo `Períodos` (manter o id, para não quebrar `irParaAba` do quadro da importação); `chamadasSalvas` converte `null` em `''`; `paraEnvio` manda as quatro chaves, com `''` → `null`.
  - `ChamadasEdital.vue`: em cada chamada, depois do nome, três grupos rotulados — **Inscrição** (as datas de hoje, obrigatórias), **Validação** e **Homologação** (opcionais, `min` do fim = início, `limpar` como os de hoje). `nova()` inicia as quatro com `''`. O `resumo` continua mostrando a Inscrição. O texto de vazio continua igual.
  - Rótulos: "Início" / "Fim" dentro de cada grupo (o grupo diz a fase), com `aria` pelo `CampoCadastro` como hoje; para o grupo, `fieldset`/`legend` ou o padrão de subtítulo que o projeto já usa nos cartões de `ListaEditavel` (conferir `CategoriasEdital.vue`).

- [ ] **Step 2: Verificar**
  Run: `npm run build`
  Expected: sem erro de tipo.
  Depois, no navegador (retaguarda, editar um edital): a aba se chama Períodos; preencher e salvar Validação; reabrir mostra a data; enviar só o início da Homologação mostra o erro no campo e o contador na aba.

- [ ] **Step 3: Commit**
  `git commit -m "feat: aba Períodos com as fases de cada chamada no formulário do edital"`

---

### Task 4: Importação por IA preenche as fases

**Files:**
- Modify: `app/Support/EditalIa/EsquemaDaResposta.php:61-65`
- Modify: `app/Support/EditalIa/EditalDaResposta.php:267-281` (`chamada()`) e o docblock do topo, se citar os campos
- Modify: `app/Support/EditalIa/PromptModelo.php` (`editavel()`)
- Modify: `tests/Support/RespostaDaIa.php:28-31`
- Test: `tests/Unit/EditalDaRespostaTest.php`; `tests/Feature/EditalIa/AnalisadorOpenAiTest.php:225` deve continuar passando sem mudança

**Interfaces:**
- Consumes: as quatro chaves da Task 2 (a lista `chamadas` que o `EditalDaResposta` entrega ao `SincronizadorDoEdital`).

- [ ] **Step 1: Testes que falham**
  - Em `RespostaDaIa`, a "Chamada 1" ganha `validacao_inicio 2026-12-05`, `validacao_fim 2026-12-12`, `homologacao_inicio 2026-12-20`, `homologacao_fim 2026-12-22`; a "Chamada 2" fica sem as fases.
  - `test_fases_da_chamada_sao_gravadas`: a Chamada 1 do edital gerado tem as quatro datas; a Chamada 2 tem as quatro `null`.
  - `test_resposta_sem_os_campos_das_fases_ainda_e_lida`: remove as quatro chaves de todas as chamadas; o edital é criado, fases `null`, sem ponto de atenção sobre elas.
  - `test_fase_com_so_uma_data_fica_vazia_com_ponto_de_atencao`: Chamada 1 com só `homologacao_inicio 2026-12-20`; a fase fica vazia e há ponto de atenção do bloco `inscricao`, tipo leitura incerta (o mesmo de `avisar()`), com o texto `A chamada "Chamada 1" trouxe só uma das datas da homologação (20/12/2026); a fase ficou vazia.`
  - `test_fase_com_data_impossivel_ou_fim_antes_do_inicio_fica_vazia_com_ponto_de_atencao`: `validacao_inicio 2026-02-30` → fase vazia, ponto de atenção; a chamada **não** é descartada (diferente da Inscrição, que descarta a chamada).

- [ ] **Step 2: Rodar e ver falhar**
  Run: `docker compose exec -T app php artisan test --compact tests/Unit/EditalDaRespostaTest.php`
  Expected: FAIL nos testes novos.

- [ ] **Step 3: Implementar**
  - Esquema: as quatro chaves com `self::data(...)` e descrição curta ("Início da validação das inscrições; vazio se o edital só der prazo relativo." etc.); fora da lista de obrigatórias (só `nome` continua obrigatório; o `EsquemaOpenAi` já cuida do modo estrito).
  - `chamada()`: normaliza cada fase com `self::data()`; fase inválida (data impossível, só uma data, fim antes do início) vira `null` nas duas datas e um `avisar('inscricao', ...)`; os textos seguem o modelo do Step 1 ("trouxe só uma das datas da validação (05/12/2026)", "trouxe uma data impossível na validação", "trouxe o fim da validação antes do início"). Datas no aviso em `d/m/Y`.
  - Prompt (`editavel()`): acrescentar um item: "Em cada chamada, registre as fases de inscrição, validação e homologação com as datas de início e fim que o edital trouxer. Se uma fase só tiver prazo relativo (por exemplo, "5 dias úteis após o resultado"), deixe as datas dela vazias e registre um ponto de atenção do tipo "não encontrado", citando o item do edital."

- [ ] **Step 4: Rodar e ver passar**
  Run: `docker compose exec -T app php artisan test --compact tests/Unit/EditalDaRespostaTest.php tests/Feature/EditalIa tests/Feature/Retaguarda/ImportacaoEditalTest.php`
  Expected: PASS.

- [ ] **Step 5: Commit**
  `git commit -m "feat: importação por IA preenche as fases de cada chamada"`

---

### Task 5: Verificação final

- [ ] **Step 1:** `docker compose exec -T app vendor/bin/pint --dirty --format agent` e commit se mudar algo.
- [ ] **Step 2:** `npm run build` → sem erro.
- [ ] **Step 3:** Suíte completa, sozinha: `docker compose exec -T app php artisan test --compact` → tudo passa.
- [ ] **Step 4:** Migration nova num PostgreSQL 16 descartável (como na correção `d4a26a3`: container `postgres:16` com porta `127.0.0.1:55432`, `php artisan migrate` do PHP da máquina com `DB_CONNECTION=pgsql`; depois `migrate:rollback --step=1` e `migrate` de novo; apagar o container). Não usar o `sie_pg_homolog`.
- [ ] **Step 5:** No banco de dev, `docker compose exec -T app php artisan migrate` (só a migration nova) e conferir no navegador a tela da Integração com IA sem a seção 3 e uma importação real, se o usuário quiser.
