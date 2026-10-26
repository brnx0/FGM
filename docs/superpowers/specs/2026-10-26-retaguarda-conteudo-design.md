# Retaguarda — login de gestores e gestão do conteúdo do portal · Design

Data: 2026-10-26 · Status: aguardando aprovação

## 1. Intenção

A FGM precisa de uma área interna (retaguarda) onde a equipe gerencia o que o portal público exibe. Hoje os editais vêm de seeder e os eventos de uma lista em código (`App\Support\EventosMock`); o botão de cada edital na home baixa sempre o mesmo PDF de modelo (`public/documentos/edital-modelo.pdf`). A retaguarda terá, no futuro, um menu com várias telas (inscritos, habilitação, validação de documentos — ver fluxo geral do projeto); **esta fase cobre só a gestão do conteúdo exibido no portal**.

## 2. Objetivos

1. Login da retaguarda por **e-mail e senha** ou por **gov.br**, aceito **apenas para usuários que têm um papel (role)**.
2. Papel único nesta fase: **Administrador** (pode tudo). A estrutura de papéis já existe para receber outros papéis e permissões depois.
3. **Primeiro gestor criado por seeder.** Os demais são cadastrados por um gestor dentro da retaguarda.
4. Gestão (listar, criar, editar, publicar/despublicar, excluir) de **Editais** e **Eventos**, incluindo **upload do PDF de cada edital** e de imagem.
5. A home e a página do edital passam a ler esses dados do banco; o botão do edital baixa **o PDF daquele edital**.

## 3. Não-objetivos (agora)

- Gestão de inscritos, habilitação e validação de documentos.
- Definição dos demais papéis e de permissões finas (vêm "em breve", segundo o usuário).
- Campos extras do edital (valores, vagas, anexos múltiplos, resultados) — "no futuro vamos nos aprofundar no edital".
- Painel inicial/dashboard da retaguarda ("o que ela vê primeiro não vem ao caso").

## 4. Anti-objetivos

- Alguém sem papel (por exemplo, um proponente que entrou pelo gov.br) conseguir abrir qualquer tela da retaguarda.
- Senha de gestor guardada sem hash, ou credenciais do primeiro gestor versionadas no repositório.
- A retaguarda ganhar uma identidade visual diferente da que o portal já tem.

## 5. Restrições

- Stack existente: Laravel 13 + Inertia v2 + Vue 3 em **TypeScript** (strict, `vue-tsc` no build) + Tailwind v4, Oracle 23ai via `yajra/laravel-oci8`, PHPUnit, Docker. Seguir `CLAUDE.md` (Laravel Boost): `php artisan make:*`, Pint, testes de feature, **não adicionar dependências sem aprovação** (por isso papéis em tabelas próprias, sem pacote de permissões).
- **Identidade visual: a mesma já aplicada no portal** (tokens de `resources/css/app.css`, fontes, modo noturno, alto contraste, A-/A/A+). Sem mockups novos — decisão do usuário.
- Datas Oracle: seguir `App\Models\Concerns\UsaDiaCivil` (gravar meia-noite, consultar em intervalo semiaberto).
- Tudo em português do Brasil; CPF sempre string de 11 dígitos, mascarado na interface.

## 6. Acesso à retaguarda

- Área em **`/retaguarda`**; tela de entrada em **`/retaguarda/login`** com: formulário **e-mail + senha** e botão **"Entrar com gov.br"**.
- **E-mail e senha:** senha com hash (bcrypt/argon do Laravel), `session()->regenerate()` no login, limite de tentativas (throttle) por e-mail+IP, mensagem genérica de erro ("E-mail ou senha inválidos.").
- **gov.br:** reaproveita o fluxo existente (`GovBrClient`, login simulado em local/staging). O pedido de login guarda na sessão que o destino é a retaguarda; no retorno, se o CPF pertence a um usuário **com papel**, entra e vai para `/retaguarda`; se não, **não entra** e volta para `/retaguarda/login` com "Seu CPF não tem acesso à retaguarda."
- **Só entra quem tem papel.** Um middleware protege todas as rotas `/retaguarda/*` (exceto login): sem sessão → login da retaguarda; com sessão mas sem papel → 403/login com aviso. Gestor desativado não entra.
- **Sair** encerra a sessão (e, se entrou pelo gov.br real, segue o logout do gov.br já existente).

## 7. Usuários, papéis e primeiro gestor

- Gestores são registros da tabela `users` existente. Mudanças: `password` (nullable — proponentes continuam sem senha), `email` passa a ser **único quando preenchido**, campo `ativo` (bool, padrão verdadeiro). Gestor exige **nome, e-mail e CPF** (válido, com dígito verificador, único) — o CPF é o que vincula o gestor ao gov.br. `users.cpf` **continua NOT NULL**.
- Tabelas `roles` (`id`, `nome`, `slug` único) e `role_user` (pivô). Papel inicial: **Administrador** (`administrador`). `User::temPapel(string $slug): bool` e `User::ehGestor(): bool` (tem algum papel e está ativo).
- **Seeder do primeiro gestor** (`PrimeiroGestorSeeder`, chamado pelo `DatabaseSeeder`): cria o papel Administrador e o usuário **`admin@admin`**, senha **`123456`**, nome "Administrador", com esse papel — valores fixos no seeder, sem `.env` (decisão do usuário). Para manter `users.cpf` NOT NULL, ele recebe o CPF de preenchimento **`00000000000`** (inválido, nunca devolvido pelo gov.br, portanto nunca usado para entrar) — decisão do usuário; esse usuário entra só por e-mail e senha. Ao editar esse usuário na tela Gestores, o formulário pede um CPF válido como para qualquer gestor. Idempotente (não recria nem redefine a senha se o usuário já existe).
- *(decisão do Claude, por segurança)* O seeder do admin **não roda com `APP_ENV=production`** — em produção o primeiro administrador é criado de outra forma, a combinar. A senha `123456` vale para local e homologação.
- **Tela Gestores:** listar (nome, e-mail, CPF mascarado, papel, ativo), criar (nome, e-mail, CPF, senha inicial, papel), editar (inclusive trocar senha), ativar/desativar. Um gestor não pode desativar a si mesmo nem remover o próprio papel. Sem exclusão definitiva.

## 8. Editais na retaguarda

- Campos (mesmos de hoje) + **PDF**: título, resumo, categoria, início e fim das inscrições, imagem (opcional), **PDF do edital**, publicado.
- Slug gerado automaticamente do título (único); a situação (Aberto/Em breve/Encerrado) continua calculada pelas datas.
- **PDF obrigatório para publicar** *(decisão do Claude)*: pode salvar rascunho sem PDF, mas não marcar como publicado sem ele. Só PDF, até 20 MB *(decisão do Claude)*.
- Listagem com busca por título, filtro por situação e por publicado, paginação.
- Excluir com confirmação; usa **exclusão lógica (soft delete)** para não perder histórico quando houver inscrições *(decisão do Claude)*.
- No portal: o botão do edital baixa o PDF daquele edital; o arquivo de modelo deixa de ser usado. Edital sem PDF não aparece publicado.

## 9. Eventos na retaguarda

- Os eventos **voltam a ter tabela no banco** (`eventos`: título, slug, resumo, data de realização, local, imagem, publicado, soft delete), substituindo `App\Support\EventosMock` — o mock foi pedido "por enquanto", até existir a gestão.
- Os exemplos atuais do mock viram dados de um seeder de eventos (ambiente local).
- Mesma gestão dos editais (listar, criar, editar, publicar/despublicar, excluir com confirmação), com upload de imagem opcional.
- A home continua mostrando os **6 realizados e publicados mais recentes**, no mesmo formato de props que tem hoje.

## 10. Arquivos (PDFs e imagens)

- Guardados no disco **`public`** do Laravel (`storage/app/public`, servido via `storage:link`) *(decisão do Claude — são arquivos públicos do portal; os documentos pessoais do cadastro continuam no disco `local`, privado)*.
- Ao trocar ou excluir, o arquivo antigo é apagado. Validação por tipo real (mimes) e tamanho: imagem jpg/png/webp até 5 MB.

## 11. Telas e navegação

- Layout próprio da retaguarda (`RetaguardaLayout`), com a mesma identidade visual do portal: menu lateral com **Editais**, **Eventos** e **Gestores** (o menu já pensado para receber mais itens), topo com nome do gestor e "Sair", recolhível no celular.
- `/retaguarda` abre a lista de Editais *(decisão do Claude, já que o painel inicial está fora do escopo)*.
- Formulários com mensagens de validação em português, confirmação antes de excluir, aviso de sucesso após salvar.

## 12. Decisões do Claude após a revisão (conferir)

1. **gov.br para a retaguarda com CPF que não existe em `users`:** recusa sem criar usuário (o fluxo do proponente pelo portal continua igual, criando o usuário). Gestor que entra pelo gov.br **não** tem nome/e-mail sobrescritos pelos dados do gov.br — o cadastro do gestor manda.
2. **Uma sessão só** (guard `web` atual). Se a mesma pessoa for proponente e gestora (mesmo CPF), um login vale para as duas áreas; "Sair" encerra as duas. Estar logado sem papel é estado normal (proponente) e só barra a retaguarda.
3. **Migração de `users`:** o Oracle aceita vários `NULL` num índice único, então "e-mail único quando preenchido" é um índice único simples; e-mail vazio é gravado como `NULL`. O builder confere duplicados nos dados locais antes de criar o índice.
4. **PDF de edital publicado:** pode ser **trocado**, mas não removido enquanto o edital estiver publicado (validação). Por segurança, o portal também filtra editais sem PDF (validação + filtro na consulta).
5. **Exclusão lógica:** o slug de um edital/evento excluído continua reservado (não é reaproveitado); a página pública e o PDF de um edital excluído retornam 404.
6. **Gestores:** não é possível desativar o **último administrador ativo**, nem a si mesmo. O e-mail pode ser alterado (continua único).
7. **Imagens existentes:** os seeds atuais não têm imagens; nada a migrar. O README e o setup Docker passam a incluir `php artisan storage:link`.
8. **Eventos "realizados":** mesma regra de hoje — publicado e com data **até hoje** (inclusive), no fuso de Salvador, via `UsaDiaCivil`. A lista de eventos na retaguarda tem busca por título, filtro de publicado e paginação.

## 13. Deixado ao builder

- Estrutura de controllers/form requests/policies, componentes Vue da retaguarda, paginação e busca.
- Como o fluxo gov.br carrega o "destino retaguarda" (sessão), desde que o comportamento da seção 6 se mantenha e os testes existentes do login de proponente continuem passando.
- Testes de feature para: login por senha (ok, inválido, sem papel, desativado, throttle), gov.br com e sem papel, middleware, CRUD de editais/eventos/gestores, regra do PDF para publicar, upload e troca de arquivo, home lendo eventos do banco e o PDF do edital certo.
