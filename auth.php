<?php
/**
 * Cari Tech Graphic — Autenticação unificada
 * ---------------------------------------------------------------------------
 * Um único ponto de entrada (entrar.html) que decide, com base nas
 * credenciais, se o utilizador é ADMIN ou CLIENTE, e devolve para onde
 * encaminhar:
 *   - Admin   → admin.html   (inicia sessão de administrador)
 *   - Cliente → cliente.html (devolve e-mail + código para a Área de Cliente)
 *
 * Acções (?action=…):
 *   POST login   { identifier, secret }   → tenta admin, depois cliente
 *   POST google  { credential }           → login social (Google Identity)
 *
 * Não expõe dados de outros utilizadores: só confirma quando as credenciais
 * coincidem. Pequeno atraso em cada falha (anti-força-bruta).
 */

/* --- Sessão segura (mesma do painel admin) -------------------------------- */
/* Cookie persistente (7 dias) mas com inatividade máxima de 15 min imposta
   pelo servidor — o utilizador volta directo à conta se voltar a tempo. */
define('CTG_IDLE_MAX', 900); // 15 minutos
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 7 * 86400, 'path' => '/', 'httponly' => true,
    'secure' => $https, 'samesite' => 'Strict',
]);
session_name('CTG_ADMIN');
session_start();

/* Impõe a inatividade de 15 min: se passou muito tempo, termina a sessão. */
if (!empty($_SESSION['ctg_admin'])) {
    if (isset($_SESSION['ctg_last']) && (time() - $_SESSION['ctg_last']) > CTG_IDLE_MAX) {
        $_SESSION = [];
        session_destroy();
    } else {
        $_SESSION['ctg_last'] = time();
    }
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/armazenamento.php';

function responder($ok, $data = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

/* Credenciais de admin (guardadas no painel ou padrão do config). */
function ctg_admin_user_auth($config) {
    $cred = store_get_admin($config);
    return ($cred && !empty($cred['user'])) ? $cred['user'] : ($config['admin_user'] ?? 'admin');
}
function admin_ok($config, $user, $senha) {
    $cred = store_get_admin($config);
    if ($cred && !empty($cred['hash'])) {
        return $user === ($cred['user'] ?? '') && password_verify($senha, $cred['hash']);
    }
    $padUser = $config['admin_user'] ?? 'admin';
    $padHash = $config['admin_password_hash'] ?? '';
    return $user === $padUser && $padHash && password_verify($senha, $padHash);
}

/* Procura o lead mais recente com este e-mail (para o login de cliente). */
function cliente_por_email($config, $email) {
    $email = strtolower(trim($email));
    if ($email === '') return null;
    $achado = null;
    foreach (store_get_leads($config) as $l) {          // já vem ordenado por data desc
        if (strtolower(trim((string) ($l['email'] ?? ''))) === $email) { $achado = $l; break; }
    }
    return $achado;
}

$action = $_GET['action'] ?? 'login';

/* Estado da sessão — usado por entrar.html para ir directo à conta (GET). */
if ($action === 'session') {
    if (!empty($_SESSION['ctg_admin'])) {
        responder(true, ['admin' => true, 'redirect' => ctg_painel_url($config)]);
    }
    responder(true, ['admin' => false]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, ['message' => 'Método não permitido.'], 405);
}
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

/* -------------------------------------------------------------------------- */
/* Login por credenciais (admin OU cliente)                                   */
/* -------------------------------------------------------------------------- */
if ($action === 'login') {
    $id     = trim((string) ($body['identifier'] ?? ''));
    $secret = (string) ($body['secret'] ?? '');
    if ($id === '' || $secret === '') {
        responder(false, ['message' => 'Preencha os dois campos.'], 422);
    }

    /* Anti-força-bruta: bloqueia após 8 tentativas falhadas por IP em 15 min. */
    $rlChave = 'login:' . ctg_client_ip();
    $rl = store_rate_status($config, $rlChave, 8, 900);
    if ($rl['bloqueado']) {
        $min = (int) ceil($rl['restam'] / 60);
        responder(false, ['message' => "Demasiadas tentativas. Tente novamente dentro de {$min} min."], 429);
    }

    /* 1) É administrador? (utilizador + palavra-passe) */
    if (admin_ok($config, $id, $secret)) {
        store_rate_limpar($config, $rlChave);
        session_regenerate_id(true);
        $_SESSION['ctg_admin'] = true;
        $_SESSION['ctg_last'] = time();
        store_add_audit($config, 'login-admin', 'Sessão iniciada (entrar.html)');
        responder(true, ['role' => 'admin', 'redirect' => ctg_painel_url($config)]);
    }

    /* 2) É cliente? (e-mail + código do pedido) */
    $lead = cliente_por_email($config, $id);
    if ($lead && strtoupper(trim((string) ($lead['codigo'] ?? ''))) === strtoupper(trim($secret)) && trim($secret) !== '') {
        store_rate_limpar($config, $rlChave);
        responder(true, [
            'role' => 'client', 'redirect' => 'cliente.html',
            'email' => $lead['email'] ?? '', 'codigo' => $lead['codigo'] ?? '',
        ]);
    }

    store_rate_falha($config, $rlChave, 900);
    store_add_audit($config, 'login-falhou', "Identificador tentado: {$id}");
    usleep(600000);
    responder(false, ['message' => 'Credenciais incorrectas. Use o seu utilizador/palavra-passe (admin) ou o e-mail + código do pedido (cliente).'], 401);
}

/* Encaminha um e-mail verificado (de login social) para admin ou cliente. */
function encaminhar_por_email($config, $social, $email) {
    $email = strtolower(trim($email));
    if ($social['admin_email'] !== '' && $email === $social['admin_email']) {
        session_regenerate_id(true);
        $_SESSION['ctg_admin'] = true;
        $_SESSION['ctg_last'] = time();
        responder(true, ['role' => 'admin', 'redirect' => ctg_painel_url($config)]);
    }
    $lead = cliente_por_email($config, $email);
    if ($lead) {
        responder(true, [
            'role' => 'client', 'redirect' => 'cliente.html',
            'email' => $lead['email'] ?? '', 'codigo' => $lead['codigo'] ?? '',
        ]);
    }
    responder(false, ['message' => 'Ainda não há pedidos associados a este e-mail. Faça primeiro um pedido no site.'], 404);
}

/* -------------------------------------------------------------------------- */
/* Login social — Google Identity Services                                    */
/* Activa-se definindo o Client ID no painel (Definições → Login social).     */
/* -------------------------------------------------------------------------- */
if ($action === 'google') {
    $social = ctg_social($config);
    if (!$social['enabled'] || $social['google_client_id'] === '') {
        responder(false, ['message' => 'Login com Google não está configurado.'], 400);
    }
    $credential = (string) ($body['credential'] ?? '');
    if ($credential === '') {
        responder(false, ['message' => 'Credencial em falta.'], 422);
    }
    /* Valida o id_token junto da Google (assinatura, emissor e validade). */
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
    $resp = @file_get_contents($url);
    $info = $resp ? json_decode($resp, true) : null;
    if (!is_array($info) || empty($info['email'])) {
        responder(false, ['message' => 'Não foi possível validar a conta Google.'], 401);
    }
    $emailVerificado = ($info['email_verified'] ?? 'false');
    if (($info['aud'] ?? '') !== $social['google_client_id'] || ($emailVerificado !== true && $emailVerificado !== 'true')) {
        responder(false, ['message' => 'Conta Google inválida para esta aplicação.'], 401);
    }
    encaminhar_por_email($config, $social, $info['email']);
}

/* -------------------------------------------------------------------------- */
/* Login social — Facebook Login                                              */
/* Activa-se definindo App ID + App Secret no painel.                         */
/* -------------------------------------------------------------------------- */
if ($action === 'facebook') {
    $social = ctg_social($config);
    if (!$social['enabled'] || $social['facebook_app_id'] === '' || $social['facebook_app_secret'] === '') {
        responder(false, ['message' => 'Login com Facebook não está configurado.'], 400);
    }
    $token = (string) ($body['accessToken'] ?? '');
    if ($token === '') {
        responder(false, ['message' => 'Token em falta.'], 422);
    }
    /* 1) Valida o token (debug_token) — confirma que foi emitido para ESTA app. */
    $appToken = $social['facebook_app_id'] . '|' . $social['facebook_app_secret'];
    $dbgUrl = 'https://graph.facebook.com/debug_token?input_token=' . urlencode($token) . '&access_token=' . urlencode($appToken);
    $dbg = @file_get_contents($dbgUrl);
    $dbgData = $dbg ? (json_decode($dbg, true)['data'] ?? null) : null;
    if (!is_array($dbgData) || empty($dbgData['is_valid']) || ($dbgData['app_id'] ?? '') !== $social['facebook_app_id']) {
        responder(false, ['message' => 'Não foi possível validar a conta Facebook.'], 401);
    }
    /* 2) Obtém o e-mail (com appsecret_proof para segurança). */
    $proof = hash_hmac('sha256', $token, $social['facebook_app_secret']);
    $meUrl = 'https://graph.facebook.com/me?fields=email&access_token=' . urlencode($token) . '&appsecret_proof=' . $proof;
    $me = @file_get_contents($meUrl);
    $meData = $me ? json_decode($me, true) : null;
    if (!is_array($meData) || empty($meData['email'])) {
        responder(false, ['message' => 'A conta Facebook não partilhou um e-mail. Use o login por código.'], 401);
    }
    encaminhar_por_email($config, $social, $meData['email']);
}

responder(false, ['message' => 'Acção desconhecida.'], 400);
