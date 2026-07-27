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

    // Endereço público do site (sem barra no fim). Usado no recibo automático
    // para indicar ao cliente o link da Área de Cliente (cliente.html).
    'site_url'    => 'https://caritechgraphic.com',

    // Enviar recibo automático (confirmação) ao cliente quando ele faz um
    // pedido. Deixe true para confirmar a receção e enviar o código de acesso
    // à Área de Cliente.
    'send_receipt' => true,

    // ---- Painel de administração (admin.html) ------------------------------
    // Credenciais INICIAIS de acesso. Depois de entrar, pode alterar o
    // utilizador e a palavra-passe directamente no painel (Definições → Conta),
    // e passam a ser guardados na base de dados/ficheiro. Estes valores servem
    // apenas como padrão de arranque / recuperação.
    //   Utilizador padrão: "admin"
    //   Senha padrão:      "caritech2026"   (MUDE-A assim que entrar!)
    // Para gerar um hash manualmente:
    //   php -r 'echo password_hash("A_SUA_SENHA", PASSWORD_DEFAULT), PHP_EOL;'
    'admin_user'          => 'admin',
    'admin_password_hash' => '$2y$12$X.Q5w2g879KLOCGa9daRReVzTHnGCdfluiLK.MRxwfLPTy1ny2f1S',

    // ---- Login social (Google) — OPCIONAL --------------------------------
    // O botão "Continuar com Google" na página de entrada (entrar.html) só
    // aparece se preencher 'google_client_id'. Para obter um:
    //   1. https://console.cloud.google.com → APIs e Serviços → Credenciais
    //   2. Criar "ID de cliente OAuth" → tipo "Aplicação Web"
    //   3. Em "Origens JavaScript autorizadas" ponha https://seudominio.com
    //   4. Copie o "ID de cliente" para aqui.
    // Um cliente entra com a conta Google se o e-mail coincidir com um pedido.
    // Se puser o seu e-mail em 'admin_email', entra como administrador.
    'google_client_id' => '',
    'admin_email'      => '',

    // Onde ficam guardados os leads em JSON (usado pelo painel admin).
    'leads_json' => __DIR__ . '/dados/leads.json',

    // Onde fica o conteúdo gerido no painel (Serviços e Portfólio),
    // lido pelo site público através de conteudo.php.
    // (Usado apenas quando a base de dados está DESLIGADA — ver 'db' abaixo.)
    'content_json' => __DIR__ . '/dados/conteudo.json',

    // ---- Base de dados MySQL (opcional) ------------------------------------
    // Por omissão o site guarda tudo em ficheiros (dados/*.json) e funciona
    // sem qualquer configuração. Para usar MySQL da Hostinger:
    //   1. hPanel → Bases de Dados → MySQL: crie uma base de dados e um
    //      utilizador (anote nome, utilizador e palavra-passe).
    //   2. Preencha os dados abaixo e ponha 'enabled' => true.
    //   3. As tabelas são criadas automaticamente na primeira utilização.
    // Se a ligação falhar, o site volta automaticamente aos ficheiros.
    'db' => [
        'enabled' => false,
        'host'    => 'localhost',              // na Hostinger é normalmente 'localhost'
        'name'    => '',                       // ex.: u123456789_caritech
        'user'    => '',                       // ex.: u123456789_admin
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],

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
