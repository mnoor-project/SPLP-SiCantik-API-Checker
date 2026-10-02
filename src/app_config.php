<?php
declare(strict_types=1);

const APP_VERSION = '1.0.0';

function app_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $defaults = [
        'mode' => 'private',
        'app_title' => 'SPLP SiCantik API Checker',
        'base_api_url' => 'https://api-splp.layanan.go.id/api/sicantik/1/interop/',
        'default_user_agent' => 'SPLP-SiCantik-API-Checker/1.0',
        'verify_ssl' => true,
        'allow_disable_ssl' => false,
        'max_attempts' => 3,
        'max_response_bytes' => 5 * 1024 * 1024,
        'db_file' => dirname(__DIR__) . '/data/checker.sqlite',
        'trust_cf_ip' => false,
        'repo_url' => 'https://github.com/mnoor-project/SPLP-SiCantik-API-Checker',
        'show_donation' => true,
    ];
    $file = dirname(__DIR__) . '/config.php';
    $user = is_file($file) ? require $file : [];
    $cfg = array_merge($defaults, is_array($user) ? $user : []);
    if (!in_array($cfg['mode'], ['private', 'public'], true)) {
        $cfg['mode'] = 'private';
    }
    if ($cfg['mode'] === 'public') {
        $cfg['allow_disable_ssl'] = false;
        $cfg['max_attempts'] = min((int)$cfg['max_attempts'], 2);
    }
    $cfg['max_attempts'] = max(1, (int)$cfg['max_attempts']);
    return $cfg;
}

function cfg(string $key)
{
    return app_config()[$key] ?? null;
}

function is_public(): bool
{
    return cfg('mode') === 'public';
}

function client_ip(): string
{
    if (cfg('trust_cf_ip') && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function json_out($data, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean(); // buang keluaran tak sengaja (mis. BOM) sebelum JSON
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}
