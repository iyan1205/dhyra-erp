<?php
/**
 * ============================================================
 *  MODUL SISTEM — login tunggal, Beranda, Pengguna & Hak Akses, Log
 *  Dipanggil dari beranda.html & pengguna.html lewat api.php { m:'sistem', a, t, d }
 * ============================================================
 */
declare(strict_types=1);

const SISTEM_ACTIONS = [
    'me' => ['f' => 's_me'],
    'logout' => ['f' => 's_logout'],
    'changePw' => ['f' => 's_changePw', 'w' => 1],
    'users' => ['f' => 's_users', 'admin' => 1],
    'userSave' => ['f' => 's_userSave', 'w' => 1, 'admin' => 1],
    'userDel' => ['f' => 's_userDel', 'w' => 1, 'admin' => 1],
    'userReset' => ['f' => 's_userReset', 'w' => 1, 'admin' => 1],
    'jabatanSave' => ['f' => 's_jabatanSave', 'w' => 1, 'admin' => 1],
    'jabatanDel' => ['f' => 's_jabatanDel', 'w' => 1, 'admin' => 1],
    'log' => ['f' => 's_log', 'admin' => 1],
    'setGet' => ['f' => 's_setGet', 'admin' => 1],
    'setSave' => ['f' => 's_setSave', 'w' => 1, 'admin' => 1],
    'skuIsi' => ['f' => 's_skuIsi', 'w' => 1, 'admin' => 1],
    'backup' => ['f' => 's_backup', 'admin' => 1],
    'userImport' => ['f' => 's_userImport', 'w' => 1, 'admin' => 1],
    'akunKaryawan' => ['f' => 's_akunKaryawan', 'w' => 1, 'admin' => 1],
    'waGet' => ['f' => 's_waGet', 'admin' => 1, 'notif' => 1],
    'waSave' => ['f' => 's_waSave', 'w' => 1, 'admin' => 1, 'notif' => 1],
    'waTest' => ['f' => 's_waTest', 'admin' => 1, 'notif' => 1],
    'resetInfo' => ['f' => 's_resetInfo', 'admin' => 1],
    'resetRun' => ['f' => 's_resetRun', 'w' => 1, 'admin' => 1],
];
function s_resetInfo(): array { require_once __DIR__ . '/reset.php'; return rs_info(); }
function s_resetRun(array $d, array $u): array { require_once __DIR__ . '/reset.php'; return rs_run($d, $u); }

function sistem_dispatch(string $a, array $d, ?string $token): array
{
    if ($a === 'login') {
        $r = do_login($d);
        log_act($r['user'], 'Masuk', 'Sistem', 'Login berhasil');
        $me = s_me(['_pw' => $r['defaultPassword']], $r['user']);
        $me['token'] = $r['token'];
        return $me;
    }
    $cfg = SISTEM_ACTIONS[$a] ?? null;
    if (!$cfg) throw new AppErr('Aksi tidak dikenal');
    $u = auth_token($token);
    $u['_token'] = $token;
    if (!empty($cfg['admin']) && !user_akses($u)['admin']) throw new AppErr('Menu ini hanya untuk Administrator.', 'MODUL');
    if (!empty($cfg['notif'])) require_once __DIR__ . '/notif.php';
    return !empty($cfg['w']) ? with_write(function () use ($cfg, $d, $u) { $r = $cfg['f']($d, $u); bump_rev(); return $r; }) : $cfg['f']($d, $u);
}

function s_me(array $d, array $u): array
{
    $ak = user_akses($u); $s = settings_all();
    return [
        'user' => ['id' => $u['id'], 'username' => $u['username'], 'nama' => $u['nama'], 'jabatan' => $ak['jabatan'], 'admin' => $ak['admin']],
        'akses' => array_intersect_key($ak, MODUL), 'toko' => $s['namaToko'] ?: ($s['NamaPerusahaan'] ?: APP_NAME), 'versi' => APP_VERSION,
        'hpTerbatas' => hp_terbatas($u),
        'defaultPassword' => !empty($d['_pw']) || (int)val('SELECT ganti_pw FROM users WHERE id = ?', [$u['id']]) === 1,
        'saas' => saas_ctx() ? saas_me($u) : null,
    ];
}
function s_logout(array $d, array $u): array { q('DELETE FROM sesi WHERE token = ?', [$u['_token']]); log_act($u, 'Keluar', 'Sistem', ''); return ['ok' => true]; }
function s_changePw(array $d, array $u): array
{
    if (!password_verify((string)($d['lama'] ?? ''), $u['pass_hash'])) throw new AppErr('Password lama salah.');
    if (mb_strlen((string)($d['baru'] ?? '')) < 6) throw new AppErr('Password baru minimal 6 karakter.');
    if (hash_equals((string)$d['lama'], (string)$d['baru'])) throw new AppErr('Password baru harus berbeda dari password lama.');
    q('UPDATE users SET pass_hash = ?, ganti_pw = 0 WHERE id = ?', [make_pw((string)$d['baru']), $u['id']]);
    kill_sessions($u['id'], $u['_token']);
    log_act($u, 'Ubah password', 'Pengguna', $u['username']);
    return ['ok' => true];
}

/* ---------------------------- Pengguna & jabatan ---------------------------- */
function s_jabatanOut(): array
{
    $cnt = [];
    foreach (rows('SELECT jabatan_id, COUNT(*) n FROM users GROUP BY jabatan_id') as $r) $cnt[$r['jabatan_id']] = (int)$r['n'];
    return array_map(function ($j) use ($cnt) {
        $a = json_decode((string)$j['akses'], true) ?: [];
        $ak = [];
        foreach (MODUL as $m => $_) $ak[$m] = $j['is_admin'] ? 3 : (int)($a[$m] ?? 0);
        return ['id' => $j['id'], 'nama' => $j['nama'], 'admin' => (bool)$j['is_admin'], 'sistem' => (bool)$j['sistem'], 'akses' => $ak, 'pengguna' => $cnt[$j['id']] ?? 0];
    }, rows('SELECT * FROM jabatan ORDER BY is_admin DESC, nama'));
}
function s_users(): array
{
    return [
        'users' => array_map(fn($x) => ['id' => $x['id'], 'username' => $x['username'], 'nama' => $x['nama'], 'email' => $x['email'], 'jabatanId' => $x['jabatan_id'], 'status' => $x['status'],
            'login' => $x['login_terakhir'] ? substr($x['login_terakhir'], 0, 16) : '', 'dibuat' => substr($x['dibuat'], 0, 10), 'gantiPw' => (int)($x['ganti_pw'] ?? 0) === 1], rows('SELECT * FROM users ORDER BY nama')),
        'jabatan' => s_jabatanOut(), 'modul' => MODUL, 'level' => LEVEL_NAMA,
    ];
}
function s_adminCount(?string $exceptId = null): int
{
    return (int)val("SELECT COUNT(*) FROM users u JOIN jabatan j ON j.id = u.jabatan_id WHERE j.is_admin = 1 AND u.status = 'aktif'" . ($exceptId ? ' AND u.id <> ?' : ''), $exceptId ? [$exceptId] : []);
}
function s_userSave(array $d, array $me): array
{
    $un = strtolower(trim((string)($d['username'] ?? '')));
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $un)) throw new AppErr('Username 3–30 karakter: huruf kecil, angka, titik, strip.');
    $nama = str($d['nama'] ?? '', 100);
    if ($nama === '') throw new AppErr('Nama lengkap wajib diisi.');
    $jab = row('SELECT * FROM jabatan WHERE id = ?', [(string)($d['jabatanId'] ?? '')]);
    if (!$jab) throw new AppErr('Pilih jabatan.');
    $status = ($d['status'] ?? '') === 'nonaktif' ? 'nonaktif' : 'aktif';
    $email = str($d['email'] ?? '', 120); $pw = (string)($d['password'] ?? '');
    $id = (string)($d['id'] ?? '');
    if (val('SELECT id FROM users WHERE username = ? AND id <> ?', [$un, $id])) throw new AppErr('Username sudah dipakai.');
    $legacy = $jab['is_admin'] ? 'admin' : 'kasir';
    if ($id) {
        $cur = row('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$cur) throw new AppErr('Pengguna tidak ditemukan.');
        if ($cur['id'] === $me['id'] && (!$jab['is_admin'] || $status !== 'aktif')) throw new AppErr('Anda tidak dapat mencabut akses Administrator atau menonaktifkan akun sendiri.');
        if ((!$jab['is_admin'] || $status !== 'aktif') && s_adminCount($id) < 1) throw new AppErr('Minimal harus ada 1 Administrator aktif.');
        $hash = $cur['pass_hash']; $kill = $cur['jabatan_id'] !== $jab['id'] || $cur['status'] !== $status || $cur['username'] !== $un;
        $gp = (int)($cur['ganti_pw'] ?? 0);
        if ($pw !== '') { if (mb_strlen($pw) < 6) throw new AppErr('Password minimal 6 karakter.'); $hash = make_pw($pw); $kill = true; $gp = $cur['id'] === $me['id'] ? 0 : 1; }
        q('UPDATE users SET username=?, nama=?, email=?, jabatan_id=?, role=?, status=?, pass_hash=?, ganti_pw=? WHERE id=?', [$un, $nama, $email, $jab['id'], $legacy, $status, $hash, $gp, $id]);
        if ($kill) kill_sessions($id, $id === $me['id'] ? $me['_token'] : null);
        log_act($me, 'Ubah', 'Pengguna', $un . ' (' . $jab['nama'] . ')');
    } else {
        if (mb_strlen($pw) < 6) throw new AppErr('Password minimal 6 karakter.');
        q('INSERT INTO users (id, username, nama, email, role, jabatan_id, pass_hash, status, dibuat, ganti_pw) VALUES (?,?,?,?,?,?,?,?,?,1)', [uid('U'), $un, $nama, $email, $legacy, $jab['id'], make_pw($pw), $status, now_s()]);
        log_act($me, 'Tambah', 'Pengguna', $un . ' (' . $jab['nama'] . ')');
    }
    return s_users();
}
function s_userDel(array $d, array $me): array
{
    $id = (string)($d['id'] ?? '');
    if ($id === $me['id']) throw new AppErr('Anda tidak dapat menghapus akun sendiri.');
    $x = row('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$x) throw new AppErr('Pengguna tidak ditemukan.');
    if (s_adminCount($id) < 1) throw new AppErr('Minimal harus ada 1 Administrator aktif.');
    q('DELETE FROM users WHERE id = ?', [$id]);
    kill_sessions($id);
    log_act($me, 'Hapus', 'Pengguna', $x['username']);
    return s_users();
}
function s_jabatanSave(array $d, array $me): array
{
    $nama = str($d['nama'] ?? '', 60);
    if ($nama === '') throw new AppErr('Nama jabatan wajib diisi.');
    $id = (string)($d['id'] ?? '');
    if (val('SELECT id FROM jabatan WHERE LOWER(nama) = LOWER(?) AND id <> ?', [$nama, $id])) throw new AppErr('Nama jabatan sudah ada.');
    $ak = [];
    foreach (MODUL as $m => $_) $ak[$m] = max(0, min(3, (int)($d['akses'][$m] ?? 0)));
    if ($id) {
        $j = row('SELECT * FROM jabatan WHERE id = ?', [$id]);
        if (!$j) throw new AppErr('Jabatan tidak ditemukan.');
        if ($j['is_admin']) { q('UPDATE jabatan SET nama = ? WHERE id = ?', [$nama, $id]); }
        else {
            q('UPDATE jabatan SET nama = ?, akses = ? WHERE id = ?', [$nama, json_encode($ak), $id]);
            if ($j['akses'] !== json_encode($ak)) foreach (rows('SELECT id FROM users WHERE jabatan_id = ?', [$id]) as $u) kill_sessions($u['id'], $u['id'] === $me['id'] ? $me['_token'] : null);
        }
        log_act($me, 'Ubah', 'Jabatan', $nama);
    } else {
        q('INSERT INTO jabatan (id, nama, is_admin, akses, sistem, dibuat) VALUES (?,?,0,?,0,?)', [uid('J'), $nama, json_encode($ak), now_s()]);
        log_act($me, 'Tambah', 'Jabatan', $nama);
    }
    return s_users();
}
function s_jabatanDel(array $d, array $me): array
{
    $j = row('SELECT * FROM jabatan WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$j) throw new AppErr('Jabatan tidak ditemukan.');
    if ($j['is_admin']) throw new AppErr('Jabatan Administrator tidak dapat dihapus.');
    $n = (int)val('SELECT COUNT(*) FROM users WHERE jabatan_id = ?', [$j['id']]);
    if ($n) throw new AppErr('Jabatan masih dipakai ' . $n . ' pengguna. Pindahkan dulu penggunanya.');
    q('DELETE FROM jabatan WHERE id = ?', [$j['id']]);
    log_act($me, 'Hapus', 'Jabatan', $j['nama']);
    return s_users();
}
function s_log(array $d): array
{
    $w = []; $a = [];
    if (is_date($d['from'] ?? null)) { $w[] = 'waktu >= ?'; $a[] = $d['from'] . ' 00:00:00'; }
    if (is_date($d['to'] ?? null)) { $w[] = 'waktu <= ?'; $a[] = $d['to'] . ' 23:59:59'; }
    $qs = trim((string)($d['q'] ?? ''));
    if ($qs !== '') { $w[] = "CONCAT_WS(' ', user, aksi, modul, detail) LIKE ?"; $a[] = '%' . addcslashes($qs, '%_\\') . '%'; }
    $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
    $total = (int)val('SELECT COUNT(*) FROM log_aktivitas' . $where, $a);
    $size = 50; $page = max(1, (int)($d['page'] ?? 1));
    $r = rows('SELECT * FROM log_aktivitas' . $where . ' ORDER BY id DESC LIMIT ' . $size . ' OFFSET ' . (($page - 1) * $size), $a);
    return ['rows' => array_map(fn($x) => ['waktu' => substr($x['waktu'], 0, 16), 'user' => $x['user'], 'aksi' => $x['aksi'], 'modul' => $x['modul'], 'detail' => $x['detail']], $r), 'total' => $total, 'page' => $page, 'pages' => max(1, (int)ceil($total / $size))];
}

/* ---------------------------- Pengaturan umum (admin) ---------------------------- */
const S_SET_KEYS = ['namaToko', 'alamat', 'telepon', 'Email', 'NPWP', 'footer', 'pajak', 'pajakNama', 'kertas', 'izinMinus', 'autoCetak',
    'MemberAktif', 'PoinPer', 'PoinNilai', 'PoinMin', 'SkuMode', 'SkuPrefix'];
function s_setGet(): array
{
    $s = settings_all(); $o = [];
    foreach (S_SET_KEYS as $k) $o[$k] = (string)($s[$k] ?? '');
    $mysql = (string)val('SELECT VERSION()');
    return ['set' => $o, 'info' => [
        'versi' => APP_VERSION, 'db' => (int)($s['DB_V'] ?? 0), 'php' => PHP_VERSION, 'mysql' => $mysql,
        'produk' => (int)val('SELECT COUNT(*) FROM produk'), 'tanpaSku' => (int)val("SELECT COUNT(*) FROM produk WHERE sku IS NULL OR sku = ''"),
        'transaksi' => (int)val('SELECT COUNT(*) FROM transaksi'), 'member' => (int)val('SELECT COUNT(*) FROM member'), 'pengguna' => (int)val('SELECT COUNT(*) FROM users'),
        'lokasi' => count(lokasi_all()), 'contohSku' => sku_prefix('Minuman') . '-0001',
    ]];
}
function s_setSave(array $d, array $u): array
{
    $put = [];
    foreach (S_SET_KEYS as $k) {
        if (!array_key_exists($k, $d)) continue;
        $v = $d[$k]; if (is_bool($v)) $v = $v ? '1' : '0';
        $v = trim(mb_substr((string)$v, 0, 200));
        switch ($k) {
            case 'namaToko': if ($v === '') throw new AppErr('Nama usaha wajib diisi'); break;
            case 'pajak': $v = (string)min(100, max(0, num($v))); break;
            case 'kertas': $v = $v === '80' ? '80' : '58'; break;
            case 'izinMinus': case 'autoCetak': case 'MemberAktif': $v = $v === '1' ? '1' : '0'; break;
            case 'PoinPer': $v = (string)max(100, (int)num($v)); break;
            case 'PoinNilai': $v = (string)max(0, (int)num($v)); break;
            case 'PoinMin': $v = (string)max(1, (int)num($v)); break;
            case 'SkuMode': $v = $v === 'prefix' ? 'prefix' : 'kategori'; break;
            case 'SkuPrefix': $v = substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v)), 0, 8) ?: 'DHY'; break;
        }
        $put[$k] = $v;
    }
    // profil usaha dipakai juga oleh akuntansi (kop faktur & laporan)
    if (isset($put['namaToko'])) $put['NamaPerusahaan'] = $put['namaToko'];
    if (isset($put['alamat'])) $put['Alamat'] = $put['alamat'];
    if (isset($put['telepon'])) $put['Telepon'] = $put['telepon'];
    setting_put($put);
    log_act($u, 'Ubah', 'Pengaturan', implode(', ', array_keys($put)));
    return s_setGet();
}
function s_skuIsi(array $d, array $u): array
{
    $n = 0;
    foreach (rows("SELECT id, kategori FROM produk WHERE sku IS NULL OR sku = '' ORDER BY dibuat, nama") as $p) { q('UPDATE produk SET sku = ? WHERE id = ?', [sku_gen((string)$p['kategori']), $p['id']]); $n++; }
    log_act($u, 'SKU otomatis', 'Produk', $n . ' produk');
    return ['n' => $n] + s_setGet();
}
function s_backup(array $d, array $u): array
{
    $r = db_backup();
    log_act($u, 'Backup', 'Sistem', $r['name']);
    return $r;
}

/* ======================================================================
 *  AKUN LOGIN MASSAL — import Excel & buat otomatis untuk karyawan
 * ==================================================================== */
/** Password sementara yang mudah diketik: dua suku kata + 4 angka, mis. "bali4821". */
function s_pwGen(): string
{
    $k = 'bcdfghjklmnprstw'; $v = 'aiueo'; $o = '';
    for ($i = 0; $i < 2; $i++) $o .= $k[random_int(0, strlen($k) - 1)] . $v[random_int(0, 4)];
    return $o . str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
}
/** Username dari nama: kata pertama huruf kecil, ditambah angka bila sudah dipakai. */
function s_unGen(string $nama, array &$pakai): string
{
    $w = preg_split('/\s+/', trim(mb_strtolower(@iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nama) ?: $nama)));
    $b = preg_replace('/[^a-z0-9]/', '', $w[0] ?? '');
    if (strlen($b) < 3) $b = preg_replace('/[^a-z0-9]/', '', implode('', $w));
    if (strlen($b) < 3) $b = 'staf' . $b;
    $b = substr($b, 0, 20); $u = $b; $n = 1;
    while (isset($pakai[$u]) || val('SELECT id FROM users WHERE username = ?', [$u])) $u = $b . (++$n);
    $pakai[$u] = 1;
    return $u;
}
function s_jabMap(): array { $m = []; foreach (rows('SELECT id, nama FROM jabatan') as $j) { $m[mb_strtolower($j['nama'])] = $j['id']; $m[mb_strtolower($j['id'])] = $j['id']; } return $m; }
/** Jabatan bawaan berdasarkan posisi karyawan. */
function s_jabPosisi(string $pos): string
{
    $p = mb_strtolower($pos); $ada = fn($id) => (bool)val('SELECT id FROM jabatan WHERE id = ?', [$id]);
    foreach (['kasir' => 'JKASIR', 'gudang' => 'JGUDANG', 'kepala' => 'JKEPALA', 'manajer' => 'JKEPALA', 'supervisor' => 'JKEPALA', 'spv' => 'JKEPALA', 'akunt' => 'JAKUNTAN', 'keuangan' => 'JAKUNTAN', 'finance' => 'JAKUNTAN'] as $k => $j)
        if (strpos($p, $k) !== false && $ada($j)) return $j;
    return $ada('JSTAF') ? 'JSTAF' : 'JKASIR';
}
/** Buat satu akun; mengembalikan [id, password]. */
function s_akunBaru(string $un, string $nama, string $jab, string $pw, string $email, array $me): array
{
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $un)) throw new AppErr('Username "' . $un . '" tidak valid (3–30 huruf kecil/angka/titik/strip)');
    if (val('SELECT id FROM users WHERE username = ?', [$un])) throw new AppErr('Username "' . $un . '" sudah dipakai');
    $j = row('SELECT * FROM jabatan WHERE id = ?', [$jab]); if (!$j) throw new AppErr('Jabatan tidak ditemukan');
    if ($pw === '') $pw = s_pwGen(); elseif (mb_strlen($pw) < 6) throw new AppErr('Password "' . $un . '" minimal 6 karakter');
    $id = uid('U');
    q('INSERT INTO users (id, username, nama, email, role, jabatan_id, pass_hash, status, dibuat, ganti_pw) VALUES (?,?,?,?,?,?,?,?,?,1)', [$id, $un, $nama, $email, $j['is_admin'] ? 'admin' : 'kasir', $j['id'], make_pw($pw), 'aktif', now_s()]);
    log_act($me, 'Tambah', 'Pengguna', $un . ' (' . $j['nama'] . ')');
    return [$id, $pw, $j['nama']];
}
function s_userImport(array $d, array $me): array
{
    $items = (array)($d['items'] ?? []); if (!$items) throw new AppErr('Tidak ada baris untuk diimpor'); if (count($items) > 1000) throw new AppErr('Maksimal 1.000 baris');
    $JM = s_jabMap(); $err = []; $akun = []; $n = ['baru' => 0, 'ubah' => 0];
    foreach ($items as $i => $x) {
        $row = (int)($x['_row'] ?? $i + 2);
        try {
            $un = strtolower(trim((string)($x['username'] ?? '')));
            $nama = str($x['nama'] ?? '', 100); if ($nama === '') throw new AppErr('Nama kosong');
            if ($un === '') { $tmp = []; $un = s_unGen($nama, $tmp); }
            $jn = mb_strtolower(trim((string)($x['jabatan'] ?? '')));
            $jab = $jn === '' ? 'JSTAF' : ($JM[$jn] ?? null); if ($jab === null) throw new AppErr('Jabatan "' . $x['jabatan'] . '" tidak ada');
            $pw = trim((string)($x['password'] ?? '')); $email = str($x['email'] ?? '', 120);
            $st = mb_strtolower(trim((string)($x['status'] ?? ''))) === 'nonaktif' ? 'nonaktif' : 'aktif';
            $cur = row('SELECT * FROM users WHERE username = ?', [$un]);
            if ($cur) {
                s_userSave(['id' => $cur['id'], 'username' => $un, 'nama' => $nama, 'email' => $email ?: $cur['email'], 'jabatanId' => $jab, 'status' => $st, 'password' => $pw], $me);
                if ($pw !== '') $akun[] = ['username' => $un, 'nama' => $nama, 'jabatan' => (string)val('SELECT nama FROM jabatan WHERE id = ?', [$jab]), 'password' => $pw, 'status' => 'Diperbarui'];
                $n['ubah']++;
            } else {
                [$id, $p, $jnm] = s_akunBaru($un, $nama, $jab, $pw, $email, $me);
                if ($st === 'nonaktif') q("UPDATE users SET status = 'nonaktif' WHERE id = ?", [$id]);
                // hubungkan otomatis ke karyawan bernama sama yang belum punya akun
                $k = rows("SELECT id FROM karyawan WHERE user_id = '' AND LOWER(nama) = LOWER(?)", [$nama]);
                if (count($k) === 1) q('UPDATE karyawan SET user_id = ? WHERE id = ?', [$id, $k[0]['id']]);
                $akun[] = ['username' => $un, 'nama' => $nama, 'jabatan' => $jnm, 'password' => $p, 'status' => 'Baru' . (count($k) === 1 ? ' · terhubung ke data karyawan' : '')];
                $n['baru']++;
            }
        } catch (AppErr $e) { $err[] = 'baris ' . $row . ': ' . $e->getMessage(); }
    }
    if ($err) throw new AppErr(count($err) . ' baris bermasalah, belum ada yang disimpan — ' . implode(' · ', array_slice($err, 0, 4)) . (count($err) > 4 ? ' · …' : ''));
    log_act($me, 'Import Excel', 'Pengguna', $n['baru'] . ' baru, ' . $n['ubah'] . ' diperbarui');
    return $n + ['akun' => $akun] + s_users();
}
/** Reset password: password sementara baru dibuat otomatis, wajib diganti saat login berikutnya. */
function s_userReset(array $d, array $me): array
{
    $ids = array_values(array_unique(array_filter(array_map('strval', (array)($d['ids'] ?? [])))));
    if (!$ids) throw new AppErr('Pilih pengguna yang akan direset passwordnya');
    if (in_array($me['id'], $ids, true)) throw new AppErr('Password akun Anda sendiri diganti lewat menu Ganti password, bukan reset.');
    $akun = [];
    foreach ($ids as $id) {
        $x = row('SELECT u.*, j.nama jnama FROM users u LEFT JOIN jabatan j ON j.id = u.jabatan_id WHERE u.id = ?', [$id]);
        if (!$x) throw new AppErr('Pengguna tidak ditemukan');
        $pw = s_pwGen();
        q('UPDATE users SET pass_hash = ?, ganti_pw = 1 WHERE id = ?', [make_pw($pw), $id]);
        q('DELETE FROM login_gagal WHERE username = ?', [$x['username']]);
        kill_sessions($id, null);
        $akun[] = ['username' => $x['username'], 'nama' => $x['nama'], 'jabatan' => (string)($x['jnama'] ?? ''), 'password' => $pw, 'status' => 'Password direset'];
    }
    log_act($me, 'Reset password', 'Pengguna', implode(', ', array_column($akun, 'username')));
    return ['akun' => $akun] + s_users();
}
/** Buatkan akun login untuk karyawan terpilih yang belum punya akun. */
function s_akunKaryawan(array $d, array $me): array
{
    $items = (array)($d['items'] ?? []); if (!$items) throw new AppErr('Pilih karyawan');
    $akun = []; $pakai = [];
    foreach ($items as $x) {
        $k = row('SELECT * FROM karyawan WHERE id = ? FOR UPDATE', [(string)($x['karyawan'] ?? '')]);
        if (!$k) throw new AppErr('Karyawan tidak ditemukan');
        if ($k['user_id'] !== '' && val('SELECT id FROM users WHERE id = ?', [$k['user_id']])) continue;
        $un = strtolower(trim((string)($x['username'] ?? ''))); if ($un === '') $un = s_unGen($k['nama'], $pakai); else $pakai[$un] = 1;
        $jab = (string)($x['jabatan'] ?? '') ?: s_jabPosisi((string)$k['posisi']);
        try { [$id, $pw, $jn] = s_akunBaru($un, $k['nama'], $jab, trim((string)($x['password'] ?? '')), (string)$k['email'], $me); }
        catch (AppErr $e) { throw new AppErr($k['nama'] . ': ' . $e->getMessage()); }
        q('UPDATE karyawan SET user_id = ? WHERE id = ?', [$id, $k['id']]);
        $akun[] = ['username' => $un, 'nama' => $k['nama'], 'jabatan' => $jn, 'password' => $pw, 'status' => 'Baru · ' . $k['kode']];
    }
    return ['akun' => $akun, 'baru' => count($akun)];
}
