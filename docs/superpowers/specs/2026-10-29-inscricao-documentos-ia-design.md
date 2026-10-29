# Inscrição no edital com envio de documentos e checagem por IA

## Por quê

O proponente já tem cadastro no portal (pessoa física e, se for o caso, organizações). Falta ele se inscrever num
edital aberto enviando os documentos que o edital exige. A FGM quer que cada documento seja conferido por IA no
momento em que é anexado, para pegar cedo arquivo errado ou ilegível, com rigor diferente por documento.

Esta entrega é a primeira parte da inscrição: **escolhas da inscrição + envio dos documentos + checagem por IA**.
O questionário (perguntas da inscrição) vem na sequência, em outra entrega.

## Objetivos

- Proponente cria a inscrição num edital com inscrições abertas, escolhe com qual cadastro se inscreve, a categoria
  e as cotas, e vê a lista dos documentos exigidos para essas escolhas.
- Cada documento anexado é conferido pela IA em segundo plano, conforme o modo de checagem do documento.
- A inscrição fica em rascunho até o proponente finalizar; pode ser reaberta (volta a rascunho) e finalizada de novo.
- Na retaguarda, cada documento do edital ganha o modo de checagem e uma instrução para a IA, que a IA pode gerar.

## Fora do escopo (próximas fases)

- Questionário da inscrição.
- Tela da retaguarda com as inscrições e os resultados da IA (fase de validação).
- Recurso, pendência, homologação, comprovante em PDF, e-mail de confirmação.
- Ligar áreas de atuação ao edital ou à inscrição.

## Decisões do usuário

- O proponente já está cadastrado; os dados dele vêm do cadastro.
- Antes da lista de documentos ele escolhe: **com qual cadastro** (a própria pessoa física ou uma organização dele),
  **a categoria** e **se concorre por cota**. Os documentos aparecem filtrados por essas escolhas.
- **Todos os documentos vão de uma vez na inscrição**: os das etapas Inscrição, Validação de cotas e Habilitação.
- Rascunho: ele anexa aos poucos; cada documento é conferido ao ser anexado. **Finalizar** só com todos os
  obrigatórios. Depois de finalizada ele pode **reabrir para editar**: ela volta a rascunho e só conta de novo quando
  ele finalizar outra vez.
- **Modos de checagem**, por documento do edital:

  | Modo | IA aprova | IA reprova | IA não consegue conferir (ilegível, serviço fora) |
  |---|---|---|---|
  | Rigoroso | segue | não segue | não segue |
  | Aceita sem validar | segue | segue, com alerta mostrando o problema; marcado para a equipe | segue, marcado para a equipe |
  | Não validar | sem checagem | — | — |

  "Não segue" = o proponente não consegue finalizar enquanto aquele documento não for aprovado (troca o arquivo ou
  pede a checagem de novo).
- **Instrução para a IA**, por documento: o roteiro do que conferir naquele arquivo. Serve à IA, não ao proponente.
  Na retaguarda há um botão para a IA gerar a instrução de um documento e outro para gerar a de todos de uma vez.
- Retaguarda de inscrições fica para a próxima fase.

## Como funciona

### Retaguarda: documentos do edital

Em `edital_documentos`, duas colunas novas:

- `checagem`: `rigorosa` | `aceita_sem_validar` | `nenhuma` (enum `ChecagemDocumento`). Documentos já cadastrados
  e os novos começam em `aceita_sem_validar` (*minha decisão*: não trava ninguém por padrão).
- `instrucao_ia`: texto (até 2000 caracteres), editável pela equipe.

Na aba Documentos do formulário do edital, cada documento mostra o modo (seleção) e a instrução (área de texto) com o
botão **Gerar com IA**; no topo da aba, **Gerar instruções de todos**. A geração usa o nome do documento, a orientação,
a etapa, o anexo de referência e o contexto do edital (título, objeto, naturezas, categorias); não envia o PDF do edital.
"Gerar de todos" é uma chamada só, que devolve a instrução de cada documento, e só preenche os que estão vazios
(*minha decisão*: não sobrescreve o que a equipe escreveu; o botão do documento sobrescreve aquele). As instruções
geradas aparecem no formulário para revisão e só são gravadas quando a equipe salva o edital.

A importação por IA não preenche `checagem` nem `instrucao_ia`.

### Portal: inscrição

Substitui o aviso de hoje em `/portal/editais/{edital}/inscricao` (`PortalController::inscricao`, `Portal/Inscricao.vue`).

1. **Escolhas**: cadastro (pessoa física do usuário ou organização em que ele é responsável), categoria do edital e
   cotas (as modalidades que o edital oferece; pode marcar mais de uma — *minha decisão*). Só aparecem cadastros cuja
   natureza o edital aceita, e só categorias que aceitam essa natureza. Salvar as escolhas cria a inscrição em rascunho.
   As escolhas podem ser trocadas enquanto for rascunho; os documentos que deixarem de ser exigidos são descartados.
2. **Documentos**: a lista dos documentos exigidos, de todas as etapas, filtrados por natureza, categoria e cota, com
   obrigatório/opcional, a orientação e o modelo para baixar. Cada um tem o campo de envio e a situação da checagem.
3. **Finalizar**: habilitado quando todos os obrigatórios estão anexados, nenhum está em conferência e nenhum
   rigoroso está sem aprovação. Finalizada, a tela mostra o número da inscrição e a data e hora do envio.
4. **Reabrir**: em inscrição finalizada, com aviso forte: "Ao reabrir, sua inscrição volta a ser rascunho e só conta
   se você finalizar de novo até DD/MM/AAAA."

Regras:

- **Chamada** (*minha decisão*): a inscrição pertence à chamada da categoria escolhida; categoria sem chamada, ou
  edital com uma chamada só, usa a única chamada. A inscrição guarda a chamada. Quando a branch dos Períodos entrar,
  o período vale pela fase de inscrição dessa chamada; até lá, pelas datas da chamada.
- Só com o edital visível e no período de inscrição **da chamada**. Fora do período nada se cria, edita, finaliza ou
  reabre; a inscrição fica como está (finalizada continua valendo; rascunho fora do prazo não conta). O aviso de
  reabrir mostra o fim do período da chamada.
- Limite `editais.inscricoes_por_proponente` (nulo = sem limite), contado por cadastro (CPF ou CNPJ), somando
  rascunhos e finalizadas, no edital — ou na chamada, quando `limite_por_chamada` é verdadeiro.
- **Dono**: a inscrição é só de quem a criou; só essa pessoa edita, finaliza, reabre e baixa os arquivos. Outro
  responsável da mesma organização, ao escolher esse CNPJ, vê que já existe inscrição dele no edital, com a situação
  (rascunho ou finalizada), sem poder abrir os documentos nem editar. Se o limite já foi atingido, não cria outra.
- **Natureza do cadastro** (*minha decisão*): pessoa física = `pessoa_fisica`; organização com `mei` = `mei`; com
  finalidade lucrativa = `com_fins`; senão `sem_fins`. Coletivo sem CNPJ se inscreve pela pessoa física de quem o
  representa.
- **Filtro dos documentos** (*minha decisão*): entra o documento cuja `naturezas` é nula ou contém a natureza do
  cadastro; com `todas_categorias` ou ligado à categoria escolhida; com `modalidade_cota` nula ou igual a uma das
  cotas marcadas. Sem cota marcada, os documentos de cota não aparecem. Edital sem cotas não mostra a escolha de cota.
  As cotas marcadas ficam numa coluna JSON da inscrição.
- Arquivos: **PDF, até 10 MB** (*minha decisão*; os editais pedem PDF), no disco `local` (privado, o mesmo do PDF do
  edital), em `inscricoes/{id}`, servidos só a quem pode ver a inscrição. Um arquivo por documento: anexar outro
  substitui o anterior e dispara nova checagem. Documento opcional pode ser removido. Arquivos substituídos, removidos
  ou descartados pela troca de escolhas são apagados do disco.
- A página de editais do portal mostra, em cada edital, se o proponente já tem inscrição e em que situação.

### Checagem por IA

- Ao anexar um documento com modo diferente de `nenhuma`, a checagem roda em segundo plano, sem fila, do mesmo jeito
  que a importação do edital (processo destacado com token de execução; considerada travada após alguns minutos), um
  processo por documento. A tela consulta a situação a cada poucos segundos enquanto houver documento conferindo, como
  a tela da importação.
- Sem chave de liga e desliga própria (*minha decisão*): se a FGM não autorizar a IA em algum documento, a equipe põe
  o documento em "Não validar". Com a IA sem configuração, valem as regras de "não consegue conferir".
- "Gerar instruções de todos" divide em lotes se o edital tiver muitos documentos.
- A IA recebe o PDF, o nome do documento, a instrução (ou, vazia, o nome e a orientação) e os dados da inscrição que
  ajudam a conferir (nome ou razão social, CPF ou CNPJ, natureza), e responde num esquema fixo: `aprovado` (sim/não)
  e `motivo` (texto curto, para o proponente).
- Situações do documento: `enviado` (sem checagem), `conferindo`, `aprovado`, `reprovado` (com motivo),
  `nao_conferido` (IA fora, sem configuração ou arquivo ilegível). O proponente pode pedir **Conferir de novo** em
  `reprovado` e `nao_conferido`.
- Usa a configuração da Integração com IA (principal e redundância). Mensagem ao proponente quando a IA falha:
  "Não foi possível conferir o documento agora." — sem detalhe técnico; o detalhe fica gravado para a equipe.
- Reaproveitar os analisadores (`AnalisadorOpenAi`, `AnalisadorGemini`) e a redundância. Hoje
  `AnaliseComRedundancia` confere a raiz do JSON do edital; o construtor deve tirar essa conferência de dentro dela
  (ou parametrizá-la) para servir às duas análises. A geração de instruções não envia arquivo: precisa de uma chamada
  só com texto nos analisadores.

### Dados

- `inscricoes`: edital, usuário, cadastro escolhido (pessoa física ou organização), categoria, cotas, situação
  (`rascunho` | `finalizada`), número (gerado ao finalizar pela primeira vez e mantido), finalizada_em.
- `inscricao_documentos`: inscrição, documento do edital, arquivo (caminho e nome original), situação da checagem,
  motivo, detalhe técnico da falha, provedor e modelo usados, token de execução, conferido_em.

## Restrições do projeto

- Laravel 13, Inertia v2, Vue 3 com TypeScript, Tailwind v4. Oracle em produção, PostgreSQL em ambiente de colegas:
  migrations e SQL portáveis. VARCHAR2 conta bytes (regra `TamanhoEmBytes`); CLOB fora das listagens.
- A chave da API nunca sai do servidor. Testes só com `Http::fake`.
- Sem dependências novas. Layout segue o portal e a retaguarda atuais (nunca o site da FGM).

## Pendente com o cliente

- **LGPD**: enviar documentos pessoais (RG, comprovantes, laudos) à OpenAI ou ao Gemini precisa do aval da FGM
  (jurídico ou proteção de dados). Isso não muda o que se constrói, mas pode impedir ligar a checagem em produção.

## Pronto quando

- Proponente cria, edita, finaliza e reabre a inscrição dentro do período, com os documentos filtrados pelas escolhas.
- Os três modos de checagem se comportam como a tabela, inclusive com a IA fora do ar.
- A equipe define modo e instrução de cada documento e gera instruções com IA (um e todos).
- Testes de feature cobrindo as regras acima; `npm run build` e a suíte completa passam.
