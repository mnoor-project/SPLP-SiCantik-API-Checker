# SPLP SiCantik API Checker

Alat web untuk **menguji endpoint API interop SiCantik (SPLP)**: memanggil endpoint, memeriksa kesehatan koneksinya, mendekripsi respons AES-256-GCM, lalu menampilkan datanya.

> Aplikasi pendamping yang dikembangkan secara mandiri. **Bukan produk resmi** SiCantik Cloud maupun SPLP.

## Demo

Coba langsung (mode `public`, tanpa login, tidak ada yang disimpan di server):

**https://sc-api.ptspkotim.my.id/**

Anda perlu mengisi sendiri UUID endpoint, Bearer Token, API Key, dan Salt Key milik instansi Anda. Semuanya hanya diteruskan ke API SPLP lalu dibuang.

## Fitur

- Health check: koneksi, validitas JSON, dekripsi, struktur data, daftar field, field kosong
- Pratinjau data, respons mentah, dan JSON terdekripsi
- Salin JSON dan unduh CSV
- Query parameter bebas, User-Agent bisa diubah
- Percobaan ulang otomatis untuk error sementara gateway (429/5xx)
- Verifikasi sertifikat SSL aktif secara bawaan

## Dua mode

Satu kode, dua cara pakai. Atur di `config.php`:

| | `private` (bawaan) | `public` |
|---|---|---|
| Untuk | Pemakaian pribadi di server sendiri | Alat tes bersama / demo |
| Login | Ya (sandi acak saat pertama kali) | Tidak |
| Database | SQLite: preset dan riwayat | **Tidak ada** |
| Token/Salt | Bisa disimpan sebagai preset | **Tidak pernah disimpan** |
| Riwayat | Di database | Hanya di browser (hilang saat tab ditutup) |

## Isian yang dibutuhkan

| Field | Keterangan |
|---|---|
| API Key Path (UUID) - Template Data | UUID endpoint di URL |
| Bearer Token - Token Key | Dikirim di header `auth` |
| **API Key** | Dikirim di header `apikey`. Diterbitkan oleh **Komdigi** lewat API Manager SPLP, **bukan** dari dashboard SiCantik, dan berbeda untuk tiap instansi |
| Salt Key | Untuk dekripsi AES-256-GCM |
| Query Parameters | Misalnya `instansi_id`, `tgl_awal`, `tgl_akhir`, `no_permohonan` |

Tidak ada kredensial bawaan di repo ini.

## Instalasi

Prasyarat: PHP 8.0+ dengan ekstensi `curl`, `openssl`, `mbstring`, dan (untuk mode private) `pdo_sqlite`.

```bash
git clone https://github.com/mnoor-project/SPLP-SiCantik-API-Checker.git splp-checker
cd splp-checker
cp config.example.php config.php      # lalu sesuaikan, misalnya 'mode' => 'public'
chmod 750 data && chown www-data data  # mode private: folder harus bisa ditulis PHP
```

Coba cepat secara lokal:

```bash
php -S 127.0.0.1:8080
```

### Mode private: sandi

Saat pertama kali dibuka, sandi **acak** dibuat dan disimpan di `data/.initial_password` (izin 0600). Baca, login, lalu ganti:

```bash
cat data/.initial_password
php set-password.php "sandi-baru-anda"   # juga menghapus file sandi awal
```

Peringatan: preset menyimpan token di `data/checker.sqlite` sebagai teks biasa. Pastikan folder `data/` tidak bisa diakses lewat web.

## Keamanan

- **Tujuan dikunci** ke `base_api_url` (bawaan: `api-splp.layanan.go.id`). UUID divalidasi sehingga tidak bisa dipakai untuk menjangkau alamat lain (anti-SSRF), tanpa redirect, hanya HTTPS.
- Respons dibatasi ukurannya (5 MB), timeout 30 detik.
- Header dibersihkan dari karakter kontrol (anti header injection).
- Mode public: tidak ada sesi, cookie, database, atau berkas yang ditulis. Aplikasi tidak mencatat masukan pengguna.
- Mode private: CSRF token, pembatasan percobaan login (5 gagal / 10 menit / IP), cookie `HttpOnly` + `SameSite`.
- Header keamanan (CSP, `X-Frame-Options`, dan lainnya) dikirim otomatis.
- CSV dilindungi dari formula injection.

### Server web

`.htaccess` disertakan untuk Apache. **Nginx tidak membaca `.htaccess`**, jadi gunakan `nginx.example.conf`: berisi pemblokiran `data/`, `src/`, `config.php`, berkas `.sqlite`, serta rate limit untuk request POST. Untuk instance publik sangat disarankan memakai rate limit tersebut agar server Anda tidak dipakai membanjiri API pemerintah.

## Struktur

```
index.php               router dan pengaman
src/app_config.php      konfigurasi dan helper
src/checker.php         validasi input, panggilan API, dekripsi
src/private.php         login, preset, riwayat (hanya mode private)
views/page.php          tampilan
assets/                 CSS dan JavaScript
config.example.php      contoh konfigurasi
set-password.php        ganti sandi (CLI)
nginx.example.conf      contoh Nginx
```

## Dukung Pengembangan ❤️

Alat ini gratis dan dikembangkan mandiri. Jika bermanfaat, dukungan sukarela Anda membantu waktu dan tenaga untuk perbaikan, fitur baru, dan dokumentasi.

| | |
|---|---|
| 🏦 Bank Jago | `100891675874` a.n. Muhammad Noor |
| 💬 WhatsApp | [085752735703](https://wa.me/6285752735703) |
| 🌐 Website | [muhammadnoor.com](https://muhammadnoor.com) |

Donasi bersifat sukarela dan tidak wajib. Seluruh fitur tetap dapat digunakan tanpa donasi. Dukungan bersifat pribadi kepada pengembang dan tidak berkaitan dengan layanan, pungutan, atau kewenangan instansi mana pun.

Proyek terkait: [Sicantik Cloud Dashboard](https://github.com/mnoor-project/sicantik-cloud-dashboard).

## Lisensi

[MIT](LICENSE) © 2026 Muhammad Noor
