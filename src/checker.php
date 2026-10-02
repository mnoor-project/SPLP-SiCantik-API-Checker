<?php
declare(strict_types=1);

/** Potong string berdasarkan byte tanpa merusak karakter UTF-8. */
function cut(string $s, int $bytes): string
{
    return function_exists('mb_strcut') ? mb_strcut($s, 0, $bytes, 'UTF-8') : substr($s, 0, $bytes);
}

/**
 * Validasi dan bersihkan masukan pengguna.
 * @return array{0: array, 1: string[]} [masukan bersih, daftar galat]
 */
function normalize_input(array $p): array
{
    $errors = [];

    $uuid = trim((string)($p['api_key_path'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{5,99}$/', $uuid)) {
        $errors[] = 'API Key Path (UUID) kosong atau formatnya tidak valid.';
    }

    $bearer = preg_replace('/^Bearer\s+/i', '', trim((string)($p['bearer_token'] ?? '')));
    if ($bearer === '') {
        $errors[] = 'Bearer Token wajib diisi.';
    }

    $apikey = trim((string)($p['apikey_header'] ?? ''));
    if ($apikey === '') {
        $errors[] = 'API Key (header apikey) wajib diisi.';
    }

    $salt = trim((string)($p['salt_key'] ?? ''));
    if ($salt === '') {
        $errors[] = 'Salt Key wajib diisi.';
    }

    $ua = trim((string)($p['user_agent'] ?? ''));
    if ($ua === '') {
        $ua = (string)cfg('default_user_agent');
    }
    if (strlen($ua) > 150) {
        $errors[] = 'User-Agent terlalu panjang (maksimal 150 karakter).';
    }

    // Cegah header injection
    foreach (['Bearer Token' => $bearer, 'API Key' => $apikey, 'User-Agent' => $ua] as $label => $v) {
        if (preg_match('/[\x00-\x1F\x7F]/', (string)$v)) {
            $errors[] = "$label mengandung karakter yang tidak diizinkan.";
        }
    }

    $raw = $p['query_params'] ?? '{}';
    $qp = is_array($raw) ? $raw : json_decode((string)$raw, true);
    if (!is_array($qp)) {
        $qp = [];
    }
    if (count($qp) > 20) {
        $errors[] = 'Parameter query maksimal 20 buah.';
        $qp = array_slice($qp, 0, 20, true);
    }
    $clean = [];
    foreach ($qp as $k => $v) {
        $k = (string)$k;
        if (!preg_match('/^[A-Za-z0-9_.\-]{1,60}$/', $k)) {
            $errors[] = 'Nama parameter query tidak valid (hanya huruf, angka, titik, strip, garis bawah).';
            continue;
        }
        if (!is_scalar($v)) {
            continue;
        }
        $v = (string)$v;
        if (strlen($v) > 300) {
            $errors[] = "Nilai parameter \"$k\" terlalu panjang (maksimal 300 karakter).";
            continue;
        }
        $clean[$k] = $v;
    }

    $verify = (bool)cfg('verify_ssl');
    if (cfg('allow_disable_ssl') && !empty($p['skip_ssl_verify']) && $p['skip_ssl_verify'] !== '0') {
        $verify = false;
    }

    return [[
        'uuid' => $uuid, 'bearer' => $bearer, 'apikey' => $apikey, 'salt' => $salt,
        'user_agent' => $ua, 'query' => $clean, 'verify_ssl' => $verify,
    ], $errors];
}

function decrypt_splp(string $hex, string $salt): ?string
{
    $key = hash('sha256', $salt, true);
    $raw = @hex2bin($hex);
    if ($raw === false || strlen($raw) < 28) {
        return null;
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, -16);
    $ct = substr($raw, 12, -16);
    $d = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $d === false ? null : $d;
}

/**
 * Panggil API SPLP lalu periksa respons. Tidak menulis apa pun ke disk atau log.
 * Tujuan dikunci ke base_api_url; UUID divalidasi sehingga tidak bisa keluar dari path itu.
 */
function run_check(array $in): array
{
    $checks = [];
    $r = [
        'is_success' => false, 'checks' => &$checks, 'raw_response' => '', 'decrypted_json' => '',
        'decrypted_truncated' => false, 'record_count' => 0, 'error_message' => '', 'preview_data' => [],
        'rows' => [], 'rows_truncated' => false, 'all_fields' => [], 'http_status' => 0,
        'response_time' => 0, 'full_url' => '',
    ];

    $base = (string)cfg('base_api_url');
    $url = rtrim($base, '/') . '/' . $in['uuid'];
    if ($in['query']) {
        $url .= '?' . http_build_query($in['query']);
    }
    $r['full_url'] = $url;

    $headers = [
        'auth: Bearer ' . $in['bearer'],
        'apikey: ' . $in['apikey'],
        'Accept: */*',
        'User-Agent: ' . $in['user_agent'],
    ];
    $proto = str_starts_with($base, 'https://') ? CURLPROTO_HTTPS : (CURLPROTO_HTTPS | CURLPROTO_HTTP);
    $limit = (int)cfg('max_response_bytes');
    $maxAttempts = (int)cfg('max_attempts');

    $t0 = microtime(true);
    $raw = false;
    $code = 0;
    $cerr = '';
    $tooBig = false;
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $buf = '';
        $tooBig = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => function ($c, $data) use (&$buf, &$tooBig, $limit) {
                if (strlen($buf) + strlen($data) > $limit) {
                    $tooBig = true;
                    return 0;
                }
                $buf .= $data;
                return strlen($data);
            },
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => $proto,
            CURLOPT_SSL_VERIFYPEER => $in['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => $in['verify_ssl'] ? 2 : 0,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
        $raw = $ok === false && !$tooBig ? false : $buf;
        if ($tooBig) {
            break;
        }
        $retryable = ($raw === false) || in_array($code, [0, 408, 425, 429, 500, 502, 503, 504], true);
        if (!$retryable || $attempt === $maxAttempts) {
            break;
        }
        usleep(500000 * $attempt);
    }
    $r['response_time'] = round(microtime(true) - $t0, 3);
    $r['http_status'] = $code;
    $r['raw_response'] = is_string($raw) ? cut($raw, 5000) : '';

    if ($tooBig) {
        $checks[] = ['label' => 'Koneksi API', 'status' => 'error', 'detail' => 'Respons melebihi batas ukuran ' . round($limit / 1048576, 1) . ' MB'];
        $r['error_message'] = 'Respons terlalu besar';
        $r['raw_response'] = '';
        return $r;
    }
    if ($raw === false || $cerr !== '') {
        $checks[] = ['label' => 'Koneksi API', 'status' => 'error', 'detail' => 'cURL: ' . $cerr];
        $r['error_message'] = $cerr !== '' ? $cerr : 'Koneksi gagal';
        return $r;
    }
    $checks[] = ['label' => 'Koneksi API', 'status' => $code === 200 ? 'ok' : 'error', 'detail' => "HTTP $code - {$r['response_time']}s"];

    if ($code !== 200) {
        $err = "HTTP $code";
        $jb = json_decode($raw, true);
        if (is_array($jb)) {
            $m = $jb['message'] ?? $jb['description'] ?? $jb['error'] ?? '';
            if (is_string($m) && $m !== '') {
                $err .= ' - ' . $m;
            }
            if (isset($jb['code']) && is_scalar($jb['code'])) {
                $err .= ' [code ' . $jb['code'] . ']';
            }
        }
        if ($code === 401 || $code === 403) {
            $err .= '  (Periksa API Key dan Bearer Token, serta pastikan aplikasi Anda sudah di-subscribe ke endpoint ini di API Manager)';
        }
        $r['error_message'] = $err;
        $checks[] = ['label' => 'Response', 'status' => 'error', 'detail' => cut($raw, 300)];
        return $r;
    }

    $json = json_decode($raw, true);
    if (!$json) {
        $checks[] = ['label' => 'Response Valid', 'status' => 'error', 'detail' => 'Bukan JSON valid'];
        $r['error_message'] = 'Invalid JSON';
        return $r;
    }
    $checks[] = ['label' => 'Response Valid', 'status' => 'ok', 'detail' => 'JSON parsed OK'];

    $enc = $json['data'] ?? null;
    if (!$enc || !is_string($enc)) {
        $checks[] = ['label' => 'Dekripsi', 'status' => 'error', 'detail' => 'Field data tidak ditemukan'];
        $r['error_message'] = 'No encrypted data';
        return $r;
    }
    $dec = decrypt_splp($enc, $in['salt']);
    if ($dec === null) {
        $checks[] = ['label' => 'Dekripsi', 'status' => 'error', 'detail' => 'AES-256-GCM gagal - cek Salt Key'];
        $r['error_message'] = 'Decryption failed';
        return $r;
    }
    $checks[] = ['label' => 'Dekripsi', 'status' => 'ok', 'detail' => 'AES-256-GCM OK - ' . strlen($dec) . ' bytes'];

    $dj = json_decode($dec, true);
    if (!$dj) {
        $checks[] = ['label' => 'Struktur Data', 'status' => 'error', 'detail' => 'Hasil dekripsi bukan JSON'];
        $r['error_message'] = 'Bad JSON';
        $r['decrypted_json'] = cut($dec, 2000);
        return $r;
    }
    $pretty = json_encode($dj, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $pretty = $pretty === false ? $dec : $pretty;
    if (strlen($pretty) > 200000) {
        $r['decrypted_truncated'] = true;
    }
    $r['decrypted_json'] = cut($pretty, 200000);

    if (isset($dj['items']) && is_array($dj['items'])) {
        $arr = $dj['items'];
        $dp = 'items';
    } elseif (isset($dj['data']) && is_array($dj['data'])) {
        $arr = $dj['data'];
        $dp = 'data';
    } elseif (isset($dj[0])) {
        $arr = $dj;
        $dp = 'root[]';
    } else {
        $arr = [$dj];
        $dp = 'root{}';
    }
    $cnt = count($arr);
    $r['record_count'] = $cnt;
    $checks[] = ['label' => 'Struktur Data', 'status' => 'ok', 'detail' => "$dp - $cnt record"];

    if ($cnt > 0) {
        $af = [];
        $ec = [];
        foreach ($arr as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as $k => $v) {
                $af[$k] = 1;
                if ($v === null || $v === '') {
                    $ec[$k] = ($ec[$k] ?? 0) + 1;
                }
            }
        }
        $fn = array_keys($af);
        $r['all_fields'] = $fn;
        $checks[] = ['label' => 'Field', 'status' => 'ok', 'detail' => count($fn) . ' field: ' . implode(', ', $fn)];
        if ($ec) {
            $ei = [];
            foreach ($ec as $f2 => $c) {
                $ei[] = "$f2($c)";
            }
            $checks[] = ['label' => 'Field Kosong', 'status' => 'warn', 'detail' => implode(', ', $ei)];
        }
        $r['preview_data'] = array_slice($arr, 0, 5);
        $r['rows'] = array_slice($arr, 0, 1000);
        $r['rows_truncated'] = $cnt > 1000;
    }
    $r['is_success'] = true;
    return $r;
}
