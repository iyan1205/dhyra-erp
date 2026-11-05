/* ============================================================
 *  Dhyra — aset bersama: logo, ikon modul, sesi & pemanggil API
 *  Dipakai oleh beranda.html, pengguna.html, kasir.html, inventaris.html, akuntansi.html
 * ============================================================ */
(function (W) {
  'use strict';
  var C = { toska: '#10A391', amber: '#F5B83D', biru: '#3B5BDB', koral: '#EF5B5B', tinta: '#14212B', abu: '#7A868D' };

  function mark(size, mono) {
    var t = mono || C.toska, b = mono || C.biru, a = mono || C.amber, k = mono || C.koral;
    return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 120 120" aria-hidden="true" style="display:block;flex:none">' +
      '<rect x="12" y="12" width="44" height="44" rx="12" fill="' + t + '"/><rect x="12" y="64" width="44" height="44" rx="12" fill="' + b + '"/>' +
      '<path d="M76 12 H74 a34 34 0 0 1 34 34 V74 a34 34 0 0 1 -34 34 H76 a12 12 0 0 1 -12 -12 V24 a12 12 0 0 1 12 -12 Z" fill="' + a + '"/>' +
      '<rect x="78" y="52" width="16" height="16" rx="4" fill="' + k + '" transform="rotate(45 86 60)"/></svg>';
  }
  /** Logo lengkap: ikon + tulisan "dhyra." (d toska, hyra abu, titik koral). dark=true untuk latar gelap. */
  function logo(size, dark) {
    var fs = Math.round(size * 0.86), s = Math.max(4, Math.round(fs * 0.21));
    return '<span class="dh-logo" role="img" aria-label="Dhyra" style="display:inline-flex;align-items:center;gap:' + Math.round(size * 0.18) + 'px">' + mark(size) +
      '<span style="display:inline-flex;align-items:flex-end;font-family:\'Plus Jakarta Sans\',system-ui,sans-serif;font-weight:800;font-size:' + fs + 'px;letter-spacing:-.045em;line-height:1">' +
      '<span style="color:' + (dark ? '#2FC6B3' : C.toska) + '">d</span><span style="color:' + (dark ? '#C9D1D5' : C.abu) + '">hyra</span>' +
      '<span style="display:inline-block;width:' + s + 'px;height:' + s + 'px;border-radius:' + Math.round(s * .25) + 'px;background:' + C.koral + ';transform:rotate(45deg);margin:0 0 ' + Math.round(s * .15) + 'px ' + Math.round(s * .55) + 'px"></span></span></span>';
  }

  /* Ikon modul — gaya ubin Dhyra */
  var ICON = {
    kasir: '<rect x="30" y="12" width="60" height="58" rx="10" fill="' + C.amber + '"/><rect x="42" y="26" width="36" height="7" rx="3.5" fill="#fff" opacity=".85"/><rect x="42" y="40" width="24" height="7" rx="3.5" fill="#fff" opacity=".85"/>' +
      '<rect x="12" y="56" width="96" height="52" rx="16" fill="' + C.toska + '"/><rect x="52" y="74" width="16" height="16" rx="4" fill="' + C.koral + '" transform="rotate(45 60 82)"/>',
    inventaris: '<rect x="39" y="12" width="42" height="42" rx="11" fill="' + C.toska + '"/><rect x="14" y="62" width="44" height="44" rx="11" fill="' + C.amber + '"/><rect x="62" y="62" width="44" height="44" rx="11" fill="' + C.biru + '"/>' +
      '<rect x="51" y="25" width="18" height="6" rx="3" fill="#fff" opacity=".85"/><rect x="26" y="76" width="20" height="6" rx="3" fill="#fff" opacity=".85"/><rect x="74" y="76" width="20" height="6" rx="3" fill="#fff" opacity=".85"/>',
    akuntansi: '<rect x="14" y="58" width="26" height="48" rx="9" fill="' + C.amber + '"/><rect x="47" y="38" width="26" height="68" rx="9" fill="' + C.toska + '"/><rect x="80" y="20" width="26" height="86" rx="9" fill="' + C.biru + '"/>' +
      '<rect x="20" y="22" width="18" height="18" rx="5" fill="' + C.koral + '" transform="rotate(45 29 31)"/>',
    pengguna: '<circle cx="44" cy="38" r="17" fill="' + C.toska + '"/><rect x="16" y="62" width="56" height="44" rx="22" fill="' + C.toska + '"/>' +
      '<circle cx="82" cy="44" r="14" fill="' + C.amber + '"/><rect x="60" y="66" width="46" height="40" rx="20" fill="' + C.amber + '"/><rect x="78" y="14" width="18" height="18" rx="5" fill="' + C.koral + '" transform="rotate(45 87 23)"/>',
    member: '<path d="M60 104 C24 80 12 62 12 44 a24 24 0 0 1 48 -6 a24 24 0 0 1 48 6 c0 18 -12 36 -48 60z" fill="' + C.koral + '"/><rect x="50" y="44" width="20" height="20" rx="5" fill="#fff" opacity=".85" transform="rotate(45 60 54)"/>',
    promo: '<path d="M16 22 a8 8 0 0 1 8 -8 h38 l46 46 a8 8 0 0 1 0 11 l-37 37 a8 8 0 0 1 -11 0 l-44 -44z" fill="' + C.amber + '"/><circle cx="40" cy="40" r="9" fill="#fff"/><rect x="58" y="56" width="22" height="22" rx="6" fill="' + C.koral + '" transform="rotate(45 69 67)"/>',
    pembelian: '<rect x="16" y="40" width="88" height="66" rx="16" fill="' + C.biru + '"/><path d="M40 44 v-8 a20 20 0 0 1 40 0 v8" fill="none" stroke="' + C.toska + '" stroke-width="10" stroke-linecap="round"/><rect x="52" y="64" width="16" height="16" rx="4" fill="' + C.amber + '" transform="rotate(45 60 72)"/>',
    dashboard: '<rect x="12" y="12" width="44" height="58" rx="12" fill="' + C.toska + '"/><rect x="64" y="12" width="44" height="34" rx="12" fill="' + C.amber + '"/><rect x="64" y="54" width="44" height="54" rx="12" fill="' + C.biru + '"/><rect x="12" y="78" width="44" height="30" rx="12" fill="' + C.koral + '"/>',
    laporan: '<rect x="18" y="10" width="72" height="96" rx="14" fill="' + C.biru + '"/><rect x="30" y="26" width="40" height="7" rx="3.5" fill="#fff" opacity=".85"/><rect x="30" y="40" width="28" height="7" rx="3.5" fill="#fff" opacity=".6"/>' +
      '<rect x="30" y="74" width="10" height="18" rx="4" fill="' + C.amber + '"/><rect x="45" y="64" width="10" height="28" rx="4" fill="' + C.toska + '"/><rect x="60" y="56" width="10" height="36" rx="4" fill="#fff" opacity=".9"/><rect x="84" y="78" width="22" height="22" rx="6" fill="' + C.koral + '" transform="rotate(45 95 89)"/>',
    pengaturan: '<rect x="14" y="20" width="92" height="16" rx="8" fill="' + C.toska + '"/><rect x="14" y="52" width="92" height="16" rx="8" fill="' + C.amber + '"/><rect x="14" y="84" width="92" height="16" rx="8" fill="' + C.biru + '"/>' +
      '<circle cx="78" cy="28" r="13" fill="#fff" stroke="' + C.toska + '" stroke-width="6"/><circle cx="40" cy="60" r="13" fill="#fff" stroke="' + C.amber + '" stroke-width="6"/><rect x="62" y="80" width="24" height="24" rx="6" fill="' + C.koral + '" transform="rotate(45 74 92)"/>',
    qris: '<rect x="12" y="12" width="40" height="40" rx="10" fill="' + C.toska + '"/><rect x="68" y="12" width="40" height="40" rx="10" fill="' + C.biru + '"/><rect x="12" y="68" width="40" height="40" rx="10" fill="' + C.amber + '"/>' +
      '<rect x="24" y="24" width="16" height="16" rx="4" fill="#fff"/><rect x="80" y="24" width="16" height="16" rx="4" fill="#fff"/><rect x="24" y="80" width="16" height="16" rx="4" fill="#fff"/><rect x="70" y="70" width="14" height="14" rx="4" fill="' + C.toska + '"/><rect x="92" y="92" width="14" height="14" rx="4" fill="' + C.biru + '"/><rect x="82" y="80" width="16" height="16" rx="4" fill="' + C.koral + '" transform="rotate(45 90 88)"/>',
    wa: '<path d="M60 12c27 0 46 18 46 42s-19 42-46 42c-7 0-14-1-20-4L16 102l7-20C16 74 14 65 14 54 14 30 33 12 60 12z" fill="' + C.toska + '"/><rect x="38" y="38" width="44" height="8" rx="4" fill="#fff" opacity=".9"/><rect x="38" y="52" width="30" height="8" rx="4" fill="#fff" opacity=".7"/><rect x="74" y="62" width="18" height="18" rx="5" fill="' + C.amber + '" transform="rotate(45 83 71)"/>',
    online: '<rect x="10" y="16" width="100" height="78" rx="14" fill="' + C.biru + '"/><rect x="10" y="16" width="100" height="18" rx="9" fill="' + C.toska + '"/><circle cx="22" cy="25" r="4" fill="#fff"/><circle cx="34" cy="25" r="4" fill="#fff"/>' +
      '<path d="M40 50h8l6 24h22l6-18H52" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="58" cy="84" r="4" fill="#fff"/><circle cx="74" cy="84" r="4" fill="#fff"/><rect x="88" y="90" width="20" height="20" rx="5" fill="' + C.amber + '" transform="rotate(45 98 100)"/>',
    cabang: '<path d="M10 52l24-20 24 20v50H10z" fill="' + C.toska + '"/><path d="M62 40l24-22 24 22v62H62z" fill="' + C.biru + '"/><rect x="24" y="74" width="20" height="28" rx="5" fill="#fff" opacity=".85"/><rect x="76" y="70" width="20" height="32" rx="5" fill="#fff" opacity=".85"/>' +
      '<rect x="20" y="104" width="80" height="8" rx="4" fill="' + C.amber + '"/><rect x="50" y="10" width="18" height="18" rx="5" fill="' + C.koral + '" transform="rotate(45 59 19)"/>',
    hp: '<rect x="30" y="8" width="60" height="104" rx="16" fill="' + C.abu + '"/><rect x="37" y="20" width="46" height="74" rx="8" fill="' + C.toska + '"/><rect x="44" y="30" width="32" height="7" rx="3.5" fill="#fff" opacity=".85"/><rect x="44" y="44" width="20" height="7" rx="3.5" fill="#fff" opacity=".6"/>' +
      '<rect x="52" y="100" width="16" height="5" rx="2.5" fill="#fff" opacity=".7"/><rect x="62" y="62" width="20" height="20" rx="5" fill="' + C.amber + '" transform="rotate(45 72 72)"/>'
  };
  ICON.aset = '<rect x="12" y="40" width="96" height="66" rx="14" fill="' + C.biru + '"/><rect x="24" y="14" width="72" height="34" rx="10" fill="' + C.amber + '"/><rect x="36" y="24" width="48" height="8" rx="4" fill="#fff" opacity=".85"/>' +
      '<rect x="24" y="58" width="30" height="34" rx="7" fill="#fff" opacity=".9"/><rect x="62" y="58" width="34" height="8" rx="4" fill="#fff" opacity=".7"/><rect x="62" y="72" width="22" height="8" rx="4" fill="#fff" opacity=".5"/><rect x="88" y="86" width="20" height="20" rx="5" fill="' + C.koral + '" transform="rotate(45 98 96)"/>';
  ICON.sdm = '<rect x="14" y="16" width="92" height="88" rx="16" fill="' + C.toska + '"/><circle cx="60" cy="48" r="15" fill="#fff"/><rect x="34" y="68" width="52" height="24" rx="12" fill="#fff" opacity=".9"/>' +
      '<rect x="74" y="8" width="30" height="30" rx="9" fill="' + C.amber + '"/><path d="M82 23l5 5 9-10" fill="none" stroke="#fff" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>';
  ICON.kpi = '<circle cx="60" cy="62" r="46" fill="' + C.amber + '"/><circle cx="60" cy="62" r="31" fill="#fff"/><circle cx="60" cy="62" r="17" fill="' + C.koral + '"/><circle cx="60" cy="62" r="6" fill="#fff"/>' +
      '<path d="M62 60 L100 22" stroke="' + C.tinta + '" stroke-width="7" stroke-linecap="round"/><path d="M92 14 L108 14 L108 30" fill="none" stroke="' + C.toska + '" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>';
  ICON.langganan = '<rect x="10" y="24" width="100" height="72" rx="14" fill="' + C.biru + '"/><rect x="10" y="40" width="100" height="14" fill="' + C.tinta + '" opacity=".55"/><rect x="22" y="68" width="34" height="10" rx="5" fill="#fff" opacity=".9"/>' +
      '<circle cx="86" cy="76" r="20" fill="' + C.amber + '"/><path d="M78 76l6 6 11-12" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>';
  ICON.marketplace = '<path d="M14 40 L24 14 H96 L106 40 Z" fill="' + C.koral + '"/><rect x="14" y="40" width="92" height="66" rx="12" fill="' + C.amber + '"/><rect x="44" y="64" width="32" height="42" rx="6" fill="#fff" opacity=".9"/><rect x="24" y="52" width="14" height="14" rx="4" fill="#fff" opacity=".7"/><rect x="82" y="52" width="14" height="14" rx="4" fill="#fff" opacity=".7"/>';
  ICON.pajak = '<rect x="20" y="10" width="80" height="100" rx="14" fill="' + C.toska + '"/><text x="60" y="72" font-family="Arial" font-weight="800" font-size="40" fill="#fff" text-anchor="middle">%</text><rect x="34" y="88" width="52" height="8" rx="4" fill="#fff" opacity=".7"/><rect x="80" y="4" width="24" height="24" rx="6" fill="' + C.amber + '" transform="rotate(45 92 16)"/>';
  function icon(name, size) { return '<svg width="' + size + '" height="' + size + '" viewBox="0 0 120 120" aria-hidden="true" style="display:block;flex:none">' + (ICON[name] || '') + '</svg>'; }

  /* ak = kunci hak akses (bila berbeda dari id); admin = khusus Administrator */
  var MODULES = [
    { id: 'dashboard', ak: 'laporan', nama: 'Dashboard Pemilik', sub: 'Ringkasan semua cabang', url: 'laporan.html#ringkasan' },
    { id: 'kasir', nama: 'Kasir', sub: 'Penjualan & struk', url: 'kasir.html' },
    { id: 'inventaris', nama: 'Inventaris & Gudang', sub: 'Produk, SKU, kartu stok', url: 'inventaris.html' },
    { id: 'pembelian', nama: 'Pembelian', sub: 'PO, terima barang, retur', url: 'pembelian.html' },
    { id: 'member', nama: 'Member & CRM', sub: 'Poin, kartu member', url: 'member.html' },
    { id: 'promo', nama: 'Promo', sub: 'Harga coret, grosir, B2G1', url: 'promo.html' },
    { id: 'online', nama: 'Toko Online', sub: 'Katalog & pesanan WA', url: 'online.html' },
    { id: 'akuntansi', nama: 'Akuntansi', sub: 'Jurnal, faktur, laporan', url: 'akuntansi.html' },
    { id: 'aset', nama: 'Inventaris Aset', sub: 'Peralatan & penyusutan', url: 'aset.html' },
    { id: 'sdm', nama: 'SDM & Gaji', sub: 'Absen, cuti, slip gaji', url: 'sdm.html' },
    { id: 'kpi', nama: 'KPI Karyawan', sub: 'Target & kinerja', url: 'kpi.html' },
    { id: 'laporan', ak: 'laporan', nama: 'Laporan', sub: 'Penjualan, stok, pembelian', url: 'laporan.html#lap' },
    { id: 'pengguna', nama: 'Pengguna & Akses', sub: 'Pegawai & hak akses', url: 'pengguna.html', admin: true },
    { id: 'pengaturan', nama: 'Pengaturan', sub: 'Profil usaha, struk, poin', url: 'pengaturan.html', admin: true },
    { id: 'langganan', nama: 'Langganan', sub: 'Paket, tagihan, referral', url: 'langganan.html', admin: true, saas: true },
    { id: 'hp', nama: 'Aplikasi HP', sub: 'Pasang Dhyra di ponsel', url: 'app.html', semua: true }
  ];
  var SOON = [
    { id: 'qris', nama: 'Pembayaran QRIS', sub: 'QRIS, e-wallet, VA bank' },
    { id: 'wa', nama: 'Struk WhatsApp', sub: 'Kirim struk & promo via WA' },
    { id: 'cabang', nama: 'Multi Cabang', sub: 'Banyak toko, satu kendali' },
    { id: 'marketplace', nama: 'Marketplace', sub: 'Sinkron stok Shopee, Tokopedia' },
    { id: 'pajak', nama: 'Pajak & e-Faktur', sub: 'PPN, PPh, laporan pajak' }
  ];
  var LEVEL = ['Tidak ada', 'Lihat', 'Petugas', 'Manajer'];

  /* ---- sesi bersama semua modul ---- */
  var store = {
    get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
    del: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
  };
  function token() { return store.get('kpt_token'); }
  function toLogin(msg, next) {
    var q = [];
    if (next !== false) q.push('next=' + encodeURIComponent(next || location.pathname.split('/').pop() + location.hash));
    if (msg) q.push('msg=' + encodeURIComponent(msg));
    location.href = 'beranda.html' + (q.length ? '?' + q.join('&') : '');
  }
  /** Panggil modul sistem. Menolak (reject) dengan Error ber-code. */
  function sys(a, d) {
    return fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ m: 'sistem', a: a, t: token(), d: d || {} }) })
      .then(function (res) { return res.text().then(function (t) { try { return JSON.parse(t); } catch (e) { throw new Error(res.ok ? 'Jawaban server tidak valid.' : 'Server error ' + res.status + '.'); } }); })
      .then(function (r) {
        if (r && r.error) { var e = new Error(r.error); e.code = r.code; if (r.code === 'AUTH') { store.del('kpt_token'); } if (r.code === 'INSTALL') location.href = 'install.php'; throw e; }
        return r;
      }, function (e) { if (e && e.code) throw e; throw new Error(e && e.message && !/fetch|network/i.test(e.message) ? e.message : 'Gagal terhubung ke server.'); });
  }
  /** Tombol kembali ke Beranda untuk dipasang di modul (HTML string). */
  function homeBtn(cls) {
    return '<a class="' + (cls || '') + '" href="beranda.html?home=1" title="Kembali ke Beranda" style="text-decoration:none"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg><span>Beranda</span></a>';
  }

  /* PWA: pasang service worker agar Dhyra bisa diinstal di HP & tetap terbuka saat sinyal lemah */
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1'))
    W.addEventListener('load', function () { navigator.serviceWorker.register('sw.js').catch(function () {}); });
  /* HP (bukan tablet/komputer): dikirim ke server agar akun karyawan di HP hanya bisa membuka Absen saya. */
  var isHp = (function () { try { var ua = navigator.userAgent || '', s = Math.min(screen.width || 9999, screen.height || 9999);
    return /iPhone|iPod|Android.*Mobile|Windows Phone|IEMobile|Opera Mini/i.test(ua) || (W.matchMedia && matchMedia('(pointer:coarse)').matches && s < 600); } catch (e) { return false; } })();
  if (isHp && W.fetch && !W.fetch.__dh) {
    var of = W.fetch.bind(W);
    W.fetch = function (u, o) { try { if (typeof u === 'string' && /(^|\/)api\.php/.test(u)) { o = o || {}; var h = new Headers(o.headers || {}); h.set('X-Dhyra-Hp', '1'); o.headers = h; } } catch (e) {} return of(u, o); };
    W.fetch.__dh = 1;
  }
  /* Layar kunci di HP untuk akun karyawan (kode HP dari server). */
  function hpBlock(msg) {
    if (document.getElementById('dhJam')) return;
    var d = document.createElement('div'); d.id = 'dhJam';
    d.setAttribute('style', 'position:fixed;inset:0;z-index:9999;background:#14212B;display:flex;align-items:center;justify-content:center;padding:20px;font-family:inherit');
    d.innerHTML = '<div role="alertdialog" aria-modal="true" style="background:#fff;border-radius:22px;max-width:400px;width:100%;padding:28px 22px;text-align:center">' +
      '<div style="width:64px;height:64px;border-radius:50%;background:#E3F4F1;display:grid;place-items:center;margin:0 auto 12px"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0B7A6D" stroke-width="2" stroke-linecap="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>' +
      '<h2 style="margin:0 0 8px;font-size:19px;color:#14212B">Khusus komputer / tablet kasir</h2><p id="dhJamMsg" style="margin:0 0 18px;color:#4A5A63;font-size:14.5px;line-height:1.55"></p>' +
      '<a href="absen.html" style="display:block;padding:13px;border-radius:12px;background:#0B7A6D;color:#fff;font-weight:700;text-decoration:none">Buka Absen saya</a></div>';
    document.body.appendChild(d); document.getElementById('dhJamMsg').textContent = msg;
  }
  /* Layar kunci saat aplikasi dibuka di luar jam kerja (kode JAM dari server). */
  function jamBlock(msg) {
    if (document.getElementById('dhJam')) { document.getElementById('dhJamMsg').textContent = msg; return; }
    var d = document.createElement('div'); d.id = 'dhJam';
    d.setAttribute('style', 'position:fixed;inset:0;z-index:9999;background:rgba(15,26,33,.92);display:flex;align-items:center;justify-content:center;padding:20px;font-family:inherit');
    d.innerHTML = '<div role="alertdialog" aria-modal="true" aria-labelledby="dhJamT" style="background:#fff;border-radius:22px;max-width:440px;width:100%;padding:28px 24px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)">' +
      '<div style="width:64px;height:64px;border-radius:50%;background:#FFF4DE;display:grid;place-items:center;margin:0 auto 12px"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#C98A12" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></div>' +
      '<h2 id="dhJamT" style="margin:0 0 8px;font-size:20px;color:#14212B">Di luar jam kerja</h2><p id="dhJamMsg" style="margin:0 0 18px;color:#4A5A63;font-size:14.5px;line-height:1.55"></p>' +
      '<div style="display:flex;flex-direction:column;gap:8px"><a href="absen.html#aju" style="display:block;padding:12px;border-radius:12px;background:#0B7A6D;color:#fff;font-weight:700;text-decoration:none">Ajukan lembur / izin akses</a>' +
      '<div style="display:flex;gap:8px"><a href="beranda.html?home=1" style="flex:1;padding:11px;border-radius:12px;border:1px solid #D5DDE0;color:#14212B;font-weight:700;text-decoration:none">Beranda</a>' +
      '<button type="button" onclick="location.reload()" style="flex:1;padding:11px;border-radius:12px;border:1px solid #D5DDE0;background:#fff;color:#14212B;font-weight:700;cursor:pointer;font:inherit">Coba lagi</button></div></div>' +
      '<p style="margin:14px 0 0;font-size:12px;color:#7A8A92">Percobaan membuka aplikasi di luar jam kerja tercatat untuk pemilik toko.</p></div>';
    document.body.appendChild(d); document.getElementById('dhJamMsg').textContent = msg;
  }
  /* Paket langganan (SaaS): menu di luar paket (PAKET) atau toko mode baca-saja (LANGGANAN). */
  function subBlock(code, msg, admin) {
    var old = document.getElementById('dhSub'); if (old) old.remove();
    var tetap = code === 'PAKET';
    var d = document.createElement('div'); d.id = 'dhSub';
    d.setAttribute('style', 'position:fixed;inset:0;z-index:9998;background:rgba(15,26,33,' + (tetap ? '.92' : '.55') + ');display:flex;align-items:center;justify-content:center;padding:20px;font-family:inherit');
    d.innerHTML = '<div role="alertdialog" aria-modal="true" aria-labelledby="dhSubT" style="background:#fff;border-radius:22px;max-width:440px;width:100%;padding:28px 24px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)">' +
      '<div style="width:64px;height:64px;border-radius:50%;background:' + (tetap ? '#E7ECFC' : '#FDF2DA') + ';display:grid;place-items:center;margin:0 auto 12px"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="' + (tetap ? '#2C44A8' : '#8A5A00') + '" stroke-width="2" stroke-linecap="round"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></div>' +
      '<h2 id="dhSubT" style="margin:0 0 8px;font-size:20px;color:#14212B">' + (tetap ? 'Tidak termasuk paket' : 'Toko dalam mode baca-saja') + '</h2><p id="dhSubMsg" style="margin:0 0 18px;color:#4A5A63;font-size:14.5px;line-height:1.55"></p>' +
      '<div style="display:flex;flex-direction:column;gap:8px"><a href="langganan.html" style="display:block;padding:12px;border-radius:12px;background:#0B7A6D;color:#fff;font-weight:700;text-decoration:none">' + (tetap ? 'Lihat paket langganan' : 'Perpanjang langganan') + '</a>' +
      '<div style="display:flex;gap:8px"><a href="beranda.html?home=1" style="flex:1;padding:11px;border-radius:12px;border:1px solid #D5DDE0;color:#14212B;font-weight:700;text-decoration:none">Beranda</a>' +
      (tetap ? '' : '<button type="button" id="dhSubX" style="flex:1;padding:11px;border-radius:12px;border:1px solid #D5DDE0;background:#fff;color:#14212B;font-weight:700;cursor:pointer;font:inherit">Tutup</button>') + '</div></div>' +
      (admin === false ? '<p style="margin:14px 0 0;font-size:12px;color:#7A8A92">Hanya Administrator toko yang dapat mengganti paket.</p>' : '') + '</div>';
    document.body.appendChild(d); document.getElementById('dhSubMsg').textContent = msg;
    var x = document.getElementById('dhSubX'); if (x) x.onclick = function () { d.remove(); };
  }
  W.Dhyra = { C: C, subBlock: subBlock, mark: mark, logo: logo, icon: icon, MODULES: MODULES, SOON: SOON, LEVEL: LEVEL, store: store, token: token, toLogin: toLogin, sys: sys, homeBtn: homeBtn, jamBlock: jamBlock, hpBlock: hpBlock, isHp: isHp };
})(window);
