# Edital importado do PDF com IA · Design

Data: 2026-10-28 · Status: aguardando aprovação

## 1. Intenção

O edital da FGM chega pronto: é redigido pela área de editais e publicado no Diário Oficial do Município (DOM). O sistema **não faz a gestão completa do edital**; ele existe para **receber as inscrições, validar os documentos e homologar as inscrições** (decisão do usuário). Por isso o cadastro do edital precisa de duas coisas:

- os dados que a inscrição, a validação e a homologação **usam como regra**, em campos estruturados;
- o resto do edital como **informação** para quem lê.

Para não digitar tudo à mão, a equipe envia o PDF do edital e um **modelo de IA** (provavelmente o Gemini, do Google) lê o documento e preenche o cadastro. A IA preenche o que encontrar, o edital fica salvo **sem publicar**, a tela mostra um **índice de confiabilidade** e os pontos de atenção, e a equipe completa e corrige.

O cadastro anterior do edital (tipo de fomento, vigência, divulgação, categorias, documentos) foi desfeito; este projeto o refaz com o modelo de dados abaixo, desenhado a partir de cinco editais reais de 2026 (Pontos de Cultura 007, SalCine II 008, Salvador Circula 001, Curto Circuito das Artes 005, Inventário de Bens Culturais 006). A análise está publicada em https://claude.ai/artifact/TWfmHGVGD2QT5geyawyZuw.

## 2. Objetivos

1. Tela **Configurações da IA** na retaguarda.
2. **Importar edital do PDF**: a IA preenche o cadastro, o edital fica salvo sem publicar, com confiabilidade e pontos de atenção.
3. **Novo cadastro do edital** (também usável à mão, sem IA), com os blocos estruturados e os informativos da §7.
4. O portal mostra os dados informativos e estruturados na página do edital.

## 3. Não-objetivos (agora)

- Inscrição pelo portal, validação documental (triagem por IA e manual) e homologação: são os próximos projetos, que usarão os dados estruturados deste.
- Avaliação de mérito: os critérios são guardados agora, mas nenhuma tela os usa ainda.
- Gestão do edital (redação, retificação pela IA, publicação no DOM). **Retificação é manual** no cadastro (decisão do usuário).
- Formulário de perguntas da inscrição (o espelho do formulário fica fora do PDF na maioria dos editais).

## 4. Anti-objetivos

- A chave da API aparecer na tela, no log, no navegador ou no banco em texto puro.
- A IA publicar um edital sozinha ou sobrescrever um edital existente.
- A IA inventar um dado sem que a equipe saiba onde conferir.
- A importação travar a tela ou cair por tempo de espera com um PDF grande.
- Dados de outros editais da mesma página do DOM entrarem no cadastro (aconteceu no PDF do Pontos de Cultura, que traz o cronograma de um edital do CMDCA).

## 5. Configurações da IA

Tela na retaguarda (só gestores), um registro único:

| Campo | Regra |
|---|---|
| **Importação por IA** | Ligada/desligada. Desligada, o botão "Importar do PDF" some e o cadastro é só manual. |
| **Provedor** | Lista com uma opção por enquanto: Google Gemini. A importação é escrita para que outro provedor entre depois sem refazê-la. |
| **Chave da API** | Gravada criptografada. Nunca volta para a tela: aparece "Chave cadastrada, terminada em …X4f2" com o botão "Trocar chave". |
| **Modelo** | Texto (ex.: `gemini-2.5-pro`), com uma sugestão de modelo padrão. |
| **Temperatura** | 0 a 1, padrão baixo (0,1), porque a extração precisa ser fiel ao texto. |
| **Tamanho máximo do PDF** | Em MB, padrão 20. |
| **Prompt** | Texto das instruções que a IA recebe. O sistema vem com um **prompt modelo**; a equipe pode editar e há o botão **"Restaurar o prompt modelo"** (decisão do usuário). |
| **Testar conexão** | Faz uma chamada mínima com a chave e o modelo e mostra "Conexão funcionando" ou o erro do provedor em português. |

O **formato da resposta** (os campos que a IA devolve) é **fixo no sistema** e enviado ao provedor como esquema estruturado; editar o prompt não muda os campos (decisão do Claude, aceita pelo usuário).

## 6. Importar edital do PDF

1. Na lista de editais da retaguarda, botão **Importar do PDF** (com a importação ligada).
2. A equipe escolhe o PDF (o tamanho máximo da configuração) e confirma.
3. A análise roda **em segundo plano**; a tela mostra "Analisando o edital…" e a pessoa pode sair e voltar. Ao terminar, a lista de editais avisa.
4. Resultado:
   - um **edital novo, não publicado**, com o que a IA encontrou preenchido e o **PDF enviado anexado** como o PDF do edital;
   - na página do edital, o quadro **"Importado pela IA"**: confiabilidade **geral** e **por bloco** (Identificação, Inscrição, Participação, Categorias, Cotas, Documentos, Homologação, Avaliação, Informações), e a lista de **pontos de atenção**, cada um com o bloco, o tipo (não encontrado, divergência no edital, leitura incerta) e os itens do edital citados — por exemplo "O item 5.1 diz 01 a 31/07 e o 11.1 dá outro prazo". Não há marcação campo a campo (decisão do usuário).
   - o quadro fica no edital até alguém clicar **"Já conferi"**; a publicação **não é bloqueada** por ele.
5. **Edital incompleto pode ser salvo** (não publicado): título, datas e categoria da vitrine passam a aceitar ficar vazios enquanto o edital não está publicado. Sem título encontrado, o edital recebe "Edital importado em dd/mm/aaaa". Para **publicar**, continuam exigidos o PDF e passam a ser exigidos: título, ao menos uma chamada com início e fim, ao menos uma natureza aceita e ao menos uma categoria; a tela diz o que falta.
6. Falha (chave inválida, PDF ilegível, provedor fora do ar, resposta fora do formato): nenhum edital é criado; a importação aparece com o erro em português e o botão **Tentar de novo**.
7. Cada importação guarda: quem enviou, quando, modelo usado, tokens consumidos (para a equipe acompanhar o custo), situação e a resposta recebida (para conferência). O PDF fica guardado mesmo se a análise falhar; a importação com erro tem os botões **Tentar de novo** (refaz a análise no mesmo registro) e **Descartar** (apaga o registro e o PDF). Excluir o edital criado não apaga o registro da importação. Importar duas vezes o mesmo PDF cria dois editais (o endereço do segundo ganha um sufixo, como já acontece com títulos repetidos).
8. Erros do provedor aparecem em português, com mensagens fixas para os casos comuns (chave inválida ou sem permissão, cota ou limite de uso atingido, serviço indisponível, tempo esgotado, PDF não lido) e uma mensagem geral para o resto, com o código do provedor para suporte.

O prompt tem duas partes: a **editável** (o prompt modelo, que a equipe pode mudar) e uma **parte fixa do sistema**, sempre enviada junto e que a tela não mostra para edição, com as regras que não podem sumir: responder só no formato do sistema, não inventar, ignorar outros editais da página e apontar divergências. A resposta é conferida no servidor contra o formato; fora dele, a importação falha com erro.

O prompt modelo instrui a IA a: considerar só o edital do cabeçalho principal e ignorar outros editais e avisos da mesma página do DOM; não inventar (campo não encontrado fica vazio e vira ponto de atenção); apontar divergências internas em vez de escolher uma versão; citar o item do edital de onde tirou cada dado nos pontos de atenção; converter valores e datas para o formato pedido.

## 7. Modelo do edital

### 7.1 Estruturado (usado pela inscrição, validação e homologação)

**Identificação**
- Número (ex.: `007/2026`), tipo (Edital ou Chamamento Público), título, objeto (texto curto), data de publicação no DOM e número da edição.
- Categoria/área da vitrine (o campo "Categoria" que já existe, ex.: "Audiovisual") e imagem da vitrine (já existem).

**Inscrição**
- **Chamadas**: uma ou mais, cada uma com nome (ex.: "Chamada 1"), início e fim das inscrições. Edital comum tem uma chamada. (O Salvador Circula tem duas.)
- As colunas atuais de início e fim das inscrições do edital continuam e passam a ser **calculadas ao salvar**: o menor início e o maior fim das chamadas. A vitrine, a situação (em breve, aberto, encerrado) e os filtros continuam usando essas colunas. Editais já cadastrados ganham uma chamada com as datas que já têm.
- Forma de inscrição no edital original (texto, ex.: link do formulário) — informativo, porque a inscrição passará a ser pelo portal.

**Participação**
- **Naturezas aceitas**: Pessoa Física, MEI, PJ sem fins lucrativos, PJ com fins lucrativos.
- **Coletivos sem CNPJ** aceitos (sim/não), por meio de um proponente que os represente.
- **Inscrições por proponente** (número, padrão 1) e se o limite vale por chamada.

**Categorias**
- Lista: nome, descrição, valor por proposta (pode ficar vazio quando o edital usa faixa ou valor variável), vagas (pode ficar vazio quando o edital não define), naturezas aceitas na categoria (padrão: as do edital; ex.: SalCine, PF só na Cineclube), chamada (quando houver mais de uma).
- **Itens da categoria** (opcional): nome, valor, vagas e suplentes. Para editais em que o proponente escolhe um item dentro da categoria (os bens do Inventário, os espaços do Curto Circuito).

**Cotas**
- Modalidades oferecidas: pessoas negras, indígenas, pessoas com deficiência (cada uma ligada/desligada), com percentual e quantidade (vagas ou valor) quando o edital informar.
- Base da reserva (vagas ou recursos) e alcance (todo o edital, por categoria, por chamada).
- Concorrer a negros e indígenas ao mesmo tempo é vedado (sim/não).
- Exige heteroidentificação (sim/não).

**Documentos exigidos**
- Lista: nome, orientação (texto curto), **etapa** (inscrição, validação de cotas, habilitação), obrigatório, naturezas a que se aplica, categorias (todas ou algumas), modalidade de cota (para os da validação de cotas), anexo do edital que serve de modelo (número e nome, ex.: "Anexo IV — Declaração de endereço") e **arquivo-modelo** para baixar (enviado pela equipe; a IA não tem o arquivo).

**Requisitos de homologação**
- Lista das condições que a inscrição precisa cumprir para ser homologada, cada uma com descrição (ex.: "Sede ou domicílio em Salvador há pelo menos 2 anos", "CNAE compatível com audiovisual", "Planilha orçamentária com o valor exato da categoria") e, quando houver, os documentos que a comprovam.

**Avaliação** (guardada para a avaliação de mérito futura)
- Critérios: nome, descrição, peso ou pontos máximos, categorias a que se aplica.
- Nota máxima e nota de corte.
- Pontuação extra: grupo, pontos por pessoa, máximo por grupo, teto total, e se conta para atingir a nota de corte.

### 7.2 Informativo (texto exibido no portal)

Seções de texto, cada uma opcional, preenchidas pela IA com um resumo fiel do edital e editáveis pela equipe: **Recursos e pagamento** (valor total, fonte, forma de pagamento), **Vedações**, **Etapas e prazos** (homologação, mérito, recursos, habilitação, assinatura), **Contrapartida e execução**, **Prestação de contas**, **Base legal**, **Anexos** (lista com número, nome e onde encontrar), **Contatos** e **Outras regras** (ex.: orçamento com 10% para acessibilidade, equipe técnica mínima, reserva temática).

O "resumo" atual do edital continua como o texto de apresentação curto da vitrine.

## 8. Telas

- **Retaguarda — cadastro do edital:** formulário em abas laterais (o padrão já usado), uma aba por bloco da §7, com listas editáveis em sanfona para chamadas, categorias e itens, documentos, requisitos e critérios. Serve tanto para completar uma importação quanto para cadastrar à mão.
- **Retaguarda — lista de editais:** botão "Importar do PDF" e a situação das importações em andamento ou com erro.
- **Retaguarda — Configurações da IA:** item no menu da retaguarda. Acesso: qualquer gestor, como o resto da retaguarda.
- **Portal — página do edital:** mostra objeto, período(s) de inscrição, quem pode participar, categorias com valores e vagas, cotas, documentos exigidos na inscrição e as seções informativas.

## 9. Restrições

- Stack e regras do projeto (`CLAUDE.md`): Laravel 13, Inertia v2, Vue 3 em TypeScript strict, Tailwind v4, Oracle via `yajra/laravel-oci8`, PHPUnit, Pint. **Sem dependências novas**: a chamada ao Gemini é feita com o cliente HTTP do Laravel; a chave é guardada com a criptografia do próprio Laravel.
- Oracle: `''` vira `NULL`; ids voltam como string; texto longo em CLOB, fora das listagens; nada de `migrate:fresh`.
- A análise roda **em segundo plano, sem fila** (decisão do usuário: poucos editais enviados ao mesmo tempo). Ao confirmar a importação, o sistema grava o registro e inicia um processo separado (um comando `php artisan` lançado em segundo plano no servidor), e a resposta volta na hora para a tela; a tela consulta a situação até terminar. Nenhum processo precisa ficar rodando permanentemente. Se o processo morrer no meio, a importação fica "Analisando" por mais de 10 minutos e passa a ser mostrada como erro, com "Tentar de novo".
- Os editais já cadastrados continuam funcionando; os campos novos começam vazios.
- Branch `feat/edital-importacao-ia`, a partir da `revert/edital-ajustes` (local, ainda não enviada).

## 10. Decisões do Claude (conferir)

1. **Processo separado lançado em segundo plano** (em vez de fila ou de esperar na requisição), com tempo limite de 10 minutos.
2. **Um só provedor (Gemini) agora**, com a chamada isolada numa classe para outro provedor entrar depois.
3. **PDF enviado inteiro ao provedor** (o Gemini lê PDF direto, sem OCR à parte); acima do tamanho máximo, a importação é recusada antes de chamar a IA.
4. **Confiabilidade** é uma nota de 0 a 100 por bloco dada pela própria IA, com justificativa curta; a geral é a média dos blocos.
5. **Importação nunca altera edital existente**: sempre cria um novo, não publicado.
6. **Uso de tokens** guardado por importação para acompanhar custo; sem limite automático por enquanto.
7. **Critérios de avaliação estruturados já agora**, sem tela que os use.
8. **Arquivo-modelo dos documentos** é enviado pela equipe; a IA só registra qual anexo do edital serve de modelo.
9. **LGPD:** o PDF do edital é documento público, então enviá-lo ao provedor não expõe dados pessoais. (A triagem de documentos dos proponentes, no projeto de validação, terá essa questão.)
10. **Armazenamento:** chamadas, categorias e seus itens, documentos, requisitos de homologação e critérios de avaliação em **tabelas próprias** (a inscrição e a validação vão apontar para eles); cotas e participação em colunas do edital; as seções informativas, a confiabilidade e os pontos de atenção em texto longo (JSON).
11. **Arquivo-modelo dos documentos** no disco privado, baixado no portal por rota pública, como o PDF do edital.
12. **Ordem de entrega** numa só branch: cadastro novo do edital (modelo e formulário), configurações da IA, importação, portal.

## 11. Deixado ao builder

- Tabelas, colunas e componentes; o esquema exato da resposta da IA (seguindo a §7); o texto do prompt modelo (seguindo a §6).
- Testes de feature para: configurações (chave criptografada e nunca devolvida, restaurar prompt, testar conexão com o provedor simulado), importação com o provedor simulado (`Http::fake`) — sucesso cria edital não publicado com PDF, blocos e confiabilidade; resposta fora do formato, erro do provedor e PDF acima do limite não criam edital; importação desligada esconde o botão e recusa a rota —, cadastro manual de todos os blocos, e portal exibindo os blocos.
