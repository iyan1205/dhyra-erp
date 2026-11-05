/* ============================================================
 *  Dhyra — cetak struk langsung ke printer thermal (ESC/POS)
 *  Jalur: Bluetooth (Web Bluetooth) · USB (WebUSB) · USB-Serial/COM (Web Serial)
 *         · aplikasi RawBT (Android) · dialog cetak browser (cadangan)
 *  Pengaturan disimpan per perangkat (localStorage), karena printer menempel di perangkat kasir.
 * ============================================================ */
(function (W) {
  'use strict';
  const KEY = 'dhyra_printer';
  const DEF = { mode: 'browser', lebar: '', potong: true, laci: false, salinan: 1, baud: 9600, nama: '', feed: 3 };
  const cfg = () => { try { return Object.assign({}, DEF, JSON.parse(localStorage.getItem(KEY) || '{}')); } catch (e) { return Object.assign({}, DEF); } };
  const save = c => { try { localStorage.setItem(KEY, JSON.stringify(Object.assign(cfg(), c))); } catch (e) {} };
  const supported = {
    bt: !!(navigator.bluetooth && navigator.bluetooth.requestDevice),
    usb: !!(navigator.usb && navigator.usb.requestDevice),
    serial: !!(navigator.serial && navigator.serial.requestPort),
    rawbt: /android/i.test(navigator.userAgent)
  };

  /* ---------------- pembuat perintah ESC/POS ---------------- */
  const ESC = 0x1B, GS = 0x1D;
  const ascii = s => String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[‘’]/g, "'").replace(/[“”]/g, '"').replace(/[–—]/g, '-').replace(/…/g, '...').replace(/×/g, 'x').replace(/[^\x20-\x7E\n]/g, '');
  function Doc(cols) {
    const b = [];
    const raw = (...x) => { x.forEach(v => Array.isArray(v) ? b.push(...v) : b.push(v)); return api; };
    const text = s => { const t = ascii(s); for (let i = 0; i < t.length; i++) b.push(t.charCodeAt(i)); return api; };
    const api = {
      cols, raw, text,
      init: () => raw(ESC, 0x40, ESC, 0x74, 0x00),
      align: a => raw(ESC, 0x61, a === 'c' ? 1 : a === 'r' ? 2 : 0),
      bold: on => raw(ESC, 0x45, on ? 1 : 0),
      size: (w, h) => raw(GS, 0x21, ((w - 1) << 4) | (h - 1)),
      ln: (s = '') => { text(s); b.push(0x0A); return api; },
      feed: n => raw(ESC, 0x64, n),
      line: (ch = '-') => api.ln(ch.repeat(cols)),
      /* teks kiri + kanan rata dalam satu baris (kanan dipertahankan utuh) */
      lr: (l, r, c = cols) => { l = ascii(l); r = ascii(r); const sp = c - r.length - 1; if (l.length > sp) { api.ln(l); return api.ln(' '.repeat(Math.max(0, c - r.length)) + r); } return api.ln(l + ' '.repeat(c - l.length - r.length) + r); },
      wrap: (s, ind = '') => { const words = ascii(s).split(/\s+/); let cur = ''; words.forEach(w => { if ((cur + ' ' + w).trim().length > cols - ind.length) { if (cur) api.ln(ind + cur); while (w.length > cols - ind.length) { api.ln(ind + w.slice(0, cols - ind.length)); w = w.slice(cols - ind.length); } cur = w; } else cur = (cur + ' ' + w).trim(); }); if (cur) api.ln(ind + cur); return api; },
      center: s => { api.align('c'); api.wrap(s); return api.align('l'); },
      cut: () => raw(GS, 0x56, 0x42, 0x00),
      drawer: () => raw(ESC, 0x70, 0x00, 0x19, 0xFA),
      bytes: () => new Uint8Array(b)
    };
    return api;
  }
  const n0 = n => Math.round(+n || 0).toLocaleString('id-ID');
  const nf = n => (+n || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 });
  const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const fdt = s => { s = String(s || ''); const d = s.slice(0, 10).split('-'); return d.length === 3 ? (+d[2]) + ' ' + MON[+d[1] - 1] + ' ' + d[0] + ' ' + s.slice(11, 16) : s; };
  const colsOf = (c, s) => ((c.lebar || (s && s.kertas) || '58') === '80' ? 48 : 32);

  /** Struk penjualan dari objek transaksi Dhyra. s = pengaturan toko (S.set). */
  function receipt(t, s, c) {
    c = c || cfg(); const d = Doc(colsOf(c, s));
    d.init().align('c').bold(true);
    const nm = ascii(s.namaToko || '');
    if (nm.length * 2 <= d.cols) d.size(2, 2).ln(nm).size(1, 1); else d.size(1, 2).wrap(nm).size(1, 1);
    d.bold(false); if (s.alamat) d.wrap(s.alamat); if (s.telepon) d.ln(s.telepon); d.align('l').line();
    d.lr('No', t.no).lr('Tanggal', fdt(t.tanggal)).lr('Kasir', t.kasir || ''); if (t.pelanggan) d.lr('Pelanggan', t.pelanggan);
    if (t.offline) d.align('c').bold(true).ln('** TRANSAKSI OFFLINE **').bold(false).align('l');
    d.line();
    let itD = 0, hemat = 0;
    (t.items || []).forEach(i => {
      const dp = +i.dp || 0, dm = (+i.diskon || 0) - dp; itD += +i.diskon || 0; hemat += dp;
      d.wrap(i.nama);
      d.lr('  ' + nf(i.qty) + ' ' + (i.satuan || '') + ' x ' + n0(i.harga), n0((+i.subtotal || 0) + (+i.diskon || 0)));
      if (dp > 0) d.lr('    ' + (i.promo || 'Promo'), '-' + n0(dp));
      if (dm > 0) d.lr('    Diskon', '-' + n0(dm));
    });
    d.line().lr('Subtotal', n0(t.subtotal));
    const X = t.x || {}, nota = X.nota && X.nota.d > 0 ? X.nota : null, pv = +X.poinNilai || 0;
    if (nota) hemat += nota.d;
    if (t.x) {
      if (itD > 0) d.lr('Potongan item', '-' + n0(itD));
      if (nota) d.lr(nota.nama, '-' + n0(nota.d));
      const rest = t.diskon - itD - (nota ? nota.d : 0) - pv; if (rest > 0) d.lr('Diskon', '-' + n0(rest));
      if (pv > 0) d.lr('Tukar ' + n0(t.poinTukar || 0) + ' poin', '-' + n0(pv));
    } else if (t.diskon > 0) d.lr('Diskon', '-' + n0(t.diskon));
    if (t.pajak > 0) d.lr(s.pajakNama || 'Pajak', n0(t.pajak));
    d.bold(true).size(1, 2).lr('TOTAL', 'Rp ' + n0(t.total)).size(1, 1).bold(false);
    d.lr('Bayar (' + t.metode + ')', n0(t.bayar)).lr('Kembali', n0(t.kembali));
    if (hemat > 0) d.align('c').bold(true).ln('Anda hemat Rp ' + n0(hemat)).bold(false).align('l');
    if (t.member) { d.line().lr('Member', t.member.nama).lr('Kode', t.member.kode).lr('Poin didapat', '+' + n0(t.poinDapat || 0)).lr('Saldo poin', n0(t.member.poin)); }
    d.line(); if (s.footer) d.center(s.footer);
    if (t.status === 'Batal') d.align('c').bold(true).ln('*** DIBATALKAN ***').bold(false).align('l');
    d.feed(Math.max(1, +c.feed || 3));
    if (c.potong) d.cut();
    return d;
  }
  function testDoc(s, c) {
    c = c || cfg(); const d = Doc(colsOf(c, s));
    d.init().align('c').bold(true).size(2, 2).ln('DHYRA').size(1, 1).bold(false).ln('Tes printer berhasil').ln(new Date().toLocaleString('id-ID')).align('l').line()
      .lr('Lebar kertas', (d.cols === 48 ? '80' : '58') + ' mm').lr('Karakter / baris', String(d.cols)).lr('Jalur', c.mode).line()
      .ln('1234567890'.repeat(5).slice(0, d.cols)).align('c').ln('Stok pas, kas cocok, untung kelihatan.').align('l').feed(Math.max(1, +c.feed || 3));
    if (c.potong) d.cut();
    return d;
  }

  /* ---------------- jalur pengiriman ---------------- */
  let BT = null, USB = null, SER = null;
  const BT_SVC = ['000018f0-0000-1000-8000-00805f9b34fb', 'e7810a71-73ae-499d-8c15-faa9aef0c3f2', '49535343-fe7d-4ae5-8fa9-9fafd205e455', '0000ff00-0000-1000-8000-00805f9b34fb', '0000fee7-0000-1000-8000-00805f9b34fb', '0000ae30-0000-1000-8000-00805f9b34fb'];
  async function btChar(dev) {
    const srv = await dev.gatt.connect();
    for (const s of await srv.getPrimaryServices()) {
      for (const ch of await s.getCharacteristics()) if (ch.properties.writeWithoutResponse || ch.properties.write) return ch;
    }
    throw new Error('Printer Bluetooth tidak menyediakan jalur tulis');
  }
  const bt = {
    async connect(pick) {
      if (!supported.bt) throw new Error('Browser ini tidak mendukung Bluetooth. Pakai Chrome di Android / komputer, atau aplikasi RawBT.');
      let dev = BT && BT.dev;
      if (!dev && !pick && navigator.bluetooth.getDevices) { const l = await navigator.bluetooth.getDevices(); dev = l.find(x => x.name === cfg().nama) || l[0]; }
      if (!dev || pick) dev = await navigator.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: BT_SVC });
      const ch = await btChar(dev); BT = { dev, ch }; save({ nama: dev.name || 'Printer Bluetooth' });
      dev.addEventListener('gattserverdisconnected', () => { if (BT && BT.dev === dev) BT.ch = null; });
      return dev.name || 'Printer Bluetooth';
    },
    async send(u8) {
      if (!BT || !BT.ch) await bt.connect(false);
      const ch = BT.ch, n = 100;
      for (let i = 0; i < u8.length; i += n) {
        const part = u8.slice(i, i + n);
        if (ch.properties.writeWithoutResponse && ch.writeValueWithoutResponse) await ch.writeValueWithoutResponse(part); else await ch.writeValue(part);
        await new Promise(r => setTimeout(r, 12));
      }
    }
  };
  const usb = {
    async connect(pick) {
      if (!supported.usb) throw new Error('Browser ini tidak mendukung USB langsung. Pakai Chrome / Edge di komputer.');
      let dev = USB && USB.dev;
      if (!dev && !pick) { const l = await navigator.usb.getDevices(); dev = l[0]; }
      if (!dev || pick) dev = await navigator.usb.requestDevice({ filters: [] });
      await dev.open(); if (dev.configuration === null) await dev.selectConfiguration(1);
      let ep = null, ifn = null;
      for (const itf of dev.configuration.interfaces) for (const alt of itf.alternates) for (const e of alt.endpoints) if (e.direction === 'out' && !ep) { ep = e.endpointNumber; ifn = itf.interfaceNumber; }
      if (ep === null) throw new Error('Printer USB tidak memiliki jalur tulis');
      await dev.claimInterface(ifn); USB = { dev, ep }; save({ nama: dev.productName || 'Printer USB' });
      return dev.productName || 'Printer USB';
    },
    async send(u8) { if (!USB) await usb.connect(false); for (let i = 0; i < u8.length; i += 4096) await USB.dev.transferOut(USB.ep, u8.slice(i, i + 4096)); }
  };
  const serial = {
    async connect(pick) {
      if (!supported.serial) throw new Error('Browser ini tidak mendukung port serial. Pakai Chrome / Edge di komputer.');
      let port = SER && SER.port;
      if (!port && !pick) { const l = await navigator.serial.getPorts(); port = l[0]; }
      if (!port || pick) port = await navigator.serial.requestPort();
      if (!port.writable) await port.open({ baudRate: +cfg().baud || 9600 });
      SER = { port }; save({ nama: 'Port serial / COM' }); return 'Port serial / COM';
    },
    async send(u8) { if (!SER) await serial.connect(false); const w = SER.port.writable.getWriter(); try { await w.write(u8); } finally { w.releaseLock(); } }
  };
  const rawbt = {
    async connect() { save({ nama: 'Aplikasi RawBT' }); return 'Aplikasi RawBT'; },
    async send(u8) { let s = ''; for (let i = 0; i < u8.length; i++) s += String.fromCharCode(u8[i]); location.href = 'intent:base64,' + btoa(s) + '#Intent;scheme=rawbt;package=ru.a402d.rawbtprinter;end;'; }
  };
  const PATH = { bt, usb, serial, rawbt };
  async function sendBytes(u8, c) {
    c = c || cfg(); const p = PATH[c.mode]; if (!p) throw new Error('Pilih jenis printer terlebih dahulu');
    for (let k = 0; k < Math.max(1, Math.min(5, +c.salinan || 1)); k++) await p.send(u8);
  }
  W.DhyraPrint = {
    cfg, save, supported, Doc, receipt, testDoc, ascii,
    isDirect: () => cfg().mode !== 'browser',
    connect: (pick) => { const p = PATH[cfg().mode]; if (!p) throw new Error('Mode cetak browser tidak perlu dihubungkan'); return p.connect(pick); },
    connected: () => { const m = cfg().mode; return m === 'bt' ? !!(BT && BT.ch) : m === 'usb' ? !!USB : m === 'serial' ? !!SER : m === 'rawbt'; },
    /** Cetak struk transaksi; bila tunai & laci aktif, laci uang dibuka. */
    async print(t, s) {
      const c = cfg(), d = receipt(t, s, c);
      const u8 = d.bytes();
      let out = u8;
      if (c.laci && t.metode === 'Tunai' && !t.reprint) { const k = new Uint8Array([ESC, 0x70, 0x00, 0x19, 0xFA]); out = new Uint8Array(k.length + u8.length); out.set(k); out.set(u8, k.length); }
      await sendBytes(out, c);
    },
    async test(s) { await sendBytes(testDoc(s).bytes(), Object.assign(cfg(), { salinan: 1 })); },
    async drawer() { await sendBytes(Doc(32).init().drawer().bytes(), Object.assign(cfg(), { salinan: 1 })); }
  };
})(window);
