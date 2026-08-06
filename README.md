# Cari Tech Graphic — Website

Site do estúdio **Cari Tech Graphic** (design, marketing e tecnologia · Nampula, Moçambique),
pensado para **captar, atender e entregar trabalho a clientes**: formulários que chegam ao seu
e-mail, painel de gestão, **Área de Cliente com entregas**, WhatsApp e chat de apoio.

> **Stack:** HTML + CSS + React (via CDN, **sem build**) + PHP. Corre em qualquer
> hospedagem partilhada com PHP (Hostinger, cPanel…) e também em VPS. Sem dependências obrigatórias.

---

## Índice

1. [O que o site faz](#1-o-que-o-site-faz)
2. [Estrutura dos ficheiros](#2-estrutura-dos-ficheiros)
3. [Publicar na Hostinger](#3-publicar-na-hostinger-passo-a-passo)
4. [Acessos: administração e clientes](#4-acessos-administração-e-clientes)
5. [Painel de administração](#5-painel-de-administração-adminhtml)
6. [Área de Cliente e entregas](#6-área-de-cliente-e-entregas)
7. [Login unificado e login social (Google)](#7-login-unificado-e-login-social-google)
8. [Onde os dados ficam (ficheiros ou MySQL)](#8-onde-os-dados-ficam-ficheiros-ou-mysql)
9. [E-mail e SMTP](#9-e-mail-e-smtp)
10. [Atualizar o site (versões)](#10-atualizar-o-site-versões)
11. [VPS (opcional)](#11-publicar-num-vps-opcional)
12. [Desenvolvimento local](#12-desenvolvimento-local)
13. [Segurança](#13-segurança)

---

## 1. O que o site faz

| Área | Descrição |
|------|-----------|
| **Site público** | Página inicial, "Sobre", serviços, portfólio, testemunhos, contactos — tudo editável no painel. |
| **Pedidos (leads)** | Formulário de contacto/orçamento e **checkout** de serviços → chegam ao seu e-mail e ficam guardados. |
| **Recibo automático** | Quem faz um pedido recebe um e-mail de confirmação com o resumo e o acesso à Área de Cliente. |
| **Painel de gestão** | Gerir leads, serviços, portfólio, testemunhos, contactos, logótipo, pagamentos e **entregas**. |
| **Área de Cliente** | O cliente entra com e-mail + código e **transfere as suas entregas** (ficheiros e links). |
| **Login unificado** | Um único acesso (`entrar.html`) que encaminha para o painel (admin) ou a Área de Cliente. |
| **WhatsApp + Chat** | Botão de WhatsApp com mensagem pré-preenchida e chat de apoio (sem chave de API). |
| **PT / EN** | Site e painel totalmente bilingues. |

---

## 2. Estrutura dos ficheiros

```
cari-tech-graphic/
├── index.html            ← página principal
├── sobre.html            ← página "Sobre nós"
├── entrar.html           ← login unificado (admin ou cliente)     ⭐
├── admin.html            ← painel de administração
├── cliente.html          ← Área de Cliente (entregas)             ⭐
├── enviar.php            ← recebe os formulários, guarda o lead e envia e-mails
├── auth.php              ← login unificado (decide admin vs cliente)
├── admin-api.php         ← API do painel (leads, conteúdo, entregas…)
├── cliente-api.php       ← API pública da Área de Cliente
├── conteudo.php          ← conteúdo público (serviços/portfólio geridos)
├── armazenamento.php     ← camada de dados: MySQL ou ficheiros (fallback)
├── config.php            ← as SUAS definições (e-mail, WhatsApp, senha, BD, SMTP)  ⭐
├── .htaccess             ← HTTPS, cache, segurança
├── assets/{css,js}/      ← estilos e componentes React (.jsx)
├── uploads/              ← imagens e logótipo (e uploads/entregas/ para os ficheiros entregues)
├── dados/               ← leads e conteúdo (protegida ao público)
├── atualizacoes/         ← pacotes .zip de cada versão + guia
└── docs/                 ← guias de implantação e schema.sql
```

Normalmente só edita **`config.php`** (definições do servidor). O resto gere-se pelo painel.

---

## 3. Publicar na Hostinger (passo a passo)

O plano **Profissional** já inclui PHP e envio de e-mail — é tudo o que este site precisa.

1. **Enviar os ficheiros.** hPanel → **Ficheiros → Gestor de Ficheiros** → pasta **`public_html`**.
   Envie o `.zip` do projecto e **Extrair** (os ficheiros devem ficar na raiz de `public_html`,
   não dentro de uma subpasta).
2. **Criar os e-mails.** hPanel → **E-mails** → crie `contacto@seudominio.com` e `no-reply@seudominio.com`.
3. **Configurar `config.php`:**
   ```php
   'to_email'   => 'contacto@seudominio.com',   // onde recebe os pedidos
   'from_email' => 'no-reply@seudominio.com',   // TEM de ser do seu domínio
   'whatsapp'   => '258XXXXXXXXX',              // só dígitos, formato internacional
   'site_url'   => 'https://seudominio.com',    // usado no recibo (link da Área de Cliente)
   ```
4. **Activar HTTPS.** Segurança → SSL → activar. O `.htaccess` já força `https://`.
5. **Testar.** Abra o domínio, envie o formulário e confirme o e-mail. Depois entre em
   `seudominio.com/admin.html` e **mude a palavra-passe**.

> **Nada a atualizar no site?** Faça *hard refresh*: `Ctrl+Shift+R` (Windows) / `Cmd+Shift+R` (Mac).
> Cada versão do site já força a recarga do CSS/JS automaticamente (ver [secção 10](#10-atualizar-o-site-versões)).

---

## 4. Acessos: administração e clientes

Há **um único ponto de entrada**: **`seudominio.com/entrar.html`** (também no botão **Entrar** do menu).
Consoante as credenciais, o sistema encaminha:

| Perfil | Credenciais | Vai para |
|--------|-------------|----------|
| **Administrador** | utilizador + palavra-passe | `admin.html` (painel de gestão) |
| **Cliente** | e-mail + código do pedido | `cliente.html` (Área de Cliente) |

- Administrador padrão: **`admin`** / **`caritech2026`** — **mude assim que entrar**.
- O cliente recebe o **código** no e-mail de confirmação (recibo automático) de cada pedido.

**Login único:** `admin.html` e `cliente.html` reencaminham automaticamente para `entrar.html`
quando não há sessão — há uma só página de login, e são as **credenciais** que decidem o destino.
Pode também entrar com **Google/Facebook** (ver [secção 7](#7-login-unificado-e-login-social-google)).

**Sessão:** ao voltar (sem terminar sessão) e clicar Entrar, vai **directo à conta**; a sessão
desliga sozinha após **15 min de inactividade** (cliente e administrador).

**Painel oculto (segurança):** em **Definições → Segurança**, o administrador define/gera um
endereço secreto. O painel passa a abrir só em **`painel.php?k=SEGREDO`** e o acesso directo a
`admin.html` é bloqueado (`.htaccess`) — para despistar ataques automáticos. Se esquecer o segredo,
entre sempre por `entrar.html`: o servidor encaminha-o automaticamente para o endereço certo.

---

## 5. Painel de administração (`admin.html`)

- **Leads:** lista os pedidos, muda o estado, responde por **WhatsApp/e-mail**, **Entregar**
  (ver [secção 6](#6-área-de-cliente-e-entregas)) e **descarregar relatório** (CSV para Excel/Sheets).
- **Serviços:** cada serviço mostra quantos **pedidos** teve (adesão).
- **Finanças:** módulo interno (só admin) para registar entradas/saídas e ver o **saldo**.
- **Serviços / Portfólio / Testemunhos:** adicione, edite, remova e defina os cabeçalhos de cada
  secção. "Activo/Publicado" aparece no site; "Rascunho" fica oculto. Guardado automaticamente.
- **Contactos:** e-mail, telefone, WhatsApp, morada e horário mostrados no site.
- **Definições → Conta:** mude utilizador e palavra-passe (pede a senha actual).
- **Definições → Logótipo:** dois logótipos (fundo claro/escuro); também vira o *favicon*.
- **Definições → Pagamentos:** métodos mostrados no checkout (M-Pesa, e-Mola, transferência, link online).
- **Definições → Pagamento automático:** cobrança directa no checkout, com dois gateways possíveis
  (o que estiver activo é usado; a [PagaJá](https://pagaja.site) tem prioridade quando ambos estão ligados):
  - **[PagaJá](https://pagaja.site)** (recomendado): M-Pesa, e-Mola, mKesh e **Cartão** numa só conta,
    via OAuth2. Requer `client_id`/`client_secret` (gerados em pagaja.site → Programador) e um
    **webhook** configurado num clique no painel — é o que confirma pagamentos por STK push/cartão
    em produção (o checkout mostra "a aguardar confirmação" e o cliente recebe e-mail assim que a
    PagaJá confirmar). Ver `webhook-pagaja.php`.
  - **[MozPayment](https://mozpayment.co.mz)** (recurso, só M-Pesa/e-Mola): o cliente aprova um
    pedido de confirmação (USSD) no telemóvel e o pedido é marcado **Pago** de imediato. Precisa de
    uma carteira MozPayment por método.
  Em ambos, as credenciais/carteiras ficam só no servidor, nunca expostas ao site público.
- **Definições → Sites & Sistemas:** cole links de sites/sistemas que criou — aparecem na secção
  “Nossos Sites & Sistemas” do site com **pré-visualização ao vivo**. "Rascunho" fica oculto.
- **Definições → Atualizar o site (ZIP):** aplica uma nova versão preservando `config.php`, `dados/` e `uploads/`.

> Segurança: sessão PHP com cookie `HttpOnly`/`SameSite=Strict`, palavra-passe em *hash* bcrypt,
> e a API recusa qualquer pedido sem sessão válida.

---

## 6. Área de Cliente e entregas

A Área de Cliente é um **painel** (sidebar fixo + pedidos em grelha). O cliente entra com
**e-mail + código** (ou conta social) e vê **todos os seus pedidos** — cada um com:

- o **estado** (recebido, em andamento, concluído);
- as **entregas**: ficheiros e/ou links, com botão **“Descarregar tudo”**;
- **comentários**: o cliente comenta em cada pedido e o estúdio responde (na aba Leads → Entregar).

Basta **um** código válido para ver a lista completa. O **e-mail de confirmação** é enviado em HTML com o
visual da marca, e o botão leva **directamente à conta do cliente** — só falta introduzir o código.

**Como entregar (no painel):** aba **Leads → botão Entregar** de um pedido:
1. **Carregue ficheiros** (`uploads/entregas/`) e/ou **adicione links**.
2. Escreva uma **mensagem** para o cliente.
3. **Guardar entrega** (o pedido passa a "Ganho") e **Notificar cliente** por WhatsApp/e-mail
   — a notificação inclui o link da Área de Cliente e o código.

Cada pedido recebe um **código de acesso** automático, incluído no recibo enviado ao cliente.

---

## 7. Login unificado e login social (Google)

`entrar.html` tem um formulário único (**e-mail/utilizador** + **palavra-passe/código**) que chama
`auth.php`, que tenta primeiro admin e depois cliente, e devolve para onde encaminhar.

### Login social (Google e Facebook) — gerido no painel

Configura-se em **Definições → Login social** no painel (também aceita valores em `config.php` como
recuperação). Os botões aparecem em `entrar.html` **assim que existir um ID** — pronto a activar:

- **Google** — só precisa do *Client ID*:
  [Google Cloud Console](https://console.cloud.google.com) → **APIs e Serviços → Credenciais →
  ID de cliente OAuth → Aplicação Web** → em *Origens JavaScript autorizadas* ponha `https://seudominio.com`.
- **Facebook** — precisa de *App ID* + *App Secret*:
  [developers.facebook.com](https://developers.facebook.com) → criar app → adicionar **Facebook Login**.
  O *App Secret* fica **só no servidor** (nunca é exposto ao público).
- **E-mail de administrador** (opcional): se entrar com uma conta social com esse e-mail, vai para o
  **painel**; caso contrário, o cliente entra se o e-mail **coincidir com um pedido** existente.

Os tokens são **validados no servidor** (Google `tokeninfo`; Facebook `debug_token` + `appsecret_proof`).

---

## 8. Onde os dados ficam (ficheiros ou MySQL)

Por omissão **não é preciso base de dados**: tudo fica em ficheiros na pasta `dados/`.

| Dados | Ficheiros (padrão) | MySQL |
|-------|--------------------|-------|
| Leads (+ códigos) | `dados/leads.json` (+ `dados/leads.csv`) | tabela `leads` |
| Conteúdo do site | `dados/conteudo.json` | `content_items` + `settings` |
| Entregas, logótipo, pagamentos, admin | `dados/*.json` | tabela `settings` |

**Usar MySQL (opcional):** hPanel → **Bases de Dados → MySQL**, crie BD + utilizador, preencha a
secção `'db'` do `config.php` e ponha `'enabled' => true`. As tabelas criam-se sozinhas
(`docs/schema.sql` para referência). Se a ligação falhar, o site volta **automaticamente** aos ficheiros.

---

## 9. E-mail e SMTP

`enviar.php` usa a função `mail()` do PHP (funciona na Hostinger). Para melhor entregabilidade,
active **SMTP** — agora directamente no painel em **Definições → E-mail (SMTP)**:

1. hPanel → **E-mails → Contas de E-mail** → crie/abra `no-reply@seudominio.com`.
2. No painel, active **SMTP** e preencha: host `smtp.hostinger.com`, porta `465` (SSL),
   utilizador = o e-mail completo, palavra-passe = a **palavra-passe dessa caixa**.
3. (Opcional, para SMTP: instale o PHPMailer via SSH — `composer require phpmailer/phpmailer`.)

As definições do painel têm prioridade sobre o `config.php`. O **recibo** e as **notificações**
ao cliente ligam/desligam com `'send_receipt'` e `'notify_client'` no `config.php`.

---

## 10. Atualizar o site (versões)

Cada versão é um pacote `.zip` em **`atualizacoes/`**, no formato **1.0.0** (use o número mais alto).
No painel: **Definições → Atualizar o site (ZIP)** → escolha o ZIP → **Atualizar**.

- **Preservados sempre:** `config.php`, `dados/` (leads e conteúdo) e `uploads/` (imagens/logótipo).
- Cada versão força a **recarga do CSS/JS** (cache-busting `?v=`), por isso as alterações aparecem
  logo — sem esperar pela expiração da cache. A versão actual aparece no canto do painel.
- Detalhes e histórico: **`atualizacoes/LEIA-ME.md`**.

---

## 11. Publicar num VPS (opcional)

Não é necessário — a hospedagem partilhada chega. Num VPS, sirva os ficheiros com **Nginx + PHP-FPM**
ou **Apache** (o `.htaccess` incluído já funciona no Apache com `mod_rewrite`/`headers`/`expires`),
e obtenha SSL grátis com `certbot`. Num VPS, `mail()` normalmente não sai — use **SMTP** (secção 9).

Exemplo Docker:
```dockerfile
FROM php:8.2-apache
RUN a2enmod rewrite headers expires deflate
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
```

---

## 12. Desenvolvimento local

Como há PHP, use um servidor com PHP (abrir por `file://` não processa os formulários):

```bash
php -S localhost:8000
# abra http://localhost:8000
```

Sem PHP, o site ainda abre e os formulários oferecem o WhatsApp como alternativa.

---

## 13. Segurança

- [x] HTTPS forçado pelo `.htaccess`
- [x] `config.php` e `dados/` bloqueados ao acesso web
- [x] Honeypot anti-spam + validação no servidor
- [x] Painel protegido por login (sessão PHP + hash bcrypt)
- [x] Uploads de entrega limitados a tipos seguros (sem PHP/executáveis)
- [x] Login social valida o token junto da Google
- [ ] Depois de publicar: **mude a palavra-passe do painel** e a `smtp_pass` em `config.php`

---

**Suporte:** `atualizacoes/LEIA-ME.md` (como atualizar) ·
`docs/checklist-primeira-publicacao.html` (checklist imprimível) ·
`docs/guia-implantacao-hostinger.html` (guia visual).
