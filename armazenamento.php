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
        status VARCHAR(20) NOT NULL DEFAULT 'new')$suf");
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
            (id, criado_em, nome, email, telefone, servico, mensagem, ip, status)
            VALUES (?,?,?,?,?,?,?,?,?)");
        return $st->execute([
            $lead['id'], date('Y-m-d H:i:s'), $lead['nome'], $lead['email'],
            $lead['telefone'], $lead['servico'], $lead['mensagem'],
            $lead['ip'], $lead['status'] ?? 'new',
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
