<?php
/**
 * Cari Tech Graphic — Webhook da PagaJá (confirmação assíncrona de pagamentos)
 * ---------------------------------------------------------------------------
 * A PagaJá chama este endpoint quando uma cobrança criada em pagamento.php é
 * confirmada ("payment.completed"). Autenticado pela assinatura HMAC do
 * cabeçalho x-pagaja-signature (nunca por sessão — é a própria PagaJá que
 * chama isto, não o browser do cliente).
 *
 * Responde sempre 2xx rapidamente (exigência da PagaJá — 10s; não há
 * reenvio automático, por isso um erro nosso aqui perderia o evento para
 * sempre) — incluindo quando o evento não corresponde a nenhuma cobrança
 * pendente conhecida (ex.: um teste a partir do painel da PagaJá), e mesmo
 * que ocorra um erro inesperado a processar (fica registado na auditoria
 * para o admin rever, mas a PagaJá nunca vê um 5xx daqui).
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/armazenamento.php';
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

/* Versão local (sem sessão de admin) de ctg_lead_por_id — este script corre
   fora do admin-api.php, por isso não pode depender das suas funções. */
function ctg_lead_por_id_webhook($config, $leadId) {
    foreach (store_get_leads($config) as $l) {
        if (($l['id'] ?? '') === $leadId) return $l;
    }
    return null;
}

$leadIdSeguranca = '';

try {
    $rawBody = file_get_contents('php://input');
    $assinatura = $_SERVER['HTTP_X_PAGAJA_SIGNATURE'] ?? '';

    $pj = store_get_pagaja($config);
    $segredo = (string) ($pj['webhook_secret'] ?? '');
    if ($segredo === '' || !pagaja_verificar_assinatura($rawBody, $assinatura, $segredo)) {
        store_add_audit($config, 'pagaja-webhook-invalido', 'Assinatura inválida ou webhook não configurado.');
        responder(false, ['message' => 'Assinatura inválida.'], 401);
    }

    $evento = json_decode($rawBody, true);
    if (!is_array($evento) || ($evento['event'] ?? '') !== 'payment.completed') {
        responder(true, ['message' => 'Evento ignorado.']); // reconhece, mas não há nada a fazer.
    }

    $dados = $evento['data'] ?? [];
    $reference = (string) ($dados['id'] ?? '');
    if ($reference === '') {
        responder(true, ['message' => 'Evento sem referência — ignorado.']);
    }

    $pendente = store_tomar_pagaja_pendente($config, $reference);
    if (!$pendente) {
        // Pode ser um pagamento confirmado depois de já termos desistido de o
        // acompanhar (mais de 7 dias) ou um evento de teste — reconhece na mesma.
        store_add_audit($config, 'pagaja-webhook-sem-correspondencia', "reference={$reference}");
        responder(true, ['message' => 'Sem cobrança pendente correspondente.']);
    }

    $leadId = (string) ($pendente['leadId'] ?? '');
    $leadIdSeguranca = $leadId;
    $email  = (string) ($pendente['email'] ?? '');
    $codigo = (string) ($pendente['codigo'] ?? '');
    $valor  = (float) ($pendente['valor'] ?? ($dados['amount'] ?? 0));
    $ehTotal = !empty($pendente['ehTotal']);
    $valorTotalDeclarado = $pendente['valorTotal'] ?? null;

    $lead = ctg_lead_por_id_webhook($config, $leadId);

    $estadoAnterior = store_get_delivery($config, $leadId);
    $pagoAntes = $estadoAnterior['pago'] ?? 'nao';
    $slot = store_registar_pagamento($config, $leadId, $valor, $ehTotal, $valorTotalDeclarado);
    $pagoAgora = $slot['pago'] ?? 'nao';

    $financas = store_get_financas($config);
    $financas[] = [
        'id'    => bin2hex(random_bytes(8)),
        'data'  => date('Y-m-d'),
        'desc'  => ($pagoAgora === 'parcial' ? 'Entrada (parcela) — ' : 'Pagamento — ') . 'PAGAJÁ — ' . ($lead['servico'] ?? $lead['nome'] ?? $leadId),
        'tipo'  => 'entrada',
        'valor' => $valor,
        'categoria' => 'Checkout',
    ];
    store_set_financas($config, $financas);

    if ($pagoAgora === 'pago' && $pagoAntes !== 'pago' && $lead) {
        $entrega = store_entregar_automaticamente($config, $lead, $leadId);
        $instrucoes = trim((string) ($entrega['instrucoes'] ?? ''));

        $marca = $config['from_name'] ?? 'Cari Tech Graphic';
        $branding = store_get_branding($config);
        $html = email_notificacao_html($config, [
            'tipo' => 'pago', 'nome' => $lead['nome'] ?? '', 'servico' => $lead['servico'] ?? '',
            'texto' => $instrucoes, 'codigo' => $codigo, 'email' => $email, 'logo' => $branding['light'] ?? ($branding['dark'] ?? ''),
        ]);
        $corpo = "Olá " . ($lead['nome'] ?? '') . ",\n\n"
            . "Recebemos o seu pagamento de " . number_format($valor, 2, ',', ' ') . " MT — o pedido está totalmente pago. "
            . "Os seus ficheiros já estão disponíveis para descarregar na Área de Cliente.\n";
        if ($instrucoes !== '') $corpo .= "\nComo aceder/usar:\n{$instrucoes}\n";
        enviar_email($config, $email, 'Pagamento confirmado — Cari Tech Graphic', $corpo, $config['to_email'] ?? '', $marca, $html);
    }

    store_add_audit($config, 'pagamento-gateway-estado', "Pedido {$leadId} (PagaJá): {$pagoAntes} → {$pagoAgora}");

    responder(true, ['message' => 'Processado.']);

} catch (\Throwable $erro) {
    error_log('webhook-pagaja.php: ' . $erro->getMessage());
    try {
        store_add_audit($config, 'pagaja-webhook-erro', ($leadIdSeguranca ?: '?') . ': ' . $erro->getMessage());
        if ($leadIdSeguranca !== '') {
            // Melhor esforço: mesmo que algo tenha falhado a meio, tenta não
            // deixar um pagamento já confirmado pela PagaJá por marcar.
            store_set_pagamento($config, $leadIdSeguranca, 'pago');
        }
    } catch (\Throwable $e2) { /* nunca deixar o registo do erro derrubar a resposta */ }
    // Responde sempre 2xx à PagaJá — não há reenvio automático do lado deles.
    responder(true, ['message' => 'Recebido, com um erro interno registado para revisão.']);
}
