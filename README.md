# Cari Tech Graphic — Website

Site institucional do estúdio **Cari Tech Graphic** (design, marketing e tecnologia · Nampula, Moçambique).
Feito para captar e responder a clientes: formulários de contacto/orçamento que chegam ao seu e-mail,
registo automático de leads, botão de WhatsApp e chat de apoio.

> **Stack:** HTML + CSS + React (via CDN, sem build) + um único ficheiro PHP para receber os formulários.
> Corre em qualquer hospedagem partilhada com PHP (Hostinger, cPanel, etc.) e também em VPS.

---

## 1. Estrutura do projecto

```
cari-tech-graphic/
├── index.html                 ← página principal (abre automaticamente no domínio)
├── sobre.html                 ← página "Sobre nós"
├── admin.html                 ← painel de administração (login + gestão de leads)
├── enviar.php                 ← recebe os formulários e envia o e-mail  ⭐
├── admin-api.php              ← API do painel (login + leads + conteúdo)  ⭐
├── conteudo.php               ← conteúdo público (serviços/portfólio geridos)
├── config.php                 ← as SUAS definições (e-mail, WhatsApp, senha do painel, SMTP)  ⭐
├── .htaccess                  ← HTTPS, cache, segurança, protecção de ficheiros
├── robots.txt
├── assets/
│   ├── css/                   ← todos os estilos
│   └── js/                    ← config.js, i18n.js e componentes React (.jsx)
├── uploads/                   ← imagens do site
├── dados/                     ← cópia de segurança dos leads (leads.csv) — protegida
└── docs/                      ← guia de implantação em PDF/HTML e capturas de ecrã
```

Os únicos ficheiros que você precisa de editar são **`config.php`** (definições do servidor)
e **`assets/js/config.js`** (contactos mostrados no site).

---

## 2. Publicar na Hostinger (hospedagem partilhada) — recomendado

O plano **Profissional** da Hostinger já inclui PHP e envio de e-mail — é tudo o que este site precisa.

### Passo 1 — Preparar o ZIP
Baixe/compacte a pasta do projecto num ficheiro `.zip` (com os ficheiros na raiz, não dentro de uma subpasta extra).

### Passo 2 — Enviar para o servidor
1. Entre em **hpanel.hostinger.com** e escolha o seu site.
2. **Ficheiros → Gestor de Ficheiros** e abra a pasta **`public_html`**.
3. Apague ficheiros de exemplo que a Hostinger tenha criado (`default.php`, `index.html` em branco).
4. Clique em **Upload** e envie o `.zip`; depois clique com o botão direito → **Extrair** para descompactar no servidor.

> Não é preciso renomear nada: o ficheiro principal já se chama `index.html` e abre sozinho no domínio.

### Passo 3 — Criar o e-mail que recebe os contactos
1. No hPanel, vá a **E-mails → Contas de E-mail** e crie uma caixa no seu domínio,
   por exemplo `contacto@seudominio.com`.
2. Crie também `no-reply@seudominio.com` (usada como remetente dos avisos).

### Passo 4 — Configurar `config.php`
No Gestor de Ficheiros, edite **`config.php`** e ajuste:

```php
'to_email'   => 'contacto@seudominio.com',   // onde quer receber os pedidos
'from_email' => 'no-reply@seudominio.com',   // TEM de ser do seu domínio
'whatsapp'   => '258XXXXXXXXX',              // só dígitos, formato internacional
```

E edite **`assets/js/config.js`** com o WhatsApp, e-mail e telefone que aparecem no site.

### Passo 5 — Activar HTTPS (SSL)
Em **Segurança → SSL**, active o certificado gratuito. O `.htaccess` já força `https://` automaticamente.

### Passo 6 — Testar
Abra o seu domínio, preencha o formulário de contacto e confirme que o e-mail chega.
Se não carregar a versão nova, faça *hard refresh*: `Ctrl+Shift+R` (Windows) / `Cmd+Shift+R` (Mac).

---

## 3. Como funciona a interação com clientes

| Canal | O que acontece |
|-------|----------------|
| **Formulário de Contacto / Orçamento** | Envia para `enviar.php` → valida → **guarda em `dados/leads.csv`** → envia e-mail para si. |
| **Rede de segurança** | Mesmo que o e-mail falhe, o lead fica gravado no CSV e o cliente é convidado a continuar pelo WhatsApp — nenhum contacto se perde. |
| **Botão WhatsApp** | Abre uma conversa com mensagem pré-preenchida (nome, serviço, mensagem). |
| **Chat de apoio** | Responde a perguntas comuns (preços, serviços, prazos, contactos) e encaminha para o WhatsApp. Não precisa de nenhuma chave de API para funcionar no site publicado. |

**Ver os leads guardados:** use o **painel de administração** (ver secção 3.1) ou, em alternativa,
baixe `dados/leads.csv` do Gestor de Ficheiros e abra no Excel/Sheets.
A pasta `dados/` está bloqueada ao público pelo `.htaccess` (ninguém acede pela web).

### 3.1. Painel de administração (`admin.html`)

Página protegida por palavra-passe para gerir os pedidos que chegam pelo site.

- **Entrar:** abra `https://seudominio.com/admin.html` — pede a palavra-passe.
- **Senha padrão:** `caritech2026` — **mude-a assim que publicar** (ver abaixo).
- **Aba Leads (dados reais):** lista os contactos, permite mudar o estado
  (Novo → Contactado → Ganho / Perdido), responder por **WhatsApp** ou **e-mail** com um clique,
  e remover. Os estados ficam guardados em `dados/leads.json`.
- **Abas Serviços e Portfólio (editáveis, refletidas no site):** adicione, edite ou remova
  serviços e projectos, **e edite os cabeçalhos de cada secção** (rótulo, título e descrição
  que aparecem no site). O que marcar como **Activo/Publicado** aparece na página inicial;
  "Rascunho" fica oculto no site. É guardado automaticamente em `dados/conteudo.json` e lido
  pelo site através de `conteudo.php`. Enquanto não editar nada, o site mostra os textos padrão.
- **Aba Testemunhos (editável, refletida no site):** adicione, edite ou remova depoimentos de
  clientes (texto, nome, cargo) e edite o cabeçalho da secção. Os 3 primeiros marcados como
  **Activo** aparecem no site; "Rascunho" fica oculto.
- **Aba Contactos:** edite o e-mail, telefone, WhatsApp, morada e horário. Estes valores
  aparecem na secção Contacto, no rodapé, no botão flutuante de WhatsApp e são usados pelos
  formulários e pelo chat. Guardado automaticamente.
- **Botão "Ver site":** abre a página pública numa nova aba — como o site lê o conteúdo a cada
  carregamento, mostra sempre a versão mais recente do que guardou.

**Mudar a palavra-passe do painel:**
```bash
php -r 'echo password_hash("A_SUA_NOVA_SENHA", PASSWORD_DEFAULT);'
```
Copie o resultado para `config.php` → `admin_password_hash`. (Também há instruções dentro do painel, na aba Definições.)

> Segurança: o acesso usa sessão PHP com cookie `HttpOnly`/`SameSite=Strict`, a senha é guardada
> apenas como *hash* bcrypt, e a API (`admin-api.php`) recusa qualquer pedido sem sessão válida.

### Melhorar a entrega de e-mail (opcional, mas recomendado)
A função `mail()` do PHP às vezes cai no spam. Para entrega fiável via SMTP:

1. Instale o PHPMailer na pasta do projecto (via SSH):
   ```bash
   composer require phpmailer/phpmailer
   ```
2. Em `config.php`, ponha `'smtp_enabled' => true` e preencha `smtp_host`, `smtp_user`, `smtp_pass`
   (use os dados SMTP da sua caixa de e-mail Hostinger — porta 465/SSL).

---

## 4. Publicar num VPS (só se precisar de mais controlo)

Um VPS **não é necessário** para este site — a hospedagem partilhada chega perfeitamente.
Considere VPS apenas se, no futuro, quiser: tráfego muito alto, uma base de dados própria,
um back-end Node/Python, ou vários sites no mesmo servidor.

### Opção A — Nginx (site estático + PHP-FPM)

```bash
# 1. Instalar servidor web + PHP
sudo apt update
sudo apt install -y nginx php-fpm php-cli

# 2. Colocar o site
sudo mkdir -p /var/www/caritech
sudo cp -r ./* /var/www/caritech/
sudo chown -R www-data:www-data /var/www/caritech
```

Configuração `/etc/nginx/sites-available/caritech`:

```nginx
server {
    listen 80;
    server_name seudominio.com www.seudominio.com;
    root /var/www/caritech;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Processar o enviar.php
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php-fpm.sock;
    }

    # Bloquear ficheiros sensíveis
    location ~ ^/(config\.php|dados/) { deny all; return 403; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/caritech /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# 3. HTTPS grátis com Let's Encrypt
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d seudominio.com -d www.seudominio.com
```

### Opção B — Apache
O `.htaccess` incluído já funciona no Apache. Instale `apache2` + `libapache2-mod-php`,
copie os ficheiros para `/var/www/html`, active `mod_rewrite`/`mod_headers`
(`sudo a2enmod rewrite headers expires deflate`) e corra o `certbot --apache` para o SSL.

### Opção C — Docker (portátil)
```dockerfile
FROM php:8.2-apache
RUN a2enmod rewrite headers expires deflate
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html
EXPOSE 80
```
```bash
docker build -t caritech . && docker run -d -p 80:80 --name caritech caritech
```

Em VPS, o e-mail via `mail()` normalmente **não** sai (sem servidor de correio). Use **SMTP**
(secção 3) apontando para um provedor de e-mail — é o método fiável num VPS.

---

## 5. Desenvolvimento local

Como há um ficheiro PHP, use um servidor com PHP (abrir o `index.html` por `file://` não processa o formulário):

```bash
php -S localhost:8000
# abra http://localhost:8000
```

Sem PHP instalado, o site continua a abrir e os formulários oferecem automaticamente
o botão de WhatsApp como alternativa.

---

## 6. Personalização rápida

| Quer mudar… | Edite |
|-------------|-------|
| Contactos mostrados no site (WhatsApp, e-mail, telefone) | `assets/js/config.js` |
| Para onde chegam os e-mails / SMTP | `config.php` |
| Serviços e projectos do portfólio | painel **admin.html** → abas Serviços / Portfólio |
| Textos e traduções gerais (PT/EN) | `assets/js/i18n.js` |
| Cores, tipografia, layout | painel **Tweaks** (canto do site) ou `assets/css/` |
| Imagens (fundador, equipa) | pasta `uploads/` |

---

## 7. Segurança — checklist

- [x] HTTPS forçado pelo `.htaccess`
- [x] `config.php` e `dados/` bloqueados ao acesso web
- [x] Honeypot anti-spam nos formulários
- [x] Validação e limpeza dos dados no servidor
- [x] Painel `admin.html` protegido por login (sessão PHP + senha em hash bcrypt)
- [ ] Depois de publicar, **mude a senha do painel** (secção 3.1) e a `smtp_pass` em `config.php`

---

## 8. Suporte

- **Checklist de primeira publicação** (recomendado, interactivo e imprimível):
  **`docs/checklist-primeira-publicacao.html`** — abra no navegador e marque cada passo;
  o progresso fica guardado. Cobre todo o fluxo: subir → e-mails → configurar → SSL →
  testar → mudar a senha do painel → personalizar.
- **Guia visual detalhado:** **`docs/guia-implantacao-hostinger.html`**.
