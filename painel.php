<?php
/**
 * Cari Tech Graphic — Porta de entrada do painel de administração.
 * ---------------------------------------------------------------------------
 * Serve o painel (admin.html) apenas quando o "slug" secreto está correcto.
 * O acesso directo a admin.html é bloqueado pelo .htaccess — assim o endereço
 * real do painel fica oculto (dificulta ataques automáticos).
 *
 *   - Sem slug definido  → painel.php serve o painel (compatível com o padrão).
 *   - Com slug definido   → é preciso painel.php?k=SLUG; caso contrário, 404.
 *
 * O administrador define/gera o slug no painel (Definições → Segurança). Depois
 * de entrar em entrar.html, o servidor encaminha automaticamente para o URL
 * correcto — não é preciso decorar o slug.
 */
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/armazenamento.php';

$slug = ctg_admin_slug($config);
$k = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['k'] ?? ''));

if ($slug !== '' && !hash_equals($slug, $k)) {
    /* Página 404 genérica — não revela que aqui existe um painel. */
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><html><head><meta charset=\"utf-8\"><title>404 Not Found</title></head>"
       . "<body style=\"font-family:sans-serif;text-align:center;padding:60px\"><h1>404</h1>"
       . "<p>A página que procura não existe.</p><p><a href=\"index.html\">Voltar ao início</a></p></body></html>";
    exit;
}

/* Serve o painel a partir do ficheiro (o .htaccess bloqueia o acesso directo). */
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
readfile(__DIR__ . '/admin.html');
