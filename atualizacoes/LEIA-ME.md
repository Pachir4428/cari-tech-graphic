# Atualizações do site

Cada versão tem um pacote `.zip` aqui, no formato **1.0.0** (maior.menor.correcção):
`cari-tech-graphic-1.0.0.zip`, `cari-tech-graphic-1.0.1.zip`, etc.
**Use sempre o de número mais alto.**

> Versão actual: **1.0.4** — a mesma que aparece no canto do painel de administração.

## Como atualizar o site (2 minutos)

1. **Baixe** o ZIP com o número mais alto desta pasta (no GitHub: abra o ficheiro → **Download**).
2. Entre no painel: `https://seudominio.com/admin.html`.
3. Vá a **Definições → Atualizar o site (ZIP)**, escolha o ZIP e clique em **Atualizar site**.
4. Confirme a versão no canto do painel. (Cada versão força a recarga do CSS/JS — sem precisar de limpar cache.)

### O que é preservado (nunca se perde)
- **`config.php`** — as suas definições (e-mail, WhatsApp, base de dados)
- **`dados/`** — os leads e todo o conteúdo do painel
- **`uploads/`** — as imagens e os logótipos

> Dica: faça um backup (hPanel → Backups) antes de atualizar.

---

## Histórico de versões

| Versão | Alterações |
|--------|------------|
| **1.0.4** | **Menu do telemóvel corrigido em definitivo** — o menu, ao fechar, fica totalmente inerte (`visibility`/`pointer-events`), deixa de se sobrepor ao site, e ganha um fundo escurecido que fecha ao tocar fora. **Login social gerido no painel** (Definições → Login social) — **Google e Facebook**: basta colar os IDs e os botões aparecem no login; o App Secret do Facebook fica só no servidor. **Login verdadeiramente único** — `admin.html` e `cliente.html` reencaminham para `entrar.html`; são as credenciais que decidem se vai para o painel ou para a Área de Cliente. |
| **1.0.3** | **Login unificado** (`entrar.html`) — um único acesso que, pelas credenciais, encaminha para o painel (admin: utilizador+palavra-passe) ou para a Área de Cliente (cliente: e-mail+código). **Botão "Entrar" no menu** do site (desktop e telemóvel). **Login social com Google** (opcional) — activa-se pondo o `google_client_id` no `config.php`. **Header do telemóvel** reforçado (logótipo e acções nunca cortados). **README reorganizado** e atualizado. |
| **1.0.2** | **Área de Cliente** (`cliente.html`) — o cliente entra com o **e-mail + código** e acompanha o estado do pedido e **transfere as entregas** (ficheiros e links). No painel, cada lead ganha a acção **“Entregar”**: carregue ficheiros ou adicione links, escreva uma mensagem e **notifique o cliente** (WhatsApp/e-mail) com o link e o código. **Recibo automático ao cliente** — quem envia um pedido recebe um e-mail de confirmação com o resumo e o acesso à Área de Cliente. **Tradução PT/EN a 100%** — todo o painel (login, formulários de edição, contactos, logótipo, pagamentos, conta, atualização e entregas) passa a mudar de idioma. |
| **1.0.1** | Correcção definitiva do transbordo lateral no telemóvel (secções passam a clipar os elementos decorativos → logótipo deixa de ser cortado, conteúdo centrado, menu no sítio certo e visível). **Pagamentos no checkout** — configuráveis no painel (M-Pesa, e-Mola, transferência e link de pagamento online); mostrados ao cliente ao finalizar o pedido. **Tradução PT/EN** alargada a tabelas, estados, listas e formulários dos separadores. |
| **1.0.0** | Versionamento semântico (1.0.0) e **cache-busting** (cada versão recarrega CSS/JS automaticamente). Correcções mobile: hero do site sem transbordo, conteúdo centrado, acções do cabeçalho no canto, **padding do checkout** corrigido. Painel: **notificações** — sino no topo (na linha do tema) com contador de novos pedidos, aviso do navegador e botão **“notificar cliente”** (WhatsApp/e-mail); **tradução PT/EN** da navegação, dashboard e separadores principais. |
| (anterior) | Painel de gestão completo (leads, serviços, portfólio, testemunhos, contactos, logótipo claro/escuro, imagens, checkout de serviços), MySQL opcional, atualização por ZIP. |
