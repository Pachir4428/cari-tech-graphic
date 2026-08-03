<?php
/**
 * Cari Tech Graphic — Checkout: pagamento automático (M-Pesa / e-Mola)
 * ---------------------------------------------------------------------------
 * Recebe do site público o pedido de cobrança via MozPayment e, em caso de
 * sucesso, marca o pedido como Pago, regista a entrada nas Finanças e avisa
 * o cliente por e-mail — tal como acontece quando o admin marca "Pago" no
 * painel.
 *
 *   POST { leadId, email, codigo, metodo: 'mpesa'|'emola', numero, valor }
 *
 * Segurança: exige o e-mail + código que o cliente recebeu no e-mail de
 * confirmação do pedido (mesma verificação usada na Área de Cliente), e
 * limita tentativas por IP.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/armazenamento.php';
require_once __DIR__ . '/mozpayment.php';
require_once __DIR__ . '/email-enviar.php';
require_once __DIR__ . '/email-template.php';
$config = ctg_apply_smtp($config);

function responder($ok, $data = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, ['message' => 'Método não permitido.'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

$leadId = (string) ($body['leadId'] ?? '');
$email  = strtolower(trim((string) ($body['email'] ?? '')));
$codigo = strtoupper(trim((string) ($body['codigo'] ?? '')));
$metodo = (($body['metodo'] ?? '') === 'emola') ? 'emola' : 'mpesa';
$numero = preg_replace('/\D+/', '', (string) ($body['numero'] ?? ''));
$valor  = (float) ($body['valor'] ?? 0);

if ($leadId === '' || $email === '' || $codigo === '') {
    responder(false, ['message' => 'Não foi possível identificar o seu pedido. Recarregue a página e tente novamente.'], 422);
}
if (strlen($numero) < 9) {
    responder(false, ['message' => 'Indique um número de telemóvel válido.'], 422);
}
if ($valor <= 0) {
    responder(false, ['message' => 'Indique o valor a pagar.'], 422);
}

/* Anti-abuso: limita tentativas de cobrança por IP. */
$rlChave = 'pagamento:' . ctg_client_ip();
$rl = store_rate_status($config, $rlChave, 6, 900);
if ($rl['bloqueado']) {
    $min = (int) ceil($rl['restam'] / 60);
    responder(false, ['message' => "Demasiadas tentativas. Tente novamente dentro de {$min} min."], 429);
}

/* Confirma que o pedido pertence mesmo a este cliente (mesma prova de posse
   do e-mail usada na Área de Cliente: e-mail + código enviados por e-mail). */
$lead = null;
foreach (store_get_leads($config) as $l) {
    if (($l['id'] ?? '') === $leadId
        && strtolower(trim((string) ($l['email'] ?? ''))) === $email
        && strtoupper(trim((string) ($l['codigo'] ?? ''))) === $codigo) {
        $lead = $l;
        break;
    }
}
if (!$lead) {
    store_rate_falha($config, $rlChave, 900);
    responder(false, ['message' => 'Pedido não encontrado. Confirme o e-mail e o código recebidos na confirmação do pedido.'], 404);
}
store_rate_limpar($config, $rlChave);

$moz = store_get_mozpayment($config);
$activo = !empty($moz[$metodo === 'emola' ? 'emolaEnabled' : 'mpesaEnabled']);
$carteira = trim((string) ($moz[$metodo === 'emola' ? 'emolaCarteira' : 'mpesaCarteira'] ?? ''));
if (!$activo || $carteira === '') {
    responder(false, ['message' => 'Este método de pagamento não está disponível de momento.'], 422);
}

$res = mozpay_cobrar($carteira, $numero, $valor, $metodo);

store_add_audit(
    $config,
    'pagamento-gateway',
    "Pedido {$leadId} · " . strtoupper($metodo) . ' · ' . number_format($valor, 2, ',', ' ') . ' MT · '
        . ($res['ok'] ? 'sucesso' : 'falha') . ': ' . $res['message']
);

if (!$res['ok']) {
    responder(false, ['message' => $res['message']], 402);
}

/* Sucesso: marca o pedido como pago (desbloqueia entregas), regista a
   entrada nas Finanças e avisa o cliente — tal como o admin faz no painel. */
store_set_pagamento($config, $leadId, 'pago');

$financas = store_get_financas($config);
$financas[] = [
    'id'    => bin2hex(random_bytes(8)),
    'data'  => date('Y-m-d'),
    'desc'  => 'Pagamento online (' . strtoupper($metodo) . ') — ' . ($lead['servico'] ?: ($lead['nome'] ?? $leadId)),
    'tipo'  => 'entrada',
    'valor' => $valor,
    'categoria' => 'Checkout',
];
store_set_financas($config, $financas);

$marca = $config['from_name'] ?? 'Cari Tech Graphic';
$branding = store_get_branding($config);
$html = email_notificacao_html($config, [
    'tipo' => 'pago', 'nome' => $lead['nome'] ?? '', 'servico' => $lead['servico'] ?? '',
    'texto' => '', 'codigo' => $codigo, 'email' => $email, 'logo' => $branding['light'] ?? ($branding['dark'] ?? ''),
]);
$corpo = "Olá " . ($lead['nome'] ?? '') . ",\n\n"
    . "Recebemos o seu pagamento de " . number_format($valor, 2, ',', ' ') . " MT. "
    . "Os seus ficheiros já estão disponíveis para descarregar na Área de Cliente.\n";
enviar_email($config, $email, 'Pagamento confirmado — Cari Tech Graphic', $corpo, $config['to_email'] ?? '', $marca, $html);

responder(true, ['message' => $res['message'] ?: 'Pagamento confirmado! Obrigado.']);
