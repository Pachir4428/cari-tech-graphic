# Atualizações do site

Cada versão tem um pacote `.zip` aqui, no formato **1.0.0** (maior.menor.correcção):
`cari-tech-graphic-1.0.0.zip`, `cari-tech-graphic-1.0.1.zip`, etc.
**Use sempre o de número mais alto.**

> Versão actual: **1.0.0** — a mesma que aparece no canto do painel de administração.

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
| **1.0.0** | Versionamento semântico (1.0.0) e **cache-busting** (cada versão recarrega CSS/JS automaticamente). Correcções mobile: hero do site sem transbordo, conteúdo centrado, acções do cabeçalho no canto, **padding do checkout** corrigido. Painel: **notificações** — sino no topo (na linha do tema) com contador de novos pedidos, aviso do navegador e botão **“notificar cliente”** (WhatsApp/e-mail); **tradução PT/EN** da navegação, dashboard e separadores principais. |
| (anterior) | Painel de gestão completo (leads, serviços, portfólio, testemunhos, contactos, logótipo claro/escuro, imagens, checkout de serviços), MySQL opcional, atualização por ZIP. |
