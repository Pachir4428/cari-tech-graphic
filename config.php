<?php
/**
 * Cari Tech Graphic — Configuração do servidor (backend)
 * ---------------------------------------------------------------------------
 * Edite os valores abaixo depois de publicar na Hostinger.
 * Este ficheiro NÃO deve ser acessível pelo navegador — o .htaccess bloqueia
 * o acesso directo a config.php.
 */

return [
    // Para onde as mensagens dos clientes são enviadas.
    // Use um e-mail do SEU domínio criado no hPanel (ex.: contacto@seudominio.com).
    'to_email'    => 'contacto@caritechgraphic.com',

    // Remetente. Na Hostinger, o "From" TEM de ser uma caixa @seudominio.com
    // para a mensagem não cair no spam nem ser recusada.
    'from_email'  => 'no-reply@caritechgraphic.com',
    'from_name'   => 'Website Cari Tech Graphic',

    // Prefixo do assunto do e-mail que você recebe.
    'subject_prefix' => '[Site] Novo contacto',

    // Guardar uma cópia de cada mensagem num ficheiro CSV (rede de segurança:
    // mesmo que o e-mail falhe, nenhum lead se perde). Caminho relativo a este
    // ficheiro. A pasta e o CSV são protegidos pelo .htaccess.
    'log_csv'     => __DIR__ . '/dados/leads.csv',

    // Número de WhatsApp (só dígitos, formato internacional) — usado na
    // resposta para oferecer continuação da conversa.
    'whatsapp'    => '258834157731',

    // ---- Painel de administração (admin.html) ------------------------------
    // Palavra-passe de acesso ao painel, guardada como hash (NUNCA em texto).
    // Senha padrão: "caritech2026" — MUDE-A assim que publicar!
    // Para gerar um novo hash, corra no terminal (ou peça-me para gerar):
    //   php -r 'echo password_hash("A_SUA_SENHA", PASSWORD_DEFAULT), PHP_EOL;'
    // e cole o resultado aqui.
    'admin_password_hash' => '$2y$12$X.Q5w2g879KLOCGa9daRReVzTHnGCdfluiLK.MRxwfLPTy1ny2f1S',

    // Onde ficam guardados os leads em JSON (usado pelo painel admin).
    'leads_json' => __DIR__ . '/dados/leads.json',

    // Onde fica o conteúdo gerido no painel (Serviços e Portfólio),
    // lido pelo site público através de conteudo.php.
    'content_json' => __DIR__ . '/dados/conteudo.json',

    // ---- SMTP (opcional, recomendado para melhor entregabilidade) ----------
    // Deixe 'smtp_enabled' => false para usar a função mail() nativa do PHP.
    // Para activar SMTP é necessário instalar o PHPMailer (ver README).
    'smtp_enabled'  => false,
    'smtp_host'     => 'smtp.hostinger.com',
    'smtp_port'     => 465,
    'smtp_secure'   => 'ssl',           // 'ssl' (465) ou 'tls' (587)
    'smtp_user'     => 'no-reply@caritechgraphic.com',
    'smtp_pass'     => 'DEFINA_A_SUA_PALAVRA_PASSE',
];
