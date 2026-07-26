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

if ($action !== 'deliverables') {
    responder(false, ['message' => 'Acção desconhecida.'], 400);
}

$email  = strtolower(trim((string) ($body['email'] ?? '')));
$codigo = strtoupper(trim((string) ($body['codigo'] ?? '')));

if ($email === '' || $codigo === '') {
    responder(false, ['message' => 'Indique o e-mail e o código de acesso.'], 422);
}

/* Procura um lead com este e-mail + código. */
$encontrado = null;
foreach (store_get_leads($config) as $l) {
    if (strtolower(trim((string) ($l['email'] ?? ''))) === $email
        && strtoupper(trim((string) ($l['codigo'] ?? ''))) === $codigo
        && $codigo !== '') {
        $encontrado = $l;
        break;
    }
}

if (!$encontrado) {
    usleep(600000); // atrasa tentativas por força bruta
    responder(false, ['message' => 'E-mail ou código incorrectos. Confirme os dados do e-mail de confirmação.'], 401);
}

/* Estado legível para o cliente. */
$estados = [
    'new'       => 'Pedido recebido',
    'contacted' => 'Em andamento',
    'won'       => 'Concluído',
    'lost'      => 'Encerrado',
];
$status = $encontrado['status'] ?? 'new';

$entrega = store_get_delivery($config, $encontrado['id'] ?? '');

responder(true, [
    'pedido' => [
        'nome'    => $encontrado['nome'] ?? '',
        'servico' => $encontrado['servico'] ?? '',
        'mensagem'=> $encontrado['mensagem'] ?? '',
        'data'    => $encontrado['data'] ?? '',
        'status'  => $status,
        'estado'  => $estados[$status] ?? 'Pedido recebido',
    ],
    'entrega' => $entrega ? [
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
]);
