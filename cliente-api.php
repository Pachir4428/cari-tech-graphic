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

if (!in_array($action, ['deliverables', 'comment', 'upload'], true)) {
    responder(false, ['message' => 'Acção desconhecida.'], 400);
}

/* Upload usa multipart (POST normal), não JSON. */
if ($action === 'upload') {
    $email  = strtolower(trim((string) ($_POST['email'] ?? '')));
    $codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
} else {
    $email  = strtolower(trim((string) ($body['email'] ?? '')));
    $codigo = strtoupper(trim((string) ($body['codigo'] ?? '')));
}

if ($email === '' || $codigo === '') {
    responder(false, ['message' => 'Indique o e-mail e o código de acesso.'], 422);
}

/* Anti-força-bruta: limita tentativas de código por IP (10 em 15 min). */
$rlChave = 'cliente:' . ctg_client_ip();
$rl = store_rate_status($config, $rlChave, 10, 900);
if ($rl['bloqueado']) {
    $min = (int) ceil($rl['restam'] / 60);
    responder(false, ['message' => "Demasiadas tentativas. Tente novamente dentro de {$min} min."], 429);
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
    store_rate_falha($config, $rlChave, 900);
    usleep(600000);
    responder(false, ['message' => 'E-mail ou código incorrectos. Confirme os dados do e-mail de confirmação.'], 401);
}
/* Sucesso: limpa o contador deste IP. */
store_rate_limpar($config, $rlChave);

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

/* -------------------------------------------------------------------------- */
/* Upload de ficheiro pelo cliente (brief/referências) num pedido             */
/* -------------------------------------------------------------------------- */
if ($action === 'upload') {
    $leadId = (string) ($_POST['leadId'] ?? '');
    $pertence = null;
    foreach ($doCliente as $l) { if (($l['id'] ?? '') === $leadId) { $pertence = $l; break; } }
    if (!$pertence) responder(false, ['message' => 'Pedido não encontrado.'], 404);

    $file = $_FILES['file'] ?? null;
    if (empty($file) || ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        responder(false, ['message' => 'Nenhum ficheiro recebido (ou demasiado grande).'], 422);
    }
    if ($file['size'] > 15 * 1024 * 1024) responder(false, ['message' => 'O ficheiro não pode exceder 15 MB.'], 422);
    $nomeOrig = (string) ($file['name'] ?? 'ficheiro');
    $ext = strtolower(pathinfo($nomeOrig, PATHINFO_EXTENSION));
    $permitidas = ['pdf','doc','docx','txt','rtf','png','jpg','jpeg','gif','webp','svg','zip','rar','ai','psd','eps'];
    if ($ext === '' || !in_array($ext, $permitidas, true)) {
        responder(false, ['message' => 'Tipo de ficheiro não permitido.'], 422);
    }
    if (!ctg_ficheiro_seguro($file['tmp_name'], $ext)) {
        responder(false, ['message' => 'Ficheiro rejeitado por segurança: o conteúdo não corresponde ao tipo declarado.'], 422);
    }
    $dir = __DIR__ . '/uploads/cliente';
    @mkdir($dir, 0755, true);
    $slug = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($nomeOrig, PATHINFO_FILENAME));
    $slug = trim(substr($slug, 0, 40), '-') ?: 'ficheiro';
    $nomeDisco = $slug . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nomeDisco)) {
        responder(false, ['message' => 'Não foi possível guardar o ficheiro.'], 500);
    }
    $lista = store_add_cliente_ficheiro($config, $leadId, [
        'url' => 'uploads/cliente/' . $nomeDisco, 'nome' => $nomeOrig, 'data' => date('c'),
    ]);
    responder(true, ['message' => 'Ficheiro enviado.', 'cliente_ficheiros' => $lista]);
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
        'pago' => $entrega['pago'] ?? 'nao',
        'cliente_ficheiros' => ($entrega && !empty($entrega['cliente_ficheiros'])) ? array_map(function ($f) {
            return ['url' => $f['url'] ?? '', 'nome' => $f['nome'] ?? '', 'data' => $f['data'] ?? ''];
        }, $entrega['cliente_ficheiros']) : [],
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
