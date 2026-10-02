<?php
declare(strict_types=1);

// Tampung keluaran dini: BOM atau spasi nyasar di config.php tidak boleh merusak respons JSON.
ob_start();

require __DIR__ . '/src/app_config.php';
require __DIR__ . '/src/checker.php';

// Galat dicatat oleh PHP sendiri, tidak pernah ditampilkan. Aplikasi ini tidak mencatat masukan pengguna.
ini_set('display_errors', '0');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");

$public = is_public();
if (!$public) {
    require __DIR__ . '/src/private.php';
    private_boot();
    db(); // pastikan database dan sandi awal dibuat sejak halaman pertama dibuka
    if (isset($_GET['logout'])) {
        logout();
        header('Location: ' . strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?'));
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = (string)$_POST['action'];

    if ($public) {
        // Mode public: hanya satu aksi, tanpa sesi, tanpa database, tanpa penyimpanan.
        if ($action !== 'test_api') {
            json_out(['error' => 'Aksi tidak dikenal'], 400);
        }
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'splp-checker') {
            json_out(['error' => 'Permintaan tidak valid'], 400);
        }
        $origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $hostOnly = explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0];
        if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $hostOnly) {
            json_out(['error' => 'Asal permintaan tidak diizinkan'], 403);
        }
        [$in, $errors] = normalize_input($_POST);
        if ($errors) {
            json_out(['is_success' => false, 'validation' => $errors], 422);
        }
        json_out(run_check($in));
    }

    private_handle($action);
}

$mode = $public ? 'public' : 'private';
$loggedIn = $public || is_logged_in();
$csrf = $public ? '' : (string)($_SESSION['csrf'] ?? '');
$sslToggle = !$public && (bool)cfg('allow_disable_ssl');
require __DIR__ . '/views/page.php';
