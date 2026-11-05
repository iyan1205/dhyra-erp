<?php
/**
 * ============================================================
 *  MODUL AKUNTANSI — port dari Akuntansi Pro Code.gs ke PHP + MySQL
 *  Aksi dipanggil dari akuntansi.html lewat api.php  { m:'akun', a, t, d }
 * ============================================================
 */
declare(strict_types=1);
require_once __DIR__ . '/posting.php';

const KATEGORI = ['Aset', 'Kewajiban', 'Ekuitas', 'Pendapatan', 'Beban'];
const JENIS_FORM = ['Kas Masuk', 'Kas Keluar', 'Jurnal Umum'];
const A_SET_KEYS = ['NamaPerusahaan', 'Alamat', 'Telepon', 'Email', 'NPWP', 'MataUang', 'TarifPPN', 'KunciPeriode', 'AkunKas', 'AkunPiutang', 'AkunHutang', 'AkunPPNKeluaran', 'AkunPPNMasukan',
    'PosAkunTunai', 'PosAkunQRIS', 'PosAkunTransfer', 'PosAkunDebit', 'PosAkunBank', 'PosAkunPenjualan', 'PosAkunPotongan', 'PosAkunHPP', 'PosAkunPersediaan', 'PosAkunSelisih', 'PosAkunStokAwal',
    'FakturCatatan', 'FakturPenutup', 'LogoUrl'];
const A_ACC_KEYS = ['AkunKas', 'AkunPiutang', 'AkunHutang', 'AkunPPNKeluaran', 'AkunPPNMasukan', 'PosAkunTunai', 'PosAkunQRIS', 'PosAkunTransfer', 'PosAkunDebit', 'PosAkunBank',
    'PosAkunPenjualan', 'PosAkunPotongan', 'PosAkunHPP', 'PosAkunPersediaan', 'PosAkunSelisih', 'PosAkunStokAwal'];

const AKUN_ACTIONS = [
    'logout' => ['f' => 'a_logout'], 'bootstrap' => ['f' => 'a_bootstrap'], 'master' => ['f' => 'a_bootstrap'], 'rev' => ['f' => 'a_rev'],
    'dashboard' => ['f' => 'a_dashboard'], 'list' => ['f' => 'a_list'], 'get' => ['f' => 'a_get'],
    'save' => ['f' => 'a_save', 'w' => 1], 'del' => ['f' => 'a_delete', 'w' => 1],
    'savePayment' => ['f' => 'a_savePayment', 'w' => 1], 'deletePayment' => ['f' => 'a_deletePayment', 'w' => 1],
    'report' => ['f' => 'a_report'], 'exportFile' => ['f' => 'a_export'], 'pdfFaktur' => ['f' => 'a_pdfFaktur'],
    'previewTemplate' => ['f' => 'a_previewTemplate', 'roles' => ['admin']],
    'saveSettings' => ['f' => 'a_saveSettings', 'w' => 1, 'roles' => ['admin']],
    'changePassword' => ['f' => 'a_changePassword', 'w' => 1],
    'backup' => ['f' => 'a_backup', 'roles' => ['admin']],
    'seedDemo' => ['f' => 'a_seedDemo', 'w' => 1, 'roles' => ['admin']],
];

function akun_dispatch(string $a, array $p, ?string $token): array
{
    if ($a === 'init') return ['nama' => settings_all()['NamaPerusahaan'] ?: APP_NAME, 'versi' => APP_VERSION];
    if ($a === 'login') {
        $r = do_login($p);
        log_act($r['user'], 'Masuk', 'Sistem', 'Login berhasil');
        $lvl = user_akses($r['user'])['akuntansi'] ?? 0;
        $r['user']['role'] = [0 => 'viewer', 1 => 'viewer', 2 => 'akuntan', 3 => 'admin'][$lvl];
        return ['token' => $r['token'], 'user' => a_pubUser($r['user']), 'defaultPassword' => $r['defaultPassword'], 'redirect' => $lvl < 1 ? 'beranda.html' : ''];
    }
    $cfg = AKUN_ACTIONS[$a] ?? null;
    if (!$cfg) throw new AppErr('Aksi tidak dikenal: ' . $a);
    $user = auth_token($token);
    paket_cek_modul('akuntansi');
    $lvl = user_akses($user)['akuntansi'] ?? 0;
    if ($lvl < 1) throw new AppErr('Akun Anda tidak memiliki akses ke modul Akuntansi.', 'MODUL');
    if (hp_terbatas($user)) throw new AppErr(HP_PESAN, 'HP');
    if ((settings_all()['SdmAksesJam'] ?? '0') === '1') { require_once __DIR__ . '/kasir.php'; akses_cek($user, 'Akuntansi'); }
    $user['role'] = [1 => 'viewer', 2 => 'akuntan', 3 => 'admin'][$lvl];
    $user['_token'] = $token;
    if (!empty($cfg['roles']) && !in_array($user['role'], $cfg['roles'], true)) throw new AppErr('Anda tidak memiliki akses untuk aksi ini.');
    $res = !empty($cfg['w']) ? with_write(function () use ($cfg, $p, $user) { $r = $cfg['f']($p, $user); bump_rev(); return $r; }) : $cfg['f']($p, $user);
    if (is_array($res)) $res['rev'] = rev();
    return $res;
}

/* ========================================================================
 *  UTILITAS DOMAIN
 * ====================================================================== */
function a_need(array $u, array $roles): void { if (!in_array($u['role'], $roles, true)) throw new AppErr('Anda tidak memiliki akses untuk aksi ini.'); }
function a_assertDate($s): void { if (!is_date($s)) throw new AppErr('Format tanggal tidak valid.'); }
function a_assertOpen($tgl): void
{
    $k = settings_all()['KunciPeriode'] ?? '';
    if ($k && $tgl && $tgl <= $k) throw new AppErr('Periode sampai ' . dmy($k) . ' sudah dikunci. Transaksi tidak dapat diubah.');
}
function a_pubUser(array $u): array { return ['username' => $u['username'], 'nama' => $u['nama'] ?: $u['username'], 'email' => $u['email'], 'role' => role_label($u['role'])]; }
function a_cmp($a, $b): int
{
    if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) return $a <=> $b;
    return strcmp((string)($a ?? ''), (string)($b ?? ''));
}
function a_nextNo(string $prefix, ?string $tgl): string
{
    $yr = substr($tgl ?: today_s(), 0, 4);
    return $prefix . '-' . $yr . '-' . str_pad((string)counter_next('CNT_' . $prefix . '_' . $yr), 4, '0', STR_PAD_LEFT);
}

function a_akunMap(): array
{
    static $m = null;
    if ($m === null) {
        $m = [];
        foreach (rows('SELECT * FROM akun ORDER BY kode') as $a) $m[$a['kode']] = ['Kode' => $a['kode'], 'Nama' => $a['nama'], 'Kategori' => $a['kategori'], 'Subkategori' => $a['subkategori'], 'SaldoNormal' => $a['saldo_normal'], 'SaldoAwal' => (float)$a['saldo_awal'], 'Status' => $a['status'], 'Keterangan' => $a['keterangan']];
    }
    return $m;
}
function a_kontakMap(): array
{
    static $m = null;
    if ($m === null) { $m = []; foreach (rows('SELECT * FROM kontak') as $k) $m[$k['id']] = $k; }
    return $m;
}
/** Saldo bersih (debit - kredit) per akun, termasuk saldo awal, sampai tanggal $to. */
function a_balNet(?string $to = null): array
{
    $net = [];
    foreach (a_akunMap() as $k => $a) $net[$k] = $a['SaldoNormal'] === 'Debit' ? $a['SaldoAwal'] : -$a['SaldoAwal'];
    $sql = 'SELECT kode_akun k, SUM(debit) - SUM(kredit) n FROM jurnal' . ($to ? ' WHERE tanggal <= ?' : '') . ' GROUP BY kode_akun';
    foreach (rows($sql, $to ? [$to] : []) as $r) $net[$r['k']] = ($net[$r['k']] ?? 0) + (float)$r['n'];
    return $net;
}

function a_akunList(): array
{
    $net = a_balNet();
    return array_values(array_map(fn($a) => ['kode' => $a['Kode'], 'nama' => $a['Nama'], 'kategori' => $a['Kategori'], 'sub' => $a['Subkategori'], 'normal' => $a['SaldoNormal'], 'saldoAwal' => jn($a['SaldoAwal']), 'status' => $a['Status'], 'ket' => $a['Keterangan'],
        'saldo' => jn(r2($a['SaldoNormal'] === 'Debit' ? ($net[$a['Kode']] ?? 0) : -($net[$a['Kode']] ?? 0)))], a_akunMap()));
}
function a_kontakList(): array
{
    return array_map(fn($k) => ['id' => $k['id'], 'nama' => $k['nama'], 'tipe' => $k['tipe'], 'telepon' => $k['telepon'], 'email' => $k['email'], 'alamat' => $k['alamat'], 'npwp' => $k['npwp'], 'status' => $k['status']], rows('SELECT * FROM kontak ORDER BY nama'));
}
function a_penggunaList(): array
{
    return array_map(fn($u) => ['username' => $u['username'], 'nama' => $u['nama'], 'email' => $u['email'], 'role' => role_label($u['role']), 'status' => $u['status'] === 'aktif' ? 'Aktif' : 'Nonaktif', 'login' => $u['login_terakhir'] ? substr($u['login_terakhir'], 0, 16) : ''], rows('SELECT * FROM users ORDER BY username'));
}
function a_logList(): array
{
    return array_map(fn($r) => ['waktu' => substr($r['waktu'], 0, 16), 'tgl' => substr($r['waktu'], 0, 10), 'user' => $r['user'], 'aksi' => $r['aksi'], 'modul' => $r['modul'], 'detail' => $r['detail']],
        array_reverse(rows('SELECT * FROM log_aktivitas ORDER BY id DESC LIMIT 1500')));
}

function a_fakturList(): array
{
    static $memo = null;
    if ($memo !== null) return $memo;
    $td = today_s();
    $rs = rows('SELECT f.*, COALESCE(p.paid, 0) paid, COALESCE(k.nama, "") kontak FROM faktur f LEFT JOIN (SELECT faktur_id, SUM(jumlah) paid FROM pembayaran GROUP BY faktur_id) p ON p.faktur_id = f.id LEFT JOIN kontak k ON k.id = f.kontak_id ORDER BY f.tanggal, f.nomor');
    return $memo = array_map(function ($f) use ($td) {
        $total = (float)$f['total']; $terbayar = r2((float)$f['paid']); $sisa = r2($total - $terbayar);
        $status = $sisa <= 0.005 ? 'Lunas' : ($f['jatuh_tempo'] && $f['jatuh_tempo'] < $td ? 'Jatuh Tempo' : ($terbayar > 0 ? 'Sebagian' : 'Belum Bayar'));
        return ['id' => $f['id'], 'nomor' => $f['nomor'], 'tipe' => $f['tipe'], 'tanggal' => $f['tanggal'], 'jatuhTempo' => $f['jatuh_tempo'], 'kontakId' => $f['kontak_id'], 'kontak' => $f['kontak'], 'ket' => $f['keterangan'],
            'subtotal' => jn($f['subtotal']), 'persenPPN' => jn($f['persen_ppn']), 'ppn' => jn($f['ppn']), 'total' => jn($total), 'akunLawan' => $f['akun_lawan'], 'terbayar' => jn($terbayar), 'sisa' => jn(max($sisa, 0)),
            'status' => $status, 'user' => $f['dibuat_oleh'], 'sumber' => $f['sumber']];
    }, $rs);
}
function a_fakturSummary(array $rows): array
{
    $s = ['count' => count($rows), 'total' => 0, 'terbayar' => 0, 'sisa' => 0, 'jatuhTempo' => 0];
    foreach ($rows as $r) { $s['total'] += $r['total']; $s['terbayar'] += $r['terbayar']; $s['sisa'] += $r['sisa']; if ($r['status'] === 'Jatuh Tempo') $s['jatuhTempo'] += $r['sisa']; }
    foreach (['total', 'terbayar', 'sisa', 'jatuhTempo'] as $k) $s[$k] = jn(r2($s[$k]));
    return $s;
}

/* ---------- Entri jurnal: dikelompokkan per EntriID langsung di MySQL ---------- */
const ENTRI_SORT = ['tanggal' => 'e.tanggal', 'no' => 'e.no', 'jenis' => 'e.jenis', 'ket' => 'e.ket', 'kontak' => 'kontak', 'total' => 'e.total', 'user' => 'e.user', 'ref' => 'e.ref'];
function a_entriBase(): string
{
    return "SELECT j.entri_id id, MIN(j.tanggal) tanggal, MIN(j.no_bukti) no, MIN(j.jenis) jenis,
        SUBSTRING_INDEX(GROUP_CONCAT(j.keterangan ORDER BY j.id SEPARATOR '\x1f'), '\x1f', 1) ket,
        MAX(j.kontak_id) kontakId, ROUND(SUM(j.debit), 2) total, MAX(j.ref) ref, MIN(j.dibuat_oleh) user, MIN(j.id) ord
        FROM jurnal j GROUP BY j.entri_id";
}
function a_entriQuery(array $p, bool $all = false, ?array $forceOrder = null): array
{
    $f = $p['f'] ?? []; $w = []; $a = [];
    if (!empty($f['from'])) { $w[] = 'e.tanggal >= ?'; $a[] = $f['from']; }
    if (!empty($f['to'])) { $w[] = 'e.tanggal <= ?'; $a[] = $f['to']; }
    if (isset($f['jenis']) && $f['jenis'] !== '') { $w[] = 'e.jenis = ?'; $a[] = (string)$f['jenis']; }
    if (isset($f['kontak']) && $f['kontak'] !== '') { $w[] = 'e.kontakId = ?'; $a[] = (string)$f['kontak']; }
    $qs = trim(mb_strtolower((string)($p['q'] ?? '')));
    if ($qs !== '') foreach (preg_split('/\s+/', $qs) as $t) {
        $w[] = "LOWER(CONCAT_WS(' ', e.no, e.ket, COALESCE(k.nama,''), e.jenis)) LIKE ?";
        $a[] = '%' . addcslashes($t, '%_\\') . '%';
    }
    $from = '(' . a_entriBase() . ') e LEFT JOIN kontak k ON k.id = e.kontakId' . ($w ? ' WHERE ' . implode(' AND ', $w) : '');
    $total = (int)val('SELECT COUNT(*) FROM ' . $from, $a);
    $key = $p['sort'] ?? 'tanggal'; $col = ENTRI_SORT[$key] ?? 'e.tanggal';
    $dir = ($p['dir'] ?? 'asc') === 'asc' ? 'ASC' : 'DESC';
    $order = $forceOrder ? implode(', ', $forceOrder) : "$col $dir, e.ord $dir";
    $sql = 'SELECT e.id, e.tanggal, e.no, e.jenis, e.ket, e.kontakId, COALESCE(k.nama, "") kontak, e.total, e.ref, e.user FROM ' . $from . ' ORDER BY ' . $order;
    if ($all) return ['rows' => a_entriFix(rows($sql, $a)), 'total' => $total];
    $size = min(200, max(5, (int)($p['size'] ?? 25))); $pages = max(1, (int)ceil($total / $size));
    $page = min(max(1, (int)($p['page'] ?? 1)), $pages);
    $rs = rows($sql . ' LIMIT ' . $size . ' OFFSET ' . (($page - 1) * $size), $a);
    return ['rows' => a_entriFix($rs), 'total' => $total, 'page' => $page, 'pages' => $pages, 'size' => $size, 'summary' => null];
}
function a_entriFix(array $rs): array { foreach ($rs as &$r) $r['total'] = jn($r['total']); return $rs; }

/* ---------- Engine list untuk data kecil (di memori) ---------- */
function a_listCfg(string $e): ?array
{
    $c = [
        'faktur' => ['load' => 'a_fakturList', 'search' => ['nomor', 'kontak', 'ket', 'status'], 'dateKey' => 'tanggal', 'eq' => ['tipe' => 'tipe', 'kontak' => 'kontakId'], 'custom' => 1, 'sort' => 'tanggal', 'dir' => 'asc', 'summary' => 'a_fakturSummary'],
        'akun' => ['load' => 'a_akunList', 'search' => ['kode', 'nama', 'kategori', 'sub'], 'eq' => ['kategori' => 'kategori', 'status' => 'status'], 'sort' => 'kode', 'dir' => 'asc'],
        'kontak' => ['load' => 'a_kontakList', 'search' => ['nama', 'telepon', 'email', 'alamat', 'npwp'], 'eq' => ['tipe' => 'tipe', 'status' => 'status'], 'sort' => 'nama', 'dir' => 'asc'],
        'pengguna' => ['load' => 'a_penggunaList', 'search' => ['username', 'nama', 'email', 'role'], 'eq' => ['role' => 'role', 'status' => 'status'], 'sort' => 'username', 'dir' => 'asc', 'roles' => ['admin']],
        'log' => ['load' => 'a_logList', 'search' => ['user', 'aksi', 'modul', 'detail'], 'dateKey' => 'tgl', 'eq' => ['modul' => 'modul'], 'sort' => 'waktu', 'dir' => 'desc', 'roles' => ['admin']],
    ];
    return $c[$e] ?? null;
}
function a_listMem(array $cfg, array $p, bool $all = false): array
{
    $rows = $cfg['load'](); $f = $p['f'] ?? []; $qs = trim(mb_strtolower((string)($p['q'] ?? '')));
    $toks = $qs !== '' ? preg_split('/\s+/', $qs) : [];
    $rows = array_values(array_filter($rows, function ($r) use ($cfg, $f, $toks) {
        if (!empty($cfg['dateKey'])) { $dv = $r[$cfg['dateKey']]; if (!empty($f['from']) && $dv < $f['from']) return false; if (!empty($f['to']) && $dv > $f['to']) return false; }
        foreach ($cfg['eq'] as $k => $field) if (isset($f[$k]) && $f[$k] !== '' && (string)$r[$field] !== (string)$f[$k]) return false;
        if (!empty($cfg['custom']) && !empty($f['status'])) { if ($f['status'] === 'Belum Lunas' ? !($r['sisa'] > 0.005) : $r['status'] !== $f['status']) return false; }
        if ($toks) {
            $hay = mb_strtolower(implode(' ', array_map(fn($s) => (string)($r[$s] ?? ''), $cfg['search'])));
            foreach ($toks as $t) if (mb_strpos($hay, $t) === false) return false;
        }
        return true;
    }));
    $key = $p['sort'] ?? $cfg['sort']; $dir = (($p['dir'] ?? $cfg['dir']) === 'asc') ? 1 : -1;
    $wr = []; foreach ($rows as $i => $r) $wr[] = [$r, $i];
    usort($wr, function ($x, $y) use ($key, $dir) { $c = a_cmp($x[0][$key] ?? null, $y[0][$key] ?? null); if (!$c) $c = $x[1] - $y[1]; return $c * $dir; });
    $rows = array_map(fn($x) => $x[0], $wr);
    $total = count($rows); $summary = !empty($cfg['summary']) ? $cfg['summary']($rows) : null;
    if ($all) return ['rows' => $rows, 'total' => $total];
    $size = min(200, max(5, (int)($p['size'] ?? 25))); $pages = max(1, (int)ceil($total / $size));
    $page = min(max(1, (int)($p['page'] ?? 1)), $pages);
    return ['rows' => array_slice($rows, ($page - 1) * $size, $size), 'total' => $total, 'page' => $page, 'pages' => $pages, 'size' => $size, 'summary' => $summary];
}

/* ========================================================================
 *  SESI, BOOTSTRAP
 * ====================================================================== */
function a_logout(array $p, array $user): array { q('DELETE FROM sesi WHERE token = ?', [$user['_token']]); log_act($user, 'Keluar', 'Sistem', ''); return ['ok' => true]; }
function a_rev(): array { return []; }
function a_bootstrap(array $p, array $user): array
{
    $st = settings_all(); $pub = [];
    foreach (A_SET_KEYS as $k) $pub[$k] = $st[$k] ?? '';
    return [
        'user' => a_pubUser($user), 'settings' => $pub, 'versi' => APP_VERSION, 'akses' => array_intersect_key(user_akses($user), MODUL), 'isAdmin' => user_akses($user)['admin'],
        'akun' => array_values(array_map(fn($a) => ['kode' => $a['Kode'], 'nama' => $a['Nama'], 'kategori' => $a['Kategori'], 'sub' => $a['Subkategori'], 'normal' => $a['SaldoNormal'], 'status' => $a['Status']], a_akunMap())),
        'kontak' => array_map(fn($k) => ['id' => $k['id'], 'nama' => $k['nama'], 'tipe' => $k['tipe'], 'status' => $k['status']], rows('SELECT id, nama, tipe, status FROM kontak ORDER BY nama')),
    ];
}
function a_changePassword(array $p, array $user): array
{
    $np = (string)($p['newPassword'] ?? '');
    if (mb_strlen($np) < 6) throw new AppErr('Password baru minimal 6 karakter.');
    if (!password_verify((string)($p['oldPassword'] ?? ''), $user['pass_hash'])) throw new AppErr('Password lama tidak sesuai.');
    q('UPDATE users SET pass_hash = ? WHERE id = ?', [make_pw($np), $user['id']]);
    kill_sessions($user['id'], $user['_token']);
    log_act($user, 'Ubah password', 'Pengguna', $user['username']);
    return ['ok' => true];
}

/* ========================================================================
 *  LIST / GET
 * ====================================================================== */
function a_list(array $p, array $user): array
{
    $e = (string)($p['entity'] ?? '');
    if ($e === 'entri') return a_entriQuery($p);
    $cfg = a_listCfg($e);
    if (!$cfg) throw new AppErr('Data tidak dikenal.');
    if (!empty($cfg['roles']) && !in_array($user['role'], $cfg['roles'], true)) throw new AppErr('Akses ditolak.');
    return a_listMem($cfg, $p);
}

function a_entriById(string $id): ?array
{
    $r = row('SELECT e.id, e.tanggal, e.no, e.jenis, e.ket, e.kontakId, COALESCE(k.nama, "") kontak, e.total, e.ref, e.user FROM (' . a_entriBase() . ') e LEFT JOIN kontak k ON k.id = e.kontakId WHERE e.id = ?', [$id]);
    if ($r) $r['total'] = jn($r['total']);
    return $r;
}

function a_get(array $p, array $user): array
{
    $ak = a_akunMap();
    $id = (string)($p['id'] ?? '');
    if (($p['entity'] ?? '') === 'entri') {
        $e = a_entriById($id);
        if (!$e) throw new AppErr('Transaksi tidak ditemukan.');
        $lines = array_map(fn($l) => ['kode' => $l['kode_akun'], 'nama' => $ak[$l['kode_akun']]['Nama'] ?? '(Akun tidak dikenal)', 'ket' => $l['keterangan'], 'debit' => jn($l['debit']), 'kredit' => jn($l['kredit'])],
            rows('SELECT * FROM jurnal WHERE entri_id = ? ORDER BY id', [$id]));
        $deb = array_values(array_filter($lines, fn($l) => $l['debit'] > 0)); $kre = array_values(array_filter($lines, fn($l) => $l['kredit'] > 0));
        $form = null;
        if (!$e['ref']) {
            if ($e['jenis'] === 'Kas Masuk' && count($deb) === 1) $form = ['jenis' => 'Kas Masuk', 'akunKas' => $deb[0]['kode'], 'lines' => array_map(fn($l) => ['akun' => $l['kode'], 'ket' => $l['ket'], 'jumlah' => $l['kredit']], $kre)];
            elseif ($e['jenis'] === 'Kas Keluar' && count($kre) === 1) $form = ['jenis' => 'Kas Keluar', 'akunKas' => $kre[0]['kode'], 'lines' => array_map(fn($l) => ['akun' => $l['kode'], 'ket' => $l['ket'], 'jumlah' => $l['debit']], $deb)];
            else $form = ['jenis' => 'Jurnal Umum', 'lines' => array_map(fn($l) => ['akun' => $l['kode'], 'ket' => $l['ket'], 'debit' => $l['debit'] ?: '', 'kredit' => $l['kredit'] ?: ''], $lines)];
        }
        return $e + ['lines' => $lines, 'form' => $form];
    }
    if (($p['entity'] ?? '') === 'faktur') {
        $f = null; foreach (a_fakturList() as $x) if ($x['id'] === $id) { $f = $x; break; }
        if (!$f) throw new AppErr('Faktur tidak ditemukan.');
        $items = json_decode((string)val('SELECT item FROM faktur WHERE id = ?', [$id]), true) ?: [];
        $pays = array_map(fn($r) => ['id' => $r['id'], 'tanggal' => $r['tanggal'], 'jumlah' => jn($r['jumlah']), 'akunKas' => $r['akun_kas'], 'akunKasNama' => $ak[$r['akun_kas']]['Nama'] ?? '', 'catatan' => $r['catatan']],
            rows('SELECT * FROM pembayaran WHERE faktur_id = ? ORDER BY tanggal, id', [$id]));
        return ['faktur' => $f, 'items' => $items, 'pembayaran' => $pays];
    }
    throw new AppErr('Data tidak dikenal.');
}

/* ========================================================================
 *  SAVE / DELETE
 * ====================================================================== */
function a_save(array $p, array $user): array
{
    $e = (string)($p['entity'] ?? '');
    if ($e === 'pengguna') a_need($user, ['admin']); else a_need($user, ['admin', 'akuntan']);
    $d = $p['data'] ?? [];
    switch ($e) {
        case 'entri': return a_saveEntri($d, $user);
        case 'faktur': return a_saveFaktur($d, $user);
        case 'akun': return a_saveAkun($d, $user);
        case 'kontak': return a_saveKontak($d, $user);
        case 'pengguna': return a_savePengguna($d, $user);
    }
    throw new AppErr('Data tidak dikenal.');
}
function a_delete(array $p, array $user): array
{
    $e = (string)($p['entity'] ?? ''); $id = (string)($p['id'] ?? '');
    if ($e === 'pengguna') a_need($user, ['admin']); else a_need($user, ['admin', 'akuntan']);
    switch ($e) {
        case 'entri': return a_delEntri($id, $user);
        case 'faktur': return a_delFaktur($id, $user);
        case 'akun': return a_delAkun($id, $user);
        case 'kontak': return a_delKontak($id, $user);
        case 'pengguna': return a_delPengguna($id, $user);
    }
    throw new AppErr('Data tidak dikenal.');
}

function a_refMsg(string $ref, string $aksi): string
{
    if (strpos($ref, 'F:') === 0 || strpos($ref, 'P:') === 0) return 'Transaksi ini dibuat otomatis dari faktur. ' . $aksi . ' melalui menu Faktur.';
    foreach (['AST' => 'Inventaris Aset', 'GJ' => 'SDM & Penggajian', 'KB' => 'SDM & Penggajian (kasbon)', 'ON' => 'Toko Online', 'ONDP' => 'Toko Online (pembayaran pesanan)', 'ONRF' => 'Toko Online (pembatalan pesanan)'] as $p => $m)
        if (strpos($ref, $p . ':') === 0) return 'Transaksi ini dibuat otomatis dari modul ' . $m . ' (' . $ref . '). ' . $aksi . ' melalui modul tersebut.';
    return 'Transaksi ini dibuat otomatis dari modul Kasir (' . $ref . '). ' . $aksi . ' melalui modul Kasir (batalkan transaksi / stok opname).';
}

/* ---- Jurnal / Kas ---- */
function a_saveEntri(array $d, array $user): array
{
    a_assertDate($d['tanggal'] ?? null); a_assertOpen($d['tanggal']);
    if (!in_array($d['jenis'] ?? '', JENIS_FORM, true)) throw new AppErr('Jenis transaksi tidak valid.');
    $ak = a_akunMap(); $ket = str($d['ket'] ?? ''); $src = $d['lines'] ?? []; $lines = [];
    if ($d['jenis'] === 'Jurnal Umum') {
        foreach ($src as $l) {
            $dr = r2(num($l['debit'] ?? 0)); $kr = r2(num($l['kredit'] ?? 0)); $acc = (string)($l['akun'] ?? '');
            if ($acc === '' && !$dr && !$kr) continue;
            if (!isset($ak[$acc])) throw new AppErr('Akun pada salah satu baris tidak valid.');
            if ($dr < 0 || $kr < 0) throw new AppErr('Jumlah tidak boleh negatif.');
            if ($dr > 0 && $kr > 0) throw new AppErr('Satu baris tidak boleh berisi debit dan kredit sekaligus.');
            if (!$dr && !$kr) throw new AppErr('Jumlah pada salah satu baris belum diisi.');
            $lines[] = [$acc, str($l['ket'] ?? '') ?: $ket, $dr, $kr];
        }
        if (count($lines) < 2) throw new AppErr('Jurnal minimal terdiri dari 2 baris.');
        $td = 0; $tk = 0; foreach ($lines as $l) { $td += $l[2]; $tk += $l[3]; }
        if (abs($td - $tk) > 0.005) throw new AppErr('Jurnal belum seimbang. Selisih debit-kredit: ' . fmt_num($td - $tk));
    } else {
        $kas = (string)($d['akunKas'] ?? '');
        if (!isset($ak[$kas])) throw new AppErr('Pilih akun kas/bank.');
        $total = 0; $rest = [];
        foreach ($src as $l) {
            $jm = r2(num($l['jumlah'] ?? 0)); $acc = (string)($l['akun'] ?? '');
            if ($acc === '' && !$jm) continue;
            if (!isset($ak[$acc])) throw new AppErr('Akun pada salah satu baris tidak valid.');
            if ($jm <= 0) throw new AppErr('Jumlah harus lebih dari 0.');
            if ($acc === $kas) throw new AppErr('Akun rincian tidak boleh sama dengan akun kas/bank.');
            $rest[] = [$acc, str($l['ket'] ?? '') ?: $ket, $jm]; $total += $jm;
        }
        if (!$rest) throw new AppErr('Tambahkan minimal satu baris rincian.');
        $total = r2($total); $kasKet = $ket ?: $rest[0][1];
        if ($d['jenis'] === 'Kas Masuk') { $lines[] = [$kas, $kasKet, $total, 0]; foreach ($rest as $x) $lines[] = [$x[0], $x[1], 0, $x[2]]; }
        else { foreach ($rest as $x) $lines[] = [$x[0], $x[1], $x[2], 0]; $lines[] = [$kas, $kasKet, 0, $total]; }
    }
    $kon = !empty($d['kontakId']) && isset(a_kontakMap()[$d['kontakId']]) ? (string)$d['kontakId'] : '';
    if (!empty($d['id'])) {
        $old = a_entriById((string)$d['id']);
        if (!$old) throw new AppErr('Transaksi tidak ditemukan.');
        if ($old['ref']) throw new AppErr(a_refMsg($old['ref'], 'Ubah'));
        a_assertOpen($old['tanggal']);
        $id = (string)$d['id']; $no = str($d['no'] ?? '', 40) ?: $old['no'];
        q('DELETE FROM jurnal WHERE entri_id = ?', [$id]);
    } else {
        $id = uid('J', 10);
        $no = str($d['no'] ?? '', 40) ?: a_nextNo($d['jenis'] === 'Kas Masuk' ? 'KM' : ($d['jenis'] === 'Kas Keluar' ? 'KK' : 'JU'), $d['tanggal']);
    }
    $now = now_s();
    insert_many('jurnal', ['entri_id', 'tanggal', 'no_bukti', 'jenis', 'kode_akun', 'keterangan', 'debit', 'kredit', 'kontak_id', 'ref', 'dibuat_oleh', 'dibuat_pada'],
        array_map(fn($l) => [$id, $d['tanggal'], $no, $d['jenis'], $l[0], mb_substr($l[1], 0, 255), $l[2], $l[3], $kon, '', $user['username'], $now], $lines));
    log_act($user, !empty($d['id']) ? 'Ubah' : 'Tambah', 'Jurnal', $no . ' (' . $d['jenis'] . ')');
    return ['ok' => true, 'id' => $id, 'no' => $no];
}
function a_delEntri(string $id, array $user): array
{
    $e = a_entriById($id);
    if (!$e) throw new AppErr('Transaksi tidak ditemukan.');
    if ($e['ref']) throw new AppErr(a_refMsg($e['ref'], 'Hapus'));
    a_assertOpen($e['tanggal']);
    q('DELETE FROM jurnal WHERE entri_id = ?', [$id]);
    log_act($user, 'Hapus', 'Jurnal', (string)$e['no']);
    return ['ok' => true];
}

/* ---- Faktur ---- */
function a_fakturLines(string $tipe, string $lawan, float $sub, float $ppn, float $total, array $st, string $ket): array
{
    if ($tipe === 'Penjualan') {
        $a = [[$st['AkunPiutang'], $ket, $total, 0], [$lawan, $ket, 0, $sub]];
        if ($ppn > 0) $a[] = [$st['AkunPPNKeluaran'], 'PPN Keluaran', 0, $ppn];
        return $a;
    }
    $b = [[$lawan, $ket, $sub, 0]];
    if ($ppn > 0) $b[] = [$st['AkunPPNMasukan'], 'PPN Masukan', $ppn, 0];
    $b[] = [$st['AkunHutang'], $ket, 0, $total];
    return $b;
}
function a_payLines(string $tipe, string $kas, float $jml, array $st, string $ket): array
{
    return $tipe === 'Penjualan' ? [[$kas, $ket, $jml, 0], [$st['AkunPiutang'], $ket, 0, $jml]] : [[$st['AkunHutang'], $ket, $jml, 0], [$kas, $ket, 0, $jml]];
}
function a_jurnalInsert(string $eid, string $tgl, string $no, string $jenis, array $lines, string $kontak, string $ref, array $user): void
{
    $now = now_s();
    insert_many('jurnal', ['entri_id', 'tanggal', 'no_bukti', 'jenis', 'kode_akun', 'keterangan', 'debit', 'kredit', 'kontak_id', 'ref', 'dibuat_oleh', 'dibuat_pada'],
        array_map(fn($l) => [$eid, $tgl, $no, $jenis, $l[0], mb_substr((string)$l[1], 0, 255), $l[2], $l[3], $kontak, $ref, $user['username'], $now], $lines));
}

/** Validasi + simpan faktur (dipakai juga oleh Stok Masuk kasir untuk pembelian secara hutang). */
function a_fakturCreateInternal(array $d, array $user, ?array $old = null): array
{
    $tipe = ($d['tipe'] ?? '') === 'Pembelian' ? 'Pembelian' : 'Penjualan';
    a_assertDate($d['tanggal'] ?? null);
    $jt = $d['jatuhTempo'] ?? '' ?: $d['tanggal']; a_assertDate($jt);
    if ($jt < $d['tanggal']) throw new AppErr('Jatuh tempo tidak boleh sebelum tanggal faktur.');
    a_assertOpen($d['tanggal']);
    $kontak = (string)($d['kontakId'] ?? '');
    if (!$kontak || !val('SELECT id FROM kontak WHERE id = ?', [$kontak])) throw new AppErr('Pilih ' . ($tipe === 'Penjualan' ? 'pelanggan' : 'pemasok') . '.');
    $ak = a_akunMap();
    if (!isset($ak[$d['akunLawan'] ?? ''])) throw new AppErr('Pilih akun ' . ($tipe === 'Penjualan' ? 'pendapatan.' : 'beban / persediaan.'));
    $items = []; $sub = 0;
    foreach ($d['items'] ?? [] as $it) {
        $qv = num($it['q'] ?? 0); $hv = num($it['h'] ?? 0); $desc = str($it['d'] ?? '', 255);
        if ($desc === '' && !$qv && !$hv) continue;
        if ($desc === '') throw new AppErr('Deskripsi item wajib diisi.');
        if ($qv <= 0) throw new AppErr('Qty harus lebih dari 0.');
        if ($hv < 0) throw new AppErr('Harga tidak boleh negatif.');
        $items[] = ['d' => $desc, 'q' => jn($qv), 'h' => jn($hv)]; $sub += $qv * $hv;
    }
    if (!$items) throw new AppErr('Tambahkan minimal satu item.');
    $st = settings_all(); $subtotal = r2($sub); $pp = max(0, num($d['persenPPN'] ?? 0)); $ppn = r2($subtotal * $pp / 100); $total = r2($subtotal + $ppn);
    foreach ($tipe === 'Penjualan' ? ['AkunPiutang', 'AkunPPNKeluaran'] : ['AkunHutang', 'AkunPPNMasukan'] as $k) if (!isset($ak[$st[$k]])) throw new AppErr('Akun default ' . $k . ' belum diatur di Pengaturan.');
    $ket = str($d['ket'] ?? '');
    if ($old) {
        a_assertOpen($old['tanggal']);
        if ($total < $old['terbayar'] - 0.005) throw new AppErr('Total faktur tidak boleh lebih kecil dari jumlah yang sudah dibayar (' . fmt_num($old['terbayar']) . ').');
        $tipe = $old['tipe']; $id = $old['id']; $no = str($d['nomor'] ?? '', 40) ?: $old['nomor'];
        q('DELETE FROM jurnal WHERE ref = ?', ['F:' . $id]);
        q('UPDATE faktur SET nomor=?, tanggal=?, jatuh_tempo=?, kontak_id=?, keterangan=?, subtotal=?, persen_ppn=?, ppn=?, total=?, akun_lawan=?, item=?, dibuat_oleh=?, dibuat_pada=? WHERE id=?',
            [$no, $d['tanggal'], $jt, $kontak, $ket, $subtotal, $pp, $ppn, $total, $d['akunLawan'], json_encode($items, JSON_UNESCAPED_UNICODE), $user['username'], now_s(), $id]);
    } else {
        $id = uid('F', 10);
        $no = str($d['nomor'] ?? '', 40) ?: a_nextNo($tipe === 'Penjualan' ? 'INV' : 'PO', $d['tanggal']);
        q('INSERT INTO faktur (id, nomor, tipe, tanggal, jatuh_tempo, kontak_id, keterangan, subtotal, persen_ppn, ppn, total, akun_lawan, item, sumber, dibuat_oleh, dibuat_pada) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$id, $no, $tipe, $d['tanggal'], $jt, $kontak, $ket, $subtotal, $pp, $ppn, $total, $d['akunLawan'], json_encode($items, JSON_UNESCAPED_UNICODE), (string)($d['sumber'] ?? ''), $user['username'], now_s()]);
    }
    $jket = ($tipe === 'Penjualan' ? 'Penjualan ' : 'Pembelian ') . $no . ($ket ? ' - ' . $ket : '');
    a_jurnalInsert(uid('J', 10), $d['tanggal'], $no, 'Faktur ' . $tipe, a_fakturLines($tipe, $d['akunLawan'], $subtotal, $ppn, $total, $st, $jket), $kontak, 'F:' . $id, $user);
    log_act($user, $old ? 'Ubah' : 'Tambah', 'Faktur', $no . ' total ' . fmt_num($total));
    return ['ok' => true, 'id' => $id, 'no' => $no];
}
function a_fakturFind(string $id): ?array { foreach (a_fakturList() as $x) if ($x['id'] === $id) return $x; return null; }
function a_saveFaktur(array $d, array $user): array
{
    $old = null;
    if (!empty($d['id'])) {
        $old = a_fakturFind((string)$d['id']);
        if (!$old) throw new AppErr('Faktur tidak ditemukan.');
        if ($old['sumber']) throw new AppErr('Purchase order ini dibuat otomatis dari Stok Masuk kasir (' . substr($old['sumber'], 3) . ') dan tidak dapat diubah. Pembayaran tetap bisa dicatat.');
    }
    unset($d['sumber']);
    return a_fakturCreateInternal($d, $user, $old);
}
function a_delFaktur(string $id, array $user): array
{
    $f = a_fakturFind($id);
    if (!$f) throw new AppErr('Faktur tidak ditemukan.');
    if ($f['sumber']) throw new AppErr('Purchase order ini dibuat otomatis dari Stok Masuk kasir (' . substr($f['sumber'], 3) . ') sehingga tidak dapat dihapus. Stok barangnya sudah bertambah.');
    a_assertOpen($f['tanggal']);
    $refs = ['F:' . $id];
    foreach (rows('SELECT id FROM pembayaran WHERE faktur_id = ?', [$id]) as $r) $refs[] = 'P:' . $r['id'];
    q('DELETE FROM jurnal WHERE ref IN (' . implode(',', array_fill(0, count($refs), '?')) . ')', $refs);
    q('DELETE FROM pembayaran WHERE faktur_id = ?', [$id]);
    q('DELETE FROM faktur WHERE id = ?', [$id]);
    log_act($user, 'Hapus', 'Faktur', $f['nomor']);
    return ['ok' => true];
}
function a_savePayment(array $p, array $user): array
{
    a_need($user, ['admin', 'akuntan']);
    $f = a_fakturFind((string)($p['fakturId'] ?? ''));
    if (!$f) throw new AppErr('Faktur tidak ditemukan.');
    a_assertDate($p['tanggal'] ?? null); a_assertOpen($p['tanggal']);
    $jml = r2(num($p['jumlah'] ?? 0));
    if ($jml <= 0) throw new AppErr('Jumlah pembayaran harus lebih dari 0.');
    if ($jml > $f['sisa'] + 0.005) throw new AppErr('Jumlah melebihi sisa tagihan (' . fmt_num($f['sisa']) . ').');
    if (!isset(a_akunMap()[$p['akunKas'] ?? ''])) throw new AppErr('Pilih akun kas/bank.');
    $st = settings_all(); $id = uid('P', 10); $catatan = str($p['catatan'] ?? '');
    $no = a_nextNo($f['tipe'] === 'Penjualan' ? 'KM' : 'KK', $p['tanggal']);
    $ket = 'Pembayaran ' . $f['nomor'] . ($catatan ? ' - ' . $catatan : '');
    q('INSERT INTO pembayaran (id, faktur_id, tanggal, jumlah, akun_kas, catatan, dibuat_oleh) VALUES (?,?,?,?,?,?,?)', [$id, $f['id'], $p['tanggal'], $jml, $p['akunKas'], $catatan, $user['username']]);
    a_jurnalInsert(uid('J', 10), $p['tanggal'], $no, $f['tipe'] === 'Penjualan' ? 'Pembayaran Piutang' : 'Pembayaran Hutang', a_payLines($f['tipe'], $p['akunKas'], $jml, $st, $ket), $f['kontakId'], 'P:' . $id, $user);
    log_act($user, 'Pembayaran', 'Faktur', $f['nomor'] . ' sebesar ' . fmt_num($jml));
    return ['ok' => true, 'id' => $id];
}
function a_deletePayment(array $p, array $user): array
{
    a_need($user, ['admin', 'akuntan']);
    $r = row('SELECT * FROM pembayaran WHERE id = ?', [(string)($p['id'] ?? '')]);
    if (!$r) throw new AppErr('Pembayaran tidak ditemukan.');
    if ($rb = val('SELECT no FROM retur_beli WHERE bayar_id = ?', [$r['id']])) throw new AppErr('Potongan ini berasal dari retur pembelian ' . $rb . ' (barang sudah keluar dari stok) sehingga tidak dapat dihapus.');
    a_assertOpen($r['tanggal']);
    q('DELETE FROM jurnal WHERE ref = ?', ['P:' . $r['id']]);
    q('DELETE FROM pembayaran WHERE id = ?', [$r['id']]);
    log_act($user, 'Hapus pembayaran', 'Faktur', $r['id']);
    return ['ok' => true];
}

/* ---- Akun ---- */
function a_saveAkun(array $d, array $user): array
{
    $kode = str($d['kode'] ?? '', 12); $nama = str($d['nama'] ?? '', 120);
    if (!preg_match('/^[0-9A-Za-z.\-]{2,12}$/', $kode)) throw new AppErr('Kode akun 2-12 karakter (huruf/angka).');
    if ($nama === '') throw new AppErr('Nama akun wajib diisi.');
    if (!in_array($d['kategori'] ?? '', KATEGORI, true)) throw new AppErr('Kategori tidak valid.');
    $normal = ($d['normal'] ?? '') === 'Kredit' ? 'Kredit' : (($d['normal'] ?? '') === 'Debit' ? 'Debit' : (in_array($d['kategori'], ['Aset', 'Beban'], true) ? 'Debit' : 'Kredit'));
    $vals = [$nama, $d['kategori'], str($d['sub'] ?? '', 60), $normal, num($d['saldoAwal'] ?? 0), ($d['status'] ?? '') === 'Nonaktif' ? 'Nonaktif' : 'Aktif', str($d['ket'] ?? '')];
    if (!empty($d['orig'])) {
        if (!val('SELECT kode FROM akun WHERE kode = ?', [$d['orig']])) throw new AppErr('Akun tidak ditemukan.');
        $kode = (string)$d['orig'];
        q('UPDATE akun SET nama=?, kategori=?, subkategori=?, saldo_normal=?, saldo_awal=?, status=?, keterangan=? WHERE kode=?', array_merge($vals, [$kode]));
    } else {
        if (val('SELECT kode FROM akun WHERE kode = ?', [$kode])) throw new AppErr('Kode akun sudah dipakai.');
        q('INSERT INTO akun (nama, kategori, subkategori, saldo_normal, saldo_awal, status, keterangan, kode) VALUES (?,?,?,?,?,?,?,?)', array_merge($vals, [$kode]));
    }
    log_act($user, !empty($d['orig']) ? 'Ubah' : 'Tambah', 'Akun', $kode . ' ' . $nama);
    return ['ok' => true];
}
function a_delAkun(string $kode, array $user): array
{
    if (val('SELECT 1 FROM jurnal WHERE kode_akun = ? LIMIT 1', [$kode]) || val('SELECT 1 FROM faktur WHERE akun_lawan = ? LIMIT 1', [$kode])) throw new AppErr('Akun sudah dipakai pada transaksi. Ubah statusnya menjadi Nonaktif.');
    $st = settings_all();
    foreach (A_ACC_KEYS as $k) if (($st[$k] ?? '') === $kode) throw new AppErr('Akun ini dipakai sebagai akun default / integrasi kasir di Pengaturan.');
    if (!val('SELECT kode FROM akun WHERE kode = ?', [$kode])) throw new AppErr('Akun tidak ditemukan.');
    q('DELETE FROM akun WHERE kode = ?', [$kode]);
    log_act($user, 'Hapus', 'Akun', $kode);
    return ['ok' => true];
}

/* ---- Kontak ---- */
function a_saveKontak(array $d, array $user): array
{
    $nama = str($d['nama'] ?? '', 150);
    if ($nama === '') throw new AppErr('Nama wajib diisi.');
    $tipe = in_array($d['tipe'] ?? '', ['Pelanggan', 'Pemasok', 'Keduanya'], true) ? $d['tipe'] : 'Pelanggan';
    $vals = [$nama, $tipe, str($d['telepon'] ?? '', 40), str($d['email'] ?? '', 120), str($d['alamat'] ?? ''), str($d['npwp'] ?? '', 40), ($d['status'] ?? '') === 'Nonaktif' ? 'Nonaktif' : 'Aktif'];
    if (!empty($d['id'])) {
        if (!val('SELECT id FROM kontak WHERE id = ?', [$d['id']])) throw new AppErr('Kontak tidak ditemukan.');
        $id = (string)$d['id'];
        q('UPDATE kontak SET nama=?, tipe=?, telepon=?, email=?, alamat=?, npwp=?, status=? WHERE id=?', array_merge($vals, [$id]));
    } else {
        $id = uid('K', 10);
        q('INSERT INTO kontak (nama, tipe, telepon, email, alamat, npwp, status, id) VALUES (?,?,?,?,?,?,?,?)', array_merge($vals, [$id]));
    }
    log_act($user, !empty($d['id']) ? 'Ubah' : 'Tambah', 'Kontak', $nama);
    return ['ok' => true, 'id' => $id];
}
function a_delKontak(string $id, array $user): array
{
    if (val('SELECT 1 FROM jurnal WHERE kontak_id = ? LIMIT 1', [$id]) || val('SELECT 1 FROM faktur WHERE kontak_id = ? LIMIT 1', [$id]) || val('SELECT 1 FROM stok_masuk_doc WHERE kontak_id = ? LIMIT 1', [$id]))
        throw new AppErr('Kontak dipakai pada transaksi. Ubah statusnya menjadi Nonaktif.');
    if (!val('SELECT id FROM kontak WHERE id = ?', [$id])) throw new AppErr('Kontak tidak ditemukan.');
    q('DELETE FROM kontak WHERE id = ?', [$id]);
    log_act($user, 'Hapus', 'Kontak', $id);
    return ['ok' => true];
}

/* ---- Pengguna (tabel yang sama dengan modul kasir) ---- */
function a_activeAdmins(): int { return (int)val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'aktif'"); }
function a_savePengguna(array $d, array $user): array
{
    $un = strtolower(trim((string)($d['username'] ?? '')));
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $un)) throw new AppErr('Username 3-30 karakter (huruf kecil, angka, titik, strip, garis bawah).');
    $role = role_norm($d['role'] ?? 'viewer');
    $status = ($d['status'] ?? '') === 'Nonaktif' ? 'nonaktif' : 'aktif';
    $nama = str($d['nama'] ?? '', 100) ?: $un; $email = str($d['email'] ?? '', 120); $pw = (string)($d['password'] ?? '');
    if (!empty($d['orig'])) {
        $old = row('SELECT * FROM users WHERE username = ?', [strtolower((string)$d['orig'])]);
        if (!$old) throw new AppErr('Pengguna tidak ditemukan.');
        $hash = $old['pass_hash']; $kill = $old['role'] !== $role || $old['status'] !== $status;
        if ($pw !== '') { if (mb_strlen($pw) < 6) throw new AppErr('Password minimal 6 karakter.'); $hash = make_pw($pw); $kill = true; }
        if ($old['role'] === 'admin' && $old['status'] === 'aktif' && ($role !== 'admin' || $status !== 'aktif') && a_activeAdmins() <= 1) throw new AppErr('Minimal harus ada satu Admin aktif.');
        if ($old['id'] === $user['id'] && ($role !== 'admin' || $status !== 'aktif')) throw new AppErr('Anda tidak dapat menurunkan/menonaktifkan akun sendiri.');
        q('UPDATE users SET nama=?, email=?, role=?, pass_hash=?, status=? WHERE id=?', [$nama, $email, $role, $hash, $status, $old['id']]);
        if ($kill) kill_sessions($old['id'], $old['id'] === $user['id'] ? $user['_token'] : null);
        log_act($user, 'Ubah', 'Pengguna', $old['username']);
    } else {
        if (val('SELECT id FROM users WHERE username = ?', [$un])) throw new AppErr('Username sudah dipakai.');
        if (mb_strlen($pw) < 6) throw new AppErr('Password minimal 6 karakter.');
        q('INSERT INTO users (id, username, nama, email, role, pass_hash, status, dibuat) VALUES (?,?,?,?,?,?,?,?)', [uid('U'), $un, $nama, $email, $role, make_pw($pw), $status, now_s()]);
        log_act($user, 'Tambah', 'Pengguna', $un);
    }
    return ['ok' => true];
}
function a_delPengguna(string $un, array $user): array
{
    $un = strtolower($un);
    if ($un === strtolower($user['username'])) throw new AppErr('Anda tidak dapat menghapus akun sendiri.');
    $u = row('SELECT * FROM users WHERE username = ?', [$un]);
    if (!$u) throw new AppErr('Pengguna tidak ditemukan.');
    if ($u['role'] === 'admin' && $u['status'] === 'aktif' && a_activeAdmins() <= 1) throw new AppErr('Minimal harus ada satu Admin aktif.');
    q('DELETE FROM users WHERE id = ?', [$u['id']]);
    kill_sessions($u['id']);
    log_act($user, 'Hapus', 'Pengguna', $un);
    return ['ok' => true];
}

/* ---- Pengaturan ---- */
function a_saveSettings(array $p, array $user): array
{
    $d = $p['data'] ?? []; $ak = a_akunMap();
    if (array_key_exists('NamaPerusahaan', $d) && str($d['NamaPerusahaan']) === '') throw new AppErr('Nama perusahaan wajib diisi.');
    if (!empty($d['KunciPeriode']) && !is_date($d['KunciPeriode'])) throw new AppErr('Tanggal kunci periode tidak valid.');
    foreach (A_ACC_KEYS as $k) if (array_key_exists($k, $d) && !isset($ak[$d[$k]])) throw new AppErr('Akun tidak valid: ' . $k);
    $put = [];
    foreach (A_SET_KEYS as $k) if (array_key_exists($k, $d)) $put[$k] = mb_substr(trim((string)($d[$k] ?? '')), 0, 500);
    if (isset($put['TarifPPN'])) $put['TarifPPN'] = (string)max(0, min(100, num($put['TarifPPN'])));
    setting_put($put);
    log_act($user, 'Ubah', 'Pengaturan', 'Pengaturan diperbarui');
    return ['ok' => true];
}

/* ========================================================================
 *  DASHBOARD
 * ====================================================================== */
function a_dashboard(array $p): array
{
    $from = $p['from'] ?? ''; $to = $p['to'] ?? '';
    if (!is_date($from) || !is_date($to) || $from > $to) throw new AppErr('Rentang tanggal tidak valid.');
    $ak = a_akunMap();
    $span = diff_days($to, $from) + 1; $pTo = add_days($from, -1); $pFrom = add_days($from, -$span); $yr = substr($to, 0, 4);
    $cur = ['p' => 0, 'b' => 0]; $prv = ['p' => 0, 'b' => 0]; $mp = array_fill(0, 12, 0); $mb = array_fill(0, 12, 0); $byB = []; $kas = [];
    foreach ($ak as $k => $a) if ($a['Subkategori'] === 'Kas & Bank') $kas[$k] = $a['SaldoNormal'] === 'Debit' ? $a['SaldoAwal'] : -$a['SaldoAwal'];
    $agg = rows('SELECT kode_akun k,
        SUM(CASE WHEN tanggal BETWEEN ? AND ? THEN debit - kredit ELSE 0 END) cur,
        SUM(CASE WHEN tanggal BETWEEN ? AND ? THEN debit - kredit ELSE 0 END) prv,
        SUM(CASE WHEN tanggal <= ? THEN debit - kredit ELSE 0 END) upto
        FROM jurnal GROUP BY kode_akun', [$from, $to, $pFrom, $pTo, $to]);
    foreach ($agg as $r) {
        $a = $ak[$r['k']] ?? null; if (!$a) continue;
        if ($a['Kategori'] === 'Pendapatan') { $cur['p'] += -(float)$r['cur']; $prv['p'] += -(float)$r['prv']; }
        elseif ($a['Kategori'] === 'Beban') { $cur['b'] += (float)$r['cur']; $prv['b'] += (float)$r['prv']; $byB[$a['Kode']] = ($byB[$a['Kode']] ?? 0) + (float)$r['cur']; }
        if ($a['Subkategori'] === 'Kas & Bank') $kas[$a['Kode']] += (float)$r['upto'];
    }
    foreach (rows('SELECT kode_akun k, MONTH(tanggal) m, SUM(debit - kredit) n FROM jurnal WHERE tanggal BETWEEN ? AND ? GROUP BY kode_akun, MONTH(tanggal)', [$yr . '-01-01', $yr . '-12-31']) as $r) {
        $a = $ak[$r['k']] ?? null; if (!$a) continue;
        if ($a['Kategori'] === 'Pendapatan') $mp[(int)$r['m'] - 1] += -(float)$r['n'];
        elseif ($a['Kategori'] === 'Beban') $mb[(int)$r['m'] - 1] += (float)$r['n'];
    }
    $fk = a_fakturList(); $piutang = 0; $hutang = 0; $pJT = 0; $hJT = 0;
    foreach ($fk as $f) {
        if ($f['sisa'] <= 0.005) continue;
        if ($f['tipe'] === 'Penjualan') { $piutang += $f['sisa']; if ($f['status'] === 'Jatuh Tempo') $pJT += $f['sisa']; }
        else { $hutang += $f['sisa']; if ($f['status'] === 'Jatuh Tempo') $hJT += $f['sisa']; }
    }
    $kasList = []; $kasTotal = 0;
    ksort($kas, SORT_STRING);
    foreach ($kas as $k => $v) if ($ak[$k]['Status'] === 'Aktif' || $v) { $kasList[] = ['kode' => (string)$k, 'nama' => $ak[$k]['Nama'], 'saldo' => jn(r2($v))]; $kasTotal += $v; }
    $bebanTop = [];
    foreach ($byB as $k => $v) if ($v > 0) $bebanTop[] = ['nama' => $ak[$k]['Nama'], 'total' => jn(r2($v))];
    usort($bebanTop, fn($a, $b) => $b['total'] <=> $a['total']);
    $recent = a_entriQuery(['size' => 8, 'page' => 1], false, ['e.tanggal DESC', 'e.no DESC'])['rows'];
    $due = array_values(array_filter($fk, fn($f) => $f['sisa'] > 0.005));
    usort($due, fn($a, $b) => strcmp((string)$a['jatuhTempo'], (string)$b['jatuhTempo']));
    $r2a = fn($a) => array_map(fn($x) => jn(r2($x)), $a);
    return [
        'kpi' => [
            'pendapatan' => jn(r2($cur['p'])), 'beban' => jn(r2($cur['b'])), 'laba' => jn(r2($cur['p'] - $cur['b'])),
            'prev' => ['pendapatan' => jn(r2($prv['p'])), 'beban' => jn(r2($prv['b'])), 'laba' => jn(r2($prv['p'] - $prv['b']))],
            'kas' => jn(r2($kasTotal)), 'piutang' => jn(r2($piutang)), 'hutang' => jn(r2($hutang)), 'piutangJT' => jn(r2($pJT)), 'hutangJT' => jn(r2($hJT)),
        ],
        'monthly' => ['tahun' => $yr, 'pendapatan' => $r2a($mp), 'beban' => $r2a($mb)],
        'bebanTop' => $bebanTop, 'kasList' => $kasList, 'recent' => $recent, 'due' => array_slice($due, 0, 6),
        'counts' => ['entri' => (int)val('SELECT COUNT(DISTINCT entri_id) FROM jurnal'), 'faktur' => count($fk)],
        'pos' => a_posSummary($from, $to),
    ];
}
/** Ringkasan penjualan kasir untuk dashboard akuntansi. */
function a_posSummary(string $from, string $to): array
{
    $r = row("SELECT COUNT(*) n, COALESCE(SUM(total),0) omzet, COALESCE(SUM(laba),0) laba FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?", [$from . ' 00:00:00', $to . ' 23:59:59']);
    $nilai = (float)val('SELECT COALESCE(SUM(stok * harga_beli), 0) FROM produk WHERE stok > 0');
    return ['n' => (int)$r['n'], 'omzet' => jn($r['omzet']), 'laba' => jn($r['laba']), 'nilaiStok' => jn(round($nilai))];
}

/* ========================================================================
 *  LAPORAN
 * ====================================================================== */
function a_report(array $p, array $user): array { return a_buildTable((string)($p['kind'] ?? ''), $p['params'] ?? [], $user); }
function a_sub(string $f, string $t): string { return 'Periode: ' . dmy($f) . ' s.d. ' . dmy($t); }
function a_checkRange(array $p): void { if (!is_date($p['from'] ?? null) || !is_date($p['to'] ?? null) || $p['from'] > $p['to']) throw new AppErr('Rentang tanggal tidak valid.'); }
function C(string $label, string $type = 'text'): array { return ['label' => $label, 'type' => $type]; }

function a_rptNeracaSaldo(array $p): array
{
    a_checkRange($p);
    $ak = a_akunMap(); $before = []; $mov = []; $codes = [];
    foreach (rows('SELECT kode_akun k, SUM(CASE WHEN tanggal < ? THEN debit ELSE 0 END) bd, SUM(CASE WHEN tanggal < ? THEN kredit ELSE 0 END) bk, SUM(CASE WHEN tanggal >= ? THEN debit ELSE 0 END) md, SUM(CASE WHEN tanggal >= ? THEN kredit ELSE 0 END) mk FROM jurnal WHERE tanggal <= ? GROUP BY kode_akun',
        [$p['from'], $p['from'], $p['from'], $p['from'], $p['to']]) as $r) { $before[$r['k']] = [(float)$r['bd'], (float)$r['bk']]; $mov[$r['k']] = [(float)$r['md'], (float)$r['mk']]; $codes[$r['k']] = 1; }
    foreach ($ak as $k => $_) $codes[$k] = 1;
    $codes = array_map('strval', array_keys($codes)); sort($codes, SORT_STRING);
    $rows = []; $styles = []; $tot = [0, 0, 0, 0];
    foreach ($codes as $k) {
        $a = $ak[$k] ?? ['Nama' => '(Akun tidak dikenal)', 'SaldoNormal' => 'Debit', 'SaldoAwal' => 0]; $isD = $a['SaldoNormal'] === 'Debit';
        $b = $before[$k] ?? [0, 0]; $m = $mov[$k] ?? [0, 0];
        $open = ($isD ? $a['SaldoAwal'] : -$a['SaldoAwal']) + $b[0] - $b[1]; $end = $open + $m[0] - $m[1];
        if (!$open && !$m[0] && !$m[1] && !$end) continue;
        $ed = $end > 0 ? $end : 0; $ek = $end < 0 ? -$end : 0;
        $rows[] = [$k, $a['Nama'], r2($isD ? $open : -$open), r2($m[0]), r2($m[1]), r2($ed), r2($ek)]; $styles[] = 'i';
        $tot[0] += $m[0]; $tot[1] += $m[1]; $tot[2] += $ed; $tot[3] += $ek;
    }
    $rows[] = ['', 'TOTAL', null, r2($tot[0]), r2($tot[1]), r2($tot[2]), r2($tot[3])]; $styles[] = 'g';
    return ['title' => 'Neraca Saldo', 'subtitle' => a_sub($p['from'], $p['to']) . '  |  Saldo awal ditampilkan menurut saldo normal akun', 'columns' => [C('Kode'), C('Nama Akun'), C('Saldo Awal', 'num'), C('Mutasi Debit', 'num'), C('Mutasi Kredit', 'num'), C('Saldo Akhir Debit', 'num'), C('Saldo Akhir Kredit', 'num')], 'rows' => $rows, 'styles' => $styles];
}

function a_rptLabaRugi(array $p): array
{
    a_checkRange($p);
    $ak = a_akunMap(); $sum = [];
    foreach (rows('SELECT kode_akun k, SUM(debit) - SUM(kredit) n FROM jurnal WHERE tanggal BETWEEN ? AND ? GROUP BY kode_akun', [$p['from'], $p['to']]) as $r) $sum[$r['k']] = (float)$r['n'];
    $rows = []; $styles = [];
    $section = function ($title, $pred, $sign) use ($ak, $sum, &$rows, &$styles) {
        $rows[] = ['', $title, null]; $styles[] = 'h'; $t = 0;
        foreach ($ak as $k => $a) {
            if (!$pred($a)) continue;
            $v = ($sum[$k] ?? 0) * $sign; if (abs($v) < 0.005) continue;
            $rows[] = [(string)$k, $a['Nama'], r2($v)]; $styles[] = 'i'; $t += $v;
        }
        $rows[] = ['', 'Total ' . mb_strtolower($title), r2($t)]; $styles[] = 't';
        return $t;
    };
    $pend = $section('Pendapatan', fn($a) => $a['Kategori'] === 'Pendapatan', -1);
    $hpp = $section('Harga Pokok Penjualan', fn($a) => $a['Kategori'] === 'Beban' && $a['Subkategori'] === 'Harga Pokok', 1);
    $rows[] = ['', 'Laba kotor', r2($pend - $hpp)]; $styles[] = 't';
    $opr = $section('Beban Operasional', fn($a) => $a['Kategori'] === 'Beban' && $a['Subkategori'] !== 'Harga Pokok', 1);
    $rows[] = ['', 'LABA (RUGI) BERSIH', r2($pend - $hpp - $opr)]; $styles[] = 'g';
    return ['title' => 'Laporan Laba Rugi', 'subtitle' => a_sub($p['from'], $p['to']), 'columns' => [C('Kode'), C('Keterangan'), C('Jumlah', 'num')], 'rows' => $rows, 'styles' => $styles];
}

function a_rptNeraca(array $p): array
{
    if (!is_date($p['to'] ?? null)) throw new AppErr('Tanggal tidak valid.');
    $ak = a_akunMap(); $net = a_balNet($p['to']); $rows = []; $styles = []; $laba = 0;
    foreach ($net as $k => $v) { $a = $ak[$k] ?? null; if ($a && in_array($a['Kategori'], ['Pendapatan', 'Beban'], true)) $laba -= $v; }
    $section = function ($kat, $sign) use ($ak, $net, $laba, &$rows, &$styles) {
        $rows[] = ['', mb_strtoupper($kat), null]; $styles[] = 'h'; $t = 0;
        foreach ($ak as $k => $a) {
            if ($a['Kategori'] !== $kat) continue;
            $v = ($net[$k] ?? 0) * $sign; if (abs($v) < 0.005) continue;
            $rows[] = [(string)$k, $a['Nama'], r2($v)]; $styles[] = 'i'; $t += $v;
        }
        if ($kat === 'Ekuitas') { $rows[] = ['', 'Laba (rugi) ditahan & berjalan', r2($laba)]; $styles[] = 'i'; $t += $laba; }
        $rows[] = ['', 'Total ' . mb_strtolower($kat), r2($t)]; $styles[] = 't';
        return $t;
    };
    $aset = $section('Aset', 1); $kew = $section('Kewajiban', -1); $eku = $section('Ekuitas', -1);
    $rows[] = ['', 'TOTAL KEWAJIBAN + EKUITAS', r2($kew + $eku)]; $styles[] = 'g';
    $diff = r2($aset - $kew - $eku);
    if (abs($diff) > 0.005) { $rows[] = ['', 'Selisih (periksa saldo awal / jurnal)', $diff]; $styles[] = 'w'; }
    return ['title' => 'Neraca', 'subtitle' => 'Per tanggal ' . dmy($p['to']), 'columns' => [C('Kode'), C('Keterangan'), C('Jumlah', 'num')], 'rows' => $rows, 'styles' => $styles];
}

function a_rptBukuBesar(array $p): array
{
    a_checkRange($p);
    $a = a_akunMap()[(string)($p['akun'] ?? '')] ?? null;
    if (!$a) throw new AppErr('Pilih akun terlebih dahulu.');
    $isD = $a['SaldoNormal'] === 'Debit'; $sgn = $isD ? 1 : -1;
    $open = ($isD ? $a['SaldoAwal'] : -$a['SaldoAwal']) + (float)val('SELECT COALESCE(SUM(debit) - SUM(kredit), 0) FROM jurnal WHERE kode_akun = ? AND tanggal < ?', [$a['Kode'], $p['from']]);
    $mv = rows('SELECT tanggal, no_bukti, keterangan, debit, kredit FROM jurnal WHERE kode_akun = ? AND tanggal BETWEEN ? AND ? ORDER BY tanggal, no_bukti, id', [$a['Kode'], $p['from'], $p['to']]);
    $run = $open; $td = 0; $tk = 0; $rows = [[$p['from'], '', 'Saldo awal', null, null, r2($run * $sgn)]]; $styles = ['t'];
    foreach ($mv as $l) {
        $d = (float)$l['debit']; $k = (float)$l['kredit'];
        $run += $d - $k; $td += $d; $tk += $k;
        $rows[] = [$l['tanggal'], $l['no_bukti'], $l['keterangan'], $d ?: null, $k ?: null, r2($run * $sgn)]; $styles[] = 'i';
    }
    $rows[] = ['', '', 'TOTAL MUTASI / SALDO AKHIR', r2($td), r2($tk), r2($run * $sgn)]; $styles[] = 'g';
    return ['title' => 'Buku Besar - ' . $a['Kode'] . ' ' . $a['Nama'], 'subtitle' => a_sub($p['from'], $p['to']), 'columns' => [C('Tanggal', 'date'), C('No Bukti'), C('Keterangan'), C('Debit', 'num'), C('Kredit', 'num'), C('Saldo', 'num')], 'rows' => $rows, 'styles' => $styles];
}

function a_rptKas(array $p): array
{
    a_checkRange($p);
    $ak = a_akunMap(); $st = [];
    foreach ($ak as $k => $a) if ($a['Subkategori'] === 'Kas & Bank') $st[$k] = [$a['SaldoNormal'] === 'Debit' ? $a['SaldoAwal'] : -$a['SaldoAwal'], 0, 0];
    if ($st) {
        $codes = array_map('strval', array_keys($st));
        foreach (rows('SELECT kode_akun k, SUM(CASE WHEN tanggal < ? THEN debit - kredit ELSE 0 END) o, SUM(CASE WHEN tanggal >= ? THEN debit ELSE 0 END) d, SUM(CASE WHEN tanggal >= ? THEN kredit ELSE 0 END) c FROM jurnal WHERE tanggal <= ? AND kode_akun IN (' . implode(',', array_fill(0, count($codes), '?')) . ') GROUP BY kode_akun',
            array_merge([$p['from'], $p['from'], $p['from'], $p['to']], $codes)) as $r) { $st[$r['k']][0] += (float)$r['o']; $st[$r['k']][1] += (float)$r['d']; $st[$r['k']][2] += (float)$r['c']; }
    }
    ksort($st, SORT_STRING);
    $rows = []; $styles = []; $tot = [0, 0, 0, 0];
    foreach ($st as $k => $o) {
        $end = $o[0] + $o[1] - $o[2];
        if (!$o[0] && !$o[1] && !$o[2] && $ak[$k]['Status'] !== 'Aktif') continue;
        $rows[] = [(string)$k, $ak[$k]['Nama'], r2($o[0]), r2($o[1]), r2($o[2]), r2($end)]; $styles[] = 'i';
        $tot[0] += $o[0]; $tot[1] += $o[1]; $tot[2] += $o[2]; $tot[3] += $end;
    }
    $rows[] = ['', 'TOTAL KAS & BANK', r2($tot[0]), r2($tot[1]), r2($tot[2]), r2($tot[3])]; $styles[] = 'g';
    return ['title' => 'Laporan Kas & Bank', 'subtitle' => a_sub($p['from'], $p['to']), 'columns' => [C('Kode'), C('Akun'), C('Saldo Awal', 'num'), C('Masuk', 'num'), C('Keluar', 'num'), C('Saldo Akhir', 'num')], 'rows' => $rows, 'styles' => $styles];
}

function a_rptAging(array $p): array
{
    $tipe = ($p['tipe'] ?? '') === 'Pembelian' ? 'Pembelian' : 'Penjualan'; $td = today_s(); $rows = []; $styles = []; $tot = array_fill(0, 6, 0);
    $list = array_values(array_filter(a_fakturList(), fn($f) => $f['tipe'] === $tipe && $f['sisa'] > 0.005));
    usort($list, fn($a, $b) => strcmp((string)$a['jatuhTempo'], (string)$b['jatuhTempo']));
    foreach ($list as $f) {
        $late = diff_days($td, $f['jatuhTempo'] ?: $f['tanggal']); $b = [0, 0, 0, 0, 0];
        $b[$late <= 0 ? 0 : ($late <= 30 ? 1 : ($late <= 60 ? 2 : ($late <= 90 ? 3 : 4)))] = $f['sisa'];
        $rows[] = [$f['nomor'], $f['kontak'], $f['tanggal'], $f['jatuhTempo'], $f['sisa'], $b[0], $b[1], $b[2], $b[3], $b[4]]; $styles[] = 'i';
        $tot[0] += $f['sisa']; for ($i = 0; $i < 5; $i++) $tot[$i + 1] += $b[$i];
    }
    $rows[] = ['', 'TOTAL', null, null, r2($tot[0]), r2($tot[1]), r2($tot[2]), r2($tot[3]), r2($tot[4]), r2($tot[5])]; $styles[] = 'g';
    return ['title' => $tipe === 'Penjualan' ? 'Umur Piutang' : 'Umur Hutang', 'subtitle' => 'Per tanggal ' . dmy($td),
        'columns' => [C('Nomor'), C($tipe === 'Penjualan' ? 'Pelanggan' : 'Pemasok'), C('Tanggal', 'date'), C('Jatuh Tempo', 'date'), C('Sisa', 'num'), C('Belum JT', 'num'), C('1-30 hari', 'num'), C('31-60 hari', 'num'), C('61-90 hari', 'num'), C('> 90 hari', 'num')],
        'rows' => $rows, 'styles' => $styles];
}

const LISTCOLS = [
    'faktur' => ['title' => 'Daftar Faktur', 'cols' => [['Nomor', 'nomor'], ['Tipe', 'tipe'], ['Tanggal', 'tanggal', 'date'], ['Jatuh Tempo', 'jatuhTempo', 'date'], ['Kontak', 'kontak'], ['Keterangan', 'ket'], ['Subtotal', 'subtotal', 'num'], ['PPN', 'ppn', 'num'], ['Total', 'total', 'num'], ['Terbayar', 'terbayar', 'num'], ['Sisa', 'sisa', 'num'], ['Status', 'status']], 'sum' => [6, 7, 8, 9, 10]],
    'akun' => ['title' => 'Daftar Akun', 'cols' => [['Kode', 'kode'], ['Nama Akun', 'nama'], ['Kategori', 'kategori'], ['Subkategori', 'sub'], ['Saldo Normal', 'normal'], ['Saldo Awal', 'saldoAwal', 'num'], ['Saldo Saat Ini', 'saldo', 'num'], ['Status', 'status']]],
    'kontak' => ['title' => 'Daftar Kontak', 'cols' => [['Nama', 'nama'], ['Tipe', 'tipe'], ['Telepon', 'telepon'], ['Email', 'email'], ['Alamat', 'alamat'], ['NPWP', 'npwp'], ['Status', 'status']]],
    'pengguna' => ['title' => 'Daftar Pengguna', 'cols' => [['Username', 'username'], ['Nama', 'nama'], ['Email', 'email'], ['Role', 'role'], ['Status', 'status']]],
    'log' => ['title' => 'Log Aktivitas', 'cols' => [['Waktu', 'waktu'], ['User', 'user'], ['Aksi', 'aksi'], ['Modul', 'modul'], ['Detail', 'detail']]],
];

function a_tblEntri(array $p): array
{
    $r = a_entriQuery($p, true); $ak = a_akunMap(); $rows = []; $f = $p['f'] ?? [];
    $ids = array_map(fn($e) => $e['id'], $r['rows']); $lines = [];
    foreach (array_chunk($ids, 500) as $ch) foreach (rows('SELECT * FROM jurnal WHERE entri_id IN (' . implode(',', array_fill(0, count($ch), '?')) . ') ORDER BY id', $ch) as $l) $lines[$l['entri_id']][] = $l;
    foreach ($r['rows'] as $e) foreach ($lines[$e['id']] ?? [] as $l) {
        $rows[] = [$e['tanggal'], $e['no'], $e['jenis'] === 'Faktur Pembelian' ? 'Purchase Order' : $e['jenis'], $l['kode_akun'], $ak[$l['kode_akun']]['Nama'] ?? '', $l['keterangan'], $e['kontak'], (float)$l['debit'] ?: null, (float)$l['kredit'] ?: null];
    }
    return ['title' => 'Jurnal Transaksi', 'subtitle' => (!empty($f['from']) || !empty($f['to'])) ? a_sub($f['from'] ?? '...', $f['to'] ?? '...') : 'Semua periode',
        'columns' => [C('Tanggal', 'date'), C('No Bukti'), C('Jenis'), C('Kode Akun'), C('Nama Akun'), C('Keterangan'), C('Kontak'), C('Debit', 'num'), C('Kredit', 'num')], 'rows' => $rows, 'sum' => ['label' => 'TOTAL', 'cols' => [7, 8]]];
}

function a_buildTable(string $kind, array $p, array $user): array
{
    switch ($kind) {
        case 'labarugi': return a_rptLabaRugi($p);
        case 'neraca': return a_rptNeraca($p);
        case 'neracasaldo': return a_rptNeracaSaldo($p);
        case 'bukubesar': return a_rptBukuBesar($p);
        case 'kas': return a_rptKas($p);
        case 'aging': return a_rptAging($p);
        case 'entri': return a_tblEntri($p);
    }
    $def = LISTCOLS[$kind] ?? null; $cfg = a_listCfg($kind);
    if (!$def || !$cfg) throw new AppErr('Jenis laporan tidak dikenal.');
    if (!empty($cfg['roles']) && !in_array($user['role'], $cfg['roles'], true)) throw new AppErr('Akses ditolak.');
    $r = a_listMem($cfg, $p, true); $title = $def['title'];
    if ($kind === 'faktur' && !empty($p['f']['tipe'])) $title = $p['f']['tipe'] === 'Pembelian' ? 'Daftar Purchase Order' : 'Daftar Faktur Penjualan';
    $out = ['title' => $title, 'subtitle' => 'Total ' . $r['total'] . ' data', 'columns' => array_map(fn($c) => C($c[0], $c[2] ?? 'text'), $def['cols']),
        'rows' => array_map(fn($row) => array_map(function ($c) use ($row, $kind) { $v = $row[$c[1]] ?? ''; if ($kind === 'faktur' && $c[1] === 'tipe' && $v === 'Pembelian') $v = 'Purchase Order'; return $v ?? ''; }, $def['cols']), $r['rows'])];
    if (!empty($def['sum'])) $out['sum'] = ['label' => 'TOTAL', 'cols' => $def['sum']];
    return $out;
}

/* ========================================================================
 *  EKSPOR (Excel .xlsx dibuat di server, PDF lewat dialog cetak browser)
 * ====================================================================== */
function a_export(array $p, array $user): array
{
    require_once __DIR__ . '/export.php';
    $t = a_buildTable((string)($p['kind'] ?? ''), $p['params'] ?? [], $user); $st = settings_all(); $pdf = ($p['format'] ?? '') === 'pdf';
    $base = preg_replace('/\s+/', ' ', preg_replace('#[\\\\/:*?"<>|]+#', '-', $t['title'] . ' ' . today_s()));
    log_act($user, 'Ekspor', 'Laporan', $t['title'] . ' (' . ($pdf ? 'PDF' : 'Excel') . ')');
    if ($pdf) return ['name' => $base . '.pdf', 'html' => export_table_html($t, $st, $user)];
    return ['name' => $base . '.xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'b64' => base64_encode(export_table_xlsx($t, $st, $user))];
}
function a_pdfFaktur(array $p, array $user): array
{
    require_once __DIR__ . '/export.php';
    $d = a_get(['entity' => 'faktur', 'id' => $p['id'] ?? ''], $user);
    $d['kon'] = a_kontakMap()[$d['faktur']['kontakId']] ?? [];
    log_act($user, 'Cetak', 'Faktur', $d['faktur']['nomor']);
    return ['name' => $d['faktur']['nomor'] . '.pdf', 'html' => faktur_html($d, $user)];
}
function a_previewTemplate(array $p, array $user): array
{
    require_once __DIR__ . '/export.php';
    $tipe = ($p['tipe'] ?? '') === 'Pembelian' ? 'Pembelian' : 'Penjualan'; $jual = $tipe === 'Penjualan'; $td = today_s();
    $items = [['d' => $jual ? 'Paket laptop bisnis 14 inci' : 'Bahan baku kemasan', 'q' => 2, 'h' => 10000000], ['d' => $jual ? 'Jasa instalasi dan konfigurasi jaringan kantor lengkap dengan pelatihan pengguna' : 'Ongkos kirim', 'q' => 1, 'h' => 7500000], ['d' => $jual ? 'Lisensi perangkat lunak akuntansi' : 'Palet kayu', 'q' => 1.5, 'h' => 4000000]];
    $sub = 0; foreach ($items as $i) $sub += $i['q'] * $i['h'];
    $ppn = r2($sub * 0.11); $total = r2($sub + $ppn); $paid = 10000000;
    $d = [
        'faktur' => ['id' => 'PREVIEW', 'nomor' => ($jual ? 'INV' : 'PO') . '-' . substr($td, 0, 4) . '-0001', 'tipe' => $tipe, 'tanggal' => $td, 'jatuhTempo' => add_days($td, 30), 'kontak' => $jual ? 'PT Contoh Pelanggan' : 'CV Contoh Pemasok', 'subtotal' => $sub, 'persenPPN' => 11, 'ppn' => $ppn, 'total' => $total, 'terbayar' => $paid, 'sisa' => $total - $paid, 'status' => 'Sebagian', 'ket' => 'Ini adalah pratinjau template dengan data contoh. Data asli tidak terpengaruh.'],
        'items' => $items,
        'pembayaran' => [['id' => 'P1', 'tanggal' => add_days($td, -3), 'jumlah' => $paid, 'akunKas' => '1102', 'akunKasNama' => a_akunMap()['1102']['Nama'] ?? 'Bank', 'catatan' => 'Uang muka']],
        'kon' => ['nama' => 'Contoh', 'alamat' => 'Jl. Contoh No. 12, Bandung', 'telepon' => '022-1234567', 'email' => 'kontak@contoh.id', 'npwp' => '01.234.567.8-901.000'],
    ];
    return ['name' => 'Contoh ' . ($jual ? 'Faktur Penjualan' : 'Purchase Order') . '.pdf', 'html' => faktur_html($d, $user)];
}

/** Cadangan: file .sql berisi seluruh data, bisa diimpor ulang lewat phpMyAdmin. */
function a_backup(array $p, array $user): array
{
    $r = db_backup();
    log_act($user, 'Backup', 'Sistem', $r['name']);
    return $r;
}

/* ========================================================================
 *  DATA CONTOH  (opsional, dari menu Pengaturan)
 * ====================================================================== */
function a_seedDemo(array $p, array $user): array
{
    if ((settings_all()['DEMO'] ?? '') === '1') throw new AppErr('Data contoh sudah pernah dimuat.');
    $st = settings_all(); $todayS = today_s(); $seed = 7;
    $rnd = function () use (&$seed) { $seed = ($seed * 16807) % 2147483647; return $seed / 2147483647; };
    $amt = fn($min, $max, $step) => round(($min + $rnd() * ($max - $min)) / $step) * $step;
    $dt = function ($mOff, $day) use ($todayS) {
        $base = strtotime(date('Y-m-01') . ' 12:00:00 ' . ($mOff >= 0 ? '+' : '') . $mOff . ' month');
        $s = date('Y-m-', $base) . str_pad((string)min($day, (int)date('t', $base)), 2, '0', STR_PAD_LEFT);
        return $s > $todayS ? $todayS : $s;
    };
    $no = fn($prefix, $tgl) => a_nextNo($prefix, $tgl);
    $kontak = [];
    foreach ([['Toko Sumber Rejeki', 'Pelanggan'], ['CV Maju Bersama', 'Pelanggan'], ['PT Nusantara Retail', 'Pelanggan'], ['UD Berkah Jaya', 'Pelanggan'], ['PT Sinar Logistik', 'Pemasok'], ['CV Mitra Print', 'Pemasok'], ['PT Cahaya Listrik', 'Pemasok']] as $i => $k)
        $kontak[] = [uid('K', 10), $k[0], $k[1], '0812345' . (1000 + $i * 37), '', 'Jakarta', '', 'Aktif'];
    insert_many('kontak', ['id', 'nama', 'tipe', 'telepon', 'email', 'alamat', 'npwp', 'status'], $kontak);
    $cust = array_values(array_filter($kontak, fn($k) => $k[2] === 'Pelanggan')); $supp = array_values(array_filter($kontak, fn($k) => $k[2] === 'Pemasok'));
    $J = []; $F = []; $PAY = []; $now = now_s();
    $ent = function ($tgl, $jenis, $ket, $kid, $ref, $lines, $prefix) use (&$J, $no, $user, $now) {
        $id = uid('J', 10); $nb = $no($prefix, $tgl);
        foreach ($lines as $l) $J[] = [$id, $tgl, $nb, $jenis, $l[0], $l[1] ?: $ket, $l[2], $l[3], $kid ?: '', $ref ?: '', $user['username'], $now];
        return $nb;
    };
    for ($m = -5; $m <= 0; $m++) {
        if ($m === -5) $ent($dt($m, 1), 'Jurnal Umum', 'Setoran modal awal', '', '', [['1102', '', 300000000, 0], ['1101', '', 20000000, 0], ['3101', '', 0, 320000000]], 'JU');
        for ($s = 0; $s < 3; $s++) {
            $a = $amt(8000000, 30000000, 500000); $akunP = $s === 1 ? '4102' : '4101'; $kasA = $s === 2 ? '1101' : '1102'; $dd = 3 + $s * 9;
            $ent($dt($m, $dd), 'Kas Masuk', ($s === 1 ? 'Pendapatan jasa konsultasi' : 'Penjualan tunai') . ' ' . $dd, $cust[$s % count($cust)][0], '', [[$kasA, '', $a, 0], [$akunP, '', 0, $a]], 'KM');
        }
        foreach ([['6102', 'Sewa kantor', 7500000, 7500000, 5], ['6101', 'Gaji karyawan', 26000000, 30000000, 25], ['6103', 'Listrik, air & internet', 1500000, 2500000, 10], ['6104', 'Iklan & promosi', 1000000, 4500000, 14], ['6106', 'Transportasi & BBM', 500000, 1800000, 17], ['6105', 'Perlengkapan kantor', 300000, 1200000, 20], ['6108', 'Biaya admin bank', 50000, 50000, 28]] as $b) {
            $v = $amt($b[2], $b[3], 50000) ?: $b[2];
            $ent($dt($m, $b[4]), 'Kas Keluar', $b[1], '', '', [[$b[0], '', $v, 0], ['1102', '', 0, $v]], 'KK');
        }
        for ($qq = 0; $qq < 2; $qq++) {
            $tg = $dt($m, 8 + $qq * 12); $sub = $amt(10000000, 40000000, 500000); $ppn = r2($sub * 0.11); $tot = $sub + $ppn; $k = $cust[($m + 5 + $qq) % count($cust)];
            $fid = uid('F', 10); $fno = $no('INV', $tg); $jt = add_days($tg, 30);
            $F[] = [$fid, $fno, 'Penjualan', $tg, $jt, $k[0], 'Proyek / pesanan ' . ($qq + 1), $sub, 11, $ppn, $tot, $qq ? '4102' : '4101', json_encode([['d' => $qq ? 'Jasa implementasi' : 'Penjualan barang', 'q' => 1, 'h' => $sub]]), '', $user['username'], $now];
            $eid = uid('J', 10);
            foreach (a_fakturLines('Penjualan', $qq ? '4102' : '4101', $sub, $ppn, $tot, $st, 'Penjualan ' . $fno) as $l) $J[] = [$eid, $tg, $fno, 'Faktur Penjualan', $l[0], $l[1], $l[2], $l[3], $k[0], 'F:' . $fid, $user['username'], $now];
            $age = diff_days($todayS, $tg); $paid = $age > 45 ? $tot : (($age > 20 && $qq === 0) ? r2($tot / 2) : 0);
            if ($paid > 0) {
                $pd = add_days($tg, 18); if ($pd > $todayS) $pd = $todayS;
                $pid = uid('P', 10); $pno = $no('KM', $pd); $pe = uid('J', 10);
                $PAY[] = [$pid, $fid, $pd, $paid, '1102', 'Transfer pelanggan', $user['username']];
                foreach (a_payLines('Penjualan', '1102', $paid, $st, 'Pembayaran ' . $fno) as $l) $J[] = [$pe, $pd, $pno, 'Pembayaran Piutang', $l[0], $l[1], $l[2], $l[3], $k[0], 'P:' . $pid, $user['username'], $now];
            }
        }
        $tg2 = $dt($m, 12); $sub2 = $amt(5000000, 20000000, 500000); $ppn2 = r2($sub2 * 0.11); $tot2 = $sub2 + $ppn2; $sp = $supp[($m + 5) % count($supp)];
        $fid2 = uid('F', 10); $fno2 = $no('PO', $tg2);
        $F[] = [$fid2, $fno2, 'Pembelian', $tg2, add_days($tg2, 30), $sp[0], 'Pembelian persediaan', $sub2, 11, $ppn2, $tot2, '5102', json_encode([['d' => 'Pembelian barang dagang', 'q' => 1, 'h' => $sub2]]), '', $user['username'], $now];
        $eid2 = uid('J', 10);
        foreach (a_fakturLines('Pembelian', '5102', $sub2, $ppn2, $tot2, $st, 'Pembelian ' . $fno2) as $l) $J[] = [$eid2, $tg2, $fno2, 'Faktur Pembelian', $l[0], $l[1], $l[2], $l[3], $sp[0], 'F:' . $fid2, $user['username'], $now];
        if (diff_days($todayS, $tg2) > 35) {
            $pd2 = add_days($tg2, 25); if ($pd2 > $todayS) $pd2 = $todayS;
            $pid2 = uid('P', 10); $pno2 = $no('KK', $pd2); $pe2 = uid('J', 10);
            $PAY[] = [$pid2, $fid2, $pd2, $tot2, '1102', 'Transfer ke pemasok', $user['username']];
            foreach (a_payLines('Pembelian', '1102', $tot2, $st, 'Pembayaran ' . $fno2) as $l) $J[] = [$pe2, $pd2, $pno2, 'Pembayaran Hutang', $l[0], $l[1], $l[2], $l[3], $sp[0], 'P:' . $pid2, $user['username'], $now];
        }
    }
    insert_many('faktur', ['id', 'nomor', 'tipe', 'tanggal', 'jatuh_tempo', 'kontak_id', 'keterangan', 'subtotal', 'persen_ppn', 'ppn', 'total', 'akun_lawan', 'item', 'sumber', 'dibuat_oleh', 'dibuat_pada'], $F);
    insert_many('pembayaran', ['id', 'faktur_id', 'tanggal', 'jumlah', 'akun_kas', 'catatan', 'dibuat_oleh'], $PAY);
    insert_many('jurnal', ['entri_id', 'tanggal', 'no_bukti', 'jenis', 'kode_akun', 'keterangan', 'debit', 'kredit', 'kontak_id', 'ref', 'dibuat_oleh', 'dibuat_pada'], $J);
    setting_put(['DEMO' => '1']);
    log_act($user, 'Data contoh', 'Sistem', count($J) . ' baris jurnal, ' . count($F) . ' faktur');
    return ['ok' => true, 'jurnal' => count($J), 'faktur' => count($F)];
}
