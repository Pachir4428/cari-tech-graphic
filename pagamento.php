<?php
/**
 * Cari Tech Graphic — Checkout: pagamento automático (M-Pesa / e-Mola)
 * ---------------------------------------------------------------------------
 * Recebe do site público o pedido de cobrança via MozPayment e, em caso de
 * sucesso, regista o valor pago (total ou parcial — entrada/parcela), regista
 * a entrada nas Finanças e, quando o pedido fica totalmente pago, avisa o
 * cliente por e-mail — tal como acontece quando o admin marca "Pago" no painel.
 *
 *   POST { leadId, email, codigo, metodo: 'mpesa'|'emola', numero, valor,
 *          ehTotal?: bool, valorTotal?: number }
 *   - ehTotal: true quando o cliente está a pagar o valor total do pedido.
 *   - valorTotal: quando paga só uma percentagem, o valor total do trabalho
 *     que o próprio declarou (usado para calcular o saldo em falta).
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
$ehTotal = !empty($body['ehTotal']);
$valorTotalDeclarado = isset($body['valorTotal']) && $body['valorTotal'] !== '' ? (float) $body['valorTotal'] : null;

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

/* Sucesso: regista o valor pago (a função recalcula 'nao'/'parcial'/'pago'
   consoante o valor acordado) e a entrada nas Finanças. */
$estadoAnterior = store_get_delivery($config, $leadId);
$pagoAntes = $estadoAnterior['pago'] ?? 'nao';
$slot = store_registar_pagamento($config, $leadId, $valor, $ehTotal, $valorTotalDeclarado);
$pagoAgora = $slot['pago'] ?? 'nao';
$saldo = !empty($slot['valorAcordado']) ? max(0, round($slot['valorAcordado'] - $slot['valorPago'], 2)) : null;

$financas = store_get_financas($config);
$financas[] = [
    'id'    => bin2hex(random_bytes(8)),
    'data'  => date('Y-m-d'),
    'desc'  => ($pagoAgora === 'parcial' ? 'Entrada (parcela) — ' : 'Pagamento — ') . strtoupper($metodo) . ' — ' . ($lead['servico'] ?: ($lead['nome'] ?? $leadId)),
    'tipo'  => 'entrada',
    'valor' => $valor,
    'categoria' => 'Checkout',
];
store_set_financas($config, $financas);

/* Só avisa o cliente por e-mail (ficheiros desbloqueados) na TRANSIÇÃO para "pago". */
if ($pagoAgora === 'pago' && $pagoAntes !== 'pago') {
    $marca = $config['from_name'] ?? 'Cari Tech Graphic';
    $branding = store_get_branding($config);
    $html = email_notificacao_html($config, [
        'tipo' => 'pago', 'nome' => $lead['nome'] ?? '', 'servico' => $lead['servico'] ?? '',
        'texto' => '', 'codigo' => $codigo, 'email' => $email, 'logo' => $branding['light'] ?? ($branding['dark'] ?? ''),
    ]);
    $corpo = "Olá " . ($lead['nome'] ?? '') . ",\n\n"
        . "Recebemos o seu pagamento de " . number_format($valor, 2, ',', ' ') . " MT — o pedido está totalmente pago. "
        . "Os seus ficheiros já estão disponíveis para descarregar na Área de Cliente.\n";
    enviar_email($config, $email, 'Pagamento confirmado — Cari Tech Graphic', $corpo, $config['to_email'] ?? '', $marca, $html);
}
store_add_audit($config, 'pagamento-gateway-estado', "Pedido {$leadId}: {$pagoAntes} → {$pagoAgora}");

$msg = $res['message'] ?: 'Pagamento confirmado! Obrigado.';
if ($pagoAgora === 'parcial' && $saldo !== null) {
    $msg .= ' Falta pagar ' . number_format($saldo, 2, ',', ' ') . ' MT — pode fazê-lo mais tarde na Área de Cliente.';
} elseif ($pagoAgora === 'parcial') {
    $msg .= ' Registámos esta parcela — combine o saldo com o estúdio ou pague o resto na Área de Cliente.';
}

responder(true, ['message' => $msg, 'pago' => $pagoAgora, 'valorPago' => $slot['valorPago'] ?? $valor, 'valorAcordado' => $slot['valorAcordado'] ?? null, 'saldo' => $saldo]);
