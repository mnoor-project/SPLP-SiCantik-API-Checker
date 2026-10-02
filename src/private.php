<?php
declare(strict_types=1);

/**
 * Mode PRIVATE: login, preset dan riwayat tersimpan di SQLite.
 * File ini TIDAK dimuat pada mode public, sehingga tidak ada database yang dibuat.
 */

function private_boot(): void
{
    $secure = !empty($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_name('splp_checker');
    session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
}

function db(): PDO
{
    static $db = null;
    if ($db) {
        return $db;
    }
    $file = (string)cfg('db_file');
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $db = new PDO('sqlite:' . $file);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA busy_timeout = 5000');
    $db->exec("CREATE TABLE IF NOT EXISTS presets(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,api_key_path TEXT NOT NULL,bearer_token TEXT NOT NULL,apikey_header TEXT NOT NULL,salt_key TEXT NOT NULL,query_params TEXT DEFAULT '{}',user_agent TEXT DEFAULT '',created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS test_history(id INTEGER PRIMARY KEY AUTOINCREMENT,preset_id INTEGER,endpoint_name TEXT,full_url TEXT,query_params TEXT,http_status INTEGER,response_time REAL,is_success INTEGER DEFAULT 0,raw_response TEXT,decrypted_data TEXT,checks_result TEXT,record_count INTEGER DEFAULT 0,error_message TEXT,tested_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS settings(key TEXT PRIMARY KEY,value TEXT NOT NULL)");
    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts(ip TEXT NOT NULL,at INTEGER NOT NULL)");
    if (!get_setting($db, 'password_hash')) {
        init_password($db, $dir);
    }
    return $db;
}

function get_setting(PDO $db, string $key): ?string
{
    $st = $db->prepare('SELECT value FROM settings WHERE key=?');
    $st->execute([$key]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string)$v;
}

function set_setting(PDO $db, string $key, string $value): void
{
    $db->prepare('INSERT INTO settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value')->execute([$key, $value]);
}

/** Sandi awal acak, disimpan di file tersembunyi (bukan di kode). */
function init_password(PDO $db, string $dir): void
{
    $pw = bin2hex(random_bytes(6));
    set_setting($db, 'password_hash', password_hash($pw, PASSWORD_DEFAULT));
    $f = $dir . '/.initial_password';
    @file_put_contents($f, "Sandi awal: $pw\n\nSegera login lalu ganti dengan: php set-password.php \"sandi-baru\"\nSetelah itu hapus file ini.\n");
    @chmod($f, 0600);
}

function is_logged_in(): bool
{
    return !empty($_SESSION['auth']);
}

function try_login(string $password): ?string
{
    $db = db();
    $ip = client_ip();
    $db->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 600]);
    $st = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip=?');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= 5) {
        return 'Terlalu banyak percobaan gagal. Coba lagi dalam 10 menit.';
    }
    $hash = (string)get_setting($db, 'password_hash');
    if ($hash !== '' && password_verify($password, $hash)) {
        session_regenerate_id(true);
        $_SESSION['auth'] = true;
        $db->prepare('DELETE FROM login_attempts WHERE ip=?')->execute([$ip]);
        return null;
    }
    $db->prepare('INSERT INTO login_attempts(ip,at) VALUES(?,?)')->execute([$ip, time()]);
    return 'Sandi salah.';
}

function logout(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function private_save_history(PDO $db, array $post, array $res, array $in): int
{
    $st = $db->prepare('INSERT INTO test_history(preset_id,endpoint_name,full_url,query_params,http_status,response_time,is_success,raw_response,decrypted_data,checks_result,record_count,error_message) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([
        !empty($post['preset_id']) ? (int)$post['preset_id'] : null,
        cut((string)($post['name'] ?? 'Tanpa nama'), 200),
        $res['full_url'] ?? '',
        json_encode($in['query'] ?? []),
        $res['http_status'] ?? 0,
        $res['response_time'] ?? 0,
        !empty($res['is_success']) ? 1 : 0,
        cut((string)($res['raw_response'] ?? ''), 5000),
        cut((string)($res['decrypted_json'] ?? ''), 100000),
        json_encode($res['checks'] ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        $res['record_count'] ?? 0,
        $res['error_message'] ?? null,
    ]);
    return (int)$db->lastInsertId();
}

function preset_fields(array $p): array
{
    return [
        cut(trim((string)($p['name'] ?? '')), 200),
        trim((string)($p['api_key_path'] ?? '')),
        trim((string)($p['bearer_token'] ?? '')),
        trim((string)($p['apikey_header'] ?? '')),
        trim((string)($p['salt_key'] ?? '')),
        (string)($p['query_params'] ?? '{}'),
        cut(trim((string)($p['user_agent'] ?? '')), 150),
    ];
}

/** Tangani aksi POST pada mode private. Selalu mengakhiri dengan json_out(). */
function private_handle(string $action): void
{
    if ($action === 'login') {
        $err = try_login((string)($_POST['password'] ?? ''));
        json_out($err === null ? ['ok' => true] : ['ok' => false, 'error' => $err], $err === null ? 200 : 401);
    }
    if (!is_logged_in()) {
        json_out(['error' => 'Belum login.'], 401);
    }
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
        json_out(['error' => 'Token CSRF tidak valid. Muat ulang halaman.'], 403);
    }
    $db = db();

    switch ($action) {
        case 'test_api':
            [$in, $errors] = normalize_input($_POST);
            if ($errors) {
                json_out(['is_success' => false, 'validation' => $errors], 422);
            }
            $res = run_check($in);
            $res['history_id'] = private_save_history($db, $_POST, $res, $in);
            json_out($res);

        case 'save_preset':
            $f = preset_fields($_POST);
            if ($f[0] === '') {
                json_out(['error' => 'Nama preset wajib diisi.'], 422);
            }
            $db->prepare('INSERT INTO presets(name,api_key_path,bearer_token,apikey_header,salt_key,query_params,user_agent) VALUES(?,?,?,?,?,?,?)')->execute($f);
            json_out(['ok' => true, 'id' => (int)$db->lastInsertId()]);

        case 'update_preset':
            $f = preset_fields($_POST);
            $f[] = (int)($_POST['preset_id'] ?? 0);
            $db->prepare('UPDATE presets SET name=?,api_key_path=?,bearer_token=?,apikey_header=?,salt_key=?,query_params=?,user_agent=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute($f);
            json_out(['ok' => true]);

        case 'delete_preset':
            $db->prepare('DELETE FROM presets WHERE id=?')->execute([(int)($_POST['preset_id'] ?? 0)]);
            json_out(['ok' => true]);

        case 'get_presets':
            json_out($db->query('SELECT * FROM presets ORDER BY updated_at DESC')->fetchAll());

        case 'get_history':
            $pg = max(1, (int)($_POST['page'] ?? 1));
            $lm = 20;
            $of = ($pg - 1) * $lm;
            $tot = (int)$db->query('SELECT COUNT(*) FROM test_history')->fetchColumn();
            $rows = $db->query("SELECT id,preset_id,endpoint_name,full_url,http_status,response_time,is_success,record_count,error_message,tested_at FROM test_history ORDER BY id DESC LIMIT $lm OFFSET $of")->fetchAll();
            json_out(['rows' => $rows, 'total' => $tot, 'page' => $pg, 'pages' => (int)ceil($tot / $lm)]);

        case 'get_history_detail':
            $st = $db->prepare('SELECT * FROM test_history WHERE id=?');
            $st->execute([(int)($_POST['history_id'] ?? 0)]);
            json_out($st->fetch() ?: ['error' => 'Tidak ditemukan']);

        case 'delete_history':
            if (!empty($_POST['history_id'])) {
                $db->prepare('DELETE FROM test_history WHERE id=?')->execute([(int)$_POST['history_id']]);
            }
            if (!empty($_POST['delete_all'])) {
                $db->exec('DELETE FROM test_history');
            }
            json_out(['ok' => true]);
    }
    json_out(['error' => 'Aksi tidak dikenal'], 400);
}
