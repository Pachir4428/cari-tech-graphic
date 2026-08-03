<?php
/**
 * Cari Tech Graphic — Cliente da API MozPayment (M-Pesa / e-Mola)
 * ---------------------------------------------------------------------------
 * Base URL: https://mozpayment.co.mz/api/1.1/wf/
 * Envia um pedido de cobrança (push USSD) para o número do cliente; ele
 * confirma no telemóvel. A "carteira" é o ID da conta MozPayment do estúdio
 * (Definições → Pagamentos automáticos, no painel) — nunca é exposta ao site
 * público, só usada aqui no servidor.
 */

const MOZPAY_BASE_URL = 'https://mozpayment.co.mz/api/1.1/wf/';

/**
 * Cobra um valor por M-Pesa ou e-Mola via MozPayment.
 * Devolve ['ok' => bool, 'message' => string, 'raw' => mixed].
 */
function mozpay_cobrar($carteira, $numero, $valor, $metodo) {
    $endpoint = ($metodo === 'emola') ? 'pagamentorotativoemola' : 'pagamentorotativompesa';
    $payload = [
        'carteira' => $carteira,
        'numero'   => $numero,
        'valor'    => (string) $valor,
    ];

    $ch = curl_init(MOZPAY_BASE_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resposta = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resposta === false) {
        return ['ok' => false, 'message' => 'Não foi possível contactar o MozPayment (' . $erroCurl . ').', 'raw' => null];
    }

    $json = json_decode($resposta, true);
    $texto = is_array($json) ? (string) ($json['message'] ?? ($json['error'] ?? '')) : '';

    // A API não documenta publicamente um formato de resposta fixo; assume-se
    // falha em qualquer HTTP de erro ou texto que indique recusa/erro.
    $falhou = $codigoHttp >= 400 || preg_match('/(erro|error|fail|falh|insufic|invalid|recus)/i', $texto);

    if ($falhou) {
        return ['ok' => false, 'message' => $texto !== '' ? $texto : 'Pagamento recusado pelo operador.', 'raw' => $json ?? $resposta];
    }
    return ['ok' => true, 'message' => $texto !== '' ? $texto : 'Pagamento confirmado.', 'raw' => $json ?? $resposta];
}
