# Atualizações do site

Esta pasta guarda os pacotes de atualização do site, em `.zip`.
A partir de agora, cada nova versão é entregue aqui como um ZIP pronto a aplicar.

## Como atualizar o site (2 minutos)

1. **Baixe** o ficheiro **`site-atual.zip`** desta pasta
   (no GitHub: abra o ficheiro → botão **Download**).
2. Entre no painel: `https://seudominio.com/admin.html`.
3. Vá a **Definições → Atualizar o site (ZIP)**, escolha o ZIP e clique em **Atualizar site**.

Pronto — o site fica na versão nova.

### O que é preservado (nunca se perde)
Ao aplicar a atualização, estes são **sempre mantidos**:
- **`config.php`** — as suas definições (e-mail, WhatsApp, base de dados)
- **`dados/`** — os leads e todo o conteúdo do painel
- **`uploads/`** — as imagens e o logótipo

> Em alternativa, pode extrair o ZIP dentro de `public_html` pelo Gestor de Ficheiros da Hostinger.
> Dica: faça um backup (hPanel → Backups) antes de atualizar, por precaução.

---

## Histórico de versões

| Data | Versão | Alterações |
|------|--------|------------|
| 2026-07-26 | v1.1 | Login: novo layout responsivo (cores do sistema), correção de acesso, remoção da dica de credenciais. Menu do painel organizado em grupos e responsivo. |
| 2026-07-26 | v1.0 | Primeira versão: site + painel (leads, serviços, portfólio, testemunhos, contactos, logótipo), MySQL opcional, atualização por ZIP. |
