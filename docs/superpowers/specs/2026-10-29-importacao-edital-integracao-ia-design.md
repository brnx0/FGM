# Importação do edital usando a Integração com IA · Design

Data: 2026-10-29 · Status: aguardando aprovação

## 1. Intenção

Duas peças foram feitas em paralelo:

- **Integração com IA** (Daniel, PR #8, já na `master`): tela Retaguarda › Parametrização › Integração com IA. Guarda, por provedor (OpenAI e Google Gemini), o modelo e a chave cifrada (`conexoes_ia`), e numa configuração única se a validação por IA está ligada, o provedor principal e o de redundância (`configuracoes_ia`). Lista modelos e testa a conexão (`App\Support\ClienteIa`, erros em `App\Support\FalhaNaIa`). Ainda não faz nenhuma chamada de análise.
- **Importação do edital por IA** (branch local `feat/edital-importacao-ia`, design `2026-10-28-edital-importacao-ia-design.md`): estrutura nova do edital, importação do PDF em segundo plano, confiabilidade e pontos de atenção — com uma tela própria de Configurações da IA, só para o Gemini.

O usuário quer **a tela do Daniel como base** e a importação usando a conexão dela. As duas não convivem como estão: ambas criam a tabela `configuracoes_ia` e o modelo `ConfiguracaoIa`, com colunas diferentes.

## 2. Objetivos

1. A importação do edital passa a usar a **conexão da Integração com IA**: provedor principal e, se a chamada falhar, o de redundância (decisão do usuário).
2. A importação funciona com **OpenAI e Gemini** (decisão do usuário).
3. A nossa tela de Configurações da IA deixa de existir. O que só a importação usa vira a seção **"3. Importação de editais"** na tela do Daniel: prompt modelo editável com "Restaurar o prompt modelo", temperatura e tamanho máximo do PDF.
4. A importação fica **sempre disponível**, sem liga/desliga próprio e sem depender do "Ativar validação por IA" (que é das validações automáticas). Sem provedor configurado, ou com a chamada falhando, a importação mostra o erro com o que fazer (decisão do usuário).
5. Tudo junto numa branch que parte da `master` atual, levando a reversão dos ajustes antigos do edital e a importação.

## 3. Não-objetivos

- Mudar o que a tela do Daniel já faz nas seções 1 e 2.
- Validações automáticas de documentos (projeto próprio, que usará a mesma conexão).
- Enviar ao GitHub ou abrir PR (só quando o usuário pedir).

## 4. Anti-objetivos

- Duas fontes de chave ou de provedor no sistema.
- A chave sair do servidor (props, sessão, log, erro gravado, URL) — regra que as duas implementações já seguem.
- A importação travar ou ficar "analisando" para sempre quando o provedor falha.
- Perder o que o Daniel fez em eventos, Prefeituras-Bairro, proponentes e portal ao juntar as branches.

## 5. Como fica

### 5.1 Configuração

- **Conexão** (provedor, modelo, chave, principal, redundância): só na tela do Daniel, como está.
- **Seção 3, Importação de editais** (nova, na mesma tela): prompt editável com o prompt modelo e o botão "Restaurar o prompt modelo", temperatura (0 a 1, padrão 0,1) e tamanho máximo do PDF (1 a 14 MB, padrão 14). A parte fixa do prompt continua no código e não aparece na tela. Aviso na seção quando não há provedor principal com chave: "Configure o provedor principal acima para importar editais."
- Esses campos vão para a configuração única do Daniel (`configuracoes_ia`), em colunas novas da importação, por uma migration nova (a dele já rodou).
- A seção 3 tem **formulário, rota e validação próprios** (salvar só a importação), sem mexer no envio das seções 1 e 2. O mesmo prompt vale para os dois provedores.

### 5.2 Chamada de análise

- Um cliente de análise por provedor, com a mesma entrada (PDF, prompt, esquema da resposta, temperatura) e a mesma saída (dados em JSON e tokens consumidos):
  - **Gemini:** como já está na importação (`generateContent`, PDF em `inline_data`, `responseSchema`).
  - **OpenAI:** PDF enviado como arquivo na própria chamada e resposta presa ao esquema (saída estruturada da API); o esquema é traduzido para o formato que a OpenAI aceita.
- **Temperatura:** enviada aos dois. Se o provedor recusar o parâmetro para aquele modelo (comum nos modelos de raciocínio da OpenAI), a mesma chamada é repetida uma vez sem ele.
- **Tempo:** cada chamada tem até 5 minutos; com a redundância, a importação passa a ser mostrada como erro só depois de **15 minutos** "analisando" (antes eram 10).
- **Redundância:** a importação chama o principal; se a chamada falhar por erro do provedor (chave recusada, limite atingido, serviço fora, tempo esgotado, resposta fora do formato), chama o de redundância, se houver. Registra na importação qual provedor e modelo responderam. Se os dois falharem, a mensagem diz isso e mostra o motivo de cada um.
- Os erros seguem o padrão de mensagens em português do Daniel (`FalhaNaIa`).

### 5.3 Sempre disponível

- O botão **"Importar com IA"** aparece sempre na lista de editais.
- Sem provedor principal com chave e modelo, o envio é recusado antes de qualquer chamada: "A integração com IA não está configurada. Configure o provedor em Parametrização › Integração com IA." (com link para a tela; todos os gestores acessam a retaguarda inteira).
- Uma importação que já começou e falha segue o fluxo atual: erro com "Tentar de novo" e "Descartar".

### 5.4 Junção das branches

- Branch nova `feat/edital-importacao-ia-integrada` a partir da `master` atual, com: a reversão dos ajustes antigos do edital, os commits da importação, e a adaptação desta spec.
- **Migrations dos ajustes antigos do edital são removidas** da branch (decisão do usuário: não serão usadas), como na reversão local: `2026_10_27_225850_add_dados_gerais_to_editais_table`, `2026_10_27_231628_create_edital_categorias_table`, `2026_10_27_235924_create_edital_documentos_table`, `2026_10_27_235932_create_edital_documento_categoria_table`. Para um banco onde elas já rodaram (ex.: o do Daniel) continuar subindo só com `migrate`, uma **migration de limpeza**, com data anterior às da estrutura nova, confere o que existe e só então apaga: as tabelas antigas `edital_documento_categoria`, `edital_documentos` e `edital_categorias`, e as colunas `tipo_fomento`, `vigencia_inicio`, `vigencia_fim` e `divulgacao`, devolvendo o `resumo` em texto a partir da Divulgação quando ele não existir. Num banco que nunca rodou as antigas, ela não faz nada. As linhas dessas migrations antigas na tabela `migrations` também são removidas por ela.
- A tabela e o modelo de configuração da importação (`configuracoes_ia` nossa, `ConfiguracaoIa` nosso, a tela `ConfiguracaoIa.vue`, as rotas `retaguarda.ia.*` e o item de menu) saem, e a migration que criava a nossa `configuracoes_ia` não entra na branch nova (ela nunca foi enviada a ninguém). `importacoes_edital` também nunca foi enviada: a migration de criação pode ser ajustada para já ter o provedor e o modelo que responderam.
- **Banco de desenvolvimento:** hoje está no estado da `master`. Rodar `migrate` na branch nova e restaurar o backup da estrutura do edital, salvo em `storage/app/backup-feat-edital-ia.json` no container (cópia em `scratchpad/backup-feat-edital-ia.json`), que já está no formato novo (chamadas, categorias, itens, documentos, requisitos, critérios). A chave do Gemini cadastrada antes deve ser cadastrada de novo na tela do Daniel.

## 6. Restrições

- As mesmas do projeto (`CLAUDE.md`) e do design da importação: Laravel 13, Inertia v2, Vue 3 TS strict, Oracle, sem dependências novas, chave só no servidor, nada de `migrate:fresh`.
- Testes nunca chamam provedores de verdade (`Http::fake`).

## 7. Decisões do Claude (conferir)

1. **Colunas novas na configuração do Daniel** em vez de uma tabela só para a importação: é uma configuração única, e a tela também é a dele.
2. **O que conta como falha para chamar a redundância:** erro do provedor ou resposta fora do formato. Uma resposta válida, mesmo com campos faltando, não troca de provedor (vira ponto de atenção, como hoje).
3. **Tamanho máximo do PDF continua 14 MB** para os dois provedores (limite mais restrito, o do Gemini).
4. **A importação grava o provedor e o modelo que responderam**, para a equipe saber de onde veio o resultado e o custo.
5. **Migration de limpeza condicional** para os bancos que já rodaram os ajustes antigos, já que os arquivos dessas migrations saem da branch.
6. **Tempo de 15 minutos** para considerar a importação travada, por causa das duas chamadas possíveis.
7. **Temperatura recusada:** repete a chamada sem ela no mesmo provedor, antes de ir para a redundância.
8. **Seção 3 com salvamento próprio**, sem tocar no formulário das seções 1 e 2.
