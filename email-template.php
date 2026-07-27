<?php
/**
 * Cari Tech Graphic — Template HTML do e-mail para o cliente.
 * Tabelas + estilos inline para compatibilidade com clientes de e-mail.
 */

function email_cliente_html($config, $opts) {
    $marca   = htmlspecialchars($config['from_name'] ?? 'Cari Tech Graphic', ENT_QUOTES);
    $site    = rtrim((string) ($config['site_url'] ?? ''), '/');
    $nome    = htmlspecialchars($opts['nome'] ?? '', ENT_QUOTES);
    $servico = htmlspecialchars($opts['servico'] ?? '', ENT_QUOTES);
    $mensagem= nl2br(htmlspecialchars($opts['mensagem'] ?? '', ENT_QUOTES));
    $codigo  = htmlspecialchars($opts['codigo'] ?? '', ENT_QUOTES);
    $email   = htmlspecialchars($opts['email'] ?? '', ENT_QUOTES);
    $whats   = preg_replace('/\D/', '', (string) ($config['whatsapp'] ?? ''));
    $helpMail= htmlspecialchars($config['to_email'] ?? '', ENT_QUOTES);
    $areaUrl = $site !== '' ? ($site . '/entrar.html') : 'entrar.html';

    // Logótipo: usa o do site (se definido e com site_url) senão o wordmark.
    $logoImg = '';
    if ($site !== '' && !empty($opts['logo'])) {
        $logoImg = '<img src="' . $site . '/' . ltrim(htmlspecialchars($opts['logo'], ENT_QUOTES), '/') . '" alt="' . $marca . '" height="40" style="height:40px;width:auto;display:inline-block;border:0;">';
    }
    $header = $logoImg !== '' ? $logoImg :
        '<span style="display:inline-block;vertical-align:middle;width:34px;height:34px;line-height:34px;text-align:center;background:#FF7200;color:#ffffff;border-radius:9px;font-weight:800;font-size:16px;font-family:Arial,sans-serif;">C</span>'
        . '<span style="display:inline-block;vertical-align:middle;margin-left:10px;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:800;color:#025254;letter-spacing:-.3px;">' . $marca . '</span>';

    ob_start(); ?>
<!DOCTYPE html>
<html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light"><title><?= $marca ?></title></head>
<body style="margin:0;padding:0;background:#eef3f3;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef3f3;">
<tr><td align="center" style="padding:28px 14px;">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 20px 50px rgba(2,82,84,.12);">
    <!-- Logo -->
    <tr><td align="center" style="padding:34px 30px 10px;"><?= $header ?></td></tr>
    <!-- Hero -->
    <tr><td align="center" style="padding:14px 30px 0;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:16px;background:#025254;background:linear-gradient(135deg,#036C6F 0%,#025254 55%,#FF7200 160%);">
        <tr><td align="center" style="padding:40px 20px;">
          <div style="width:78px;height:78px;line-height:78px;margin:0 auto;text-align:center;background:#FF7200;border-radius:50%;color:#ffffff;font-size:38px;font-family:Arial,sans-serif;">&#10003;</div>
        </td></tr>
      </table>
    </td></tr>
    <!-- Título -->
    <tr><td align="center" style="padding:30px 40px 6px;font-family:Arial,Helvetica,sans-serif;">
      <h1 style="margin:0;font-size:30px;line-height:1.2;color:#0f1720;font-weight:800;">Recebemos o seu pedido</h1>
    </td></tr>
    <!-- Corpo -->
    <tr><td align="center" style="padding:10px 44px 4px;font-family:Arial,Helvetica,sans-serif;">
      <p style="margin:0;font-size:16px;line-height:1.7;color:#5b6b73;">
        Olá<?= $nome !== '' ? ' ' . $nome : '' ?>, obrigado por escolher a <?= $marca ?>.
        Recebemos o seu pedido<?= $servico !== '' ? ' de <strong style="color:#025254;">' . $servico . '</strong>' : '' ?>
        e vamos entrar em contacto em breve. Na sua <strong>Área de Cliente</strong> acompanha o estado e recebe as entregas.
      </p>
    </td></tr>
    <!-- Botão -->
    <tr><td align="center" style="padding:26px 30px 6px;">
      <table role="presentation" cellpadding="0" cellspacing="0"><tr>
        <td align="center" style="border-radius:999px;background:#FF7200;">
          <a href="<?= $areaUrl ?>" style="display:inline-block;padding:16px 40px;font-family:Arial,Helvetica,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:999px;">Aceder à Área de Cliente</a>
        </td>
      </tr></table>
    </td></tr>
    <!-- Código -->
    <tr><td align="center" style="padding:20px 44px 4px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f8f8;border:1px solid #e4ebec;border-radius:12px;">
        <tr><td align="center" style="padding:16px;font-family:Arial,Helvetica,sans-serif;">
          <div style="font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#5b6b73;font-weight:700;">O seu código de acesso</div>
          <div style="font-size:26px;letter-spacing:6px;font-weight:800;color:#025254;margin-top:6px;font-family:'Courier New',monospace;"><?= $codigo ?></div>
          <div style="font-size:12px;color:#8a999f;margin-top:6px;">E-mail: <?= $email ?></div>
        </td></tr>
      </table>
    </td></tr>
    <?php if (trim(strip_tags($mensagem)) !== ''): ?>
    <tr><td style="padding:16px 44px 0;font-family:Arial,Helvetica,sans-serif;">
      <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#8a999f;font-weight:700;margin-bottom:6px;">Resumo do pedido</div>
      <div style="font-size:14px;line-height:1.6;color:#5b6b73;background:#fafcfc;border-left:3px solid #FF7200;border-radius:8px;padding:12px 14px;"><?= $mensagem ?></div>
    </td></tr>
    <?php endif; ?>
    <!-- Divider -->
    <tr><td style="padding:26px 44px 0;"><div style="height:1px;background:#e4ebec;"></div></td></tr>
    <!-- Ajuda -->
    <tr><td align="center" style="padding:18px 44px 6px;font-family:Arial,Helvetica,sans-serif;">
      <p style="margin:0;font-size:13px;line-height:1.7;color:#8a999f;">
        Tem dúvidas? Responda a este e-mail<?= $helpMail !== '' ? ' ou escreva para <a href="mailto:' . $helpMail . '" style="color:#036C6F;text-decoration:none;font-weight:700;">' . $helpMail . '</a>' : '' ?>.
        <?= $whats !== '' ? '<br>WhatsApp: <a href="https://wa.me/' . $whats . '" style="color:#036C6F;text-decoration:none;font-weight:700;">falar connosco</a>.' : '' ?>
      </p>
    </td></tr>
    <!-- Rodapé -->
    <tr><td align="center" style="padding:22px 40px 34px;font-family:Arial,Helvetica,sans-serif;">
      <p style="margin:0;font-size:11px;line-height:1.7;color:#aab6bb;">
        <?= $marca ?> · Nampula, Moçambique<br>
        Recebeu este e-mail porque fez um pedido no nosso site.<br>
        &copy; <?= date('Y') ?> <?= $marca ?>. Todos os direitos reservados.
      </p>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>
<?php
    return ob_get_clean();
}
