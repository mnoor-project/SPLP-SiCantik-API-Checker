<?php
/** @var string $mode @var bool $loggedIn @var string $csrf @var bool $sslToggle */
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
$title = (string)cfg('app_title');
$isPublic = $mode === 'public';
$ua = (string)cfg('default_user_agent');
$repo = (string)cfg('repo_url');
$ver = h(APP_VERSION . '-' . (int)@filemtime(__DIR__ . '/../assets/app.js'));
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title) ?></title>
<link rel="stylesheet" href="assets/app.css?v=<?= $ver ?>">
</head>
<body data-mode="<?= h($mode) ?>" data-csrf="<?= h($csrf) ?>" data-ua="<?= h($ua) ?>">
<?php if (!$loggedIn): ?>
<div class="lw"><div class="lb">
  <div class="icon">&#x1F510;</div>
  <h1><?= h($title) ?></h1>
  <p>Masukkan sandi untuk mengakses alat ini</p>
  <div class="le" id="login-err" hidden></div>
  <form id="login-form">
    <input type="password" id="login-pw" placeholder="Masukkan sandi" autocomplete="current-password" autofocus required>
    <button type="submit" class="btn btn-pr">Masuk</button>
  </form>
</div></div>
<?php else: ?>
<div class="topbar">
  <h1>&#x1F50D; <?= h($title) ?></h1>
  <div class="topbar-r">
    <span class="muted">v<?= h(APP_VERSION) ?></span>
    <a href="<?= h($repo) ?>" target="_blank" rel="noopener" class="btn btn-ol btn-sm">GitHub</a>
    <?php if (!$isPublic): ?><a href="?logout" class="btn btn-ol btn-sm">Logout</a><?php endif; ?>
  </div>
</div>

<div class="ctr">
<?php if ($isPublic): ?>
  <div class="notice">
    <strong>&#x1F512; Token Anda tidak disimpan di server ini.</strong>
    Data yang Anda isi hanya diteruskan ke API SPLP lalu dibuang. Tidak ada database dan tidak ada akun.
    Riwayat hanya tersimpan di browser Anda selama tab ini terbuka.
  </div>
<?php endif; ?>

  <div class="tabs">
    <button class="tab active" data-tab="test">&#x1F50D; Test API</button>
    <?php if (!$isPublic): ?><button class="tab" data-tab="presets">&#x1F4BE; Preset</button><?php endif; ?>
    <button class="tab" data-tab="history">&#x1F4CB; Riwayat<?= $isPublic ? ' Sesi' : '' ?></button>
    <button class="tab" data-tab="panduan">&#x1F4D6; Cara Pakai</button>
  </div>

  <div class="tp active" id="panel-test">
<?php if (!$isPublic): ?>
    <div class="card"><h3>&#x1F4C2; Muat Preset</h3>
      <div class="fr"><div class="fg"><select id="ps"><option value="">-- Pilih preset --</option></select></div>
      <button class="btn btn-ol btn-sm" id="btn-load-preset" type="button">Load</button></div>
    </div>
<?php endif; ?>
    <div class="card"><h3>&#x2699;&#xFE0F; Konfigurasi Endpoint</h3>
      <div class="fg"><label for="f-name">Nama Endpoint <span class="opt">(opsional)</span></label>
        <input type="text" id="f-name" maxlength="200" placeholder="Misal: Daftar Proses Perizinan"></div>
      <div class="fg"><label for="f-uuid">API Key Path (UUID)</label>
        <input type="text" id="f-uuid" autocomplete="off" spellcheck="false" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
        <div class="ht">UUID di URL: <?= h(rtrim((string)cfg('base_api_url'), '/')) ?>/<b>{UUID}</b></div></div>
      <div class="fg"><label for="f-bearer">Bearer Token <span class="opt">(header: auth)</span></label>
        <textarea id="f-bearer" rows="2" autocomplete="off" spellcheck="false" placeholder="eyJhbGciOiJIUzI1NiIs... (bisa berbeda tiap endpoint)"></textarea></div>
      <div class="fg"><label for="f-apikey">API Key <span class="opt">(header: apikey)</span></label>
        <textarea id="f-apikey" rows="2" autocomplete="off" spellcheck="false" placeholder="API key dari API Manager instansi Anda"></textarea>
        <div class="ht info">&#x2139;&#xFE0F; Kode ini diterbitkan oleh <b>Komdigi</b> melalui API Manager SPLP, <b>bukan</b> dari dashboard SiCantik. Nilainya berbeda untuk tiap instansi.</div></div>
      <div class="fg"><label for="f-salt">Salt Key</label>
        <input type="text" id="f-salt" autocomplete="off" spellcheck="false" placeholder="Salt key endpoint">
        <div class="ht">Untuk dekripsi AES-256-GCM respons</div></div>
      <details class="adv"><summary>Pengaturan lanjutan</summary>
        <div class="fg"><label for="f-ua">User-Agent</label>
          <input type="text" id="f-ua" maxlength="150" autocomplete="off" placeholder="<?= h($ua) ?>">
          <div class="ht">Kosongkan untuk memakai bawaan. Ubah hanya jika gateway instansi Anda menolak User-Agent bawaan.</div></div>
<?php if ($sslToggle): ?>
        <div class="fg"><label class="chk"><input type="checkbox" id="f-skipssl"> Lewati verifikasi sertifikat SSL (tidak aman)</label>
          <div class="ht">Aktifkan hanya jika sertifikat server tujuan bermasalah.</div></div>
<?php endif; ?>
      </details>
    </div>
    <div class="card"><h3>&#x1F4DD; Query Parameters</h3>
      <p class="howto">Parameter tambahan yang dikirim ke endpoint sebagai <code>?nama=nilai</code>.
        Isi <b>nama parameter</b> di kolom kiri dan <b>nilainya</b> di kolom kanan. Baris yang namanya kosong diabaikan.
        Parameter yang dibutuhkan berbeda tiap endpoint, lihat dokumentasi endpoint di API Manager.</p>
      <div class="chips"><span class="muted">Tambah cepat:</span>
        <button type="button" class="chip" data-qk="instansi_id" data-qph="ID instansi Anda, mis. 123">instansi_id</button>
        <button type="button" class="chip" data-qk="tgl_awal" data-qph="YYYY-MM-DD, mis. 2026-01-01">tgl_awal</button>
        <button type="button" class="chip" data-qk="tgl_akhir" data-qph="YYYY-MM-DD, mis. 2026-12-31">tgl_akhir</button>
        <button type="button" class="chip" data-qk="no_permohonan" data-qph="nomor permohonan yang ingin dilacak">no_permohonan</button>
      </div>
      <div class="qhead"><span>Nama parameter</span><span>Nilai</span><span></span></div>
      <div id="qpc"><div class="qr"><input type="text" placeholder="contoh: instansi_id" class="qk" autocomplete="off" spellcheck="false"><input type="text" placeholder="contoh: 123" class="qv" autocomplete="off" spellcheck="false"><button class="bi" type="button" data-rm title="Hapus baris">&#x2715;</button></div></div>
      <button class="bi add" type="button" id="btn-addqp" title="Tambah parameter">&#xFF0B;</button>
      <p class="ht" style="margin-top:12px">Contoh: <code>instansi_id</code> = <code>123</code>, <code>tgl_awal</code> = <code>2026-01-01</code>, <code>tgl_akhir</code> = <code>2026-12-31</code>
        &rarr; dikirim sebagai <code>?instansi_id=123&amp;tgl_awal=2026-01-01&amp;tgl_akhir=2026-12-31</code></p>
    </div>
    <div class="fa">
      <button class="btn btn-pr" id="btn-test" type="button" style="width:auto">&#x25B6; Test API</button>
<?php if (!$isPublic): ?>
      <button class="btn btn-sc btn-sm" id="btn-save" type="button">&#x1F4BE; Simpan Preset</button>
      <button class="btn btn-ol btn-sm" id="btn-update" type="button">&#x1F4DD; Update Preset</button>
<?php endif; ?>
    </div>

    <div id="results" hidden>
      <div class="card"><h3>&#x1F3E5; Health Check</h3><div class="cg" id="cg"></div></div>
      <div class="card" id="pcard" hidden>
        <div class="row-between"><h3>&#x1F4CA; Data Preview <span id="rcnt" class="accent"></span></h3>
          <div class="fa" style="margin:0"><button class="btn btn-ol btn-sm" type="button" id="btn-copy">Salin JSON</button>
          <button class="btn btn-ol btn-sm" type="button" id="btn-csv">Unduh CSV</button></div></div>
        <div class="tw" id="ptbl"></div>
      </div>
      <div class="card"><h3 class="col" id="col-raw">Raw Response (terenkripsi)</h3><div class="jv" id="rawres" hidden></div></div>
      <div class="card"><h3 class="col" id="col-dec">Full JSON (terdekripsi)</h3><div class="jv" id="decjson" hidden></div></div>
    </div>
  </div>

<?php if (!$isPublic): ?>
  <div class="tp" id="panel-presets">
    <div class="card"><h3>&#x1F4BE; Preset Tersimpan</h3><div id="plist"><p class="muted">Memuat...</p></div>
      <p class="ht" style="margin-top:12px">Preset menyimpan Bearer Token, API Key, dan Salt Key di database server Anda. Pastikan folder <code>data/</code> tidak bisa diakses lewat web.</p></div>
  </div>
<?php endif; ?>

  <div class="tp" id="panel-history">
    <div class="card">
      <div class="row-between"><h3 style="margin:0">&#x1F4CB; Riwayat Test<?= $isPublic ? ' (hanya di browser ini)' : '' ?></h3>
        <button class="btn btn-dg btn-sm" type="button" id="btn-clear-hist">&#x1F5D1; Hapus Semua</button></div>
      <div id="hlist"><p class="muted">Memuat...</p></div>
      <div id="hpager" class="pager"></div>
    </div>
  </div>

  <div class="tp" id="panel-panduan">
    <div class="card">
      <h3>&#x1F4D6; Cara Pakai</h3>
      <p class="muted" style="margin:6px 0 16px">Alat ini menguji API interop SiCantik SPLP dan mendekripsi respons AES-256-GCM-nya.</p>
      <div class="cg">
<?php if (!$isPublic): ?>
        <div class="ci"><span class="ci-i">1&#xFE0F;&#x20E3;</span><span class="ci-l">Pilih Preset</span><span class="ci-d">Pilih preset dari dropdown, atau isi form manual.</span></div>
<?php endif; ?>
        <div class="ci"><span class="ci-i">&#x1F4DD;</span><span class="ci-l">Isi Konfigurasi</span><span class="ci-d">UUID endpoint, Bearer Token, API Key, Salt Key, dan Query Parameters.</span></div>
        <div class="ci"><span class="ci-i">&#x25B6;&#xFE0F;</span><span class="ci-l">Test API</span><span class="ci-d">Klik &quot;Test API&quot; untuk memanggil endpoint.</span></div>
        <div class="ci"><span class="ci-i">&#x1F4CA;</span><span class="ci-l">Lihat Hasil</span><span class="ci-d">Health Check, pratinjau data, respons mentah, dan JSON terdekripsi. Data bisa disalin atau diunduh sebagai CSV.</span></div>
      </div>
    </div>
    <div class="card">
      <h3>&#x1F9FE; Penjelasan Field</h3>
      <div class="tw"><table>
        <thead><tr><th>Field</th><th>Keterangan</th></tr></thead>
        <tbody>
          <tr><td>Nama Endpoint</td><td>Bebas, hanya untuk identitas pada riwayat. Opsional.</td></tr>
          <tr><td>API Key Path (UUID)</td><td>UUID endpoint di URL. Wajib.</td></tr>
          <tr><td>Bearer Token (auth)</td><td>Token untuk header <code>auth</code>. Bisa berbeda tiap endpoint. Wajib.</td></tr>
          <tr><td>API Key (apikey)</td><td>Nilai header <code>apikey</code>. Diterbitkan oleh <b>Komdigi</b> lewat API Manager SPLP (bukan dari dashboard SiCantik) dan berbeda untuk tiap instansi. Wajib.</td></tr>
          <tr><td>Salt Key</td><td>Untuk dekripsi AES-256-GCM pada respons. Wajib.</td></tr>
          <tr><td>Query Parameters</td><td>Pasangan key-value yang menjadi query string, misalnya <code>instansi_id=...</code> atau <code>no_permohonan=...</code>.</td></tr>
          <tr><td>User-Agent</td><td>Opsional. Bawaan: <code><?= h($ua) ?></code>.</td></tr>
        </tbody>
      </table></div>
    </div>
    <div class="card">
      <h3>&#x26A0;&#xFE0F; Catatan</h3>
      <div class="cg">
        <div class="ci"><span class="ci-i">&#x2705;</span><span class="ci-l">Sukses</span><span class="ci-d">HTTP 200, respons terdekripsi, dan jumlah record ditampilkan.</span></div>
        <div class="ci"><span class="ci-i">&#x274C;</span><span class="ci-l">Gagal</span><span class="ci-d">Biasanya non-200 (misalnya 401/403). Periksa API Key, Bearer Token, dan subscription endpoint di API Manager.</span></div>
        <div class="ci"><span class="ci-i">&#x26A0;&#xFE0F;</span><span class="ci-l">Error Umum</span><span class="ci-d">&quot;missing required parameters&quot; berarti parameter query wajib belum diisi.</span></div>
        <div class="ci"><span class="ci-i">&#x1F512;</span><span class="ci-l">Keamanan</span><span class="ci-d"><?= $isPublic
            ? 'Tidak ada yang disimpan di server. Riwayat hanya ada di browser Anda dan hilang saat tab ditutup.'
            : 'Riwayat dan preset disimpan di database SQLite pada server Anda.' ?> Request hanya diteruskan ke <code><?= h(parse_url((string)cfg('base_api_url'), PHP_URL_HOST)) ?></code>.</span></div>
      </div>
    </div>
  </div>

  <footer class="foot">
    <span>MIT &middot; <a href="<?= h($repo) ?>" target="_blank" rel="noopener">github.com/mnoor-project/SPLP-SiCantik-API-Checker</a></span>
<?php if (cfg('show_donation')): ?>
    <a href="#" id="open-donate">&#x2764;&#xFE0F; Dukung pengembangan</a>
<?php endif; ?>
  </footer>
</div>

<div class="mb" id="dmodal" hidden><div class="mdl"><div class="mdh"><h3>&#x1F4CB; Detail Test</h3><button class="mdc" type="button" data-close>&times;</button></div><div class="mdb" id="dbody"></div></div></div>

<?php if (cfg('show_donation')): ?>
<div class="mb" id="donate" hidden><div class="mdl" style="max-width:520px"><div class="mdh"><h3>&#x2764;&#xFE0F; Dukung Pengembangan</h3><button class="mdc" type="button" data-close>&times;</button></div>
  <div class="mdb">
    <p class="muted" style="margin-bottom:14px">Alat ini dikembangkan dan dirawat secara mandiri, gratis, dan terbuka (MIT). Jika bermanfaat, dukungan sukarela Anda membantu waktu dan tenaga untuk perbaikan, fitur baru, dan dokumentasi.</p>
    <div class="cg">
      <div class="ci"><span class="ci-i">&#x1F3E6;</span><span class="ci-l">Bank Jago</span><span class="ci-d"><b id="rek">100891675874</b> a.n. Muhammad Noor <button class="btn btn-ol btn-sm" type="button" id="copy-rek">Salin</button></span></div>
      <div class="ci"><span class="ci-i">&#x1F4AC;</span><span class="ci-l">WhatsApp</span><span class="ci-d"><a href="https://wa.me/6285752735703" target="_blank" rel="noopener">085752735703</a></span></div>
      <div class="ci"><span class="ci-i">&#x1F310;</span><span class="ci-l">Website</span><span class="ci-d"><a href="https://muhammadnoor.com" target="_blank" rel="noopener">muhammadnoor.com</a></span></div>
    </div>
    <p class="ht" style="margin-top:14px">Donasi bersifat sukarela dan tidak wajib. Seluruh fitur tetap dapat digunakan tanpa donasi. Dukungan bersifat pribadi kepada pengembang dan tidak berkaitan dengan layanan, pungutan, atau kewenangan instansi mana pun.</p>
  </div></div></div>
<?php endif; ?>

<div class="lo" id="loading" hidden><div class="bx"><div class="sp" style="width:36px;height:36px;border-width:3px;margin:0 auto"></div><p>Menghubungi API...</p></div></div>
<?php endif; ?>
<script src="assets/app.js?v=<?= $ver ?>"></script>
</body>
</html>
