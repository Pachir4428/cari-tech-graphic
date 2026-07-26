# Atualizações do site

Esta pasta guarda os pacotes de atualização, um por versão: `cari-tech-graphic-v1.zip`,
`cari-tech-graphic-v2.zip`, etc. **Use sempre o de número mais alto** (é a versão mais recente).

> Versão actual: **v2** — a mesma que aparece no canto do painel de administração.

## Como atualizar o site (2 minutos)

1. **Baixe** o ZIP com o número mais alto desta pasta (no GitHub: abra o ficheiro → **Download**).
2. Entre no painel: `https://seudominio.com/admin.html`.
3. Vá a **Definições → Atualizar o site (ZIP)**, escolha o ZIP e clique em **Atualizar site**.

Pronto. Confirme a versão no canto do painel.

### O que é preservado (nunca se perde)
- **`config.php`** — as suas definições (e-mail, WhatsApp, base de dados)
- **`dados/`** — os leads e todo o conteúdo do painel
- **`uploads/`** — as imagens e os logótipos

> Em alternativa, extraia o ZIP dentro de `public_html` pelo Gestor de Ficheiros da Hostinger.
> Dica: faça um backup (hPanel → Backups) antes de atualizar.

---

## Histórico de versões

| Versão | Alterações |
|--------|------------|
| **v2** | Responsividade: hero do site corrigido no telemóvel (cartões flutuantes deixam de transbordar). Painel: menu lateral passa a **gaveta (drawer)** no telemóvel com botão de menu; adicionados **pesquisa** (com lupa), **alternador de tema** (claro/escuro) e **alternador de idioma** (PT/EN) no topo. Novo: **checkout de serviços** no site — botão "Adicionar ao pedido", carrinho flutuante e finalização por e-mail ou WhatsApp. |
| **v1** | Base do painel + site. Dashboard (layout tipo dashboard, cores do sistema), login responsivo, sidebar organizada, gestão de leads/serviços/portfólio/testemunhos/contactos, MySQL opcional, atualização por ZIP. Novidades: **versão visível no painel**, **logótipo claro + escuro** aplicado a todo o site (incl. ícone/favicon), **upload de imagem** em serviços/portfólio/testemunhos, **link de site externo** no portfólio com pré-visualização. |
