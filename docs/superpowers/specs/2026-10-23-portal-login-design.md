# Mapa Cultura (FGM) — Portal público + Login gov.br · Design

Data: 2026-10-23 · Status: pronto para aprovação final

## 1. Intenção

A Fundação Gregório de Mattos (FGM, Prefeitura de Salvador) precisa de um portal moderno onde proponentes culturais encontrem editais e eventos e entrem no sistema com a conta gov.br. O site atual (https://fgm.salvador.ba.gov.br/) serve **apenas como fonte de conteúdo** (nomes de editais, eventos, endereço, textos institucionais) — **o layout/modelo visual dele nunca deve ser reproduzido**. O objetivo é modernizar o processo.

Esta primeira entrega cobre só **o portal público e o login**. Tudo o mais (cadastro do proponente, gestão de editais, inscrições, painel do gestor) vem em fases futuras, e o desenho atual não deve fechar portas para elas.

## 2. Objetivos

1. Página inicial pública (modelo "A" aprovado) com: login gov.br em destaque, vitrine de editais abertos e galeria de eventos realizados.
2. Login de **proponente e preposto** via gov.br (OpenID Connect + PKCE), pronto para produção, ativado só por configuração.
3. Login **simulado** para desenvolvimento enquanto as credenciais gov.br não chegam.
4. Após login, redirecionar para a tela **"Complete seu cadastro"** (placeholder).

## 3. Não-objetivos (agora)

- Perfil e login do **gestor** (quem administra publicações) — fase futura.
- Cadastro completo do proponente/preposto, diferenciação proponente × preposto.
- Página de detalhe de edital com documentos, inscrição em edital.
- CRUD/painel administrativo de editais e eventos.

## 4. Anti-objetivos (seria fracasso mesmo funcionando)

- Parecer com o site atual da FGM.
- Login simulado acessível em produção, ou em homologação sem o aviso de ambiente de teste.
- Guardar senha de usuário (o sistema não tem senha própria para proponentes).
- Usar versão vulnerável de Laravel/PHP.

## 5. Stack e restrições (decididas pelo usuário)

| Item | Decisão |
|---|---|
| Backend | PHP + **Laravel 13** — usar a última versão estável (hoje **13.38.0**, 2026-10-20), que inclui as correções de 2026 (CVE-2026-114509 TokenGuard, CVE-2026-102279, CVE-2026-48019). Mínimo aceitável: 13.30.0. |
| PHP | **8.4.27+** (corrige CVE-2026-109618/109619, release de 21/10/2026). |
| Frontend | **Vue.js 3** |
| Banco | **Oracle** — via `yajra/laravel-oci8` (v13.x, compatível com Laravel 13; exige extensão `oci8` + Oracle Instant Client). Ainda não existe Oracle do projeto: em desenvolvimento usar **Oracle Database 23ai Free em Docker**; esquema gerido por **migrations do Laravel** (decisão do usuário). |
| Domínio | `https://fgm.techpulseglobal.com.br/` (homologação). Produção exigirá domínio oficial de governo (Portaria SGD/MGI nº 7.076/2024) — fora do escopo do código, só configuração. |

## 6. Login gov.br

- Fluxo: **Authorization Code + PKCE (S256)**, `state` e `nonce` validados, conforme o Roteiro de Integração do Login Único (https://roteiro-tecnico.acesso.gov.br/). Escopos: `openid email profile` (ajustável por `.env`).
- Rotas fixas:
  - `GET /auth/govbr/redirect` — inicia o fluxo.
  - `GET /auth/govbr/callback` — retorno do gov.br. URL de homologação: `https://fgm.techpulseglobal.com.br/auth/govbr/callback`.
  - `POST /logout` — encerra a sessão local e redireciona para o logout do gov.br, que volta para `GET /auth/govbr/logout` → página inicial.
- Tudo parametrizado em `.env`: `GOVBR_CLIENT_ID`, `GOVBR_CLIENT_SECRET`, `GOVBR_REDIRECT_URI`, `GOVBR_LOGOUT_REDIRECT_URI`, `GOVBR_BASE_URL` (staging `https://sso.staging.acesso.gov.br` / produção `https://sso.acesso.gov.br`), `GOVBR_SCOPES`, `GOVBR_DRIVER` (`govbr` | `fake`).
- Validar o `id_token` (assinatura via JWKS do gov.br, `iss`, `aud`, `exp`, `nonce`). Dados usados: `sub` (CPF), `name`, `email`, `email_verified`, `phone_number` (se vier), nível de confiabilidade (se disponível).
- **Primeiro acesso:** usuário criado/atualizado automaticamente a partir do gov.br (chave: CPF). Sem senha.
- **Pós-login:** se o cadastro não estiver completo → `/cadastro` ("Complete seu cadastro", placeholder com nome/CPF mascarado e texto "em breve"). Nesta fase todo usuário cai lá.
- **Login simulado (`GOVBR_DRIVER=fake`):** tela que imita a escolha de conta, com alguns CPFs/nomes de teste (CPFs válidos fictícios) e campo livre. Executa o mesmo caminho pós-callback do real. **Funciona em `APP_ENV=local` e `APP_ENV=staging` (homologação)** — decisão do usuário: a homologação usa o simulado até as credenciais gov.br chegarem. Em homologação, exibe uma **faixa de aviso fixa e visível em todas as páginas** ("Ambiente de teste — login simulado, não use dados reais"). Em `APP_ENV=production` a rota retorna 404 e o boot da aplicação falha se `GOVBR_DRIVER=fake`.

## 7. Portal público (página inicial — modelo A aprovado)

Ordem das faixas (largura total, sem cantos arredondados, a troca de cor é o divisor):

0. **Elementos herdados do FPS-SEMOP** (pedido do usuário em 2026-10-23, iguais ao https://fps-semop.techpulseglobal.com.br/):
   - Link "Pular para o conteúdo" (visível só no foco por teclado).
   - **Barra institucional** fina no topo, fundo azul profundo #0A2747, com ícone de escudo e o texto "Site oficial da Prefeitura de Salvador · Fundação Gregório de Mattos". À direita ficam os controles de acessibilidade **A-**, **A**, **A+** (tamanho da fonte em 3 níveis: 90%, 100% e 115%) e **Alto contraste**. As preferências ficam salvas no navegador.
   - **Logo** no topo: logo da FGM + logo "Salvador Prefeitura". *(Arquivos oficiais pendentes; enquanto não chegam, usar marca em texto "Fundação Gregório de Mattos" + "Mapa Cultura".)*
   - **Abertura com imagem de fundo** (`public/images/hero-salvador.jpg`) em largura total, com sobreposição azul da família Prefeitura e texto branco. A borda inferior é em onda.
   - **Aviso de cookies** em cartão flutuante na parte de baixo, com botão "Entendi" e fechar, e link para Privacidade. Ao ser dispensado, não aparece mais.
1. **Topo fixo** branco translúcido (blur), logo abaixo da barra institucional: logo, links Editais · Eventos · A FGM · Dúvidas, botão pílula "Entrar com gov.br". Logado: nome do usuário + "Sair".
2. **Abertura (imagem de Salvador com sobreposição azul, texto branco):** título "Sua cultura começa aqui.", subtítulo sobre inscrição em editais da FGM com gov.br, botões "Entrar com gov.br" e "Ver editais" (âncora para a vitrine).
3. **Editais (faixa azul Prefeitura #004D9F, texto branco):** número grande de editais abertos + "editais com inscrições abertas." + cards brancos (situação Aberto/Em breve, nome, prazo, "Saiba mais ›").
4. **"Já aconteceu." (azul-névoa #ECF6FF):** grade de eventos realizados com foto, nome e data.
5. **Rodapé (parchment #F5F5F7):** FGM, endereço (Ladeira da Barroquinha, 02 — Salvador/BA), links do portal, Aviso de privacidade (LGPD), Termos de uso.

Mockup de referência aprovado: `.superpowers/brainstorm/*/content/portal-home-azul-prefeitura.html` (opção A).

**Conteúdo nesta fase:** editais e eventos vêm de tabelas Oracle preenchidas por *seeders* com dados de exemplo baseados no site da FGM (PNAB Salvador Ciclo II, SalCine 2, Pontos de Cultura, Jornada do Patrimônio etc.). O futuro painel do gestor editará essas mesmas tabelas.

## 8. Sistema visual

Base: `DESIGN-apple.md` (linguagem Apple — estrutura) copiado para a raiz do projeto como `DESIGN.md`, com estas substituições aprovadas:

- **Tipografia:** Inter (mesma do FPS-SEMOP, https://fps-semop.techpulseglobal.com.br/). Títulos **peso 800** com tracking negativo (≈ -0.025em); corpo 400 a 17px; 600 para ênfase.
- **Cor de ação:** azul Prefeitura **#004D9F** (substitui o #0066cc da Apple) para links, botões e foco. Família: #0C427B (pressionado/escuro), #0A2747 (profundo), #D6EAFF/#ECF6FF (claros).
- **Exceção:** botão "Entrar com gov.br" em **#1351B4** (azul oficial gov.br).
- **Sem preto:** nada de barra preta nem faixas pretas. Texto em #1D1D1F. A barra institucional usa azul profundo #0A2747, que não é preto.
- **Alto contraste:** o modo troca os tokens de cor (fundo escuro, texto branco, ações em amarelo #FFD700, bordas visíveis) e mantém o layout.
- Regras Apple mantidas: botões pílula, cards 18px com borda fina, sem sombras/degradês decorativos, `scale(.95)` ao pressionar, responsivo até 360px, alvos de toque ≥ 44px.
- Acessibilidade: contraste AA, foco visível, navegação por teclado, textos alternativos.

## 9. Dados (Oracle)

- `users`: id, cpf (único), name, email, phone, govbr_sub, govbr_trust_level (nullable), profile_completed_at (nullable), last_login_at, timestamps.
- `editais`: id, titulo, slug, resumo, categoria, status (`aberto`|`em_breve`|`encerrado`), inscricoes_inicio, inscricoes_fim, imagem (nullable), publicado (bool), timestamps.
- `eventos`: id, titulo, slug, resumo, data_realizacao, local (nullable), imagem, publicado (bool), timestamps.
- Sessões em banco ou arquivo (escolha do builder).

## 10. Segurança

- CSRF, cookies `Secure`/`HttpOnly`/`SameSite=Lax`, HTTPS forçado fora de local, cabeçalhos de segurança (CSP, HSTS, X-Frame-Options), rate limit nas rotas de auth, CPF mascarado na interface, `APP_DEBUG=false` fora de local.
- `composer audit` / `npm audit` limpos.
- Aviso de cookies: apenas cookies essenciais (sessão).

## 11. Decisões tomadas pelo Claude (revisar)

- **Nível de confiabilidade gov.br (bronze/prata/ouro):** apenas informativo nesta fase — se a API de níveis responder, grava em `govbr_trust_level`; não bloqueia login.
- **"Saiba mais ›" nos editais:** abre uma página simples do edital (`/editais/{slug}`) com título, resumo, prazo e o aviso "Documentos e inscrição em breve". Eventos não têm detalhe.
- **"A FGM", "Dúvidas", Aviso de privacidade e Termos de uso:** páginas estáticas com texto provisório baseado no site da FGM, marcadas como "texto a validar".
- **Situação do edital:** calculada pelas datas (`inscricoes_inicio` futuro → "Em breve"; dentro do período → "Aberto"; após → "Encerrado"); a coluna `status` sai do modelo. `publicado` controla a visibilidade.
- **DESIGN.md:** copiado de `C:\Users\Breno\Desktop\DESIGN-apple.md` (origem: `npx getdesign@latest add apple`) para a raiz do projeto.
- **Ambiente:** o projeto inclui `docker-compose` (PHP 8.4 + oci8 + Instant Client, e Oracle Free para dev) e um `Dockerfile` reaproveitável na homologação.

## 12. Deixado ao builder

- Arquitetura Laravel+Vue (Inertia vs SPA via API), Tailwind ou CSS próprio, estrutura de pastas.
- Como rodar Oracle + oci8 em desenvolvimento no Windows.
- Implementação OIDC (Socialite + provider custom ou cliente OIDC próprio).
- Testes automatizados (feature tests do fluxo de login real com gov.br mockado e do fake; teste do bloqueio do fake fora de local).
- Imagens de exemplo dos eventos.
