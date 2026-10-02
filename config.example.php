<?php
// Salin file ini menjadi config.php lalu sesuaikan. config.php tidak ikut di-commit (.gitignore).
return [
    // 'private' = login + preset & riwayat tersimpan di SQLite (pemakaian pribadi)
    // 'public'  = tanpa login, tanpa database, tidak ada yang disimpan di server (alat tes sekali pakai)
    'mode' => 'private',

    'app_title' => 'SPLP SiCantik API Checker',

    // Tujuan request dikunci ke alamat ini (anti-SSRF). UUID endpoint ditambahkan di belakangnya.
    'base_api_url' => 'https://api-splp.layanan.go.id/api/sicantik/1/interop/',

    // User-Agent bawaan. Pengguna bisa mengubahnya di "Pengaturan lanjutan".
    'default_user_agent' => 'SPLP-SiCantik-API-Checker/1.0',

    // Verifikasi sertifikat SSL server tujuan. Sebaiknya selalu true.
    'verify_ssl' => true,
    // Izinkan checkbox "lewati verifikasi SSL" di UI. Hanya berlaku untuk mode private.
    'allow_disable_ssl' => false,

    // Jumlah percobaan maksimal saat gateway membalas error sementara (mode public dibatasi 2).
    'max_attempts' => 3,
    // Batas ukuran respons dari API (byte).
    'max_response_bytes' => 5 * 1024 * 1024,

    // Hanya mode private: lokasi database SQLite. Pastikan folder ini TIDAK bisa diakses lewat web.
    'db_file' => __DIR__ . '/data/checker.sqlite',

    // Percayai header CF-Connecting-IP untuk IP klien (isi true hanya jika di belakang Cloudflare).
    'trust_cf_ip' => false,

    'repo_url' => 'https://github.com/mnoor-project/SPLP-SiCantik-API-Checker',
    // Tampilkan tautan "Dukung pengembangan" di footer.
    'show_donation' => true,
];
