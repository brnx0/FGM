# Períodos do edital (fases por chamada) e importação por IA sem configuração na tela

Data: 2026-10-29. Base: `master` em `d4a26a3`.

## Por quê

O próximo projeto é a inscrição no portal, seguida da validação e da homologação das inscrições. Hoje o edital só guarda
o período de inscrição (por chamada). A validação e a homologação existem apenas como texto na seção informativa
"Etapas e prazos". O sistema precisa conhecer as datas de cada fase: por enquanto para registro e exibição na
retaguarda, e mais à frente para liberar e bloquear ações (envio de correções, recursos etc.) dentro de cada período.

Além disso, a equipe não deve mexer nos parâmetros da importação por IA: o prompt, a temperatura e o tamanho máximo do
PDF passam a ficar só no código.

## Parte 1: Seção 3 da Integração com IA sai da tela

Hoje a tela Integração com IA (`resources/js/Pages/Retaguarda/IntegracaoIa.vue`) tem a seção 3, "Importação de
editais", com o prompt modelo (editável e com restauração), a temperatura e o tamanho máximo do PDF, gravados em
`configuracoes_ia` (`importacao_prompt`, `importacao_temperatura`, `importacao_tamanho_maximo_mb`).

Decidido:

- A seção 3 some da tela. As seções 1 e 2 (do Daniel) não mudam.
- A importação usa sempre: o prompt de `App\Support\EditalIa\PromptModelo` (as partes `editavel()` e `fixo()`
  continuam existindo e sendo enviadas juntas, como hoje), temperatura 0,1 e PDF de até 14 MB (o teto que já existe,
  `ConfiguracaoIa::TAMANHO_MAXIMO_PDF_MB`).
- Saem: as rotas `retaguarda.integracao-ia.importacao` e `retaguarda.integracao-ia.importacao.prompt-modelo`, os
  métodos `importacao` e `restaurarPrompt` do `IntegracaoIaController`, o `SalvarImportacaoIaRequest`, a prop
  `importacao` da página e os testes dessa seção.
- Uma migration nova apaga as três colunas de `configuracoes_ia`, descartando o que estiver gravado (decidido: não
  preservar; o prompt nunca foi alterado). Nenhum seeder ou factory usa essas colunas. Ela precisa rodar em Oracle e em PostgreSQL (há
  colegas em PostgreSQL). O `down` recria as colunas nulas.
- Os pontos que leem esses valores (`AnalisarEditalCommand`, `ImportarEditalRequest`,
  `Retaguarda\EditalController` para o `tamanhoMaximoPdfMb`) passam a ler as constantes. Os nomes dos métodos de
  `ConfiguracaoIa` (`promptDaImportacao`, `temperaturaDaImportacao`, `tamanhoMaximoPdfMb`) podem continuar existindo,
  devolvendo os valores fixos, ou ir para outra classe: escolha do construtor.

Mudar o prompt passa a ser alteração de código, enviada por commit.

## Parte 2: Períodos do edital

### Modelo

- Chamadas continuam. Um edital tem uma ou mais chamadas; a maioria tem uma só ("Chamada única"). Categorias, cotas
  por chamada e "limite por chamada" continuam como estão.
- Cada chamada tem **fases**: **Inscrição**, **Validação** e **Homologação**, cada uma com início e fim (datas, dia
  civil, como as de hoje).
- A lista de fases é fixa e conhecida pelo sistema (vai ser usada para bloquear ações). Uma fase de **Convocação**
  talvez venha depois, sem certeza: o desenho deve permitir acrescentá-la sem retrabalho grande, mas ela não entra
  agora.
- As datas atuais da chamada (`edital_chamadas.inicio` e `fim`) são a fase de Inscrição.
- Inscrição: obrigatória para publicar, como hoje (pendência "Cadastre ao menos uma chamada com início e fim das
  inscrições"; `inscricoes_inicio`/`inscricoes_fim` do edital continuam derivados das chamadas pelo
  `SincronizadorDoEdital`).
- Validação e Homologação: opcionais. Ficam vazias quando o edital não traz as datas (é comum o edital dizer "X dias
  úteis após" a etapa anterior); a equipe preenche depois, inclusive com o edital já publicado. Não entram nas
  pendências para publicar.
- Cada fase tem as duas datas ou nenhuma, e o fim é igual ou posterior ao início. A Inscrição continua com as duas
  datas obrigatórias em toda chamada salva pelo formulário, como hoje (`chamadas.*.inicio` e `fim` são `required` no
  `SalvarEditalRequest`); a regra "as duas ou nenhuma" vale para Validação e Homologação, em todo salvamento.

Chamada de projeto (minha, pode ser revista pelo construtor): guardar Validação e Homologação como colunas novas em
`edital_chamadas` (`validacao_inicio`, `validacao_fim`, `homologacao_inicio`, `homologacao_fim`), mantendo `inicio`/`fim`
como a Inscrição. É o menor desvio do que existe. A alternativa, uma tabela de fases por chamada com a fase em um enum,
só compensa se a Convocação se confirmar; se o construtor achar que fica mais limpo já assim, pode escolher, desde que
os dados atuais sejam migrados.

Chamada minha: o sistema não impõe a ordem entre as fases (por exemplo, validação começar depois do fim da inscrição).
Os editais variam e uma regra errada impediria cadastrar o edital real. Se as datas estiverem fora de ordem, nada
bloqueia.

### Tela (retaguarda, formulário do edital)

- A aba **Inscrição** passa a se chamar **Períodos**. Nela fica cada chamada (nome) com as suas fases logo abaixo,
  cada fase com início e fim. O botão de adicionar chamada continua ali. O campo "Forma de inscrição no edital
  original" continua nessa aba.
- A aba "Homologação" que já existe (requisitos de homologação) não muda.
- Os erros dos campos novos contam na aba Períodos, como os de hoje (`chamadas.*`).

### Portal

Sem mudança: a página pública do edital e a home continuam mostrando só o período de inscrição. As datas novas só
aparecem no formulário do edital na retaguarda (props de edição); não entram nas props do portal.

### Importação por IA

- O esquema da resposta (`EsquemaDaResposta`, bloco `inscricao.chamadas`) ganha, em cada chamada, início e fim da
  validação e da homologação.
- O `EditalDaResposta` lê e normaliza esses campos como já faz com as datas da inscrição.
- O prompt modelo (`PromptModelo::editavel()`) orienta: registrar as fases que tiverem data; quando o edital só der um
  prazo relativo ("X dias úteis após"), deixar vazio e registrar ponto de atenção "não encontrado" citando o item.
- Uma importação antiga (resposta já guardada sem os campos novos) continua sendo lida sem erro: os campos ficam vazios.
- Chamada minha: se a IA trouxer só o início ou só o fim de Validação ou Homologação, a fase fica vazia (para não
  gravar meia fase, que a validação do formulário recusaria) e vira ponto de atenção "leitura incerta" dizendo a data
  que veio e o item do edital.

### Dados existentes

As chamadas já cadastradas ficam como estão: as datas atuais delas já são a Inscrição; Validação e Homologação nascem
vazias. Se o construtor optar pela tabela de fases, a migration copia `inicio`/`fim` para a fase de Inscrição.

## Fora do escopo

- Bloquear ações por período (vem com a inscrição, validação e homologação no portal).
- Mostrar Validação e Homologação no portal.
- A fase de Convocação.
- Qualquer mudança nas seções 1 e 2 da Integração com IA.

## Restrições do projeto

- Laravel 13, Inertia v2, Vue 3 com `lang="ts"`, Tailwind v4; `npm run build` roda o vue-tsc e precisa passar.
- Banco Oracle no projeto, mas migrations e SQL precisam funcionar também em PostgreSQL. Oracle: `''` vira NULL,
  ids voltam como string, DDL faz commit automático.
- PHP roda no container: `docker compose exec -T app php artisan ...`. A suíte completa roda sozinha (banco de testes
  compartilhado). Nunca rodar Prettier. Sem dependências novas. Nada de `migrate:fresh` no banco de dev.
- Testes de feature com PHPUnit para o comportamento novo e para o que sai (rotas da seção 3 deixam de existir).
- Seguir `.ai/rules` (ver `.ai/rules/index.md`).

## Pronto quando

- A tela Integração com IA não tem mais a seção 3, e a importação funciona com os valores fixos.
- O formulário do edital tem a aba Períodos com as fases de cada chamada, salvando e reabrindo as datas.
- Publicar continua exigindo só a Inscrição.
- A importação por IA preenche as fases quando o PDF traz as datas.
- As migrations rodam e voltam em Oracle e PostgreSQL; build e suíte completa passam.
