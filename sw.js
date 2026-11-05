/* Dhyra — service worker: aplikasi bisa dipasang di HP dan tampilan tetap terbuka saat sinyal lemah.
   Data (api.php) selalu diambil langsung dari server, tidak pernah disimpan di cache. */
const V = 'dhyra-5.6.2';
const SHELL = ['beranda.html', 'kasir.html', 'absen.html', 'app.html', 'assets/dhyra.js?v=5.6.2', 'assets/dhyra.css?v=5.6.2', 'assets/cetak.js?v=5.6.2', 'assets/modul2.js?v=5.6.2', 'assets/modul3.js?v=5.6.2', 'assets/modul4.js?v=5.6.2',
  'assets/modul2.css?v=5.6.2', 'assets/modul3.css?v=5.6.2', 'assets/icon-192.png', 'assets/favicon-32.png', 'manifest.webmanifest'];
self.addEventListener('install', e => { e.waitUntil(caches.open(V).then(c => Promise.all(SHELL.map(u => c.add(u).catch(() => {})))).then(() => self.skipWaiting())); });
self.addEventListener('activate', e => { e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== V).map(k => caches.delete(k)))).then(() => self.clients.claim())); });
self.addEventListener('fetch', e => {
  const r = e.request, u = new URL(r.url);
  if (r.method !== 'GET' || u.origin !== location.origin || /api\.php|install\.php|\/uploads\//.test(u.pathname)) return;
  const isPage = r.mode === 'navigate' || /\.html$/.test(u.pathname);
  if (isPage) {
    e.respondWith(fetch(r).then(res => { const c = res.clone(); caches.open(V).then(x => x.put(r, c)); return res; })
      .catch(() => caches.match(r, { ignoreSearch: true }).then(m => m || new Response('<meta charset="utf-8"><meta name="viewport" content="width=device-width"><body style="font-family:sans-serif;padding:30px;text-align:center"><h2>Tidak ada koneksi</h2><p>Periksa internet Anda lalu muat ulang halaman.</p></body>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } }))));
    return;
  }
  if (/\.(js|css|png|svg|ico|webmanifest|woff2?)$/.test(u.pathname)) {
    e.respondWith(caches.match(r).then(m => { const f = fetch(r).then(res => { if (res.ok) { const c = res.clone(); caches.open(V).then(x => x.put(r, c)); } return res; }).catch(() => m); return m || f; }));
  }
});
