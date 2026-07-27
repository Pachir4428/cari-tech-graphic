# Atualizações do site

Cada versão tem um pacote `.zip` aqui, no formato **1.0.0** (maior.menor.correcção):
`cari-tech-graphic-1.0.0.zip`, `cari-tech-graphic-1.0.1.zip`, etc.
**Use sempre o de número mais alto.**

> Versão actual: **1.3.1** — a mesma que aparece no canto do painel de administração.

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
| **1.3.1** | **Notificações por e-mail ao cliente:** sempre que o estúdio adiciona uma **nova entrega** ou **responde** a um pedido, o cliente recebe automaticamente um e-mail (design da marca) com o botão que entra **directo** na Área de Cliente. Liga/desliga em `config.php` (`notify_client`). Também: guardar uma entrega já **não apaga** os comentários existentes. |
| **1.3.0** | **Área de Cliente:** alternador **PT/EN**, **lista de serviços** para pedir sem sair da área, e comentários por pedido. **O administrador vê e responde** aos comentários de cada pedido (indicador na lista de Leads). **Sessão persistente:** ao voltar e clicar Entrar entra directo na conta; desliga automaticamente após **15 min** de inactividade (cliente e admin). **Link do e-mail** "Aceder à Área de Cliente" agora entra **directo** (leva e-mail + código). **Endereço do painel oculto (segurança):** o admin define/gera um endereço secreto (Definições → Segurança) e o painel passa a abrir só por `painel.php?k=SEGREDO`; o `admin.html` directo deixa de funcionar. **Importante:** após actualizar, entre pelo `entrar.html` (é encaminhado automaticamente). |
| **1.2.0** | **Visual renovado (UI/UX):** tipografia mais fina e limpa (Inter) em todo o sistema, cores **mais vivas mas profissionais**, sem alterar o layout. **Sidebar do painel de administração agora fixo** (como o do cliente). **Painel do cliente** ganha **pesquisa, alternador de tema (claro/escuro) e sino de notificações**. **Redes sociais geridas no painel** (Definições → Contactos): os links do Instagram/Facebook/X/YouTube/LinkedIn/TikTok aparecem no rodapé do site. Sem logótipo, tanto o site como o e-mail e os acessos mostram **apenas o nome** (sem símbolo). |
| **1.1.0** | **Área de Cliente redesenhada** como painel: **sidebar fixo**, pedidos em **grelha**, botão **“Descarregar tudo”** por pedido, e **comentários** em cada entrega (o cliente comenta e o estúdio responde no painel). **Link do e-mail vai direto à conta** do cliente (já com o e-mail, só pede o código). Nova secção **“Nossos Sites & Sistemas”** — o administrador cola um link (Definições → Sites & Sistemas) e o site aparece ilustrado com pré-visualização ao vivo. **Visual mais suave** em todo o site (cores menos radiantes, brilhos reduzidos). **Ícones Font Awesome** na Área de Cliente. Sem logótipo carregado, o cabeçalho mostra **apenas o nome** do site. |
| **1.0.5** | **E-mail de confirmação redesenhado** — recibo em HTML com o visual da marca (logo, banner, botão “Aceder à Área de Cliente”, código de acesso e resumo), com versão de texto de reserva. **Área de Cliente mostra TODOS os pedidos** — depois de entrar (por e-mail+código ou conta social), o cliente vê a lista completa das suas requisições, cada uma com o estado e as entregas. **Checkout mais visível** — botão “Pedido” fixo e sempre acessível no site. |
| **1.0.4** | **Menu do telemóvel corrigido em definitivo** — o menu, ao fechar, fica totalmente inerte (`visibility`/`pointer-events`), deixa de se sobrepor ao site, e ganha um fundo escurecido que fecha ao tocar fora. **Login social gerido no painel** (Definições → Login social) — **Google e Facebook**: basta colar os IDs e os botões aparecem no login; o App Secret do Facebook fica só no servidor. **Login verdadeiramente único** — `admin.html` e `cliente.html` reencaminham para `entrar.html`; são as credenciais que decidem se vai para o painel ou para a Área de Cliente. |
| **1.0.3** | **Login unificado** (`entrar.html`) — um único acesso que, pelas credenciais, encaminha para o painel (admin: utilizador+palavra-passe) ou para a Área de Cliente (cliente: e-mail+código). **Botão "Entrar" no menu** do site (desktop e telemóvel). **Login social com Google** (opcional) — activa-se pondo o `google_client_id` no `config.php`. **Header do telemóvel** reforçado (logótipo e acções nunca cortados). **README reorganizado** e atualizado. |
| **1.0.2** | **Área de Cliente** (`cliente.html`) — o cliente entra com o **e-mail + código** e acompanha o estado do pedido e **transfere as entregas** (ficheiros e links). No painel, cada lead ganha a acção **“Entregar”**: carregue ficheiros ou adicione links, escreva uma mensagem e **notifique o cliente** (WhatsApp/e-mail) com o link e o código. **Recibo automático ao cliente** — quem envia um pedido recebe um e-mail de confirmação com o resumo e o acesso à Área de Cliente. **Tradução PT/EN a 100%** — todo o painel (login, formulários de edição, contactos, logótipo, pagamentos, conta, atualização e entregas) passa a mudar de idioma. |
| **1.0.1** | Correcção definitiva do transbordo lateral no telemóvel (secções passam a clipar os elementos decorativos → logótipo deixa de ser cortado, conteúdo centrado, menu no sítio certo e visível). **Pagamentos no checkout** — configuráveis no painel (M-Pesa, e-Mola, transferência e link de pagamento online); mostrados ao cliente ao finalizar o pedido. **Tradução PT/EN** alargada a tabelas, estados, listas e formulários dos separadores. |
| **1.0.0** | Versionamento semântico (1.0.0) e **cache-busting** (cada versão recarrega CSS/JS automaticamente). Correcções mobile: hero do site sem transbordo, conteúdo centrado, acções do cabeçalho no canto, **padding do checkout** corrigido. Painel: **notificações** — sino no topo (na linha do tema) com contador de novos pedidos, aviso do navegador e botão **“notificar cliente”** (WhatsApp/e-mail); **tradução PT/EN** da navegação, dashboard e separadores principais. |
| (anterior) | Painel de gestão completo (leads, serviços, portfólio, testemunhos, contactos, logótipo claro/escuro, imagens, checkout de serviços), MySQL opcional, atualização por ZIP. |
