<?php
/**
 * ============================================================
 *  MODUL KASIR (POS) — port dari KasirPro Code.gs ke PHP + MySQL
 *  Aksi dipanggil dari kasir.html lewat api.php  { m:'kasir', a, t, d }
 * ============================================================
 */
declare(strict_types=1);
require_once __DIR__ . '/posting.php';
require_once __DIR__ . '/promo.php';
require_once __DIR__ . '/tahap2.php';
require_once __DIR__ . '/tahap3.php';

// perm: daftar [modul, level minimal]; salah satu terpenuhi = boleh
const KASIR_ACTIONS = [
    'boot' => ['f' => 'k_boot'],
    'data' => ['f' => 'k_data'],
    'prodSave' => ['f' => 'k_prodSave', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'prodDel' => ['f' => 'k_prodDel', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'catSave' => ['f' => 'k_catSave', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'catDel' => ['f' => 'k_catDel', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'catUrut' => ['f' => 'k_catUrut', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'catPreset' => ['f' => 'k_catPreset', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'barcodeIsi' => ['f' => 'k_barcodeIsi', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'catBersih' => ['f' => 'k_catBersih', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'checkout' => ['f' => 'k_checkout', 'write' => 1, 'perm' => [['kasir', 2]]],
    'trxList' => ['f' => 'k_trxList', 'perm' => [['kasir', 1]]],
    'trxVoid' => ['f' => 'k_trxVoid', 'write' => 1, 'perm' => [['kasir', 3]]],
    'opnameSave' => ['f' => 'k_opnameSave', 'write' => 1, 'perm' => [['inventaris', 2]]],
    'opnameList' => ['f' => 'k_opnameList', 'perm' => [['inventaris', 1]]],
    'smSave' => ['f' => 'k_smSave', 'write' => 1, 'perm' => [['inventaris', 2]]],
    'smList' => ['f' => 'k_smList', 'perm' => [['inventaris', 1], ['kasir', 3]]],
    'smMeta' => ['f' => 'k_smMeta', 'perm' => [['inventaris', 1]]],
    'stokRekap' => ['f' => 'k_stokRekap', 'perm' => [['inventaris', 1], ['kasir', 3]]],
    'setSave' => ['f' => 'k_setSave', 'write' => 1, 'perm' => [['kasir', 3]]],
    'changePw' => ['f' => 'k_changePw', 'write' => 1],
    'lokasiSave' => ['f' => 'k_lokasiSave', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'lokasiDefault' => ['f' => 'k_lokasiDefault', 'write' => 1, 'perm' => [['inventaris', 3]]],
    'mutasiList' => ['f' => 'k_mutasiList', 'perm' => [['inventaris', 1]]],
    'mutasiGet' => ['f' => 'k_mutasiGet', 'perm' => [['inventaris', 1]]],
    'mutasiSave' => ['f' => 'k_mutasiSave', 'write' => 1, 'perm' => [['inventaris', 2]]],
    'mutasiKirim' => ['f' => 'k_mutasiKirim', 'write' => 1, 'perm' => [['inventaris', 2]]],
    'mutasiTerima' => ['f' => 'k_mutasiTerima', 'write' => 1, 'perm' => [['inventaris', 2]]],
    'mutasiBatal' => ['f' => 'k_mutasiBatal', 'write' => 1, 'perm' => [['inventaris', 2]]],
    'mutasiLangsung' => ['f' => 'k_mutasiLangsung', 'write' => 1, 'perm' => [['inventaris', 2]]],
    'isiUlang' => ['f' => 'k_isiUlang', 'perm' => [['inventaris', 1]]],
    // v4 — kasir: promo & member
    'promoHitung' => ['f' => 'k_promoHitung', 'perm' => [['kasir', 2]]],
    'memberCari' => ['f' => 'k_memberCari', 'perm' => [['kasir', 2], ['member', 1]]],
    'memberDaftar' => ['f' => 'k_memberDaftar', 'write' => 1, 'perm' => [['kasir', 2], ['member', 2]]],
];

function kasir_dispatch(string $a, array $d, ?string $token, string $mod = 'kasir'): array
{
    if ($a === 'logout') { if ($token) q('DELETE FROM sesi WHERE token = ?', [$token]); return ['ok' => true, 'data' => 1]; }
    try { $raw = auth_token($token); } catch (AppErr $e) { throw new AppErr('SESSION'); }
    paket_cek_modul($mod === 'saya' ? 'sdm' : $mod);
    $ak = user_akses($raw);
    if ($mod === 'saya') { $ak['saya'] = 1; $raw['_saya'] = 1; }   // layanan mandiri karyawan: cukup login
    if (($ak[$mod] ?? 0) < 1) throw new AppErr('Akun Anda tidak memiliki akses ke modul ' . MODUL[$mod] . '.', 'MODUL');
    $fn = KASIR_ACTIONS[$a] ?? T2_ACTIONS[$a] ?? T3_ACTIONS[$a] ?? null;
    if ($mod === 'saya' && empty($fn['saya'])) throw new AppErr('Aksi tidak tersedia');
    if (!$fn) throw new AppErr('Aksi tidak dikenal');
    if (!empty($fn['perm'])) {
        $ok = false;
        foreach ($fn['perm'] as [$m, $l]) if (($ak[$m] ?? 0) >= $l) $ok = true;
        if (!$ok) throw new AppErr('Akses ditolak: hak akses Anda belum mencukupi untuk tindakan ini.');
    }
    if ($mod !== 'saya' && hp_terbatas($raw)) throw new AppErr(HP_PESAN, 'HP');
    if ($mod !== 'saya' && $a !== 'checkoutOffline' && ($raw['_cekJam'] ?? true)) akses_cek($raw, MODUL[$mod] ?? $mod);
    $u = k_user($raw, $mod);
    $u['_token'] = $token;
    if ($a === 'checkoutOffline') {
        $res = with_write(function () use ($fn, $d, $u) { $r = $fn['f']($d, $u); bump_rev(); return $r; });
        try { akses_cekOffline($raw, (string)($d['waktu'] ?? ''), (string)($res['no'] ?? '')); } catch (Throwable $e) {}
        return ['ok' => true, 'data' => $res];
    }
    $res = !empty($fn['write']) ? with_write(function () use ($fn, $d, $u) { $r = $fn['f']($d, $u); bump_rev(); return $r; }) : $fn['f']($d, $u);
    return ['ok' => true, 'data' => $res];
}

/** Pengguna untuk tampilan modul: role 'admin' = Manajer di modul ini. */
function k_user(array $r, string $mod = 'kasir'): array
{
    $ak = user_akses($r);
    if ($mod === 'saya') $ak['saya'] = 1;
    return ['id' => $r['id'], 'username' => $r['username'], 'nama' => $r['nama'], 'role' => $ak[$mod] >= 3 ? 'admin' : 'kasir', 'lvl' => $ak[$mod], 'mod' => $mod,
        'jabatan' => $ak['jabatan'], 'admin' => $ak['admin'], 'akses' => array_intersect_key($ak, MODUL), 'pass_hash' => $r['pass_hash'] ?? ''];
}
function k_seeCost(array $u): bool { return ($u['akses']['inventaris'] ?? 0) >= 2 || ($u['akses']['kasir'] ?? 0) >= 3; }

/* ---------------------------- Pengaturan kasir ---------------------------- */
const K_SET_KEYS = ['namaToko', 'alamat', 'telepon', 'footer', 'pajak', 'pajakNama', 'kertas', 'izinMinus', 'autoCetak'];
function k_set(): array
{
    $o = settings_all();
    return [
        'namaToko' => $o['namaToko'], 'alamat' => $o['alamat'], 'telepon' => $o['telepon'], 'footer' => $o['footer'],
        'pajak' => jn(num($o['pajak'])), 'pajakNama' => $o['pajakNama'], 'kertas' => $o['kertas'] === '80' ? '80' : '58',
        'izinMinus' => $o['izinMinus'] === '1', 'autoCetak' => $o['autoCetak'] === '1',
    ];
}
function k_setSave(array $d): array
{
    $put = [];
    foreach (K_SET_KEYS as $k) {
        if (!array_key_exists($k, $d)) continue;
        $v = $d[$k];
        if (is_bool($v)) $v = $v ? '1' : '0';
        $v = mb_substr((string)$v, 0, 200);
        if ($k === 'pajak') $v = (string)min(100, max(0, num($v)));
        if ($k === 'kertas') $v = $v === '80' ? '80' : '58';
        $put[$k] = $v;
    }
    setting_put($put);
    return k_set();
}

/* ---------------------------- Data dasar ---------------------------- */
function k_cats(): array { return array_map(fn($r) => ['id' => $r['id'], 'nama' => $r['nama']], rows('SELECT id, nama FROM kategori ORDER BY urut, nama')); }
function k_galeri(?string $g): array { $a = json_decode((string)$g, true); return is_array($a) ? array_values(array_filter($a, 'is_string')) : []; }
const FOTO_MAX = 8;
function k_prodOut(array $o, bool $hide, array $lok = []): array
{
    return [
        'id' => $o['id'], 'sku' => (string)($o['sku'] ?? ''), 'barcode' => (string)($o['barcode'] ?? ''), 'nama' => (string)$o['nama'], 'kategori' => (string)$o['kategori'], 'satuan' => $o['satuan'] ?: 'pcs',
        'hargaBeli' => $hide ? 0 : jn($o['harga_beli']), 'hargaJual' => jn($o['harga_jual']), 'stokTotal' => jn($o['stok']), 'stokMinTotal' => jn($o['stok_min']), 'foto' => (string)$o['foto'],
        'galeri' => k_galeri($o['galeri'] ?? null), 'lok' => (object)$lok,
    ];
}
function k_prods(array $u, ?array $ids = null): array
{
    $hide = !k_seeCost($u); $L = [];
    $w = ''; $a = [];
    if ($ids !== null) { if (!$ids) return []; $w = ' WHERE pid IN (' . implode(',', array_fill(0, count($ids), '?')) . ')'; $a = $ids; }
    foreach (rows('SELECT * FROM stok_lokasi' . $w, $a) as $r) $L[$r['pid']][$r['lokasi_id']] = ['q' => jn($r['qty']), 'min' => jn($r['min']), 'rak' => $r['rak']];
    $sql = 'SELECT * FROM produk' . ($ids !== null ? ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')' : '') . ' ORDER BY nama';
    return array_map(fn($o) => k_prodOut($o, $hide, $L[$o['id']] ?? []), rows($sql, $ids ?? []));
}
function k_lokasiOut(): array
{
    return array_values(array_map(fn($l) => ['id' => $l['id'], 'kode' => $l['kode'], 'nama' => $l['nama'], 'tipe' => $l['tipe'], 'aktif' => (bool)$l['aktif']], lokasi_all()));
}
function k_boot(array $d, array $u): array
{
    $me = $u; unset($me['pass_hash']);
    return ['user' => $me, 'versi' => APP_VERSION] + k_data($d, $u);
}
function k_data(array $d, array $u): array
{
    return ['set' => k_set(), 'cats' => k_cats(), 'prods' => k_prods($u), 'lokasi' => k_lokasiOut(), 'lokJual' => lok_jual(), 'lokMasuk' => lok_masuk(),
        'promo' => (object)promo_labels(), 'poin' => poin_cfg()];
}
/* ---------------------------- Foto produk (disimpan di folder uploads/produk) ---------------------------- */
function k_saveFoto(string $dataUrl, string $id): string
{
    if (!preg_match('#^data:(image/(jpeg|jpg|png|webp|gif));base64,(.+)$#i', $dataUrl, $m)) throw new AppErr('Format foto tidak valid');
    $bin = base64_decode($m[3], true);
    if ($bin === false || strlen($bin) > 4 * 1024 * 1024) throw new AppErr('Foto rusak atau terlalu besar (maks. 4 MB)');
    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) throw new AppErr('File bukan gambar yang valid');
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2]];
    $dir = upload_dir('produk');
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new AppErr('Folder uploads/produk tidak dapat dibuat. Periksa izin folder di hosting.');
    $name = preg_replace('/[^A-Za-z0-9]/', '', $id) . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (@file_put_contents($dir . '/' . $name, $bin) === false) throw new AppErr('Gagal menyimpan foto. Pastikan folder uploads/produk dapat ditulis (izin 755).');
    return upload_url('produk') . '/' . $name;
}
function k_trashFoto(?string $url): void
{
    if (!$url || strpos($url, upload_url('produk') . '/') !== 0) return;
    $f = upload_dir('produk') . '/' . basename($url);
    if (is_file($f)) @unlink($f);
}

/* ---------------------------- Produk ---------------------------- */
function k_prodSave(array $d, array $u): array
{
    $items = $d['items'] ?? [];
    if (!$items) throw new AppErr('Tidak ada data untuk disimpan');
    $byId = []; $bc = []; $sk = [];
    foreach (rows('SELECT * FROM produk') as $r) {
        $byId[$r['id']] = $r;
        if ($r['barcode'] !== null && $r['barcode'] !== '') $bc[(string)$r['barcode']] = $r['id'];
        if ($r['sku'] !== null && $r['sku'] !== '') $sk[strtoupper((string)$r['sku'])] = $r['id'];
    }
    $catNames = array_map(fn($c) => mb_strtolower($c['nama']), k_cats());
    $newCats = []; $now = now_s(); $ids = []; $opLogs = []; $adjAwal = []; $adjKor = []; $newFiles = [];
    try {
        foreach ($items as $idx => $it) {
            $tag = ' (baris ' . ($idx + 1) . ')';
            $nama = str($it['nama'] ?? '', 150);
            if ($nama === '') throw new AppErr('Nama produk wajib diisi' . $tag);
            $hj = num($it['hargaJual'] ?? 0); $hb = num($it['hargaBeli'] ?? 0); $st = r3(num($it['stok'] ?? 0)); $mn = r3(num($it['stokMin'] ?? 0));
            if ($hj < 0 || $hb < 0) throw new AppErr('Harga tidak boleh negatif' . $tag);
            if ($st < 0) throw new AppErr('Stok tidak boleh negatif' . $tag);
            $id = !empty($it['id']) ? (string)$it['id'] : uid('P');
            $old = $byId[$id] ?? null;
            if (!empty($it['id']) && !$old) throw new AppErr('Produk tidak ditemukan' . $tag);
            $barcode = str($it['barcode'] ?? '', 60);
            if ($barcode !== '') {
                $ex = $bc[$barcode] ?? null;
                if ($ex && $ex !== $id) throw new AppErr('Barcode ' . $barcode . ' sudah dipakai produk lain' . $tag);
                $bc[$barcode] = $id;
            }
            $kat = str($it['kategori'] ?? '', 80);
            $sku = strtoupper(preg_replace('/\s+/', '-', str($it['sku'] ?? '', 40)));
            if ($sku !== '' && !preg_match('/^[A-Z0-9][A-Z0-9._\/-]*$/', $sku)) throw new AppErr('SKU hanya boleh huruf, angka, titik, garis miring, dan tanda hubung' . $tag);
            if ($sku === '') $sku = $old && $old['sku'] ? strtoupper((string)$old['sku']) : sku_gen($kat);
            if (($sk[$sku] ?? $id) !== $id) throw new AppErr('SKU ' . $sku . ' sudah dipakai produk lain' . $tag);
            $sk[$sku] = $id;
            if ($kat !== '' && !in_array(mb_strtolower($kat), $catNames, true)) { $catNames[] = mb_strtolower($kat); $newCats[] = [uid('C'), $kat]; }
            $foto = $old ? (string)$old['foto'] : '';
            $galLama = $old ? k_galeri($old['galeri'] ?? null) : []; $gal = $galLama;
            $trashOld = false;
            if (isset($it['fotos']) && is_array($it['fotos'])) {
                // daftar foto lengkap (urut; foto pertama = foto utama). Isi: URL foto lama atau data:image baru
                $punya = array_filter(array_merge([$foto], $galLama));
                $list = [];
                foreach (array_slice($it['fotos'], 0, FOTO_MAX + 1) as $f) {
                    $f = (string)$f;
                    if (strpos($f, 'data:') === 0) { $nf = k_saveFoto($f, $id); $newFiles[] = $nf; $list[] = $nf; }
                    elseif ($f !== '' && in_array($f, $punya, true) && !in_array($f, $list, true)) $list[] = $f;
                }
                if (count($list) > FOTO_MAX) throw new AppErr('Maksimal ' . FOTO_MAX . ' foto per produk' . $tag);
                $foto = $list[0] ?? ''; $gal = array_slice($list, 1);
                foreach ($punya as $f) if (!in_array($f, $list, true)) $GLOBALS['__trash'][] = $f;
            } else {
                if (!empty($it['hapusFoto']) && $old) { $trashOld = true; $foto = ''; }
                if (!empty($it['fotoData'])) { if ($old) $trashOld = true; $foto = k_saveFoto((string)$it['fotoData'], $id); $newFiles[] = $foto; }
            }
            // harga beli hanya boleh diubah oleh yang berhak melihatnya
            if ($old && !k_seeCost($u)) $hb = (float)$old['harga_beli'];
            $vals = [$barcode === '' ? null : $barcode, $sku, $nama, $kat, str($it['satuan'] ?? 'pcs', 20) ?: 'pcs', $hb, $hj, $mn, $foto, $gal ? json_encode($gal, JSON_UNESCAPED_SLASHES) : null, $now];
            if ($old) {
                q('UPDATE produk SET barcode=?, sku=?, nama=?, kategori=?, satuan=?, harga_beli=?, harga_jual=?, stok_min=?, foto=?, galeri=?, diubah=? WHERE id=?', array_merge($vals, [$id]));
                if ($trashOld && $old['foto'] !== $foto) $GLOBALS['__trash'][] = $old['foto'];
            } else {
                q('INSERT INTO produk (barcode, sku, nama, kategori, satuan, harga_beli, harga_jual, stok_min, foto, galeri, diubah, id, dibuat, stok) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0)', array_merge($vals, [$id, $now]));
                $byId[$id] = ['id' => $id];
            }
            // rak & batas minimum per lokasi
            foreach ((array)($it['lok'] ?? []) as $lid => $cfgL) {
                if (!isset(lokasi_all()[$lid])) continue;
                q('INSERT INTO stok_lokasi (pid, lokasi_id, qty, min, rak) VALUES (?, ?, 0, ?, ?) ON DUPLICATE KEY UPDATE min = VALUES(min), rak = VALUES(rak)', [$id, $lid, r3(num($cfgL['min'] ?? 0)), str($cfgL['rak'] ?? '', 30)]);
            }
            // stok: produk baru -> stok awal di lokasi; produk lama -> koreksi hanya bila diminta (setStok)
            $lok = !empty($it['lokasi']) && isset(lokasi_all()[$it['lokasi']]) ? (string)$it['lokasi'] : lok_masuk();
            if (!$old) {
                if ($st > 0) {
                    lok_move($id, $lok, $st);
                    $opLogs[] = [uid('O'), $now, $u['username'], $id, $nama, 0, $st, $st, 'Stok awal produk baru', $lok];
                    $adjAwal[] = ['id' => $id, 'nama' => $nama, 'lama' => 0, 'baru' => $st, 'hb' => $hb];
                }
            } elseif (!empty($it['setStok'])) {
                $lama = lok_qty($id, $lok);
                if (r3($st - $lama) != 0) {
                    lok_move($id, $lok, $st - $lama);
                    $opLogs[] = [uid('O'), $now, $u['username'], $id, $nama, $lama, $st, r3($st - $lama), 'Koreksi via edit produk', $lok];
                    $adjKor[] = ['id' => $id, 'nama' => $nama, 'lama' => $lama, 'baru' => $st, 'hb' => $hb];
                }
            }
            $ids[] = $id;
        }
        insert_many('opname', ['id', 'tanggal', 'user', 'pid', 'nama', 'sistem', 'fisik', 'selisih', 'catatan', 'lokasi_id'], $opLogs);
        insert_many('kategori', ['id', 'nama'], $newCats);
        if ($adjAwal) post_stock_adjust($adjAwal, $u, 'awal');
        if ($adjKor) post_stock_adjust($adjKor, $u, 'koreksi');
    } catch (Throwable $e) {
        foreach ($newFiles as $f) k_trashFoto($f);
        $GLOBALS['__trash'] = [];
        throw $e;
    }
    return ['items' => k_prods($u, array_values(array_unique($ids))), 'cats' => k_cats()];
}
function k_prodDel(array $d): int
{
    $ids = array_values(array_filter(array_map('strval', $d['ids'] ?? [])));
    if (!$ids) return 0;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $list = rows("SELECT id, foto, galeri FROM produk WHERE id IN ($ph)", $ids);
    foreach ($list as $r) { $GLOBALS['__trash'][] = $r['foto']; foreach (k_galeri($r['galeri']) as $g) $GLOBALS['__trash'][] = $g; }
    q("DELETE FROM produk WHERE id IN ($ph)", $ids);
    return count($list);
}

/* barcode EAN-13 toko (awalan 20 = kode internal toko) untuk produk yang belum punya barcode */
function ean13_cek(string $d12): string { $s = 0; for ($i = 0; $i < 12; $i++) $s += (int)$d12[$i] * ($i % 2 ? 3 : 1); return $d12 . ((10 - $s % 10) % 10); }
function k_barcodeIsi(array $d, array $u): array
{
    $ids = array_values(array_filter(array_map('strval', (array)($d['ids'] ?? []))));
    if (!$ids) throw new AppErr('Pilih produk terlebih dahulu');
    $ph = implode(',', array_fill(0, count($ids), '?')); $n = 0;
    foreach (rows("SELECT id FROM produk WHERE id IN ($ph) AND (barcode IS NULL OR barcode = '') ORDER BY nama", $ids) as $p) {
        for ($i = 0; $i < 100; $i++) {
            $bc = ean13_cek('20' . str_pad((string)counter_next('BARCODE_TOKO'), 10, '0', STR_PAD_LEFT));
            if (!val('SELECT id FROM produk WHERE barcode = ?', [$bc])) break;
        }
        q('UPDATE produk SET barcode = ?, diubah = ? WHERE id = ?', [$bc, now_s(), $p['id']]); $n++;
    }
    return ['n' => $n, 'items' => k_prods($u, $ids)];
}

/* ---------------------------- Kategori ---------------------------- */
function k_catSave(array $d): array
{
    $nama = str($d['nama'] ?? '', 80);
    if ($nama === '') throw new AppErr('Nama kategori wajib diisi');
    $id = (string)($d['id'] ?? '');
    if (val('SELECT id FROM kategori WHERE LOWER(nama) = LOWER(?) AND id <> ?', [$nama, $id])) throw new AppErr('Kategori sudah ada');
    if ($id) {
        $c = row('SELECT * FROM kategori WHERE id = ?', [$id]);
        if (!$c) throw new AppErr('Kategori tidak ditemukan');
        q('UPDATE kategori SET nama = ? WHERE id = ?', [$nama, $id]);
        if ($c['nama'] !== $nama) q('UPDATE produk SET kategori = ? WHERE kategori = ?', [$nama, $c['nama']]);
    } else q('INSERT INTO kategori (id, nama, urut) VALUES (?, ?, ?)', [uid('C'), $nama, (int)val('SELECT COALESCE(MAX(urut),0)+1 FROM kategori')]);
    return k_cats();
}
/* urutan kategori (dipakai tombol kategori di kasir & toko online) */
function k_catUrut(array $d): array
{
    foreach (array_values((array)($d['ids'] ?? [])) as $i => $id) q('UPDATE kategori SET urut = ? WHERE id = ?', [$i + 1, (string)$id]);
    return k_cats();
}
/* template kategori per jenis usaha */
const KATEGORI_PRESET = [
    'restoran' => ['Restoran / Kafe', ['Makanan Utama', 'Makanan Ringan', 'Minuman Dingin', 'Minuman Panas', 'Kopi', 'Dessert', 'Paket Hemat', 'Tambahan (Topping)']],
    'warung' => ['Warung / Kelontong', ['Sembako', 'Mie & Makanan Instan', 'Minuman', 'Rokok', 'Snack', 'Bumbu Dapur', 'Sabun & Kebersihan', 'Gas & Air Galon']],
    'ritel' => ['Minimarket / Toko Ritel', ['Makanan', 'Minuman', 'Snack', 'Susu & Bayi', 'Perawatan Diri', 'Kebutuhan Rumah', 'Frozen Food', 'Alat Tulis']],
    'peralatan' => ['Toko Peralatan / Bangunan', ['Perkakas Tangan', 'Perkakas Listrik', 'Cat & Pelapis', 'Listrik & Lampu', 'Pipa & Ledeng', 'Baut & Mur', 'Bahan Bangunan', 'Alat Kebun']],
    'fashion' => ['Fashion / Butik', ['Atasan', 'Bawahan', 'Dress', 'Hijab', 'Pakaian Anak', 'Sepatu & Sandal', 'Tas', 'Aksesoris']],
    'elektronik' => ['Elektronik / Konter HP', ['Handphone', 'Aksesoris HP', 'Pulsa & Paket Data', 'Charger & Kabel', 'Audio', 'Komputer & Laptop', 'Servis', 'Elektronik Rumah']],
    'apotek' => ['Apotek / Toko Kesehatan', ['Obat Bebas', 'Obat Bebas Terbatas', 'Vitamin & Suplemen', 'Alat Kesehatan', 'Perawatan Bayi', 'Herbal', 'P3K', 'Perawatan Kulit']],
    'kosmetik' => ['Kosmetik / Kecantikan', ['Skincare', 'Makeup', 'Perawatan Rambut', 'Parfum', 'Perawatan Tubuh', 'Alat Kecantikan', 'Paket Hadiah']],
    'bengkel' => ['Bengkel / Sparepart', ['Oli & Pelumas', 'Ban & Velg', 'Aki', 'Sparepart Mesin', 'Kelistrikan', 'Aksesoris Kendaraan', 'Jasa Servis']],
];
function k_catPreset(array $d): array
{
    $j = (string)($d['jenis'] ?? '');
    if (!isset(KATEGORI_PRESET[$j])) throw new AppErr('Jenis usaha tidak dikenal');
    $ada = array_map(fn($c) => mb_strtolower($c['nama']), k_cats()); $n = 0;
    $u = (int)val('SELECT COALESCE(MAX(urut),0) FROM kategori');
    foreach (KATEGORI_PRESET[$j][1] as $nm) {
        if (in_array(mb_strtolower($nm), $ada, true)) continue;
        q('INSERT INTO kategori (id, nama, urut) VALUES (?, ?, ?)', [uid('C'), $nm, ++$u]); $n++;
    }
    setting_put(['JenisUsaha' => $j]);
    return ['tambah' => $n, 'cats' => k_cats()];
}
/* hapus semua kategori yang tidak dipakai produk */
function k_catBersih(): array
{
    $n = q("DELETE FROM kategori WHERE nama NOT IN (SELECT DISTINCT kategori FROM produk)")->rowCount();
    return ['hapus' => $n, 'cats' => k_cats()];
}
function k_catDel(array $d): array
{
    $c = row('SELECT * FROM kategori WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$c) throw new AppErr('Kategori tidak ditemukan');
    $used = (int)val('SELECT COUNT(*) FROM produk WHERE kategori = ?', [$c['nama']]);
    if ($used) {
        if (!array_key_exists('pindah', $d)) throw new AppErr('Kategori masih dipakai ' . $used . ' produk. Pilih kategori tujuan untuk produknya.');
        $ke = str($d['pindah'] ?? '', 80);
        if ($ke !== '' && !val('SELECT id FROM kategori WHERE nama = ? AND id <> ?', [$ke, $c['id']])) throw new AppErr('Kategori tujuan tidak ditemukan');
        q('UPDATE produk SET kategori = ? WHERE kategori = ?', [$ke, $c['nama']]);
    }
    q('DELETE FROM kategori WHERE id = ?', [$c['id']]);
    return k_cats();
}

/* ---------------------------- Transaksi ---------------------------- */
function k_nextNo(string $prefix): string
{
    $day = date('ymd');
    return $prefix . '-' . $day . '-' . str_pad((string)counter_next($prefix . '_' . $day), 4, '0', STR_PAD_LEFT);
}

function k_checkout(array $d, array $u): array
{
    $set = k_set();
    $items = $d['items'] ?? [];
    if (!$items) throw new AppErr('Keranjang kosong');
    $mem = null;
    if (!empty($d['member'])) {
        $mem = row('SELECT * FROM member WHERE id = ? AND aktif = 1 FOR UPDATE', [(string)$d['member']]);
        if (!$mem) throw new AppErr('Member tidak ditemukan atau sudah nonaktif');
    }
    $c = k_price($items, $d, $mem, true);
    if (isset($d['expect']) && abs(num($d['expect']) - $c['total']) > 0.5)
        throw new AppErr('Total berubah menjadi ' . fmt_num($c['total']) . ' karena promo atau harga terbaru. Periksa keranjang lalu bayar kembali.', 'PRICE');
    $lj = $GLOBALS['__lok_jual'] ?? lok_jual(); $ids = array_values(array_unique(array_map(fn($l) => $l['id'], $c['lines'])));
    $LQ = [];
    foreach (rows('SELECT pid, qty FROM stok_lokasi WHERE lokasi_id = ? AND pid IN (' . implode(',', array_fill(0, count($ids), '?')) . ') FOR UPDATE', array_merge([$lj], $ids)) as $r) $LQ[$r['pid']] = (float)$r['qty'];
    $stokBaru = []; $lines = [];
    foreach ($c['lines'] as $l) {
        $r = $l['p'];
        $sisa = $stokBaru[$r['id']] ?? ($LQ[$r['id']] ?? 0.0);
        if (!$set['izinMinus'] && empty($GLOBALS['__izin_minus']) && $l['qty'] > $sisa + 1e-9) throw new AppErr('Stok "' . $r['nama'] . '" tidak cukup (sisa ' . jn($sisa) . ' ' . $r['satuan'] . ')');
        $stokBaru[$r['id']] = r3($sisa - $l['qty']);
        $lines[] = ['id' => $r['id'], 'sku' => (string)($r['sku'] ?? ''), 'barcode' => (string)($r['barcode'] ?? ''), 'nama' => $r['nama'], 'kategori' => $r['kategori'], 'satuan' => $r['satuan'],
            'qty' => jn($l['qty']), 'harga' => jn($l['harga']), 'hpp' => jn((float)$r['harga_beli']), 'diskon' => $l['dp'] + $l['dm'], 'promo' => $l['promo'], 'dp' => $l['dp'], 'subtotal' => $l['net']];
    }
    $total = $c['total'];
    $metode = in_array($d['metode'] ?? '', ['Tunai', 'QRIS', 'Transfer', 'Debit'], true) ? $d['metode'] : 'Tunai';
    $bayar = num($d['bayar'] ?? 0);
    if ($metode === 'Tunai') { if ($bayar < $total) throw new AppErr('Uang diterima kurang dari total tagihan'); } else $bayar = $total;
    $kembali = $bayar - $total;
    $hppTot = $c['hpp'];
    $laba = (int)round($c['after'] - $hppTot);

    foreach ($stokBaru as $id => $st) lok_move($id, $lj, $st - ($LQ[$id] ?? 0.0));
    $now = $GLOBALS['__trx_time'] ?? now_s(); $no = k_nextNo('TRX'); $diskon = $c['diskon'];
    $pel = str($d['pelanggan'] ?? '', 120); $cat = str($d['catatan'] ?? '', 500);
    if ($mem && $pel === '') $pel = $mem['nama'];
    $extra = ['nota' => $c['nota'], 'gd' => $c['gd'], 'poinNilai' => $c['nilaiPoin']];
    q('INSERT INTO transaksi (no, tanggal, kasir, pelanggan, subtotal, diskon, pajak, total, metode, bayar, kembali, laba, hpp, status, catatan, items, lokasi_id, member_id, poin_dapat, poin_tukar, diskon_promo, diskon_poin) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$no, $now, $u['username'], $pel, $c['gross'], $diskon, $c['pajak'], $total, $metode, $bayar, $kembali, $laba, $hppTot, 'Selesai', $cat, json_encode(['v' => 4, 'items' => $lines, 'x' => $extra], JSON_UNESCAPED_UNICODE), $lj,
         $mem['id'] ?? '', $c['poinDapat'], $c['poinTukar'], $c['diskonPromo'], $c['nilaiPoin']]);
    insert_many('transaksi_detail', ['no', 'tanggal', 'pid', 'barcode', 'nama', 'kategori', 'satuan', 'qty', 'harga', 'diskon', 'subtotal', 'hpp', 'status'],
        array_map(fn($l) => [$no, $now, $l['id'], $l['barcode'], $l['nama'], $l['kategori'], $l['satuan'], $l['qty'], $l['harga'], $l['diskon'], $l['subtotal'], $l['hpp'], 'Selesai'], $lines));
    $pk = [];
    foreach ($c['used'] as $pid => $dv) if ($dv > 0) $pk[] = [$pid, $no, $now, $dv, 'Selesai'];
    insert_many('promo_pakai', ['promo_id', 'no', 'tanggal', 'diskon', 'status'], $pk);
    // ===== member & poin =====
    $mOut = null;
    if ($mem) {
        if ($c['poinTukar'] > 0) member_poin($mem['id'], -$c['poinTukar'], 'Tukar', $no, 'Ditukar senilai ' . fmt_num($c['nilaiPoin']), $u);
        $saldo = member_poin($mem['id'], $c['poinDapat'], 'Dapat', $no, 'Belanja ' . fmt_num($total), $u);
        q('UPDATE member SET total_belanja = total_belanja + ?, jml_trx = jml_trx + 1, terakhir = ? WHERE id = ?', [$total, $now, $mem['id']]);
        $mOut = ['id' => $mem['id'], 'kode' => $mem['kode'], 'nama' => $mem['nama'], 'poin' => $saldo];
    }
    // ===== integrasi: jurnal penjualan otomatis =====
    post_pos_sale(['no' => $no, 'tanggal' => $now, 'metode' => $metode, 'subtotal' => $c['gross'], 'diskon' => $diskon, 'pajak' => $c['pajak'], 'total' => $total, 'hpp' => $hppTot, 'pelanggan' => $pel], $u);

    return [
        'trx' => ['no' => $no, 'tanggal' => $now, 'kasir' => $u['username'], 'pelanggan' => $pel, 'subtotal' => $c['gross'], 'diskon' => $diskon, 'pajak' => $c['pajak'], 'total' => $total, 'metode' => $metode,
            'bayar' => jn($bayar), 'kembali' => jn($kembali), 'laba' => $laba, 'status' => 'Selesai', 'catatan' => $cat, 'items' => $lines, 'x' => $extra,
            'member' => $mOut, 'poinDapat' => $c['poinDapat'], 'poinTukar' => $c['poinTukar']],
        'stok' => stok_snapshot(array_keys($stokBaru)),
    ];
}
/** Pratinjau harga keranjang (promo, poin, pajak) — sama persis dengan checkout. */
function k_promoHitung(array $d, array $u): array
{
    if (empty($d['items'])) return ['lines' => [], 'gross' => 0, 'sub' => 0, 'nota' => null, 'gd' => 0, 'poinTukar' => 0, 'nilaiPoin' => 0, 'pajak' => 0, 'total' => 0, 'poinDapat' => 0, 'diskon' => 0];
    $mem = !empty($d['member']) ? row('SELECT * FROM member WHERE id = ? AND aktif = 1', [(string)$d['member']]) : null;
    return price_out(k_price($d['items'], $d, $mem));
}
function k_memberCari(array $d): array
{
    $q = str($d['q'] ?? '', 60);
    if (mb_strlen($q) < 2) return [];
    $hp = member_hp($q); $like = '%' . $q . '%';
    $sql = 'SELECT * FROM member WHERE aktif = 1 AND (nama LIKE ? OR kode LIKE ?' . (strlen($hp) >= 3 ? ' OR hp LIKE ?' : '') . ') ORDER BY (kode = ?) DESC, (hp = ?) DESC, nama LIMIT 12';
    $a = [$like, $like]; if (strlen($hp) >= 3) $a[] = '%' . $hp . '%'; $a[] = strtoupper($q); $a[] = $hp;
    return array_map('member_out', rows($sql, $a));
}
function k_memberDaftar(array $d, array $u): array { unset($d['id']); return member_save($d, $u); }

function k_trxOut(array $r, bool $hide): array
{
    $raw = json_decode((string)$r['items'], true) ?: [];
    $items = isset($raw['items']) ? $raw['items'] : $raw; $x = $raw['x'] ?? null;
    if ($hide) foreach ($items as &$i) $i['hpp'] = 0;
    unset($i);
    return [
        'no' => $r['no'], 'tanggal' => $r['tanggal'], 'kasir' => $r['kasir'], 'pelanggan' => $r['pelanggan'], 'subtotal' => jn($r['subtotal']), 'diskon' => jn($r['diskon']), 'pajak' => jn($r['pajak']),
        'total' => jn($r['total']), 'metode' => $r['metode'], 'bayar' => jn($r['bayar']), 'kembali' => jn($r['kembali']), 'laba' => $hide ? 0 : jn($r['laba']), 'status' => $r['status'], 'catatan' => $r['catatan'], 'items' => $items,
        'x' => $x, 'memberId' => $r['member_id'] ?? '', 'poinDapat' => (int)($r['poin_dapat'] ?? 0), 'poinTukar' => (int)($r['poin_tukar'] ?? 0), 'lokasi' => $r['lokasi_id'] ?? '',
    ];
}
function k_trxList(array $d, array $u): array
{
    if (!is_date($d['from'] ?? null) || !is_date($d['to'] ?? null)) throw new AppErr('Rentang tanggal tidak valid');
    $lvl = $u['akses']['kasir'] ?? 0;
    $own = $lvl === 2;                       // petugas kasir hanya melihat transaksinya sendiri
    $hide = $lvl < 3;                        // HPP & laba hanya untuk manajer
    $sql = 'SELECT * FROM transaksi WHERE tanggal BETWEEN ? AND ?';
    $a = [$d['from'] . ' 00:00:00', $d['to'] . ' 23:59:59'];
    if ($own) { $sql .= ' AND kasir = ?'; $a[] = $u['username']; }
    return array_map(fn($r) => k_trxOut($r, $hide), rows($sql . ' ORDER BY tanggal, no', $a));
}
function k_trxVoid(array $d, array $u): array
{
    $t = row('SELECT * FROM transaksi WHERE no = ? FOR UPDATE', [(string)($d['no'] ?? '')]);
    if (!$t) throw new AppErr('Transaksi tidak ditemukan');
    if ($t['status'] === 'Batal') throw new AppErr('Transaksi sudah dibatalkan');
    $raw = json_decode((string)$t['items'], true) ?: [];
    $items = isset($raw['items']) ? $raw['items'] : $raw;
    $lok = isset(lokasi_all()[$t['lokasi_id'] ?? '']) ? $t['lokasi_id'] : lok_jual();
    $pids = [];
    foreach ($items as $it) {
        if (!val('SELECT id FROM produk WHERE id = ?', [$it['id']])) continue;
        lok_move((string)$it['id'], $lok, num($it['qty']));
        $pids[] = $it['id'];
    }
    $alasan = str($d['alasan'] ?? '', 200);
    $cat = (string)$t['catatan'];
    q('UPDATE transaksi SET status = ?, batal_pada = NOW(), catatan = ? WHERE no = ?', ['Batal', ($cat !== '' ? $cat . ' | ' : '') . 'DIBATALKAN oleh ' . $u['username'] . ($alasan ? ': ' . $alasan : ''), $t['no']]);
    q('UPDATE transaksi_detail SET status = ? WHERE no = ?', ['Batal', $t['no']]);
    q("UPDATE promo_pakai SET status = 'Batal' WHERE no = ?", [$t['no']]);
    if (!empty($t['member_id']) && val('SELECT id FROM member WHERE id = ?', [$t['member_id']])) {
        $net = (int)$t['poin_tukar'] - (int)$t['poin_dapat'];
        member_poin($t['member_id'], $net, 'Batal', $t['no'], 'Transaksi dibatalkan' . ($alasan ? ': ' . $alasan : ''), $u);
        q('UPDATE member SET total_belanja = GREATEST(0, total_belanja - ?), jml_trx = GREATEST(0, jml_trx - 1) WHERE id = ?', [$t['total'], $t['member_id']]);
    }
    post_pos_void($t['no'], $u, $alasan);
    return ['stok' => stok_snapshot($pids)];
}
/* ---------------------------- Stok Opname ---------------------------- */
function k_opnameSave(array $d, array $u): array
{
    $items = $d['items'] ?? [];
    if (!$items) throw new AppErr('Belum ada produk yang dihitung');
    $lok = lok_valid($d['lokasi'] ?? '');
    $now = now_s(); $logs = []; $adj = []; $diff = 0; $pids = [];
    $ket = trim(str($d['petugas'] ?? '', 60) . ($d['catatan'] ?? '' ? ' · ' . str($d['catatan'], 180) : ''), ' ·');
    foreach ($items as $it) {
        $p = row('SELECT id, nama, harga_beli FROM produk WHERE id = ? FOR UPDATE', [(string)($it['id'] ?? '')]);
        if (!$p) continue;
        $fisik = r3(num($it['fisik'] ?? 0));
        if ($fisik < 0) throw new AppErr('Stok fisik tidak boleh negatif: ' . $p['nama']);
        $sistem = lok_qty($p['id'], $lok); $sel = r3($fisik - $sistem);
        if ($sel != 0) { $diff++; $adj[] = ['id' => $p['id'], 'nama' => $p['nama'], 'lama' => $sistem, 'baru' => $fisik, 'hb' => (float)$p['harga_beli']]; lok_move($p['id'], $lok, $sel); }
        $pids[] = $p['id'];
        $logs[] = [uid('O'), $now, $u['username'], $p['id'], $p['nama'], $sistem, $fisik, $sel, mb_substr(str($it['catatan'] ?? '') ?: $ket, 0, 255), $lok];
    }
    insert_many('opname', ['id', 'tanggal', 'user', 'pid', 'nama', 'sistem', 'fisik', 'selisih', 'catatan', 'lokasi_id'], $logs);
    if ($adj) post_stock_adjust($adj, $u, 'opname');
    return ['stok' => stok_snapshot($pids), 'checked' => count($logs), 'diff' => $diff];
}
function k_opnameList(array $d): array
{
    $L = lokasi_all();
    return array_map(fn($r) => ['id' => $r['id'], 'tanggal' => $r['tanggal'], 'user' => $r['user'], 'pid' => $r['pid'], 'nama' => $r['nama'], 'sistem' => jn($r['sistem']), 'fisik' => jn($r['fisik']), 'selisih' => jn($r['selisih']), 'catatan' => $r['catatan'],
        'lokasi' => $L[$r['lokasi_id']]['nama'] ?? ''], rows('SELECT * FROM opname ORDER BY tanggal DESC, id DESC LIMIT 500'));
}
/* ---------------------------- Stok Masuk ---------------------------- */
/** Data pendukung form stok masuk: pemasok (kontak akuntansi) & akun kas/bank. */
function k_smMeta(): array
{
    $s = settings_all();
    return [
        'pemasok' => array_map(fn($r) => ['id' => $r['id'], 'nama' => $r['nama']], rows("SELECT id, nama FROM kontak WHERE tipe IN ('Pemasok','Keduanya') AND status = 'Aktif' ORDER BY nama")),
        'kas' => array_map(fn($r) => ['kode' => $r['kode'], 'nama' => $r['nama']], rows("SELECT kode, nama FROM akun WHERE subkategori = 'Kas & Bank' AND status = 'Aktif' ORDER BY kode")),
        'akunKas' => $s['AkunKas'] ?: '1101', 'akunBank' => $s['PosAkunBank'] ?: '1102',
    ];
}
function k_findOrCreateSupplier(string $nama, array $u): string
{
    if ($nama === '') return '';
    $k = row("SELECT id, tipe FROM kontak WHERE LOWER(nama) = LOWER(?) ORDER BY FIELD(tipe,'Pemasok','Keduanya','Pelanggan') LIMIT 1", [$nama]);
    if ($k) {
        if ($k['tipe'] === 'Pelanggan') q("UPDATE kontak SET tipe = 'Keduanya' WHERE id = ?", [$k['id']]);
        return $k['id'];
    }
    $id = uid('K', 10);
    q("INSERT INTO kontak (id, nama, tipe, telepon, email, alamat, npwp, status) VALUES (?, ?, 'Pemasok', '', '', '', '', 'Aktif')", [$id, $nama]);
    log_act($u, 'Tambah', 'Kontak', $nama . ' (otomatis dari stok masuk)');
    return $id;
}

function k_smSave(array $d, array $u, string $poNo = ''): array
{
    $items = $d['items'] ?? [];
    if (!$items) throw new AppErr('Belum ada produk yang ditambahkan');
    $now = now_s(); $tgl = $now;
    $ts = trim((string)($d['tanggal'] ?? ''));
    if ($ts !== '') {
        if (!is_date($ts)) throw new AppErr('Format tanggal tidak valid');
        if ($ts > today_s()) throw new AppErr('Tanggal stok masuk tidak boleh di masa depan');
        if ($ts !== today_s()) $tgl = $ts . ' 12:00:00';
    }
    $bayar = in_array($d['bayar'] ?? '', ['Kas', 'Bank', 'Hutang'], true) ? $d['bayar'] : 'Kas';
    $sup = str($d['supplier'] ?? '', 120); $cat = str($d['catatan'] ?? '', 255);
    if ($bayar === 'Hutang' && $sup === '') throw new AppErr('Isi nama supplier untuk pembelian secara hutang');

    $merged = []; $order = [];
    foreach ($items as $n => $it) {
        $id = (string)($it['id'] ?? '');
        $p = row('SELECT * FROM produk WHERE id = ? FOR UPDATE', [$id]);
        if (!$p) throw new AppErr('Produk tidak ditemukan atau sudah dihapus (baris ' . ($n + 1) . ')');
        $qty = r3(num($it['qty'] ?? 0));
        if (!($qty > 0)) throw new AppErr('Jumlah harus lebih dari 0: ' . $p['nama']);
        $harga = max(0, num($it['harga'] ?? 0));
        if (!isset($merged[$id])) { $merged[$id] = ['p' => $p, 'qty' => 0, 'harga' => 0]; $order[] = $id; }
        $merged[$id]['qty'] = r3($merged[$id]['qty'] + $qty);
        if ($harga > 0) $merged[$id]['harga'] = $harga;
    }
    $no = k_nextNo('SM');
    $lok = !empty($d['lokasi']) ? lok_valid($d['lokasi']) : lok_masuk();
    $logs = []; $out = []; $stok = []; $hargaBaru = []; $grand = 0;
    foreach ($order as $id) {
        $m = $merged[$id]; $r = $m['p'];
        $sebelum = lok_qty($id, $lok);
        $harga = $m['harga'] > 0 ? $m['harga'] : (float)$r['harga_beli'];
        $upd = !empty($d['updHarga']) && $m['harga'] > 0;
        $sesudah = r3(lok_move($id, $lok, $m['qty']));
        q('UPDATE produk SET ' . ($upd ? 'harga_beli = ?, ' : '') . 'diubah = ? WHERE id = ?', $upd ? [$m['harga'], $now, $id] : [$now, $id]);
        if ($upd) $hargaBaru[$id] = jn($m['harga']);
        $stok[$id] = jn($sesudah);
        $total = (int)round($m['qty'] * $harga); $grand += $total;
        $logs[] = [$no, $tgl, $u['username'], $sup, $id, (string)($r['barcode'] ?? ''), $r['nama'], $r['satuan'], $m['qty'], $harga, $total, $sebelum, $sesudah, $cat, $lok];
        $out[] = ['pid' => $id, 'barcode' => (string)($r['barcode'] ?? ''), 'nama' => $r['nama'], 'satuan' => $r['satuan'], 'qty' => jn($m['qty']), 'harga' => jn($harga), 'total' => $total, 'sebelum' => jn($sebelum), 'sesudah' => jn($sesudah)];
    }
    insert_many('stok_masuk', ['no', 'tanggal', 'user', 'supplier', 'pid', 'barcode', 'nama', 'satuan', 'qty', 'harga', 'total', 'sebelum', 'sesudah', 'catatan', 'lokasi_id'], $logs);

    // ===== integrasi akuntansi =====
    $tglD = substr($tgl, 0, 10);
    $kontakId = $sup !== '' ? k_findOrCreateSupplier($sup, $u) : '';
    $akunKas = ''; $fakturId = ''; $fakturNo = '';
    if ($grand > 0) assert_open_period($tglD, 'Stok masuk');
    if ($bayar === 'Hutang') {
        if ($grand <= 0) throw new AppErr('Isi harga beli produk untuk pembelian secara hutang');
        require_once __DIR__ . '/akun.php';
        $jt = (string)($d['jatuhTempo'] ?? '');
        if (!is_date($jt) || $jt < $tglD) $jt = add_days($tglD, 30);
        $f = a_fakturCreateInternal([
            'tipe' => 'Pembelian', 'tanggal' => $tglD, 'jatuhTempo' => $jt, 'kontakId' => $kontakId, 'akunLawan' => pos_acc('PosAkunPersediaan'),
            'ket' => 'Stok masuk ' . $no . ($poNo ? ' (PO ' . $poNo . ')' : '') . ($cat ? ' - ' . $cat : ''), 'persenPPN' => 0, 'sumber' => 'SM:' . $no,
            'items' => array_map(fn($o) => ['d' => $o['nama'] . ' (' . $o['satuan'] . ')', 'q' => $o['qty'], 'h' => $o['harga']], array_values(array_filter($out, fn($o) => $o['total'] > 0))),
        ], $u);
        $fakturId = $f['id']; $fakturNo = $f['no'];
    } else {
        $s = settings_all();
        $akunKas = (string)($d['akunKas'] ?? '');
        if (!$akunKas || !akun_exists($akunKas)) $akunKas = $bayar === 'Bank' ? ($s['PosAkunBank'] ?: '1102') : ($s['AkunKas'] ?: '1101');
        post_stock_in_cash($no, $tglD, (float)$grand, $akunKas, $sup, $kontakId, $u);
    }
    q('INSERT INTO stok_masuk_doc (no, tanggal, user, supplier, kontak_id, bayar, akun_kas, faktur_id, total, catatan, lokasi_id, po_no) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [$no, $tgl, $u['username'], $sup, $kontakId, $bayar, $akunKas, $fakturId, $grand, $cat, $lok, $poNo]);
    return ['no' => $no, 'tanggal' => $tgl, 'user' => $u['username'], 'supplier' => $sup, 'catatan' => $cat, 'items' => $out, 'stok' => stok_snapshot($order), 'lokasi' => lokasi_all()[$lok]['nama'], 'harga' => (object)$hargaBaru,
        'total' => $grand, 'bayar' => $bayar, 'fakturNo' => $fakturNo];
}

function k_smList(array $d): array
{
    if (!is_date($d['from'] ?? null) || !is_date($d['to'] ?? null)) throw new AppErr('Rentang tanggal tidak valid');
    $L = lokasi_all();
    $r = rows('SELECT s.*, COALESCE(d.bayar, "") AS bayar FROM stok_masuk s LEFT JOIN stok_masuk_doc d ON d.no = s.no WHERE s.tanggal BETWEEN ? AND ? ORDER BY s.tanggal DESC, s.id LIMIT 5000',
        [$d['from'] . ' 00:00:00', $d['to'] . ' 23:59:59']);
    return array_map(fn($x) => ['no' => $x['no'], 'tanggal' => $x['tanggal'], 'user' => $x['user'], 'supplier' => $x['supplier'], 'pid' => $x['pid'], 'barcode' => $x['barcode'], 'nama' => $x['nama'], 'satuan' => $x['satuan'],
        'qty' => jn($x['qty']), 'harga' => jn($x['harga']), 'total' => jn($x['total']), 'sebelum' => jn($x['sebelum']), 'sesudah' => jn($x['sesudah']), 'catatan' => $x['catatan'], 'bayar' => $x['bayar'], 'lokasi' => $L[$x['lokasi_id']]['nama'] ?? ''], $r);
}
/** Rekap stok periode: stok awal, masuk, terjual, penyesuaian (opname/koreksi), stok akhir. */
function k_stokRekap(array $d): array
{
    if (!is_date($d['from'] ?? null) || !is_date($d['to'] ?? null)) throw new AppErr('Rentang tanggal tidak valid');
    $a = $d['from'] . ' 00:00:00'; $b = $d['to'] . ' 23:59:59';
    $mv = [];
    $slot = function ($id) use (&$mv) { if (!isset($mv[$id])) $mv[$id] = ['m' => 0.0, 'k' => 0.0, 'a' => 0.0, 'after' => 0.0]; };
    foreach (rows('SELECT pid, SUM(CASE WHEN tanggal BETWEEN ? AND ? THEN qty ELSE 0 END) x, SUM(CASE WHEN tanggal > ? THEN qty ELSE 0 END) aft FROM stok_masuk GROUP BY pid', [$a, $b, $b]) as $r) { $slot($r['pid']); $mv[$r['pid']]['m'] = (float)$r['x']; $mv[$r['pid']]['after'] += (float)$r['aft']; }
    foreach (rows("SELECT pid, SUM(CASE WHEN tanggal BETWEEN ? AND ? THEN qty ELSE 0 END) x, SUM(CASE WHEN tanggal > ? THEN qty ELSE 0 END) aft FROM transaksi_detail WHERE status = 'Selesai' GROUP BY pid", [$a, $b, $b]) as $r) { $slot($r['pid']); $mv[$r['pid']]['k'] = (float)$r['x']; $mv[$r['pid']]['after'] -= (float)$r['aft']; }
    foreach (rows('SELECT pid, SUM(CASE WHEN tanggal BETWEEN ? AND ? THEN selisih ELSE 0 END) x, SUM(CASE WHEN tanggal > ? THEN selisih ELSE 0 END) aft FROM opname GROUP BY pid', [$a, $b, $b]) as $r) { $slot($r['pid']); $mv[$r['pid']]['a'] = (float)$r['x']; $mv[$r['pid']]['after'] += (float)$r['aft']; }
    $out = [];
    foreach (rows('SELECT * FROM produk ORDER BY nama') as $p) {
        $x = $mv[$p['id']] ?? ['m' => 0, 'k' => 0, 'a' => 0, 'after' => 0];
        $akhir = r3((float)$p['stok'] - $x['after']);
        $awal = r3($akhir - ($x['m'] - $x['k'] + $x['a']));
        $hb = (float)$p['harga_beli'];
        $out[] = ['id' => $p['id'], 'sku' => (string)($p['sku'] ?? ''), 'barcode' => (string)($p['barcode'] ?? ''), 'nama' => $p['nama'], 'kategori' => $p['kategori'], 'satuan' => $p['satuan'], 'stokMin' => jn($p['stok_min']),
            'awal' => jn($awal), 'masuk' => jn(r3($x['m'])), 'keluar' => jn(r3($x['k'])), 'adj' => jn(r3($x['a'])), 'akhir' => jn($akhir), 'hargaBeli' => jn($hb), 'nilai' => (int)round($akhir * $hb)];
    }
    return $out;
}

/* ---------------------------- Akun ---------------------------- */
function k_changePw(array $d, array $u): int
{
    if (!password_verify((string)($d['lama'] ?? ''), $u['pass_hash'])) throw new AppErr('Password lama salah');
    if (mb_strlen((string)($d['baru'] ?? '')) < 6) throw new AppErr('Password baru minimal 6 karakter');
    q('UPDATE users SET pass_hash = ? WHERE id = ?', [make_pw((string)$d['baru']), $u['id']]);
    kill_sessions($u['id'], $u['_token']);
    return 1;
}

/* ---------------------------- Lokasi (toko / gudang) ---------------------------- */
function k_lokasiSave(array $d, array $u): array
{
    $nama = str($d['nama'] ?? '', 80); $kode = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string)($d['kode'] ?? '')));
    if ($nama === '') throw new AppErr('Nama lokasi wajib diisi.');
    if ($kode === '' || strlen($kode) > 12) throw new AppErr('Kode lokasi 1–12 huruf/angka.');
    $tipe = ($d['tipe'] ?? '') === 'toko' ? 'toko' : 'gudang';
    $aktif = !isset($d['aktif']) || !empty($d['aktif']) ? 1 : 0;
    $id = (string)($d['id'] ?? '');
    if (val('SELECT id FROM lokasi WHERE kode = ? AND id <> ?', [$kode, $id])) throw new AppErr('Kode lokasi sudah dipakai.');
    if ($id) {
        if (!isset(lokasi_all()[$id])) throw new AppErr('Lokasi tidak ditemukan.');
        if (!$aktif) {
            if (in_array($id, [lok_jual(), lok_masuk()], true)) throw new AppErr('Lokasi ini dipakai sebagai lokasi default. Ganti default dulu.');
            if ((float)val('SELECT COALESCE(SUM(ABS(qty)),0) FROM stok_lokasi WHERE lokasi_id = ?', [$id]) > 0) throw new AppErr('Masih ada stok di lokasi ini. Pindahkan dulu lewat mutasi.');
        }
        q('UPDATE lokasi SET kode=?, nama=?, tipe=?, aktif=? WHERE id=?', [$kode, $nama, $tipe, $aktif, $id]);
    } else {
        q('INSERT INTO lokasi (id, kode, nama, tipe, aktif, urut) VALUES (?,?,?,?,?,?)', [uid('L'), $kode, $nama, $tipe, $aktif, 9]);
    }
    log_act($u, $id ? 'Ubah' : 'Tambah', 'Lokasi', $kode . ' ' . $nama);
    return ['lokasi' => k_lokasiOutFresh(), 'lokJual' => lok_jual(), 'lokMasuk' => lok_masuk()];
}
function k_lokasiOutFresh(): array
{
    return array_map(fn($l) => ['id' => $l['id'], 'kode' => $l['kode'], 'nama' => $l['nama'], 'tipe' => $l['tipe'], 'aktif' => (bool)$l['aktif']], rows('SELECT * FROM lokasi ORDER BY urut, nama'));
}
function k_lokasiDefault(array $d, array $u): array
{
    $L = []; foreach (rows('SELECT * FROM lokasi WHERE aktif = 1') as $r) $L[$r['id']] = $r;
    $j = (string)($d['jual'] ?? ''); $m = (string)($d['masuk'] ?? '');
    if (!isset($L[$j]) || !isset($L[$m])) throw new AppErr('Lokasi default tidak valid.');
    setting_put(['LokasiJual' => $j, 'LokasiMasuk' => $m]);
    log_act($u, 'Ubah', 'Lokasi', 'Default jual ' . $L[$j]['kode'] . ', masuk ' . $L[$m]['kode']);
    return ['lokJual' => $j, 'lokMasuk' => $m];
}

/* ---------------------------- Mutasi stok antar-lokasi ----------------------------
 * Permintaan -> Dikirim (stok asal berkurang, barang "di jalan") -> Diterima (stok tujuan bertambah).
 * Kekurangan saat diterima dicatat sebagai selisih stok (opname + jurnal). Total persediaan tidak berubah.
 */
function k_mutDoc(string $no, bool $lock = false): array
{
    $m = row('SELECT * FROM mutasi WHERE no = ?' . ($lock ? ' FOR UPDATE' : ''), [$no]);
    if (!$m) throw new AppErr('Dokumen mutasi tidak ditemukan.');
    return $m;
}
function k_mutOut(array $m, bool $items = false): array
{
    $L = lokasi_all();
    $o = ['no' => $m['no'], 'tanggal' => $m['tanggal'], 'dari' => $m['dari'], 'ke' => $m['ke'], 'dariNama' => $L[$m['dari']]['nama'] ?? '?', 'keNama' => $L[$m['ke']]['nama'] ?? '?',
        'status' => $m['status'], 'catatan' => $m['catatan'], 'dibuat' => $m['dibuat_oleh'], 'dikirim' => $m['dikirim_oleh'], 'dikirimPada' => $m['dikirim_pada'] ?? '', 'diterima' => $m['diterima_oleh'], 'diterimaPada' => $m['diterima_pada'] ?? ''];
    if ($items) {
        $rows = rows('SELECT i.*, COALESCE(a.qty,0) q_dari, COALESCE(b.qty,0) q_ke, COALESCE(a.rak,"") rak_dari, COALESCE(b.rak,"") rak_ke, COALESCE(p.barcode,"") barcode FROM mutasi_item i
            LEFT JOIN stok_lokasi a ON a.pid = i.pid AND a.lokasi_id = ? LEFT JOIN stok_lokasi b ON b.pid = i.pid AND b.lokasi_id = ? LEFT JOIN produk p ON p.id = i.pid WHERE i.no = ? ORDER BY i.id', [$m['dari'], $m['ke'], $m['no']]);
        $o['items'] = array_map(fn($r) => ['pid' => $r['pid'], 'nama' => $r['nama'], 'satuan' => $r['satuan'], 'barcode' => $r['barcode'], 'minta' => jn($r['qty_minta']), 'kirim' => jn($r['qty_kirim']), 'terima' => jn($r['qty_terima']),
            'stokDari' => jn($r['q_dari']), 'stokKe' => jn($r['q_ke']), 'rakDari' => $r['rak_dari'], 'rakKe' => $r['rak_ke']], $rows);
    }
    return $o;
}
function k_mutasiList(array $d): array
{
    $w = []; $a = [];
    if (is_date($d['from'] ?? null)) { $w[] = 'm.tanggal >= ?'; $a[] = $d['from'] . ' 00:00:00'; }
    if (is_date($d['to'] ?? null)) { $w[] = 'm.tanggal <= ?'; $a[] = $d['to'] . ' 23:59:59'; }
    if (!empty($d['status'])) { $w[] = 'm.status = ?'; $a[] = (string)$d['status']; }
    $sql = 'SELECT m.*, COUNT(i.id) n, COALESCE(SUM(i.qty_minta),0) qm FROM mutasi m LEFT JOIN mutasi_item i ON i.no = m.no' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' GROUP BY m.no ORDER BY m.tanggal DESC LIMIT 500';
    return array_map(fn($r) => k_mutOut($r) + ['n' => (int)$r['n'], 'qty' => jn($r['qm'])], rows($sql, $a));
}
function k_mutasiGet(array $d): array { return k_mutOut(k_mutDoc((string)($d['no'] ?? '')), true); }
function k_mutItems(array $items): array
{
    $out = [];
    foreach ($items as $it) {
        $pid = (string)($it['pid'] ?? $it['id'] ?? ''); $q = r3(num($it['qty'] ?? 0));
        if ($q <= 0) continue;
        $p = row('SELECT id, nama, satuan FROM produk WHERE id = ?', [$pid]);
        if (!$p) throw new AppErr('Produk tidak ditemukan atau sudah dihapus.');
        $out[$pid] = isset($out[$pid]) ? ['p' => $p, 'q' => r3($out[$pid]['q'] + $q)] : ['p' => $p, 'q' => $q];
    }
    if (!$out) throw new AppErr('Isi minimal satu produk dengan jumlah lebih dari 0.');
    return $out;
}
function k_mutasiSave(array $d, array $u): array
{
    $dari = lok_valid($d['dari'] ?? ''); $ke = lok_valid($d['ke'] ?? '');
    if ($dari === $ke) throw new AppErr('Lokasi asal dan tujuan tidak boleh sama.');
    $items = k_mutItems($d['items'] ?? []);
    $no = (string)($d['no'] ?? '');
    if ($no) {
        $m = k_mutDoc($no, true);
        if ($m['status'] !== 'Permintaan') throw new AppErr('Hanya permintaan yang belum dikirim yang bisa diubah.');
        q('UPDATE mutasi SET dari=?, ke=?, catatan=? WHERE no=?', [$dari, $ke, str($d['catatan'] ?? ''), $no]);
        q('DELETE FROM mutasi_item WHERE no = ?', [$no]);
    } else {
        $no = k_nextNo('MUT');
        q('INSERT INTO mutasi (no, tanggal, dari, ke, status, catatan, dibuat_oleh) VALUES (?,?,?,?,?,?,?)', [$no, now_s(), $dari, $ke, 'Permintaan', str($d['catatan'] ?? ''), $u['username']]);
    }
    insert_many('mutasi_item', ['no', 'pid', 'nama', 'satuan', 'qty_minta'], array_map(fn($x) => [$no, $x['p']['id'], $x['p']['nama'], $x['p']['satuan'], $x['q']], array_values($items)));
    log_act($u, 'Simpan', 'Mutasi', $no);
    return k_mutOut(k_mutDoc($no), true);
}
function k_mutasiKirim(array $d, array $u): array
{
    $m = k_mutDoc((string)($d['no'] ?? ''), true);
    if ($m['status'] !== 'Permintaan') throw new AppErr('Mutasi ini sudah ' . strtolower($m['status']) . '.');
    $qty = []; foreach ($d['items'] ?? [] as $it) $qty[(string)$it['pid']] = r3(num($it['qty'] ?? 0));
    $allow = settings_all()['izinMinus'] === '1'; $any = false;
    foreach (rows('SELECT * FROM mutasi_item WHERE no = ?', [$m['no']]) as $it) {
        $q = array_key_exists($it['pid'], $qty) ? $qty[$it['pid']] : (float)$it['qty_minta'];
        if ($q < 0) throw new AppErr('Jumlah kirim tidak boleh negatif.');
        if ($q > 0) {
            $ada = lok_qty($it['pid'], $m['dari']);
            if (!$allow && $q > $ada + 1e-9) throw new AppErr('Stok "' . $it['nama'] . '" di ' . lokasi_all()[$m['dari']]['nama'] . ' hanya ' . jn($ada) . ' ' . $it['satuan'] . '.');
            lok_move($it['pid'], $m['dari'], -$q, false);
            $any = true;
        }
        q('UPDATE mutasi_item SET qty_kirim = ? WHERE id = ?', [$q, $it['id']]);
    }
    if (!$any) throw new AppErr('Tidak ada barang yang dikirim. Isi jumlah kirim.');
    q("UPDATE mutasi SET status = 'Dikirim', dikirim_oleh = ?, dikirim_pada = ? WHERE no = ?", [$u['username'], now_s(), $m['no']]);
    log_act($u, 'Kirim', 'Mutasi', $m['no']);
    return k_mutOut(k_mutDoc($m['no']), true) + ['stok' => stok_snapshot(array_column(rows('SELECT pid FROM mutasi_item WHERE no = ?', [$m['no']]), 'pid'))];
}
function k_mutasiTerima(array $d, array $u): array
{
    $m = k_mutDoc((string)($d['no'] ?? ''), true);
    if ($m['status'] !== 'Dikirim') throw new AppErr('Mutasi ini belum dikirim atau sudah diterima.');
    $qty = []; foreach ($d['items'] ?? [] as $it) $qty[(string)$it['pid']] = r3(num($it['qty'] ?? 0));
    $now = now_s(); $logs = []; $adj = [];
    foreach (rows('SELECT i.*, p.harga_beli FROM mutasi_item i LEFT JOIN produk p ON p.id = i.pid WHERE i.no = ?', [$m['no']]) as $it) {
        $kirim = (float)$it['qty_kirim'];
        $q = array_key_exists($it['pid'], $qty) ? $qty[$it['pid']] : $kirim;
        if ($q < 0 || $q > $kirim + 1e-9) throw new AppErr('Jumlah diterima "' . $it['nama'] . '" harus 0 sampai ' . jn($kirim) . '.');
        if ($q > 0) lok_move($it['pid'], $m['ke'], $q, false);
        $kurang = r3($kirim - $q);
        if ($kurang > 0 && $it['harga_beli'] !== null) {
            q('UPDATE produk SET stok = ROUND(stok - ?, 3) WHERE id = ?', [$kurang, $it['pid']]);
            $logs[] = [uid('O'), $now, $u['username'], $it['pid'], $it['nama'], $kirim, $q, -$kurang, 'Kurang terima mutasi ' . $m['no'], $m['ke']];
            $adj[] = ['id' => $it['pid'], 'nama' => $it['nama'], 'lama' => $kirim, 'baru' => $q, 'hb' => (float)$it['harga_beli']];
        }
        q('UPDATE mutasi_item SET qty_terima = ? WHERE id = ?', [$q, $it['id']]);
    }
    insert_many('opname', ['id', 'tanggal', 'user', 'pid', 'nama', 'sistem', 'fisik', 'selisih', 'catatan', 'lokasi_id'], $logs);
    if ($adj) post_stock_adjust($adj, $u, 'opname');
    q("UPDATE mutasi SET status = 'Diterima', diterima_oleh = ?, diterima_pada = ? WHERE no = ?", [$u['username'], $now, $m['no']]);
    log_act($u, 'Terima', 'Mutasi', $m['no'] . ($logs ? ' (' . count($logs) . ' produk kurang)' : ''));
    return k_mutOut(k_mutDoc($m['no']), true) + ['stok' => stok_snapshot(array_column(rows('SELECT pid FROM mutasi_item WHERE no = ?', [$m['no']]), 'pid'))];
}
function k_mutasiBatal(array $d, array $u): array
{
    $m = k_mutDoc((string)($d['no'] ?? ''), true);
    if (!in_array($m['status'], ['Permintaan', 'Dikirim'], true)) throw new AppErr('Mutasi yang sudah ' . strtolower($m['status']) . ' tidak dapat dibatalkan.');
    if ($m['status'] === 'Dikirim') foreach (rows('SELECT * FROM mutasi_item WHERE no = ?', [$m['no']]) as $it) if ((float)$it['qty_kirim'] > 0) lok_move($it['pid'], $m['dari'], (float)$it['qty_kirim'], false);
    q("UPDATE mutasi SET status = 'Batal', batal_pada = NOW(), catatan = CONCAT(catatan, ?) WHERE no = ?", [' | Dibatalkan ' . $u['username'] . (!empty($d['alasan']) ? ': ' . str($d['alasan'], 120) : ''), $m['no']]);
    log_act($u, 'Batal', 'Mutasi', $m['no']);
    return k_mutOut(k_mutDoc($m['no']), true) + ['stok' => stok_snapshot(array_column(rows('SELECT pid FROM mutasi_item WHERE no = ?', [$m['no']]), 'pid'))];
}
/** Pindahkan langsung (tanpa tahap kirim–terima), cocok untuk gudang & toko satu lokasi. */
function k_mutasiLangsung(array $d, array $u): array
{
    $doc = k_mutasiSave($d, $u);
    k_mutasiKirim(['no' => $doc['no']], $u);
    return k_mutasiTerima(['no' => $doc['no']], $u);
}

/** Saran isi ulang rak toko dari gudang. */
function k_isiUlang(array $d): array
{
    $toko = !empty($d['ke']) ? lok_valid($d['ke']) : lok_jual();
    $gud = !empty($d['dari']) ? lok_valid($d['dari']) : lok_masuk();
    if ($toko === $gud) return ['rows' => [], 'ke' => $toko, 'dari' => $gud];
    $pend = [];
    foreach (rows("SELECT i.pid, SUM(CASE WHEN m.status='Permintaan' THEN i.qty_minta ELSE i.qty_kirim END) q FROM mutasi m JOIN mutasi_item i ON i.no = m.no WHERE m.ke = ? AND m.status IN ('Permintaan','Dikirim') GROUP BY i.pid", [$toko]) as $r) $pend[$r['pid']] = (float)$r['q'];
    $rs = rows('SELECT p.id, p.nama, p.satuan, p.barcode, p.foto, COALESCE(t.qty,0) tq, COALESCE(t.min,0) tmin, COALESCE(t.rak,"") trak, COALESCE(g.qty,0) gq, COALESCE(g.rak,"") grak
        FROM produk p LEFT JOIN stok_lokasi t ON t.pid = p.id AND t.lokasi_id = ? LEFT JOIN stok_lokasi g ON g.pid = p.id AND g.lokasi_id = ?
        WHERE COALESCE(t.min,0) > 0 AND COALESCE(t.qty,0) <= COALESCE(t.min,0) ORDER BY (COALESCE(t.qty,0) / t.min), p.nama', [$toko, $gud]);
    $out = [];
    foreach ($rs as $r) {
        $tq = (float)$r['tq']; $min = (float)$r['tmin']; $gq = (float)$r['gq']; $pd = $pend[$r['id']] ?? 0;
        $target = $min * 2;
        $saran = max(0, min($gq, $target - $tq - $pd));
        if (fmod($saran, 1) != 0) $saran = floor($saran) ?: r3($saran);
        $out[] = ['pid' => $r['id'], 'nama' => $r['nama'], 'satuan' => $r['satuan'], 'barcode' => (string)$r['barcode'], 'foto' => $r['foto'], 'toko' => jn($tq), 'min' => jn($min), 'rakToko' => $r['trak'],
            'gudang' => jn($gq), 'rakGudang' => $r['grak'], 'proses' => jn($pd), 'saran' => jn($saran)];
    }
    return ['rows' => $out, 'ke' => $toko, 'dari' => $gud];
}
