(function () {
  'use strict';

  const D = document.body.dataset;
  const IS_PUBLIC = D.mode === 'public';
  const CSRF = D.csrf || '';
  const $ = (id) => document.getElementById(id);
  const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
  const esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, (c) => ESC[c]);

  async function post(action, data) {
    const body = new URLSearchParams();
    body.set('action', action);
    if (CSRF) body.set('csrf', CSRF);
    for (const [k, v] of Object.entries(data || {})) body.set(k, v == null ? '' : v);
    let r;
    try {
      r = await fetch(location.pathname, {
        method: 'POST',
        headers: { 'X-Requested-With': 'splp-checker', 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
      });
    } catch (e) {
      return { error: 'Tidak dapat menghubungi server: ' + e.message };
    }
    let j;
    try { j = await r.json(); } catch (e) { j = { error: 'Respons server tidak valid (HTTP ' + r.status + ')' }; }
    if (r.status === 401 && !IS_PUBLIC && action !== 'login') location.reload();
    return j;
  }

  // ---------- Login (mode private) ----------
  const loginForm = $('login-form');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const res = await post('login', { password: $('login-pw').value });
      if (res.ok) { location.reload(); return; }
      const box = $('login-err');
      box.textContent = res.error || 'Gagal login.';
      box.hidden = false;
    });
    return;
  }
  if (!$('panel-test')) return;

  // ---------- Tab ----------
  let presetsCache = [];
  document.querySelectorAll('.tab').forEach((t) => t.addEventListener('click', () => {
    document.querySelectorAll('.tab').forEach((x) => x.classList.remove('active'));
    document.querySelectorAll('.tp').forEach((x) => x.classList.remove('active'));
    t.classList.add('active');
    $('panel-' + t.dataset.tab).classList.add('active');
    if (t.dataset.tab === 'presets') loadPresets();
    if (t.dataset.tab === 'history') loadHistory(1);
  }));
  const openTab = (name) => document.querySelector('.tab[data-tab="' + name + '"]').click();

  // ---------- Query parameters ----------
  function qpRow(k, v, ph) {
    const d = document.createElement('div');
    d.className = 'qr';
    d.innerHTML = '<input type="text" placeholder="contoh: instansi_id" class="qk" autocomplete="off" spellcheck="false" value="' + esc(k) + '">' +
      '<input type="text" placeholder="' + esc(ph || 'contoh: 123') + '" class="qv" autocomplete="off" spellcheck="false" value="' + esc(v) + '">' +
      '<button class="bi" type="button" data-rm title="Hapus baris">✕</button>';
    return d;
  }
  // Tombol "Tambah cepat": isi baris kosong yang ada, atau tambah baris baru; jangan duplikat nama.
  document.querySelectorAll('.chip').forEach((b) => b.addEventListener('click', () => {
    const key = b.dataset.qk, ph = b.dataset.qph;
    const rows = Array.from(document.querySelectorAll('#qpc .qr'));
    const same = rows.find((r) => r.querySelector('.qk').value.trim() === key);
    if (same) { same.querySelector('.qv').focus(); return; }
    let row = rows.find((r) => !r.querySelector('.qk').value.trim() && !r.querySelector('.qv').value.trim());
    if (!row) { row = qpRow('', '', ph); $('qpc').appendChild(row); }
    row.querySelector('.qk').value = key;
    row.querySelector('.qv').placeholder = ph;
    row.querySelector('.qv').focus();
  }));
  function getQP() {
    const o = {};
    document.querySelectorAll('#qpc .qr').forEach((r) => {
      const k = r.querySelector('.qk').value.trim();
      if (k) o[k] = r.querySelector('.qv').value.trim();
    });
    return o;
  }
  function setQP(obj) {
    const c = $('qpc');
    c.innerHTML = '';
    const e = Object.entries(obj || {});
    if (!e.length) e.push(['', '']);
    e.forEach(([k, v]) => c.appendChild(qpRow(k, v)));
  }
  $('btn-addqp').addEventListener('click', () => $('qpc').appendChild(qpRow('', '')));
  $('qpc').addEventListener('click', (e) => {
    const b = e.target.closest('[data-rm]');
    if (!b) return;
    const c = $('qpc');
    if (c.children.length > 1) b.parentElement.remove();
    else b.parentElement.querySelectorAll('input').forEach((i) => { i.value = ''; });
  });

  // ---------- Form ----------
  function getForm() {
    const skip = $('f-skipssl');
    return {
      name: $('f-name').value.trim(),
      api_key_path: $('f-uuid').value.trim(),
      bearer_token: $('f-bearer').value.trim(),
      apikey_header: $('f-apikey').value.trim(),
      salt_key: $('f-salt').value.trim(),
      query_params: JSON.stringify(getQP()),
      user_agent: $('f-ua').value.trim(),
      skip_ssl_verify: skip && skip.checked ? '1' : '',
    };
  }
  function missingField(f) {
    if (!f.api_key_path) return 'API Key Path (UUID) wajib diisi.';
    if (!f.bearer_token) return 'Bearer Token wajib diisi.';
    if (!f.apikey_header) return 'API Key (header apikey) wajib diisi.';
    if (!f.salt_key) return 'Salt Key wajib diisi.';
    return '';
  }
  const loading = (on) => { $('loading').hidden = !on; $('btn-test').disabled = on; };

  // ---------- Test ----------
  let lastResult = null;
  async function doTest() {
    const f = getForm();
    const miss = missingField(f);
    if (miss) { alert(miss); return; }
    loading(true);
    const extra = IS_PUBLIC ? {} : { preset_id: $('ps') ? $('ps').value : '' };
    const res = await post('test_api', Object.assign(extra, f));
    loading(false);
    if (res.validation) { alert(res.validation.join('\n')); return; }
    if (res.error && res.is_success === undefined) { alert(res.error); return; }
    lastResult = res;
    renderResults(res);
    if (IS_PUBLIC) pushSessionHistory(f.name, res);
  }
  $('btn-test').addEventListener('click', doTest);

  const icon = (s) => (s === 'ok' ? '✅' : s === 'warn' ? '⚠️' : '❌');
  function checksHTML(checks) {
    return (checks || []).map((c) => '<div class="ci"><span class="ci-i">' + icon(c.status) + '</span><span class="ci-l">' +
      esc(c.label) + '</span><span class="ci-d">' + esc(c.detail) + '</span></div>').join('');
  }
  function tableHTML(rows, fields, total) {
    if (!rows.length) return '';
    fields = fields && fields.length ? fields : Object.keys(rows[0]);
    let h = '<table><thead><tr><th>#</th>' + fields.map((f) => '<th>' + esc(f) + '</th>').join('') + '</tr></thead><tbody>';
    rows.forEach((row, i) => {
      h += '<tr><td>' + (i + 1) + '</td>' + fields.map((f) => {
        let v = row[f];
        v = v == null ? '—' : (typeof v === 'object' ? JSON.stringify(v) : String(v));
        return '<td title="' + esc(v) + '">' + esc(v) + '</td>';
      }).join('') + '</tr>';
    });
    if (total > rows.length) {
      h += '<tr><td colspan="' + (fields.length + 1) + '" class="muted" style="text-align:center">... dan ' + (total - rows.length) + ' record lainnya</td></tr>';
    }
    return h + '</tbody></table>';
  }
  function renderResults(res) {
    $('results').hidden = false;
    $('cg').innerHTML = checksHTML(res.checks);
    const prev = res.preview_data || [];
    $('pcard').hidden = prev.length === 0;
    if (prev.length) {
      $('rcnt').textContent = '(' + res.record_count + ' record)';
      $('ptbl').innerHTML = tableHTML(prev, res.all_fields, res.record_count);
    }
    $('rawres').textContent = res.raw_response || '(kosong)';
    let dec = res.decrypted_json || '(kosong)';
    if (res.decrypted_truncated) dec += '\n\n... (dipotong, respons terlalu besar untuk ditampilkan penuh)';
    $('decjson').textContent = dec;
    $('results').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  ['col-raw', 'col-dec'].forEach((id) => $(id).addEventListener('click', function () {
    this.classList.toggle('open');
    this.nextElementSibling.hidden = !this.classList.contains('open');
  }));

  // ---------- Salin & CSV ----------
  async function copyText(t) {
    try { await navigator.clipboard.writeText(t); return true; } catch (e) { /* fallback */ }
    const ta = document.createElement('textarea');
    ta.value = t; document.body.appendChild(ta); ta.select();
    let ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    ta.remove();
    return ok;
  }
  $('btn-copy').addEventListener('click', async () => {
    if (!lastResult) return;
    const ok = await copyText(lastResult.decrypted_json || '');
    alert(ok ? 'JSON disalin.' : 'Gagal menyalin.');
  });
  function csvCell(v) {
    if (v == null) return '';
    if (typeof v === 'object') v = JSON.stringify(v);
    v = String(v);
    if (/^[=+\-@\t\r]/.test(v)) v = "'" + v; // cegah formula injection di Excel
    return '"' + v.replace(/"/g, '""') + '"';
  }
  $('btn-csv').addEventListener('click', () => {
    if (!lastResult || !lastResult.rows || !lastResult.rows.length) { alert('Tidak ada data.'); return; }
    const fields = lastResult.all_fields && lastResult.all_fields.length ? lastResult.all_fields : Object.keys(lastResult.rows[0]);
    const lines = [fields.map(csvCell).join(',')];
    lastResult.rows.forEach((r) => lines.push(fields.map((f) => csvCell(r[f])).join(',')));
    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'hasil-api-' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    if (lastResult.rows_truncated) alert('CSV dibatasi 1000 baris pertama.');
  });

  // ---------- Riwayat ----------
  const HK = 'splp_hist';
  const histLoad = () => { try { return JSON.parse(sessionStorage.getItem(HK) || '[]'); } catch (e) { return []; } };
  const histSave = (a) => { try { sessionStorage.setItem(HK, JSON.stringify(a.slice(0, 20))); } catch (e) { /* abaikan */ } };
  function pushSessionHistory(name, res) {
    const a = histLoad();
    a.unshift({
      id: Date.now(), tested_at: new Date().toLocaleString('id-ID'), endpoint_name: name || 'Tanpa nama',
      full_url: res.full_url, http_status: res.http_status, is_success: res.is_success ? 1 : 0,
      record_count: res.record_count, response_time: res.response_time, checks: res.checks,
      error_message: res.error_message, preview: res.preview_data, fields: res.all_fields,
    });
    histSave(a);
  }

  let histPage = 1;
  async function loadHistory(page) {
    histPage = page || 1;
    let res;
    if (IS_PUBLIC) {
      const a = histLoad();
      res = { rows: a, total: a.length, page: 1, pages: 1 };
    } else {
      res = await post('get_history', { page: histPage });
    }
    renderHistory(res);
  }
  function renderHistory(res) {
    const el = $('hlist'), pg = $('hpager');
    pg.innerHTML = '';
    if (!res.rows || !res.rows.length) { el.innerHTML = '<p class="muted">Belum ada riwayat.</p>'; return; }
    let h = '<div class="tw"><table><thead><tr><th>#</th><th>Waktu</th><th>Endpoint</th><th>Status</th><th>HTTP</th><th>Durasi</th><th>Record</th><th>Aksi</th></tr></thead><tbody>';
    res.rows.forEach((r, i) => {
      const badge = r.is_success ? '<span class="badge b-ok">✅ OK</span>' : '<span class="badge b-er">❌ Gagal</span>';
      const hb = r.http_status == 200 ? '<span class="badge b-ok">200</span>' : '<span class="badge b-er">' + esc(r.http_status || '—') + '</span>';
      h += '<tr><td>' + ((res.page - 1) * 20 + i + 1) + '</td><td>' + esc(r.tested_at) + '</td><td>' + esc(r.endpoint_name) +
        '</td><td>' + badge + '</td><td>' + hb + '</td><td>' + esc(r.response_time) + 's</td><td>' + esc(r.record_count) +
        '</td><td><button class="btn btn-ol btn-sm" type="button" data-act="detail" data-id="' + esc(r.id) + '">Detail</button> ' +
        '<button class="btn btn-dg btn-sm" type="button" data-act="delhist" data-id="' + esc(r.id) + '">Hapus</button></td></tr>';
    });
    el.innerHTML = h + '</tbody></table></div>';
    for (let p = 1; p <= (res.pages || 1) && res.pages > 1; p++) {
      pg.innerHTML += '<button class="btn ' + (p === res.page ? 'btn-pr' : 'btn-ol') + ' btn-sm" type="button" style="width:auto" data-act="page" data-id="' + p + '">' + p + '</button>';
    }
  }
  async function viewDetail(id) {
    let d;
    if (IS_PUBLIC) {
      const r = histLoad().find((x) => String(x.id) === String(id));
      if (!r) { alert('Tidak ditemukan'); return; }
      d = { checks: r.checks, full_url: r.full_url, http: r.http_status, ms: r.response_time, rc: r.record_count, err: r.error_message, rows: r.preview || [], fields: r.fields || [], raw: '', dec: '' };
    } else {
      const r = await post('get_history_detail', { history_id: id });
      if (r.error) { alert(r.error); return; }
      let checks = [];
      try { checks = JSON.parse(r.checks_result || '[]'); } catch (e) { /* abaikan */ }
      let rows = [], dec = r.decrypted_data || '';
      try {
        const j = JSON.parse(dec);
        rows = (j.items || j.data || (Array.isArray(j) ? j : [j])).slice(0, 10);
        dec = JSON.stringify(j, null, 2);
      } catch (e) { /* biarkan apa adanya */ }
      d = { checks, full_url: r.full_url, http: r.http_status, ms: r.response_time, rc: r.record_count, err: r.error_message, rows, fields: [], raw: r.raw_response || '', dec };
    }
    let h = '<div class="cg" style="margin-bottom:16px">' + checksHTML(d.checks) + '</div>' +
      '<p class="muted">URL: ' + esc(d.full_url) + '</p>' +
      '<p class="muted" style="margin-bottom:16px">HTTP ' + esc(d.http) + ' | ' + esc(d.ms) + 's | ' + esc(d.rc) + ' record</p>';
    if (d.err) h += '<div class="errbox">Error: ' + esc(d.err) + '</div>';
    if (d.rows.length && typeof d.rows[0] === 'object') h += '<div class="tw" style="margin-bottom:12px">' + tableHTML(d.rows, d.fields, 0) + '</div>';
    if (d.raw) h += '<h4 class="sub">Raw Response</h4><div class="jv">' + esc(d.raw) + '</div>';
    if (d.dec) h += '<h4 class="sub">Decrypted JSON</h4><div class="jv">' + esc(d.dec) + '</div>';
    $('dbody').innerHTML = h;
    $('dmodal').hidden = false;
  }
  $('hlist').addEventListener('click', async (e) => {
    const b = e.target.closest('[data-act]');
    if (!b) return;
    if (b.dataset.act === 'detail') viewDetail(b.dataset.id);
    if (b.dataset.act === 'delhist') {
      if (!confirm('Hapus riwayat ini?')) return;
      if (IS_PUBLIC) histSave(histLoad().filter((x) => String(x.id) !== String(b.dataset.id)));
      else await post('delete_history', { history_id: b.dataset.id });
      loadHistory(histPage);
    }
  });
  $('hpager').addEventListener('click', (e) => {
    const b = e.target.closest('[data-act="page"]');
    if (b) loadHistory(parseInt(b.dataset.id, 10));
  });
  $('btn-clear-hist').addEventListener('click', async () => {
    if (!confirm('Hapus SEMUA riwayat?')) return;
    if (IS_PUBLIC) histSave([]);
    else await post('delete_history', { delete_all: 1 });
    loadHistory(1);
  });

  // ---------- Preset (mode private) ----------
  async function loadPresets() {
    const res = await post('get_presets');
    presetsCache = Array.isArray(res) ? res : [];
    const sel = $('ps');
    sel.innerHTML = '<option value="">-- Pilih preset --</option>' + presetsCache.map((p) => '<option value="' + esc(p.id) + '">' + esc(p.name) + '</option>').join('');
    const el = $('plist');
    if (!presetsCache.length) { el.innerHTML = '<p class="muted">Belum ada preset.</p>'; return; }
    let h = '<div class="tw"><table><thead><tr><th>Nama</th><th>UUID</th><th>Query</th><th>Diubah</th><th>Aksi</th></tr></thead><tbody>';
    presetsCache.forEach((p) => {
      let qp = {};
      try { qp = JSON.parse(p.query_params || '{}'); } catch (e) { /* abaikan */ }
      const qs = Object.entries(qp).map(([k, v]) => k + '=' + v).join(', ') || '—';
      h += '<tr><td>' + esc(p.name) + '</td><td title="' + esc(p.api_key_path) + '">' + esc(p.api_key_path.substring(0, 18)) + '...</td><td>' + esc(qs) +
        '</td><td>' + esc(p.updated_at) + '</td><td><button class="btn btn-ol btn-sm" type="button" data-act="loadp" data-id="' + esc(p.id) +
        '">Load</button> <button class="btn btn-dg btn-sm" type="button" data-act="delp" data-id="' + esc(p.id) + '">Hapus</button></td></tr>';
    });
    el.innerHTML = h + '</tbody></table></div>';
  }
  function loadPresetById(id) {
    const p = presetsCache.find((x) => String(x.id) === String(id));
    if (!p) return;
    $('f-name').value = p.name;
    $('f-uuid').value = p.api_key_path;
    $('f-bearer').value = p.bearer_token;
    $('f-apikey').value = p.apikey_header;
    $('f-salt').value = p.salt_key;
    $('f-ua').value = p.user_agent || '';
    let qp = {};
    try { qp = JSON.parse(p.query_params || '{}'); } catch (e) { /* abaikan */ }
    setQP(qp);
    $('ps').value = id;
    openTab('test');
  }
  if (!IS_PUBLIC) {
    $('btn-load-preset').addEventListener('click', () => { if ($('ps').value) loadPresetById($('ps').value); });
    $('btn-save').addEventListener('click', async () => {
      const f = getForm();
      if (!f.name) { alert('Nama endpoint wajib diisi untuk menyimpan preset.'); return; }
      const res = await post('save_preset', f);
      if (res.error) { alert(res.error); return; }
      await loadPresets();
      alert('Preset disimpan.');
    });
    $('btn-update').addEventListener('click', async () => {
      const id = $('ps').value;
      if (!id) { alert('Pilih preset dulu.'); return; }
      const f = getForm();
      if (!f.name) { alert('Nama endpoint wajib diisi.'); return; }
      await post('update_preset', Object.assign({ preset_id: id }, f));
      await loadPresets();
      alert('Preset diperbarui.');
    });
    $('plist').addEventListener('click', async (e) => {
      const b = e.target.closest('[data-act]');
      if (!b) return;
      if (b.dataset.act === 'loadp') loadPresetById(b.dataset.id);
      if (b.dataset.act === 'delp') {
        if (!confirm('Hapus preset ini?')) return;
        await post('delete_preset', { preset_id: b.dataset.id });
        loadPresets();
      }
    });
    loadPresets();
  }

  // ---------- Modal & donasi ----------
  document.querySelectorAll('.mb').forEach((m) => {
    m.addEventListener('click', (e) => { if (e.target === m || e.target.closest('[data-close]')) m.hidden = true; });
  });
  const od = $('open-donate');
  if (od) {
    od.addEventListener('click', (e) => { e.preventDefault(); $('donate').hidden = false; });
    $('copy-rek').addEventListener('click', async () => {
      const ok = await copyText($('rek').textContent.trim());
      alert(ok ? 'Nomor rekening disalin.' : 'Gagal menyalin.');
    });
  }
})();
