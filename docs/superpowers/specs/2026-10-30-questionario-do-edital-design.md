# Questionário do edital, importado por IA, e perfil no cadastro

Data: 2026-10-30. Base: `master` (inscrição com documentos já existe; ver
`docs/superpowers/specs/2026-10-29-inscricao-documentos-ia-design.md`).

## Por quê

Cada edital da FGM tem um formulário de inscrição (o "espelho do formulário", anexo do edital, em Word ou PDF) com
perguntas que o proponente responde ao se inscrever. Hoje a inscrição no portal só tem as escolhas (cadastro,
categoria, cotas) e os documentos. Falta o questionário. A equipe não quer digitar 50 a 80 perguntas por edital: a IA
lê o espelho e monta o questionário, e a equipe confere e ajusta, como já acontece na importação do edital.

Exemplos analisados: Pontos de Cultura (Anexo III), Samba Junino Ano VII e SalCine II.

## O que os espelhos têm, e o que vira o quê

| Tipo de pergunta no espelho | Destino |
|---|---|
| Identificação, endereço, contato, representante legal | Já vem do cadastro: fica fora do questionário |
| Natureza, categoria, cotas escolhidas | Já vem das escolhas da inscrição: fica fora |
| Uploads de anexos | Já são os documentos do edital: fica fora |
| Perfil: identidade de gênero, raça/cor, deficiência (e tipo), escolaridade, renda, povos e comunidades tradicionais, anos de atuação | **Cadastro da pessoa física** (respondido uma vez) |
| Perguntas do edital: categoria/modalidade da proposta, textos da proposta, listas de marcar, datas de execução, histórico em editais, "recebeu fomento nos últimos 5 anos", perguntas de cota que dependem de resposta | **Questionário do edital** |
| Declarações "DECLARO que…" | **Questionário do edital** (tipo declaração, obrigatória) |

"Recebeu fomento nos últimos 5 anos" é pergunta do edital, sempre que o espelho trouxer (decisão do usuário).

## Parte 1: Perfil no cadastro da pessoa física

`pessoas_fisicas` já tem `identidade_genero`, `raca_cor` e `pcd` (booleano). Entram:

- tipo de deficiência (só quando `pcd`): auditiva, física-motora, intelectual, visual, múltipla, transtorno do espectro
  autista, outra (com texto "qual");
- escolaridade: não possui educação formal; ensino fundamental incompleto ou equivalente; ensino fundamental completo
  ou equivalente; ensino médio incompleto ou equivalente; ensino médio completo ou equivalente; curso técnico
  incompleto; curso técnico completo; ensino superior incompleto ou equivalente; ensino superior completo;
  pós-graduação incompleto; pós-graduação completo;
- renda média individual: nenhuma renda; até 1 salário mínimo; de 1 a 3; de 3 a 5; de 5 a 8; de 8 a 10; acima de 10;
- povos e comunidades tradicionais: "não pertence" ou um da lista oficial usada no SalCine (o construtor copia a lista
  completa do espelho do SalCine, item "O proponente pertence a povos e comunidades tradicionais?");
- anos de atuação na área cultural: número inteiro, 0 ou mais.

Listas como enums em `app/Enums`, como `IdentidadeGenero` e `RacaCor`. Os campos entram no formulário de cadastro
e de edição da pessoa física, obrigatórios.

Mesmo na inscrição de PJ ou MEI, o perfil usado é o da pessoa física de quem está cadastrando (o usuário logado),
não o do representante legal da organização (decisão do usuário).

Chamada minha: cadastros já existentes ficam com os campos novos vazios (colunas nulas no banco). Ao iniciar uma
inscrição, quem tiver o perfil incompleto é levado a completar o cadastro antes, com um aviso; o resto do portal
segue funcionando.

## Parte 2: Questionário no edital (retaguarda)

### Estrutura

- O questionário é do edital, dividido em **seções** (título, descrição opcional, ordem).
- Cada **pergunta** tem: enunciado; texto de ajuda opcional; tipo; obrigatória ou não; limite de caracteres (textos);
  opções (escolha única e múltipla), com uma opção marcável como "Outro", que abre um texto "qual"; ordem.
- **Tipos**: texto curto, texto longo, escolha única, múltipla escolha, sim/não, data, número, declaração (texto que o
  proponente precisa marcar; sempre obrigatória).
- **Vínculo**: cada pergunta vale para **todas as categorias** ou para **algumas** categorias do edital, e dentro delas
  para **todas as naturezas** ou **algumas**. As naturezas oferecidas são só as que as categorias escolhidas aceitam
  (a categoria já guarda as naturezas que aceita; vazia = as do edital).
- **Sem vínculo**: estado de uma pergunta que ainda não foi ligada a categorias (nem "todas", nem alguma). Aparece
  destacada na tela ("Vincule às categorias"). **Edital com pergunta sem vínculo não publica**: entra em
  `Edital::pendenciasParaPublicar()` com a mensagem dizendo quantas perguntas faltam vincular, e a aba mostra o
  contador de pendência como as outras abas mostram erros.
- **Condição por resposta** (opcional): a pergunta só aparece quando outra pergunta anterior do mesmo questionário teve
  uma das respostas escolhidas (ex.: "Qual deficiência?" só para quem respondeu "Sim"; "Nomes das pessoas negras…" só
  para quem escolheu a modalidade "Negros"). Vale para perguntas de escolha única, múltipla e sim/não como origem.
- Não há condição por cota escolhida (decisão do usuário).

### Tela

Aba nova **Questionário** no formulário do edital, depois de Documentos:

- seções e perguntas em cartões, no padrão das listas editáveis do formulário (`ListaEditavel`), com reordenação;
- edição de todos os campos acima; vínculo por categorias e naturezas; condição por resposta;
- **vínculo em lote**: selecionar várias perguntas (ou uma seção inteira) e aplicar categorias e naturezas de uma vez;
- botão **Importar com IA** (Word .docx ou PDF).

Sim/não, declarações e opções de múltipla escolha usam **toggle**, nunca checkbox (regra do projeto). Vale para a
retaguarda e para o portal. Há um toggle em `resources/js/Pages/Retaguarda/IntegracaoIa.vue` (`role="switch"`) que deve
virar componente reutilizável.

Chamada minha: depois que o edital tem a primeira inscrição finalizada, o questionário fica travado para edição (a
tela avisa o motivo). Mudar perguntas com respostas já enviadas deixaria respostas sem pergunta ou perguntas
obrigatórias sem resposta.

### Importação por IA

- Mesma base da importação do edital: análise em segundo plano sem fila (processo destacado), provedores da
  Integração com IA com redundância (`AnaliseComRedundancia`), mensagens de falha de `FalhaNaIa`, nota de
  confiabilidade e pontos de atenção, prompt e parâmetros fixos no código (temperatura 0,1; arquivo até 14 MB).
- **PDF** vai para a IA como arquivo. **Word (.docx)**: o servidor extrai o texto (com a extensão zip do PHP, sem
  dependência nova), mantendo a ordem dos parágrafos e das células de tabela e marcando as caixas de seleção, e envia o
  texto.
- A IA recebe também os nomes das categorias do edital e as naturezas de cada uma. Por isso **importar exige ao menos
  uma categoria cadastrada** (o botão explica quando falta).
- A IA **não inclui** o que já vem do cadastro, das escolhas da inscrição ou dos documentos (tabela acima), nem as
  instruções gerais do formulário ("Antes de começar…", "Prepare um rascunho…").
- **Vínculo na importação**: quando o espelho diz para quais categorias ou naturezas uma pergunta vale ("apenas para as
  categorias…", "No caso de Pessoa Jurídica…"), a IA vincula pelo nome; nome que não bate com nenhuma categoria vira
  ponto de atenção e a pergunta fica sem vínculo. Quando o espelho não diz, a pergunta fica **sem vínculo** (a IA não
  chuta "todas"); quem vincula é a equipe. Quando não diz a natureza, vale para todas as naturezas.
- Condições por resposta descritas no espelho ("só deve aparecer se a resposta anterior for Sim") viram a condição da
  pergunta.
- Limites de caracteres e obrigatoriedade (`*`) do espelho são preservados.
- Resultado: o questionário importado entra no edital (sem publicar nada), com o quadro de confiabilidade e pontos de
  atenção na aba Questionário. Chamada minha: se o edital já tem questionário, importar pede confirmação e **substitui**
  o questionário inteiro.

## Parte 3: Questionário na inscrição (portal)

- **A inscrição passa a ser sequencial**, em passos, e não mais uma página com rolagem (decisão do usuário): uma
  seção por vez, e só avança quando a seção atual está preenchida por completo. Os passos são: **Escolhas** (cadastro,
  categoria, cotas) → **uma etapa por seção do questionário** que tenha ao menos uma pergunta visível para aquele
  proponente → **Documentos** → **Revisão e envio**. Um indicador mostra os passos, o atual e os concluídos.
- "Completo" = todas as perguntas visíveis e obrigatórias da seção respondidas, limites respeitados, declarações
  marcadas; nos Documentos, todos os obrigatórios anexados (as regras atuais de finalizar sobre a checagem por IA
  continuam valendo no envio). O botão **Avançar** salva o passo no rascunho e só passa adiante se estiver completo;
  se não, mostra os erros do passo.
- Chamada minha: o proponente pode **voltar** a qualquer passo já concluído, e ao reabrir um rascunho continua do
  primeiro passo incompleto. Se, ao voltar, ele trocar uma escolha ou resposta que mude o que vale depois, os passos
  seguintes são conferidos de novo.
- O passo **Questionário** de antes vira essas etapas por seção.
- Mostra só as perguntas cujo vínculo inclui a categoria e a natureza da inscrição, e cuja condição por resposta está
  satisfeita (atualiza na hora, conforme o proponente responde).
- As respostas ficam salvas no rascunho da inscrição; ao trocar as escolhas, respostas de perguntas que deixaram de
  valer são descartadas. Ao finalizar, valem as regras atuais da inscrição mais: todas as perguntas **visíveis** e
  obrigatórias respondidas, limites de caracteres respeitados, declarações marcadas. Reabrir a inscrição permite editar
  as respostas, como os documentos.
- Respostas de perguntas que ficaram ocultas (condição deixou de valer) não contam e são descartadas ao salvar.

## Decisões de construção (minhas, revisáveis)

- **Respostas em tabela**, uma linha por inscrição e pergunta (valor em texto/JSON conforme o tipo), e não um JSON na
  inscrição: a fase de validação vai precisar filtrar e listar respostas.
- **Retrato do perfil**: ao finalizar, a inscrição guarda uma cópia do perfil da pessoa física (os sete campos do
  perfil), para que mudar o cadastro depois não altere uma inscrição enviada. Reabrir e finalizar de novo refaz a
  cópia.
- **Perfil incompleto** bloqueia criar a inscrição e finalizar; rascunhos existentes abrem normalmente com o aviso.
  O perfil é sempre o do usuário dono da inscrição (`inscricoes.user_id`).
- **Trava do questionário**: vale a partir da primeira inscrição do edital que já foi finalizada alguma vez
  (`finalizada_em` preenchido), mesmo que depois reaberta. Trava tudo: edição, vínculo em lote e importação. Sem
  exceção para a equipe.
- **Questionário é opcional**: edital sem perguntas publica (é o caso dos já publicados). A pendência só conta
  perguntas sem vínculo.
- **Vínculo** segue o padrão dos documentos do edital (`todas_categorias` + categorias ligadas); sem vínculo =
  `todas_categorias` falso e nenhuma categoria ligada. Naturezas: lista (vazia = todas as naturezas que as categorias
  vinculadas aceitam; categoria sem naturezas próprias aceita as do edital).
- **Condição por resposta** guarda a pergunta de origem e as opções pela chave estável da opção (não pelo texto). A
  origem precisa vir antes na ordem do questionário (o salvar recusa o contrário). Apagar a origem limpa a condição da
  dependente, com aviso; tirar uma opção da origem tira essa opção da condição. Na importação a IA cita a origem e as
  opções pelo texto, e o servidor resolve para as chaves; o que não resolver vira ponto de atenção e a pergunta fica
  sem condição.
- **"Outro"** pode ser usado em condição. Escolhido "Outro" numa pergunta obrigatória, o "qual" é obrigatório.
- **Conteúdo fora do modelo** no espelho (ficha técnica com várias pessoas, tabelas repetidas): a IA não cria pergunta
  e registra ponto de atenção. Notas "orientação para a plataforma" servem para a IA deduzir tipo, condição e limite,
  e não viram texto da pergunta.
- **Importação** em tabela própria (como `importacoes_edital`), por edital; o questionário só é substituído, numa
  transação, quando a análise termina com sucesso; falha deixa o questionário atual intacto.
- **Povos e comunidades tradicionais**: a lista do SalCine, como enum; sem texto livre além do que a lista tiver.
- **Inscrições já finalizadas** antes de existir questionário ficam sem respostas; como travam o questionário, um
  edital nessa situação não recebe questionário (a tela explica).

## Fora do escopo

- Tela da retaguarda para ver as respostas das inscrições (vem com a fase de validação).
- Comprovante em PDF com as respostas.
- Perguntas com anexo, tabela/repetição de itens (ex.: ficha técnica com várias pessoas) e pontuação automática.
- Condição por cota escolhida.

## Restrições do projeto

- Laravel 13, Inertia v2, Vue 3 `lang="ts"` (vue-tsc no build), Tailwind v4. Campos obrigatórios com o padrão do
  projeto (asterisco vermelho e destaque no erro).
- Oracle no projeto, e tudo também em PostgreSQL (há colegas nele). Oracle: `''` vira NULL, ids voltam como string,
  VARCHAR2 em bytes (usar a regra `TamanhoEmBytes`), CLOB fora das listagens.
- A chave da API da IA nunca sai do servidor. Testes sem chamada real (Http::fake).
- Sem dependências novas, sem Prettier, sem `migrate:fresh` no banco de dev.

## Pronto quando

- O cadastro da pessoa física tem os cinco campos novos, obrigatórios, e a inscrição pede para completar quem não tem.
- A aba Questionário cria, edita, reordena e vincula (um a um e em lote) seções e perguntas de todos os tipos, com
  condição por resposta, e trava após a primeira inscrição finalizada.
- Importar um .docx e um PDF de espelho monta o questionário com vínculos, condições, limites e declarações, e pontos
  de atenção; o que já vem do cadastro, das escolhas e dos documentos fica de fora.
- Edital com pergunta sem vínculo não publica e mostra a pendência.
- O proponente responde o questionário na inscrição, vendo só o que vale para ele, e só finaliza com as obrigatórias
  visíveis respondidas.
- Build, suíte completa e migrations em Oracle e PostgreSQL passam.
