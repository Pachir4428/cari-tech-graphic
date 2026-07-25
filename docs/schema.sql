-- ============================================================================
-- Cari Tech Graphic — Esquema da base de dados (MySQL / MariaDB)
-- ----------------------------------------------------------------------------
-- NÃO precisa de correr isto manualmente: quando activa o MySQL em config.php,
-- a aplicação cria estas tabelas automaticamente na primeira utilização.
-- Este ficheiro serve apenas de referência (ou para criar as tabelas à mão,
-- via phpMyAdmin no hPanel da Hostinger, se preferir).
-- ============================================================================

-- Pedidos recebidos pelo formulário do site
CREATE TABLE IF NOT EXISTS leads (
    id        VARCHAR(32) PRIMARY KEY,
    criado_em DATETIME NOT NULL,
    nome      VARCHAR(200),
    email     VARCHAR(200),
    telefone  VARCHAR(80),
    servico   VARCHAR(200),
    mensagem  TEXT,
    ip        VARCHAR(64),
    status    VARCHAR(20) NOT NULL DEFAULT 'new'   -- new | contacted | won | lost
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Conteúdo gerido no painel (serviços, projectos e testemunhos).
-- Cada linha guarda um item em JSON no campo 'dados'.
CREATE TABLE IF NOT EXISTS content_items (
    id    VARCHAR(40) PRIMARY KEY,
    tipo  VARCHAR(20) NOT NULL,                    -- service | portfolio | testimonial
    pos   INT NOT NULL DEFAULT 0,                  -- ordem de apresentação
    dados LONGTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Definições singulares em JSON: cabeçalhos das secções e contactos do site.
CREATE TABLE IF NOT EXISTS settings (
    chave VARCHAR(50) PRIMARY KEY,                 -- headings | contact | content_saved
    valor LONGTEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
