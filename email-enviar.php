<?php
/**
 * Cari Tech Graphic — Envio de e-mail (mail() nativo ou SMTP via PHPMailer).
 * Partilhado por enviar.php (recibos) e admin-api.php (notificações de entrega/resposta).
 */

function enviar_email($config, $to, $assunto, $corpo, $replyEmail = '', $replyName = '', $html = null) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    if (!empty($config['smtp_enabled'])) {
        return enviar_smtp($config, $to, $assunto, $corpo, $replyEmail, $replyName, $html);
    }
    /* mail() nativo (funciona na Hostinger sem SMTP) */
    $fromEmail = $config['from_email'] ?? ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName  = $config['from_name']  ?? 'Cari Tech Graphic';

    $headers  = 'From: ' . mb_encode_mimeheader($fromName) . " <{$fromEmail}>\r\n";
    if ($replyEmail && filter_var($replyEmail, FILTER_VALIDATE_EMAIL)) {
        $headers .= 'Reply-To: ' . mb_encode_mimeheader($replyName) . " <{$replyEmail}>\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion() . "\r\n";

    $assuntoMime = '=?UTF-8?B?' . base64_encode($assunto) . '?=';

    if ($html !== null && $html !== '') {
        /* multipart/alternative: versão texto + versão HTML (o cliente escolhe). */
        $bound = 'ctg_' . bin2hex(random_bytes(8));
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$bound}\"\r\n";
        $msg  = "--{$bound}\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $msg .= $corpo . "\r\n\r\n";
        $msg .= "--{$bound}\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $msg .= $html . "\r\n\r\n";
        $msg .= "--{$bound}--";
        return @mail($to, $assuntoMime, $msg, $headers, "-f{$fromEmail}");
    }

    $headers .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";
    return @mail($to, $assuntoMime, $corpo, $headers, "-f{$fromEmail}");
}

/* -------------------------------------------------------------------------- */
/* SMTP opcional (requer PHPMailer em vendor/ — ver README)                   */
/* -------------------------------------------------------------------------- */
function enviar_smtp($config, $to, $assunto, $corpo, $replyEmail = '', $replyName = '', $html = null) {
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
        if ($html !== null && $html !== '') {
            $mail->isHTML(true);
            $mail->Body    = $html;
            $mail->AltBody = $corpo;
        } else {
            $mail->Body    = $corpo;
        }
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}
