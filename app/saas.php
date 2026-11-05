<?php
/**
 * ============================================================
 *  Dhyra SaaS — banyak toko dalam satu server
 *  · Setiap toko = 1 database sendiri + alamat sendiri: <toko>.<domain>
 *  · Database PUSAT: daftar toko, paket (harga, menu, batas), tagihan, voucher, pengumuman
 *  · Halaman pusat (domain utama): daftar.html (pendaftaran) & pusat.html (panel pengelola)
 *  Aktif bila app/config.php memiliki bagian 'saas' (lihat install.php mode SaaS).
 * ============================================================
 */
declare(strict_types=1);

const SAAS_DB_V = 1;
const SAAS_BULAN = [1, 3, 6, 12];
const SAAS_CADANGAN = ['www', 'admin', 'api', 'app', 'pusat', 'mail', 'email', 'smtp', 'ftp', 'cpanel', 'webmail', 'ns1', 'ns2', 'dhyra', 'daftar',
    'login', 'masuk', 'help', 'bantuan', 'support', 'status', 'blog', 'static', 'cdn', 'assets', 'uploads', 'billing', 'tagihan', 'root', 'test', 'dev'];
const SAAS_SET = [
    'brand' => 'Dhyra', 'trialHari' => '14', 'trialPaket' => 'PBISNIS', 'tenggangHari' => '7', 'tenggangTrial' => '3', 'ingatHari' => '7,3,1',
    'rekening' => "Bank BCA 000-000-0000 a/n Nama Anda", 'qris' => '', 'kontakWa' => '', 'adminWa' => '',
    'waProvider' => 'fonnte', 'waToken' => '', 'waSecret' => '', 'waDomain' => '', 'waUrl' => '',
    'otpAktif' => '1', 'daftarAktif' => '1', 'maksTokoPerWa' => '3', 'refBonusHari' => '30', 'refDiskon' => '10',
    'cronKey' => '', 'harianTerakhir' => '', 'SAAS_V' => '0',
];
const SAAS_PAKET_AWAL = [
    ['PBASIC', 'Basic', 'Untuk warung & toko kecil: kasir, stok, member, laporan.', 99000, 990000, ['kasir', 'inventaris', 'member', 'laporan'], 1, 3, 1000, 0],
    ['PPRO', 'Pro', 'Toko berkembang: + pembelian, promo, toko online, akuntansi, aset.', 199000, 1990000, ['kasir', 'inventaris', 'member', 'laporan', 'pembelian', 'promo', 'online', 'akuntansi', 'aset'], 2, 10, 5000, 1],
    ['PBISNIS', 'Bisnis', 'Semua fitur termasuk SDM, absensi, gaji, KPI & multi-cabang.', 349000, 3490000, ['kasir', 'inventaris', 'member', 'laporan', 'pembelian', 'promo', 'online', 'akuntansi', 'aset', 'sdm', 'kpi'], 0, 0, 0, 0],
];

/* ============================================================
 *  DATABASE PUSAT
 * ============================================================ */
function saas_cfg(): array { return cfg()['saas']; }
function mdb(): PDO
{
    static $p = null;
    if ($p === null) { $c = cfg(); $c['db_name'] = saas_cfg()['master']; $p = pdo_connect($c); }
    return $p;
}
function mq(string $sql, array $a = []): PDOStatement { $s = mdb()->prepare($sql); $s->execute($a); return $s; }
function mrows(string $sql, array $a = []): array { return mq($sql, $a)->fetchAll(); }
function mrow(string $sql, array $a = []): ?array { $r = mq($sql, $a)->fetch(); return $r === false ? null : $r; }
function mval(string $sql, array $a = []) { $r = mq($sql, $a)->fetchColumn(); return $r === false ? null : $r; }
function m_tx(callable $fn)
{
    $p = mdb();
    if ($p->inTransaction()) return $fn();
    $p->beginTransaction();
    try { $r = $fn(); $p->commit(); return $r; } catch (Throwable $e) { if ($p->inTransaction()) $p->rollBack(); throw $e; }
}
function sset_all(bool $fresh = false): array
{
    static $m = null;
    if ($m === null || $fresh) { $m = SAAS_SET; foreach (mrows('SELECT k, v FROM s_set') as $r) $m[$r['k']] = (string)$r['v']; }
    return $m;
}
function sset(string $k): string { return (string)(sset_all()[$k] ?? ''); }
function sset_put(array $kv): void
{
    foreach ($kv as $k => $v) mq('INSERT INTO s_set (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, (string)$v]);
    sset_all(true);
}
function saas_nomor(string $k): int
{
    mq('INSERT INTO s_nomor (k, n) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE n = LAST_INSERT_ID(n + 1)', [$k]);
    return (int)mdb()->lastInsertId();
}
function saas_log(string $oleh, string $tokoId, string $aksi, string $detail = ''): void
{
    mq('INSERT INTO s_log (waktu, oleh, toko_id, aksi, detail) VALUES (?,?,?,?,?)', [now_s(), mb_substr($oleh, 0, 40), $tokoId, mb_substr($aksi, 0, 60), mb_substr($detail, 0, 255)]);
}
/** Buat / perbarui tabel pusat. Dipanggil install.php dan setiap permintaan (cepat bila sudah terbaru). */
function saas_migrate(): void
{
    try { $v = (int)(mval("SELECT v FROM s_set WHERE k = 'SAAS_V'") ?? 0); } catch (PDOException $e) { $v = 0; }
    if ($v >= SAAS_DB_V) return;
    $sql = (string)file_get_contents(__DIR__ . '/saas_schema.sql');
    $sql = preg_replace('#/\*.*?\*/#s', '', preg_replace('/^\s*--.*$/m', '', $sql));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $st) mdb()->exec($st);
    if (!(int)mval('SELECT COUNT(*) FROM s_paket'))
        foreach (SAAS_PAKET_AWAL as $i => $p)
            mq('INSERT INTO s_paket (id, nama, deskripsi, harga_bulan, harga_tahun, modul, max_outlet, max_user, max_produk, aktif, unggulan, urut, dibuat) VALUES (?,?,?,?,?,?,?,?,?,1,?,?,?)',
                [$p[0], $p[1], $p[2], $p[3], $p[4], json_encode($p[5]), $p[6], $p[7], $p[8], $p[9], $i + 1, now_s()]);
    $have = array_column(mrows('SELECT k FROM s_set'), 'k');
    $miss = [];
    foreach (SAAS_SET as $k => $d) if (!in_array($k, $have, true)) $miss[$k] = $d;
    if (empty(sset_all(true)['cronKey']) || isset($miss['cronKey'])) $miss['cronKey'] = bin2hex(random_bytes(12));
    $miss['SAAS_V'] = (string)SAAS_DB_V;
    sset_put($miss);
}

/* ============================================================
 *  ALAMAT & TOKO YANG DILAYANI
 * ============================================================ */
function saas_host(): string { return preg_replace('/:\d+$/', '', strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')))); }
/** null = domain utama (halaman pusat); string = slug toko; false = alamat lain */
function saas_slug_host()
{
    $dom = strtolower(saas_cfg()['domain']); $h = saas_host();
    if ($h === $dom || $h === 'www.' . $dom) return null;
    $suf = '.' . $dom;
    if (strlen($h) > strlen($suf) && substr($h, -strlen($suf)) === $suf) {
        $sub = substr($h, 0, -strlen($suf));
        if (preg_match('/^[a-z0-9-]+$/', $sub)) return $sub;
    }
    return false;
}
function saas_url(string $slug = ''): string
{
    $c = saas_cfg();
    $port = !empty($c['port']) ? ':' . (int)$c['port'] : '';
    return ($c['scheme'] ?? 'https') . '://' . ($slug !== '' ? $slug . '.' : '') . $c['domain'] . $port;
}
/** Dipanggil api.php: pilih database toko dari subdomain. */
function saas_boot(string $m): void
{
    saas_migrate();
    $slug = saas_slug_host();
    if ($slug === false) throw new AppErr('Alamat ' . saas_host() . ' tidak dikenal. Buka ' . saas_url() . ' atau alamat toko Anda.', 'NOTOKO');
    if ($slug === null) {
        if ($m !== 'saas') throw new AppErr('Ini halaman utama ' . sset('brand') . '. Buka aplikasi dari alamat toko Anda, misalnya namatoko.' . saas_cfg()['domain'] . '.', 'NOTOKO');
        return;
    }
    $t = mrow('SELECT * FROM s_toko WHERE slug = ?', [$slug]);
    if (!$t) throw new AppErr('Toko "' . $slug . '" tidak ditemukan. Periksa alamatnya, atau daftar toko baru di ' . saas_url() . '.', 'NOTOKO');
    if ((int)$t['beku'] === 1) throw new AppErr('Toko ini sedang dibekukan oleh pengelola' . ($t['beku_alasan'] !== '' ? ': ' . $t['beku_alasan'] : '') . '. Hubungi pengelola ' . sset('brand') . (sset('kontakWa') !== '' ? ' (WA ' . sset('kontakWa') . ')' : '') . '.', 'BEKU');
    saas_pakai($t);
    if (!$t['aktif_terakhir'] || strtotime((string)$t['aktif_terakhir']) < time() - 600) mq('UPDATE s_toko SET aktif_terakhir = ? WHERE id = ?', [now_s(), $t['id']]);
}
function saas_pakai(array $t): void
{
    $st = saas_status($t); $p = saas_paket_efektif($t);
    $GLOBALS['__tenantDb'] = $t['db_name'];
    $GLOBALS['__saas'] = [
        'id' => $t['id'], 'slug' => $t['slug'], 'nama' => $t['nama'], 'toko' => $t, 'status' => $st, 'ro' => $st['kode'] === 'baca',
        'paket' => $p, 'paketNama' => $p ? $p['nama'] : '-', 'modul' => $p ? saas_modul_paket($p) : [],
        'batas' => ['outlet' => $p ? (int)$p['max_outlet'] : 0, 'user' => $p ? (int)$p['max_user'] : 0, 'produk' => $p ? (int)$p['max_produk'] : 0],
        'naik' => saas_naik(),
    ];
}
/** Jalankan $fn di database toko lain (dari halaman pusat), lalu kembali. */
function saas_di_toko(array $t, callable $fn)
{
    $old = [$GLOBALS['__tenantDb'] ?? null, $GLOBALS['__saas'] ?? null];
    $GLOBALS['__tenantDb'] = $t['db_name']; $GLOBALS['__saas'] = null;
    try { return $fn(); } finally { $GLOBALS['__tenantDb'] = $old[0]; $GLOBALS['__saas'] = $old[1]; }
}
function saas_modul_paket(array $p): array { $m = json_decode((string)$p['modul'], true); return is_array($m) ? array_values(array_intersect(array_keys(MODUL), $m)) : []; }
function saas_paket_all(bool $aktifSaja = false): array
{
    if (!isset($GLOBALS['__paketCache'])) $GLOBALS['__paketCache'] = mrows('SELECT * FROM s_paket ORDER BY urut, harga_bulan');
    $c = $GLOBALS['__paketCache'];
    return $aktifSaja ? array_values(array_filter($c, fn($p) => (int)$p['aktif'] === 1)) : $c;
}
function saas_paket(string $id): ?array { foreach (saas_paket_all() as $p) if ($p['id'] === $id) return $p; return null; }
function saas_paket_efektif(array $t): ?array
{
    $p = (int)$t['trial'] === 1 ? (saas_paket(sset('trialPaket')) ?? saas_paket($t['paket_id'])) : saas_paket($t['paket_id']);
    if (!$p) { $all = saas_paket_all(true); $p = $all ? end($all) : null; }
    return $p;
}
/** Modul → nama paket termurah yang memuatnya (untuk ajakan upgrade). */
function saas_naik(): array
{
    $o = []; $ps = saas_paket_all(true);
    usort($ps, fn($a, $b) => (float)$a['harga_bulan'] <=> (float)$b['harga_bulan']);
    foreach ($ps as $p) foreach (saas_modul_paket($p) as $m) if (!isset($o[$m])) $o[$m] = $p['nama'];
    return $o;
}
/** Status langganan: trial | aktif | tenggang | baca | beku */
function saas_status(array $t): array
{
    $trial = (int)$t['trial'] === 1;
    $g = max(0, (int)sset($trial ? 'tenggangTrial' : 'tenggangHari'));
    $sisa = diff_days((string)$t['berakhir'], today_s());
    if ((int)$t['beku'] === 1) $k = 'beku';
    elseif ($sisa >= 0) $k = $trial ? 'trial' : 'aktif';
    elseif (-$sisa <= $g) $k = 'tenggang';
    else $k = 'baca';
    return ['kode' => $k, 'sisa' => $sisa, 'berakhir' => (string)$t['berakhir'], 'tenggangSampai' => add_days((string)$t['berakhir'], $g), 'trial' => $trial, 'tenggang' => $g];
}
const SAAS_STATUS_NAMA = ['trial' => 'Masa coba', 'aktif' => 'Aktif', 'tenggang' => 'Masa tenggang', 'baca' => 'Baca-saja', 'beku' => 'Dibekukan'];

/** Ringkasan langganan untuk Beranda toko (dipanggil sistem.php s_me). */
function saas_me(array $u): array
{
    $c = saas_ctx(); $st = $c['status'];
    return ['aktif' => true, 'brand' => sset('brand'), 'paket' => $c['paketNama'], 'status' => $st['kode'], 'statusNama' => SAAS_STATUS_NAMA[$st['kode']],
        'sisa' => $st['sisa'], 'berakhir' => $st['berakhir'], 'tenggangSampai' => $st['tenggangSampai'], 'trial' => $st['trial'], 'ro' => $c['ro'],
        'kunci' => array_diff_key(saas_naik(), array_flip($c['modul'])), 'pengumuman' => saas_pengumuman_toko($c['toko'])];
}
function saas_pengumuman_toko(array $t): array
{
    $td = today_s(); $st = saas_status($t);
    $o = [];
    foreach (mrows('SELECT * FROM s_pengumuman WHERE aktif = 1 AND mulai <= ? AND (sampai IS NULL OR sampai >= ?) ORDER BY mulai DESC, dibuat DESC LIMIT 10', [$td, $td]) as $p) {
        $tg = (string)$p['target'];
        $ok = $tg === 'semua' || ($tg === 'trial' && $st['trial']) || ($tg === 'bayar' && !$st['trial']) || ($tg === 'paket:' . $t['paket_id'] && !$st['trial']);
        if ($ok) $o[] = ['id' => $p['id'], 'judul' => $p['judul'], 'isi' => (string)$p['isi'], 'tipe' => $p['tipe'], 'tgl' => $p['mulai']];
    }
    return $o;
}

/* ============================================================
 *  WHATSAPP (gateway milik pengelola SaaS)
 * ============================================================ */
function saas_wa_nomor(string $s): string
{
    $n = preg_replace('/\D/', '', $s);
    if (strpos($n, '0') === 0) $n = '62' . substr($n, 1);
    elseif (strpos($n, '8') === 0) $n = '62' . $n;
    return (strlen($n) >= 10 && strlen($n) <= 15 && strpos($n, '62') === 0) ? $n : '';
}
function saas_wa(string $to, string $msg): array
{
    $s = sset_all();
    $tos = array_values(array_filter(array_map('saas_wa_nomor', preg_split('/[,;\s]+/', $to))));
    if (!$tos) return [false, 'Nomor tujuan kosong'];
    if ($s['waToken'] === '') return [false, 'Gateway WhatsApp belum diatur'];
    if (!function_exists('wa_http')) {
        // salinan ringan dari notif.php (tanpa memuat modul kasir)
        $http = function (string $url, array $form, array $hdr): array {
            if (function_exists('curl_init')) {
                $c = curl_init($url);
                curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($form), CURLOPT_HTTPHEADER => $hdr, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 8]);
                $b = curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
                return [$code, $b === false ? '' : $b];
            }
            $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", array_merge($hdr, ['Content-Type: application/x-www-form-urlencoded'])), 'content' => http_build_query($form), 'timeout' => 20, 'ignore_errors' => true]]);
            $b = @file_get_contents($url, false, $ctx); $code = 0;
            foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $code = (int)$m[1];
            return [$code, $b === false ? '' : $b];
        };
    } else $http = 'wa_http';
    if ($s['waProvider'] === 'wablas') {
        $dom = rtrim($s['waDomain'], '/'); if ($dom === '') return [false, 'Domain server Wablas belum diisi'];
        if (!preg_match('#^https?://#', $dom)) $dom = 'https://' . $dom;
        $auth = $s['waSecret'] !== '' ? $s['waToken'] . '.' . $s['waSecret'] : $s['waToken']; $ok = 0; $last = '';
        foreach ($tos as $n) { [$code, $body] = $http($dom . '/api/send-message', ['phone' => $n, 'message' => $msg], ['Authorization: ' . $auth]); $j = json_decode((string)$body, true); if ($code < 300 && !empty($j['status'])) $ok++; else $last = $j['message'] ?? ('HTTP ' . $code); }
        return [$ok === count($tos), $ok . '/' . count($tos) . ' terkirim' . ($last ? ' · ' . $last : '')];
    }
    [$code, $body] = $http($s['waUrl'] ?: 'https://api.fonnte.com/send', ['target' => implode(',', $tos), 'message' => $msg, 'countryCode' => '62'], ['Authorization: ' . $s['waToken']]);
    $j = json_decode((string)$body, true);
    if ($code < 300 && !empty($j['status'])) return [true, 'Terkirim'];
    return [false, 'Fonnte: ' . ($j['reason'] ?? $j['detail'] ?? ('HTTP ' . $code . ($code ? '' : ' (tidak dapat terhubung)')))];
}
/** Kirim setelah jawaban dikirim ke browser (tidak memperlambat). */
function saas_wa_nanti(string $to, string $msg): void
{
    if ($to === '' || sset('waToken') === '') return;
    if (empty($GLOBALS['__saasWa'])) {
        $GLOBALS['__saasWa'] = [];
        register_shutdown_function(function () {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            foreach ($GLOBALS['__saasWa'] as [$t, $m]) { try { saas_wa($t, $m); } catch (Throwable $e) { error_log('[Dhyra SaaS WA] ' . $e->getMessage()); } }
        });
    }
    $GLOBALS['__saasWa'][] = [$to, $msg];
}
function saas_rp($n): string { return 'Rp' . number_format((float)$n, 0, ',', '.'); }

/* ============================================================
 *  PENDAFTARAN TOKO BARU
 * ============================================================ */
function saas_slug_bersih(string $s): string
{
    $s = strtolower(function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s) : $s);
    $s = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', $s)), '-');
    return substr($s, 0, 30);
}
/** null = boleh dipakai; selain itu alasan */
function saas_slug_alasan(string $slug): ?string
{
    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28})[a-z0-9]$/', $slug)) return 'Alamat 3–30 karakter: huruf kecil, angka, dan tanda strip (tidak di awal/akhir).';
    if (strpos($slug, '--') !== false) return 'Alamat tidak boleh memuat dua strip berurutan.';
    if (in_array($slug, SAAS_CADANGAN, true)) return 'Alamat ini dicadangkan sistem. Pilih yang lain.';
    if (mval('SELECT id FROM s_toko WHERE slug = ?', [$slug])) return 'Alamat sudah dipakai toko lain.';
    return null;
}
function saas_slug_saran(string $base): array
{
    $b = substr(saas_slug_bersih($base), 0, 26); if (strlen($b) < 3) $b = 'toko-' . $b;
    $o = [];
    foreach ([$b, $b . '-id', 'toko' . str_replace('-', '', $b), $b . '-' . random_int(10, 99), $b . '-' . random_int(100, 999)] as $c) {
        $c = trim(substr($c, 0, 30), '-');
        if (saas_slug_alasan($c) === null && !in_array($c, $o, true)) $o[] = $c;
        if (count($o) >= 3) break;
    }
    return $o;
}
function saas_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }

function sp_info(): array
{
    require_once __DIR__ . '/kasir.php';
    $s = sset_all();
    return ['brand' => $s['brand'], 'domain' => saas_cfg()['domain'], 'url' => saas_url(), 'daftarAktif' => $s['daftarAktif'] === '1', 'otp' => $s['otpAktif'] === '1',
        'trialHari' => (int)$s['trialHari'], 'trialPaket' => (saas_paket($s['trialPaket'])['nama'] ?? ''), 'kontakWa' => $s['kontakWa'],
        'paket' => array_map('saas_paket_out', saas_paket_all(true)), 'modul' => MODUL,
        'jenis' => array_map(fn($k, $v) => ['id' => $k, 'nama' => $v[0]], array_keys(KATEGORI_PRESET), KATEGORI_PRESET),
        'refDiskon' => (int)$s['refDiskon']];
}
function saas_paket_out(array $p): array
{
    return ['id' => $p['id'], 'nama' => $p['nama'], 'deskripsi' => $p['deskripsi'], 'hargaBulan' => (int)$p['harga_bulan'], 'hargaTahun' => (int)$p['harga_tahun'],
        'modul' => saas_modul_paket($p), 'maxOutlet' => (int)$p['max_outlet'], 'maxUser' => (int)$p['max_user'], 'maxProduk' => (int)$p['max_produk'],
        'aktif' => (int)$p['aktif'] === 1, 'unggulan' => (int)$p['unggulan'] === 1, 'urut' => (int)$p['urut']];
}
function sp_cekSlug(array $d): array
{
    $slug = strtolower(trim((string)($d['slug'] ?? '')));
    if ($slug === '' && !empty($d['nama'])) return ['ok' => false, 'saran' => saas_slug_saran((string)$d['nama'])];
    $a = saas_slug_alasan($slug);
    return ['ok' => $a === null, 'alasan' => $a, 'saran' => $a === null ? [] : saas_slug_saran($d['nama'] ?? $slug)];
}
function sp_otpKirim(array $d): array
{
    if (sset('daftarAktif') !== '1') throw new AppErr('Pendaftaran toko baru sedang ditutup.');
    if (sset('otpAktif') !== '1') return ['ok' => true, 'perlu' => false];
    $wa = saas_wa_nomor((string)($d['wa'] ?? ''));
    if ($wa === '') throw new AppErr('Nomor WhatsApp tidak valid. Contoh: 0812xxxxxxx');
    if (sset('waToken') === '') throw new AppErr('Verifikasi WhatsApp belum disiapkan pengelola. Silakan hubungi ' . (sset('kontakWa') ?: 'pengelola') . '.');
    $ip = saas_ip();
    if ((int)mval('SELECT COUNT(*) FROM s_otp WHERE ip = ? AND kirim_pada > ?', [$ip, date('Y-m-d H:i:s', time() - 3600)]) >= 10) throw new AppErr('Terlalu banyak permintaan kode dari jaringan ini. Coba lagi 1 jam lagi.');
    $o = mrow('SELECT * FROM s_otp WHERE wa = ?', [$wa]);
    if ($o) {
        $tunggu = 60 - (time() - strtotime((string)$o['kirim_pada']));
        if ($tunggu > 0) throw new AppErr('Tunggu ' . $tunggu . ' detik sebelum meminta kode lagi.');
        if (strtotime((string)$o['jam_mulai']) > time() - 3600 && (int)$o['kirim'] >= 5) throw new AppErr('Kode sudah diminta 5 kali. Coba lagi 1 jam lagi.');
    }
    $kode = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    [$ok, $info] = saas_wa($wa, "Kode verifikasi " . sset('brand') . ": *" . $kode . "*\nBerlaku 10 menit. Jangan berikan kode ini kepada siapa pun.");
    if (!$ok) throw new AppErr('Kode gagal dikirim ke WhatsApp ' . $wa . ' (' . $info . '). Periksa nomornya lalu coba lagi.');
    $baruJam = !$o || strtotime((string)$o['jam_mulai']) <= time() - 3600;
    mq('REPLACE INTO s_otp (wa, kode_hash, sampai, coba, kirim, kirim_pada, jam_mulai, ip, lolos) VALUES (?,?,?,0,?,?,?,?,0)',
        [$wa, password_hash($kode, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 600), $baruJam ? 1 : (int)$o['kirim'] + 1, now_s(), $baruJam ? now_s() : $o['jam_mulai'], $ip]);
    return ['ok' => true, 'perlu' => true, 'wa' => $wa, 'detik' => 60];
}
function saas_otp_cek(string $wa, string $kode): void
{
    $o = mrow('SELECT * FROM s_otp WHERE wa = ?', [$wa]);
    if (!$o) throw new AppErr('Minta kode verifikasi WhatsApp terlebih dahulu.', 'OTP');
    if (strtotime((string)$o['sampai']) < time()) throw new AppErr('Kode verifikasi sudah kedaluwarsa. Minta kode baru.', 'OTP');
    if ((int)$o['coba'] >= 5) throw new AppErr('Terlalu banyak kode salah. Minta kode baru.', 'OTP');
    if (!password_verify(preg_replace('/\D/', '', $kode), (string)$o['kode_hash'])) {
        mq('UPDATE s_otp SET coba = coba + 1 WHERE wa = ?', [$wa]);
        throw new AppErr('Kode verifikasi salah.', 'OTP');
    }
}
function sp_daftar(array $d): array
{
    if (sset('daftarAktif') !== '1') throw new AppErr('Pendaftaran toko baru sedang ditutup.');
    $wa = saas_wa_nomor((string)($d['wa'] ?? ''));
    if ($wa === '') throw new AppErr('Nomor WhatsApp tidak valid.');
    $maks = max(1, (int)sset('maksTokoPerWa'));
    if ((int)mval('SELECT COUNT(*) FROM s_toko WHERE wa = ?', [$wa]) >= $maks) throw new AppErr('Nomor WhatsApp ini sudah dipakai untuk ' . $maks . ' toko. Hubungi pengelola bila perlu toko tambahan.');
    $ref = strtoupper(trim((string)($d['ref'] ?? '')));
    $refToko = $ref !== '' ? mrow('SELECT * FROM s_toko WHERE ref_kode = ?', [$ref]) : null;
    if ($ref !== '' && !$refToko) throw new AppErr('Kode referral "' . $ref . '" tidak ditemukan. Kosongkan bila tidak punya.');
    $data = saas_data_toko($d, $wa);
    if (sset('otpAktif') === '1') saas_otp_cek($wa, (string)($d['otp'] ?? ''));
    $t = saas_buat_toko($data + ['ref_oleh' => $refToko['id'] ?? ''], 'daftar');
    mq('DELETE FROM s_otp WHERE wa = ?', [$wa]);
    // masuk otomatis ke toko baru
    $kode = bin2hex(random_bytes(24));
    mq('INSERT INTO s_masuk (kode, toko_id, oleh, sampai) VALUES (?,?,?,?)', [$kode, $t['id'], 'daftar', date('Y-m-d H:i:s', time() + 600)]);
    $url = saas_url($t['slug']);
    $st = saas_status($t);
    saas_wa_nanti($wa, "Selamat datang di " . sset('brand') . " 🎉\n\nToko *" . $t['nama'] . "* sudah aktif.\nAlamat: " . $url . "\nUsername admin: " . $data['username'] .
        "\n\nMasa coba gratis sampai " . dmy($st['berakhir']) . ". Simpan pesan ini untuk masuk kembali.");
    if (sset('adminWa') !== '') saas_wa_nanti(sset('adminWa'), "🆕 Toko baru terdaftar: *" . $t['nama'] . "* (" . $t['slug'] . ")\nPemilik: " . $t['pemilik'] . " · " . $wa . ($refToko ? "\nReferral dari: " . $refToko['nama'] : ''));
    return ['ok' => true, 'url' => $url, 'masuk' => $url . '/masuk.html#k=' . $kode, 'slug' => $t['slug'], 'username' => $data['username'], 'berakhir' => $st['berakhir']];
}
/** Validasi data toko baru (dipakai pendaftaran & panel pusat). */
function saas_data_toko(array $d, string $wa): array
{
    require_once __DIR__ . '/kasir.php';
    $nama = str($d['nama'] ?? '', 100); if (mb_strlen($nama) < 2) throw new AppErr('Isi nama toko.');
    $slug = strtolower(trim((string)($d['slug'] ?? '')));
    if (($a = saas_slug_alasan($slug)) !== null) throw new AppErr('Alamat toko: ' . $a);
    $jenis = (string)($d['jenis'] ?? ''); if ($jenis !== '' && !isset(KATEGORI_PRESET[$jenis])) $jenis = '';
    $pemilik = str($d['pemilik'] ?? '', 100); if ($pemilik === '') throw new AppErr('Isi nama pemilik.');
    $email = str($d['email'] ?? '', 120); if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new AppErr('Format email tidak valid.');
    $un = strtolower(trim((string)($d['username'] ?? '')));
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $un)) throw new AppErr('Username 3–30 karakter: huruf kecil, angka, titik, strip.');
    $pw = (string)($d['password'] ?? ''); if (mb_strlen($pw) < 6) throw new AppErr('Password minimal 6 karakter.');
    $paket = (string)($d['paket'] ?? ''); if ($paket === '' || !saas_paket($paket)) $paket = sset('trialPaket');
    return ['nama' => $nama, 'slug' => $slug, 'jenis' => $jenis, 'pemilik' => $pemilik, 'wa' => $wa, 'email' => $email, 'username' => $un, 'password' => $pw, 'paket' => $paket];
}
/** Buat database toko + akun admin + kategori sesuai jenis usaha. */
function saas_buat_toko(array $x, string $oleh): array
{
    require_once __DIR__ . '/kasir.php';
    $c = saas_cfg();
    $dbn = preg_replace('/[^a-z0-9_]/', '', ($c['prefix'] ?? 'dhyra_t_') . str_replace('-', '_', $x['slug']));
    if (strlen($dbn) > 64) throw new AppErr('Alamat toko terlalu panjang.');
    if (mval('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?', [$dbn])) throw new AppErr('Database untuk alamat ini sudah ada di server. Pilih alamat lain.');
    $id = uid('T');
    do { $rk = strtoupper(substr(preg_replace('/[^a-z0-9]/', '', $x['slug']), 0, 6)) . random_int(10, 99); } while (mval('SELECT id FROM s_toko WHERE ref_kode = ?', [$rk]));
    $trialHari = max(0, (int)sset('trialHari'));
    $berakhir = add_days(today_s(), max(0, $trialHari - 1));
    mdb()->exec('CREATE DATABASE `' . $dbn . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    try {
        mq('INSERT INTO s_toko (id, slug, nama, pemilik, wa, email, jenis, paket_id, trial, berakhir, db_name, ref_kode, ref_oleh, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$id, $x['slug'], $x['nama'], $x['pemilik'], $x['wa'], $x['email'], $x['jenis'], $x['paket'], $trialHari > 0 ? 1 : 0, $berakhir, $dbn, $rk, $x['ref_oleh'] ?? '', now_s()]);
        $t = mrow('SELECT * FROM s_toko WHERE id = ?', [$id]);
        saas_di_toko($t, function () use ($x, $t) {
            $sql = (string)file_get_contents(__DIR__ . '/schema.sql');
            $sql = preg_replace('#/\*.*?\*/#s', '', preg_replace('/^\s*--.*$/m', '', $sql));
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $st) db()->exec($st);
            seed_defaults(['username' => $x['username'], 'password' => $x['password'], 'nama' => $x['pemilik']]);
            migrate();
            $lok = '0' . substr($x['wa'], 2);
            setting_put(['namaToko' => $x['nama'], 'NamaPerusahaan' => $x['nama'], 'telepon' => $lok, 'Telepon' => $lok, 'Email' => $x['email'], 'alamat' => '', 'Alamat' => '',
                'AppUrl' => saas_url($t['slug']), 'FakturCatatan' => 'Pembayaran mohon ditransfer ke: Bank ... a/n ' . $x['nama'] . ', No. Rek. ...']);
            q('UPDATE users SET ganti_pw = 0');
            if ($x['jenis'] !== '') { q('DELETE FROM kategori'); k_catPreset(['jenis' => $x['jenis']]); }
            log_act(['username' => 'system'], 'Toko dibuat', 'Sistem', 'Pendaftaran ' . sset('brand'));
        });
        $dir = dirname(__DIR__) . '/uploads/t/' . $t['slug'];
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        saas_log($oleh, $id, 'Toko dibuat', $x['nama'] . ' · ' . $x['slug']);
        return $t;
    } catch (Throwable $e) {
        try { mdb()->exec('DROP DATABASE IF EXISTS `' . $dbn . '`'); } catch (Throwable $e2) {}
        mq('DELETE FROM s_toko WHERE id = ?', [$id]);
        throw $e;
    }
}
/** Tukar kode sekali-pakai (pendaftaran / "masuk sebagai toko") menjadi sesi admin toko. */
function sp_masuk(array $d): array
{
    $c = saas_ctx(); if (!$c) throw new AppErr('Buka dari alamat toko.');
    $k = (string)($d['k'] ?? '');
    if (!preg_match('/^[a-f0-9]{48}$/', $k)) throw new AppErr('Tautan masuk tidak valid.');
    $m = mrow('SELECT * FROM s_masuk WHERE kode = ?', [$k]);
    if (!$m || $m['toko_id'] !== $c['id'] || (int)$m['dipakai'] === 1 || strtotime((string)$m['sampai']) < time()) throw new AppErr('Tautan masuk sudah dipakai atau kedaluwarsa. Silakan masuk dengan username & password.');
    mq('UPDATE s_masuk SET dipakai = 1 WHERE kode = ?', [$k]);
    $u = row("SELECT u.* FROM users u JOIN jabatan j ON j.id = u.jabatan_id WHERE j.is_admin = 1 AND u.status = 'aktif' ORDER BY u.dibuat LIMIT 1");
    if (!$u) throw new AppErr('Toko ini tidak punya Administrator aktif.');
    $token = bin2hex(random_bytes(32));
    $ttl = $m['oleh'] === 'daftar' ? SESSION_TTL : 7200;
    q('INSERT INTO sesi (token, user_id, dibuat, kedaluwarsa) VALUES (?,?,?,?)', [$token, $u['id'], now_s(), date('Y-m-d H:i:s', time() + $ttl)]);
    if ($m['oleh'] === 'daftar') { log_act($u, 'Masuk', 'Sistem', 'Masuk otomatis setelah pendaftaran'); q('UPDATE users SET login_terakhir = ? WHERE id = ?', [now_s(), $u['id']]); }
    else { log_act(['username' => 'dukungan'], 'Masuk sebagai admin', 'Dukungan ' . sset('brand'), 'oleh ' . $m['oleh'] . ' → akun ' . $u['username']); saas_log($m['oleh'], $c['id'], 'Masuk sebagai toko', $u['username']); }
    return ['token' => $token, 'dukungan' => $m['oleh'] !== 'daftar'];
}

/* ============================================================
 *  TAGIHAN & PEMBAYARAN
 * ============================================================ */
function saas_tambah_bulan(string $d, int $n): string
{
    $dt = new DateTime($d . ' 12:00:00'); $day = (int)$dt->format('d');
    $dt->modify('first day of this month')->modify('+' . $n . ' month');
    $dt->setDate((int)$dt->format('Y'), (int)$dt->format('m'), min($day, (int)$dt->format('t')));
    return $dt->format('Y-m-d');
}
/** Harga & potongan untuk tagihan baru. */
function saas_hitung(array $t, string $paketId, int $bulan, string $voucher): array
{
    $p = saas_paket($paketId);
    if (!$p || (int)$p['aktif'] !== 1) throw new AppErr('Paket tidak tersedia.');
    if (!in_array($bulan, SAAS_BULAN, true)) throw new AppErr('Pilih lama langganan 1, 3, 6, atau 12 bulan.');
    $normal = (int)$p['harga_bulan'] * $bulan;
    $harga = $bulan === 12 && (int)$p['harga_tahun'] > 0 ? (int)$p['harga_tahun'] : $normal;
    $diskon = 0; $ket = []; $vk = '';
    $rd = (int)sset('refDiskon');
    if ($t['ref_oleh'] !== '' && !$t['bayar_pertama'] && $rd > 0 && $harga > 0) { $x = (int)round($harga * min(100, $rd) / 100); $diskon += $x; $ket[] = 'Diskon referral ' . $rd . '%'; }
    $voucher = strtoupper(trim($voucher));
    if ($voucher !== '') {
        $v = mrow('SELECT * FROM s_voucher WHERE kode = ?', [$voucher]);
        if (!$v || (int)$v['aktif'] !== 1) throw new AppErr('Kode voucher "' . $voucher . '" tidak ditemukan atau tidak aktif.', 'VOUCHER');
        if ($v['sampai'] && $v['sampai'] < today_s()) throw new AppErr('Voucher ' . $voucher . ' sudah kedaluwarsa (' . dmy($v['sampai']) . ').', 'VOUCHER');
        if ((int)$v['kuota'] > 0 && (int)$v['terpakai'] >= (int)$v['kuota']) throw new AppErr('Kuota voucher ' . $voucher . ' sudah habis.', 'VOUCHER');
        $vp = json_decode((string)$v['paket'], true);
        if (is_array($vp) && $vp && !in_array($paketId, $vp, true)) throw new AppErr('Voucher ' . $voucher . ' tidak berlaku untuk paket ' . $p['nama'] . '.', 'VOUCHER');
        if ($bulan < (int)$v['min_bulan']) throw new AppErr('Voucher ' . $voucher . ' berlaku untuk langganan minimal ' . (int)$v['min_bulan'] . ' bulan.', 'VOUCHER');
        if (mval("SELECT id FROM s_tagihan WHERE toko_id = ? AND voucher = ? AND status = 'dibayar'", [$t['id'], $voucher])) throw new AppErr('Voucher ' . $voucher . ' sudah pernah dipakai toko ini.', 'VOUCHER');
        $sisa = $harga - $diskon;
        $x = $v['jenis'] === 'nominal' ? min((int)$v['nilai'], $sisa) : (int)round($sisa * min(100, (int)$v['nilai']) / 100);
        $diskon += $x; $ket[] = 'Voucher ' . $voucher . ($v['jenis'] === 'nominal' ? '' : ' (' . (int)$v['nilai'] . '%)'); $vk = $voucher;
    }
    $diskon = min($diskon, $harga);
    return ['paket' => $p, 'bulan' => $bulan, 'normal' => $normal, 'harga' => $harga, 'hemat' => $normal - $harga, 'diskon' => $diskon, 'diskonKet' => implode(' + ', $ket), 'total' => $harga - $diskon, 'voucher' => $vk];
}
/** Periode langganan bila tagihan dibayar hari ini. */
function saas_periode(array $t, array $p, int $bulan): array
{
    $td = today_s(); $bonus = 0; $ket = '';
    $masih = (string)$t['berakhir'] >= $td;
    if ($masih && ((int)$t['trial'] === 1 || $t['paket_id'] === $p['id'])) {
        $mulai = add_days((string)$t['berakhir'], 1);
        $ket = (int)$t['trial'] === 1 ? 'Dimulai setelah masa coba berakhir' : 'Diperpanjang dari masa aktif sekarang';
    } else {
        $mulai = $td;
        if ($masih) {
            $lama = saas_paket((string)$t['paket_id']);
            if ($lama && (int)$lama['harga_bulan'] > 0 && (int)$p['harga_bulan'] > 0) {
                $sisa = diff_days((string)$t['berakhir'], $td) + 1;
                $bonus = (int)floor($sisa * (int)$lama['harga_bulan'] / (int)$p['harga_bulan']);
                if ($bonus > 0) $ket = 'Termasuk ' . $bonus . ' hari dari sisa paket ' . $lama['nama'];
            }
        }
    }
    $sampai = add_days(saas_tambah_bulan($mulai, $bulan), $bonus - 1);
    return ['mulai' => $mulai, 'sampai' => $sampai, 'bonus' => $bonus, 'ket' => $ket];
}
function saas_tagihan_buat(array $t, string $paketId, int $bulan, string $voucher, string $oleh, string $catatan = ''): array
{
    return m_tx(function () use ($t, $paketId, $bulan, $voucher, $oleh, $catatan) {
        $h = saas_hitung($t, $paketId, $bulan, $voucher);
        // hanya satu tagihan menunggu per toko
        mq("UPDATE s_tagihan SET status = 'batal', catatan = 'Diganti tagihan baru' WHERE toko_id = ? AND status = 'menunggu' AND bukti = ''", [$t['id']]);
        if (mval("SELECT id FROM s_tagihan WHERE toko_id = ? AND status = 'menunggu'", [$t['id']])) throw new AppErr('Masih ada tagihan yang bukti bayarnya sedang diperiksa. Tunggu konfirmasi pengelola.');
        $id = uid('B');
        $no = 'INV-' . date('Ym') . '-' . str_pad((string)saas_nomor('INV' . date('Ym')), 4, '0', STR_PAD_LEFT);
        mq('INSERT INTO s_tagihan (id, no, toko_id, paket_id, paket_nama, bulan, harga, diskon, diskon_ket, total, voucher, status, catatan, dibuat, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$id, $no, $t['id'], $h['paket']['id'], $h['paket']['nama'], $bulan, $h['harga'], $h['diskon'], $h['diskonKet'], $h['total'], $h['voucher'], 'menunggu', $catatan, now_s(), $oleh]);
        saas_log($oleh, $t['id'], 'Tagihan dibuat', $no . ' · ' . $h['paket']['nama'] . ' ' . $bulan . ' bln · ' . saas_rp($h['total']));
        if ($h['total'] <= 0) saas_lunas($id, 'otomatis (gratis)');
        return mrow('SELECT * FROM s_tagihan WHERE id = ?', [$id]);
    });
}
/** Konfirmasi pembayaran: perpanjang masa aktif, catat voucher & bonus referral. */
function saas_lunas(string $tid, string $oleh): array
{
    return m_tx(function () use ($tid, $oleh) {
        $b = mrow('SELECT * FROM s_tagihan WHERE id = ? FOR UPDATE', [$tid]);
        if (!$b) throw new AppErr('Tagihan tidak ditemukan.');
        if ($b['status'] !== 'menunggu') throw new AppErr('Tagihan ' . $b['no'] . ' berstatus ' . $b['status'] . '.');
        $t = mrow('SELECT * FROM s_toko WHERE id = ? FOR UPDATE', [$b['toko_id']]);
        if (!$t) throw new AppErr('Toko tidak ditemukan.');
        $p = saas_paket($b['paket_id']); if (!$p) throw new AppErr('Paket pada tagihan sudah dihapus.');
        $per = saas_periode($t, $p, (int)$b['bulan']);
        $pertama = !$t['bayar_pertama'];
        mq('UPDATE s_toko SET paket_id = ?, trial = 0, berakhir = ?, bayar_pertama = COALESCE(bayar_pertama, ?) WHERE id = ?', [$p['id'], $per['sampai'], now_s(), $t['id']]);
        mq("UPDATE s_tagihan SET status = 'dibayar', dibayar = ?, dikonfirmasi = ?, periode_mulai = ?, periode_sampai = ? WHERE id = ?", [now_s(), mb_substr($oleh, 0, 40), $per['mulai'], $per['sampai'], $tid]);
        if ($b['voucher'] !== '') mq('UPDATE s_voucher SET terpakai = terpakai + 1 WHERE kode = ?', [$b['voucher']]);
        saas_log($oleh, $t['id'], 'Pembayaran dikonfirmasi', $b['no'] . ' · aktif s.d. ' . $per['sampai']);
        $url = saas_url($t['slug']);
        saas_wa_nanti($t['wa'], "✅ Pembayaran " . $b['no'] . " sebesar " . saas_rp($b['total']) . " sudah diterima.\n\nToko *" . $t['nama'] . "* · paket " . $p['nama'] . " aktif sampai " . dmy($per['sampai']) . ".\nTerima kasih telah memakai " . sset('brand') . " 🙏");
        // bonus referral untuk toko pengajak (sekali, saat toko baru pertama kali membayar)
        $bh = (int)sset('refBonusHari');
        if ($pertama && $t['ref_oleh'] !== '' && $bh > 0) {
            $r = mrow('SELECT * FROM s_toko WHERE id = ? FOR UPDATE', [$t['ref_oleh']]);
            if ($r) {
                $baru = add_days(max((string)$r['berakhir'], add_days(today_s(), -1)), $bh);
                mq('UPDATE s_toko SET berakhir = ? WHERE id = ?', [$baru, $r['id']]);
                saas_log('referral', $r['id'], 'Bonus referral', '+' . $bh . ' hari dari ' . $t['nama'] . ' · s.d. ' . $baru);
                saas_wa_nanti($r['wa'], "🎁 Terima kasih sudah mengajak *" . $t['nama'] . "* memakai " . sset('brand') . "!\nMasa aktif toko *" . $r['nama'] . "* bertambah " . $bh . " hari, sekarang sampai " . dmy($baru) . ".");
            }
        }
        return $per;
    });
}
function saas_tagihan_out(array $b, bool $pusat = false): array
{
    $o = ['id' => $b['id'], 'no' => $b['no'], 'paket' => $b['paket_nama'], 'paketId' => $b['paket_id'], 'bulan' => (int)$b['bulan'], 'harga' => (int)$b['harga'], 'diskon' => (int)$b['diskon'],
        'diskonKet' => $b['diskon_ket'], 'total' => (int)$b['total'], 'voucher' => $b['voucher'], 'status' => $b['status'], 'adaBukti' => $b['bukti'] !== '',
        'buktiPada' => $b['bukti_pada'] ? substr((string)$b['bukti_pada'], 0, 16) : '', 'buktiKet' => $b['bukti_ket'], 'catatan' => $b['catatan'],
        'dibuat' => substr((string)$b['dibuat'], 0, 16), 'dibayar' => $b['dibayar'] ? substr((string)$b['dibayar'], 0, 16) : '', 'mulai' => $b['periode_mulai'], 'sampai' => $b['periode_sampai']];
    if ($pusat) $o += ['tokoId' => $b['toko_id'], 'toko' => $b['toko_nama'] ?? '', 'slug' => $b['toko_slug'] ?? '', 'dikonfirmasi' => $b['dikonfirmasi'], 'dibuatOleh' => $b['dibuat_oleh']];
    return $o;
}
function saas_data_dir(string $sub): string
{
    $d = __DIR__ . '/data/' . $sub;
    if (!is_dir($d)) { @mkdir($d, 0750, true); @file_put_contents(__DIR__ . '/data/.htaccess', "Require all denied\n"); }
    return $d;
}
function saas_gambar(string $dataUrl, int $maxKb = 3000): array
{
    if (!preg_match('#^data:(image/(jpeg|jpg|png|webp)|application/pdf);base64,(.+)$#i', $dataUrl, $m)) throw new AppErr('Unggah gambar JPG/PNG/WebP atau PDF.');
    $bin = base64_decode($m[3], true);
    if ($bin === false || strlen($bin) > $maxKb * 1024) throw new AppErr('File terlalu besar (maks. ' . round($maxKb / 1024, 1) . ' MB).');
    if (stripos($m[1], 'pdf') !== false) { if (strncmp($bin, '%PDF', 4) !== 0) throw new AppErr('File PDF tidak valid.'); return [$bin, 'pdf']; }
    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new AppErr('File bukan gambar yang valid.');
    return [$bin, [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]]];
}

/* ---------------- aksi toko: menu Langganan (admin toko) ---------------- */
function lg_info(array $d, array $u): array
{
    $c = saas_ctx(); $t = mrow('SELECT * FROM s_toko WHERE id = ?', [$c['id']]); $st = saas_status($t); $s = sset_all();
    $pk = saas_paket_efektif($t);
    $pakai = ['outlet' => (int)val("SELECT COUNT(*) FROM lokasi WHERE tipe = 'toko' AND aktif = 1"), 'user' => (int)val("SELECT COUNT(*) FROM users WHERE status = 'aktif'"), 'produk' => (int)val('SELECT COUNT(*) FROM produk')];
    $refN = (int)mval('SELECT COUNT(*) FROM s_toko WHERE ref_oleh = ?', [$t['id']]);
    $refBayar = (int)mval('SELECT COUNT(*) FROM s_toko WHERE ref_oleh = ? AND bayar_pertama IS NOT NULL', [$t['id']]);
    return [
        'brand' => $s['brand'], 'toko' => ['nama' => $t['nama'], 'slug' => $t['slug'], 'url' => saas_url($t['slug']), 'pemilik' => $t['pemilik'], 'wa' => $t['wa'], 'dibuat' => substr((string)$t['dibuat'], 0, 10)],
        'status' => $st + ['nama' => SAAS_STATUS_NAMA[$st['kode']]], 'paketAktif' => $pk ? saas_paket_out($pk) : null, 'paketPilihan' => $t['paket_id'],
        'paket' => array_map('saas_paket_out', saas_paket_all(true)), 'modul' => MODUL, 'bulan' => SAAS_BULAN, 'pakai' => $pakai,
        'tagihan' => array_map('saas_tagihan_out', mrows("SELECT * FROM s_tagihan WHERE toko_id = ? AND status <> 'batal' ORDER BY dibuat DESC LIMIT 30", [$t['id']])),
        'bayar' => ['rekening' => $s['rekening'], 'qris' => $s['qris'], 'kontakWa' => $s['kontakWa']],
        'ref' => ['kode' => $t['ref_kode'], 'link' => saas_url() . '/daftar.html?ref=' . $t['ref_kode'], 'jumlah' => $refN, 'bayar' => $refBayar, 'bonusHari' => (int)$s['refBonusHari'], 'diskon' => (int)$s['refDiskon']],
        'pengumuman' => saas_pengumuman_toko($t),
    ];
}
function lg_hitung(array $d, array $u): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [saas_ctx()['id']]);
    $h = saas_hitung($t, (string)($d['paket'] ?? ''), (int)($d['bulan'] ?? 1), (string)($d['voucher'] ?? ''));
    $per = saas_periode($t, $h['paket'], $h['bulan']);
    unset($h['paket']);
    return $h + ['periode' => $per];
}
function lg_buat(array $d, array $u): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [saas_ctx()['id']]);
    $b = saas_tagihan_buat($t, (string)($d['paket'] ?? ''), (int)($d['bulan'] ?? 1), (string)($d['voucher'] ?? ''), $u['username'] . '@' . $t['slug']);
    log_act($u, 'Buat tagihan', 'Langganan', $b['no'] . ' ' . saas_rp($b['total']));
    return ['baru' => saas_tagihan_out(mrow('SELECT * FROM s_tagihan WHERE id = ?', [$b['id']]))] + lg_info([], $u);
}
function lg_batal(array $d, array $u): array
{
    $t = saas_ctx();
    $b = mrow('SELECT * FROM s_tagihan WHERE id = ? AND toko_id = ?', [(string)($d['id'] ?? ''), $t['id']]);
    if (!$b || $b['status'] !== 'menunggu') throw new AppErr('Tagihan tidak dapat dibatalkan.');
    mq("UPDATE s_tagihan SET status = 'batal', catatan = 'Dibatalkan toko' WHERE id = ?", [$b['id']]);
    saas_log($u['username'] . '@' . $t['slug'], $t['id'], 'Tagihan dibatalkan', $b['no']);
    return lg_info([], $u);
}
function lg_bukti(array $d, array $u): array
{
    $t = saas_ctx();
    $b = mrow('SELECT * FROM s_tagihan WHERE id = ? AND toko_id = ?', [(string)($d['id'] ?? ''), $t['id']]);
    if (!$b || $b['status'] !== 'menunggu') throw new AppErr('Tagihan tidak ditemukan atau sudah diproses.');
    [$bin, $ext] = saas_gambar((string)($d['file'] ?? ''));
    $name = $b['id'] . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (@file_put_contents(saas_data_dir('bukti') . '/' . $name, $bin) === false) throw new AppErr('Gagal menyimpan bukti. Hubungi pengelola.');
    if ($b['bukti'] !== '') @unlink(saas_data_dir('bukti') . '/' . basename((string)$b['bukti']));
    $ket = str($d['ket'] ?? '', 255);
    mq("UPDATE s_tagihan SET bukti = ?, bukti_pada = ?, bukti_ket = ?, catatan = '' WHERE id = ?", [$name, now_s(), $ket, $b['id']]);
    saas_log($u['username'] . '@' . $t['slug'], $t['id'], 'Bukti bayar diunggah', $b['no']);
    log_act($u, 'Unggah bukti bayar', 'Langganan', $b['no']);
    if (sset('adminWa') !== '') saas_wa_nanti(sset('adminWa'), "💳 Bukti bayar masuk\n" . $b['no'] . " · *" . $t['nama'] . "* (" . $t['slug'] . ")\n" . $b['paket_nama'] . ' ' . $b['bulan'] . ' bulan · ' . saas_rp($b['total']) . ($ket !== '' ? "\nCatatan: " . $ket : '') . "\n\nKonfirmasi di " . saas_url() . '/pusat.html#tagihan');
    return lg_info([], $u);
}
function lg_invoice(array $d, array $u): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [saas_ctx()['id']]);
    $b = mrow('SELECT * FROM s_tagihan WHERE id = ? AND toko_id = ?', [(string)($d['id'] ?? ''), $t['id']]);
    if (!$b) throw new AppErr('Tagihan tidak ditemukan.');
    return saas_invoice_data($b, $t);
}
function saas_invoice_data(array $b, array $t): array
{
    $s = sset_all();
    return ['tagihan' => saas_tagihan_out($b), 'toko' => ['nama' => $t['nama'], 'pemilik' => $t['pemilik'], 'wa' => $t['wa'], 'email' => $t['email'], 'url' => saas_url($t['slug'])],
        'penjual' => ['brand' => $s['brand'], 'rekening' => $s['rekening'], 'kontakWa' => $s['kontakWa'], 'url' => saas_url()]];
}

/* ============================================================
 *  PANEL PUSAT (pengelola SaaS)
 * ============================================================ */
function pz_auth(?string $t): array
{
    if (!$t || !preg_match('/^[a-f0-9]{64}$/', $t)) throw new AppErr('Sesi berakhir. Silakan masuk kembali.', 'AUTH');
    $a = mrow('SELECT a.*, s.kedaluwarsa FROM s_sesi s JOIN s_admin a ON a.id = s.admin_id WHERE s.token = ?', [$t]);
    if (!$a || strtotime((string)$a['kedaluwarsa']) < time() || (int)$a['aktif'] !== 1) { mq('DELETE FROM s_sesi WHERE token = ?', [$t]); throw new AppErr('Sesi berakhir. Silakan masuk kembali.', 'AUTH'); }
    if (strtotime((string)$a['kedaluwarsa']) - time() < 21600) mq('UPDATE s_sesi SET kedaluwarsa = ? WHERE token = ?', [date('Y-m-d H:i:s', time() + 43200), $t]);
    $a['_token'] = $t;
    return $a;
}
function pz_login(array $d): array
{
    $un = strtolower(trim((string)($d['username'] ?? ''))); $pw = (string)($d['password'] ?? '');
    $k = 'adm:' . $un . '|' . saas_ip();
    $f = mrow('SELECT * FROM s_gagal WHERE k = ?', [$k]);
    if ($f && (int)$f['n'] >= 5 && strtotime((string)$f['sampai']) > time()) throw new AppErr('Terlalu banyak percobaan gagal. Coba lagi 10 menit lagi.');
    $a = mrow('SELECT * FROM s_admin WHERE username = ?', [$un]);
    if (!$a || (int)$a['aktif'] !== 1 || !password_verify($pw, (string)$a['pass_hash'])) {
        $n = ($f && strtotime((string)$f['sampai']) > time()) ? (int)$f['n'] + 1 : 1;
        mq('REPLACE INTO s_gagal (k, n, sampai) VALUES (?,?,?)', [$k, $n, date('Y-m-d H:i:s', time() + 600)]);
        throw new AppErr('Username atau password salah.');
    }
    mq('DELETE FROM s_gagal WHERE k = ?', [$k]);
    $tok = bin2hex(random_bytes(32));
    mq('INSERT INTO s_sesi (token, admin_id, kedaluwarsa) VALUES (?,?,?)', [$tok, $a['id'], date('Y-m-d H:i:s', time() + 43200)]);
    mq('UPDATE s_admin SET login_terakhir = ? WHERE id = ?', [now_s(), $a['id']]);
    mq('DELETE FROM s_sesi WHERE kedaluwarsa < ?', [now_s()]);
    saas_log($a['username'], '', 'Masuk panel pusat');
    return ['token' => $tok] + pz_me([], $a);
}
function pz_me(array $d, array $a): array { return ['admin' => ['nama' => $a['nama'], 'username' => $a['username']], 'brand' => sset('brand'), 'domain' => saas_cfg()['domain'], 'url' => saas_url(), 'versi' => APP_VERSION]; }
function pz_logout(array $d, array $a): array { mq('DELETE FROM s_sesi WHERE token = ?', [$a['_token']]); return ['ok' => true]; }
function pz_pw(array $d, array $a): array
{
    if (!password_verify((string)($d['lama'] ?? ''), (string)$a['pass_hash'])) throw new AppErr('Password lama salah.');
    if (mb_strlen((string)($d['baru'] ?? '')) < 8) throw new AppErr('Password baru minimal 8 karakter.');
    mq('UPDATE s_admin SET pass_hash = ? WHERE id = ?', [password_hash((string)$d['baru'], PASSWORD_DEFAULT), $a['id']]);
    mq('DELETE FROM s_sesi WHERE admin_id = ? AND token <> ?', [$a['id'], $a['_token']]);
    saas_log($a['username'], '', 'Ganti password');
    return ['ok' => true];
}
function pz_toko_out(array $t): array
{
    $st = saas_status($t); $p = saas_paket_efektif($t); $pp = saas_paket((string)$t['paket_id']);
    return ['id' => $t['id'], 'slug' => $t['slug'], 'nama' => $t['nama'], 'pemilik' => $t['pemilik'], 'wa' => $t['wa'], 'email' => $t['email'], 'jenis' => $t['jenis'],
        'paketId' => $t['paket_id'], 'paket' => $pp['nama'] ?? '-', 'paketEfektif' => $p['nama'] ?? '-', 'hargaBulan' => (int)($pp['harga_bulan'] ?? 0),
        'trial' => (int)$t['trial'] === 1, 'berakhir' => $t['berakhir'], 'status' => $st['kode'], 'statusNama' => SAAS_STATUS_NAMA[$st['kode']], 'sisa' => $st['sisa'],
        'beku' => (int)$t['beku'] === 1, 'bekuAlasan' => $t['beku_alasan'], 'refKode' => $t['ref_kode'], 'refOleh' => $t['ref_oleh'], 'url' => saas_url($t['slug']),
        'dibuat' => substr((string)$t['dibuat'], 0, 10), 'bayarPertama' => $t['bayar_pertama'] ? substr((string)$t['bayar_pertama'], 0, 10) : '',
        'aktifTerakhir' => $t['aktif_terakhir'] ? substr((string)$t['aktif_terakhir'], 0, 16) : '', 'catatan' => (string)$t['catatan']];
}
function pz_dash(array $d, array $a): array
{
    $tok = mrows('SELECT * FROM s_toko'); $n = array_fill_keys(array_keys(SAAS_STATUS_NAMA), 0); $mrr = 0; $habis = [];
    foreach ($tok as $t) {
        $st = saas_status($t); $n[$st['kode']]++;
        if (in_array($st['kode'], ['aktif', 'tenggang'], true)) $mrr += (int)(saas_paket((string)$t['paket_id'])['harga_bulan'] ?? 0);
        if (in_array($st['kode'], ['aktif', 'trial', 'tenggang'], true) && $st['sisa'] <= 7) $habis[] = pz_toko_out($t);
    }
    usort($habis, fn($x, $y) => $x['sisa'] <=> $y['sisa']);
    $bln = []; $mulai = date('Y-m-01', strtotime(date('Y-m-01') . ' -11 month'));
    for ($i = 0; $i < 12; $i++) $bln[date('Y-m', strtotime($mulai . ' +' . $i . ' month'))] = 0;
    foreach (mrows("SELECT DATE_FORMAT(dibayar, '%Y-%m') b, SUM(total) s FROM s_tagihan WHERE status = 'dibayar' AND dibayar >= ? GROUP BY b", [$mulai . ' 00:00:00']) as $r) if (isset($bln[$r['b']])) $bln[$r['b']] = (int)$r['s'];
    $daftar = []; for ($i = 29; $i >= 0; $i--) $daftar[date('Y-m-d', strtotime("-$i day"))] = 0;
    foreach (mrows('SELECT DATE(dibuat) d, COUNT(*) n FROM s_toko WHERE dibuat >= ? GROUP BY d', [array_key_first($daftar) . ' 00:00:00']) as $r) if (isset($daftar[$r['d']])) $daftar[$r['d']] = (int)$r['n'];
    $lama = array_filter($tok, fn($t) => (int)$t['trial'] === 0 || (string)$t['berakhir'] < today_s());
    $konv = count($lama) ? round(100 * count(array_filter($lama, fn($t) => $t['bayar_pertama'])) / count($lama), 1) : 0;
    $per = [];
    foreach (saas_paket_all() as $p) $per[$p['id']] = ['nama' => $p['nama'], 'n' => 0];
    foreach ($tok as $t) { $st = saas_status($t); if (in_array($st['kode'], ['aktif', 'tenggang'], true) && isset($per[$t['paket_id']])) $per[$t['paket_id']]['n']++; }
    return [
        'jumlah' => $n, 'total' => count($tok), 'mrr' => $mrr,
        'bulanIni' => (int)mval("SELECT COALESCE(SUM(total),0) FROM s_tagihan WHERE status = 'dibayar' AND dibayar >= ?", [date('Y-m-01') . ' 00:00:00']),
        'bulanLalu' => (int)mval("SELECT COALESCE(SUM(total),0) FROM s_tagihan WHERE status = 'dibayar' AND dibayar >= ? AND dibayar < ?", [date('Y-m-01', strtotime(date('Y-m-01') . ' -1 month')) . ' 00:00:00', date('Y-m-01') . ' 00:00:00']),
        'baru30' => array_sum($daftar), 'konversi' => $konv, 'grafik' => $bln, 'daftar' => $daftar, 'perPaket' => array_values($per),
        'menunggu' => (int)mval("SELECT COUNT(*) FROM s_tagihan WHERE status = 'menunggu' AND bukti <> ''"),
        'akanHabis' => array_slice($habis, 0, 15),
        'terbaru' => array_map('pz_toko_out', mrows('SELECT * FROM s_toko ORDER BY dibuat DESC LIMIT 6')),
        'waSiap' => sset('waToken') !== '', 'adminWa' => sset('adminWa') !== '',
    ];
}
function pz_toko(array $d, array $a): array
{
    $q = '%' . trim((string)($d['q'] ?? '')) . '%'; $f = (string)($d['status'] ?? '');
    $o = [];
    foreach (mrows('SELECT * FROM s_toko WHERE nama LIKE ? OR slug LIKE ? OR pemilik LIKE ? OR wa LIKE ? ORDER BY dibuat DESC', [$q, $q, $q, $q]) as $t) {
        $x = pz_toko_out($t);
        if ($f === '' || $x['status'] === $f || ($f === 'habis7' && $x['sisa'] <= 7 && in_array($x['status'], ['trial', 'aktif', 'tenggang'], true))) $o[] = $x;
    }
    return ['toko' => $o, 'paket' => array_map('saas_paket_out', saas_paket_all())];
}
function pz_tokoDetail(array $d, array $a): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$t) throw new AppErr('Toko tidak ditemukan.');
    $pakai = null;
    try {
        $pakai = saas_di_toko($t, fn() => [
            'outlet' => (int)val("SELECT COUNT(*) FROM lokasi WHERE tipe = 'toko' AND aktif = 1"), 'user' => (int)val("SELECT COUNT(*) FROM users WHERE status = 'aktif'"),
            'produk' => (int)val('SELECT COUNT(*) FROM produk'), 'trx30' => (int)val("SELECT COUNT(*) FROM transaksi WHERE status <> 'Batal' AND tanggal >= ?", [date('Y-m-d', strtotime('-30 day'))]),
            'omzet30' => (int)val("SELECT COALESCE(SUM(total),0) FROM transaksi WHERE status <> 'Batal' AND tanggal >= ?", [date('Y-m-d', strtotime('-30 day'))]),
            'loginTerakhir' => (string)(val('SELECT MAX(login_terakhir) FROM users') ?? ''),
        ]);
    } catch (Throwable $e) { $pakai = ['err' => 'Database toko tidak dapat dibaca: ' . $e->getMessage()]; }
    $ref = $t['ref_oleh'] ? mrow('SELECT nama, slug FROM s_toko WHERE id = ?', [$t['ref_oleh']]) : null;
    return ['toko' => pz_toko_out($t), 'pakai' => $pakai, 'refDari' => $ref, 'diajak' => array_map(fn($x) => ['nama' => $x['nama'], 'slug' => $x['slug'], 'bayar' => (bool)$x['bayar_pertama']], mrows('SELECT nama, slug, bayar_pertama FROM s_toko WHERE ref_oleh = ?', [$t['id']])),
        'tagihan' => array_map('saas_tagihan_out', mrows('SELECT * FROM s_tagihan WHERE toko_id = ? ORDER BY dibuat DESC', [$t['id']])),
        'log' => mrows('SELECT waktu, oleh, aksi, detail FROM s_log WHERE toko_id = ? ORDER BY id DESC LIMIT 40', [$t['id']]), 'paket' => array_map('saas_paket_out', saas_paket_all())];
}
function pz_tokoSimpan(array $d, array $a): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$t) throw new AppErr('Toko tidak ditemukan.');
    $nama = str($d['nama'] ?? $t['nama'], 100); if ($nama === '') throw new AppErr('Nama toko wajib diisi.');
    $wa = isset($d['wa']) ? saas_wa_nomor((string)$d['wa']) : $t['wa']; if ($wa === '') throw new AppErr('Nomor WhatsApp tidak valid.');
    $paket = (string)($d['paketId'] ?? $t['paket_id']); if (!saas_paket($paket)) throw new AppErr('Paket tidak ditemukan.');
    $akhir = (string)($d['berakhir'] ?? $t['berakhir']); if (!is_date($akhir)) throw new AppErr('Tanggal berakhir tidak valid.');
    $trial = !empty($d['trial']) ? 1 : 0;
    mq('UPDATE s_toko SET nama = ?, pemilik = ?, wa = ?, email = ?, paket_id = ?, berakhir = ?, trial = ?, catatan = ? WHERE id = ?',
        [$nama, str($d['pemilik'] ?? $t['pemilik'], 100), $wa, str($d['email'] ?? $t['email'], 120), $paket, $akhir, $trial, str($d['catatan'] ?? (string)$t['catatan'], 2000), $t['id']]);
    $ch = [];
    foreach (['paket_id' => $paket, 'berakhir' => $akhir, 'trial' => (string)$trial, 'nama' => $nama] as $k => $v) if ((string)$t[$k] !== (string)$v) $ch[] = $k . ': ' . $t[$k] . ' → ' . $v;
    saas_log($a['username'], $t['id'], 'Ubah data toko', implode('; ', $ch) ?: '-');
    if ($nama !== $t['nama']) saas_di_toko($t, fn() => setting_put(['namaToko' => $nama, 'NamaPerusahaan' => $nama]));
    return pz_tokoDetail(['id' => $t['id']], $a);
}
function pz_tokoBeku(array $d, array $a): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [(string)($d['id'] ?? '')]); if (!$t) throw new AppErr('Toko tidak ditemukan.');
    $b = !empty($d['beku']) ? 1 : 0; $al = str($d['alasan'] ?? '', 255);
    mq('UPDATE s_toko SET beku = ?, beku_alasan = ? WHERE id = ?', [$b, $b ? $al : '', $t['id']]);
    if ($b) saas_di_toko($t, fn() => q('DELETE FROM sesi'));
    saas_log($a['username'], $t['id'], $b ? 'Toko dibekukan' : 'Toko diaktifkan lagi', $al);
    return pz_tokoDetail(['id' => $t['id']], $a);
}
function pz_tokoTambahHari(array $d, array $a): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [(string)($d['id'] ?? '')]); if (!$t) throw new AppErr('Toko tidak ditemukan.');
    $h = (int)($d['hari'] ?? 0); if ($h === 0 || abs($h) > 3650) throw new AppErr('Isi jumlah hari (boleh minus untuk mengurangi).');
    $dasar = $h > 0 ? max((string)$t['berakhir'], add_days(today_s(), -1)) : (string)$t['berakhir'];
    $baru = add_days($dasar, $h);
    mq('UPDATE s_toko SET berakhir = ? WHERE id = ?', [$baru, $t['id']]);
    saas_log($a['username'], $t['id'], 'Ubah masa aktif', ($h > 0 ? '+' : '') . $h . ' hari → ' . $baru . (($d['alasan'] ?? '') !== '' ? ' · ' . str($d['alasan'], 120) : ''));
    return pz_tokoDetail(['id' => $t['id']], $a);
}
function pz_tokoBuat(array $d, array $a): array
{
    $wa = saas_wa_nomor((string)($d['wa'] ?? '')); if ($wa === '') throw new AppErr('Nomor WhatsApp tidak valid.');
    $x = saas_data_toko($d, $wa);
    $t = saas_buat_toko($x + ['ref_oleh' => ''], $a['username']);
    return ['toko' => pz_toko_out($t), 'username' => $x['username']];
}
function pz_tokoHapus(array $d, array $a): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [(string)($d['id'] ?? '')]); if (!$t) throw new AppErr('Toko tidak ditemukan.');
    if (strtolower(trim((string)($d['konfirmasi'] ?? ''))) !== $t['slug']) throw new AppErr('Ketik alamat toko "' . $t['slug'] . '" untuk konfirmasi hapus permanen.');
    mdb()->exec('DROP DATABASE IF EXISTS `' . preg_replace('/[^a-z0-9_]/', '', (string)$t['db_name']) . '`');
    $dir = dirname(__DIR__) . '/uploads/t/' . preg_replace('/[^a-z0-9-]/', '', (string)$t['slug']);
    if ($t['slug'] !== '' && is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }
    mq('DELETE FROM s_toko WHERE id = ?', [$t['id']]);
    mq("UPDATE s_tagihan SET status = 'batal', catatan = 'Toko dihapus' WHERE toko_id = ? AND status = 'menunggu'", [$t['id']]);
    saas_log($a['username'], $t['id'], 'Toko dihapus permanen', $t['nama'] . ' · ' . $t['slug']);
    return ['ok' => true];
}
function pz_masuk(array $d, array $a): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [(string)($d['id'] ?? '')]); if (!$t) throw new AppErr('Toko tidak ditemukan.');
    if ((int)$t['beku'] === 1) throw new AppErr('Toko sedang dibekukan. Aktifkan dulu untuk masuk.');
    $k = bin2hex(random_bytes(24));
    mq('INSERT INTO s_masuk (kode, toko_id, oleh, sampai) VALUES (?,?,?,?)', [$k, $t['id'], $a['username'], date('Y-m-d H:i:s', time() + 120)]);
    mq('DELETE FROM s_masuk WHERE sampai < ?', [date('Y-m-d H:i:s', time() - 86400)]);
    saas_log($a['username'], $t['id'], 'Membuat tautan masuk sebagai toko');
    return ['url' => saas_url($t['slug']) . '/masuk.html#k=' . $k];
}
/* ---- paket ---- */
function pz_paket(array $d, array $a): array
{
    $pakai = [];
    foreach (mrows('SELECT paket_id, COUNT(*) n FROM s_toko GROUP BY paket_id') as $r) $pakai[$r['paket_id']] = (int)$r['n'];
    return ['paket' => array_map(fn($p) => saas_paket_out($p) + ['dipakai' => $pakai[$p['id']] ?? 0], saas_paket_all()), 'modul' => MODUL, 'trialPaket' => sset('trialPaket')];
}
function pz_paketSimpan(array $d, array $a): array
{
    $id = (string)($d['id'] ?? '');
    $nama = str($d['nama'] ?? '', 40); if ($nama === '') throw new AppErr('Nama paket wajib diisi.');
    $hb = max(0, (int)num($d['hargaBulan'] ?? 0)); $ht = max(0, (int)num($d['hargaTahun'] ?? 0));
    $modul = array_values(array_intersect(array_keys(MODUL), (array)($d['modul'] ?? [])));
    if (!$modul) throw new AppErr('Pilih minimal satu menu untuk paket ini.');
    $vals = [$nama, str($d['deskripsi'] ?? '', 255), $hb, $ht, json_encode($modul), max(0, (int)($d['maxOutlet'] ?? 0)), max(0, (int)($d['maxUser'] ?? 0)), max(0, (int)($d['maxProduk'] ?? 0)),
        !isset($d['aktif']) || !empty($d['aktif']) ? 1 : 0, !empty($d['unggulan']) ? 1 : 0, (int)($d['urut'] ?? 0)];
    if ($id !== '') {
        if (!saas_paket($id)) throw new AppErr('Paket tidak ditemukan.');
        if (!$vals[8] && $id === sset('trialPaket')) throw new AppErr('Paket ini dipakai untuk masa coba. Ganti paket masa coba di Pengaturan dulu.');
        mq('UPDATE s_paket SET nama=?, deskripsi=?, harga_bulan=?, harga_tahun=?, modul=?, max_outlet=?, max_user=?, max_produk=?, aktif=?, unggulan=?, urut=? WHERE id=?', array_merge($vals, [$id]));
    } else {
        $id = 'P' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $nama), 0, 8)) . random_int(10, 99);
        mq('INSERT INTO s_paket (nama, deskripsi, harga_bulan, harga_tahun, modul, max_outlet, max_user, max_produk, aktif, unggulan, urut, id, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [$id, now_s()]));
    }
    if ($vals[9]) mq('UPDATE s_paket SET unggulan = 0 WHERE id <> ?', [$id]);
    unset($GLOBALS['__paketCache']);
    saas_log($a['username'], '', 'Simpan paket', $nama . ' · ' . saas_rp($hb) . '/bln · ' . implode(',', $modul));
    return pz_paket([], $a);
}
function pz_paketHapus(array $d, array $a): array
{
    $id = (string)($d['id'] ?? '');
    if ($id === sset('trialPaket')) throw new AppErr('Paket ini dipakai untuk masa coba. Ganti paket masa coba di Pengaturan dulu.');
    if ((int)mval('SELECT COUNT(*) FROM s_toko WHERE paket_id = ?', [$id])) throw new AppErr('Paket masih dipakai toko. Nonaktifkan saja agar tidak bisa dipilih lagi.');
    if ((int)mval("SELECT COUNT(*) FROM s_tagihan WHERE paket_id = ? AND status = 'menunggu'", [$id])) throw new AppErr('Masih ada tagihan menunggu untuk paket ini.');
    mq('DELETE FROM s_paket WHERE id = ?', [$id]);
    unset($GLOBALS['__paketCache']);
    saas_log($a['username'], '', 'Hapus paket', $id);
    return pz_paket([], $a);
}
/* ---- tagihan ---- */
function pz_tagihan(array $d, array $a): array
{
    $f = (string)($d['status'] ?? ''); $w = []; $p = [];
    if ($f === 'periksa') $w[] = "b.status = 'menunggu' AND b.bukti <> ''";
    elseif (in_array($f, ['menunggu', 'dibayar', 'batal'], true)) { $w[] = 'b.status = ?'; $p[] = $f; }
    if (!empty($d['q'])) { $w[] = '(b.no LIKE ? OR t.nama LIKE ? OR t.slug LIKE ?)'; $q = '%' . $d['q'] . '%'; array_push($p, $q, $q, $q); }
    $rows = mrows('SELECT b.*, t.nama toko_nama, t.slug toko_slug FROM s_tagihan b LEFT JOIN s_toko t ON t.id = b.toko_id' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY (b.status = \'menunggu\' AND b.bukti <> \'\') DESC, b.dibuat DESC LIMIT 300', $p);
    return ['tagihan' => array_map(fn($b) => saas_tagihan_out($b, true), $rows), 'periksa' => (int)mval("SELECT COUNT(*) FROM s_tagihan WHERE status = 'menunggu' AND bukti <> ''")];
}
function pz_bukti(array $d, array $a): array
{
    $b = mrow('SELECT * FROM s_tagihan WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$b || $b['bukti'] === '') throw new AppErr('Bukti bayar tidak ada.');
    $f = saas_data_dir('bukti') . '/' . basename((string)$b['bukti']);
    if (!is_file($f)) throw new AppErr('File bukti tidak ditemukan di server.');
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'][$ext] ?? 'application/octet-stream';
    return ['mime' => $mime, 'data' => 'data:' . $mime . ';base64,' . base64_encode((string)file_get_contents($f)), 'ket' => $b['bukti_ket']];
}
function pz_konfirmasi(array $d, array $a): array
{
    $per = saas_lunas((string)($d['id'] ?? ''), $a['username']);
    return ['periode' => $per] + pz_tagihan(['status' => $d['filter'] ?? ''], $a);
}
function pz_tolak(array $d, array $a): array
{
    $b = mrow('SELECT b.*, t.wa, t.nama tnama, t.slug FROM s_tagihan b JOIN s_toko t ON t.id = b.toko_id WHERE b.id = ?', [(string)($d['id'] ?? '')]);
    if (!$b || $b['status'] !== 'menunggu') throw new AppErr('Tagihan tidak dapat diproses.');
    $al = str($d['alasan'] ?? '', 200); if ($al === '') throw new AppErr('Tuliskan alasan penolakan agar toko tahu yang harus diperbaiki.');
    mq("UPDATE s_tagihan SET catatan = ?, bukti = '', bukti_pada = NULL WHERE id = ?", ['Bukti ditolak: ' . $al, $b['id']]);
    if ($b['bukti'] !== '') @unlink(saas_data_dir('bukti') . '/' . basename((string)$b['bukti']));
    saas_log($a['username'], $b['toko_id'], 'Bukti bayar ditolak', $b['no'] . ' · ' . $al);
    saas_wa_nanti($b['wa'], "⚠️ Bukti bayar " . $b['no'] . " (" . $b['tnama'] . ") belum bisa kami terima.\nAlasan: " . $al . "\n\nSilakan unggah ulang di menu Langganan: " . saas_url($b['slug']) . '/langganan.html');
    return pz_tagihan(['status' => $d['filter'] ?? ''], $a);
}
function pz_tagihanBatal(array $d, array $a): array
{
    $b = mrow('SELECT * FROM s_tagihan WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$b || $b['status'] !== 'menunggu') throw new AppErr('Hanya tagihan menunggu yang bisa dibatalkan.');
    mq("UPDATE s_tagihan SET status = 'batal', catatan = ? WHERE id = ?", ['Dibatalkan pengelola' . (($d['alasan'] ?? '') !== '' ? ': ' . str($d['alasan'], 150) : ''), $b['id']]);
    saas_log($a['username'], $b['toko_id'], 'Tagihan dibatalkan', $b['no']);
    return pz_tagihan(['status' => $d['filter'] ?? ''], $a);
}
function pz_tagihanBuat(array $d, array $a): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [(string)($d['tokoId'] ?? '')]); if (!$t) throw new AppErr('Pilih toko.');
    $b = saas_tagihan_buat($t, (string)($d['paket'] ?? ''), (int)($d['bulan'] ?? 1), (string)($d['voucher'] ?? ''), $a['username'], str($d['catatan'] ?? '', 200));
    if (!empty($d['lunas']) && $b['status'] === 'menunggu') saas_lunas($b['id'], $a['username']);
    elseif ($b['status'] === 'menunggu') saas_wa_nanti($t['wa'], "🧾 Tagihan " . $b['no'] . " untuk *" . $t['nama'] . "*\nPaket " . $b['paket_nama'] . " · " . $b['bulan'] . " bulan · " . saas_rp($b['total']) . "\n\nBayar & unggah bukti di: " . saas_url($t['slug']) . '/langganan.html');
    return pz_tagihan([], $a) + ['baru' => saas_tagihan_out(mrow('SELECT * FROM s_tagihan WHERE id = ?', [$b['id']]))];
}
function pz_hitung(array $d, array $a): array
{
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [(string)($d['tokoId'] ?? '')]); if (!$t) throw new AppErr('Pilih toko.');
    $h = saas_hitung($t, (string)($d['paket'] ?? ''), (int)($d['bulan'] ?? 1), (string)($d['voucher'] ?? ''));
    $per = saas_periode($t, $h['paket'], $h['bulan']); unset($h['paket']);
    return $h + ['periode' => $per];
}
function pz_invoice(array $d, array $a): array
{
    $b = mrow('SELECT * FROM s_tagihan WHERE id = ?', [(string)($d['id'] ?? '')]); if (!$b) throw new AppErr('Tagihan tidak ditemukan.');
    $t = mrow('SELECT * FROM s_toko WHERE id = ?', [$b['toko_id']]) ?? ['nama' => '(toko dihapus)', 'pemilik' => '', 'wa' => '', 'email' => '', 'slug' => ''];
    return saas_invoice_data($b, $t);
}
/* ---- voucher ---- */
function pz_voucher(array $d, array $a): array
{
    return ['voucher' => array_map(fn($v) => ['kode' => $v['kode'], 'jenis' => $v['jenis'], 'nilai' => (int)$v['nilai'], 'kuota' => (int)$v['kuota'], 'terpakai' => (int)$v['terpakai'],
        'sampai' => $v['sampai'], 'paket' => json_decode((string)$v['paket'], true) ?: [], 'minBulan' => (int)$v['min_bulan'], 'aktif' => (int)$v['aktif'] === 1, 'ket' => $v['ket'],
        'habis' => ($v['sampai'] && $v['sampai'] < today_s()) || ((int)$v['kuota'] > 0 && (int)$v['terpakai'] >= (int)$v['kuota'])], mrows('SELECT * FROM s_voucher ORDER BY dibuat DESC')),
        'paket' => array_map('saas_paket_out', saas_paket_all())];
}
function pz_voucherSimpan(array $d, array $a): array
{
    $k = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string)($d['kode'] ?? '')));
    if (strlen($k) < 3 || strlen($k) > 30) throw new AppErr('Kode voucher 3–30 huruf/angka.');
    $jenis = ($d['jenis'] ?? '') === 'nominal' ? 'nominal' : 'persen';
    $nilai = max(0, (int)num($d['nilai'] ?? 0)); if ($nilai <= 0) throw new AppErr('Isi nilai potongan.');
    if ($jenis === 'persen' && $nilai > 100) throw new AppErr('Potongan persen maksimal 100.');
    $sampai = (string)($d['sampai'] ?? ''); if ($sampai !== '' && !is_date($sampai)) throw new AppErr('Tanggal berlaku tidak valid.');
    $paket = array_values(array_filter((array)($d['paket'] ?? []), fn($p) => saas_paket((string)$p)));
    $vals = [$jenis, $nilai, max(0, (int)($d['kuota'] ?? 0)), $sampai ?: null, $paket ? json_encode($paket) : null, max(1, (int)($d['minBulan'] ?? 1)), !isset($d['aktif']) || !empty($d['aktif']) ? 1 : 0, str($d['ket'] ?? '', 160)];
    $lama = (string)($d['lama'] ?? '');
    if ($lama !== '') {
        if ($lama !== $k) throw new AppErr('Kode voucher tidak bisa diganti. Buat voucher baru.');
        mq('UPDATE s_voucher SET jenis=?, nilai=?, kuota=?, sampai=?, paket=?, min_bulan=?, aktif=?, ket=? WHERE kode=?', array_merge($vals, [$k]));
    } else {
        if (mval('SELECT kode FROM s_voucher WHERE kode = ?', [$k])) throw new AppErr('Kode voucher sudah ada.');
        mq('INSERT INTO s_voucher (jenis, nilai, kuota, sampai, paket, min_bulan, aktif, ket, kode, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [$k, now_s()]));
    }
    saas_log($a['username'], '', 'Simpan voucher', $k);
    return pz_voucher([], $a);
}
function pz_voucherHapus(array $d, array $a): array
{
    $k = (string)($d['kode'] ?? '');
    if ((int)mval("SELECT COUNT(*) FROM s_tagihan WHERE voucher = ? AND status <> 'batal'", [$k])) throw new AppErr('Voucher sudah dipakai di tagihan. Nonaktifkan saja.');
    mq('DELETE FROM s_voucher WHERE kode = ?', [$k]); saas_log($a['username'], '', 'Hapus voucher', $k);
    return pz_voucher([], $a);
}
/* ---- pengumuman ---- */
function pz_pengumuman(array $d, array $a): array
{
    return ['pengumuman' => array_map(fn($p) => ['id' => $p['id'], 'judul' => $p['judul'], 'isi' => (string)$p['isi'], 'tipe' => $p['tipe'], 'target' => $p['target'], 'mulai' => $p['mulai'], 'sampai' => $p['sampai'],
        'aktif' => (int)$p['aktif'] === 1, 'tayang' => (int)$p['aktif'] === 1 && $p['mulai'] <= today_s() && (!$p['sampai'] || $p['sampai'] >= today_s())], mrows('SELECT * FROM s_pengumuman ORDER BY mulai DESC, dibuat DESC')),
        'paket' => array_map('saas_paket_out', saas_paket_all())];
}
function pz_pengumumanSimpan(array $d, array $a): array
{
    $judul = str($d['judul'] ?? '', 120); if ($judul === '') throw new AppErr('Judul pengumuman wajib diisi.');
    $tipe = in_array($d['tipe'] ?? '', ['info', 'penting', 'promo'], true) ? $d['tipe'] : 'info';
    $tg = (string)($d['target'] ?? 'semua'); if (!in_array($tg, ['semua', 'trial', 'bayar'], true) && !(strpos($tg, 'paket:') === 0 && saas_paket(substr($tg, 6)))) $tg = 'semua';
    $mulai = (string)($d['mulai'] ?? today_s()); if (!is_date($mulai)) $mulai = today_s();
    $sampai = (string)($d['sampai'] ?? ''); if ($sampai !== '' && (!is_date($sampai) || $sampai < $mulai)) throw new AppErr('Tanggal selesai tidak valid.');
    $vals = [$judul, mb_substr((string)($d['isi'] ?? ''), 0, 3000), $tipe, $tg, $mulai, $sampai ?: null, !isset($d['aktif']) || !empty($d['aktif']) ? 1 : 0];
    $id = (string)($d['id'] ?? '');
    if ($id !== '') mq('UPDATE s_pengumuman SET judul=?, isi=?, tipe=?, target=?, mulai=?, sampai=?, aktif=? WHERE id=?', array_merge($vals, [$id]));
    else mq('INSERT INTO s_pengumuman (judul, isi, tipe, target, mulai, sampai, aktif, id, dibuat) VALUES (?,?,?,?,?,?,?,?,?)', array_merge($vals, [uid('N'), now_s()]));
    saas_log($a['username'], '', 'Simpan pengumuman', $judul);
    return pz_pengumuman([], $a);
}
function pz_pengumumanHapus(array $d, array $a): array { mq('DELETE FROM s_pengumuman WHERE id = ?', [(string)($d['id'] ?? '')]); saas_log($a['username'], '', 'Hapus pengumuman', (string)($d['id'] ?? '')); return pz_pengumuman([], $a); }
/* ---- pengaturan ---- */
const PZ_SET_KEYS = ['brand', 'trialHari', 'trialPaket', 'tenggangHari', 'tenggangTrial', 'ingatHari', 'rekening', 'kontakWa', 'adminWa', 'waProvider', 'waDomain', 'waUrl',
    'otpAktif', 'daftarAktif', 'maksTokoPerWa', 'refBonusHari', 'refDiskon'];
function pz_set(array $d, array $a): array
{
    $s = sset_all(); $o = [];
    foreach (PZ_SET_KEYS as $k) $o[$k] = $s[$k];
    $o['qris'] = $s['qris']; $o['adaToken'] = $s['waToken'] !== ''; $o['token'] = $s['waToken'] !== '' ? '••••••' . substr($s['waToken'], -4) : '';
    $base = saas_url();
    return ['set' => $o, 'paket' => array_map('saas_paket_out', saas_paket_all()),
        'cron' => ['cli' => 'php ' . dirname(__DIR__) . '/cron.php', 'url' => $base . '/cron.php?key=' . $s['cronKey'], 'terakhir' => $s['harianTerakhir']],
        'admins' => array_map(fn($x) => ['id' => $x['id'], 'username' => $x['username'], 'nama' => $x['nama'], 'aktif' => (int)$x['aktif'] === 1, 'login' => $x['login_terakhir']], mrows('SELECT * FROM s_admin ORDER BY dibuat'))];
}
function pz_setSimpan(array $d, array $a): array
{
    $put = [];
    foreach (PZ_SET_KEYS as $k) {
        if (!array_key_exists($k, $d)) continue;
        $v = trim((string)$d[$k]);
        switch ($k) {
            case 'brand': if ($v === '') throw new AppErr('Nama layanan wajib diisi.'); $v = mb_substr($v, 0, 40); break;
            case 'trialHari': case 'tenggangHari': case 'tenggangTrial': case 'refBonusHari': $v = (string)max(0, min(365, (int)$v)); break;
            case 'refDiskon': $v = (string)max(0, min(100, (int)$v)); break;
            case 'maksTokoPerWa': $v = (string)max(1, min(50, (int)$v)); break;
            case 'trialPaket': if (!saas_paket($v)) throw new AppErr('Paket masa coba tidak ditemukan.'); break;
            case 'ingatHari': $x = array_unique(array_filter(array_map('intval', preg_split('/[,\s]+/', $v)), fn($n) => $n >= 0 && $n <= 60)); rsort($x); $v = implode(',', $x); break;
            case 'otpAktif': case 'daftarAktif': $v = $v === '1' || $v === 'true' ? '1' : '0'; break;
            case 'waProvider': $v = $v === 'wablas' ? 'wablas' : 'fonnte'; break;
            case 'kontakWa': case 'adminWa': $v = implode(', ', array_filter(array_map('saas_wa_nomor', preg_split('/[,;\s]+/', $v)))); break;
            case 'rekening': $v = mb_substr($v, 0, 1000); break;
            default: $v = mb_substr($v, 0, 200);
        }
        $put[$k] = $v;
    }
    if (isset($d['token']) && $d['token'] !== '' && strpos((string)$d['token'], '•') === false) $put['waToken'] = str($d['token'], 200);
    if (!empty($d['hapusToken'])) $put['waToken'] = '';
    if (isset($d['secret']) && strpos((string)$d['secret'], '•') === false) $put['waSecret'] = str($d['secret'], 200);
    if (!empty($d['qrisFile'])) {
        [$bin, $ext] = saas_gambar((string)$d['qrisFile'], 1500);
        if ($ext === 'pdf') throw new AppErr('QRIS harus berupa gambar.');
        $dir = dirname(__DIR__) . '/uploads/pusat'; if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $nm = 'qris-' . bin2hex(random_bytes(5)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $nm, $bin) === false) throw new AppErr('Gagal menyimpan gambar QRIS.');
        $old = sset('qris'); if ($old !== '' && strpos($old, 'uploads/pusat/') === 0) @unlink(dirname(__DIR__) . '/' . $old);
        $put['qris'] = 'uploads/pusat/' . $nm;
    }
    if (!empty($d['hapusQris'])) { $old = sset('qris'); if ($old !== '') @unlink(dirname(__DIR__) . '/' . $old); $put['qris'] = ''; }
    if (($put['otpAktif'] ?? sset('otpAktif')) === '1' && ($put['waToken'] ?? sset('waToken')) === '') throw new AppErr('Verifikasi WA saat daftar butuh token gateway WhatsApp. Isi token dulu atau matikan verifikasi.');
    sset_put($put);
    saas_log($a['username'], '', 'Ubah pengaturan', implode(', ', array_keys($put)));
    return pz_set([], $a);
}
function pz_waTes(array $d, array $a): array
{
    $to = (string)($d['ke'] ?? sset('adminWa'));
    [$ok, $info] = saas_wa($to, '✅ Tes WhatsApp ' . sset('brand') . ' berhasil. Gateway siap mengirim kode verifikasi & pengingat tagihan.');
    if (!$ok) throw new AppErr('Gagal: ' . $info);
    return ['info' => $info];
}
function pz_adminSimpan(array $d, array $a): array
{
    $un = strtolower(trim((string)($d['username'] ?? ''))); $nama = str($d['nama'] ?? '', 100); $pw = (string)($d['password'] ?? '');
    $id = (string)($d['id'] ?? '');
    if ($id === '') {
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $un)) throw new AppErr('Username 3–30 karakter.');
        if (mval('SELECT id FROM s_admin WHERE username = ?', [$un])) throw new AppErr('Username sudah dipakai.');
        if (mb_strlen($pw) < 8) throw new AppErr('Password minimal 8 karakter.');
        mq('INSERT INTO s_admin (id, username, nama, pass_hash, aktif, dibuat) VALUES (?,?,?,?,1,?)', [uid('A'), $un, $nama ?: $un, password_hash($pw, PASSWORD_DEFAULT), now_s()]);
        saas_log($a['username'], '', 'Tambah pengelola', $un);
    } else {
        $x = mrow('SELECT * FROM s_admin WHERE id = ?', [$id]); if (!$x) throw new AppErr('Pengelola tidak ditemukan.');
        $akt = !empty($d['aktif']) ? 1 : 0;
        if ($x['id'] === $a['id'] && !$akt) throw new AppErr('Tidak dapat menonaktifkan akun sendiri.');
        mq('UPDATE s_admin SET nama = ?, aktif = ? WHERE id = ?', [$nama ?: $x['nama'], $akt, $id]);
        if ($pw !== '') { if (mb_strlen($pw) < 8) throw new AppErr('Password minimal 8 karakter.'); mq('UPDATE s_admin SET pass_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]); mq('DELETE FROM s_sesi WHERE admin_id = ?', [$id]); }
        if (!$akt) mq('DELETE FROM s_sesi WHERE admin_id = ?', [$id]);
        saas_log($a['username'], '', 'Ubah pengelola', $x['username']);
    }
    return pz_set([], $a);
}
function pz_log(array $d, array $a): array
{
    return ['log' => mrows('SELECT l.waktu, l.oleh, l.aksi, l.detail, l.toko_id, t.nama toko FROM s_log l LEFT JOIN s_toko t ON t.id = l.toko_id ORDER BY l.id DESC LIMIT 300')];
}
function pz_harian(array $d, array $a): array { return ['hasil' => saas_harian(true)]; }

/* ============================================================
 *  TUGAS HARIAN: pengingat WA & perubahan status
 * ============================================================ */
function saas_harian(bool $paksa = false): array
{
    if (!$paksa && sset('harianTerakhir') === today_s()) return [];
    $got = (int)mval('SELECT GET_LOCK(?, 1)', ['dhyra_saas_harian']);
    if ($got !== 1) return [];
    $hasil = [];
    try {
        if (!$paksa && sset_all(true)['harianTerakhir'] === today_s()) return [];
        sset_put(['harianTerakhir' => today_s()]);
        $ingat = array_values(array_filter(array_map('intval', explode(',', sset('ingatHari'))), fn($n) => $n >= 0)); rsort($ingat);
        foreach (mrows('SELECT * FROM s_toko WHERE beku = 0') as $t) {
            $st = saas_status($t); $url = saas_url($t['slug']) . '/langganan.html'; $msg = ''; $jenis = '';
            $kirim = function (string $j) use ($t) { return (bool)mq('INSERT IGNORE INTO s_kirim (toko_id, jenis, tanggal) VALUES (?,?,?)', [$t['id'], $j, $t['berakhir']])->rowCount(); };
            $sudah = fn(string $j) => (bool)mval('SELECT 1 FROM s_kirim WHERE toko_id = ? AND jenis = ? AND tanggal = ?', [$t['id'], $j, $t['berakhir']]);
            $apa = $st['trial'] ? 'Masa coba gratis' : 'Langganan paket ' . (saas_paket((string)$t['paket_id'])['nama'] ?? '');
            if ($st['sisa'] >= 0) {
                // pengingat: kirim hanya yang terdekat; yang lebih jauh dianggap sudah lewat
                $pilih = null;
                foreach ($ingat as $n) if ($st['sisa'] <= $n && !$sudah('ingat' . $n)) $pilih = $n;
                if ($pilih !== null) {
                    foreach ($ingat as $n) if ($n >= $pilih) $kirim('ingat' . $n);
                    $jenis = 'ingat' . $pilih;
                    $msg = "⏰ " . $apa . " toko *" . $t['nama'] . "* berakhir " . ($st['sisa'] === 0 ? '*hari ini*' : 'dalam *' . $st['sisa'] . ' hari* (' . dmy($st['berakhir']) . ')') . ".\n\n" .
                        ($st['trial'] ? "Pilih paket & bayar agar toko tetap bisa dipakai:" : "Perpanjang sekarang agar toko tidak terkunci:") . "\n" . $url;
                }
            } elseif ($st['kode'] === 'tenggang' && $kirim('habis')) {
                $jenis = 'habis';
                $msg = "⚠️ " . $apa . " toko *" . $t['nama'] . "* sudah berakhir " . dmy($st['berakhir']) . ".\nToko masih bisa dipakai sampai *" . dmy($st['tenggangSampai']) . "* (masa tenggang). Setelah itu toko masuk mode baca-saja: data aman, tetapi transaksi dikunci.\n\nPerpanjang: " . $url;
            } elseif ($st['kode'] === 'baca' && $kirim('baca')) {
                $kirim('habis');
                $jenis = 'baca';
                $msg = "🔒 Toko *" . $t['nama'] . "* sekarang dalam mode *baca-saja* karena " . mb_strtolower($apa) . " sudah berakhir.\nData Anda tetap aman dan laporan masih bisa dilihat. Transaksi akan terbuka lagi begitu pembayaran dikonfirmasi.\n\nPerpanjang: " . $url;
            }
            if ($msg !== '') {
                [$ok, $info] = sset('waToken') !== '' ? saas_wa($t['wa'], $msg) : [false, 'gateway belum diatur'];
                saas_log('sistem', $t['id'], 'Pengingat WA ' . $jenis, ($ok ? 'terkirim' : 'gagal: ' . $info));
                $hasil[] = ['toko' => $t['nama'], 'jenis' => $jenis, 'ok' => $ok, 'info' => $info];
            }
        }
        mq('DELETE FROM s_otp WHERE sampai < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        mq('DELETE FROM s_sesi WHERE kedaluwarsa < ?', [now_s()]);
    } finally { mq('SELECT RELEASE_LOCK(?)', ['dhyra_saas_harian']); }
    return $hasil;
}

/* ============================================================
 *  DISPATCH  { m:'saas', a, t, d }
 * ============================================================ */
const SAAS_PUBLIK = ['info' => 'sp_info', 'cekSlug' => 'sp_cekSlug', 'otpKirim' => 'sp_otpKirim', 'daftar' => 'sp_daftar'];
const SAAS_TOKO = ['lgInfo' => 'lg_info', 'lgHitung' => 'lg_hitung', 'lgBuat' => 'lg_buat', 'lgBatal' => 'lg_batal', 'lgBukti' => 'lg_bukti', 'lgInvoice' => 'lg_invoice'];
const SAAS_PUSAT = ['me' => 'pz_me', 'logout' => 'pz_logout', 'pw' => 'pz_pw', 'dash' => 'pz_dash', 'toko' => 'pz_toko', 'tokoDetail' => 'pz_tokoDetail', 'tokoSimpan' => 'pz_tokoSimpan',
    'tokoBeku' => 'pz_tokoBeku', 'tokoTambahHari' => 'pz_tokoTambahHari', 'tokoBuat' => 'pz_tokoBuat', 'tokoHapus' => 'pz_tokoHapus', 'masuk' => 'pz_masuk',
    'paket' => 'pz_paket', 'paketSimpan' => 'pz_paketSimpan', 'paketHapus' => 'pz_paketHapus',
    'tagihan' => 'pz_tagihan', 'bukti' => 'pz_bukti', 'konfirmasi' => 'pz_konfirmasi', 'tolak' => 'pz_tolak', 'tagihanBatal' => 'pz_tagihanBatal', 'tagihanBuat' => 'pz_tagihanBuat', 'hitung' => 'pz_hitung', 'invoice' => 'pz_invoice',
    'voucher' => 'pz_voucher', 'voucherSimpan' => 'pz_voucherSimpan', 'voucherHapus' => 'pz_voucherHapus',
    'pengumuman' => 'pz_pengumuman', 'pengumumanSimpan' => 'pz_pengumumanSimpan', 'pengumumanHapus' => 'pz_pengumumanHapus',
    'set' => 'pz_set', 'setSimpan' => 'pz_setSimpan', 'waTes' => 'pz_waTes', 'adminSimpan' => 'pz_adminSimpan', 'log' => 'pz_log', 'harian' => 'pz_harian'];

function saas_dispatch(string $a, array $d, ?string $t): array
{
    $ctx = saas_ctx();
    if ($a === 'masuk') return sp_masuk($d);
    if (isset(SAAS_TOKO[$a])) {
        if (!$ctx) throw new AppErr('Menu Langganan dibuka dari alamat toko.');
        $u = auth_token($t);
        if (!user_akses($u)['admin']) throw new AppErr('Menu Langganan hanya untuk Administrator toko.', 'MODUL');
        return (SAAS_TOKO[$a])($d, $u);
    }
    if ($ctx) throw new AppErr('Aksi ini hanya tersedia di halaman utama ' . sset('brand') . '.');
    if (isset(SAAS_PUBLIK[$a])) return (SAAS_PUBLIK[$a])($d);
    if ($a === 'pLogin') return pz_login($d);
    $fn = SAAS_PUSAT[lcfirst(substr($a, 1))] ?? null;
    if ($a[0] !== 'p' || !$fn) throw new AppErr('Aksi tidak dikenal');
    $adm = pz_auth($t);
    return $fn($d, $adm);
}
