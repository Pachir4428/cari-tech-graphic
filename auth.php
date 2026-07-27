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
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'secure' => $https, 'samesite' => 'Strict',
]);
session_name('CTG_ADMIN');
session_start();

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, ['message' => 'Método não permitido.'], 405);
}
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;
$action = $_GET['action'] ?? 'login';

/* -------------------------------------------------------------------------- */
/* Login por credenciais (admin OU cliente)                                   */
/* -------------------------------------------------------------------------- */
if ($action === 'login') {
    $id     = trim((string) ($body['identifier'] ?? ''));
    $secret = (string) ($body['secret'] ?? '');
    if ($id === '' || $secret === '') {
        responder(false, ['message' => 'Preencha os dois campos.'], 422);
    }

    /* 1) É administrador? (utilizador + palavra-passe) */
    if (admin_ok($config, $id, $secret)) {
        session_regenerate_id(true);
        $_SESSION['ctg_admin'] = true;
        responder(true, ['role' => 'admin', 'redirect' => 'admin.html']);
    }

    /* 2) É cliente? (e-mail + código do pedido) */
    $lead = cliente_por_email($config, $id);
    if ($lead && strtoupper(trim((string) ($lead['codigo'] ?? ''))) === strtoupper(trim($secret)) && trim($secret) !== '') {
        responder(true, [
            'role' => 'client', 'redirect' => 'cliente.html',
            'email' => $lead['email'] ?? '', 'codigo' => $lead['codigo'] ?? '',
        ]);
    }

    usleep(600000);
    responder(false, ['message' => 'Credenciais incorrectas. Use o seu utilizador/palavra-passe (admin) ou o e-mail + código do pedido (cliente).'], 401);
}

/* -------------------------------------------------------------------------- */
/* Login social — Google Identity Services                                    */
/* O botão só aparece se 'google_client_id' estiver definido no config.       */
/* -------------------------------------------------------------------------- */
if ($action === 'google') {
    $clientId = $config['google_client_id'] ?? '';
    if ($clientId === '') {
        responder(false, ['message' => 'Login com Google não está configurado.'], 400);
    }
    $credential = (string) ($body['credential'] ?? '');
    if ($credential === '') {
        responder(false, ['message' => 'Credencial em falta.'], 422);
    }

    /* Valida o id_token junto da Google (verifica assinatura, emissor e validade). */
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
    $resp = @file_get_contents($url);
    $info = $resp ? json_decode($resp, true) : null;
    if (!is_array($info) || empty($info['email'])) {
        responder(false, ['message' => 'Não foi possível validar a conta Google.'], 401);
    }
    /* Confere que o token foi emitido para ESTA aplicação e está verificado. */
    $aud = $info['aud'] ?? '';
    $emailVerificado = ($info['email_verified'] ?? 'false');
    if ($aud !== $clientId || ($emailVerificado !== true && $emailVerificado !== 'true')) {
        responder(false, ['message' => 'Conta Google inválida para esta aplicação.'], 401);
    }
    $email = strtolower(trim($info['email']));

    /* É o administrador? (se tiver definido admin_email no config) */
    $adminEmail = strtolower(trim((string) ($config['admin_email'] ?? '')));
    if ($adminEmail !== '' && $email === $adminEmail) {
        session_regenerate_id(true);
        $_SESSION['ctg_admin'] = true;
        responder(true, ['role' => 'admin', 'redirect' => 'admin.html']);
    }

    /* É cliente? (tem pedidos com este e-mail) */
    $lead = cliente_por_email($config, $email);
    if ($lead) {
        responder(true, [
            'role' => 'client', 'redirect' => 'cliente.html',
            'email' => $lead['email'] ?? '', 'codigo' => $lead['codigo'] ?? '',
        ]);
    }

    responder(false, ['message' => 'Ainda não há pedidos associados a este e-mail. Faça primeiro um pedido no site.'], 404);
}

responder(false, ['message' => 'Acção desconhecida.'], 400);
