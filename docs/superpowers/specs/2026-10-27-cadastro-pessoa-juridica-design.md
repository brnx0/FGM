# Cadastro de pessoa jurídica e Minhas organizações · Design

Data: 2026-10-27 · Status: aguardando aprovação

## 1. Intenção

Organizações culturais (associações, produtoras, MEIs, empresas) também se inscrevem nos editais da FGM. Quem age por elas é sempre uma pessoa física logada pelo gov.br. Hoje o portal só tem o cadastro de pessoa física (`/cadastro`, formulário feito pelo Daniel); a página `/cadastro/pessoa-juridica` é um "Formulário em breve" e nenhuma tela leva a ela.

Esta fase cria o **cadastro da organização (pessoa jurídica)** e a área **Minhas organizações**, de modo que uma pessoa possa atuar **como ela mesma (PF) ou por uma ou mais organizações (PJ)**.

## 2. Objetivos

1. Depois de salvar a pessoa física pela primeira vez, o fluxo **segue na sequência** para o cadastro da organização, que **pode ser pulado** ("Não represento uma organização").
2. Formulário da organização com três blocos: **dados institucionais**, **endereço da sede** e **representantes legais** (seção 5).
3. Área **Minhas organizações**: lista das organizações a que a pessoa está vinculada, cadastrar outra, editar, e (para o responsável) vincular e desvincular pessoas.
4. Regras de acesso da seção 6: quem cadastrou é o **responsável**; ele vincula outras pessoas pelo CPF, e os vinculados podem editar.

## 3. Não-objetivos (agora)

- Anexos/documentos da organização ou dos representantes (contrato social, procuração, ata) — "por enquanto sem documento".
- Tipologia do agente — decisão do usuário: o campo não entra.
- Busca de dados de representantes por CPF em outra base — decisão do usuário: preenchimento manual.
- Visão das organizações na retaguarda — "fica pra depois".
- A escolha "inscrever como PF ou como organização X" na inscrição em edital (a inscrição ainda não existe). Esta fase só registra os vínculos.
- Transferir a responsabilidade da organização a outra pessoa; excluir organização.

## 4. Anti-objetivos

- Uma pessoa ver ou editar uma organização à qual não está vinculada (inclusive por troca do id na URL).
- O mesmo CNPJ cadastrado duas vezes por pessoas diferentes.
- Revelar a quem tenta cadastrar um CNPJ existente quem é o responsável por ele.
- Usuário da retaguarda (ativo, com papel) usar essas telas — vale a mesma regra do cadastro (`nao-gestor`).

## 5. O formulário da organização

### 5.1 Dados institucionais

| Campo | Regra |
|---|---|
| CNPJ * | Aceita o numérico (`00.000.000/0000-00`) e o alfanumérico da IN RFB 2.229/2024 (`12.ABC.345/01DE-35`). Validado pelo dígito verificador (cálculo da Receita para o alfanumérico: valor de cada caractere = código ASCII − 48, pesos 2–9, módulo 11). Guardado sem máscara, 14 caracteres em maiúsculas. Único no sistema. Não pode ser alterado depois de salvo. Texto de ajuda: "Aceita o CNPJ numérico e o alfanumérico (IN RFB 2.229/2024)." |
| Razão social * | Texto. |
| Nome fantasia | Texto. |
| Natureza jurídica * | Código de 4 dígitos da tabela da Receita, escolhido numa lista com busca (código + descrição). Ajuda: "Os quatro dígitos que constam no cartão CNPJ — por exemplo, 2062 para sociedade empresária limitada." |
| Optante pelo MEI (Simei) | Caixa de marcação. Ajuda: "Marque se a empresa é microempreendedor individual. O edital pode exigir documentos diferentes de um MEI." |
| Finalidade lucrativa | Sim / Não. **Sugerida pela natureza jurídica** ao escolher o código (grupo 2 — entidades empresariais → Sim; demais grupos → Não), editável. Ajuda: "Sugerida pela natureza jurídica. Confira e ajuste se necessário." |
| Município * | Município de **atuação** da organização (pode ser diferente da cidade da sede), **qualquer município do Brasil**, escolhido numa lista com busca. Guardado pelo código IBGE. |
| Inscrição municipal | Texto com máscara `000000-0`. |
| Inscrição estadual | Texto com máscara `00.000.00-0`. |
| Porte | Micro, Pequeno, Médio, Grande porte. |
| Data de fundação | Data (`dd/mm/aaaa`), não futura. Ajuda: "Dia, mês e ano de fundação da organização." |
| Telefone | Mesma máscara do formulário de pessoa física. |
| Logo da organização | Imagem jpg, png ou webp, até 5 MB. |

**Preenchimento pelos dados públicos do CNPJ** (pedido do usuário). Ao sair do campo CNPJ (válido e ainda não cadastrado), a tela consulta os dados públicos da Receita e preenche o que vier: razão social, nome fantasia, natureza jurídica (e, por ela, a sugestão de finalidade lucrativa), optante pelo MEI, porte (Microempresa → Micro; Empresa de Pequeno Porte → Pequeno; "Demais" não preenche), data de fundação (início de atividade), telefone e todo o endereço da sede. O município de atuação vem sugerido com a cidade da sede. **Tudo continua editável**, e a pessoa vê o aviso "Dados preenchidos com as informações públicas da Receita Federal. Confira antes de salvar." Se a consulta falhar ou o CNPJ não for encontrado (o alfanumérico pode ainda não estar nas bases públicas), nada é bloqueado e a pessoa preenche à mão. Os representantes legais **não** são preenchidos: a base pública traz os sócios com o CPF mascarado, e nem todo representante é sócio.

### 5.2 Endereço da sede

CEP, logradouro, número (com a opção **"Sem número"**), complemento, bairro, cidade, UF. Ajuda: "Informe o CEP para preencher automaticamente. Logradouro e bairro seguem editáveis." Ao completar o CEP, a tela consulta o endereço e preenche logradouro, bairro, cidade e UF; se a consulta falhar, nada é bloqueado e a pessoa preenche à mão.

### 5.3 Representantes legais

Texto da seção: "Quem responde juridicamente pela organização. A empresa pode ter mais de um; nem todo representante legal é sócio." Lista de cartões "Representante N de M", com adicionar e remover; **pelo menos um**.

| Campo | Regra |
|---|---|
| CPF * | Válido (dígito verificador); não pode repetir dentro da mesma organização. |
| Nome * | Texto. |
| Tipo de documento de identidade | RG, CNH, Passaporte, RNE/CRNM, Outro. |
| Número do documento | Texto. |
| Cargo | Texto. |
| Telefone | Máscara de telefone. |
| E-mail | E-mail válido. |
| Origem da representação | Contrato social, Procuração, Ata de eleição, Outro. Com "Outro", aparece um campo para descrever. |

Estar na lista de representantes **não dá acesso** à organização; é só um dado do cadastro (seção 6).

## 6. Acesso e vínculos

- Cada organização tem **pessoas vinculadas**, identificadas pelo **CPF** (com o nome). Uma delas é a **responsável**: quem fez o cadastro.
- **Vinculados podem ver e editar** os dados da organização (seção 5).
- **Só o responsável** vincula e desvincula pessoas. Basta informar CPF e nome; a pessoa **não precisa já ter entrado** no sistema — o vínculo vale quando ela entrar pelo gov.br com aquele CPF.
- O responsável não pode ser desvinculado.
- Uma pessoa pode estar vinculada a **várias organizações**.
- CNPJ já cadastrado: o formulário recusa com "Esta organização já está cadastrada. Peça ao responsável por ela para vincular você." — sem dizer quem é o responsável.

## 7. Fluxo e telas

1. **Pessoa física primeiro.** Cadastrar organização exige a pessoa física completa; quem tentar abrir as telas de organização sem ela vai para `/cadastro`.
2. **Na sequência.** Ao salvar a pessoa física **pela primeira vez**, em vez de voltar à home, a pessoa vai para a etapa da organização, com duas saídas: **cadastrar a organização** (abre o formulário da seção 5) e **"Não represento uma organização"** (vai para a home com a mensagem de sucesso de hoje). Edições posteriores da pessoa física continuam voltando à home.
3. **Minhas organizações** (link no menu da pessoa logada no portal): cartões com nome fantasia (ou razão social), CNPJ mascarado, logo e o selo "Responsável" quando for o caso; botões para editar e, para o responsável, gerenciar as pessoas vinculadas; botão "Cadastrar organização".
4. Após salvar uma organização: volta a Minhas organizações com "Organização salva."
5. Tudo com a identidade visual atual do portal (faixa azul, cartões, `CampoCadastro`/`SecaoCadastro` do formulário de pessoa física, modo noturno, alto contraste, A-/A/A+).

## 8. Restrições

- Stack e regras do projeto (`CLAUDE.md`, Laravel Boost): Laravel 13, Inertia v2, Vue 3 em TypeScript strict, Tailwind v4, Oracle via `yajra/laravel-oci8`, PHPUnit, Pint. **Sem dependências novas.**
- Oracle: `''` vira `NULL`; datas seguem `App\Models\Concerns\UsaDiaCivil`.
- Rotas do portal com `auth` + `nao-gestor`, como o cadastro de pessoa física.
- Tudo em português do Brasil.
- Branch `feat/cadastro-pj`, a partir de `feat/retaguarda` (precisa do `nao-gestor`).

## 9. Decisões do Claude (conferir)

1. **Consulta de CEP pelo nosso servidor no ViaCEP** (gratuito), por uma rota do portal com limite de tentativas e tempo de espera curto; o navegador não fala com o ViaCEP, então a CSP não muda.
2. **Listas no código, sem tabela**: municípios do IBGE (código + nome + UF) e naturezas jurídicas da Receita (código + descrição), como `App\Support\Estados` e `Paises`.
3. **Logo no disco `public`** (é um dado de exibição, como as imagens dos eventos), com URL relativa.
4. **CNPJ não editável** depois de salvo; corrigir um CNPJ errado exige outro cadastro (exclusão fica para depois).
5. **Vínculo guardado por CPF**, não por usuário, para valer antes do primeiro login da pessoa.
6. **No formulário novo, o Representante 1 vem preenchido** com nome e CPF de quem está cadastrando, editável e removível (a pessoa pode não ser representante legal).
7. **Limite de 10 representantes** e **20 pessoas vinculadas** por organização.
8. **Vinculado pode se desvincular** ("Sair da organização"); o responsável não.
9. As etapas no topo (`EtapasCadastro`) passam a ser: Conta gov.br · Seus dados · Organização (opcional) · Inscrição nos editais.
10. A página antiga `/cadastro/pessoa-fisica` (placeholder) sai; `/cadastro/pessoa-juridica` passa a ser o formulário real.
11. **Listas a partir das fontes oficiais, nunca de memória**: os municípios saem da API de localidades do IBGE e as naturezas jurídicas da tabela oficial da Receita/CONCLA, baixadas uma vez e versionadas como arquivo de dados no repositório.
12. **Nome do vinculado**: guarda-se o nome digitado pelo responsável; quando a pessoa já tem conta, a tela mostra o nome do gov.br. Não há reconciliação além disso.
13. **Vinculado que ainda não completou a pessoa física** passa primeiro por `/cadastro`, como qualquer um (seção 7, item 1).
14. **Sem fluxo de "pedir acesso"**: quem recebe o aviso de CNPJ já cadastrado fala com o responsável fora do sistema.
15. **CPF de usuário da retaguarda pode ser vinculado** (não atrapalha); ele só não consegue usar as telas enquanto for usuário da retaguarda.
16. **Consulta de CEP**: limite de 30 consultas por minuto por pessoa e espera máxima de 3 segundos; se o servidor não tiver acesso à internet em produção, a tela cai no preenchimento manual.
17. **Logo** guardada em `organizacoes/{id}` no disco `public`; inscrições municipal e estadual são texto livre (a máscara só ajuda a digitar), sem unicidade.
18. **"Primeira vez"** da pessoa física = `profile_completed_at` vazio antes de salvar.
19. **Consulta de CNPJ pelo nosso servidor na BrasilAPI** (`/api/cnpj/v1/{cnpj}`, gratuita, sem chave), com os mesmos cuidados do CEP: limite de 30 consultas por minuto por pessoa, espera máxima de 5 segundos, resposta em cache por 1 dia, e só os campos da seção 5.1 voltam para a tela. Fica isolada numa classe própria, para trocar de fornecedor (ReceitaWS, CNPJá) sem mexer nas telas.

## 10. Deixado ao builder

- Nomes de tabelas, models, controllers, form requests e policies; a forma de entregar a lista de municípios à tela (prop ou busca no servidor).
- Componentes Vue (reaproveitar os do formulário de pessoa física) e o desenho dos cartões de Minhas organizações dentro da identidade atual.
- Testes de feature para: CNPJ numérico e alfanumérico (válidos e inválidos), CNPJ duplicado, campos obrigatórios, mínimo de um representante, sugestão de finalidade lucrativa, fluxo "pular", acesso negado a não vinculado (ver, editar, gerenciar), só o responsável gerencia vínculos, vínculo por CPF antes do primeiro login, bloqueio de usuário da retaguarda, consulta de CEP com falha do serviço.
