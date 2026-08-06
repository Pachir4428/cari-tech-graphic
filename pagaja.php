<?php
/**
 * Cari Tech Graphic — Cliente da API PagaJá (OAuth 2.0 client-credentials)
 * ---------------------------------------------------------------------------
 * Base URL: https://pagaja.site — cobranças por M-Pesa/e-Mola/mKesh/Cartão,
 * com webhook assinado para confirmação assíncrona. Ver documentação oficial
 * (Introdução, Autenticação, Cobranças, Webhooks) para o contrato completo.
 *
 * As credenciais (client_id/client_secret) e o segredo do webhook ficam só no
 * servidor (dados/pagaja.json) — nunca são expostos ao site público.
 */

const PAGAJA_BASE_URL = 'https://pagaja.site';

/* Pedido HTTP genérico à PagaJá. Devolve sempre um array uniforme, mesmo em
   falha de rede, para os chamadores nunca terem de lidar com excepções. */
function pagaja_http($metodo, $caminho, $body = null, $token = null) {
    $ch = curl_init(PAGAJA_BASE_URL . $caminho);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    $opts = [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
    curl_setopt_array($ch, $opts);
    $resposta = curl_exec($ch);
    $erro = curl_error($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resposta === false) {
        return ['ok' => false, 'status' => 0, 'body' => null, 'error' => $erro];
    }
    $json = json_decode($resposta, true);
    return ['ok' => $codigo >= 200 && $codigo < 300, 'status' => $codigo, 'body' => $json, 'error' => null];
}

/* Obtém um access_token válido, reaproveitando o último em cache até perto de
   expirar (a API não tem refresh token — é sempre um novo /oauth/token). */
function pagaja_token($config) {
    $cfg = store_get_pagaja($config);
    $clientId = trim((string) ($cfg['client_id'] ?? ''));
    $clientSecret = trim((string) ($cfg['client_secret'] ?? ''));
    if ($clientId === '' || $clientSecret === '') {
        return ['ok' => false, 'message' => 'PagaJá não está configurado (falta o ID/segredo da chave).'];
    }
    $cache = store_get_pagaja_token($config);
    if (!empty($cache['access_token']) && ($cache['client_id'] ?? '') === $clientId && ($cache['expires_at'] ?? 0) > time() + 60) {
        return ['ok' => true, 'access_token' => $cache['access_token']];
    }
    $r = pagaja_http('POST', '/api/v1/oauth/token', ['client_id' => $clientId, 'client_secret' => $clientSecret]);
    if (!$r['ok'] || empty($r['body']['access_token'])) {
        $msg = $r['body']['error'] ?? ($r['error'] ?: 'Não foi possível autenticar na PagaJá.');
        if (($r['status'] ?? 0) === 401) $msg = 'Credenciais da PagaJá inválidas — confirme o ID e o segredo da chave.';
        return ['ok' => false, 'message' => $msg];
    }
    store_set_pagaja_token($config, [
        'access_token' => $r['body']['access_token'],
        'client_id'    => $clientId,
        'expires_at'   => time() + (int) ($r['body']['expires_in'] ?? 3600),
    ]);
    return ['ok' => true, 'access_token' => $r['body']['access_token']];
}

/* Cria uma cobrança (valor livre, sem produto associado) para um cliente.
 * $params: valor, descricao, nome, email, telefone, metodo (mpesa|emola|mkesh|visa_mastercard). */
function pagaja_cobrar($config, $params) {
    $tok = pagaja_token($config);
    if (!$tok['ok']) return ['ok' => false, 'message' => $tok['message']];
    $corpo = [
        'amount'          => (float) $params['valor'],
        'currency'        => 'MZN',
        'description'     => (string) ($params['descricao'] ?? 'Pagamento — Cari Tech Graphic'),
        'customer_name'   => (string) $params['nome'],
        'customer_email'  => (string) $params['email'],
        'customer_phone'  => (string) ($params['telefone'] ?? ''),
        'payment_method'  => (string) ($params['metodo'] ?? 'mpesa'),
    ];
    $r = pagaja_http('POST', '/api/v1/charges', $corpo, $tok['access_token']);
    if (!$r['ok'] || empty($r['body']['success'])) {
        $msg = $r['body']['error'] ?? ($r['error'] ?: 'Não foi possível criar a cobrança na PagaJá.');
        if (($r['status'] ?? 0) === 403) $msg = 'A conta PagaJá ainda não tem o modo de produção activo (chave live por desbloquear).';
        return ['ok' => false, 'message' => $msg];
    }
    $d = $r['body']['data'] ?? [];
    return [
        'ok'           => true,
        'reference'    => (string) ($d['reference'] ?? ''),
        'status'       => (string) ($d['status'] ?? 'pending'),
        'checkout_url' => $d['checkout_url'] ?? null,
        'test_mode'    => !empty($d['test_mode']),
    ];
}

/* Configura (upsert) o webhook de vendas da conta — um único endpoint por conta. */
function pagaja_configurar_webhook($config, $url) {
    $tok = pagaja_token($config);
    if (!$tok['ok']) return ['ok' => false, 'message' => $tok['message']];
    $r = pagaja_http('POST', '/api/v1/webhooks', ['url' => $url, 'events' => ['payment.completed']], $tok['access_token']);
    if (!$r['ok'] || empty($r['body']['success'])) {
        $msg = $r['body']['error'] ?? ($r['error'] ?: 'Não foi possível configurar o webhook.');
        return ['ok' => false, 'message' => $msg];
    }
    return ['ok' => true, 'webhook' => $r['body']['webhook'] ?? []];
}

/* Consulta o webhook actualmente configurado na conta (segredo mascarado). */
function pagaja_estado_webhook($config) {
    $tok = pagaja_token($config);
    if (!$tok['ok']) return ['ok' => false, 'message' => $tok['message']];
    $r = pagaja_http('GET', '/api/v1/webhooks', null, $tok['access_token']);
    if (!$r['ok'] || !isset($r['body']['success'])) {
        $msg = $r['body']['error'] ?? ($r['error'] ?: 'Não foi possível consultar o webhook.');
        return ['ok' => false, 'message' => $msg];
    }
    return ['ok' => true, 'webhook' => $r['body']['webhook'] ?? null];
}

/* Valida a assinatura HMAC-SHA256 de um evento de webhook recebido:
 * cabeçalho "t=TIMESTAMP,v1=ASSINATURA", ASSINATURA = HMAC_SHA256("{t}.{corpo}"). */
function pagaja_verificar_assinatura($rawBody, $signatureHeader, $secret) {
    if (!$signatureHeader || $secret === '') return false;
    $partes = [];
    foreach (explode(',', $signatureHeader) as $par) {
        $kv = explode('=', $par, 2);
        if (count($kv) === 2) $partes[$kv[0]] = $kv[1];
    }
    $timestamp = $partes['t'] ?? '';
    $assinatura = $partes['v1'] ?? '';
    if ($timestamp === '' || $assinatura === '') return false;
    $esperada = hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    return hash_equals($esperada, $assinatura);
}

/* Modo da chave, deduzido do prefixo do client_secret (só informativo, para a UI). */
function pagaja_modo_chave($clientSecret) {
    if (strpos((string) $clientSecret, 'pgj_live_') === 0) return 'live';
    if (strpos((string) $clientSecret, 'pgj_test_') === 0) return 'test';
    return '';
}
