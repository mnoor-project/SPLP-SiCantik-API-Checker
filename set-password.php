<?php
// Ganti sandi login (mode private). Jalankan dari terminal:
//   php set-password.php "sandi-baru"     atau     php set-password.php   (akan diminta mengetik)
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/src/app_config.php';
require __DIR__ . '/src/checker.php';
require __DIR__ . '/src/private.php';

if (is_public()) {
    fwrite(STDERR, "Mode public tidak memakai login.\n");
    exit(1);
}

$pw = $argv[1] ?? '';
if ($pw === '') {
    fwrite(STDOUT, 'Sandi baru (minimal 8 karakter): ');
    $pw = trim((string)fgets(STDIN));
}
if (strlen($pw) < 8) {
    fwrite(STDERR, "Sandi minimal 8 karakter.\n");
    exit(1);
}

$db = db();
set_setting($db, 'password_hash', password_hash($pw, PASSWORD_DEFAULT));
$initial = dirname((string)cfg('db_file')) . '/.initial_password';
if (is_file($initial)) {
    unlink($initial);
}
echo "Sandi diperbarui.\n";
