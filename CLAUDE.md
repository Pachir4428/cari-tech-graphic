# Cari Tech Graphic — notas para o assistente

Estúdio de design/marketing/tecnologia (Nampula, Moçambique). Site para
**alojamento partilhado (Hostinger)**. Comunicação com o dono em **português**.

## Stack
- **Frontend:** HTML + CSS + **React via CDN** (Babel no browser — **sem passo de build**).
  O `admin.html` tem o React inline em `<script type="text/babel">`.
- **Backend:** PHP (alojamento partilhado). MySQL opcional via PDO, com
  **fallback para ficheiros JSON** em `dados/` (`armazenamento.php`).

## Regra de lançamento (SEMPRE cumprir)
Cada nova funcionalidade/correcção é uma **versão** (semver `maior.menor.correcção`):

1. Faz as alterações e **incrementa `APP_VERSION`** em `admin.html`.
2. Actualiza o **cache-bust** `?v=NNN` em `index.html`, `sobre.html`,
   `admin.html`, `cliente.html`.
3. Actualiza o **`atualizacoes/LEIA-ME.md`** (linha "Versão actual" + tabela de histórico).
4. **Gera o pacote** `atualizacoes/cari-tech-graphic-<versão>.zip` (o dono
   instala-o no painel → Definições → Atualizar o site).
5. **Comita E envia (push) o ZIP junto com o código** — o ZIP de cada versão
   tem de ficar **sempre versionado no git**, na pasta `atualizacoes/`.
   Nunca deixar um ZIP de fora do commit.

### O que NÃO entra no ZIP nem no git
- `dados/*.json`, `dados/leads.csv` (dados reais de clientes)
- `uploads/entregas/`, `uploads/cliente/` (ficheiros carregados em execução)
- `dados/ratelimit.json` e restantes metadados (`smtp.json`, `social.json`, etc.)
- `config.php` vai no ZIP como modelo, mas o updater **preserva** o do servidor.

### Como construir o ZIP
Copiar os ficheiros **tracked** do git (respeita `.gitignore`) + `demo.php`,
excluindo `atualizacoes/` e `docs/screenshots/`, e mantendo em `uploads/`
apenas as imagens do template (`owner-portrait.png`, `owner-casual.png`).
O `atualizar_por_zip` (em `armazenamento.php`) preserva `config.php`,
`dados/` e `uploads/` na extracção.

## Validação antes de comitar
- JSX: `SCRATCH=<scratchpad> node <scratchpad>/jsxcheck.js`
- PHP: `php -l` em cada ficheiro alterado.

## Branch de desenvolvimento
Trabalhar em `claude/esse-website-shared-hosting-5glwfe` e enviar para lá.
Não enviar para `main` sem autorização explícita do dono.
