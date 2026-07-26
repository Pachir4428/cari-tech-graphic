<?php
/**
 * Cari Tech Graphic — Receptor de formulários (Contacto / Orçamento)
 * ---------------------------------------------------------------------------
 * Recebe JSON (ou POST de formulário) do site, valida, guarda uma cópia em
 * CSV e envia o e-mail para o estúdio. Responde sempre em JSON.
 *
 * Compatível com hospedagem partilhada Hostinger (PHP 7.4+). Sem dependências.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/armazenamento.php';

/* -------------------------------------------------------------------------- */
/* Helpers                                                                    */
/* -------------------------------------------------------------------------- */
function responder($ok, $message, $extra = [], $status = 200) {
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function limpar($v) {
    return trim(str_replace(["\r", "\n", "\t"], ' ', (string) $v));
}

/* -------------------------------------------------------------------------- */
/* Método                                                                     */
/* -------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(false, 'Método não permitido.', [], 405);
}

/* Aceita JSON e também POST clássico (application/x-www-form-urlencoded) */
$raw = file_get_contents('php://input');
$dados = json_decode($raw, true);
if (!is_array($dados)) {
    $dados = $_POST;
}

/* -------------------------------------------------------------------------- */
/* Anti-spam: honeypot                                                        */
/* Se o campo oculto "website" vier preenchido, é um bot — fingimos sucesso.  */
/* -------------------------------------------------------------------------- */
if (!empty($dados['website'])) {
    responder(true, 'Mensagem recebida.');
}

/* -------------------------------------------------------------------------- */
/* Validação                                                                  */
/* -------------------------------------------------------------------------- */
$nome     = limpar($dados['name']    ?? '');
$email    = limpar($dados['email']   ?? '');
$telefone = limpar($dados['phone']   ?? '');
$servico  = limpar($dados['service'] ?? '');
$mensagem = trim((string) ($dados['message'] ?? ''));

$erros = [];
if ($nome === '')                                        $erros['name']    = 'Indique o seu nome.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))          $erros['email']   = 'E-mail inválido.';
if (mb_strlen($mensagem) < 10)                           $erros['message'] = 'Escreva pelo menos 10 caracteres.';

if ($erros) {
    responder(false, 'Verifique os campos assinalados.', ['errors' => $erros], 422);
}

/* -------------------------------------------------------------------------- */
/* Rede de segurança: guardar cópia em CSV                                    */
/* -------------------------------------------------------------------------- */
$linha = [
    date('Y-m-d H:i:s'),
    $nome, $email, $telefone, $servico,
    str_replace(["\r", "\n"], ' ', $mensagem),
    $_SERVER['REMOTE_ADDR'] ?? '',
];
$csvPath = $config['log_csv'] ?? (__DIR__ . '/dados/leads.csv');
@mkdir(dirname($csvPath), 0755, true);
if ($fh = @fopen($csvPath, 'a')) {
    if (@filesize($csvPath) === 0) {
        fputcsv($fh, ['data', 'nome', 'email', 'telefone', 'servico', 'mensagem', 'ip']);
    }
    fputcsv($fh, $linha);
    fclose($fh);
}

/* Código de acesso do cliente (para a Área de Cliente / entregas). Curto e legível. */
$codigo = strtoupper(bin2hex(random_bytes(3))); // 6 caracteres hex

/* Grava o lead no armazenamento (MySQL se configurado, senão dados/leads.json).
   É a fonte usada pelo painel admin (com id e estado). */
store_add_lead($config, [
    'id'       => bin2hex(random_bytes(8)),
    'data'     => date('c'),
    'nome'     => $nome,
    'email'    => $email,
    'telefone' => $telefone,
    'servico'  => $servico,
    'mensagem' => $mensagem,
    'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
    'status'   => 'new',
    'codigo'   => $codigo,
]);

/* -------------------------------------------------------------------------- */
/* Montar e enviar o e-mail                                                   */
/* -------------------------------------------------------------------------- */
$assunto = ($config['subject_prefix'] ?? '[Site] Novo contacto')
    . ($servico !== '' ? " — {$servico}" : '');

$corpo  = "Novo contacto pelo website Cari Tech Graphic\n";
$corpo .= "----------------------------------------\n\n";
$corpo .= "Nome:     {$nome}\n";
$corpo .= "E-mail:   {$email}\n";
$corpo .= "Telefone: " . ($telefone !== '' ? $telefone : '—') . "\n";
$corpo .= "Serviço:  " . ($servico  !== '' ? $servico  : '—') . "\n\n";
$corpo .= "Mensagem:\n{$mensagem}\n\n";
$corpo .= "----------------------------------------\n";
$corpo .= "Enviado em " . date('d/m/Y H:i') . " · IP " . ($_SERVER['REMOTE_ADDR'] ?? '?') . "\n";

/* Envia para o estúdio (dono). */
$enviado = enviar_email($config, $config['to_email'], $assunto, $corpo, $email, $nome);

/* Recibo automático ao cliente (confirmação do pedido). Activo por omissão. */
if (($config['send_receipt'] ?? true) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $marca = $config['from_name'] ?? 'Cari Tech Graphic';
    $rAssunto = 'Recebemos o seu pedido — Cari Tech Graphic';
    $rCorpo  = "Olá {$nome},\n\n";
    $rCorpo .= "Recebemos o seu pedido" . ($servico !== '' ? " de {$servico}" : '') . " e vamos entrar em contacto em breve.\n\n";
    $rCorpo .= "Resumo do seu pedido:\n{$mensagem}\n\n";
    $rCorpo .= "-------------------------------------------\n";
    $rCorpo .= "ÁREA DE CLIENTE\n";
    $rCorpo .= "Acompanhe o seu pedido e receba as suas entregas (ficheiros e links) em:\n";
    $rCorpo .= "  " . ($config['site_url'] ?? '') . "/cliente.html\n";
    $rCorpo .= "  E-mail:  {$email}\n";
    $rCorpo .= "  Código:  {$codigo}\n";
    $rCorpo .= "-------------------------------------------\n\n";
    $rCorpo .= "Se tiver dúvidas, responda a este e-mail ou fale connosco pelo WhatsApp " . ($config['whatsapp'] ?? '') . ".\n\n";
    $rCorpo .= "Obrigado por escolher a Cari Tech Graphic.\n";
    enviar_email($config, $email, $rAssunto, $rCorpo, $config['to_email'] ?? '', $marca);
}

/* -------------------------------------------------------------------------- */
/* Resposta                                                                   */
/* Mesmo que o e-mail falhe, a mensagem já está guardada no CSV — por isso    */
/* devolvemos sucesso e oferecemos o WhatsApp como alternativa imediata.      */
/* -------------------------------------------------------------------------- */
if ($enviado) {
    responder(true, 'Mensagem enviada com sucesso. Entraremos em contacto em breve.');
}

responder(true, 'Recebemos o seu pedido. Se preferir uma resposta imediata, fale connosco pelo WhatsApp.', [
    'email_delivered' => false,
    'whatsapp'        => $config['whatsapp'] ?? null,
]);

/* -------------------------------------------------------------------------- */
/* Envio de e-mail — SMTP (se configurado) ou mail() nativo                   */
/* -------------------------------------------------------------------------- */
function enviar_email($config, $to, $assunto, $corpo, $replyEmail = '', $replyName = '') {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (!empty($config['smtp_enabled'])) {
        return enviar_smtp($config, $to, $assunto, $corpo, $replyEmail, $replyName);
    }
    /* mail() nativo (funciona na Hostinger sem SMTP) */
    $fromEmail = $config['from_email'] ?? ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName  = $config['from_name']  ?? 'Cari Tech Graphic';

    $headers  = 'From: ' . mb_encode_mimeheader($fromName) . " <{$fromEmail}>\r\n";
    if ($replyEmail && filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
        $headers .= 'Reply-To: ' . mb_encode_mimeheader($replyName) . " <{$replyEmail}>\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";

    $assuntoMime = '=?UTF-8?B?' . base64_encode($assunto) . '?=';
    return @mail($to, $assuntoMime, $corpo, $headers, "-f{$fromEmail}");
}

/* -------------------------------------------------------------------------- */
/* SMTP opcional (requer PHPMailer em vendor/ — ver README)                   */
/* -------------------------------------------------------------------------- */
function enviar_smtp($config, $to, $assunto, $corpo, $replyEmail = '', $replyName = '') {
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        return false; // PHPMailer não instalado; cai para o CSV/WhatsApp
    }
    require_once $autoload;
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_user'];
        $mail->Password   = $config['smtp_pass'];
        $mail->SMTPSecure = $config['smtp_secure'];
        $mail->Port       = (int) $config['smtp_port'];
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);
        if ($replyEmail && filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyEmail, $replyName);
        }
        $mail->Subject = $assunto;
        $mail->Body    = $corpo;
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}
