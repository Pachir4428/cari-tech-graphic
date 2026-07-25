<?php
/**
 * Cari Tech Graphic — Conteúdo público (Serviços e Portfólio)
 * ---------------------------------------------------------------------------
 * Devolve, em JSON, os serviços e projectos geridos no painel de administração.
 * É apenas de LEITURA e público (o site usa-o para mostrar o conteúdo).
 * Se ainda não houver conteúdo gerido, devolve arrays vazios e o site usa os
 * textos padrão do i18n.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
header('Access-Control-Allow-Origin: *');

$config = require __DIR__ . '/config.php';
$path = $config['content_json'] ?? (__DIR__ . '/dados/conteudo.json');

$dados = ['services' => [], 'portfolio' => [], 'headings' => new stdClass(), 'contact' => new stdClass()];
if (file_exists($path)) {
    $lido = json_decode(@file_get_contents($path) ?: '{}', true);
    if (is_array($lido)) {
        $dados['services']  = $lido['services']  ?? [];
        $dados['portfolio'] = $lido['portfolio'] ?? [];
        $dados['headings']  = $lido['headings']  ?? new stdClass();
        $dados['contact']   = $lido['contact']   ?? new stdClass();
    }
}

/* Só expõe ao público os itens marcados como activos/publicados. */
$publicos = fn($lista) => array_values(array_filter(
    is_array($lista) ? $lista : [],
    fn($i) => ($i['status'] ?? 'active') === 'active'
));

echo json_encode([
    'ok'        => true,
    'services'  => $publicos($dados['services']),
    'portfolio' => $publicos($dados['portfolio']),
    'headings'  => $dados['headings'],
    'contact'   => $dados['contact'],
], JSON_UNESCAPED_UNICODE);
