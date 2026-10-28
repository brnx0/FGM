# Ajustes no edital: dados gerais e categorias · Design

Data: 2026-10-28 · Status: aguardando aprovação

## 1. Intenção

A FGM vai abrir a **inscrição nos editais pelo portal**. Para isso o edital precisa de mais dados do que tem hoje. O ponto de partida foi o espelho do formulário do SalCine II (Anexo II do Edital 08/2026), que mostra que cada edital tem regras próprias: categorias com valor e vagas, quem pode concorrer em cada uma, cotas, documentos e perguntas.

O trabalho foi dividido em projetos, um de cada vez:

1. **Ajustes no edital** — este documento: aba Dados gerais e a nova aba Categorias.
2. **Construtor de formulário** na retaguarda — cada edital monta o próprio formulário de inscrição (seções, perguntas de vários tipos, perguntas condicionais, anexos, declarações), começando do zero, de um modelo ou copiando um edital anterior; o que já existe no cadastro vem preenchido na inscrição. Design próprio, depois deste.
3. **Inscrição** pelo proponente no portal (preencher, rascunho, enviar, comprovante). Design próprio.
4. Análise das inscrições na retaguarda. Mais adiante.

## 2. Objetivos (este projeto)

1. Aba **Dados gerais** do edital: rótulo "Título do edital", **tipo de fomento**, **vigência** e o resumo substituído pela **Divulgação** com texto rico.
2. Nova aba **Categorias**: as categorias de inscrição do edital, cada uma com valor, vagas, naturezas que podem concorrer e reserva de cotas.
3. O portal passa a mostrar a Divulgação formatada na página do edital.

## 3. Não-objetivos (agora)

- O formulário de inscrição, as perguntas, documentos exigidos e declarações (projeto 2).
- A inscrição em si e o uso das vagas e cotas para resultado (projetos 3 e 4).
- Mudar a "Categoria" atual do edital (área, ex.: "Fomento", "Audiovisual") — continua como está (decisão do usuário).

## 4. Anti-objetivos

- Perder o texto dos editais já cadastrados ao trocar o resumo pela Divulgação.
- HTML não sanitizado da Divulgação chegar ao portal.
- Reserva de cotas maior que o total de vagas da categoria.

## 5. Aba Dados gerais

| Campo | Regra |
|---|---|
| **Título do edital** * | É o campo "Título" atual, só muda o rótulo. |
| Categoria * | Continua como está. |
| **Tipo de fomento** * (novo) | Direto ou Indireto. |
| **Início da vigência** * (novo) | Data. Ajuda: "Validade administrativa, não a janela de inscrição." |
| **Fim da vigência** (novo) | Data opcional, não pode ser antes do início. Ajuda: "Opcional. Sem data, o edital fica vigente por prazo indeterminado." |
| **Divulgação** * | Substitui o "Resumo". Texto informativo com **editor rico**, o mesmo da descrição dos eventos (`EditorTextoRico`, Tiptap), e HTML sanitizado no servidor (`App\Support\HtmlSeguro`, como em `Evento::descricao`). |

- As datas de inscrição continuam na aba Inscrições, separadas da vigência, sem precisar cair dentro dela (decisão do usuário).
- **Portal:** a página do edital (`Editais/Show`) mostra a Divulgação formatada, como a página do evento. O carrossel da home não mostra o resumo hoje e continua sem mostrar.

## 6. Aba Categorias (nova)

Lista de categorias de inscrição do edital (ex.: "Categoria 1 – Produção de Curta-Metragem"). Cada categoria tem:

| Campo | Regra |
|---|---|
| **Nome** * | Texto. |
| **Valor por proposta** * | Em reais (ex.: R$ 150.000,00). |
| **Vagas** * | Número inteiro ≥ 1. |
| **Naturezas que podem concorrer** * | Uma ou mais: Pessoa Física, Pessoa Jurídica – MEI, Pessoa Jurídica sem fins lucrativos, Pessoa Jurídica com fins lucrativos. (No SalCine, a Pessoa Física só concorre à categoria 4.) |
| **Reserva de cotas** | Para **pessoas negras**, **pessoas indígenas** e **pessoas com deficiência**: cada uma opcional, informada em **número de vagas ou percentual**, à escolha da equipe. |

- Adicionar, editar, remover e reordenar categorias dentro do formulário do edital; salvas junto com o edital.
- A tela mostra, ao lado de cada cota em percentual, quantas vagas isso representa.
- **Na inscrição (projeto 3)**, a natureza do proponente vem do cadastro: inscrição pela própria pessoa física = Pessoa Física; por uma Pessoa Jurídica, o cadastro dela diz se é MEI (`mei`) e se tem fins lucrativos (`finalidade_lucrativa`). O formulário só oferece as categorias que aceitam aquela natureza. (Palpite confirmado pelo usuário.)

## 7. Restrições

- Stack e regras do projeto (`CLAUDE.md`): Laravel 13, Inertia v2, Vue 3 em TypeScript strict, Tailwind v4, Oracle via `yajra/laravel-oci8`, PHPUnit, Pint. Sem dependências novas (Tiptap e symfony/html-sanitizer já estão no projeto).
- Oracle: `''` vira `NULL`; datas seguem `App\Models\Concerns\UsaDiaCivil`; texto longo em CLOB, fora das listagens.
- Formulário do edital em **abas laterais** (`AbasFormulario` com `variante="lateral"`), com marcador de erros por aba.
- Tudo em português do Brasil.
- Branch `feat/edital-ajustes`, a partir da `master`.

## 8. Decisões do Claude (conferir)

1. **Nome da nova aba:** "Categorias", e na tela o título "Categorias de inscrição", para não confundir com o campo "Categoria" (área) da aba Dados gerais.
2. **Editais já cadastrados:**
   - a Divulgação recebe o resumo atual, convertido em parágrafo (texto escapado dentro de `<p>`), e a coluna do resumo deixa de existir;
   - o início da vigência recebe a data de início das inscrições;
   - o tipo de fomento fica em branco e passa a ser exigido na próxima vez que o edital for salvo;
   - eles ficam sem categorias até a equipe cadastrar.
3. **Cota em percentual:** guardado o percentual; o número de vagas mostrado é arredondado **para cima** (ex.: 30% de 5 vagas = 2), como é comum nos editais. A regra de arredondamento usada no resultado fica para o projeto 4.
4. **Cotas não podem passar do total de vagas:** a soma das cotas em vagas (com o arredondamento acima) não pode ser maior que as vagas da categoria.
5. **Categoria é obrigatória para publicar?** Não nesta etapa: um edital pode ser publicado sem categorias (a inscrição pelo portal, no projeto 3, vai exigir).
6. **Remover categoria:** permitido enquanto não houver inscrições (as inscrições ainda não existem; a trava entra no projeto 3).
7. **Valor por proposta** guardado em centavos (inteiro), exibido em reais.
8. **Divulgação vazia:** o editor vazio envia `<p></p>`; o "obrigatório" é conferido no texto já sanitizado e sem tags.
9. **Natureza de uma Pessoa Jurídica (para o projeto 3):** `mei` marcado → MEI, independentemente da finalidade lucrativa; senão, `finalidade_lucrativa` Sim → com fins lucrativos, Não → sem fins lucrativos; se a finalidade estiver em branco, a inscrição pede para a pessoa completar o cadastro da Pessoa Jurídica antes.

## 9. Deixado ao builder

- Nomes de tabelas, colunas, enums e componentes Vue; a forma de enviar a lista de categorias junto com o formulário.
- Testes de feature para: rótulos e campos novos, vigência (fim antes do início recusado), Divulgação sanitizada (script e atributos perigosos removidos) e exibida no portal, migração dos editais existentes (resumo → Divulgação, vigência), categorias (criar, editar, remover, reordenar, validação de vagas, naturezas obrigatórias, cotas em vagas e percentual, cotas acima do total recusadas).
