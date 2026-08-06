<?php
/**
 * Cari Tech Graphic — Checkout: pagamento automático (M-Pesa/e-Mola/mKesh/Cartão)
 * ---------------------------------------------------------------------------
 * Recebe do site público o pedido de cobrança e, em caso de sucesso imediato,
 * regista o valor pago (total ou parcial — entrada/parcela), a entrada nas
 * Finanças e, quando o pedido fica totalmente pago, avisa o cliente por
 * e-mail — tal como acontece quando o admin marca "Pago" no painel.
 *
 * Dois gateways possíveis, escolhidos automaticamente consoante o que estiver
 * configurado no painel (Definições → Pagamento automático):
 *  - PagaJá (preferido quando configurado): cobre mpesa/emola/mkesh/cartão
 *    numa só API OAuth2. Cobranças "pending" (STK push ainda por aprovar, ou
 *    cartão) só ficam confirmadas quando o webhook (webhook-pagaja.php)
 *    avisar — aqui devolvemos logo uma mensagem de "a aguardar confirmação".
 *  - MozPayment (recurso, só mpesa/emola): confirmação síncrona nesta mesma
 *    chamada, como até agora.
 *
 *   POST { leadId, email, codigo, metodo: 'mpesa'|'emola'|'mkesh'|'cartao',
 *          numero?, valor, ehTotal?: bool, valorTotal?: number }
 *   - numero: obrigatório excepto para 'cartao' (paga-se pelo checkout_url).
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
require_once __DIR__ . '/pagaja.php';
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
$metodo = (string) ($body['metodo'] ?? 'mpesa');
if (!in_array($metodo, ['mpesa', 'emola', 'mkesh', 'cartao'], true)) $metodo = 'mpesa';
$numero = preg_replace('/\D+/', '', (string) ($body['numero'] ?? ''));
$valor  = (float) ($body['valor'] ?? 0);
$ehTotal = !empty($body['ehTotal']);
$valorTotalDeclarado = isset($body['valorTotal']) && $body['valorTotal'] !== '' ? (float) $body['valorTotal'] : null;

if ($leadId === '' || $email === '' || $codigo === '') {
    responder(false, ['message' => 'Não foi possível identificar o seu pedido. Recarregue a página e tente novamente.'], 422);
}
if ($metodo !== 'cartao' && strlen($numero) < 9) {
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

/* Aplica um pagamento confirmado na hora: regista o valor, a entrada nas
   Finanças e, se transitar para "pago", avisa o cliente por e-mail. Partilhado
   pelos dois gateways quando confirmam de forma síncrona (teste, ou métodos
   instantâneos). Devolve a mensagem final para a resposta. */
function ctg_aplicar_pagamento($config, $lead, $leadId, $email, $codigo, $valor, $ehTotal, $valorTotalDeclarado, $origem, $mensagemGateway) {
    $estadoAnterior = store_get_delivery($config, $leadId);
    $pagoAntes = $estadoAnterior['pago'] ?? 'nao';
    $slot = store_registar_pagamento($config, $leadId, $valor, $ehTotal, $valorTotalDeclarado);
    $pagoAgora = $slot['pago'] ?? 'nao';
    $saldo = !empty($slot['valorAcordado']) ? max(0, round($slot['valorAcordado'] - $slot['valorPago'], 2)) : null;

    $financas = store_get_financas($config);
    $financas[] = [
        'id'    => bin2hex(random_bytes(8)),
        'data'  => date('Y-m-d'),
        'desc'  => ($pagoAgora === 'parcial' ? 'Entrada (parcela) — ' : 'Pagamento — ') . $origem . ' — ' . ($lead['servico'] ?: ($lead['nome'] ?? $leadId)),
        'tipo'  => 'entrada',
        'valor' => $valor,
        'categoria' => 'Checkout',
    ];
    store_set_financas($config, $financas);

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
    store_add_audit($config, 'pagamento-gateway-estado', "Pedido {$leadId} ({$origem}): {$pagoAntes} → {$pagoAgora}");

    $msg = $mensagemGateway ?: 'Pagamento confirmado! Obrigado.';
    if ($pagoAgora === 'parcial' && $saldo !== null) {
        $msg .= ' Falta pagar ' . number_format($saldo, 2, ',', ' ') . ' MT — pode fazê-lo mais tarde na Área de Cliente.';
    } elseif ($pagoAgora === 'parcial') {
        $msg .= ' Registámos esta parcela — combine o saldo com o estúdio ou pague o resto na Área de Cliente.';
    }
    return ['message' => $msg, 'pago' => $pagoAgora, 'valorPago' => $slot['valorPago'] ?? $valor, 'valorAcordado' => $slot['valorAcordado'] ?? null, 'saldo' => $saldo];
}

/* --- Escolhe o gateway: PagaJá (cobre todos os métodos) tem prioridade; ---- */
/* --- MozPayment é o recurso só para mpesa/emola.                     ------ */
$pj = store_get_pagaja($config);
$pagajaActivo = !empty($pj['enabled']) && trim((string) ($pj['client_id'] ?? '')) !== '' && trim((string) ($pj['client_secret'] ?? '')) !== '';

if ($pagajaActivo) {
    $res = pagaja_cobrar($config, [
        'valor'     => $valor,
        'descricao' => 'Pedido — ' . ($lead['servico'] ?: ($lead['nome'] ?? $leadId)),
        'nome'      => $lead['nome'] ?? $email,
        'email'     => $email,
        'telefone'  => $numero,
        'metodo'    => $metodo === 'cartao' ? 'visa_mastercard' : $metodo,
    ]);

    store_add_audit(
        $config,
        'pagamento-gateway',
        "Pedido {$leadId} · PAGAJÁ · " . strtoupper($metodo) . ' · ' . number_format($valor, 2, ',', ' ') . ' MT · '
            . ($res['ok'] ? ('estado: ' . $res['status']) : ('falha: ' . $res['message']))
    );

    if (!$res['ok']) {
        responder(false, ['message' => $res['message']], 402);
    }

    if ($res['status'] === 'success') {
        // Modo teste (ou método que confirma na hora): aplica já o pagamento.
        $resultado = ctg_aplicar_pagamento($config, $lead, $leadId, $email, $codigo, $valor, $ehTotal, $valorTotalDeclarado, 'PagaJá', 'Pagamento confirmado (teste)! Obrigado.');
        responder(true, $resultado);
    }

    // "pending" — STK push a aguardar aprovação no telemóvel, ou cartão a
    // aguardar o checkout_url. Só fica confirmado quando o webhook chegar.
    if ($res['reference'] !== '') {
        store_set_pagaja_pendente($config, $res['reference'], [
            'leadId' => $leadId, 'email' => $email, 'codigo' => $codigo,
            'valor' => $valor, 'ehTotal' => $ehTotal, 'valorTotal' => $valorTotalDeclarado,
            'criado' => time(),
        ]);
    }
    $msg = $metodo === 'cartao'
        ? 'Cobrança criada — conclua o pagamento na página que se vai abrir.'
        : 'Enviámos um pedido de confirmação para o seu telemóvel — aprove-o para concluir. Assim que confirmarmos, avisamos por e-mail.';
    responder(true, ['message' => $msg, 'pago' => 'pendente', 'checkout_url' => $res['checkout_url']]);
}

if (in_array($metodo, ['mkesh', 'cartao'], true)) {
    responder(false, ['message' => 'Este método de pagamento não está disponível de momento.'], 422);
}

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
    "Pedido {$leadId} · MOZPAYMENT · " . strtoupper($metodo) . ' · ' . number_format($valor, 2, ',', ' ') . ' MT · '
        . ($res['ok'] ? 'sucesso' : 'falha') . ': ' . $res['message']
);

if (!$res['ok']) {
    responder(false, ['message' => $res['message']], 402);
}

$resultado = ctg_aplicar_pagamento($config, $lead, $leadId, $email, $codigo, $valor, $ehTotal, $valorTotalDeclarado, 'MozPayment', $res['message']);
responder(true, $resultado);
