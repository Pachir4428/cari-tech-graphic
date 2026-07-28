<?php
/**
 * Cari Tech Graphic — API do painel de administração
 * ---------------------------------------------------------------------------
 * Autenticação por sessão + gestão dos leads reais (dados/leads.json).
 * Todas as respostas são JSON. Acções via ?action=... :
 *   POST login         { password }          -> inicia sessão
 *   POST logout                              -> termina sessão
 *   GET  session                             -> { authenticated: bool }
 *   GET  leads                               -> lista de leads   (protegido)
 *   POST lead-status   { id, status }        -> muda o estado    (protegido)
 *   POST lead-delete   { id }                -> apaga um lead    (protegido)
 *   GET  stats                               -> contadores       (protegido)
 */

/* --- Sessão segura --------------------------------------------------------- */
/* Cookie persistente (7 dias) com inatividade máxima de 15 min. */
define('CTG_IDLE_MAX', 900);
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 7 * 86400,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $https,
    'samesite' => 'Strict',
]);
session_name('CTG_ADMIN');
session_start();

/* Termina a sessão após 15 min de inatividade; senão, renova a marca. */
if (!empty($_SESSION['ctg_admin'])) {
    if (isset($_SESSION['ctg_last']) && (time() - $_SESSION['ctg_last']) > CTG_IDLE_MAX) {
        $_SESSION = [];
        session_destroy();
    } else {
        $_SESSION['ctg_last'] = time();
    }
}

/* Token CSRF por sessão (defesa em profundidade, para além do SameSite=Strict).
   Gerado assim que a sessão de admin existe; enviado ao cliente via ?action=session. */
if (!empty($_SESSION['ctg_admin']) && empty($_SESSION['ctg_csrf'])) {
    $_SESSION['ctg_csrf'] = bin2hex(random_bytes(32));
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$config = require __DIR__ . '/config.php';

function responder($ok, $data = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}
function autenticado() {
    return !empty($_SESSION['ctg_admin']);
}
function exigir_login() {
    if (!autenticado()) responder(false, ['message' => 'Sessão não autorizada.'], 401);
}

require_once __DIR__ . '/armazenamento.php';
require_once __DIR__ . '/email-enviar.php';
require_once __DIR__ . '/email-template.php';
$config = ctg_apply_smtp($config); // definições SMTP do painel (se existirem)

/* Utilizador em vigor: o guardado no painel, ou o padrão do config. */
function ctg_admin_user($config) {
    $cred = store_get_admin($config);
    return ($cred && !empty($cred['user'])) ? $cred['user'] : ($config['admin_user'] ?? 'admin');
}

/* Devolve o lead pelo id (ou null). */
function ctg_lead_por_id($config, $leadId) {
    foreach (store_get_leads($config) as $l) {
        if (($l['id'] ?? '') === $leadId) return $l;
    }
    return null;
}

/* Notifica o cliente por e-mail de uma nova entrega ou resposta do estúdio.
   Silencioso: nunca interrompe a acção principal se o e-mail falhar. */
function ctg_notificar_cliente($config, $lead, $tipo, $texto) {
    if (($config['notify_client'] ?? true) !== true) return false;
    $email = trim((string) ($lead['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $marca   = $config['from_name'] ?? 'Cari Tech Graphic';
    $servico = (string) ($lead['servico'] ?? '');
    $codigo  = (string) ($lead['codigo'] ?? '');
    $nome    = (string) ($lead['nome'] ?? '');
    $site    = rtrim((string) ($config['site_url'] ?? ''), '/');

    if ($tipo === 'pago') {
        $assunto = 'Pagamento confirmado — ficheiros disponíveis — Cari Tech Graphic';
        $corpo  = "Olá {$nome},\n\nO pagamento do seu pedido" . ($servico !== '' ? " de {$servico}" : '') . " foi confirmado. Os seus ficheiros já estão disponíveis para descarregar (sem marca de água) na Área de Cliente.\n";
    } elseif ($tipo === 'entrega') {
        $assunto = 'Nova entrega disponível — Cari Tech Graphic';
        $corpo  = "Olá {$nome},\n\nO seu pedido" . ($servico !== '' ? " de {$servico}" : '') . " tem uma nova entrega disponível.\n";
    } else {
        $assunto = 'Nova resposta ao seu pedido — Cari Tech Graphic';
        $corpo  = "Olá {$nome},\n\nA {$marca} respondeu ao seu pedido" . ($servico !== '' ? " de {$servico}" : '') . ":\n\n{$texto}\n";
    }
    $corpo .= "\nAceda à sua Área de Cliente: {$site}/cliente.html?email=" . rawurlencode($email) . "&codigo=" . rawurlencode($codigo) . "\n";
    $corpo .= "(E-mail: {$email} · Código: {$codigo})\n";

    $branding = store_get_branding($config);
    $html = email_notificacao_html($config, [
        'tipo' => $tipo, 'nome' => $nome, 'servico' => $servico, 'texto' => $texto,
        'codigo' => $codigo, 'email' => $email, 'logo' => $branding['light'] ?? ($branding['dark'] ?? ''),
    ]);
    return enviar_email($config, $email, $assunto, $corpo, $config['to_email'] ?? '', $marca, $html);
}

/* Verifica utilizador + palavra-passe contra as credenciais guardadas
   (ou, se ainda não houver, contra o padrão do config.php). */
function credenciais_validas($config, $user, $senha) {
    $cred = store_get_admin($config);
    if ($cred && !empty($cred['hash'])) {
        return $user === ($cred['user'] ?? '') && password_verify($senha, $cred['hash']);
    }
    $padUser = $config['admin_user'] ?? 'admin';
    $padHash = $config['admin_password_hash'] ?? '';
    return $user === $padUser && $padHash && password_verify($senha, $padHash);
}

/* Valida e guarda uma imagem enviada; devolve ['ok'=>true,'path'=>'uploads/…'] */
function guardar_imagem($file, $prefixo, $maxMB = 3) {
    if (empty($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Nenhum ficheiro recebido (ou demasiado grande para o servidor).'];
    }
    if ($file['size'] > $maxMB * 1024 * 1024) {
        return ['ok' => false, 'message' => "A imagem não pode exceder {$maxMB} MB."];
    }
    $info = @getimagesize($file['tmp_name']);
    $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp', IMAGETYPE_BMP => 'bmp'];
    $svg = (strtolower($file['type'] ?? '') === 'image/svg+xml') || preg_match('/\.svg$/i', $file['name'] ?? '');
    if (!$info && !$svg) return ['ok' => false, 'message' => 'Ficheiro não é uma imagem válida (PNG, JPG, GIF, WEBP ou SVG).'];
    $ext = $svg ? 'svg' : ($tipos[$info[2]] ?? null);
    if (!$ext) return ['ok' => false, 'message' => 'Formato de imagem não suportado.'];
    @mkdir(__DIR__ . '/uploads', 0755, true);
    $nome = $prefixo . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/uploads/' . $nome)) {
        return ['ok' => false, 'message' => 'Não foi possível guardar o ficheiro.'];
    }
    return ['ok' => true, 'path' => 'uploads/' . $nome];
}

/* Guarda um ficheiro de entrega (qualquer tipo permitido); devolve
   ['ok'=>true,'path'=>'uploads/entregas/…','nome'=>'original.ext']. */
function guardar_ficheiro_entrega($file, $maxMB = 25) {
    if (empty($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message' => 'Nenhum ficheiro recebido (ou demasiado grande para o servidor).'];
    }
    if ($file['size'] > $maxMB * 1024 * 1024) {
        return ['ok' => false, 'message' => "O ficheiro não pode exceder {$maxMB} MB."];
    }
    $nomeOrig = (string) ($file['name'] ?? 'ficheiro');
    $ext = strtolower(pathinfo($nomeOrig, PATHINFO_EXTENSION));
    // Extensões permitidas para entrega de trabalhos (design, docs, media, código).
    $permitidas = [
        'pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','rtf',
        'png','jpg','jpeg','gif','webp','svg','bmp','tiff','ico',
        'ai','psd','eps','indd','cdr','sketch','fig','xd',
        'zip','rar','7z',
        'mp4','mov','webm','mp3','wav','ogg',
        'html','css','js','json',
    ];
    // Extensões perigosas — nunca permitidas em hospedagem partilhada.
    $proibidas = ['php','phtml','php3','php4','php5','php7','phps','pht','htaccess','cgi','pl','py','sh','exe','asp','aspx','jsp'];
    if ($ext === '' || in_array($ext, $proibidas, true) || !in_array($ext, $permitidas, true)) {
        return ['ok' => false, 'message' => 'Tipo de ficheiro não permitido.'];
    }
    if (!ctg_ficheiro_seguro($file['tmp_name'], $ext)) {
        return ['ok' => false, 'message' => 'Ficheiro rejeitado por segurança: o conteúdo não corresponde ao tipo declarado.'];
    }
    $dir = __DIR__ . '/uploads/entregas';
    @mkdir($dir, 0755, true);
    // Nome no disco: aleatório (evita colisões e path traversal); guarda o nome original nos metadados.
    $slug = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($nomeOrig, PATHINFO_FILENAME));
    $slug = trim(substr($slug, 0, 40), '-') ?: 'ficheiro';
    $nomeDisco = $slug . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nomeDisco)) {
        return ['ok' => false, 'message' => 'Não foi possível guardar o ficheiro.'];
    }
    return ['ok' => true, 'path' => 'uploads/entregas/' . $nomeDisco, 'nome' => $nomeOrig];
}

/* --- Entrada --------------------------------------------------------------- */
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

/* --- Protecção CSRF -------------------------------------------------------- */
/* Qualquer pedido POST autenticado (excepto o próprio login, que ainda não tem
   sessão) tem de trazer o token CSRF da sessão, no cabeçalho X-CSRF-Token ou no
   campo _csrf. Bloqueia pedidos forjados a partir de outros sites. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && autenticado() && $action !== 'login') {
    $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (is_array($body) ? ($body['_csrf'] ?? '') : '');
    if (empty($_SESSION['ctg_csrf']) || !is_string($tok) || !hash_equals($_SESSION['ctg_csrf'], $tok)) {
        responder(false, ['message' => 'Sessão expirada ou pedido inválido. Recarregue a página e tente novamente.'], 403);
    }
}

/* --- Rotas ----------------------------------------------------------------- */
switch ($action) {
    case 'login':
        $user  = trim((string) ($body['username'] ?? ''));
        $senha = (string) ($body['password'] ?? '');
        if (credenciais_validas($config, $user, $senha)) {
            session_regenerate_id(true);
            $_SESSION['ctg_admin'] = true;
            $_SESSION['ctg_last'] = time();
            responder(true, ['message' => 'Autenticado.']);
        }
        usleep(600000); // atrasa tentativas de força bruta
        responder(false, ['message' => 'Utilizador ou palavra-passe incorrectos.'], 401);

    case 'change-credentials':
        exigir_login();
        $atual    = (string) ($body['current_password'] ?? '');
        $novoUser = trim((string) ($body['username'] ?? ''));
        $novaPass = (string) ($body['new_password'] ?? '');
        // Confirma a palavra-passe actual (do utilizador em vigor).
        $userVigente = ctg_admin_user($config);
        if (!credenciais_validas($config, $userVigente, $atual)) {
            responder(false, ['message' => 'A palavra-passe actual está incorrecta.'], 401);
        }
        if ($novoUser === '')            responder(false, ['message' => 'Indique um nome de utilizador.'], 422);
        if (strlen($novaPass) < 6)       responder(false, ['message' => 'A nova palavra-passe deve ter pelo menos 6 caracteres.'], 422);
        store_set_admin($config, $novoUser, password_hash($novaPass, PASSWORD_DEFAULT));
        responder(true, ['message' => 'Credenciais actualizadas.']);

    case 'logout':
        $_SESSION = [];
        session_destroy();
        responder(true, ['message' => 'Sessão terminada.']);

    case 'session':
        responder(true, ['authenticated' => autenticado(), 'csrf' => (autenticado() ? ($_SESSION['ctg_csrf'] ?? '') : '')]);

    case 'leads':
        exigir_login();
        responder(true, ['leads' => store_get_leads($config)]);

    case 'lead-status':
        exigir_login();
        $id = (string) ($body['id'] ?? '');
        $status = (string) ($body['status'] ?? '');
        $validos = ['new', 'contacted', 'won', 'lost'];
        if (!in_array($status, $validos, true)) {
            responder(false, ['message' => 'Estado inválido.'], 422);
        }
        if (!store_set_lead_status($config, $id, $status)) {
            responder(false, ['message' => 'Lead não encontrado.'], 404);
        }
        responder(true, ['message' => 'Estado actualizado.']);

    case 'lead-delete':
        exigir_login();
        $id = (string) ($body['id'] ?? '');
        if (!store_delete_lead($config, $id)) {
            responder(false, ['message' => 'Lead não encontrado.'], 404);
        }
        responder(true, ['message' => 'Lead removido.']);

    case 'content':
        exigir_login();
        $c = store_get_content($config);
        responder(true, [
            'services'     => $c['services']     ?? null,
            'portfolio'    => $c['portfolio']    ?? null,
            'testimonials' => $c['testimonials'] ?? null,
            'headings'     => $c['headings']     ?? null,
            'contact'      => $c['contact']      ?? null,
            'branding'     => store_get_branding($config),
            'payments'     => store_get_payments($config),
            'social'       => ctg_social($config),
            'sites'        => store_get_sites($config),
            'seg'          => ['admin_slug' => ctg_admin_slug($config)],
            'smtp'         => (function () use ($config) {
                $s = store_get_smtp($config);
                return [
                    'smtp_enabled' => array_key_exists('smtp_enabled', $s) ? !empty($s['smtp_enabled']) : !empty($config['smtp_enabled']),
                    'smtp_host'    => $s['smtp_host']   ?? ($config['smtp_host']   ?? 'smtp.hostinger.com'),
                    'smtp_port'    => $s['smtp_port']   ?? ($config['smtp_port']   ?? 465),
                    'smtp_secure'  => $s['smtp_secure'] ?? ($config['smtp_secure'] ?? 'ssl'),
                    'smtp_user'    => $s['smtp_user']   ?? ($config['smtp_user']   ?? ($config['from_email'] ?? '')),
                    'smtp_pass'    => $s['smtp_pass']   ?? '',
                    'from_email'   => $s['from_email']  ?? ($config['from_email']  ?? ''),
                ];
            })(),
            'username'     => ctg_admin_user($config),
        ]);

    case 'payments-save':
        exigir_login();
        $p = $body['payments'] ?? null;
        if (!is_array($p)) responder(false, ['message' => 'Dados inválidos.'], 422);
        store_set_payments($config, $p);
        responder(true, ['message' => 'Pagamentos guardados.']);

    case 'smtp-save':
        exigir_login();
        $s = $body['smtp'] ?? null;
        if (!is_array($s)) responder(false, ['message' => 'Dados inválidos.'], 422);
        store_set_smtp($config, [
            'smtp_enabled' => !empty($s['smtp_enabled']),
            'smtp_host'    => trim((string) ($s['smtp_host']   ?? '')),
            'smtp_port'    => (int) ($s['smtp_port'] ?? 465),
            'smtp_secure'  => (($s['smtp_secure'] ?? 'ssl') === 'tls') ? 'tls' : 'ssl',
            'smtp_user'    => trim((string) ($s['smtp_user']   ?? '')),
            'smtp_pass'    => (string) ($s['smtp_pass'] ?? ''),
            'from_email'   => trim((string) ($s['from_email']  ?? ($s['smtp_user'] ?? ''))),
        ]);
        responder(true, ['message' => 'E-mail (SMTP) guardado.']);

    case 'financas':
        exigir_login();
        $lista = store_get_financas($config);
        $entradas = 0; $saidas = 0;
        foreach ($lista as $m) {
            $v = (float) ($m['valor'] ?? 0);
            if (($m['tipo'] ?? 'entrada') === 'saida') $saidas += $v; else $entradas += $v;
        }
        responder(true, ['financas' => $lista, 'totais' => [
            'entradas' => round($entradas, 2), 'saidas' => round($saidas, 2), 'saldo' => round($entradas - $saidas, 2),
        ]]);

    case 'financas-save':
        exigir_login();
        $itens = $body['financas'] ?? null;
        if (!is_array($itens)) responder(false, ['message' => 'Dados inválidos.'], 422);
        $limpos = [];
        foreach ($itens as $m) {
            $valor = (float) str_replace([' ', ','], ['', '.'], (string) ($m['valor'] ?? 0));
            $desc = trim((string) ($m['desc'] ?? ''));
            if ($desc === '' && $valor == 0) continue;
            $limpos[] = [
                'id'        => (string) ($m['id'] ?? uniqid()),
                'data'      => trim((string) ($m['data'] ?? date('Y-m-d'))),
                'desc'      => $desc,
                'tipo'      => (($m['tipo'] ?? 'entrada') === 'saida') ? 'saida' : 'entrada',
                'valor'     => round($valor, 2),
                'categoria' => trim((string) ($m['categoria'] ?? '')),
            ];
        }
        store_set_financas($config, $limpos);
        responder(true, ['message' => 'Finanças guardadas.', 'financas' => $limpos]);

    case 'seg-save':
        exigir_login();
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($body['admin_slug'] ?? ''));
        $slug = substr($slug, 0, 40);
        store_set_seg($config, ['admin_slug' => $slug]);
        responder(true, ['message' => 'Segurança guardada.', 'admin_slug' => $slug, 'painel' => ctg_painel_url($config)]);

    case 'sites-save':
        exigir_login();
        $s = $body['sites'] ?? null;
        if (!is_array($s)) responder(false, ['message' => 'Dados inválidos.'], 422);
        $limpos = [];
        foreach ($s as $it) {
            $url = trim((string) ($it['url'] ?? ''));
            $nome = trim((string) ($it['nome'] ?? ''));
            if ($url === '' && $nome === '') continue;
            if ($url !== '' && !preg_match('~^https?://~i', $url)) $url = 'https://' . $url;
            $limpos[] = [
                'id'     => (string) ($it['id'] ?? uniqid()),
                'nome'   => $nome,
                'url'    => $url,
                'desc'   => trim((string) ($it['desc'] ?? '')),
                'status' => (($it['status'] ?? 'active') === 'draft') ? 'draft' : 'active',
            ];
        }
        store_set_sites($config, $limpos);
        responder(true, ['message' => 'Sites guardados.', 'sites' => $limpos]);

    case 'social-save':
        exigir_login();
        $s = $body['social'] ?? null;
        if (!is_array($s)) responder(false, ['message' => 'Dados inválidos.'], 422);
        store_set_social($config, [
            'enabled'             => !empty($s['enabled']),
            'google_client_id'    => trim((string) ($s['google_client_id']    ?? '')),
            'facebook_app_id'     => trim((string) ($s['facebook_app_id']      ?? '')),
            'facebook_app_secret' => trim((string) ($s['facebook_app_secret']  ?? '')),
            'admin_email'         => trim((string) ($s['admin_email']          ?? '')),
        ]);
        responder(true, ['message' => 'Login social guardado.', 'social' => ctg_social($config)]);

    case 'content-save':
        exigir_login();
        $services     = $body['services']     ?? null;
        $portfolio    = $body['portfolio']    ?? null;
        $testimonials = $body['testimonials'] ?? [];
        $headings     = $body['headings']     ?? [];
        $contact      = $body['contact']      ?? [];
        if (!is_array($services) || !is_array($portfolio)) {
            responder(false, ['message' => 'Dados inválidos.'], 422);
        }
        $ok = store_save_content($config, [
            'services'     => $services,
            'portfolio'    => $portfolio,
            'testimonials' => is_array($testimonials) ? $testimonials : [],
            'headings'     => is_array($headings) ? $headings : [],
            'contact'      => is_array($contact) ? $contact : [],
        ]);
        if (!$ok) responder(false, ['message' => 'Falha ao gravar.'], 500);
        responder(true, ['message' => 'Conteúdo guardado.']);

    case 'stats':
        exigir_login();
        $lista = store_get_leads($config);
        $conta = fn($s) => count(array_filter($lista, fn($l) => ($l['status'] ?? 'new') === $s));
        responder(true, ['stats' => [
            'total'     => count($lista),
            'new'       => $conta('new'),
            'contacted' => $conta('contacted'),
            'won'       => $conta('won'),
            'lost'      => $conta('lost'),
        ]]);

    case 'upload-logo':
        exigir_login();
        // variante: 'light' (fundo claro) ou 'dark' (fundo escuro)
        $variante = ($_POST['variant'] ?? 'light') === 'dark' ? 'dark' : 'light';
        $r = guardar_imagem($_FILES['logo'] ?? null, 'logo-' . $variante, 2);
        if (!$r['ok']) responder(false, ['message' => $r['message']], 422);
        // Remove o logótipo anterior desta variante.
        $branding = store_get_branding($config);
        if (!empty($branding[$variante])) @unlink(__DIR__ . '/' . $branding[$variante]);
        $branding[$variante] = $r['path'];
        store_set_branding($config, $branding);
        responder(true, ['message' => 'Logótipo actualizado.', 'branding' => $branding]);

    case 'remove-logo':
        exigir_login();
        $variante = ($_POST['variant'] ?? 'light') === 'dark' ? 'dark' : 'light';
        $branding = store_get_branding($config);
        if (!empty($branding[$variante])) @unlink(__DIR__ . '/' . $branding[$variante]);
        unset($branding[$variante]);
        store_set_branding($config, $branding);
        responder(true, ['message' => 'Logótipo removido.', 'branding' => $branding]);

    case 'upload-image':
        exigir_login();
        $r = guardar_imagem($_FILES['image'] ?? null, 'img', 3);
        if (!$r['ok']) responder(false, ['message' => $r['message']], 422);
        responder(true, ['message' => 'Imagem carregada.', 'url' => $r['path']]);

    case 'deliveries':
        exigir_login();
        responder(true, ['deliveries' => store_get_deliveries($config)]);

    case 'lead-comment':
        exigir_login();
        $leadId = (string) ($body['leadId'] ?? '');
        $texto  = trim((string) ($body['texto'] ?? ''));
        if ($leadId === '' || $texto === '') responder(false, ['message' => 'Escreva um comentário.'], 422);
        if (mb_strlen($texto) > 1000) $texto = mb_substr($texto, 0, 1000);
        $comentarios = store_add_comment($config, $leadId, [
            'de' => 'studio', 'nome' => 'Cari Tech', 'texto' => $texto, 'data' => date('c'),
        ]);
        // Notifica o cliente por e-mail da nova resposta.
        $leadC = ctg_lead_por_id($config, $leadId);
        $notificado = $leadC ? ctg_notificar_cliente($config, $leadC, 'resposta', $texto) : false;
        responder(true, ['message' => 'Comentário enviado.', 'comentarios' => $comentarios, 'notified' => $notificado]);

    case 'lead-pago':
        exigir_login();
        $leadId = (string) ($body['leadId'] ?? '');
        $estado = (string) ($body['pago'] ?? 'nao');
        if ($leadId === '') responder(false, ['message' => 'Lead em falta.'], 422);
        $anterior = store_get_delivery($config, $leadId);
        $estadoAnt = $anterior['pago'] ?? 'nao';
        $novo = store_set_pagamento($config, $leadId, $estado);
        // Ao passar para "pago" (e só na transição), avisa o cliente que já pode descarregar.
        $notificado = false;
        if ($novo === 'pago' && $estadoAnt !== 'pago') {
            $leadP = ctg_lead_por_id($config, $leadId);
            if ($leadP) $notificado = ctg_notificar_cliente($config, $leadP, 'pago', '');
        }
        responder(true, ['message' => 'Pagamento actualizado.', 'pago' => $novo, 'notified' => $notificado]);

    case 'upload-file':
        exigir_login();
        $r = guardar_ficheiro_entrega($_FILES['file'] ?? null, 25);
        if (!$r['ok']) responder(false, ['message' => $r['message']], 422);
        responder(true, ['message' => 'Ficheiro carregado.', 'url' => $r['path'], 'nome' => $r['nome']]);

    case 'lead-deliver':
        exigir_login();
        $leadId = (string) ($body['leadId'] ?? '');
        if ($leadId === '') responder(false, ['message' => 'Lead em falta.'], 422);
        // Confirma que o lead existe.
        $lead = null;
        foreach (store_get_leads($config) as $l) { if (($l['id'] ?? '') === $leadId) { $lead = $l; break; } }
        if (!$lead) responder(false, ['message' => 'Lead não encontrado.'], 404);

        $itens = $body['entregas'] ?? [];
        $limpos = [];
        if (is_array($itens)) {
            foreach ($itens as $it) {
                $tipo = ($it['tipo'] ?? 'link') === 'file' ? 'file' : 'link';
                $url  = trim((string) ($it['url'] ?? ''));
                if ($url === '') continue;
                $limpos[] = [
                    'tipo' => $tipo,
                    'url'  => $url,
                    'nome' => trim((string) ($it['nome'] ?? '')) ?: $url,
                    'note' => trim((string) ($it['note'] ?? '')),
                ];
            }
        }
        $anterior = store_get_delivery($config, $leadId);
        $pagoIn = (string) ($body['pago'] ?? ($anterior['pago'] ?? 'nao'));
        $entrega = [
            'leadId'      => $leadId,
            'msg'         => trim((string) ($body['msg'] ?? '')),
            'entregas'    => $limpos,
            'entregue'    => !empty($limpos),
            'data'        => date('c'),
            'pago'        => in_array($pagoIn, ['nao', 'parcial', 'pago'], true) ? $pagoIn : 'nao',
            'comentarios' => ($anterior && !empty($anterior['comentarios'])) ? $anterior['comentarios'] : [],
            'cliente_ficheiros' => ($anterior && !empty($anterior['cliente_ficheiros'])) ? $anterior['cliente_ficheiros'] : [],
        ];
        if (!store_set_delivery($config, $leadId, $entrega)) {
            responder(false, ['message' => 'Falha ao guardar a entrega.'], 500);
        }
        // Marca o lead como ganho e notifica o cliente da nova entrega.
        $notificado = false;
        if (!empty($limpos)) {
            store_set_lead_status($config, $leadId, 'won');
            $leadD = ctg_lead_por_id($config, $leadId);
            if ($leadD) $notificado = ctg_notificar_cliente($config, $leadD, 'entrega', $entrega['msg']);
        }
        responder(true, ['message' => 'Entrega guardada.', 'delivery' => $entrega, 'notified' => $notificado]);

    case 'update-zip':
        exigir_login();
        if (!class_exists('ZipArchive')) {
            responder(false, ['message' => 'O servidor não suporta ZIP (ZipArchive indisponível).'], 500);
        }
        if (empty($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
            responder(false, ['message' => 'Nenhum ZIP recebido (ou demasiado grande para o servidor).'], 422);
        }
        if (!preg_match('/\.zip$/i', $_FILES['zip']['name'])) {
            responder(false, ['message' => 'O ficheiro tem de ser um .zip.'], 422);
        }
        $resultado = atualizar_por_zip(__DIR__, $_FILES['zip']['tmp_name']);
        if (!$resultado['ok']) responder(false, ['message' => $resultado['message']], 500);
        store_add_update_log($config, [
            'data'    => date('c'),
            'count'   => (int) $resultado['count'],
            'ficheiro'=> (string) ($_FILES['zip']['name'] ?? ''),
            'backup'  => (string) ($resultado['backup'] ?? ''),
        ]);
        responder(true, ['message' => "Site actualizado. {$resultado['count']} ficheiro(s) actualizado(s).", 'count' => $resultado['count']]);

    case 'update-log':
        exigir_login();
        responder(true, ['log' => store_get_update_log($config), 'backups' => atualizacao_backups(__DIR__)]);

    case 'update-restore':
        exigir_login();
        $bkp = (string) ($body['backup'] ?? '');
        $r = atualizacao_reverter(__DIR__, $bkp);
        if (!$r['ok']) responder(false, ['message' => $r['message']], 400);
        store_add_update_log($config, [
            'data'    => date('c'),
            'count'   => (int) $r['count'],
            'ficheiro'=> 'Reposição do backup ' . $r['backup'],
            'backup'  => '',
        ]);
        responder(true, ['message' => "Reposição concluída. {$r['count']} ficheiro(s) repostos.", 'count' => $r['count']]);

    default:
        responder(false, ['message' => 'Acção desconhecida.'], 400);
}
