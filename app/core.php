<?php
/**
 * ============================================================
 *  Dhyra — inti aplikasi (koneksi DB, utilitas, sesi, pengaturan)
 *  Dipakai oleh api.php & install.php. Folder app/ diblokir dari akses web.
 * ============================================================
 */
declare(strict_types=1);

const APP_NAME = 'Dhyra';
const APP_VERSION = '5.6.2';
const SESSION_TTL = 43200;          // sesi login 12 jam (diperpanjang otomatis selama dipakai)
const UPLOAD_DIR = __DIR__ . '/../uploads/produk';
const UPLOAD_URL = 'uploads/produk';
/** Folder unggahan; di mode SaaS tiap toko punya foldernya sendiri: uploads/t/<toko>/<sub> */
function upload_url(string $sub): string { $c = $GLOBALS['__saas'] ?? null; return 'uploads/' . ($c ? 't/' . $c['slug'] . '/' : '') . $sub; }
function upload_dir(string $sub): string { return dirname(__DIR__) . '/' . upload_url($sub); }

class AppErr extends Exception
{
    public string $tag;
    public function __construct(string $m, string $tag = '') { parent::__construct($m); $this->tag = $tag; }
}

/* ---------------------------- Konfigurasi & DB ---------------------------- */
function cfg(): array
{
    static $c = null;
    if ($c === null) {
        $f = __DIR__ . '/config.php';
        if (!is_file($f)) throw new AppErr('Aplikasi belum diinstal. Buka install.php terlebih dahulu.', 'INSTALL');
        $c = require $f;
        date_default_timezone_set($c['timezone'] ?? 'Asia/Jakarta');
    }
    // mode SaaS: database toko yang sedang dilayani (dipilih dari alamat subdomain oleh saas.php)
    $t = $GLOBALS['__tenantDb'] ?? null;
    return $t ? array_merge($c, ['db_name' => $t]) : $c;
}
/** true bila aplikasi dipasang sebagai SaaS (banyak toko, satu server). */
function is_saas(): bool { try { return !empty(cfg()['saas']['domain']); } catch (Throwable $e) { return false; } }
/** Data toko SaaS yang sedang dilayani (null = mode satu toko / halaman pusat). */
function saas_ctx(): ?array { return $GLOBALS['__saas'] ?? null; }

function pdo_connect(array $c): PDO
{
    $dsn = 'mysql:host=' . $c['db_host'] . ';port=' . (int)($c['db_port'] ?? 3306) . ';dbname=' . $c['db_name'] . ';charset=utf8mb4';
    $p = new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => true,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $p->exec("SET SESSION sql_mode = REPLACE(REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''), 'NO_ZERO_DATE', '')");
    return $p;
}

function db(): PDO
{
    static $p = [];
    $c = cfg(); $n = (string)$c['db_name'];
    if (!isset($p[$n])) $p[$n] = pdo_connect($c);
    return $p[$n];
}
function q(string $sql, array $a = []): PDOStatement { $s = db()->prepare($sql); $s->execute($a); return $s; }
function rows(string $sql, array $a = []): array { return q($sql, $a)->fetchAll(); }
function row(string $sql, array $a = []): ?array { $r = q($sql, $a)->fetch(); return $r === false ? null : $r; }
function val(string $sql, array $a = []) { $r = q($sql, $a)->fetchColumn(); return $r === false ? null : $r; }

/** Sisipkan banyak baris sekaligus. $cols = daftar kolom, $data = array of array (urutan sama). */
function insert_many(string $table, array $cols, array $data): void
{
    if (!$data) return;
    foreach (array_chunk($data, 300) as $chunk) {
        $ph = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $args = [];
        foreach ($chunk as $r) foreach ($r as $v) $args[] = $v;
        q('INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES ' . implode(',', array_fill(0, count($chunk), $ph)), $args);
    }
}

/** Kunci tulis global (setara LockService) + transaksi DB: semua perubahan sukses bersama atau batal bersama. */
function with_write(callable $fn)
{
    static $depth = 0;
    if ($depth > 0) return $fn();
    paket_cek_tulis();
    $got = (int)val('SELECT GET_LOCK(?, 25)', [lock_name()]);
    if ($got !== 1) throw new AppErr('Server sedang sibuk memproses transaksi lain. Coba lagi.');
    $depth++;
    db()->beginTransaction();
    try {
        $awal = paket_hitung();
        $r = $fn();
        if ($awal !== null) paket_cek_batas($awal);
        db()->commit();
        return $r;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    } finally {
        $depth--;
        try { q('SELECT RELEASE_LOCK(?)', [lock_name()]); } catch (Throwable $e) {}
    }
}
function lock_name(): string { return 'kpt_' . substr(md5(cfg()['db_name']), 0, 12); }

/* ---------------------------- Utilitas ---------------------------- */
function num($v): float
{
    if (is_int($v) || is_float($v)) return is_finite((float)$v) ? (float)$v : 0.0;
    $s = str_replace([' ', ','], ['', '.'], trim((string)($v ?? '')));
    return is_numeric($s) ? (float)$s : 0.0;
}
function r2(float $n): float { return round($n, 2); }
function r3(float $n): float { return round($n, 3); }
/** Angka keluaran JSON: bilangan bulat dikirim tanpa .0 */
function jn($v): float|int { $f = (float)$v; return (floor($f) == $f && abs($f) < 1e15) ? (int)$f : $f; }
function uid(string $p, int $len = 9): string { return $p . substr(bin2hex(random_bytes(8)), 0, $len); }
function now_s(): string { return date('Y-m-d H:i:s'); }
function today_s(): string { return date('Y-m-d'); }
function is_date($s): bool { return is_string($s) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) === 1 && checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4)); }
function add_days(string $s, int $n): string { return date('Y-m-d', strtotime($s . ' 12:00:00') + $n * 86400); }
function diff_days(string $a, string $b): int { return (int)round((strtotime($a . ' 12:00:00') - strtotime($b . ' 12:00:00')) / 86400); }
function dmy($s): string { return is_date($s) ? substr($s, 8, 2) . '/' . substr($s, 5, 2) . '/' . substr($s, 0, 4) : (string)($s ?? ''); }
function fmt_num($n): string
{
    $n = (float)round((float)$n);
    $s = number_format(abs($n), 0, ',', '.');
    return $n < 0 ? '(' . $s . ')' : $s;
}
function str($v, int $max = 255): string { return mb_substr(trim((string)($v ?? '')), 0, $max); }
function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }

/* ---------------------------- Pengaturan ---------------------------- */
const SET_DEFAULT = [
    // --- profil & kasir ---
    'namaToko' => 'Toko Saya', 'alamat' => 'Jl. Contoh No. 1, Kota Anda', 'telepon' => '0812-3456-7890',
    'footer' => 'Terima kasih atas kunjungan Anda', 'pajak' => '0', 'pajakNama' => 'PPN',
    'kertas' => '58', 'izinMinus' => '0', 'autoCetak' => '0',
    // --- akuntansi ---
    'NamaPerusahaan' => 'Toko Saya', 'Alamat' => 'Jl. Contoh No. 1, Kota Anda', 'Telepon' => '0812-3456-7890',
    'Email' => '', 'NPWP' => '', 'MataUang' => 'Rp', 'TarifPPN' => '11', 'KunciPeriode' => '',
    'AkunKas' => '1101', 'AkunPiutang' => '1201', 'AkunHutang' => '2101', 'AkunPPNKeluaran' => '2103', 'AkunPPNMasukan' => '1402',
    // --- integrasi kasir -> akuntansi ---
    'PosAkunTunai' => '1101', 'PosAkunQRIS' => '1102', 'PosAkunTransfer' => '1102', 'PosAkunDebit' => '1102',
    'PosAkunPenjualan' => '4101', 'PosAkunPotongan' => '4201', 'PosAkunHPP' => '5101', 'PosAkunPersediaan' => '1301',
    'PosAkunSelisih' => '5103', 'PosAkunStokAwal' => '3101', 'PosAkunBank' => '1102',
    // --- dokumen faktur ---
    'FakturCatatan' => 'Pembayaran mohon ditransfer ke: Bank BCA a/n Toko Saya, No. Rek. 000-000-0000',
    'FakturPenutup' => 'Terima kasih atas kepercayaan Anda.', 'LogoUrl' => '',
    // --- member & poin (v4) ---
    'MemberAktif' => '1', 'PoinPer' => '10000', 'PoinNilai' => '100', 'PoinMin' => '10',
    // --- SKU produk (v4): kategori = 3 huruf kategori + nomor, prefix = awalan tetap + nomor ---
    'SkuMode' => 'kategori', 'SkuPrefix' => 'DHY',
];

function settings_all(bool $fresh = false): array
{
    static $memo = null;
    if ($memo === null || $fresh) {
        $memo = SET_DEFAULT;
        foreach (rows('SELECT k, v FROM pengaturan') as $r) $memo[$r['k']] = (string)$r['v'];
    }
    return $memo;
}
function setting_put(array $kv): void
{
    foreach ($kv as $k => $v) q('INSERT INTO pengaturan (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, (string)$v]);
    settings_all(true);
}

/** Revisi data: dinaikkan setiap ada perubahan supaya layar lain ikut menyegarkan diri. */
function bump_rev(): void { setting_put(['REV' => (string)round(microtime(true) * 1000)]); }
function rev(): string { return settings_all()['REV'] ?? '0'; }

/** Nomor dokumen berurutan yang aman dipakai bersamaan. */
function counter_next(string $key): int
{
    q('INSERT INTO penomoran (k, n) VALUES (?, LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE n = LAST_INSERT_ID(n + 1)', [$key]);
    return (int)db()->lastInsertId();
}

function log_act(?array $user, string $aksi, string $modul, string $detail = ''): void
{
    q('INSERT INTO log_aktivitas (waktu, user, aksi, modul, detail) VALUES (?,?,?,?,?)', [now_s(), $user['username'] ?? '-', $aksi, $modul, mb_substr($detail, 0, 255)]);
}

/* ---------------------------- Sesi & login ---------------------------- */
function role_label(string $r): string { return ['admin' => 'Admin', 'akuntan' => 'Akuntan', 'kasir' => 'Kasir', 'viewer' => 'Viewer'][$r] ?? 'Viewer'; }
function role_norm($r): string { $r = strtolower(trim((string)$r)); return in_array($r, ['admin', 'akuntan', 'kasir', 'viewer'], true) ? $r : 'kasir'; }

function do_login(array $d): array
{
    $un = strtolower(trim((string)($d['username'] ?? '')));
    $pw = (string)($d['password'] ?? '');
    if ($un === '' || $pw === '') throw new AppErr('Isi username dan password.');
    $f = row('SELECT n, sampai FROM login_gagal WHERE username = ?', [$un]);
    if ($f && (int)$f['n'] >= 5 && strtotime($f['sampai']) > time()) throw new AppErr('Terlalu banyak percobaan gagal. Coba lagi dalam 5 menit.');
    $u = row('SELECT * FROM users WHERE username = ?', [$un]);
    if (!$u || $u['status'] !== 'aktif' || !password_verify($pw, $u['pass_hash'])) {
        $n = ($f && strtotime($f['sampai']) > time()) ? (int)$f['n'] + 1 : 1;
        q('REPLACE INTO login_gagal (username, n, sampai) VALUES (?, ?, ?)', [$un, $n, date('Y-m-d H:i:s', time() + 300)]);
        throw new AppErr('Username atau password salah.');
    }
    q('DELETE FROM login_gagal WHERE username = ?', [$un]);
    $token = bin2hex(random_bytes(32));
    q('INSERT INTO sesi (token, user_id, dibuat, kedaluwarsa) VALUES (?,?,?,?)', [$token, $u['id'], now_s(), date('Y-m-d H:i:s', time() + SESSION_TTL)]);
    q('UPDATE users SET login_terakhir = ? WHERE id = ?', [now_s(), $u['id']]);
    if (random_int(1, 20) === 1) q('DELETE FROM sesi WHERE kedaluwarsa < ?', [now_s()]);
    if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT)) q('UPDATE users SET pass_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
    return ['token' => $token, 'user' => $u, 'defaultPassword' => $pw === 'admin123' || !empty($u['ganti_pw'])];
}

function auth_token(?string $t): array
{
    if (!$t || !preg_match('/^[a-f0-9]{64}$/', $t)) throw new AppErr('Sesi berakhir. Silakan masuk kembali.', 'AUTH');
    $s = row('SELECT s.token, s.kedaluwarsa, u.* FROM sesi s JOIN users u ON u.id = s.user_id WHERE s.token = ?', [$t]);
    if (!$s || strtotime($s['kedaluwarsa']) < time() || $s['status'] !== 'aktif') {
        if ($s) q('DELETE FROM sesi WHERE token = ?', [$t]);
        throw new AppErr('Sesi berakhir. Silakan masuk kembali.', 'AUTH');
    }
    // perpanjang sesi bila sudah lewat separuh umur
    if (strtotime($s['kedaluwarsa']) - time() < SESSION_TTL / 2) q('UPDATE sesi SET kedaluwarsa = ? WHERE token = ?', [date('Y-m-d H:i:s', time() + SESSION_TTL), $t]);
    unset($s['token'], $s['kedaluwarsa']);
    return $s;
}
/* ---------------------------- Pembatasan HP: karyawan hanya Absen saya ---------------------------- */
/** HP (bukan tablet/komputer): dari tanda layar yang dikirim browser (X-Dhyra-Hp) atau User-Agent ponsel. */
function is_hp(): bool
{
    if (($_SERVER['HTTP_X_DHYRA_HP'] ?? '') === '1') return true;
    return (bool)preg_match('/iPhone|iPod|Android.*Mobile|Windows Phone|IEMobile|Opera Mini|BlackBerry|Mobile.*Firefox/i', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
}
function hp_bebas_list(): array { $a = json_decode((string)(settings_all()['HpBebas'] ?? '["JPEMILIK"]'), true); return is_array($a) ? array_values(array_filter($a, 'is_string')) : []; }
/** true = pengguna ini sedang memakai HP dan hanya boleh membuka Absen saya. */
function hp_terbatas(array $u): bool
{
    if ((settings_all()['HpAbsenSaja'] ?? '1') !== '1') return false;
    if (!empty(user_akses($u)['admin'])) return false;
    if (in_array((string)($u['jabatan_id'] ?? ''), hp_bebas_list(), true)) return false;
    return is_hp();
}
const HP_PESAN = 'Di HP, akun karyawan hanya bisa membuka menu Absen saya (absen, izin/cuti/lembur, slip gaji). Menu ini dibuka dari komputer atau tablet kasir di toko.';
function kill_sessions(string $userId, ?string $except = null): void
{
    if ($except) q('DELETE FROM sesi WHERE user_id = ? AND token <> ?', [$userId, $except]);
    else q('DELETE FROM sesi WHERE user_id = ?', [$userId]);
}
function make_pw(string $pw): string { return password_hash($pw, PASSWORD_DEFAULT); }

/* ---------------------------- Data awal ---------------------------- */
const COA_DEFAULT = [
    ['1101', 'Kas', 'Aset', 'Kas & Bank', 'Debit'], ['1102', 'Bank BCA', 'Aset', 'Kas & Bank', 'Debit'],
    ['1103', 'Bank Mandiri', 'Aset', 'Kas & Bank', 'Debit'],
    ['1201', 'Piutang Usaha', 'Aset', 'Piutang', 'Debit'], ['1202', 'Piutang Karyawan', 'Aset', 'Piutang', 'Debit'],
    ['1301', 'Persediaan Barang', 'Aset', 'Persediaan', 'Debit'],
    ['1401', 'Uang Muka Pembelian', 'Aset', 'Aset Lancar Lainnya', 'Debit'], ['1402', 'PPN Masukan', 'Aset', 'Aset Lancar Lainnya', 'Debit'],
    ['1501', 'Peralatan Kantor', 'Aset', 'Aset Tetap', 'Debit'], ['1502', 'Kendaraan', 'Aset', 'Aset Tetap', 'Debit'],
    ['1503', 'Akumulasi Penyusutan', 'Aset', 'Aset Tetap', 'Kredit'],
    ['2101', 'Hutang Usaha', 'Kewajiban', 'Hutang Usaha', 'Kredit'], ['2102', 'Hutang Gaji', 'Kewajiban', 'Kewajiban Lancar', 'Kredit'],
    ['2103', 'PPN Keluaran', 'Kewajiban', 'Kewajiban Lancar', 'Kredit'], ['2104', 'Hutang Pajak', 'Kewajiban', 'Kewajiban Lancar', 'Kredit'],
    ['2201', 'Hutang Bank', 'Kewajiban', 'Kewajiban Jangka Panjang', 'Kredit'],
    ['3101', 'Modal Disetor', 'Ekuitas', 'Modal', 'Kredit'], ['3102', 'Prive / Dividen', 'Ekuitas', 'Modal', 'Debit'],
    ['3103', 'Laba Ditahan', 'Ekuitas', 'Modal', 'Kredit'],
    ['4101', 'Pendapatan Penjualan', 'Pendapatan', 'Pendapatan Usaha', 'Kredit'], ['4102', 'Pendapatan Jasa', 'Pendapatan', 'Pendapatan Usaha', 'Kredit'],
    ['4103', 'Pendapatan Lain-lain', 'Pendapatan', 'Pendapatan Lain', 'Kredit'], ['4201', 'Retur & Potongan Penjualan', 'Pendapatan', 'Pendapatan Usaha', 'Debit'],
    ['5101', 'Harga Pokok Penjualan', 'Beban', 'Harga Pokok', 'Debit'], ['5102', 'Pembelian', 'Beban', 'Harga Pokok', 'Debit'],
    ['5103', 'Selisih Persediaan', 'Beban', 'Harga Pokok', 'Debit'],
    ['6101', 'Beban Gaji', 'Beban', 'Beban Operasional', 'Debit'], ['6102', 'Beban Sewa', 'Beban', 'Beban Operasional', 'Debit'],
    ['6103', 'Beban Listrik, Air & Internet', 'Beban', 'Beban Operasional', 'Debit'], ['6104', 'Beban Pemasaran', 'Beban', 'Beban Operasional', 'Debit'],
    ['6105', 'Beban Perlengkapan Kantor', 'Beban', 'Beban Operasional', 'Debit'], ['6106', 'Beban Transportasi', 'Beban', 'Beban Operasional', 'Debit'],
    ['6107', 'Beban Penyusutan', 'Beban', 'Beban Operasional', 'Debit'], ['6108', 'Beban Administrasi Bank', 'Beban', 'Beban Operasional', 'Debit'],
    ['6109', 'Beban Pajak', 'Beban', 'Beban Operasional', 'Debit'], ['6199', 'Beban Lain-lain', 'Beban', 'Beban Operasional', 'Debit'],
];

/** Isi data awal (akun admin, kategori, akun perkiraan, produk contoh). Aman dijalankan berulang. */
function seed_defaults(array $admin = []): void
{
    $now = now_s();
    if (!(int)val('SELECT COUNT(*) FROM users')) {
        q('INSERT INTO users (id, username, nama, email, role, pass_hash, status, dibuat) VALUES (?,?,?,?,?,?,?,?)', [
            uid('U'), strtolower($admin['username'] ?? 'admin'), $admin['nama'] ?? 'Administrator', '', 'admin', make_pw($admin['password'] ?? 'admin123'), 'aktif', $now]);
    }
    if (!(int)val('SELECT COUNT(*) FROM akun')) {
        insert_many('akun', ['kode', 'nama', 'kategori', 'subkategori', 'saldo_normal', 'saldo_awal', 'status', 'keterangan'],
            array_map(fn($a) => [$a[0], $a[1], $a[2], $a[3], $a[4], 0, 'Aktif', ''], COA_DEFAULT));
    } elseif (!val('SELECT kode FROM akun WHERE kode = ?', ['5103'])) {
        q("INSERT INTO akun (kode, nama, kategori, subkategori, saldo_normal, saldo_awal, status, keterangan) VALUES ('5103','Selisih Persediaan','Beban','Harga Pokok','Debit',0,'Aktif','')");
    }
    $have = [];
    foreach (rows('SELECT k FROM pengaturan') as $r) $have[$r['k']] = 1;
    $miss = [];
    foreach (SET_DEFAULT as $k => $v) if (!isset($have[$k])) $miss[$k] = $v;
    if (!isset($have['REV'])) $miss['REV'] = (string)round(microtime(true) * 1000);
    if ($miss) setting_put($miss);
    if (!(int)val('SELECT COUNT(*) FROM kategori')) {
        insert_many('kategori', ['id', 'nama'], array_map(fn($n) => [uid('C'), $n], ['Makanan', 'Minuman', 'Sembako', 'Snack', 'Lainnya']));
    }
}

/** Produk contoh (opsional saat instalasi). Stok awal dicatat ke jurnal agar neraca langsung seimbang. */
function seed_demo_products(): void
{
    if ((int)val('SELECT COUNT(*) FROM produk')) return;
    $now = now_s();
    $demo = [
        ['8991001101011', 'Beras Premium', 'Sembako', 'kg', 12000, 14500, 50, 10],
        ['8991001101028', 'Gula Pasir', 'Sembako', 'kg', 15000, 17500, 30, 8],
        ['8991001101035', 'Minyak Goreng 1L', 'Sembako', 'botol', 16000, 18500, 24, 6],
        ['8991001101042', 'Air Mineral 600ml', 'Minuman', 'botol', 2000, 3500, 96, 24],
        ['8991001101059', 'Teh Botol', 'Minuman', 'botol', 3500, 5500, 48, 12],
        ['8991001101066', 'Mie Instan Goreng', 'Makanan', 'pcs', 2700, 3500, 120, 30],
        ['8991001101073', 'Keripik Singkong', 'Snack', 'pcs', 6000, 9000, 20, 5],
    ];
    $rowsP = []; $logs = [];
    foreach ($demo as $x) {
        $id = uid('P');
        $rowsP[] = [$id, $x[0], $x[1], $x[2], $x[3], $x[4], $x[5], $x[6], $x[7], '', $now, $now, sku_gen($x[2])];
        $logs[] = ['id' => $id, 'nama' => $x[1], 'lama' => 0, 'baru' => $x[6], 'hb' => $x[4], 'ket' => 'Stok awal produk baru'];
    }
    insert_many('produk', ['id', 'barcode', 'nama', 'kategori', 'satuan', 'harga_beli', 'harga_jual', 'stok', 'stok_min', 'foto', 'dibuat', 'diubah', 'sku'], $rowsP);
    insert_many('stok_lokasi', ['pid', 'lokasi_id', 'qty', 'min', 'rak'], array_map(fn($r) => [$r[0], lok_jual(), $r[7], $r[8], ''], $rowsP));
    $sys = ['username' => 'system'];
    $op = [];
    foreach ($logs as $l) $op[] = [uid('O'), $now, 'system', $l['id'], $l['nama'], 0, $l['baru'], $l['baru'], $l['ket'], lok_jual()];
    insert_many('opname', ['id', 'tanggal', 'user', 'pid', 'nama', 'sistem', 'fisik', 'selisih', 'catatan', 'lokasi_id'], $op);
    require_once __DIR__ . '/posting.php';
    post_stock_adjust($logs, $sys, 'awal');
}

/* ============================================================
 *  HAK AKSES PER MODUL (jabatan)  — Dhyra v3
 *  Level: 0 tidak ada · 1 lihat · 2 petugas · 3 manajer
 * ============================================================ */
const MODUL = [
    'kasir' => 'Kasir (POS)',
    'inventaris' => 'Inventaris & Gudang',
    'pembelian' => 'Pembelian',
    'member' => 'Member & CRM',
    'promo' => 'Promo',
    'akuntansi' => 'Akuntansi',
    'laporan' => 'Dashboard & Laporan',
    'aset' => 'Inventaris Aset',
    'sdm' => 'SDM & Penggajian',
    'kpi' => 'KPI Karyawan',
    'online' => 'Toko Online',
];
/** Jabatan bawaan: [id, nama, is_admin, akses] */
const JABATAN_DEFAULT = [
    ['JADMIN', 'Administrator', 1, ['kasir' => 3, 'inventaris' => 3, 'pembelian' => 3, 'member' => 3, 'promo' => 3, 'akuntansi' => 3, 'laporan' => 3, 'aset' => 3, 'sdm' => 3, 'kpi' => 3, 'online' => 3]],
    ['JPEMILIK', 'Pemilik (pantau)', 0, ['kasir' => 1, 'inventaris' => 1, 'pembelian' => 1, 'member' => 1, 'promo' => 1, 'akuntansi' => 1, 'laporan' => 3, 'aset' => 1, 'sdm' => 1, 'kpi' => 1, 'online' => 1]],
    ['JKEPALA', 'Kepala Toko', 0, ['kasir' => 3, 'inventaris' => 3, 'pembelian' => 3, 'member' => 3, 'promo' => 3, 'akuntansi' => 0, 'laporan' => 3, 'aset' => 2, 'sdm' => 2, 'kpi' => 2, 'online' => 3]],
    ['JKASIR', 'Kasir', 0, ['kasir' => 2, 'inventaris' => 0, 'pembelian' => 0, 'member' => 0, 'promo' => 0, 'akuntansi' => 0, 'laporan' => 0, 'aset' => 0, 'sdm' => 0, 'kpi' => 0, 'online' => 0]],
    ['JGUDANG', 'Staf Gudang', 0, ['kasir' => 0, 'inventaris' => 2, 'pembelian' => 2, 'member' => 0, 'promo' => 0, 'akuntansi' => 0, 'laporan' => 0, 'aset' => 1, 'sdm' => 0, 'kpi' => 0, 'online' => 0]],
    ['JSTAF', 'Karyawan (absen saja)', 0, ['kasir' => 0, 'inventaris' => 0, 'pembelian' => 0, 'member' => 0, 'promo' => 0, 'akuntansi' => 0, 'laporan' => 0, 'aset' => 0, 'sdm' => 0, 'kpi' => 0, 'online' => 0]],
    ['JAKUNTAN', 'Akuntan', 0, ['kasir' => 1, 'inventaris' => 1, 'pembelian' => 1, 'member' => 0, 'promo' => 0, 'akuntansi' => 2, 'laporan' => 3, 'aset' => 3, 'sdm' => 3, 'kpi' => 1, 'online' => 1]],
];
const LEVEL_NAMA = [0 => 'Tidak ada', 1 => 'Lihat', 2 => 'Petugas', 3 => 'Manajer'];

/** Akses efektif pengguna: ['kasir'=>0..3, ..., 'admin'=>bool] */
function user_akses(array $u): array
{
    static $cache = [];
    $jid = (string)($u['jabatan_id'] ?? '');
    if (!isset($cache[$jid])) {
        $j = $jid !== '' ? row('SELECT * FROM jabatan WHERE id = ?', [$jid]) : null;
        $a = ['admin' => false];
        $raw = $j ? (json_decode((string)$j['akses'], true) ?: []) : [];
        foreach (MODUL as $m => $_) $a[$m] = $j && $j['is_admin'] ? 3 : max(0, min(3, (int)($raw[$m] ?? 0)));
        $a['admin'] = $j ? (bool)$j['is_admin'] : false;
        $a['jabatan'] = $j ? $j['nama'] : '-';
        $on = paket_modul();
        if ($on !== null) foreach (MODUL as $m => $_) if (!in_array($m, $on, true)) $a[$m] = 0;   // modul di luar paket langganan
        $cache[$jid] = $a;
    }
    return $cache[$jid];
}
/* ---------------------------- Paket langganan (mode SaaS) ---------------------------- */
/** Daftar modul yang termasuk paket toko ini; null = semua (mode satu toko). */
function paket_modul(): ?array { $c = $GLOBALS['__saas'] ?? null; return $c ? $c['modul'] : null; }
function paket_cek_modul(string $mod): void
{
    $on = paket_modul();
    if ($on === null || in_array($mod, $on, true)) return;
    $c = $GLOBALS['__saas'];
    throw new AppErr('Menu ' . (MODUL[$mod] ?? $mod) . ' tidak termasuk paket ' . $c['paketNama'] . '.' . (!empty($c['naik'][$mod]) ? ' Tersedia mulai paket ' . $c['naik'][$mod] . '.' : '') . ' Admin toko dapat mengganti paket di menu Langganan.', 'PAKET');
}
/** Mode baca-saja: masa langganan & masa tenggang sudah lewat. */
function paket_cek_tulis(): void
{
    $c = $GLOBALS['__saas'] ?? null;
    if ($c && !empty($c['ro'])) throw new AppErr('Masa langganan toko ini sudah berakhir, jadi toko dalam mode baca-saja. Data tetap aman dan laporan tetap bisa dilihat, tetapi transaksi dan perubahan data dikunci sampai langganan diperpanjang. Admin toko: buka menu Langganan.', 'LANGGANAN');
}
/** Jumlah outlet, pengguna aktif & produk (hanya bila paket punya batas). */
function paket_hitung(): ?array
{
    $c = $GLOBALS['__saas'] ?? null;
    if (!$c || !array_filter($c['batas'])) return null;
    $b = $c['batas'];
    return ['outlet' => $b['outlet'] ? (int)val("SELECT COUNT(*) FROM lokasi WHERE tipe = 'toko' AND aktif = 1") : 0,
        'user' => $b['user'] ? (int)val("SELECT COUNT(*) FROM users WHERE status = 'aktif'") : 0,
        'produk' => $b['produk'] ? (int)val('SELECT COUNT(*) FROM produk') : 0];
}
/** Tolak perubahan yang menambah jumlah melewati batas paket (data lama yang sudah melebihi tetap aman). */
function paket_cek_batas(array $awal): void
{
    $c = $GLOBALS['__saas']; $kini = paket_hitung();
    $nm = ['outlet' => 'outlet (lokasi toko aktif)', 'user' => 'pengguna aktif', 'produk' => 'produk'];
    foreach ($c['batas'] as $k => $max)
        if ($max > 0 && $kini[$k] > $max && $kini[$k] > $awal[$k])
            throw new AppErr('Batas paket ' . $c['paketNama'] . ': maksimal ' . number_format($max, 0, ',', '.') . ' ' . $nm[$k] . '. Untuk menambah, admin toko dapat mengganti paket di menu Langganan.', 'BATAS');
}
function need_level(array $u, string $mod, int $lvl): void
{
    if ((user_akses($u)[$mod] ?? 0) < $lvl) throw new AppErr('Akses ditolak: hak akses ' . (MODUL[$mod] ?? $mod) . ' Anda belum mencukupi.');
}

/* ---------------------------- Lokasi stok ---------------------------- */
function lokasi_all(): array
{
    static $m = null;
    if ($m === null) { $m = []; foreach (rows('SELECT * FROM lokasi ORDER BY urut, nama') as $r) $m[$r['id']] = $r; }
    return $m;
}
function lok_jual(): string { $s = settings_all()['LokasiJual'] ?? ''; return isset(lokasi_all()[$s]) ? $s : (string)array_key_first(lokasi_all()); }
function lok_masuk(): string { $s = settings_all()['LokasiMasuk'] ?? ''; return isset(lokasi_all()[$s]) ? $s : lok_jual(); }
function lok_valid($id): string
{
    $id = (string)$id;
    if (!isset(lokasi_all()[$id])) throw new AppErr('Lokasi stok tidak valid.');
    return $id;
}
/** Stok satu produk di satu lokasi (dikunci untuk transaksi). */
function lok_qty(string $pid, string $lok): float
{
    return (float)(val('SELECT qty FROM stok_lokasi WHERE pid = ? AND lokasi_id = ? FOR UPDATE', [$pid, $lok]) ?? 0);
}
/** Ubah stok di lokasi; $total=true juga mengubah stok total produk. */
function lok_move(string $pid, string $lok, float $delta, bool $total = true): float
{
    q('INSERT INTO stok_lokasi (pid, lokasi_id, qty) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE qty = ROUND(qty + VALUES(qty), 3)', [$pid, $lok, r3($delta)]);
    if ($total) q('UPDATE produk SET stok = ROUND(stok + ?, 3) WHERE id = ?', [r3($delta), $pid]);
    return (float)val('SELECT qty FROM stok_lokasi WHERE pid = ? AND lokasi_id = ?', [$pid, $lok]);
}
/** Ringkasan stok untuk dikirim ke tampilan: {pid: {t: total, l: {lokasi: qty}}} */
function stok_snapshot(array $pids): array
{
    $pids = array_values(array_unique(array_map('strval', $pids)));
    if (!$pids) return [];
    $ph = implode(',', array_fill(0, count($pids), '?'));
    $out = [];
    foreach (rows("SELECT id, stok FROM produk WHERE id IN ($ph)", $pids) as $r) $out[$r['id']] = ['t' => jn($r['stok']), 'l' => new stdClass()];
    foreach (rows("SELECT pid, lokasi_id, qty FROM stok_lokasi WHERE pid IN ($ph)", $pids) as $r) if (isset($out[$r['pid']])) $out[$r['pid']]['l']->{$r['lokasi_id']} = jn($r['qty']);
    return $out;
}

/* ---------------------------- Cadangan database ---------------------------- */
/** Seluruh tabel aplikasi (kecuali sesi login) dalam satu file .sql */
function db_backup(): array
{
    $tables = array_values(array_filter(array_map(fn($r) => array_values($r)[0], rows('SHOW TABLES')), fn($t) => !in_array($t, ['sesi', 'login_gagal'], true)));
    $out = "-- Cadangan " . APP_NAME . " " . APP_VERSION . " " . now_s() . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n";
    foreach ($tables as $t) {
        $cr = row('SHOW CREATE TABLE `' . $t . '`');
        $out .= "\nDROP TABLE IF EXISTS `$t`;\n" . array_values($cr)[1] . ";\n";
        $st = db()->query('SELECT * FROM `' . $t . '`');
        $buf = [];
        while ($r = $st->fetch(PDO::FETCH_NUM)) {
            $buf[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : db()->quote((string)$v), $r)) . ')';
            if (count($buf) >= 200) { $out .= "INSERT INTO `$t` VALUES\n" . implode(",\n", $buf) . ";\n"; $buf = []; }
        }
        if ($buf) $out .= "INSERT INTO `$t` VALUES\n" . implode(",\n", $buf) . ";\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return ['name' => 'Backup ' . APP_NAME . ' ' . date('Y-m-d Hi') . '.sql', 'mime' => 'application/sql', 'b64' => base64_encode($out)];
}

/* ---------------------------- SKU produk ---------------------------- */
function sku_prefix(string $kategori): string
{
    $s = settings_all();
    if (($s['SkuMode'] ?? 'kategori') === 'prefix') { $p = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$s['SkuPrefix'])); return substr($p ?: 'DHY', 0, 8); }
    $t = strtoupper(preg_replace('/[^A-Za-z]/', '', (function_exists('iconv') ? (@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $kategori) ?: $kategori) : $kategori)));
    if ($t === '') return 'UMM';
    $c = $t[0] . preg_replace('/[AIUEO]/', '', substr($t, 1));          // huruf depan + konsonan: MINUMAN -> MNM
    return substr(strlen($c) >= 3 ? $c : $t . 'XX', 0, 3);
}
/** SKU baru yang belum dipakai, mis. MNM-0007 */
function sku_gen(string $kategori): string
{
    $p = sku_prefix($kategori);
    for ($i = 0; $i < 50; $i++) {
        $sku = $p . '-' . str_pad((string)counter_next('SKU_' . $p), 4, '0', STR_PAD_LEFT);
        if (!val('SELECT id FROM produk WHERE sku = ?', [$sku])) return $sku;
    }
    return $p . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/* ---------------------------- Migrasi database ---------------------------- */
const DB_VERSION = 10;
function col_exists(string $t, string $c): bool
{
    return (bool)val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c]);
}
function migrate(): void
{
    if ((int)(settings_all()['DB_V'] ?? 0) >= DB_VERSION) return;
    $got = (int)val('SELECT GET_LOCK(?, 30)', [lock_name() . '_mig']);
    try {
        if ((int)(settings_all(true)['DB_V'] ?? 0) >= DB_VERSION) return;
        $sql = (string)file_get_contents(__DIR__ . '/schema.sql');
        $sql = preg_replace('#/\*.*?\*/#s', '', preg_replace('/^\s*--.*$/m', '', $sql));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $st) db()->exec($st);
        foreach ([['users', 'jabatan_id', "VARCHAR(20) NOT NULL DEFAULT ''"], ['opname', 'lokasi_id', "VARCHAR(20) NOT NULL DEFAULT ''"],
                  ['stok_masuk', 'lokasi_id', "VARCHAR(20) NOT NULL DEFAULT ''"], ['stok_masuk_doc', 'lokasi_id', "VARCHAR(20) NOT NULL DEFAULT ''"],
                  ['transaksi', 'lokasi_id', "VARCHAR(20) NOT NULL DEFAULT ''"],
                  // v4
                  ['produk', 'sku', 'VARCHAR(40) NULL'], ['transaksi', 'member_id', "VARCHAR(20) NOT NULL DEFAULT ''"], ['transaksi', 'poin_dapat', 'INT NOT NULL DEFAULT 0'],
                  ['transaksi', 'poin_tukar', 'INT NOT NULL DEFAULT 0'], ['transaksi', 'diskon_promo', 'DECIMAL(18,2) NOT NULL DEFAULT 0'], ['transaksi', 'diskon_poin', 'DECIMAL(18,2) NOT NULL DEFAULT 0'],
                  ['stok_masuk_doc', 'po_no', "VARCHAR(30) NOT NULL DEFAULT ''"],
                  // v5
                  ['transaksi', 'batal_pada', 'DATETIME NULL'], ['mutasi', 'batal_pada', 'DATETIME NULL'], ['produk', 'online', 'TINYINT NOT NULL DEFAULT 1'],
                  ['produk', 'deskripsi', 'TEXT NULL'], ['lokasi', 'lat', 'DECIMAL(10,7) NULL'], ['lokasi', 'lng', 'DECIMAL(10,7) NULL'], ['lokasi', 'radius', 'INT NOT NULL DEFAULT 100'],
                  ['transaksi', 'sumber', "VARCHAR(10) NOT NULL DEFAULT ''"],
                  // v8
                  ['produk', 'galeri', 'TEXT NULL'], ['kategori', 'urut', 'INT NOT NULL DEFAULT 0'],
                  // v9: atasan & persetujuan, lembur per jam, karyawan pengganti, password sementara
                  ['users', 'ganti_pw', 'TINYINT NOT NULL DEFAULT 0'], ['karyawan', 'atasan_id', "VARCHAR(20) NOT NULL DEFAULT ''"],
                  ['pengajuan', 'jam_mulai', "VARCHAR(5) NOT NULL DEFAULT ''"], ['pengajuan', 'jam_selesai', "VARCHAR(5) NOT NULL DEFAULT ''"],
                  ['pengajuan', 'pengganti_id', "VARCHAR(20) NOT NULL DEFAULT ''"], ['pengajuan', 'diajukan_oleh', "VARCHAR(30) NOT NULL DEFAULT ''"],
                  ['jadwal', 'ganti_id', "VARCHAR(20) NOT NULL DEFAULT ''"], ['jadwal', 'pj_id', "VARCHAR(20) NOT NULL DEFAULT ''"],
                  // v10: pembayaran pesanan online dicatat ke akuntansi saat diterima
                  ['pesanan_online', 'dibayar', 'DATETIME NULL']] as [$t, $c, $def])
            if (!col_exists($t, $c)) db()->exec("ALTER TABLE `$t` ADD COLUMN `$c` $def");
        if (!(int)val("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'produk' AND COLUMN_NAME = 'sku'"))
            db()->exec('ALTER TABLE produk ADD UNIQUE KEY ux_sku (sku)');
        // jabatan bawaan
        if (!(int)val('SELECT COUNT(*) FROM jabatan')) {
            foreach (JABATAN_DEFAULT as $j) q('INSERT INTO jabatan (id, nama, is_admin, akses, sistem, dibuat) VALUES (?,?,?,?,?,?)', [$j[0], $j[1], $j[2], json_encode($j[3]), $j[0] === 'JADMIN' ? 1 : 0, now_s()]);
        }
        $map = ['admin' => 'JADMIN', 'akuntan' => 'JAKUNTAN', 'kasir' => 'JKASIR', 'viewer' => 'JPEMILIK'];
        foreach ($map as $r => $jid) q("UPDATE users SET jabatan_id = ? WHERE jabatan_id = '' AND role = ?", [$jid, $r]);
        q("UPDATE users SET jabatan_id = 'JKASIR' WHERE jabatan_id = ''");
        // lokasi bawaan: seluruh stok lama dianggap stok toko
        if (!(int)val('SELECT COUNT(*) FROM lokasi')) {
            q("INSERT INTO lokasi (id, kode, nama, tipe, aktif, urut) VALUES ('LTOKO','TOKO','Toko Utama','toko',1,1),('LGUDANG','GDG','Gudang Utama','gudang',1,2)");
            q("INSERT IGNORE INTO stok_lokasi (pid, lokasi_id, qty, min, rak) SELECT id, 'LTOKO', stok, stok_min, '' FROM produk");
            q("UPDATE opname SET lokasi_id = 'LTOKO' WHERE lokasi_id = ''");
            q("UPDATE stok_masuk SET lokasi_id = 'LTOKO' WHERE lokasi_id = ''");
            q("UPDATE stok_masuk_doc SET lokasi_id = 'LTOKO' WHERE lokasi_id = ''");
            q("UPDATE transaksi SET lokasi_id = 'LTOKO' WHERE lokasi_id = ''");
            setting_put(['LokasiJual' => 'LTOKO', 'LokasiMasuk' => 'LGUDANG']);
        }
        // v4: hak akses modul baru untuk jabatan lama (jabatan bawaan memakai nilai standar, jabatan buatan sendiri = tidak ada)
        $JD = []; foreach (JABATAN_DEFAULT as $j) $JD[$j[0]] = $j[3];
        foreach (rows('SELECT id, akses FROM jabatan') as $j) {
            $a = json_decode((string)$j['akses'], true) ?: []; $ch = false;
            foreach (MODUL as $m => $_) if (!array_key_exists($m, $a)) { $a[$m] = $JD[$j['id']][$m] ?? 0; $ch = true; }
            if ($ch) q('UPDATE jabatan SET akses = ? WHERE id = ?', [json_encode($a), $j['id']]);
        }
        // v4: SKU otomatis untuk produk yang belum punya
        foreach (rows("SELECT id, kategori FROM produk WHERE sku IS NULL OR sku = '' ORDER BY dibuat, nama") as $p) q('UPDATE produk SET sku = ? WHERE id = ?', [sku_gen((string)$p['kategori']), $p['id']]);
        // v5: akun tambahan untuk aset, penggajian & toko online
        foreach ([['1504', 'Peralatan & Perlengkapan Toko', 'Aset', 'Aset Tetap', 'Debit'], ['4104', 'Laba Pelepasan Aset', 'Pendapatan', 'Pendapatan Lain', 'Kredit'],
                  ['4105', 'Pendapatan Ongkos Kirim', 'Pendapatan', 'Pendapatan Lain', 'Kredit'], ['6110', 'Beban Perbaikan & Pemeliharaan', 'Beban', 'Beban Operasional', 'Debit'],
                  ['6111', 'Rugi Pelepasan Aset', 'Beban', 'Beban Operasional', 'Debit'], ['2105', 'Uang Muka Pelanggan', 'Kewajiban', 'Kewajiban Lancar', 'Kredit']] as $ak)
            if (!val('SELECT kode FROM akun WHERE kode = ?', [$ak[0]]))
                q("INSERT INTO akun (kode, nama, kategori, subkategori, saldo_normal, saldo_awal, status, keterangan) VALUES (?,?,?,?,?,0,'Aktif','')", $ak);
        // v5: waktu pembatalan lama diambil dari jurnal pembalik (untuk kartu stok)
        q("UPDATE transaksi t SET batal_pada = (SELECT MIN(j.dibuat_pada) FROM jurnal j WHERE j.ref = CONCAT('POSV:', t.no)) WHERE t.status = 'Batal' AND t.batal_pada IS NULL");
        // v7: jabatan bawaan yang belum ada (mis. "Karyawan (absen saja)")
        foreach (JABATAN_DEFAULT as $j) if (!val('SELECT id FROM jabatan WHERE id = ?', [$j[0]]))
            q('INSERT INTO jabatan (id, nama, is_admin, akses, sistem, dibuat) VALUES (?,?,?,?,?,?)', [$j[0], $j[1], $j[2], json_encode($j[3]), 0, now_s()]);
        if (!isset(settings_all(true)['SdmMulai'])) setting_put(['SdmMulai' => today_s()]);   // hari sebelum Dhyra dipakai tidak dihitung alpa
        if (!(int)val('SELECT COUNT(*) FROM shift'))
            q("INSERT INTO shift (id, nama, masuk, pulang, toleransi, warna, aktif) VALUES ('SPAGI','Pagi','08:00','16:00',10,'#10A391',1),('SSIANG','Siang','13:00','21:00',10,'#3B5BDB',1)");
        if (!(int)val('SELECT COUNT(*) FROM kpi_tpl'))
            q('INSERT INTO kpi_tpl (id, nama, indikator, aktif, dibuat) VALUES (?,?,?,1,?),(?,?,?,1,?)', [
                'KPIKASIR', 'KPI Kasir', json_encode([['id' => 'i1', 'nama' => 'Kehadiran', 'sumber' => 'hadir', 'target' => 100, 'bobot' => 25], ['id' => 'i2', 'nama' => 'Tepat waktu', 'sumber' => 'tepat', 'target' => 100, 'bobot' => 15],
                    ['id' => 'i3', 'nama' => 'Omzet penjualan', 'sumber' => 'omzet', 'target' => 30000000, 'bobot' => 35], ['id' => 'i4', 'nama' => 'Member baru', 'sumber' => 'member', 'target' => 20, 'bobot' => 15],
                    ['id' => 'i5', 'nama' => 'Kerapian & pelayanan (penilaian atasan)', 'sumber' => 'manual', 'target' => 100, 'bobot' => 10]]), now_s(),
                'KPIGUDANG', 'KPI Staf Gudang', json_encode([['id' => 'i1', 'nama' => 'Kehadiran', 'sumber' => 'hadir', 'target' => 100, 'bobot' => 30], ['id' => 'i2', 'nama' => 'Tepat waktu', 'sumber' => 'tepat', 'target' => 100, 'bobot' => 20],
                    ['id' => 'i3', 'nama' => 'Akurasi stok opname (%)', 'sumber' => 'manual', 'target' => 100, 'bobot' => 30], ['id' => 'i4', 'nama' => 'Kerapian gudang (penilaian atasan)', 'sumber' => 'manual', 'target' => 100, 'bobot' => 20]]), now_s()]);
        setting_put(['DB_V' => (string)DB_VERSION]);
    } finally {
        if ($got) q('SELECT RELEASE_LOCK(?)', [lock_name() . '_mig']);
    }
}
