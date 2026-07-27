<?php
/**
 * Cari Tech Graphic — Conteúdo público (Serviços, Portfólio, Testemunhos…)
 * ---------------------------------------------------------------------------
 * Devolve, em JSON, o conteúdo gerido no painel de administração.
 * É apenas de LEITURA e público (o site usa-o para mostrar o conteúdo).
 * Lê do MySQL ou dos ficheiros, conforme a configuração (ver armazenamento.php).
 * Se ainda não houver conteúdo gerido, devolve vazio e o site usa o i18n.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/armazenamento.php';

$dados = store_get_content($config);
$branding = store_get_branding($config);
$payments = store_get_payments($config);

/* Só expõe ao público os itens marcados como activos/publicados. */
$publicos = fn($lista) => array_values(array_filter(
    is_array($lista) ? $lista : [],
    fn($i) => ($i['status'] ?? 'active') === 'active'
));

/* Objectos vazios quando não há nada guardado (para o site usar os padrões). */
$obj = fn($v) => (is_array($v) && $v) ? $v : new stdClass();

echo json_encode([
    'ok'           => true,
    'services'     => $publicos($dados['services']),
    'portfolio'    => $publicos($dados['portfolio']),
    'testimonials' => $publicos($dados['testimonials']),
    'headings'     => $obj($dados['headings']),
    'contact'      => $obj($dados['contact']),
    'branding'     => $obj($branding),
    'payments'     => $obj($payments),
    // ID de cliente do Google (público, não é segredo) — activa o botão social.
    'google_client_id' => (string) ($config['google_client_id'] ?? ''),
], JSON_UNESCAPED_UNICODE);
