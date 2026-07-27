<?php
/**
 * Cari Tech Graphic — Entrega protegida por pagamento (demos com marca de água).
 * ---------------------------------------------------------------------------
 * Os ficheiros de entrega (uploads/entregas/) são servidos SÓ por aqui, com
 * verificação de dono (e-mail + código) e do estado de pagamento do pedido:
 *   - Pago:        serve o ficheiro original (download).
 *   - Por pagar:   se for imagem, mostra uma PRÉ-VISUALIZAÇÃO com marca de água
 *                  (não descarregável); outros ficheiros ficam bloqueados.
 *
 * Parâmetros:  ?lead=ID&file=uploads/entregas/xxx&email=..&codigo=..[&dl=1]
 */
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/armazenamento.php';

function _demo_fim($code, $msg) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

$leadId = (string) ($_GET['lead'] ?? '');
$file   = (string) ($_GET['file'] ?? '');
$email  = strtolower(trim((string) ($_GET['email'] ?? '')));
$codigo = strtoupper(trim((string) ($_GET['codigo'] ?? '')));
$dl     = !empty($_GET['dl']);

if ($leadId === '' || $file === '' || $email === '' || $codigo === '') {
    _demo_fim(400, 'Pedido inválido.');
}

/* Só ficheiros dentro de uploads/entregas/ (sem path traversal). */
$file = str_replace('\\', '/', $file);
if (strpos($file, '..') !== false || strpos($file, 'uploads/entregas/') !== 0) {
    _demo_fim(400, 'Caminho inválido.');
}
$abs = realpath(__DIR__ . '/' . $file);
$base = realpath(__DIR__ . '/uploads/entregas');
if ($abs === false || $base === false || strpos($abs, $base) !== 0 || !is_file($abs)) {
    _demo_fim(404, 'Ficheiro não encontrado.');
}

/* Confirma a posse: e-mail + código pertencem a um pedido deste e-mail. */
$doCliente = [];
$codigoValido = false;
foreach (store_get_leads($config) as $l) {
    if (strtolower(trim((string) ($l['email'] ?? ''))) !== $email) continue;
    $doCliente[$l['id'] ?? ''] = $l;
    if (strtoupper(trim((string) ($l['codigo'] ?? ''))) === $codigo && $codigo !== '') $codigoValido = true;
}
$rlChave = 'demo:' . ctg_client_ip();
$rl = store_rate_status($config, $rlChave, 15, 900);
if ($rl['bloqueado']) {
    _demo_fim(429, 'Demasiadas tentativas. Tente novamente mais tarde.');
}
if (!$codigoValido || !isset($doCliente[$leadId])) {
    store_rate_falha($config, $rlChave, 900);
    usleep(500000);
    _demo_fim(403, 'Acesso negado.');
}
store_rate_limpar($config, $rlChave);

/* O ficheiro tem mesmo de pertencer à entrega deste pedido. */
$entrega = store_get_delivery($config, $leadId);
$pertence = false;
foreach (($entrega['entregas'] ?? []) as $e) {
    if (($e['tipo'] ?? '') === 'file' && str_replace('\\', '/', (string) ($e['url'] ?? '')) === $file) { $pertence = true; break; }
}
if (!$pertence) _demo_fim(403, 'Acesso negado.');

$pago = ($entrega['pago'] ?? 'nao');
$ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
$imagens = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
$isImg = isset($imagens[$ext]);

/* ---- PAGO: serve o original (download ou inline) ---- */
if ($pago === 'pago') {
    $mime = $isImg ? $imagens[$ext] : 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($abs));
    if ($dl || !$isImg) {
        header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
    }
    header('X-Content-Type-Options: nosniff');
    readfile($abs);
    exit;
}

/* ---- POR PAGAR: só imagens, com marca de água (pré-visualização) ---- */
if (!$isImg) {
    _demo_fim(402, 'Disponível após o pagamento.');
}
if (!extension_loaded('gd')) {
    _demo_fim(402, 'Pré-visualização indisponível. Disponível após o pagamento.');
}

$img = null;
switch ($ext) {
    case 'png':  $img = @imagecreatefrompng($abs); break;
    case 'gif':  $img = @imagecreatefromgif($abs); break;
    case 'webp': if (function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($abs); break;
    default:     $img = @imagecreatefromjpeg($abs);
}
if (!$img) _demo_fim(402, 'Disponível após o pagamento.');

$w = imagesx($img); $h = imagesy($img);
imagealphablending($img, true);

/* Véu ténue para assinalar que é uma demonstração. */
$veu = imagecolorallocatealpha($img, 12, 30, 32, 100);
imagefilledrectangle($img, 0, 0, $w, $h, $veu);

/* Marca de água repetida (fonte interna → funciona em qualquer servidor). */
$txt = 'DEMO - CARI TECH GRAPHIC - NAO PAGO';
$fw = imagefontwidth(5) * strlen($txt);
$fh = imagefontheight(5);
$corA = imagecolorallocatealpha($img, 255, 255, 255, 95);
$corB = imagecolorallocatealpha($img, 0, 0, 0, 105);
$stepY = max(46, (int) ($h / 8));
$linha = 0;
for ($y = 10; $y < $h; $y += $stepY) {
    $offset = ($linha % 2) * (int) (($fw + 60) / 2);
    for ($x = -$fw + $offset; $x < $w; $x += $fw + 60) {
        imagestring($img, 5, $x + 1, $y + 1, $txt, $corB);
        imagestring($img, 5, $x, $y, $txt, $corA);
    }
    $linha++;
}

header('Content-Type: image/jpeg');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
imagejpeg($img, null, 82);
imagedestroy($img);
