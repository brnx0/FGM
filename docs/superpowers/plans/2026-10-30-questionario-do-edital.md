# Questionário do edital, importado por IA, e perfil no cadastro — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** O edital ganha um questionário (seções e perguntas com vínculo a categorias/naturezas e condição por resposta) que a IA monta a partir do espelho do formulário (.docx ou PDF), o cadastro da pessoa física ganha o perfil, e a inscrição no portal vira sequencial, com uma etapa por seção do questionário.

**Architecture:** Três tabelas do questionário (`edital_questionario_secoes`, `edital_perguntas`, pivô `edital_pergunta_categoria`) gravadas pelo formulário do edital num campo JSON único (`questionario`, por causa do `max_input_vars` do multipart) e por `SincronizadorDoQuestionario`. A importação repete o molde da importação do edital (tabela própria, processo destacado com `execucao`, `AnaliseComRedundancia`, esquema + leitura da resposta), com o .docx convertido em texto no servidor e enviado sem arquivo. No portal, `QuestionarioDaInscricao` decide o que vale e o que está visível, `PassosDaInscricao` monta os passos, e as respostas ficam em `inscricao_respostas`.

**Tech Stack:** Laravel 13, PHPUnit, Inertia v2, Vue 3 `<script setup lang="ts">` (vue-tsc no `npm run build`), Tailwind v4, Oracle (yajra oci8) e PostgreSQL.

**Spec:** `docs/superpowers/specs/2026-10-30-questionario-do-edital-design.md`

## Global Constraints

- Laravel 13, Inertia v2, Vue 3 `lang="ts"` (vue-tsc no `npm run build`), Tailwind v4. Campos obrigatórios com o padrão do projeto: asterisco vermelho no rótulo (`CampoCadastro`, `obrigatorio`) e destaque no erro (`aria-invalid` nos campos; classe `campo-invalido` em caixas próprias, como grupos de opções).
- Oracle: `''` vira NULL (normalizar texto vazio para `null`), ids voltam como string (sempre `(int)`), VARCHAR2 conta bytes (todo texto livre com `App\Rules\TamanhoEmBytes`; texto gravado pela IA cortado com `mb_strcut`), CLOB fora das listagens (selecionar colunas sem CLOB em consultas repetidas). Nomes explícitos e curtos para chaves primárias compostas e índices únicos.
- Migrations portáveis Oracle e PostgreSQL: só Schema builder, sem SQL de dicionário. Nunca `migrate:fresh` no banco de dev.
- Sem dependências novas (o .docx é lido com `ZipArchive` + `DOMDocument`, já presentes no container). Sem Prettier.
- A chave da API da IA nunca sai do servidor (props, sessão, log, erro gravado, URL). Testes nunca chamam IA real: `Http::fake` + `Http::preventStrayRequests()`.
- Sim/não, declarações e opções de múltipla escolha são toggle (`role="switch"`, componente `Interruptor` da Task 1), nunca checkbox; vale na retaguarda e no portal.
- Execução neste worktree, `$W` = `C:/Users/Breno/AppData/Local/Temp/claude/E--Dev-Mapa-Cultura/e4d54214-7206-4ddb-aab8-4b4296549565/scratchpad/wt-questionario`. PHP só por container descartável; abaixo, `art` abrevia
  `MSYS_NO_PATHCONV=1 docker run --rm -v "$W:/var/www/html" -v "E:/Dev/Mapa_Cultura/vendor:/var/www/html/vendor:ro" --network mapa_cultura_default -w /var/www/html mapa_cultura-app php artisan` e `pint` abrevia o mesmo `docker run ... mapa_cultura-app vendor/bin/pint <arquivos> --format agent` (nunca `--dirty`, nunca `--test`).
- Nunca rodar `migrate` no banco de dev durante as tasks: os testes usam o banco de testes (RefreshDatabase); o banco de dev recebe as migrations só depois do merge, com o OK do usuário.
- Banco de testes Oracle compartilhado: um arquivo de teste por vez (`art test --compact <arquivo>`); a suíte completa só na Task 13, sozinha.
- `npm run build` no worktree (se faltar `node_modules`, junção para `E:/Dev/Mapa_Cultura/node_modules`).
- Commits pequenos em português (`feat:`, `test:`, `refactor:`), mensagem terminando com linha em branco e `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Sem push.

## Review Focus

1. Formulário do edital com questionário grande e um PDF anexado: o envio é multipart (`forceFormData`) e o PHP corta acima de 1000 variáveis (`max_input_vars`). O questionário vai como um único campo JSON; um edital com 200 perguntas e 50 documentos salva tudo. Teste na Task 4 (`test_questionario_grande_vai_inteiro_num_campo_json`).
2. Gestor reordena ou move a pergunta de origem para depois da dependente, ou apaga uma opção usada numa condição: o servidor recusa origem depois da dependente e opção que não é da origem; a tela limpa a condição com aviso. Testes na Task 4.
3. Corrida da trava: uma inscrição é finalizada enquanto o gestor está com o formulário aberto ou enquanto a IA analisa o espelho. Salvar com o questionário é recusado com o aviso da trava; a análise termina com erro e não substitui nada. Testes nas Tasks 4 e 8.
4. Proponente volta e troca uma resposta (ou a categoria) que esconde perguntas já respondidas mais à frente: as respostas ocultas são apagadas, os passos seguintes voltam a ser conferidos e uma obrigatória oculta não impede finalizar. Testes nas Tasks 10 e 11.
5. Texto no limite com acentos, emoji e quebras de linha `\r\n`: servidor conta caracteres (`mb_strlen`, `\r\n` vira `\n`) e a tela conta igual (`[...texto].length`). Teste na Task 10 (`test_limite_conta_caracteres_com_acento_emoji_e_quebra_de_linha`).

---

### Task 1: Componente Interruptor (toggle)

**Files:**
- Create: `resources/js/Components/Interruptor.vue`
- Modify: `resources/js/Pages/Retaguarda/IntegracaoIa.vue:134-165` (o `<label>` com `role="switch"`)

**Interfaces:**
- Produces: `Interruptor.vue` — `defineModel<boolean>({ required: true })`; props `{ id: string; rotulo: string; ajuda?: string; erro?: string; obrigatorio?: boolean; desabilitado?: boolean; variante?: 'caixa' | 'linha' }` (padrão `'caixa'`: a caixa com borda de hoje; `'linha'`: sem borda, para listas de opções); emite `change`. Renderiza `<label>` com `<input type="checkbox" role="switch" class="peer sr-only">` e o trilho/botão visuais copiados do `IntegracaoIa.vue`; `aria-describedby` com os ids de ajuda e erro (`${id}-ajuda`, `${id}-erro`), `aria-invalid` com erro, classe `campo-invalido` na caixa com erro, asterisco vermelho com `obrigatorio`. O rótulo aceita slot `default` para texto rico (declarações).

- [ ] **Step 1: Preparar o worktree.** O branch já está sobre a `master` `3d1a6d0` (sem os requisitos). Se faltar `$W/.env`, copiar `E:/Dev/Mapa_Cultura/.env` (não versionado); se faltar `node_modules`, criar a junção para `E:/Dev/Mapa_Cultura/node_modules`.
- [ ] **Step 2: Criar `Interruptor.vue`** com a marcação do toggle de `IntegracaoIa.vue` e trocar o de `IntegracaoIa.vue` por `<Interruptor id="ia-ativo" v-model="formPrincipal.ativo" rotulo="Ativar validação por IA" ajuda="Ligada, as validações automáticas usam este provedor (e a redundância, quando ele falhar)." :erro="formPrincipal.errors.ativo" @change="formPrincipal.clearErrors('ativo')" />`.
- [ ] **Step 3: Verificar.** `npm run build` sem erro; `art test --compact tests/Feature/Retaguarda/IntegracaoIaTest.php` passa; na tela Integração com IA o toggle liga, desliga, recebe foco pelo teclado (Espaço alterna) e mostra o erro.
- [ ] **Step 4: Commit** `feat: componente de toggle reutilizável`.

---

### Task 2: Perfil no cadastro da pessoa física

**Files:**
- Create: `app/Enums/TipoDeficiencia.php`, `app/Enums/Escolaridade.php`, `app/Enums/FaixaDeRenda.php`, `app/Enums/PovoTradicional.php`; migration `add_perfil_to_pessoas_fisicas_table` (`art make:migration ... --no-interaction`)
- Modify: `app/Models/PessoaFisica.php`, `app/Models/User.php`, `app/Http/Requests/SalvarPessoaFisicaRequest.php`, `app/Http/Controllers/CadastroController.php`, `resources/js/Pages/Cadastro/Completar.vue` (seções 3 e 5), `resources/js/types/index.ts` (`CadastroPessoaFisica`, `CadastroCompletarProps`), `database/factories/PessoaFisicaFactory.php`, `database/factories/UserFactory.php`
- Test: `tests/Feature/CadastroTest.php`

**Interfaces:**
- Produces:
  - `enum TipoDeficiencia: string` com `rotulo()`: `Auditiva='auditiva'` (Auditiva), `FisicaMotora='fisica_motora'` (Física-motora), `Intelectual='intelectual'` (Intelectual), `Visual='visual'` (Visual), `Multipla='multipla'` (Múltipla), `Tea='transtorno_espectro_autista'` (Transtorno do espectro autista), `Outra='outra'` (Outra).
  - `enum Escolaridade: string`: `SemEducacaoFormal='sem_educacao_formal'` (Não possui educação formal), `FundamentalIncompleto='fundamental_incompleto'` (Ensino fundamental incompleto ou equivalente), `FundamentalCompleto='fundamental_completo'` (Ensino fundamental completo ou equivalente), `MedioIncompleto='medio_incompleto'` (Ensino médio incompleto ou equivalente), `MedioCompleto='medio_completo'` (Ensino médio completo ou equivalente), `TecnicoIncompleto='tecnico_incompleto'` (Curso técnico incompleto), `TecnicoCompleto='tecnico_completo'` (Curso técnico completo), `SuperiorIncompleto='superior_incompleto'` (Ensino superior incompleto ou equivalente), `SuperiorCompleto='superior_completo'` (Ensino superior completo), `PosIncompleta='pos_incompleta'` (Pós-graduação incompleto), `PosCompleta='pos_completa'` (Pós-graduação completo).
  - `enum FaixaDeRenda: string`: `Nenhuma='nenhuma'` (Nenhuma renda), `AteUm='ate_1'` (Até 1 salário mínimo), `DeUmATres='de_1_a_3'` (De 1 a 3 salários mínimos), `DeTresACinco='de_3_a_5'` (De 3 a 5 salários mínimos), `DeCincoAOito='de_5_a_8'` (De 5 a 8 salários mínimos), `DeOitoADez='de_8_a_10'` (De 8 a 10 salários mínimos), `AcimaDeDez='acima_de_10'` (Acima de 10 salários mínimos).
  - `enum PovoTradicional: string`, rótulos copiados do espelho do SalCine II, nesta ordem (case = valor): `NaoPertence='nao_pertence'` → "Não pertence a povos ou comunidades tradicionais."; `Andirobeiros='andirobeiros'` → "Andirobeiros"; `ApanhadoresDeFlores='apanhadores_flores_sempre_vivas'` → "Apanhadores de flores sempre vivas"; `Benzedeiros='benzedeiros'` → "Benzedeiros"; `Caatingueiros='caatingueiros'` → "Caatingueiros"; `Caboclos='caboclos'` → "Caboclos"; `Caicaras='caicaras'` → "Caiçaras"; `CatadoresDeMangaba='catadores_mangaba'` → "Catadores de mangaba"; `Cipozeiros='cipozeiros'` → "Cipozeiros"; `FundosEFechosDePasto='fundos_fechos_pasto'` → "Comunidades de fundos e fechos de pasto"; `Quilombolas='quilombolas'` → "Comunidades quilombolas"; `Extrativistas='extrativistas'` → "Extrativistas"; `ExtrativistasCosteiros='extrativistas_costeiros_marinhos'` → "Extrativistas costeiros e marinhos"; `Faxinalenses='faxinalenses'` → "Faxinalenses"; `Geraizeiros='geraizeiros'` → "Geraizeiros"; `Ilheus='ilheus'` → "Ilhéus"; `Juventude='juventude_povos_comunidades'` → "Juventude de povos e comunidades tradicionais"; `Morroquianos='morroquianos'` → "Morroquianos"; `Pantaneiros='pantaneiros'` → "Pantaneiros"; `PescadoresArtesanais='pescadores_artesanais'` → "Pescadores artesanais"; `Pomerano='povo_pomerano'` → "Povo pomerano"; `Ciganos='povos_ciganos'` → "Povos ciganos"; `Terreiro='terreiro_matriz_africana'` → "Povos e comunidades de terreiro/de matriz africana"; `Indigenas='povos_indigenas'` → "Povos indígenas"; `QuebradeirasDeCoco='quebradeiras_coco_babacu'` → "Quebradeiras de coco babaçu"; `Raizeiros='raizeiros'` → "Raizeiros"; `RetireirosDoAraguaia='retireiros_araguaia'` → "Retireiros do Araguaia"; `Ribeirinhos='ribeirinhos'` → "Ribeirinhos"; `Vazanteiros='vazanteiros'` → "Vazanteiros"; `Veredeiros='veredeiros'` → "Veredeiros".
  - `pessoas_fisicas` (todas nulas): `deficiencia` string(30), `deficiencia_outra` string(255), `escolaridade` string(30), `renda` string(20), `povo_tradicional` string(40), `anos_atuacao` integer.
  - `PessoaFisica::CAMPOS_DO_PERFIL = ['identidade_genero', 'raca_cor', 'pcd', 'deficiencia', 'deficiencia_outra', 'escolaridade', 'renda', 'povo_tradicional', 'anos_atuacao']`; casts para os enums e `anos_atuacao` integer; `perfilCompleto(): bool` (gênero, raça, escolaridade, renda, povo e anos preenchidos; com `pcd`, `deficiencia` preenchida e, se `Outra`, `deficiencia_outra` preenchida); `retratoDoPerfil(): array<string, string|int|bool|null>` (os campos de `CAMPOS_DO_PERFIL`, enums como valor).
  - `User::perfilCompleto(): bool` (`pessoaFisica?->perfilCompleto() ?? false`).
  - `PessoaFisicaFactory`: definição preenche o perfil completo (`pcd` false, `deficiencia` null, `povo_tradicional` `NaoPertence`, `anos_atuacao` 0–30); estado `perfilIncompleto()` (escolaridade, renda, povo e anos nulos). `UserFactory::comPerfil()`: `afterCreating` cria `PessoaFisica::factory()->for($user)`.

- [ ] **Step 1: Testes** em `CadastroTest` (atualizar `dadosValidos()` com `escolaridade`, `renda`, `povo_tradicional`, `anos_atuacao`):
  - `test_salva_o_perfil_da_pessoa_fisica` — post válido grava os quatro campos e `anos_atuacao === 12`.
  - `test_perfil_e_obrigatorio` — sem escolaridade, renda, povo e anos: erro `Este campo é obrigatório.` em cada um.
  - `test_pcd_exige_o_tipo_e_outra_exige_qual` — `pcd=1` sem `deficiencia` → `Este campo é obrigatório.`; `deficiencia=outra` sem `deficiencia_outra` → `Diga qual é a deficiência.`
  - `test_sem_pcd_o_tipo_de_deficiencia_nao_fica_gravado` — `pcd=0`, `deficiencia=visual`, `deficiencia_outra=x` → ambos `null`; `deficiencia=visual` com `deficiencia_outra=x` → `deficiencia_outra` `null`.
  - `test_anos_de_atuacao_inteiro_de_zero_a_cem` — `-1` e `101` e `2.5` recusados; `0` aceito.
  - `test_cadastro_antigo_fica_com_perfil_incompleto` — `PessoaFisica::factory()->perfilIncompleto()->create()->perfilCompleto() === false`; padrão `=== true`; com `pcd` e sem tipo `=== false`.
  - `test_formulario_traz_as_opcoes_do_perfil` — `opcoes.povosTradicionais` com 30 itens, o primeiro `nao_pertence`; `tiposDeficiencia`, `escolaridades` (11), `faixasDeRenda` (7).
- [ ] **Step 2:** `art test --compact tests/Feature/CadastroTest.php` — falham.
- [ ] **Step 3: Implementar.** Regras: `deficiencia` `['nullable', 'required_if_accepted:pcd', Rule::enum(TipoDeficiencia::class)]`; `deficiencia_outra` `['nullable', 'required_if:deficiencia,outra', 'string', 'max:255', new TamanhoEmBytes(255)]`; `escolaridade`, `renda`, `povo_tradicional` `required` + `Rule::enum`; `anos_atuacao` `['required', 'integer', 'min:0', 'max:100']` (mensagens `Informe um número inteiro.`, `Informe 0 ou mais.`, `Informe no máximo 100.`). O controller zera `deficiencia`/`deficiencia_outra` conforme o teste e manda as opções novas (generalizar o tipo de `opcoes()` para `list<BackedEnum>`). Na tela, seção 5 vira "Identidade e perfil": selects de escolaridade, renda e povos (obrigatórios), número de anos (obrigatório, `inputmode="numeric"`), `pcd` como `Interruptor`; com `pcd`, select de tipo (obrigatório) e, com "Outra", texto "Qual?" (obrigatório). Os quatro sim/não da seção 3 também viram `Interruptor`.
- [ ] **Step 4:** `art test --compact tests/Feature/CadastroTest.php` passa; `npm run build` passa.
- [ ] **Step 5:** `pint` nos PHP alterados. Commit `feat: perfil da pessoa física no cadastro`.

---

### Task 3: Estrutura do questionário, pendência e trava

**Files:**
- Create: `app/Enums/TipoPergunta.php`; migration `create_edital_questionario_tables`; `app/Models/EditalQuestionarioSecao.php`, `app/Models/EditalPergunta.php`; factories `EditalQuestionarioSecaoFactory`, `EditalPerguntaFactory`
- Modify: `app/Models/Edital.php`
- Test: `tests/Feature/Questionario/EstruturaDoQuestionarioTest.php`

**Interfaces:**
- Produces:
  - `enum TipoPergunta: string`: `TextoCurto='texto_curto'` (Texto curto), `TextoLongo='texto_longo'` (Texto longo), `EscolhaUnica='escolha_unica'` (Escolha única), `MultiplaEscolha='multipla_escolha'` (Múltipla escolha), `SimNao='sim_nao'` (Sim/não), `Data='data'` (Data), `Numero='numero'` (Número), `Declaracao='declaracao'` (Declaração); `rotulo()`, `temOpcoes(): bool` (escolha única e múltipla), `podeSerOrigem(): bool` (escolha única, múltipla, sim/não), `temLimite(): bool` (textos), `limitePadrao(): ?int` (curto 255, longo 20000, demais null); constantes `CHAVE_SIM = 'sim'`, `CHAVE_NAO = 'nao'`, `CHAVE_DECLARACAO = 'declaro'`.
  - Tabelas: `edital_questionario_secoes` (`edital_id` fk cascade, `titulo` string(255), `descricao` string(2000) null, `ordem` int default 0, timestamps); `edital_perguntas` (`edital_id` fk cascade, `edital_questionario_secao_id` fk cascade, `enunciado` string(2000), `ajuda` string(2000) null, `tipo` string(20), `obrigatoria` bool default true, `limite_caracteres` int null, `opcoes` longText null — JSON `list<{chave, texto, outro}>`, `todas_categorias` bool default false, `naturezas` string(200) null, `condicao_pergunta_id` fk null → `edital_perguntas` `nullOnDelete`, `condicao_opcoes` string(2000) null — JSON `list<chave>`, `ordem` int default 0 (posição na seção), timestamps); `edital_pergunta_categoria` (`edital_pergunta_id` fk cascade, `edital_categoria_id` fk cascade, `primary([...], 'edital_perg_cat_pk')`).
  - `EditalQuestionarioSecao`: `edital()`, `perguntas(): HasMany` (ordem, id).
  - `EditalPergunta`: casts (`tipo` enum, `opcoes` array, `naturezas` `AsEnumCollection::of(NaturezaProponente::class)`, `condicao_opcoes` array, booleanos, inteiros); `edital()`, `secao()` (`edital_questionario_secao_id`), `categorias(): BelongsToMany` (`edital_pergunta_categoria`), `condicao(): BelongsTo` (`condicao_pergunta_id`); `semVinculo(): bool`; `valePara(?int $categoriaId, NaturezaProponente $natureza): bool` (sem vínculo → false; senão `todas_categorias` ou categoria ligada, e `RegrasDaInscricao::aceitaNatureza`); `chavesDasOpcoes(): list<string>` (sim/não → `['sim','nao']`, declaração → `['declaro']`, escolhas → chaves de `opcoes`); `limiteDeCaracteres(): ?int` (`limite_caracteres ?? tipo->limitePadrao()`).
  - `EditalPerguntaFactory`: padrão texto curto obrigatório, `todas_categorias` **true**; estados `semVinculo()`, `escolha(TipoPergunta $tipo, list<string> $textos, bool $comOutro = false)` (chaves `o1`, `o2`…; "Outro" por último), `simNao()`, `declaracao()`, `condicionadaA(EditalPergunta $origem, list<string> $chaves)`.
  - `Edital`: `secoesDoQuestionario(): HasMany` (ordem, id), `perguntas(): HasMany`, `inscricoes(): HasMany`; `questionarioTravado(): bool` (existe inscrição do edital com `numero` preenchido — o número nasce na primeira finalização e fica ao reabrir; `finalizada_em` é zerado ao reabrir); `pendenciasDoQuestionario(): list<string>` — `Vincule às categorias 1 pergunta do questionário.` / `Vincule às categorias N perguntas do questionário.`; `pendenciasParaPublicar()` acrescenta essas; `public const string AVISO_QUESTIONARIO_TRAVADO = 'O questionário está travado porque o edital já tem inscrição finalizada: mudar as perguntas deixaria respostas enviadas sem pergunta.'`

- [ ] **Step 1: Testes:**
  - `test_edital_sem_perguntas_nao_tem_pendencia_do_questionario`.
  - `test_pergunta_sem_vinculo_entra_na_pendencia_com_a_contagem` — 1 → mensagem no singular; 3 → `Vincule às categorias 3 perguntas do questionário.` dentro de `pendenciasParaPublicar()`.
  - `test_pergunta_ligada_so_a_categoria_apagada_fica_sem_vinculo`.
  - `test_trava_com_inscricao_ja_finalizada_mesmo_reaberta` — rascunho sem número: não trava; rascunho com `numero` (reaberto): trava.
  - `test_apagar_a_secao_apaga_as_perguntas_e_solta_a_condicao_de_outra_secao` — dependente em outra seção fica com `condicao_pergunta_id` nulo.
  - `test_vale_para_categoria_e_natureza` — todas + naturezas nulas; algumas; natureza fora da lista; sem vínculo → false; inscrição sem categoria só vê "todas".
  - `test_chaves_das_opcoes_por_tipo`.
- [ ] **Step 2:** Rodar o arquivo — falha.
- [ ] **Step 3: Implementar** o acima.
- [ ] **Step 4:** Rodar o arquivo e `tests/Feature/Retaguarda/EditaisTest.php` — passam.
- [ ] **Step 5:** `pint`; commit `feat: estrutura do questionário do edital, pendência e trava`.

---

### Task 4: Salvar o questionário pela retaguarda

**Files:**
- Create: `app/Http/Requests/Retaguarda/RegrasDoQuestionario.php`, `app/Support/Edital/SincronizadorDoQuestionario.php`
- Modify: `app/Http/Requests/Retaguarda/SalvarEditalRequest.php`, `app/Support/Edital/SincronizadorDoEdital.php:55`, `app/Http/Controllers/Retaguarda/EditalController.php` (`create`, `edit`, `salvar`, `opcoesDoFormulario`)
- Test: `tests/Feature/Retaguarda/QuestionarioDoEditalTest.php`

**Interfaces:**
- Consumes: Task 3 (modelos, `TipoPergunta`, `Edital::questionarioTravado()`, `pendenciasDoQuestionario()`, `AVISO_QUESTIONARIO_TRAVADO`).
- Produces:
  - Contrato do envio (com `com_listas`): `com_questionario=1` e `questionario` = **texto JSON** (um campo só no multipart), decodificado em `prepareForValidation` (JSON inválido fica texto e cai em `array` → `Valor inválido.`). Formato:
    `{secoes: list<{id?, titulo, descricao?, perguntas?: list<{id?, enunciado, ajuda?, tipo, obrigatoria, limite_caracteres?, opcoes?: list<{chave, texto, outro?}>, todas_categorias, categorias?: list<posição da categoria>, naturezas?: list<string>, condicao_pergunta?: posição global, condicao_opcoes?: list<chave>}>}>}`. Posição global = índice (desde 0) da pergunta na ordem do questionário (seções em ordem, perguntas em ordem dentro de cada uma). Erros voltam como `questionario.secoes.S.perguntas.P.campo`.
  - `RegrasDoQuestionario` (final): `MAXIMO_PERGUNTAS = 200`; `regras(list<int> $posicoesDeCategorias): array` (seções e perguntas por seção até `SalvarEditalRequest::MAXIMO_ITENS`; opções até 50; `titulo` 255, `descricao`/`enunciado`/`ajuda` 2000, `opcoes.*.texto` 500 — todos com `TamanhoEmBytes`; `opcoes.*.chave` `regex:/^[a-z0-9]{1,20}$/`; `limite_caracteres` 1–20000; `tipo` `Rule::enum`; `naturezas.*` enum; `categorias.*` `Rule::in($posicoes)`); `conferir(Validator $validator, mixed $questionario): void` (as regras cruzadas abaixo); `mensagens(): array`.
  - Regras cruzadas e mensagens: tipo com opções e menos de duas → `…opcoes`: `Cadastre ao menos duas opções.`; mais de um "Outro" → `…opcoes`: `Só uma opção pode ser "Outro".`; chave repetida na pergunta → `…opcoes.O.chave`: `Opção repetida.`; total acima de 200 → `questionario`: `Cadastre no máximo 200 perguntas.`; origem inexistente ou na mesma posição ou depois → `…condicao_pergunta`: `A pergunta da condição precisa vir antes desta.`; origem de tipo que não pode ser origem → `…condicao_pergunta`: `A condição só pode depender de pergunta de escolha ou de sim/não.`; condição sem respostas → `…condicao_opcoes`: `Escolha ao menos uma resposta da condição.`; resposta que não é chave da origem → `…condicao_opcoes`: `Escolha respostas da pergunta da condição.`
  - `SalvarEditalRequest::comQuestionario(): bool`; `questionarioDoEdital(): ?array` (null sem o marcador); com o marcador e `Edital::questionarioTravado()` (edital da rota), `after()` acrescenta `questionario` → `Edital::AVISO_QUESTIONARIO_TRAVADO`.
  - `SincronizadorDoEdital::sincronizar(Edital $edital, array $listas, ?array $questionario = null): void` — depois das categorias, `(new SincronizadorDoQuestionario)->sincronizar($edital, $questionario, $categorias)` quando não nulo.
  - `SincronizadorDoQuestionario`: `sincronizar(Edital $edital, array $questionario, list<EditalCategoria> $categorias): void` (seções e perguntas por `id` do mesmo edital — pergunta pode mudar de seção mantendo o id; as ausentes são apagadas; `ordem` = posição; segunda passada liga condições pela posição global e sincroniza o pivô); `substituir(Edital $edital, array $questionario): void` (apaga as seções do edital e grava tudo como novo, categorias = `$edital->categorias()->get()->all()`). Normaliza: declaração sempre obrigatória; tipo sem opções grava `opcoes` nulo; tipo sem limite grava limite nulo; naturezas vazias = nulo; sem categorias e sem "todas" = sem vínculo.
  - `EditalController@edit` passa `edital.questionario = {secoes: [{id, titulo, descricao, perguntas: [{id, enunciado, ajuda, tipo, obrigatoria, limite_caracteres, opcoes, todas_categorias, categorias: list<posição>, naturezas: list<string>, condicao_pergunta: ?posição global, condicao_opcoes: list<string>}]}]}`, `questionarioTravado: bool`, e `tiposPergunta` em `opcoesDoFormulario()` (`create` manda `questionarioTravado: false` e questionário vazio).
  - `EditalController::salvar`: além da pendência de quem passa a publicar, edital **já publicado** não salva com pergunta sem vínculo: `ValidationException` em `questionario` com `pendenciasDoQuestionario()`.

- [ ] **Step 1: Testes** (gestor; edital com duas categorias; envio montado por um helper `enviar(Edital, array $questionario, array $extras = [])` que manda `com_listas`, as categorias atuais e `questionario` como `json_encode`):
  - `test_salva_secoes_e_perguntas_de_todos_os_tipos_na_ordem`.
  - `test_vinculo_todas_algumas_e_sem_vinculo` — pivô por posição; naturezas gravadas; sem vínculo = `todas_categorias` false e pivô vazio.
  - `test_condicao_guarda_a_origem_e_as_chaves` — origem escolha única e origem sim/não (`['sim']`).
  - `test_origem_depois_da_dependente_e_recusada` e `test_origem_de_texto_e_recusada` (mensagens acima).
  - `test_resposta_da_condicao_que_nao_e_da_origem_e_recusada`.
  - `test_escolha_com_uma_opcao_ou_dois_outros_e_recusada`.
  - `test_declaracao_fica_sempre_obrigatoria` — enviada com `obrigatoria=false`, grava true.
  - `test_ids_existentes_sao_mantidos_e_os_ausentes_apagados` — pergunta movida de seção mantém o id.
  - `test_sem_o_marcador_o_questionario_nao_muda`.
  - `test_questionario_travado_recusa_o_envio` — inscrição com `numero` → erro `questionario` = aviso da trava e nada muda.
  - `test_edital_publicado_nao_salva_com_pergunta_sem_vinculo`.
  - `test_enunciado_acima_de_2000_bytes_e_recusado` — 1001 "ç".
  - `test_questionario_grande_vai_inteiro_num_campo_json` — 4 seções × 50 perguntas e 50 documentos, multipart com o PDF: as 200 perguntas gravadas.
  - `test_tela_de_edicao_traz_o_questionario_com_posicoes_e_a_trava` — `assertInertia` em `edital.questionario.secoes.0.perguntas.1.condicao_pergunta === 0`, `categorias === [1]`, `questionarioTravado`.
- [ ] **Step 2:** Rodar — falham.
- [ ] **Step 3: Implementar** o acima.
- [ ] **Step 4:** Rodar este arquivo, `tests/Feature/Retaguarda/EditalEstruturaSalvarTest.php` e `tests/Feature/Retaguarda/EditaisTest.php` (um por vez) — passam.
- [ ] **Step 5:** `pint`; commit `feat: salvar o questionário pelo formulário do edital`.

---

### Task 5: Aba Questionário na retaguarda

**Files:**
- Create: `resources/js/Components/Retaguarda/questionario.ts`, `resources/js/Components/Retaguarda/Edital/QuestionarioEdital.vue`, `.../Edital/PerguntaDoQuestionario.vue`, `.../Edital/VinculoDaPergunta.vue`, `.../Edital/VincularEmLote.vue`
- Modify: `resources/js/Pages/Retaguarda/Editais/Form.vue` (useForm, `DEFINICAO_ABAS`, `ICONES`, `vigiarReferencias`, `paraEnvio`, painel da aba), `resources/js/Components/Retaguarda/AbasFormulario.vue` (`errosDa` soma `aba.pendencias`), `resources/js/types/index.ts`

**Interfaces:**
- Consumes: Task 4 (props `edital.questionario`, `questionarioTravado`, `tiposPergunta`; contrato `com_questionario` + `questionario` JSON); Task 1 (`Interruptor`); `ListaEditavel` + `listaEditavel.ts` (`novaChave`, classes).
- Produces:
  - Tipos: `AbaFormulario.pendencias?: number`; `OpcaoDaPergunta { chave: string; texto: string; outro: boolean }`; `PerguntaEditavel extends ItemEditavel { id: number | null; enunciado; ajuda; tipo: string; obrigatoria: boolean; limite_caracteres: number | null; opcoes: OpcaoDaPergunta[]; todas_categorias: boolean; categorias: string[] /* chaves de CategoriaEditavel */; naturezas: string[]; condicao_pergunta: string | null /* chave da pergunta de origem */; condicao_opcoes: string[] }`; `SecaoQuestionarioEditavel extends ItemEditavel { id: number | null; titulo; descricao; perguntas: PerguntaEditavel[] }`; props novas em `RetaguardaEditaisFormProps`.
  - `questionario.ts`: `novaChaveDeOpcao(): string` (12 caracteres `[a-z0-9]` com `crypto.getRandomValues`); `perguntasEmOrdem(secoes): PerguntaEditavel[]`; `semVinculo(p): boolean`; `naturezasOferecidas(p, categorias: CategoriaEditavel[], naturezasDoEdital: string[], opcoes: OpcaoFormulario[]): OpcaoFormulario[]` (todas as categorias → as do edital; senão a união das naturezas das categorias escolhidas, e categoria sem naturezas vale as do edital); `respostasDaOrigem(origem): { chave: string; rotulo: string }[]` (sim/não → Sim/Não).
  - Aba `{ id: 'questionario', rotulo: 'Questionário', campos: ['questionario', 'questionario.*'], pendencias: <perguntas sem vínculo> }` logo depois de Documentos.

- [ ] **Step 1: Estado e envio em `Form.vue`.** `useForm` ganha `questionario: { secoes }` montado das props (chaves novas; categorias e condição convertidas de posição para chave). `paraEnvio` só manda `com_questionario: true` e `questionario: JSON.stringify(...)` quando `!questionarioTravado`, convertendo chaves de categoria em posições e a chave da origem em posição global (`perguntasEmOrdem`). `vigiarReferencias` das categorias limpa também as categorias das perguntas; um novo vigia das perguntas limpa a condição cuja origem sumiu e anuncia `A condição de "<enunciado>" foi removida porque a pergunta de origem foi apagada.` (região `aria-live` da aba); tirar uma opção da origem tira a chave da condição, e condição sem respostas some com o mesmo tipo de aviso.
- [ ] **Step 2: `QuestionarioEdital.vue`.** Com trava: aviso `Edital::AVISO_QUESTIONARIO_TRAVADO` (texto vindo da prop) e conteúdo num `<fieldset disabled>`. Sem trava: `ListaEditavel` de seções (singular "seção", feminino; campos título obrigatório e descrição) e, dentro de cada uma, `ListaEditavel` nível 4 de perguntas (`prefixo` `questionario.secoes.S.perguntas`); o resumo do cartão mostra tipo, "Obrigatória" e, sem vínculo, o selo destacado `Vincule às categorias`. Botão "Vincular em lote" (abre `VincularEmLote`). Lugar reservado para "Importar com IA" (Task 9).
- [ ] **Step 3: `PerguntaDoQuestionario.vue`.** Enunciado (obrigatório), ajuda, tipo (select), obrigatória (`Interruptor`; fixo ligado e desabilitado em declaração), limite (só textos; dica com o padrão 255/20000), opções (só escolhas: lista com texto obrigatório, subir/descer, remover, `Interruptor` "É a opção Outro (abre um campo Qual?)" — ligar numa desliga nas outras; "Adicionar opção" gera `novaChaveDeOpcao()`), "Mover para a seção" (select; leva a pergunta para o fim da seção escolhida), `VinculoDaPergunta`, condição (select "Mostrar só quando…" com as perguntas anteriores que podem ser origem + `Interruptor` por resposta da origem; aviso local `A pergunta de origem precisa vir antes desta.` quando a ordem mudar).
- [ ] **Step 4: `VinculoDaPergunta.vue`** (v-model de `{ todas_categorias, categorias, naturezas }`): escolha "Todas as categorias" / "Algumas categorias" / "Sem vínculo" (radio), `Interruptor` por categoria (variante `linha`), `Interruptor` por natureza de `naturezasOferecidas` (nenhuma marcada = todas). **`VincularEmLote.vue`**: `<dialog>` com as perguntas agrupadas por seção (`Interruptor` por pergunta e "Seção inteira" por seção; botão "Só as sem vínculo"), o mesmo `VinculoDaPergunta` e "Aplicar" (copia o vínculo para as escolhidas; nada é gravado até salvar o edital).
- [ ] **Step 5: Verificar.** `npm run build` passa. No navegador (dev): criar edital com 2 categorias, 2 seções, uma pergunta de cada tipo, "Outro", condição por sim/não e por escolha, vínculo em lote; salvar, recarregar e conferir tudo; a aba mostra o contador de sem vínculo; apagar a origem mostra o aviso; edital com inscrição finalizada mostra a aba travada. `art test --compact tests/Feature/Retaguarda/QuestionarioDoEditalTest.php` continua passando.
- [ ] **Step 6:** Commit `feat: aba Questionário no formulário do edital`.

---

### Task 6: Texto do Word (.docx)

**Files:**
- Create: `app/Support/EditalIa/TextoDoDocx.php`
- Test: `tests/Unit/TextoDoDocxTest.php`

**Interfaces:**
- Produces: `final class TextoDoDocx` — `public const string ERRO_ARQUIVO = 'Não foi possível ler o arquivo Word. Confira se é um .docx válido e envie de novo.'`; `public const int MAXIMO_CARACTERES = 300000`; `public static function extrair(string $binario): string` (lança `FalhaNaIa(ERRO_ARQUIVO)` para zip inválido, sem `word/document.xml` ou XML inválido).
- Regras: grava em arquivo temporário e abre com `ZipArchive` (apaga o temporário no `finally`); lê `word/document.xml` com `DOMDocument::loadXML(..., LIBXML_NONET)` sem substituir entidades, `DOMXPath` com o namespace `w`; percorre `w:body` na ordem do documento: parágrafo = uma linha (`w:t` concatenados, `w:tab` → `\t`, `w:br`/`w:cr` → `\n`); tabela = uma linha por `w:tr`, células unidas por ` | ` (parágrafos da célula unidos por espaço); conteúdo de `w:sdt` incluído; caixas de seleção: os glifos ☐ → `[ ]`, ☒ e ☑ → `[x]`, e a caixa antiga `w:ffData/w:checkBox` vira `[x]` ou `[ ]` por `w:checked` (ou `w:default`) `@w:val`; linhas vazias repetidas viram uma; corta em `MAXIMO_CARACTERES`.

- [ ] **Step 1: Testes** (o teste monta o .docx com `ZipArchive` e um `document.xml` escrito à mão):
  - `test_paragrafos_na_ordem_com_tabulacao_e_quebra`.
  - `test_tabela_vira_uma_linha_por_linha_com_celulas_separadas`.
  - `test_caixas_de_selecao_viram_marcadores` — ☐/☒ e `w:checkBox` marcado e desmarcado.
  - `test_conteudo_de_controle_de_conteudo_entra`.
  - `test_arquivo_que_nao_e_zip_lanca_a_falha` e `test_zip_sem_document_xml_lanca_a_falha` (mensagem `ERRO_ARQUIVO`).
  - `test_entidade_externa_nao_e_expandida` — `<!DOCTYPE>` com entidade `SYSTEM` não aparece no texto.
- [ ] **Step 2:** `art test --compact tests/Unit/TextoDoDocxTest.php` — falha. **Step 3:** Implementar. **Step 4:** Passa.
- [ ] **Step 5:** `pint`; commit `feat: extração de texto do .docx para a IA`.

---

### Task 7: Esquema, prompt e leitura da resposta do questionário

**Files:**
- Create: `app/Support/EditalIa/MontaEsquema.php` (trait), `app/Support/EditalIa/EsquemaDoQuestionario.php`, `app/Support/EditalIa/PromptDoQuestionario.php`, `app/Support/EditalIa/QuestionarioDaResposta.php`, `tests/Support/RespostaDoQuestionario.php`
- Modify: `app/Support/EditalIa/EsquemaDaResposta.php` (os helpers privados `objeto`, `lista`, `texto`, `data`, `numero`, `inteiro`, `booleano`, `enum`, `enumDe`, `campo` passam para o trait, sem mudar o esquema), `app/Support/EditalIa/EditalDaResposta.php` (`chaveDoNome` vira `public static`)
- Test: `tests/Feature/Questionario/QuestionarioDaRespostaTest.php`; rodar também `tests/Unit/EditalDaRespostaTest.php`

**Interfaces:**
- Consumes: Task 4 (formato de `SincronizadorDoQuestionario`/`RegrasDoQuestionario`), Task 3 (`TipoPergunta`).
- Produces:
  - `EsquemaDoQuestionario::esquema(): array` (formato Gemini, traduzível por `EsquemaOpenAi`): raiz obrigatória `secoes`, `confiabilidade`, `atencao`. `secoes[]`: `titulo` (texto não nulo), `descricao`, `perguntas[]`: `enunciado` (não nulo), `ajuda`, `tipo` (enum `TipoPergunta`, não nulo), `obrigatoria` (bool), `limite_caracteres` (int), `opcoes[]` {`texto` não nulo, `outro` bool}, `categorias[]` (texto: nomes exatamente como na lista enviada, só quando o formulário diz; vazia quando não diz), `naturezas[]` (enum `NaturezaProponente`; vazia = todas), `condicao` (objeto anulável, via `campo(objeto(...), null, true)`) {`pergunta`: enunciado da anterior como você o escreveu, `respostas[]`: textos das opções — "Sim"/"Não" no sim/não}. `confiabilidade` {`nota` 0–100, `justificativa`}; `atencao[]` {`tipo` (chaves de `EsquemaDaResposta::TIPOS_ATENCAO`), `texto`, `itens[]`}.
  - `PromptDoQuestionario`: `editavel(): string`, `fixo(): string`, `contexto(Edital $edital): string` (`Categorias do edital (use exatamente estes nomes) e as naturezas que cada uma aceita:` + uma linha `- <nome>: <rótulos das naturezas>` por categoria; categoria sem naturezas usa as do edital; edital sem naturezas, `todas`), `comTexto(string $texto): string` (`Texto do formulário, extraído do Word ([x] = caixa marcada, [ ] = caixa vazia; células da mesma linha de tabela separadas por |):` + o texto entre `<<<INICIO DO FORMULARIO>>>` e `<<<FIM DO FORMULARIO>>>`).
  - `QuestionarioDaResposta`: `public const string BLOCO = 'questionario'`; `public static function conferirRaiz(array $dados): void` (lança `RespostaInvalida` sem `secoes` lista, `confiabilidade` array ou `atencao` lista); `public function montar(array $dados, Edital $edital): array{questionario: array{secoes: list<array<string, mixed>>}, confiabilidade: array{geral: ?int, blocos: array{questionario?: array{nota: int, justificativa: string}}}, atencao: list<array{bloco: string, tipo: string, texto: string, itens: list<string>}>}` — `questionario` no formato do contrato da Task 4, com categorias como posições em `$edital->categorias`.
  - `tests/Support/RespostaDoQuestionario::respostaDoQuestionario(array $alteracoes = []): array` — resposta completa (duas seções; texto longo com limite 1000; escolha única com "Outro"; sim/não; pergunta condicionada ao "Sim"; múltipla vinculada a "Teatro"; declaração; confiabilidade 85; um ponto de atenção).
- Texto de `PromptDoQuestionario::editavel()` (copiar):
  ```
  Você vai ler o formulário de inscrição (o "espelho do formulário") de um edital da Fundação Gregório de Mattos (FGM), da Prefeitura de Salvador, e montar o questionário que o proponente responde ao se inscrever no portal.
  1. Monte só as perguntas deste edital: categoria ou modalidade da proposta, textos da proposta, listas de marcar, datas de execução, histórico em editais, se recebeu recursos de fomento nos últimos 5 anos, perguntas que dependem de outra resposta e declarações ("DECLARO que…").
  2. Deixe de fora o que o portal já tem: identificação, endereço, contato e representante legal; identidade de gênero, raça/cor, deficiência e o tipo dela, escolaridade, renda, povos e comunidades tradicionais e anos de atuação (vêm do cadastro); natureza do proponente, categoria e cotas escolhidas (vêm das escolhas da inscrição); envio de anexos e documentos. Deixe de fora também as instruções gerais do formulário ("Antes de começar…", "Prepare um rascunho…").
  3. Use as seções do formulário, com os títulos como aparecem, e as perguntas na ordem do formulário.
  4. Tipo: texto curto para respostas de uma linha; texto longo para descrições e justificativas ou limite acima de 255 caracteres; escolha única quando se marca uma opção; múltipla escolha quando se marcam várias; sim/não quando as opções são só Sim e Não; data; número para quantidades e valores; declaração para o texto que o proponente marca para concordar.
  5. A pergunta é obrigatória quando o enunciado tem asterisco (*); a declaração é sempre obrigatória. Preserve o limite de caracteres ("1 mil caracteres" vira 1000).
  6. Copie as opções como estão. A opção que abre um campo para escrever ("Outro", "Outra (especificar)") tem outro = verdadeiro.
  7. Vínculo: só quando o formulário diz para quais categorias, modalidades ou naturezas a pergunta vale ("apenas para as categorias…", "No caso de Pessoa Jurídica…"), preencha as categorias com os nomes exatamente como na lista de categorias abaixo e as naturezas. Quando não diz, deixe as categorias vazias e não marque todas.
  8. Condição: quando o formulário diz que a pergunta só aparece conforme a resposta de uma pergunta anterior ("só deve aparecer se a resposta anterior for Sim"), preencha a condição com o enunciado dessa pergunta anterior exatamente como você o escreveu e os textos das respostas que mostram esta pergunta. Se a pergunta anterior ficou de fora pela regra 2, deixe a condição vazia e registre um ponto de atenção.
  9. Notas para a plataforma ("Importante: esse campo deve aparecer…", "orientação para a plataforma") servem para deduzir tipo, condição e limite; não as copie para o enunciado nem para a ajuda.
  10. Conteúdo que não cabe numa pergunta (ficha técnica com várias pessoas, tabela de linhas repetidas, envio de arquivo): não crie pergunta e registre um ponto de atenção do tipo "leitura incerta".
  11. Dê uma nota de confiabilidade de 0 a 100 com justificativa curta. Em todo ponto de atenção, cite a seção ou o enunciado do formulário.
  ```
  e `fixo()` no molde de `PromptModelo::fixo()`: responder só no formato JSON do esquema; não inventar perguntas nem opções; não incluir o que a regra 2 tira; apontar as contradições do formulário como pontos de atenção.
- Regras de `montar` (cada descarte ou ajuste vira aviso tipo `incerta`, bloco `questionario`):
  - Tipo desconhecido → pergunta descartada. Escolha com menos de duas opções → descartada. Mais de um "Outro" → só o primeiro fica. Declaração → obrigatória. Textos cortados em bytes (`titulo` 255, `enunciado`/`ajuda`/`descricao` 2000, opção 500); enunciado vazio → descartada. Limite fora de 1–20000 → nulo. Chaves de opção `Str::lower(Str::random(12))`.
  - Categorias por nome (`EditalDaResposta::chaveDoNome`): todos os nomes encontrados → categorias por posição; algum nome sem par → pergunta sem vínculo, com aviso `A pergunta "<enunciado>" cita a categoria "<nome>", que não está no edital; ficou sem vínculo.`; lista vazia → sem vínculo. `todas_categorias` sempre falso. Natureza desconhecida → ignorada com aviso; vazia → nula.
  - Condição: origem pelo enunciado (mesma normalização) entre as perguntas **anteriores** já aceitas e de tipo que pode ser origem; respostas pelo texto das opções (sim/não aceita "Sim"/"Não"); sem origem ou sem nenhuma resposta resolvida → sem condição, com aviso `A condição da pergunta "<enunciado>" não foi reconhecida; a pergunta ficou sem condição.`
  - Limites: seções e perguntas por seção até `SalvarEditalRequest::MAXIMO_ITENS`, total até `RegrasDoQuestionario::MAXIMO_PERGUNTAS`, opções até 50; o resto sai com aviso. Seção sem perguntas aceitas sai com aviso.
  - Confiabilidade: `{geral: nota (0–100), blocos: {questionario: {nota, justificativa}}}`; sem nota → `{geral: null, blocos: []}`. Atenção da IA: bloco sempre `questionario`, tipo desconhecido vira `incerta`, texto até 2000 caracteres, até 20 itens.

- [ ] **Step 1: Testes:**
  - `test_esquema_traduz_para_o_openai_strict` — `EsquemaOpenAi::de(EsquemaDoQuestionario::esquema())` com `additionalProperties: false` e `condicao` anulável.
  - `test_esquema_do_edital_nao_muda` — `EsquemaDaResposta::esquema()` igual ao de antes (comparar com `json_encode` gravado no teste a partir do código atual antes da mudança).
  - `test_monta_secoes_perguntas_opcoes_e_declaracao_obrigatoria`.
  - `test_vincula_pelo_nome_sem_acento_e_caixa` / `test_categoria_desconhecida_deixa_sem_vinculo_com_aviso` / `test_sem_categorias_fica_sem_vinculo`.
  - `test_condicao_resolve_origem_e_respostas_em_chaves` / `test_condicao_que_nao_resolve_vira_aviso` / `test_condicao_para_pergunta_posterior_nao_vale`.
  - `test_tipo_desconhecido_e_escolha_sem_opcoes_sao_descartados`.
  - `test_textos_cortados_em_bytes_e_limite_invalido_anulado`.
  - `test_raiz_fora_do_formato_lanca_resposta_invalida`.
  - `test_contexto_lista_categorias_e_naturezas` — `PromptDoQuestionario::contexto` com categoria sem naturezas mostrando as do edital.
- [ ] **Step 2:** Rodar — falha. **Step 3:** Implementar. **Step 4:** Este arquivo e `tests/Unit/EditalDaRespostaTest.php` passam.
- [ ] **Step 5:** `pint`; commit `feat: esquema e leitura da resposta da IA para o questionário`.

---

### Task 8: Importação do questionário em segundo plano

**Files:**
- Create: migration `create_importacoes_questionario_table`; `app/Models/ImportacaoQuestionario.php` + factory; `app/Console/Commands/AnalisarQuestionarioCommand.php`; `app/Support/EditalIa/LancadorDeAnaliseDoQuestionario.php`; `app/Http/Controllers/Retaguarda/ImportacaoQuestionarioController.php`; `app/Http/Requests/Retaguarda/ImportarQuestionarioRequest.php`
- Modify: `routes/retaguarda.php` (antes do `Route::resource('editais', ...)`)
- Test: `tests/Feature/Retaguarda/ImportacaoQuestionarioTest.php`

**Interfaces:**
- Consumes: Task 6 (`TextoDoDocx::extrair`), Task 7 (`EsquemaDoQuestionario`, `PromptDoQuestionario`, `QuestionarioDaResposta`), Task 4 (`SincronizadorDoQuestionario::substituir`), Task 3 (`Edital::questionarioTravado()`, `AVISO_QUESTIONARIO_TRAVADO`); `AnaliseComRedundancia::analisar(...)` com `$arquivoBinario = null` para texto.
- Produces:
  - `importacoes_questionario`: `user_id` fk, `edital_id` fk `cascadeOnDelete`, `situacao` string(20) (`SituacaoImportacao`), `arquivo` string(255), `arquivo_nome` string(255), `tipo_arquivo` string(10) (`pdf`|`docx`), `provedor` string(20) null, `modelo` string(100), `tokens_entrada`/`tokens_saida`/`tokens_total` int null, `erro` string(1000) null, `resposta`/`confiabilidade`/`atencao` longText null, `conferida_em` timestamp null, `iniciada_em` timestamp, `execucao` string(36) null, timestamps.
  - `ImportacaoQuestionario`: `DISCO = 'local'`, `PASTA = 'editais/questionarios'`; casts como `ImportacaoEdital`; `edital()`, `user()`; `situacaoExibida(?CarbonInterface $agora = null): SituacaoImportacao` e `erroExibido(...)` com `ImportacaoEdital::MINUTOS_LIMITE` e `ImportacaoEdital::ERRO_SEM_TERMINO`; `scopeDaExecucao(Builder, string $execucao)` (execução igual e situação `Analisando`).
  - `LancadorDeAnaliseDoQuestionario::lancar(ImportacaoQuestionario $importacao): void` — `nohup php artisan questionarios:analisar {id} {execucao} &`, igual ao `LancadorDeAnalise`.
  - Comando `questionarios:analisar {importacao} {execucao?}` (`AnalisarQuestionarioCommand`), no molde de `AnalisarEditalCommand` (tempo, memória, `naExecucao` com a linha travada, log sem a chave, `ERRO_INESPERADO`): `docx` → `TextoDoDocx::extrair`, arquivo `null`, prompt = `fixo` + `editavel` + `contexto` + `comTexto`; `pdf` → binário `application/pdf`, prompt sem `comTexto`; temperatura `ConfiguracaoIa::atual()->temperaturaDaImportacao()`; conferência `QuestionarioDaResposta::conferirRaiz(...)`. Na gravação final, na mesma transação: trava a linha do edital (`lockForUpdate`), se `questionarioTravado()` falha com `Edital::AVISO_QUESTIONARIO_TRAVADO`, senão `montar` + `substituir` + importação `Concluida` com confiabilidade e atenção (aviso da redundância com bloco `questionario`). Falha não toca no questionário.
  - Rotas (gestor): `POST /retaguarda/editais/{edital}/questionario/importacoes` → `store` (`retaguarda.editais.questionario.importacoes.store`, `throttle:10,1`); `GET /retaguarda/editais/questionario/importacoes/{importacao}` → `show` (`...questionario.importacoes.show`, `whereNumber`, JSON `{situacao, erro}` sem CLOB); `POST /retaguarda/editais/{edital}/questionario/importacao/conferida` → `conferida` (`...questionario.importacao.conferida`).
  - `ImportacaoQuestionarioController`: middleware de configuração igual ao do `ImportacaoEditalController` (sem provedor → `back()->withErrors(['configuracao' => AnaliseComRedundancia::SEM_CONFIGURACAO])`), só no `store`. Recusas em `arquivo`, antes de guardar o arquivo: trava → `Edital::AVISO_QUESTIONARIO_TRAVADO`; sem categoria salva → `ERRO_SEM_CATEGORIA = 'Cadastre ao menos uma categoria e salve o edital antes de importar o questionário.'`; análise em curso no edital → `ERRO_EM_ANDAMENTO = 'Já há uma importação do questionário em análise para este edital.'`; edital com seções e sem `substituir` aceito → `substituir`: `ERRO_CONFIRMAR = 'Confirme que o questionário atual será substituído.'`. Sucesso: `back()` com `sucesso` = `ImportacaoEditalController::MENSAGEM_INICIADA`; falha ao lançar: importação com erro `ImportacaoEditalController::ERRO_AO_INICIAR`.
  - `ImportarQuestionarioRequest`: `arquivo` `['required', 'file', 'extensions:pdf,docx', 'max:'.(14 MB em KB)]` (`ConfiguracaoIa::atual()->tamanhoMaximoPdfMb()`), `substituir` `['boolean']`; `after()` confere o conteúdo (o `finfo` pode ver um .docx como `application/zip`, então nada de `mimes:docx`): `.pdf` precisa ter `getMimeType() === 'application/pdf'`; `.docx` precisa abrir com `ZipArchive` e ter `word/document.xml`. Mensagens `O arquivo precisa ser PDF ou Word (.docx).` (extensão e conteúdo), `O arquivo deve ter no máximo 14 MB.`, `Não foi possível receber o arquivo. Confira o tamanho e tente de novo.`; método `tipoDoArquivo(): string` (`pdf`|`docx`, pela extensão).

- [ ] **Step 1: Testes** (setup como `ImportacaoEditalTest`: `Storage::fake('local')`, `Http::preventStrayRequests()`, Gemini principal com chave falsa, lançador trocado por um que roda `Artisan::call('questionarios:analisar', ...)`; edital com categorias "Teatro" e "Dança"; `.docx` montado com `ZipArchive`):
  - `test_pdf_monta_o_questionario_com_vinculos_condicoes_limites_e_declaracao` — pedido com `inline_data` `application/pdf`.
  - `test_docx_vai_como_texto_sem_arquivo` — `Http::assertSent`: sem `inline_data`, texto com `[x]` e `<<<INICIO DO FORMULARIO>>>`.
  - `test_openai_recebe_o_docx_so_como_input_text`.
  - `test_prompt_leva_categorias_e_naturezas_do_edital`.
  - `test_substitui_o_questionario_existente_so_com_confirmacao`.
  - `test_falha_da_ia_deixa_o_questionario_intacto` (e o erro gravado é o motivo em português, sem a chave).
  - `test_docx_sem_document_xml_e_recusado_no_envio` e `test_docx_ilegivel_na_analise_grava_o_erro_de_leitura_e_nao_chama_a_ia` (zip com `word/document.xml` malformado: erro `TextoDoDocx::ERRO_ARQUIVO`).
  - `test_sem_categoria_ou_travado_ou_em_andamento_recusa_antes_de_guardar_o_arquivo`.
  - `test_trava_durante_a_analise_nao_substitui` — o lançador falso cria inscrição com `numero` antes do `Artisan::call`; importação termina com o aviso da trava e o questionário antigo fica.
  - `test_arquivo_que_nao_e_pdf_nem_docx_e_recusado` e `test_acima_de_14_mb_e_recusado`.
  - `test_consulta_da_situacao_e_marcar_como_conferida` e `test_proponente_nao_acessa`.
- [ ] **Step 2:** Rodar — falha. **Step 3:** Implementar. **Step 4:** Este arquivo passa; `tests/Feature/Retaguarda/ImportacaoEditalTest.php` continua passando.
- [ ] **Step 5:** `pint`; commit `feat: importação do questionário do edital por IA`.

---

### Task 9: Importar com IA na aba Questionário

**Files:**
- Create: `resources/js/Components/Retaguarda/Edital/ImportarQuestionario.vue`
- Modify: `app/Http/Controllers/Retaguarda/EditalController.php` (`edit`), `resources/js/Components/Retaguarda/Edital/QuestionarioEdital.vue`, `resources/js/Pages/Retaguarda/Editais/Form.vue`, `resources/js/types/index.ts`
- Test: `tests/Feature/Retaguarda/ImportacaoQuestionarioTest.php` (acrescentar)

**Interfaces:**
- Consumes: Task 8 (rotas, `ImportacaoQuestionario`), `QuadroImportacao.vue` (reaproveitado com `abas=['questionario']`).
- Produces (props de `edit`): `importacaoQuestionario: {id: int, situacao: string, erro: ?string, arquivoNome: string, enviadaEm: string} | null` (a mais recente do edital, sem CLOB); `quadroQuestionario` no formato `ImportacaoDoEdital` (confiabilidade com um bloco `questionario` rotulado `Questionário`, atenção com `blocoRotulo` `Questionário`) enquanto a última concluída não foi conferida, senão `null`; `importacaoQuestionarioConfigurada: bool` (`provedoresDaImportacao() !== []`).

- [ ] **Step 1: Teste** `test_tela_do_edital_traz_a_ultima_importacao_e_o_quadro_ate_conferir` — depois de importar: `importacaoQuestionario.situacao === 'concluida'`, `quadroQuestionario.confiabilidade.geral === 85`; depois de conferida, `quadroQuestionario === null`. Rodar — falha.
- [ ] **Step 2: Implementar as props.** Rodar — passa.
- [ ] **Step 3: `ImportarQuestionario.vue`** no molde de `ImportarEdital.vue`: botão "Importar com IA" desabilitado, com o motivo visível, quando o edital não está salvo, não tem categoria salva, está travado ou o formulário tem alterações não salvas (`Salve o edital antes de importar.`); `<dialog>` com o arquivo (PDF ou .docx, até 14 MB, conferido no navegador) e, se já há seções, `Interruptor` obrigatório "Substituir o questionário atual" (`substituir`); envio com `forceFormData` e `preserveState`. Linha de situação da última importação; enquanto `analisando`, consulta a rota `show` a cada 5 s (`fetch`, como a consulta de `Pages/Portal/InscricaoDocumentos.vue`); ao concluir, mensagem `Questionário importado.` com botão "Ver o questionário importado" que recarrega a página; com erro, mostra o erro. `QuadroImportacao` na aba quando há `quadroQuestionario`, com `urlConferida` da rota nova.
- [ ] **Step 4: Verificar.** `npm run build` passa; no navegador, o botão explica cada bloqueio, a janela abre/fecha pelo teclado e o envio sem provedor mostra a recusa.
- [ ] **Step 5:** `pint`; commit `feat: importar o questionário com IA pela aba Questionário`.

---

### Task 10: Respostas e visibilidade na inscrição

**Files:**
- Create: migration `create_inscricao_respostas_table`; `app/Models/InscricaoResposta.php` + factory; `app/Support/Inscricao/QuestionarioDaInscricao.php`; `app/Support/Inscricao/PassosDaInscricao.php`; `app/Http/Requests/SalvarRespostasRequest.php`; `app/Http/Controllers/InscricaoRespostaController.php`
- Modify: `app/Models/Inscricao.php` (`respostas()`), `app/Http/Controllers/InscricaoController.php` (`update` descarta respostas), `routes/web.php` (grupo do `InscricaoController`)
- Test: `tests/Feature/Portal/QuestionarioDaInscricaoTest.php`

**Interfaces:**
- Consumes: Task 3 (`EditalPergunta::valePara`, `chavesDasOpcoes`, `limiteDeCaracteres`, `TipoPergunta`).
- Produces:
  - `inscricao_respostas`: `inscricao_id` fk cascade, `edital_pergunta_id` fk cascade, `texto` longText null, `opcoes` string(2000) null (JSON `list<chave>`), `outro` string(1000) null, timestamps; `unique([...], 'inscricao_respostas_unica')`. `InscricaoResposta` com `opcoes` cast `array`.
  - Valor de uma resposta (servidor e tela): `array{texto: ?string, opcoes: list<string>, outro: ?string}`. Textos, data (`Y-m-d`) e número em `texto`; escolha única, múltipla, sim/não (`sim`/`nao`) e declaração (`declaro`) em `opcoes`; "Qual?" em `outro`.
  - `QuestionarioDaInscricao` (`__construct(private Inscricao $inscricao)`): `perguntasQueValem(): Collection<int, EditalPergunta>` (ordem global, com `valePara` pela categoria e natureza da inscrição); `respostasSalvas(): array<int, array{texto: ?string, opcoes: list<string>, outro: ?string}>`; `visiveis(array $respostas): Collection<int, EditalPergunta>` (na ordem; com condição, só se a origem é visível e a resposta dela tem alguma de `condicao_opcoes`); `secoesVisiveis(array $respostas): Collection<int, EditalQuestionarioSecao>` (com ao menos uma visível); `faltando(array $respostas, ?int $secaoId = null): array<string, string>` (chaves `respostas.{id}` e `respostas.{id}.outro`; mensagens `Responda esta pergunta.`, `Marque a declaração para continuar.`, `Diga qual.`); `descartarRespostasQueNaoValem(): void` (apaga respostas de perguntas que não valem ou não estão visíveis, recalculando até estabilizar); `public static function normalizar(EditalPergunta $pergunta, mixed $entrada): ?array` (null = sem resposta; `\r\n` → `\n`, `trim`, `''` → null; opções fora da pergunta saem; `outro` só fica se a opção "Outro" foi escolhida).
  - `PassosDaInscricao` (final, estático): `ESCOLHAS = 'escolhas'`, `DOCUMENTOS = 'documentos'`, `REVISAO = 'revisao'`; `idDaSecao(int $id): string` (`secao-{id}`); `para(Inscricao $inscricao, QuestionarioDaInscricao $questionario): list<array{id: string, rotulo: string, completo: bool}>` — Escolhas (completo se o edital não tem categorias ou a inscrição tem categoria), uma por seção visível com as respostas salvas (rótulo = título; completo se `faltando` da seção vazio), Documentos (rótulo "Documentos"; completo se nenhum obrigatório sem arquivo), Revisão (rótulo "Revisão e envio"; nunca completo); `atual(array $passos, ?string $pedido): string` (o pedido vale se existe e todos os anteriores estão completos; senão o primeiro incompleto); `seguinte(array $passos, string $id): string`.
  - Rota `PUT /portal/inscricoes/{inscricao}/secoes/{secao}` → `InscricaoRespostaController@update` (`portal.inscricoes.secoes.update`, `whereNumber(['inscricao', 'secao'])`, mesmo grupo de middlewares).
  - `SalvarRespostasRequest`: `authorize` com `Gate::authorize('gerenciar', $inscricao)`; regras montadas das perguntas da seção que valem para a inscrição (outras chaves ignoradas), depois de `\r\n` → `\n` em `prepareForValidation`: texto `['nullable', 'string', 'max:<limite>']` (`Use no máximo :max caracteres.`); data `date_format:Y-m-d` (`Digite uma data válida.`); número `numeric`, `between:-999999999999,999999999999` (`Informe um número.`); opções `array` (`max:1` fora da múltipla) e cada uma `Rule::in(chavesDasOpcoes())` (`Escolha uma opção da lista.`); `outro` `['nullable', 'string', 'max:1000', new TamanhoEmBytes(1000)]`.
  - `InscricaoRespostaController::update(SalvarRespostasRequest $request, Inscricao $inscricao, EditalQuestionarioSecao $secao): RedirectResponse` — seção de outro edital → 404; fora do período → `portal.editais` com `PortalController::AVISO_INSCRICOES_FECHADAS`; não rascunho (relido com `lockForUpdate`) → `show` com `InscricaoController::ERRO_REABRIR`; na transação grava as respostas da seção (normalizadas; null apaga) e chama `descartarRespostasQueNaoValem()`; depois do commit, `faltando` da seção não vazio → `ValidationException` com esses erros (o que veio fica salvo); senão redireciona a `show` com `passo` = `PassosDaInscricao::seguinte(...)`.
  - `InscricaoController::update` chama `descartarRespostasQueNaoValem()` na mesma transação, depois de salvar as escolhas.

- [ ] **Step 1: Testes** (proponente `comPerfil()`, edital aberto com categorias "Teatro" e "Dança", seção "Proposta"):
  - `test_vale_so_o_que_o_vinculo_inclui_para_a_categoria_e_a_natureza`.
  - `test_condicao_mostra_so_com_a_resposta_escolhida_e_em_cadeia` — origem oculta esconde a dependente da dependente.
  - `test_salvar_a_secao_grava_e_avanca_para_o_passo_seguinte`.
  - `test_secao_incompleta_salva_o_que_veio_e_volta_com_os_erros` — `assertSessionHasErrors(['respostas.<id>' => 'Responda esta pergunta.'])`, a resposta enviada está gravada.
  - `test_limite_conta_caracteres_com_acento_emoji_e_quebra_de_linha` — limite 5: `"ção\r\n😀"` (5 caracteres depois da troca) aceito; `"ação😀!"` (6) recusado e nada gravado.
  - `test_outro_escolhido_em_obrigatoria_exige_qual` e `test_declaracao_precisa_ser_marcada`.
  - `test_opcao_fora_da_lista_data_invalida_e_numero_invalido_sao_recusados`.
  - `test_trocar_a_resposta_da_origem_apaga_a_resposta_que_ficou_oculta`.
  - `test_trocar_a_categoria_apaga_respostas_que_deixaram_de_valer` (pelo `PUT` das escolhas).
  - `test_secao_de_outro_edital_404_outro_usuario_404_finalizada_e_fora_do_periodo_recusam`.
  - `test_passos_pulam_secao_sem_pergunta_visivel_e_param_no_primeiro_incompleto` — `atual(passos, 'revisao')` com seção incompleta devolve a seção; rascunho sem pedido abre no primeiro incompleto.
- [ ] **Step 2:** Rodar — falha. **Step 3:** Implementar. **Step 4:** Este arquivo passa; `tests/Feature/Portal/InscricaoTest.php` continua passando.
- [ ] **Step 5:** `pint`; commit `feat: respostas do questionário na inscrição`.

---

### Task 11: Passos, finalizar, retrato e perfil na inscrição

**Files:**
- Create: migration `add_perfil_to_inscricoes_table`
- Modify: `app/Models/Inscricao.php` (`perfil` fillable + cast `array`; `pendenciasParaFinalizar`), `app/Http/Controllers/InscricaoController.php` (`store`, `show`, `update`, `finalizar`), `app/Http/Controllers/PortalController.php` (`inscricao`), `tests/Feature/Portal/InscricaoTest.php`, `tests/Feature/Portal/FinalizarInscricaoTest.php`, `tests/Feature/Portal/InscricaoDocumentosTest.php` (usuários `comPerfil()`, redirecionamentos com `passo`, componente renomeado)
- Test: `tests/Feature/Portal/FinalizarInscricaoTest.php`, `tests/Feature/Portal/InscricaoTest.php`

**Interfaces:**
- Consumes: Task 10 (`QuestionarioDaInscricao`, `PassosDaInscricao`), Task 2 (`User::perfilCompleto()`, `PessoaFisica::retratoDoPerfil()`, `UserFactory::comPerfil()`).
- Produces:
  - `inscricoes.perfil` longText null (cast `array`): retrato de `PessoaFisica::retratoDoPerfil()` do dono (`inscricoes.user_id`), refeito a cada finalização.
  - `Inscricao::pendenciasParaFinalizar()` acrescenta, depois dos documentos, uma linha por seção visível com falta: `Responda as perguntas obrigatórias de "<título da seção>".`
  - `InscricaoController::AVISO_PERFIL_INCOMPLETO = 'Complete o seu perfil no cadastro de Pessoa Física para se inscrever.'`; `store` com perfil incompleto → `redirect()->route('cadastro')->with('aviso', ...)`; `finalizar` com perfil incompleto → `show` com `erro` = o aviso (rascunhos abrem normalmente).
  - `show` renderiza `Portal/InscricaoPassos` com as props atuais mais: `passos` (de `PassosDaInscricao::para`), `passoAtual` (`atual` com `?passo=`; finalizada ou fora do período → `revisao`), `secoes: list<{id, titulo, descricao, perguntas: list<{id, enunciado, ajuda, tipo, obrigatoria, limite: ?int, opcoes: list<{chave, texto, outro}>, condicao: {pergunta: int, opcoes: list<string>}|null}>}>` (todas as seções com perguntas que valem, para a tela recalcular a visibilidade), `respostas: array<int, valor>`, `perfilIncompleto: bool`. Sim/não e declaração vão com `opcoes` vazio (a tela usa as chaves fixas).
  - `update` (escolhas) redireciona a `show` com `passo` = `PassosDaInscricao::seguinte(..., 'escolhas')`.
  - `PortalController@inscricao` passa `perfilIncompleto: bool`.

- [ ] **Step 1: Testes:**
  - `test_nao_finaliza_com_obrigatoria_visivel_sem_resposta` — pendência `Responda as perguntas obrigatórias de "Proposta".`
  - `test_obrigatoria_oculta_ou_de_outra_categoria_nao_impede_finalizar`.
  - `test_finalizar_guarda_o_retrato_do_perfil_e_finalizar_de_novo_refaz` — muda a escolaridade, reabre, finaliza: retrato novo.
  - `test_retrato_e_do_dono_mesmo_em_inscricao_de_organizacao`.
  - `test_perfil_incompleto_nao_cria_inscricao_e_leva_ao_cadastro` e `test_perfil_incompleto_nao_finaliza`.
  - `test_rascunho_com_perfil_incompleto_abre_com_o_aviso` — `perfilIncompleto === true`.
  - `test_show_traz_passos_secoes_respostas_e_o_passo_atual` — `component('Portal/InscricaoPassos')`, ids `['escolhas', 'secao-<id>', 'documentos', 'revisao']`, `?passo=revisao` com seção incompleta abre a seção.
  - `test_salvar_escolhas_avanca_para_o_passo_seguinte`.
  - `test_pagina_de_inscricao_do_edital_avisa_perfil_incompleto`.
  - Ajustar os testes existentes desses três arquivos (`comPerfil()`, `assertRedirect` com `passo`, componente `Portal/InscricaoPassos`).
- [ ] **Step 2:** Rodar cada arquivo — falham. **Step 3:** Implementar. **Step 4:** `InscricaoTest`, `FinalizarInscricaoTest`, `InscricaoDocumentosTest`, `QuestionarioDaInscricaoTest` passam (um por vez).
- [ ] **Step 5:** `pint`; commit `feat: passos da inscrição, retrato e perfil ao finalizar`.

---

### Task 12: Inscrição em passos no portal

**Files:**
- Rename: `resources/js/Pages/Portal/InscricaoDocumentos.vue` → `resources/js/Pages/Portal/InscricaoPassos.vue` (`git mv`)
- Create: `resources/js/Components/Inscricao/questionario.ts`, `resources/js/Components/Inscricao/useSituacaoDosDocumentos.ts`, `resources/js/Components/Inscricao/IndicadorDePassos.vue`, `resources/js/Components/Inscricao/PassoDaSecao.vue`, `resources/js/Components/Inscricao/PerguntaDaInscricao.vue`, `resources/js/Components/Inscricao/RevisaoDaInscricao.vue`
- Modify: `resources/js/Pages/Portal/Inscricao.vue` (aviso de perfil), `resources/js/types/index.ts` (`PortalInscricaoDocumentosProps` → `PortalInscricaoPassosProps`; `PassoDaInscricao`, `SecaoDaInscricao`, `PerguntaDaInscricao`, `RespostaDaInscricao`; `perfilIncompleto` em `PortalInscricaoProps`)

**Interfaces:**
- Consumes: Task 11 (props), Task 10 (rota `PUT .../secoes/{secao}`, formato da resposta), Task 1 (`Interruptor`).
- Produces:
  - `questionario.ts`: `normalizarTexto(texto: string): string` (`\r\n` → `\n`); `caracteres(texto: string): number` (`[...normalizarTexto(texto)].length`); `perguntasVisiveis(secoes: SecaoDaInscricao[], respostas: Record<number, RespostaDaInscricao>): Set<number>` (mesma regra de `QuestionarioDaInscricao::visiveis`); `chavesDaPergunta(p): string[]`; `textoDaResposta(p, r): string` (para a revisão: rótulos das opções, "Sim"/"Não", `Declarado`, data em `dd/mm/aaaa`, "Outro: <qual>").
  - `useSituacaoDosDocumentos(props)`: o que hoje está em `InscricaoDocumentos.vue` (cópia local das situações, consulta a cada 5 s enquanto há conferência, `pendencias`, `podeFinalizar`).

- [ ] **Step 1: Página e indicador.** `InscricaoPassos.vue` mostra o `FaixaPortal`, o aviso de perfil incompleto (com link para `/cadastro`) e `IndicadorDePassos` (`<ol>`, passo atual com `aria-current="step"`, concluídos como links `?passo=<id>`, futuros sem link), e só o passo atual. Rodapé de cada passo: "Voltar" (link para o passo anterior) e "Avançar". Inscrição finalizada ou fora do período: só a revisão, em leitura, com "Reabrir para editar" quando `podeReabrir` (diálogo atual).
- [ ] **Step 2: Escolhas.** `EscolhasDaInscricao` atual; "Avançar" faz o `PUT` das escolhas (o servidor leva ao passo seguinte); com erro, `focarPrimeiroErro`.
- [ ] **Step 3: Seção.** `PassoDaSecao` com `useForm({ respostas })` das respostas salvas das perguntas da seção; só as visíveis (`perguntasVisiveis` sobre as salvas + as desta seção, recalculando a cada mudança). `PerguntaDaInscricao` por tipo, todos com `CampoCadastro` (asterisco nas obrigatórias, erro de `respostas.<id>`): texto curto `<input>` e longo `<textarea>` com contador `N de L caracteres` (`caracteres`), sem cortar o que se digita; escolha única em grupo de rádio (`<fieldset>` com `legend`, `campo-invalido` no erro); múltipla escolha com um `Interruptor` (variante `linha`) por opção; "Outro" escolhido abre o texto "Qual?" (obrigatório quando a pergunta é); sim/não com `Interruptor` (desligado = "Não"; a tela sempre envia `['sim']` ou `['nao']` para a pergunta visível); declaração com `Interruptor` cujo rótulo é o enunciado; data `type="date"`; número `inputmode="decimal"`. "Avançar" faz o `PUT .../secoes/<id>` só com as respostas das perguntas visíveis da seção.
- [ ] **Step 4: Documentos.** `DocumentoExigido` atual com `useSituacaoDosDocumentos`; "Avançar" confere na tela os obrigatórios sem arquivo — se houver, mostra `Anexe este documento para continuar.` em cada um e foca o primeiro; senão vai para `?passo=<seguinte>` (o servidor confere de novo).
- [ ] **Step 5: Revisão.** `RevisaoDaInscricao`: escolhas, respostas visíveis por seção (`textoDaResposta`), documentos e situação, lista de pendências (`aria-live`) e "Finalizar inscrição" (lógica atual).
- [ ] **Step 6: Verificar.** `npm run build` passa; `art test --compact tests/Feature/Portal/InscricaoTest.php` passa. No navegador (dev, proponente com perfil completo): criar inscrição, passar por escolhas → seções → documentos → revisão; tentar avançar com obrigatória vazia (erros no passo); responder a origem e ver a dependente aparecer e sumir; voltar, trocar a categoria e ver os passos seguintes refeitos; finalizar; reabrir. Proponente de perfil incompleto: aviso e link para o cadastro. Navegação só por teclado em todos os passos.
- [ ] **Step 7:** Commit `feat: inscrição em passos com o questionário do edital`.

---

### Task 13: Verificação final

**Files:** nenhum novo (só correções que a verificação apontar).

- [ ] **Step 1:** `pint` em todos os PHP alterados no branch (`git diff --name-only master -- '*.php'`).
- [ ] **Step 2:** `npm run build` — sem erro de vue-tsc.
- [ ] **Step 3:** Suíte completa, sozinha: `art test --compact` — tudo passa.
- [ ] **Step 4: PostgreSQL descartável.** `docker run -d --name pg-questionario -e POSTGRES_PASSWORD=teste -e POSTGRES_DB=mapa -p 127.0.0.1:55432:5432 postgres:16`; no worktree, com o PHP da máquina (junção `vendor` → `E:/Dev/Mapa_Cultura/vendor` se faltar): `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55432 DB_DATABASE=mapa DB_USERNAME=postgres DB_PASSWORD=teste php artisan migrate --force`, depois `migrate:rollback --step=5 --force` (as cinco migrations novas) e `migrate --force` de novo — sem erro; `docker rm -f pg-questionario`. Não tocar no `sie_pg_homolog`.
- [ ] **Step 5:** Banco Oracle de dev: nada aqui. As migrations só rodam nele depois do merge e com o OK do usuário (`php artisan migrate`, sem `migrate:fresh`).
- [ ] **Step 6:** Commit das correções, se houver (`fix: ...`).
