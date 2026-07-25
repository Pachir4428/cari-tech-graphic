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

/* --- Ler/escrever leads ---------------------------------------------------- */
function caminho_leads($config) {
    return $config['leads_json'] ?? (__DIR__ . '/dados/leads.json');
}
function ler_leads($config) {
    $path = caminho_leads($config);
    if (!file_exists($path)) return [];
    $lista = json_decode(@file_get_contents($path) ?: '[]', true);
    return is_array($lista) ? $lista : [];
}
function gravar_leads($config, $lista) {
    $path = caminho_leads($config);
    @mkdir(dirname($path), 0755, true);
    $fh = fopen($path, 'c+');
    if (!$fh) return false;
    $ok = false;
    if (flock($fh, LOCK_EX)) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode(array_values($lista), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fh);
        flock($fh, LOCK_UN);
        $ok = true;
    }
    fclose($fh);
    return $ok;
}

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
        $lista = ler_leads($config);
        usort($lista, fn($a, $b) => strcmp($b['data'] ?? '', $a['data'] ?? ''));
        responder(true, ['leads' => $lista]);

    case 'lead-status':
        exigir_login();
        $id = (string) ($body['id'] ?? '');
        $status = (string) ($body['status'] ?? '');
        $validos = ['new', 'contacted', 'won', 'lost'];
        if (!in_array($status, $validos, true)) {
            responder(false, ['message' => 'Estado inválido.'], 422);
        }
        $lista = ler_leads($config);
        $encontrado = false;
        foreach ($lista as &$l) {
            if (($l['id'] ?? '') === $id) { $l['status'] = $status; $encontrado = true; break; }
        }
        unset($l);
        if (!$encontrado) responder(false, ['message' => 'Lead não encontrado.'], 404);
        gravar_leads($config, $lista);
        responder(true, ['message' => 'Estado actualizado.']);

    case 'lead-delete':
        exigir_login();
        $id = (string) ($body['id'] ?? '');
        $lista = ler_leads($config);
        $antes = count($lista);
        $lista = array_filter($lista, fn($l) => ($l['id'] ?? '') !== $id);
        if (count($lista) === $antes) responder(false, ['message' => 'Lead não encontrado.'], 404);
        gravar_leads($config, $lista);
        responder(true, ['message' => 'Lead removido.']);

    case 'content':
        exigir_login();
        $path = $config['content_json'] ?? (__DIR__ . '/dados/conteudo.json');
        $c = file_exists($path) ? json_decode(@file_get_contents($path) ?: '{}', true) : [];
        if (!is_array($c)) $c = [];
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
        $path = $config['content_json'] ?? (__DIR__ . '/dados/conteudo.json');
        @mkdir(dirname($path), 0755, true);
        $ok = file_put_contents(
            $path,
            json_encode([
                'services'     => array_values($services),
                'portfolio'    => array_values($portfolio),
                'testimonials' => is_array($testimonials) ? array_values($testimonials) : [],
                'headings'     => is_array($headings) ? $headings : [],
                'contact'      => is_array($contact) ? $contact : [],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );
        if ($ok === false) responder(false, ['message' => 'Falha ao gravar.'], 500);
        responder(true, ['message' => 'Conteúdo guardado.']);

    case 'stats':
        exigir_login();
        $lista = ler_leads($config);
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
