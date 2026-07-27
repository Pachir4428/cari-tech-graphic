<?php
/**
 * Cari Tech Graphic — API da Área de Cliente (pública)
 * ---------------------------------------------------------------------------
 * Permite ao cliente consultar o estado do seu pedido e transferir as
 * entregas (ficheiros/links) usando o E-MAIL + CÓDIGO que recebeu por e-mail.
 * Não requer sessão de administrador.
 *
 *   POST ?action=deliverables  { email, codigo }  -> pedido + entregas
 *
 * Segurança: só devolve dados quando o e-mail E o código coincidem com um
 * lead existente. Para dificultar tentativas por força bruta há um pequeno
 * atraso em cada falha.
 */

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, ['message' => 'Método não permitido.'], 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = $_POST;

$action = $_GET['action'] ?? 'deliverables';

if (!in_array($action, ['deliverables', 'comment'], true)) {
    responder(false, ['message' => 'Acção desconhecida.'], 400);
}

$email  = strtolower(trim((string) ($body['email'] ?? '')));
$codigo = strtoupper(trim((string) ($body['codigo'] ?? '')));

if ($email === '' || $codigo === '') {
    responder(false, ['message' => 'Indique o e-mail e o código de acesso.'], 422);
}

/* Recolhe TODOS os pedidos deste e-mail e confirma que o código pertence a
   pelo menos um deles (prova de posse do e-mail — o código foi enviado para lá). */
$doCliente = [];
$codigoValido = false;
$nomeCliente = '';
foreach (store_get_leads($config) as $l) {
    if (strtolower(trim((string) ($l['email'] ?? ''))) !== $email) continue;
    $doCliente[] = $l;
    if ($nomeCliente === '' && !empty($l['nome'])) $nomeCliente = $l['nome'];
    if (strtoupper(trim((string) ($l['codigo'] ?? ''))) === $codigo && $codigo !== '') {
        $codigoValido = true;
    }
}

if (!$doCliente || !$codigoValido) {
    usleep(600000);
    responder(false, ['message' => 'E-mail ou código incorrectos. Confirme os dados do e-mail de confirmação.'], 401);
}

/* -------------------------------------------------------------------------- */
/* Comentário do cliente numa entrega/pedido                                  */
/* -------------------------------------------------------------------------- */
if ($action === 'comment') {
    $leadId = (string) ($body['leadId'] ?? '');
    $texto  = trim((string) ($body['texto'] ?? ''));
    if ($leadId === '' || $texto === '') {
        responder(false, ['message' => 'Escreva um comentário.'], 422);
    }
    /* Confirma que o pedido pertence mesmo a este cliente. */
    $pertence = false;
    foreach ($doCliente as $l) { if (($l['id'] ?? '') === $leadId) { $pertence = true; break; } }
    if (!$pertence) {
        responder(false, ['message' => 'Pedido não encontrado.'], 404);
    }
    if (mb_strlen($texto) > 1000) $texto = mb_substr($texto, 0, 1000);
    $comentarios = store_add_comment($config, $leadId, [
        'de'    => 'cliente',
        'nome'  => $nomeCliente,
        'texto' => $texto,
        'data'  => date('c'),
    ]);
    responder(true, ['comentarios' => $comentarios]);
}

/* Estado legível para o cliente. */
$estados = [
    'new'       => 'Pedido recebido',
    'contacted' => 'Em andamento',
    'won'       => 'Concluído',
    'lost'      => 'Encerrado',
];

/* Monta a lista de todos os pedidos (mais recente primeiro) com as entregas. */
$pedidos = array_map(function ($l) use ($config, $estados) {
    $status = $l['status'] ?? 'new';
    $entrega = store_get_delivery($config, $l['id'] ?? '');
    return [
        'id'       => $l['id'] ?? '',
        'servico'  => $l['servico'] ?? '',
        'mensagem' => $l['mensagem'] ?? '',
        'data'     => $l['data'] ?? '',
        'codigo'   => $l['codigo'] ?? '',
        'status'   => $status,
        'estado'   => $estados[$status] ?? 'Pedido recebido',
        'entrega'  => $entrega ? [
            'msg'      => $entrega['msg'] ?? '',
            'entregas' => array_map(function ($e) {
                return [
                    'tipo' => $e['tipo'] ?? 'link',
                    'url'  => $e['url'] ?? '',
                    'nome' => $e['nome'] ?? ($e['url'] ?? ''),
                    'note' => $e['note'] ?? '',
                ];
            }, $entrega['entregas'] ?? []),
            'data'     => $entrega['data'] ?? '',
        ] : null,
        'comentarios' => ($entrega && !empty($entrega['comentarios'])) ? array_map(function ($c) {
            return ['de' => $c['de'] ?? 'cliente', 'nome' => $c['nome'] ?? '', 'texto' => $c['texto'] ?? '', 'data' => $c['data'] ?? ''];
        }, $entrega['comentarios']) : [],
    ];
}, $doCliente);

responder(true, [
    'cliente' => ['nome' => $nomeCliente, 'email' => $email],
    'pedidos' => $pedidos,
    /* Compatibilidade: primeiro pedido também em 'pedido'/'entrega'. */
    'pedido'  => [
        'nome'    => $nomeCliente,
        'servico' => $pedidos[0]['servico'] ?? '',
        'mensagem'=> $pedidos[0]['mensagem'] ?? '',
        'data'    => $pedidos[0]['data'] ?? '',
        'status'  => $pedidos[0]['status'] ?? 'new',
        'estado'  => $pedidos[0]['estado'] ?? 'Pedido recebido',
    ],
    'entrega' => $pedidos[0]['entrega'] ?? null,
]);
