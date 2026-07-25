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
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $https,
    'samesite' => 'Strict',
]);
session_name('CTG_ADMIN');
session_start();

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

/* --- Entrada --------------------------------------------------------------- */
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

/* --- Rotas ----------------------------------------------------------------- */
switch ($action) {
    case 'login':
        $senha = (string) ($body['password'] ?? '');
        $hash  = $config['admin_password_hash'] ?? '';
        if ($hash && password_verify($senha, $hash)) {
            session_regenerate_id(true);
            $_SESSION['ctg_admin'] = true;
            responder(true, ['message' => 'Autenticado.']);
        }
        usleep(600000); // atrasa tentativas de força bruta
        responder(false, ['message' => 'Palavra-passe incorrecta.'], 401);

    case 'logout':
        $_SESSION = [];
        session_destroy();
        responder(true, ['message' => 'Sessão terminada.']);

    case 'session':
        responder(true, ['authenticated' => autenticado()]);

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
        ]);

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

    default:
        responder(false, ['message' => 'Acção desconhecida.'], 400);
}
