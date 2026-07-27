<?php
/**
 * Cari Tech Graphic — Camada de armazenamento
 * ---------------------------------------------------------------------------
 * Usa MySQL quando está configurado em config.php (secção 'db'); caso
 * contrário, guarda em ficheiros JSON na pasta dados/. O resto da aplicação
 * (enviar.php, admin-api.php, conteudo.php) usa sempre as funções store_*
 * e não precisa de saber qual o backend em uso.
 *
 * Se o MySQL estiver activado mas a ligação falhar, cai automaticamente para
 * os ficheiros — o site nunca fica em baixo por causa da base de dados.
 */

/* -------------------------------------------------------------------------- */
/* Ligação                                                                    */
/* -------------------------------------------------------------------------- */
function ctg_pdo($config) {
    static $pdo = null;
    static $tentou = false;
    if ($tentou) return $pdo;
    $tentou = true;

    $db = $config['db'] ?? [];
    $dsn = $db['dsn'] ?? '';               // 'dsn' permite override (ex.: testes)
    if ($dsn === '') {
        if (empty($db['enabled']) || empty($db['name'])) return $pdo = null;
        $host = $db['host'] ?? 'localhost';
        $charset = $db['charset'] ?? 'utf8mb4';
        $dsn = "mysql:host={$host};dbname={$db['name']};charset={$charset}";
    }
    try {
        $pdo = new PDO($dsn, $db['user'] ?? null, $db['pass'] ?? null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        ctg_ensure_schema($pdo);
    } catch (\Throwable $e) {
        $pdo = null; // fallback para ficheiros
    }
    return $pdo;
}

function ctg_ensure_schema($pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $suf = $driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $pdo->exec("CREATE TABLE IF NOT EXISTS leads (
        id VARCHAR(32) PRIMARY KEY,
        criado_em DATETIME NOT NULL,
        nome VARCHAR(200), email VARCHAR(200), telefone VARCHAR(80),
        servico VARCHAR(200), mensagem TEXT, ip VARCHAR(64),
        codigo VARCHAR(16),
        status VARCHAR(20) NOT NULL DEFAULT 'new')$suf");
    /* Coluna 'codigo' para instalações antigas (ignora erro se já existir). */
    try { $pdo->exec("ALTER TABLE leads ADD COLUMN codigo VARCHAR(16)"); } catch (\Throwable $e) {}
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_items (
        id VARCHAR(40) PRIMARY KEY,
        tipo VARCHAR(20) NOT NULL,
        pos INT NOT NULL DEFAULT 0,
        dados LONGTEXT NOT NULL)$suf");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        chave VARCHAR(50) PRIMARY KEY,
        valor LONGTEXT)$suf");
}

/* -------------------------------------------------------------------------- */
/* Leads                                                                      */
/* -------------------------------------------------------------------------- */
function store_add_lead($config, $lead) {
    $pdo = ctg_pdo($config);
    if ($pdo) {
        $st = $pdo->prepare("INSERT INTO leads
            (id, criado_em, nome, email, telefone, servico, mensagem, ip, codigo, status)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        return $st->execute([
            $lead['id'], date('Y-m-d H:i:s'), $lead['nome'], $lead['email'],
            $lead['telefone'], $lead['servico'], $lead['mensagem'],
            $lead['ip'], $lead['codigo'] ?? '', $lead['status'] ?? 'new',
        ]);
    }
    return file_add_lead($config, $lead);
}

function store_get_leads($config) {
    $pdo = ctg_pdo($config);
    if ($pdo) {
        $rows = $pdo->query("SELECT * FROM leads ORDER BY criado_em DESC, id DESC")->fetchAll();
        return array_map(function ($r) {
            return [
                'id' => $r['id'],
                'data' => str_replace(' ', 'T', $r['criado_em']),
                'nome' => $r['nome'], 'email' => $r['email'], 'telefone' => $r['telefone'],
                'servico' => $r['servico'], 'mensagem' => $r['mensagem'], 'ip' => $r['ip'],
                'codigo' => $r['codigo'] ?? '',
                'status' => $r['status'],
            ];
        }, $rows);
    }
    $lista = file_get_leads($config);
    usort($lista, fn($a, $b) => strcmp($b['data'] ?? '', $a['data'] ?? ''));
    return $lista;
}

function store_set_lead_status($config, $id, $status) {
    $pdo = ctg_pdo($config);
    if ($pdo) {
        $st = $pdo->prepare("UPDATE leads SET status=? WHERE id=?");
        $st->execute([$status, $id]);
        return $st->rowCount() > 0;
    }
    $lista = file_get_leads($config);
    $ok = false;
    foreach ($lista as &$l) { if (($l['id'] ?? '') === $id) { $l['status'] = $status; $ok = true; break; } }
    unset($l);
    if ($ok) file_put_leads($config, $lista);
    return $ok;
}

function store_delete_lead($config, $id) {
    $pdo = ctg_pdo($config);
    if ($pdo) {
        $st = $pdo->prepare("DELETE FROM leads WHERE id=?");
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }
    $lista = file_get_leads($config);
    $antes = count($lista);
    $lista = array_values(array_filter($lista, fn($l) => ($l['id'] ?? '') !== $id));
    if (count($lista) === $antes) return false;
    file_put_leads($config, $lista);
    return true;
}

/* -------------------------------------------------------------------------- */
/* Conteúdo (Serviços, Portfólio, Testemunhos, Cabeçalhos, Contactos)         */
/* Devolve null nas secções que nunca foram guardadas (para o painel manter   */
/* os valores padrão).                                                        */
/* -------------------------------------------------------------------------- */
function store_get_content($config) {
    $pdo = ctg_pdo($config);
    if ($pdo) {
        $marca = $pdo->query("SELECT valor FROM settings WHERE chave='content_saved'")->fetchColumn();
        if ($marca === false) {
            return ['services' => null, 'portfolio' => null, 'testimonials' => null, 'headings' => null, 'contact' => null];
        }
        $porTipo = function ($tipo) use ($pdo) {
            $st = $pdo->prepare("SELECT dados FROM content_items WHERE tipo=? ORDER BY pos ASC");
            $st->execute([$tipo]);
            return array_map(fn($d) => json_decode($d, true), $st->fetchAll(PDO::FETCH_COLUMN));
        };
        $setting = function ($k) use ($pdo) {
            $st = $pdo->prepare("SELECT valor FROM settings WHERE chave=?");
            $st->execute([$k]);
            $v = $st->fetchColumn();
            return $v !== false ? json_decode($v, true) : null;
        };
        return [
            'services' => $porTipo('service'),
            'portfolio' => $porTipo('portfolio'),
            'testimonials' => $porTipo('testimonial'),
            'headings' => $setting('headings'),
            'contact' => $setting('contact'),
        ];
    }
    return file_get_content($config);
}

function store_save_content($config, $data) {
    $pdo = ctg_pdo($config);
    if ($pdo) {
        $mapa = ['service' => 'services', 'portfolio' => 'portfolio', 'testimonial' => 'testimonials'];
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare("DELETE FROM content_items WHERE tipo=?");
            $ins = $pdo->prepare("INSERT INTO content_items (id, tipo, pos, dados) VALUES (?,?,?,?)");
            foreach ($mapa as $tipo => $chave) {
                $del->execute([$tipo]);
                $itens = is_array($data[$chave] ?? null) ? array_values($data[$chave]) : [];
                foreach ($itens as $i => $item) {
                    $id = (string) ($item['id'] ?? uniqid());
                    $ins->execute([$id, $tipo, $i, json_encode($item, JSON_UNESCAPED_UNICODE)]);
                }
            }
            $up = $pdo->prepare("REPLACE INTO settings (chave, valor) VALUES (?,?)");
            $up->execute(['headings', json_encode($data['headings'] ?? [], JSON_UNESCAPED_UNICODE)]);
            $up->execute(['contact', json_encode($data['contact'] ?? [], JSON_UNESCAPED_UNICODE)]);
            $up->execute(['content_saved', '1']);
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return false;
        }
    }
    return file_save_content($config, $data);
}

/* -------------------------------------------------------------------------- */
/* Definições singulares (JSON) — credenciais de admin e branding do logo     */
/* Guardadas fora do conteúdo público. store_get_content/conteudo.php nunca    */
/* lêem 'admin', por isso as credenciais nunca são expostas.                   */
/* -------------------------------------------------------------------------- */
function _meta_get($config, $chave, $ficheiro) {
    $pdo = ctg_pdo($config);
    if ($pdo) {
        $st = $pdo->prepare("SELECT valor FROM settings WHERE chave=?");
        $st->execute([$chave]);
        $v = $st->fetchColumn();
        return $v !== false ? json_decode($v, true) : null;
    }
    $path = __DIR__ . '/dados/' . $ficheiro;
    if (!file_exists($path)) return null;
    $v = json_decode(@file_get_contents($path), true);
    return is_array($v) ? $v : null;
}
function _meta_set($config, $chave, $ficheiro, $valor) {
    $pdo = ctg_pdo($config);
    if ($pdo) {
        $up = $pdo->prepare("REPLACE INTO settings (chave, valor) VALUES (?,?)");
        return $up->execute([$chave, json_encode($valor, JSON_UNESCAPED_UNICODE)]);
    }
    $path = __DIR__ . '/dados/' . $ficheiro;
    @mkdir(dirname($path), 0755, true);
    return file_put_contents($path, json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

/* Credenciais do administrador (utilizador + hash da palavra-passe). */
function store_get_admin($config)              { return _meta_get($config, 'admin', 'admin.json'); }
function store_set_admin($config, $user, $hash) { return _meta_set($config, 'admin', 'admin.json', ['user' => $user, 'hash' => $hash]); }

/* Branding: logótipo do site (público). */
function store_get_branding($config)   { return _meta_get($config, 'branding', 'branding.json') ?: []; }
function store_set_branding($config, $b) { return _meta_set($config, 'branding', 'branding.json', $b); }

/* Pagamentos: métodos mostrados no checkout (público). */
function store_get_payments($config)   { return _meta_get($config, 'payments', 'payments.json') ?: []; }
function store_set_payments($config, $p) { return _meta_set($config, 'payments', 'payments.json', $p); }

/* Login social: IDs/segredos do Google e Facebook, geridos no painel. */
function store_get_social($config)    { return _meta_get($config, 'social', 'social.json') ?: []; }
function store_set_social($config, $s) { return _meta_set($config, 'social', 'social.json', $s); }

/* Configuração social EFECTIVA: o que está guardado no painel tem prioridade
   sobre os valores padrão do config.php. Usada por auth.php e conteudo.php. */
function ctg_social($config) {
    $s = store_get_social($config);
    return [
        'enabled'             => array_key_exists('enabled', $s) ? (bool) $s['enabled'] : true,
        'google_client_id'    => trim((string) ($s['google_client_id']    ?? ($config['google_client_id']    ?? ''))),
        'facebook_app_id'     => trim((string) ($s['facebook_app_id']      ?? ($config['facebook_app_id']     ?? ''))),
        'facebook_app_secret' => trim((string) ($s['facebook_app_secret']  ?? ($config['facebook_app_secret'] ?? ''))),
        'admin_email'         => strtolower(trim((string) ($s['admin_email'] ?? ($config['admin_email'] ?? '')))),
    ];
}

/* -------------------------------------------------------------------------- */
/* Entregas (Área de Cliente)                                                  */
/* Mapa { leadId: { entregas:[{tipo,url,nome,note}], msg, entregue, data } }.  */
/* Guardado fora do conteúdo público; só é lido por quem tiver e-mail+código.  */
/* -------------------------------------------------------------------------- */
function store_get_deliveries($config)      { return _meta_get($config, 'entregas', 'entregas.json') ?: []; }
function store_set_deliveries($config, $d)  { return _meta_set($config, 'entregas', 'entregas.json', $d); }

/* Guarda/atualiza a entrega de um lead específico. */
function store_set_delivery($config, $leadId, $entrega) {
    $todas = store_get_deliveries($config);
    $todas[$leadId] = $entrega;
    return store_set_deliveries($config, $todas);
}
function store_get_delivery($config, $leadId) {
    $todas = store_get_deliveries($config);
    return $todas[$leadId] ?? null;
}

/* -------------------------------------------------------------------------- */
/* Atualização do site por ZIP                                                */
/* Extrai o ZIP e copia por cima dos ficheiros do site, PRESERVANDO SEMPRE     */
/* config.php, a pasta dados/ (leads e conteúdo) e uploads/ (imagens/logo).    */
/* -------------------------------------------------------------------------- */
function atualizar_por_zip($raiz, $zipTmp) {
    $protegidos = ['config.php'];              // ficheiros que nunca são substituídos
    $pastasProtegidas = ['dados', 'uploads'];  // pastas preservadas por completo

    $zip = new ZipArchive();
    if ($zip->open($zipTmp) !== true) {
        return ['ok' => false, 'message' => 'Não foi possível abrir o ZIP.'];
    }
    $tmp = sys_get_temp_dir() . '/ctg_update_' . bin2hex(random_bytes(4));
    @mkdir($tmp, 0755, true);
    if (!$zip->extractTo($tmp)) {
        $zip->close();
        _rmrf($tmp);
        return ['ok' => false, 'message' => 'Falha ao extrair o ZIP.'];
    }
    $zip->close();

    // Se o ZIP tiver uma única pasta raiz (ex.: "site/…"), usa-a como origem.
    $origem = $tmp;
    $itens = array_values(array_filter(scandir($tmp), fn($x) => $x !== '.' && $x !== '..'));
    if (count($itens) === 1 && is_dir($tmp . '/' . $itens[0])) {
        $origem = $tmp . '/' . $itens[0];
    }

    $count = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($origem, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $rel = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($origem))), '/');
        if ($rel === '') continue;
        $topo = explode('/', $rel)[0];
        if (in_array($rel, $protegidos, true) || in_array($topo, $pastasProtegidas, true)) continue;
        $destino = $raiz . '/' . $rel;
        if ($item->isDir()) {
            @mkdir($destino, 0755, true);
        } else {
            @mkdir(dirname($destino), 0755, true);
            if (@copy($item->getPathname(), $destino)) $count++;
        }
    }
    _rmrf($tmp);
    return ['ok' => true, 'count' => $count];
}

function _rmrf($dir) {
    if (!is_dir($dir)) { @unlink($dir); return; }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($dir);
}

/* -------------------------------------------------------------------------- */
/* Backend de ficheiros (fallback / modo sem base de dados)                   */
/* -------------------------------------------------------------------------- */
function _leads_path($config)  { return $config['leads_json']   ?? (__DIR__ . '/dados/leads.json'); }
function _content_path($config){ return $config['content_json'] ?? (__DIR__ . '/dados/conteudo.json'); }

function file_get_leads($config) {
    $path = _leads_path($config);
    if (!file_exists($path)) return [];
    $l = json_decode(@file_get_contents($path) ?: '[]', true);
    return is_array($l) ? $l : [];
}
function file_put_leads($config, $lista) {
    $path = _leads_path($config);
    @mkdir(dirname($path), 0755, true);
    file_put_contents($path, json_encode(array_values($lista), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}
function file_add_lead($config, $lead) {
    $path = _leads_path($config);
    @mkdir(dirname($path), 0755, true);
    $fh = @fopen($path, 'c+');
    if (!$fh) return false;
    if (flock($fh, LOCK_EX)) {
        $conteudo = stream_get_contents($fh);
        $lista = json_decode($conteudo ?: '[]', true);
        if (!is_array($lista)) $lista = [];
        $lista[] = $lead;
        ftruncate($fh, 0); rewind($fh);
        fwrite($fh, json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        fflush($fh); flock($fh, LOCK_UN);
    }
    fclose($fh);
    return true;
}
function file_get_content($config) {
    $path = _content_path($config);
    if (!file_exists($path)) {
        return ['services' => null, 'portfolio' => null, 'testimonials' => null, 'headings' => null, 'contact' => null];
    }
    $c = json_decode(@file_get_contents($path) ?: '{}', true);
    if (!is_array($c)) $c = [];
    return [
        'services' => $c['services'] ?? null,
        'portfolio' => $c['portfolio'] ?? null,
        'testimonials' => $c['testimonials'] ?? null,
        'headings' => $c['headings'] ?? null,
        'contact' => $c['contact'] ?? null,
    ];
}
function file_save_content($config, $data) {
    $path = _content_path($config);
    @mkdir(dirname($path), 0755, true);
    return file_put_contents($path, json_encode([
        'services' => array_values($data['services'] ?? []),
        'portfolio' => array_values($data['portfolio'] ?? []),
        'testimonials' => array_values($data['testimonials'] ?? []),
        'headings' => $data['headings'] ?? [],
        'contact' => $data['contact'] ?? [],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
}
