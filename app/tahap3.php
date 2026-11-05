<?php
/**
 * ============================================================
 *  DHYRA v5 — MODUL TAHAP 3
 *  Kartu stok · Kartu member · Inventaris Aset (penyusutan + jurnal)
 *  SDM & Penggajian (absen selfie+GPS, jadwal, pengajuan, kasbon, gaji)
 *  KPI karyawan · Toko online (admin + katalog publik) · Layanan mandiri karyawan
 * ============================================================
 */
declare(strict_types=1);

const T3_ACTIONS = [
    'kartuStok' => ['f' => 'ks_kartu', 'perm' => [['inventaris', 1]]],
    'ping' => ['f' => 'k_ping'],
    'checkoutOffline' => ['f' => 'k_checkoutOffline', 'write' => 1, 'perm' => [['kasir', 2]]],
    'kartuMember' => ['f' => 'mc_list', 'perm' => [['member', 1]]],
    // ---- Aset ----
    'asetMeta' => ['f' => 'as_meta', 'perm' => [['aset', 1]]],
    'asetList' => ['f' => 'as_list', 'perm' => [['aset', 1]]],
    'asetGet' => ['f' => 'as_get', 'perm' => [['aset', 1]]],
    'asetSave' => ['f' => 'as_save', 'write' => 1, 'perm' => [['aset', 2]]],
    'asetKondisi' => ['f' => 'as_kondisi', 'write' => 1, 'perm' => [['aset', 2]]],
    'asetPerbaikan' => ['f' => 'as_perbaikan', 'write' => 1, 'perm' => [['aset', 2]]],
    'asetLepas' => ['f' => 'as_lepas', 'write' => 1, 'perm' => [['aset', 3]]],
    'asetHapus' => ['f' => 'as_hapus', 'write' => 1, 'perm' => [['aset', 3]]],
    'asetSusutCek' => ['f' => 'as_susutCek', 'perm' => [['aset', 1]]],
    'asetSusut' => ['f' => 'as_susut', 'write' => 1, 'perm' => [['aset', 3]]],
    'asetImport' => ['f' => 'as_import', 'write' => 1, 'perm' => [['aset', 2]]],
    'asetContoh' => ['f' => 'as_contoh', 'write' => 1, 'perm' => [['aset', 3]]],
    // ---- SDM ----
    'sdmMeta' => ['f' => 'sd_meta', 'perm' => [['sdm', 1], ['kpi', 1]]],
    'kryList' => ['f' => 'sd_kryList', 'perm' => [['sdm', 1], ['kpi', 1]]],
    'krySave' => ['f' => 'sd_krySave', 'write' => 1, 'perm' => [['sdm', 3]]],
    'kryDel' => ['f' => 'sd_kryDel', 'write' => 1, 'perm' => [['sdm', 3]]],
    'kryImport' => ['f' => 'sd_kryImport', 'write' => 1, 'perm' => [['sdm', 3]]],
    'kryContoh' => ['f' => 'sd_kryContoh', 'write' => 1, 'perm' => [['sdm', 3]]],
    'shiftSave' => ['f' => 'sd_shiftSave', 'write' => 1, 'perm' => [['sdm', 3]]],
    'gpsSave' => ['f' => 'sd_gpsSave', 'write' => 1, 'perm' => [['sdm', 3]]],
    'jadwalGet' => ['f' => 'sd_jadwalGet', 'perm' => [['sdm', 1]]],
    'jadwalSave' => ['f' => 'sd_jadwalSave', 'write' => 1, 'perm' => [['sdm', 2]]],
    'jadwalImport' => ['f' => 'sd_jadwalImport', 'write' => 1, 'perm' => [['sdm', 2]]],
    'absenHari' => ['f' => 'sd_absenHari', 'perm' => [['sdm', 1]]],
    'absenRekap' => ['f' => 'sd_absenRekap', 'perm' => [['sdm', 1]]],
    'absenManual' => ['f' => 'sd_absenManual', 'write' => 1, 'perm' => [['sdm', 2]]],
    'pjList' => ['f' => 'sd_pjList', 'perm' => [['sdm', 1]]],
    'pjProses' => ['f' => 'sd_pjProses', 'write' => 1, 'perm' => [['sdm', 2]]],
    'pjCekGanti' => ['f' => 'sd_pjCekGanti', 'perm' => [['sdm', 1]]],
    'pjBatal' => ['f' => 'sd_pjBatal', 'write' => 1, 'perm' => [['sdm', 2]]],
    'pjPerintah' => ['f' => 'sd_pjPerintah', 'write' => 1, 'perm' => [['sdm', 2]]],
    'aksesLog' => ['f' => 'sd_aksesLog', 'perm' => [['sdm', 1]]],
    'kasbonList' => ['f' => 'sd_kasbonList', 'perm' => [['sdm', 1]]],
    'kasbonSave' => ['f' => 'sd_kasbonSave', 'write' => 1, 'perm' => [['sdm', 3]]],
    'gajiList' => ['f' => 'sd_gajiList', 'perm' => [['sdm', 1]]],
    'gajiHitung' => ['f' => 'sd_gajiHitung', 'write' => 1, 'perm' => [['sdm', 3]]],
    'gajiSave' => ['f' => 'sd_gajiSave', 'write' => 1, 'perm' => [['sdm', 3]]],
    'gajiFinal' => ['f' => 'sd_gajiFinal', 'write' => 1, 'perm' => [['sdm', 3]]],
    'gajiBayar' => ['f' => 'sd_gajiBayar', 'write' => 1, 'perm' => [['sdm', 3]]],
    'gajiBatal' => ['f' => 'sd_gajiBatal', 'write' => 1, 'perm' => [['sdm', 3]]],
    'sdmSet' => ['f' => 'sd_set', 'write' => 1, 'perm' => [['sdm', 3]]],
    // ---- KPI ----
    'kpiTpl' => ['f' => 'kp_tplList', 'perm' => [['kpi', 1]]],
    'kpiTplSave' => ['f' => 'kp_tplSave', 'write' => 1, 'perm' => [['kpi', 3]]],
    'kpiTplDel' => ['f' => 'kp_tplDel', 'write' => 1, 'perm' => [['kpi', 3]]],
    'kpiHasil' => ['f' => 'kp_hasil', 'perm' => [['kpi', 1]]],
    'kpiNilai' => ['f' => 'kp_nilai', 'write' => 1, 'perm' => [['kpi', 2]]],
    'kpiSet' => ['f' => 'kp_set', 'write' => 1, 'perm' => [['kpi', 3]]],
    'kpiAssign' => ['f' => 'kp_assign', 'write' => 1, 'perm' => [['kpi', 3]]],
    'kpiTplImport' => ['f' => 'kp_tplImport', 'write' => 1, 'perm' => [['kpi', 3]]],
    'kpiNilaiImport' => ['f' => 'kp_nilaiImport', 'write' => 1, 'perm' => [['kpi', 2]]],
    'kpiNilaiTpl' => ['f' => 'kp_nilaiTpl', 'perm' => [['kpi', 1]]],
    // ---- Toko online (admin) ----
    'onPesanan' => ['f' => 'on_list', 'perm' => [['online', 1]]],
    'onStatus' => ['f' => 'on_status', 'write' => 1, 'perm' => [['online', 2]]],
    'onProduk' => ['f' => 'on_produk', 'perm' => [['online', 1]]],
    'onProdukSet' => ['f' => 'on_produkSet', 'write' => 1, 'perm' => [['online', 2]]],
    'onSetGet' => ['f' => 'on_setGet', 'perm' => [['online', 1]]],
    'onSet' => ['f' => 'on_setSave', 'write' => 1, 'perm' => [['online', 3]]],
    // ---- Layanan mandiri karyawan (mod 'saya') ----
    'sayaHome' => ['f' => 'sy_home', 'saya' => 1],
    'sayaAbsen' => ['f' => 'sy_absen', 'write' => 1, 'saya' => 1],
    'sayaRiwayat' => ['f' => 'sy_riwayat', 'saya' => 1],
    'sayaAjukan' => ['f' => 'sy_ajukan', 'write' => 1, 'saya' => 1],
    'sayaBatalAju' => ['f' => 'sy_batalAju', 'write' => 1, 'saya' => 1],
    'sayaSlip' => ['f' => 'sy_slip', 'saya' => 1],
    'sayaTim' => ['f' => 'sy_tim', 'saya' => 1],
    'sayaProses' => ['f' => 'sy_proses', 'write' => 1, 'saya' => 1],
    'sayaCekGanti' => ['f' => 'sy_cekGanti', 'saya' => 1],
    'sayaPerintah' => ['f' => 'sy_perintah', 'write' => 1, 'saya' => 1],
];

function t3_per(string $p): string { if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p)) throw new AppErr('Periode tidak valid (format YYYY-MM)'); return $p; }
function t3_perAwal(string $p): string { return $p . '-01'; }
function t3_perAkhir(string $p): string { return date('Y-m-t', strtotime($p . '-01')); }
function t3_kas(string $metode, string $akun = ''): string
{
    if ($akun !== '' && akun_exists($akun)) return $akun;
    $s = settings_all();
    return $metode === 'Bank' ? ($s['PosAkunBank'] ?: '1102') : ($s['AkunKas'] ?: '1101');
}
/** Simpan foto dataURL ke uploads/<sub>/ ; mengembalikan URL relatif. */
function t3_foto(string $dataUrl, string $sub, string $prefix, int $maxKb = 1500): string
{
    if (!preg_match('#^data:image/(jpeg|jpg|png|webp);base64,(.+)$#i', $dataUrl, $m)) throw new AppErr('Format foto tidak valid');
    $bin = base64_decode($m[2], true);
    if ($bin === false || strlen($bin) > $maxKb * 1024) throw new AppErr('Foto terlalu besar');
    $info = @getimagesizefromstring($bin);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new AppErr('File bukan gambar yang valid');
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$info[2]];
    $dir = upload_dir($sub);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) throw new AppErr('Folder uploads/' . $sub . ' tidak dapat dibuat. Periksa izin folder.');
    if (!is_file($dir . '/index.html')) @file_put_contents($dir . '/index.html', '');
    $name = preg_replace('/[^A-Za-z0-9]/', '', $prefix) . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (@file_put_contents($dir . '/' . $name, $bin) === false) throw new AppErr('Gagal menyimpan foto. Pastikan folder uploads dapat ditulis.');
    return upload_url($sub) . '/' . $name;
}

/* ======================================================================
 *  KARTU STOK — mutasi masuk/keluar per produk (per lokasi atau total)
 *  Saldo dihitung mundur dari stok saat ini sehingga selalu cocok.
 * ==================================================================== */
function ks_kartu(array $d): array
{
    $p = row('SELECT id, sku, barcode, nama, satuan, stok, harga_beli FROM produk WHERE id = ?', [(string)($d['pid'] ?? '')]);
    if (!$p) throw new AppErr('Produk tidak ditemukan');
    $lok = (string)($d['lokasi'] ?? '');
    $L = lokasi_all();
    if ($lok !== '' && !isset($L[$lok])) $lok = '';
    $f = is_date($d['from'] ?? null) ? $d['from'] : add_days(today_s(), -30);
    $t = is_date($d['to'] ?? null) ? $d['to'] : today_s();
    if ($f > $t) throw new AppErr('Tanggal awal melebihi tanggal akhir');
    $a = $f . ' 00:00:00'; $b = $t . ' 23:59:59';
    $M = [];
    $add = function (string $tg, string $jenis, string $ref, string $ket, float $q, string $l, string $user = '') use (&$M, $lok, $L) {
        if ($lok !== '' && $l !== $lok) return;
        if (abs($q) < 1e-9) return;
        $M[] = ['t' => $tg, 'jenis' => $jenis, 'ref' => $ref, 'ket' => $ket, 'q' => $q, 'lok' => $L[$l]['nama'] ?? '', 'user' => $user];
    };
    foreach (rows('SELECT d.qty, d.no, t.tanggal, t.lokasi_id, t.status, t.batal_pada, t.kasir, t.pelanggan, t.sumber FROM transaksi_detail d JOIN transaksi t ON t.no = d.no WHERE d.pid = ? AND (t.tanggal >= ? OR t.batal_pada >= ?)', [$p['id'], $a, $a]) as $r) {
        if ($r['tanggal'] >= $a) $add($r['tanggal'], $r['sumber'] === 'online' ? 'Penjualan online' : 'Penjualan', $r['no'], $r['pelanggan'], -(float)$r['qty'], $r['lokasi_id'], $r['kasir']);
        if ($r['status'] === 'Batal' && $r['batal_pada']) $add($r['batal_pada'], 'Batal penjualan', $r['no'], 'Stok kembali', (float)$r['qty'], $r['lokasi_id'], $r['kasir']);
    }
    foreach (rows('SELECT tanggal, no, qty, lokasi_id, supplier, user FROM stok_masuk WHERE pid = ? AND tanggal >= ?', [$p['id'], $a]) as $r)
        $add($r['tanggal'], 'Stok masuk', $r['no'], $r['supplier'], (float)$r['qty'], $r['lokasi_id'], $r['user']);
    foreach (rows('SELECT tanggal, selisih, catatan, lokasi_id, user FROM opname WHERE pid = ? AND tanggal >= ?', [$p['id'], $a]) as $r) {
        $kurangMut = strpos((string)$r['catatan'], 'Kurang terima mutasi') === 0;
        if ($lok !== '' && $kurangMut) continue;                          // hanya mengurangi total, bukan stok lokasi
        $c = (string)$r['catatan'];
        $jenis = stripos($c, 'retur') !== false ? 'Retur pemasok' : (stripos($c, 'awal') !== false ? 'Stok awal' : ($kurangMut ? 'Selisih mutasi' : 'Penyesuaian / opname'));
        $add($r['tanggal'], $jenis, '', $c, (float)$r['selisih'], $r['lokasi_id'], $r['user']);
    }
    if ($lok !== '') {
        foreach (rows('SELECT i.qty_kirim, i.qty_terima, m.no, m.dari, m.ke, m.status, m.dikirim_pada, m.diterima_pada, m.batal_pada, m.dikirim_oleh, m.diterima_oleh FROM mutasi_item i JOIN mutasi m ON m.no = i.no WHERE i.pid = ? AND (m.dari = ? OR m.ke = ?)', [$p['id'], $lok, $lok]) as $r) {
            if ($r['dikirim_pada'] && $r['dikirim_pada'] >= $a && $r['dari'] === $lok) $add($r['dikirim_pada'], 'Mutasi keluar', $r['no'], 'Ke ' . ($L[$r['ke']]['nama'] ?? ''), -(float)$r['qty_kirim'], $lok, $r['dikirim_oleh']);
            if ($r['diterima_pada'] && $r['diterima_pada'] >= $a && $r['ke'] === $lok) $add($r['diterima_pada'], 'Mutasi masuk', $r['no'], 'Dari ' . ($L[$r['dari']]['nama'] ?? ''), (float)$r['qty_terima'], $lok, $r['diterima_oleh']);
            if ($r['status'] === 'Batal' && $r['batal_pada'] && $r['dikirim_pada'] && $r['batal_pada'] >= $a && $r['dari'] === $lok) $add($r['batal_pada'], 'Mutasi dibatalkan', $r['no'], 'Stok kembali', (float)$r['qty_kirim'], $lok);
        }
    }
    usort($M, fn($x, $y) => strcmp($x['t'], $y['t']) ?: ($y['q'] <=> $x['q']));
    $now = $lok !== '' ? (float)(val('SELECT qty FROM stok_lokasi WHERE pid = ? AND lokasi_id = ?', [$p['id'], $lok]) ?? 0) : (float)$p['stok'];
    $sumAll = 0.0; foreach ($M as $m) $sumAll += $m['q'];
    $awal = r3($now - $sumAll);
    $saldo = $awal; $rowsOut = []; $in = 0.0; $out = 0.0;
    foreach ($M as $m) {
        $saldo = r3($saldo + $m['q']);
        if ($m['t'] > $b) continue;
        if ($m['q'] > 0) $in += $m['q']; else $out += -$m['q'];
        $rowsOut[] = ['t' => $m['t'], 'jenis' => $m['jenis'], 'ref' => $m['ref'], 'ket' => $m['ket'], 'masuk' => $m['q'] > 0 ? jn($m['q']) : 0, 'keluar' => $m['q'] < 0 ? jn(-$m['q']) : 0, 'saldo' => jn($saldo), 'lok' => $m['lok'], 'user' => $m['user']];
    }
    return ['produk' => ['id' => $p['id'], 'sku' => (string)$p['sku'], 'barcode' => (string)$p['barcode'], 'nama' => $p['nama'], 'satuan' => $p['satuan']],
        'lokasi' => $lok, 'lokasiNama' => $lok ? $L[$lok]['nama'] : 'Semua lokasi', 'from' => $f, 'to' => $t,
        'awal' => jn($awal), 'masuk' => jn(r3($in)), 'keluar' => jn(r3($out)), 'akhir' => jn(r3($awal + $in - $out)), 'rows' => $rowsOut];
}

/* ======================================================================
 *  KARTU MEMBER — data cetak
 * ==================================================================== */
function mc_list(array $d): array
{
    $ids = array_values(array_filter(array_map('strval', (array)($d['ids'] ?? []))));
    $s = settings_all();
    $w = $ids ? 'id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')' : 'aktif = 1';
    $M = rows("SELECT id, kode, nama, hp, poin, dibuat FROM member WHERE $w ORDER BY kode LIMIT 500", $ids);
    return ['toko' => ['nama' => $s['namaToko'] ?? '', 'alamat' => $s['alamat'] ?? '', 'telepon' => $s['telepon'] ?? ''],
        'member' => array_map(fn($m) => ['id' => $m['id'], 'kode' => $m['kode'], 'nama' => $m['nama'], 'hp' => $m['hp'], 'poin' => (int)$m['poin'], 'sejak' => substr((string)$m['dibuat'], 0, 10)], $M)];
}

/* ======================================================================
 *  INVENTARIS ASET — penyusutan garis lurus bulanan
 *   Pembelian   : Dr Aset tetap            Cr Kas / Bank / Modal (aset yang sudah dimiliki)
 *   Penyusutan  : Dr Beban penyusutan      Cr Akumulasi penyusutan
 *   Perbaikan   : Dr Beban perbaikan       Cr Kas / Bank
 *   Pelepasan   : Dr Kas (hasil jual) + Dr Akumulasi + Dr Rugi / Cr Laba     Cr Aset tetap
 * ==================================================================== */
const ASET_KONDISI = ['Baik', 'Perlu perbaikan', 'Rusak', 'Hilang'];
const ASET_KATEGORI = ['Mesin & peralatan kasir', 'Rak & etalase', 'Elektronik', 'Furnitur', 'Kendaraan', 'Peralatan gudang', 'Bangunan & renovasi', 'Lainnya'];
function as_akunAset(): string { return akun_exists('1504') ? '1504' : '1501'; }
function as_akum(array $a): float { return r2((float)$a['susut_awal'] + (float)val('SELECT COALESCE(SUM(nilai),0) FROM aset_susut WHERE aset_id = ?', [$a['id']])); }
function as_bulanan(array $a): float { $base = max(0, (float)$a['harga'] - (float)$a['nilai_sisa']); return $a['umur_bulan'] > 0 ? $base / (int)$a['umur_bulan'] : 0; }
/** Periode pertama penyusutan = bulan pembelian. */
function as_mulai(array $a): string { return substr((string)$a['tgl_beli'], 0, 7); }
function as_out(array $a, ?float $akum = null): array
{
    $akum = $akum ?? as_akum($a);
    $L = lokasi_all();
    return ['id' => $a['id'], 'kode' => $a['kode'], 'nama' => $a['nama'], 'kategori' => $a['kategori'], 'lokasi' => $a['lokasi_id'], 'lokasiNama' => $L[$a['lokasi_id']]['nama'] ?? '-',
        'pj' => $a['pj'], 'merek' => $a['merek'], 'noSeri' => $a['no_seri'], 'tglBeli' => $a['tgl_beli'], 'harga' => jn($a['harga']), 'nilaiSisa' => jn($a['nilai_sisa']), 'umur' => (int)$a['umur_bulan'],
        'susutAwal' => jn($a['susut_awal']), 'akum' => jn($akum), 'buku' => jn(r2((float)$a['harga'] - $akum)), 'perBulan' => jn(r2(as_bulanan($a))),
        'kondisi' => $a['kondisi'], 'status' => $a['status'], 'sumber' => $a['sumber'], 'pemasok' => $a['pemasok'], 'catatan' => $a['catatan'], 'foto' => $a['foto'],
        'lepasTgl' => $a['lepas_tgl'] ?? '', 'lepasNilai' => jn($a['lepas_nilai']), 'lepasAlasan' => $a['lepas_alasan'],
        'persen' => (float)$a['harga'] > 0 ? round($akum / (float)$a['harga'] * 100, 1) : 0];
}
function as_meta(): array
{
    $last = (string)val('SELECT MAX(periode) FROM aset_susut');
    return ['kategori' => ASET_KATEGORI, 'kondisi' => ASET_KONDISI, 'lokasi' => array_values(array_map(fn($l) => ['id' => $l['id'], 'nama' => $l['nama']], lokasi_all())),
        'akunKas' => array_map(fn($r) => ['kode' => $r['kode'], 'nama' => $r['nama']], rows("SELECT kode, nama FROM akun WHERE subkategori = 'Kas & Bank' AND status = 'Aktif' ORDER BY kode")),
        'susutTerakhir' => $last, 'bulanIni' => substr(today_s(), 0, 7)];
}
function as_list(array $d): array
{
    $A = rows('SELECT * FROM aset ORDER BY status, kode');
    $S = []; foreach (rows('SELECT aset_id, SUM(nilai) s FROM aset_susut GROUP BY aset_id') as $r) $S[$r['aset_id']] = (float)$r['s'];
    $out = array_map(fn($a) => as_out($a, r2((float)$a['susut_awal'] + ($S[$a['id']] ?? 0))), $A);
    $act = array_filter($out, fn($x) => $x['status'] === 'Aktif');
    $sum = ['n' => count($act), 'harga' => 0, 'akum' => 0, 'buku' => 0, 'rusak' => 0, 'perbaikan' => 0];
    foreach ($act as $x) { $sum['harga'] += $x['harga']; $sum['akum'] += $x['akum']; $sum['buku'] += $x['buku']; if (in_array($x['kondisi'], ['Rusak', 'Hilang'], true)) $sum['rusak']++; if ($x['kondisi'] === 'Perlu perbaikan') $sum['perbaikan']++; }
    return ['rows' => $out, 'sum' => $sum, 'pending' => as_pending(substr(today_s(), 0, 7))['n']];
}
function as_get(array $d): array
{
    $a = row('SELECT * FROM aset WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$a) throw new AppErr('Aset tidak ditemukan');
    $o = as_out($a);
    $o['log'] = array_map(fn($r) => ['t' => $r['tanggal'], 'jenis' => $r['jenis'], 'kondisi' => $r['kondisi'], 'biaya' => jn($r['biaya']), 'ket' => $r['ket'], 'user' => $r['user']], rows('SELECT * FROM aset_log WHERE aset_id = ? ORDER BY tanggal DESC, id DESC', [$a['id']]));
    $o['susut'] = array_map(fn($r) => ['periode' => $r['periode'], 'nilai' => jn($r['nilai'])], rows('SELECT periode, nilai FROM aset_susut WHERE aset_id = ? ORDER BY periode', [$a['id']]));
    // jadwal penyusutan (proyeksi)
    $jad = []; $akum = (float)$a['susut_awal'] + array_sum(array_column($o['susut'], 'nilai')); $base = max(0, (float)$a['harga'] - (float)$a['nilai_sisa']);
    $per = as_mulai($a); $done = array_column($o['susut'], 'periode');
    $sisaBulan = 0;
    for ($i = 0; $i < (int)$a['umur_bulan'] && $akum < $base - 0.5 && $sisaBulan < 600; $i++) {
        if (!in_array($per, $done, true)) { $n = min(round(as_bulanan($a)), $base - $akum); $akum += $n; $jad[] = ['periode' => $per, 'nilai' => jn(r2($n)), 'buku' => jn(r2((float)$a['harga'] - $akum))]; }
        $per = date('Y-m', strtotime($per . '-01 +1 month')); $sisaBulan++;
    }
    $o['jadwal'] = array_slice($jad, 0, 12);
    $o['sisaBulan'] = count($jad);
    return $o;
}
function as_save(array $d, array $u): array
{
    $id = (string)($d['id'] ?? '');
    $old = $id ? row('SELECT * FROM aset WHERE id = ? FOR UPDATE', [$id]) : null;
    if ($id && !$old) throw new AppErr('Aset tidak ditemukan');
    if ($old && $old['status'] !== 'Aktif') throw new AppErr('Aset yang sudah dilepas tidak dapat diubah');
    $nama = str($d['nama'] ?? '', 150); if ($nama === '') throw new AppErr('Nama aset wajib diisi');
    $kat = str($d['kategori'] ?? 'Lainnya', 60) ?: 'Lainnya';
    $lok = (string)($d['lokasi'] ?? ''); if ($lok !== '' && !isset(lokasi_all()[$lok])) $lok = '';
    $kode = strtoupper(str($d['kode'] ?? '', 30));
    if ($kode === '') $kode = $old['kode'] ?? ('AST-' . str_pad((string)counter_next('ASET'), 4, '0', STR_PAD_LEFT));
    if (val('SELECT id FROM aset WHERE kode = ? AND id <> ?', [$kode, $id])) throw new AppErr('Kode aset ' . $kode . ' sudah dipakai');
    $base = [$nama, $kat, $lok, str($d['pj'] ?? '', 120), str($d['merek'] ?? '', 120), str($d['noSeri'] ?? '', 120), str($d['pemasok'] ?? '', 150), str($d['catatan'] ?? '')];
    $foto = $old['foto'] ?? '';
    if (!empty($d['fotoData'])) $foto = t3_foto((string)$d['fotoData'], 'aset', $kode);
    $lockedFin = $old && ((int)val('SELECT COUNT(*) FROM aset_susut WHERE aset_id = ?', [$id]) > 0);
    if ($old) {
        $tgl = (string)($d['tglBeli'] ?? $old['tgl_beli']); $harga = r2(num($d['harga'] ?? $old['harga'])); $sisa = r2(num($d['nilaiSisa'] ?? $old['nilai_sisa'])); $umur = (int)num($d['umur'] ?? $old['umur_bulan']);
        if ($lockedFin && ($tgl !== $old['tgl_beli'] || abs($harga - (float)$old['harga']) > 0.004 || abs($sisa - (float)$old['nilai_sisa']) > 0.004 || $umur !== (int)$old['umur_bulan']))
            throw new AppErr('Harga, tanggal, nilai sisa, dan umur tidak dapat diubah karena penyusutan sudah dibukukan.');
        if (!$lockedFin) {
            if (!is_date($tgl)) throw new AppErr('Tanggal perolehan tidak valid');
            if ($harga <= 0) throw new AppErr('Harga perolehan harus lebih dari 0');
            if ($umur < 1 || $umur > 600) throw new AppErr('Umur manfaat 1–600 bulan');
            if ($sisa < 0 || $sisa >= $harga) throw new AppErr('Nilai sisa harus lebih kecil dari harga perolehan');
            if (abs($harga - (float)$old['harga']) > 0.004 || $tgl !== $old['tgl_beli']) {
                // perbarui jurnal perolehan
                q('DELETE FROM jurnal WHERE ref = ?', ['AST:BELI:' . $id]);
                as_jurnalBeli(array_merge($old, ['harga' => $harga, 'tgl_beli' => $tgl, 'nama' => $nama, 'kode' => $kode]), $u);
            }
            q('UPDATE aset SET tgl_beli = ?, harga = ?, nilai_sisa = ?, umur_bulan = ? WHERE id = ?', [$tgl, $harga, $sisa, $umur, $id]);
        }
        q('UPDATE aset SET kode = ?, nama = ?, kategori = ?, lokasi_id = ?, pj = ?, merek = ?, no_seri = ?, pemasok = ?, catatan = ?, foto = ? WHERE id = ?', array_merge([$kode], $base, [$foto, $id]));
        if ($lok !== $old['lokasi_id']) q('INSERT INTO aset_log (aset_id, tanggal, jenis, kondisi, ket, user) VALUES (?,?,?,?,?,?)', [$id, now_s(), 'Pindah', $old['kondisi'], 'Ke ' . (lokasi_all()[$lok]['nama'] ?? '-'), $u['username']]);
        log_act($u, 'Ubah', 'Aset', $kode . ' ' . $nama);
        return as_get(['id' => $id]);
    }
    $tgl = (string)($d['tglBeli'] ?? ''); if (!is_date($tgl)) throw new AppErr('Tanggal perolehan tidak valid');
    if ($tgl > today_s()) throw new AppErr('Tanggal perolehan tidak boleh di masa depan');
    $harga = r2(num($d['harga'] ?? 0)); if ($harga <= 0) throw new AppErr('Harga perolehan harus lebih dari 0');
    $sisa = r2(max(0, num($d['nilaiSisa'] ?? 0))); if ($sisa >= $harga) throw new AppErr('Nilai sisa harus lebih kecil dari harga perolehan');
    $umur = (int)num($d['umur'] ?? 48); if ($umur < 1 || $umur > 600) throw new AppErr('Umur manfaat 1–600 bulan');
    $sumber = in_array($d['sumber'] ?? '', ['Kas', 'Bank', 'Modal'], true) ? $d['sumber'] : 'Kas';
    $susutAwal = $sumber === 'Modal' ? r2(max(0, num($d['susutAwal'] ?? 0))) : 0.0;
    if ($susutAwal > $harga - $sisa) throw new AppErr('Akumulasi penyusutan awal melebihi nilai yang dapat disusutkan');
    $kondisi = in_array($d['kondisi'] ?? '', ASET_KONDISI, true) ? $d['kondisi'] : 'Baik';
    $akunKas = $sumber === 'Modal' ? '' : t3_kas($sumber, (string)($d['akunKas'] ?? ''));
    assert_open_period($tgl, 'Perolehan aset');
    $id = uid('AS');
    q('INSERT INTO aset (id, kode, nama, kategori, lokasi_id, pj, merek, no_seri, pemasok, catatan, foto, tgl_beli, harga, nilai_sisa, umur_bulan, susut_awal, kondisi, status, sumber, akun_kas, akun_aset, dibuat, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array_merge([$id, $kode], $base, [$foto, $tgl, $harga, $sisa, $umur, $susutAwal, $kondisi, 'Aktif', $sumber, $akunKas, as_akunAset(), now_s(), $u['username']]));
    $a = row('SELECT * FROM aset WHERE id = ?', [$id]);
    as_jurnalBeli($a, $u);
    q('INSERT INTO aset_log (aset_id, tanggal, jenis, kondisi, biaya, ket, user) VALUES (?,?,?,?,?,?,?)', [$id, now_s(), 'Dicatat', $kondisi, $harga, $sumber === 'Modal' ? 'Aset yang sudah dimiliki (modal)' : 'Dibeli tunai (' . $sumber . ')', $u['username']]);
    log_act($u, 'Tambah', 'Aset', $kode . ' ' . $nama . ' ' . fmt_num($harga));
    return as_get(['id' => $id]);
}
function as_jurnalBeli(array $a, array $u): void
{
    $ket = 'Perolehan aset ' . $a['kode'] . ' - ' . $a['nama'];
    $akun = $a['akun_aset'] ?: as_akunAset();
    if ($a['sumber'] === 'Modal') {
        $sa = (float)$a['susut_awal'];
        journal_write($a['tgl_beli'], $a['kode'], 'Aset Tetap', [[$akun, $ket, $a['harga'], 0], ['1503', 'Akumulasi penyusutan s.d. dicatat ' . $a['kode'], 0, $sa], [pos_acc('PosAkunStokAwal'), $ket . ' (aset yang sudah dimiliki)', 0, (float)$a['harga'] - $sa]], '', 'AST:BELI:' . $a['id'], $u);
    } else {
        journal_write($a['tgl_beli'], $a['kode'], 'Aset Tetap', [[$akun, $ket, $a['harga'], 0], [$a['akun_kas'] ?: t3_kas($a['sumber']), $ket, 0, $a['harga']]], '', 'AST:BELI:' . $a['id'], $u);
    }
}
function as_kondisi(array $d, array $u): array
{
    $a = row('SELECT * FROM aset WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$a || $a['status'] !== 'Aktif') throw new AppErr('Aset tidak ditemukan atau sudah dilepas');
    $k = (string)($d['kondisi'] ?? ''); if (!in_array($k, ASET_KONDISI, true)) throw new AppErr('Kondisi tidak valid');
    $ket = str($d['ket'] ?? '', 255);
    q('UPDATE aset SET kondisi = ? WHERE id = ?', [$k, $a['id']]);
    q('INSERT INTO aset_log (aset_id, tanggal, jenis, kondisi, ket, user) VALUES (?,?,?,?,?,?)', [$a['id'], now_s(), 'Kondisi', $k, $ket ?: ($a['kondisi'] . ' → ' . $k), $u['username']]);
    log_act($u, 'Kondisi', 'Aset', $a['kode'] . ' ' . $k);
    return as_get(['id' => $a['id']]);
}
function as_perbaikan(array $d, array $u): array
{
    $a = row('SELECT * FROM aset WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$a || $a['status'] !== 'Aktif') throw new AppErr('Aset tidak ditemukan atau sudah dilepas');
    $biaya = r2(num($d['biaya'] ?? 0)); $ket = str($d['ket'] ?? '', 255);
    if ($ket === '') throw new AppErr('Isi keterangan perbaikan (mis. ganti printer struk, servis AC)');
    $tgl = is_date($d['tanggal'] ?? null) ? $d['tanggal'] : today_s();
    $k = in_array($d['kondisi'] ?? '', ASET_KONDISI, true) ? $d['kondisi'] : 'Baik';
    if ($biaya > 0) {
        assert_open_period($tgl, 'Biaya perbaikan');
        $kas = t3_kas((string)($d['metode'] ?? 'Kas'), (string)($d['akunKas'] ?? ''));
        $no = 'PRB-' . $a['kode'] . '-' . date('ymdHis');
        journal_write($tgl, $no, 'Perbaikan Aset', [[akun_exists('6110') ? '6110' : '6199', 'Perbaikan ' . $a['kode'] . ' ' . $a['nama'] . ': ' . $ket, $biaya, 0], [$kas, 'Perbaikan ' . $a['kode'], 0, $biaya]], '', 'AST:PRB:' . $a['id'] . ':' . uid('', 6), $u);
    }
    q('UPDATE aset SET kondisi = ? WHERE id = ?', [$k, $a['id']]);
    q('INSERT INTO aset_log (aset_id, tanggal, jenis, kondisi, biaya, ket, user) VALUES (?,?,?,?,?,?,?)', [$a['id'], $tgl . ' ' . date('H:i:s'), 'Perbaikan', $k, $biaya, $ket, $u['username']]);
    log_act($u, 'Perbaikan', 'Aset', $a['kode'] . ' ' . fmt_num($biaya));
    return as_get(['id' => $a['id']]);
}
function as_lepas(array $d, array $u): array
{
    $a = row('SELECT * FROM aset WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$a || $a['status'] !== 'Aktif') throw new AppErr('Aset tidak ditemukan atau sudah dilepas');
    $tgl = is_date($d['tanggal'] ?? null) ? $d['tanggal'] : today_s();
    if ($tgl < $a['tgl_beli']) throw new AppErr('Tanggal pelepasan sebelum tanggal perolehan');
    assert_open_period($tgl, 'Pelepasan aset');
    $alasan = str($d['alasan'] ?? '', 255); if ($alasan === '') throw new AppErr('Isi alasan (dijual, rusak berat dibuang, hilang, dll.)');
    $nilai = r2(max(0, num($d['nilai'] ?? 0)));
    $akum = as_akum($a); $buku = r2((float)$a['harga'] - $akum);
    $selisih = r2($nilai - $buku);
    $ket = 'Pelepasan aset ' . $a['kode'] . ' - ' . $a['nama'] . ' (' . $alasan . ')';
    $L = [];
    if ($nilai > 0) $L[] = [t3_kas((string)($d['metode'] ?? 'Kas'), (string)($d['akunKas'] ?? '')), $ket . ' - hasil penjualan', $nilai, 0];
    $L[] = ['1503', $ket, $akum, 0];
    $L[] = [$a['akun_aset'] ?: as_akunAset(), $ket, 0, (float)$a['harga']];
    if ($selisih > 0) $L[] = [akun_exists('4104') ? '4104' : '4103', 'Laba pelepasan ' . $a['kode'], 0, $selisih];
    if ($selisih < 0) $L[] = [akun_exists('6111') ? '6111' : '6199', 'Rugi pelepasan ' . $a['kode'], -$selisih, 0];
    journal_write($tgl, 'LPS-' . $a['kode'], 'Pelepasan Aset', $L, '', 'AST:LPS:' . $a['id'], $u);
    q("UPDATE aset SET status = 'Dilepas', lepas_tgl = ?, lepas_nilai = ?, lepas_alasan = ? WHERE id = ?", [$tgl, $nilai, $alasan, $a['id']]);
    q('INSERT INTO aset_log (aset_id, tanggal, jenis, kondisi, biaya, ket, user) VALUES (?,?,?,?,?,?,?)', [$a['id'], $tgl . ' ' . date('H:i:s'), 'Pelepasan', $a['kondisi'], $nilai, $alasan . ' · nilai buku ' . fmt_num($buku) . ($selisih ? ($selisih > 0 ? ' · laba ' : ' · rugi ') . fmt_num(abs($selisih)) : ''), $u['username']]);
    log_act($u, 'Lepas', 'Aset', $a['kode'] . ' ' . $alasan);
    return as_get(['id' => $a['id']]);
}
function as_hapus(array $d, array $u): array
{
    $a = row('SELECT * FROM aset WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$a) throw new AppErr('Aset tidak ditemukan');
    if ((int)val('SELECT COUNT(*) FROM aset_susut WHERE aset_id = ?', [$a['id']]) || $a['status'] !== 'Aktif')
        throw new AppErr('Aset sudah disusutkan / dilepas sehingga tidak dapat dihapus. Gunakan "Lepas aset".');
    assert_open_period($a['tgl_beli'], 'Penghapusan aset');
    q('DELETE FROM jurnal WHERE ref LIKE ?', ['AST:%:' . $a['id'] . '%']);
    q('DELETE FROM aset_log WHERE aset_id = ?', [$a['id']]);
    q('DELETE FROM aset WHERE id = ?', [$a['id']]);
    log_act($u, 'Hapus', 'Aset', $a['kode'] . ' ' . $a['nama']);
    return ['ok' => 1];
}
/** Penyusutan yang belum dibukukan sampai periode tertentu. */
function as_pending(string $sd): array
{
    $out = []; $tot = 0.0;
    $done = []; foreach (rows('SELECT aset_id, periode, nilai FROM aset_susut') as $r) { $done[$r['aset_id']][$r['periode']] = (float)$r['nilai']; }
    foreach (rows("SELECT * FROM aset WHERE status = 'Aktif'") as $a) {
        $base = max(0, (float)$a['harga'] - (float)$a['nilai_sisa']);
        $akum = (float)$a['susut_awal'] + array_sum($done[$a['id']] ?? []);
        $per = as_mulai($a); $mon = as_bulanan($a);
        $end = $sd;
        $guard = 0;
        // aset "sudah dimiliki": penyusutan awal menutup bulan-bulan pertama
        $skip = (float)$a['susut_awal'] > 0 && $mon > 0 ? (int)floor((float)$a['susut_awal'] / $mon + 0.01) : 0;
        if ($skip) $per = date('Y-m', strtotime($per . "-01 +$skip month"));
        while ($per <= $end && $akum < $base - 0.5 && $guard++ < 600) {
            if (!isset($done[$a['id']][$per])) {
                $n = r2(min(round($mon), $base - $akum));
                if ($n > 0) { $out[] = ['aset' => $a, 'periode' => $per, 'nilai' => $n]; $akum += $n; $tot += $n; }
            }
            $per = date('Y-m', strtotime($per . '-01 +1 month'));
        }
    }
    return ['n' => count($out), 'total' => r2($tot), 'items' => $out];
}
function as_susutCek(array $d): array
{
    $sd = t3_per((string)($d['sampai'] ?? substr(today_s(), 0, 7)));
    $p = as_pending($sd); $by = [];
    foreach ($p['items'] as $x) { $k = $x['periode']; $by[$k] = ($by[$k] ?? 0) + $x['nilai']; }
    ksort($by);
    return ['sampai' => $sd, 'n' => $p['n'], 'total' => $p['total'], 'periode' => array_map(fn($k, $v) => ['periode' => $k, 'nilai' => jn(r2($v))], array_keys($by), $by),
        'riwayat' => array_map(fn($r) => ['periode' => $r['periode'], 'nilai' => jn($r['n']), 'aset' => (int)$r['c']], rows('SELECT periode, SUM(nilai) n, COUNT(*) c FROM aset_susut GROUP BY periode ORDER BY periode DESC LIMIT 36')),
        'bulanIni' => substr(today_s(), 0, 7)];
}
function as_susut(array $d, array $u): array
{
    $sd = t3_per((string)($d['sampai'] ?? substr(today_s(), 0, 7)));
    if ($sd > substr(today_s(), 0, 7)) throw new AppErr('Penyusutan tidak dapat dibukukan untuk bulan yang belum berjalan');
    $p = as_pending($sd); if (!$p['n']) throw new AppErr('Tidak ada penyusutan yang perlu dibukukan');
    $by = []; foreach ($p['items'] as $x) $by[$x['periode']][] = $x;
    ksort($by); $n = 0;
    foreach ($by as $per => $items) {
        $tgl = t3_perAkhir($per); if ($tgl > today_s()) $tgl = today_s();
        assert_open_period($tgl, 'Penyusutan ' . $per);
        $tot = array_sum(array_column($items, 'nilai'));
        $eid = journal_write($tgl, 'SST-' . str_replace('-', '', $per), 'Penyusutan Aset', [['6107', 'Beban penyusutan aset ' . $per . ' (' . count($items) . ' aset)', $tot, 0], ['1503', 'Akumulasi penyusutan ' . $per, 0, $tot]], '', 'AST:SST:' . $per . ':' . uid('', 4), $u);
        insert_many('aset_susut', ['aset_id', 'periode', 'nilai', 'entri_id'], array_map(fn($x) => [$x['aset']['id'], $per, $x['nilai'], $eid], $items));
        $n += count($items);
    }
    log_act($u, 'Penyusutan', 'Aset', 's.d. ' . $sd . ' · ' . fmt_num($p['total']));
    return ['n' => $n, 'total' => $p['total'], 'bulan' => count($by)];
}

/* ======================================================================
 *  SDM & PENGGAJIAN
 *   Kasbon      : Dr Piutang karyawan (1202)   Cr Kas / Bank
 *   Final gaji  : Dr Beban gaji (6101)         Cr Hutang gaji (2102) · Cr Piutang karyawan (cicilan kasbon)
 *   Bayar gaji  : Dr Hutang gaji (2102)        Cr Kas / Bank
 * ==================================================================== */
const SD_STATUS = ['Hadir', 'Izin', 'Sakit', 'Cuti', 'Alpa', 'Libur'];
function sd_set_all(): array
{
    $s = settings_all();
    return ['hariKerja' => max(1, (int)($s['SdmHariKerja'] ?? 26)), 'izinPotong' => ($s['SdmIzinPotong'] ?? '1') === '1', 'radius' => max(20, (int)($s['SdmRadius'] ?? 100)),
        'tanpaGps' => ($s['SdmTanpaGps'] ?? '0') === '1', 'mulai' => is_date($s['SdmMulai'] ?? '') ? $s['SdmMulai'] : '',
        // v9
        'aksesJam' => ($s['SdmAksesJam'] ?? '0') === '1', 'aksesSebelum' => (int)min(240, max(0, (int)($s['SdmAksesSebelum'] ?? 30))), 'aksesSesudah' => (int)min(480, max(0, (int)($s['SdmAksesSesudah'] ?? 60))),
        'aksesAbsen' => ($s['SdmAksesAbsen'] ?? '0') === '1', 'aksesBebas' => array_values(array_filter((array)(json_decode((string)($s['SdmAksesBebas'] ?? '["JPEMILIK"]'), true) ?: []), 'is_string')),
        'hpAbsen' => ($s['HpAbsenSaja'] ?? '1') === '1', 'hpBebas' => hp_bebas_list(),
        'lemburAktual' => ($s['SdmLemburAktual'] ?? '1') === '1', 'gantiBayar' => ($s['SdmGantiBayar'] ?? '1') === '1', 'notifWa' => ($s['SdmNotifWa'] ?? '1') === '1'];
}
function sd_shifts(): array { $o = []; foreach (rows('SELECT * FROM shift ORDER BY masuk') as $r) $o[$r['id']] = $r; return $o; }
function sd_kryRow(string $id, bool $lock = false): array
{
    $k = row('SELECT * FROM karyawan WHERE id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
    if (!$k) throw new AppErr('Karyawan tidak ditemukan');
    return $k;
}
function sd_namaMap(): array { static $m = null; if ($m === null) { $m = []; foreach (rows('SELECT id, nama FROM karyawan') as $r) $m[$r['id']] = $r['nama']; } return $m; }
function sd_hm(string $dt): int { return (int)substr($dt, 0, 2) * 60 + (int)substr($dt, 3, 2); }
/**
 * Status harian banyak karyawan dalam rentang tanggal.
 * Hasil: [kid][Y-m-d] = ['s' => shift|null, 'a' => absensi|null, 'p' => pengajuan|null, 'st' => status tampilan]
 */
function sd_days(array $K, string $from, string $to): array
{
    if (!$K) return [];
    $ids = array_column($K, 'id'); $ph = implode(',', array_fill(0, count($ids), '?'));
    $SH = sd_shifts();
    $J = []; $G = []; foreach (rows("SELECT * FROM jadwal WHERE karyawan_id IN ($ph) AND tanggal BETWEEN ? AND ?", array_merge($ids, [$from, $to])) as $r) { $J[$r['karyawan_id']][$r['tanggal']] = $r['shift_id']; if ($r['ganti_id'] !== '') $G[$r['karyawan_id']][$r['tanggal']] = $r['ganti_id']; }
    $GN = $G ? sd_namaMap() : [];
    $A = []; foreach (rows("SELECT * FROM absensi WHERE karyawan_id IN ($ph) AND tanggal BETWEEN ? AND ?", array_merge($ids, [$from, $to])) as $r) $A[$r['karyawan_id']][$r['tanggal']] = $r;
    $P = []; foreach (rows("SELECT * FROM pengajuan WHERE status = 'Disetujui' AND jenis <> 'Lembur' AND karyawan_id IN ($ph) AND mulai <= ? AND selesai >= ?", array_merge($ids, [$to, $from])) as $r) $P[$r['karyawan_id']][] = $r;
    $td = today_s(); $out = []; $mulai = sd_set_all()['mulai'];
    foreach ($K as $k) {
        for ($d = $from; $d <= $to; $d = add_days($d, 1)) {
            if ($k['tgl_masuk'] && $d < $k['tgl_masuk']) { $out[$k['id']][$d] = ['s' => null, 'a' => null, 'p' => null, 'st' => '-']; continue; }
            $pola = strpos((string)$k['hari_kerja'], date('N', strtotime($d))) !== false;
            if (isset($J[$k['id']]) && array_key_exists($d, $J[$k['id']])) $sid = $J[$k['id']][$d];
            else $sid = $pola ? (string)$k['shift_id'] : '';
            $g = $G[$k['id']][$d] ?? '';
            $s = $sid !== '' ? ($SH[$sid] ?? null) : null;
            $a = $A[$k['id']][$d] ?? null; $p = null;
            foreach ($P[$k['id']] ?? [] as $x) if ($x['mulai'] <= $d && $x['selesai'] >= $d) { $p = $x; break; }
            if (!$a && !$p && $mulai !== '' && $d < $mulai) { $out[$k['id']][$d] = ['s' => null, 'a' => null, 'p' => null, 'st' => '-']; continue; }
            if ($a) $st = $a['status'] === 'Hadir' ? ((int)$a['telat'] > 0 ? 'Telat' : 'Hadir') : $a['status'];
            elseif ($p) $st = $p['jenis'];
            elseif ($s) $st = $d < $td ? 'Alpa' : ($d === $td ? 'Belum absen' : 'Terjadwal');
            else $st = 'Libur';
            $out[$k['id']][$d] = ['s' => $s, 'a' => $a, 'p' => $p, 'st' => $st, 'g' => $g, 'gn' => $g !== '' ? ($GN[$g] ?? '') : '', 'pola' => $pola];
        }
    }
    return $out;
}
/** Rekap angka absensi satu karyawan dari hasil sd_days. */
function sd_rekap1(array $days, array $k, string $from, string $to): array
{
    $r = ['jadwal' => 0, 'hadir' => 0, 'telat' => 0, 'telatMenit' => 0, 'izin' => 0, 'sakit' => 0, 'cuti' => 0, 'alpa' => 0, 'lembur' => 0, 'libur' => 0, 'jadwalLewat' => 0, 'ganti' => 0, 'gantiExtra' => 0, 'lemburDisetujui' => 0];
    $td = today_s();
    foreach ($days as $d => $x) {
        if ($x['st'] === '-') continue;
        if ($x['s']) { $r['jadwal']++; if ($d <= $td) $r['jadwalLewat']++; }
        $a = $x['a'];
        if ($a) {
            if ($a['status'] === 'Hadir') { $r['hadir']++; if ((int)$a['telat'] > 0) { $r['telat']++; $r['telatMenit'] += (int)$a['telat']; }
                if (($x['g'] ?? '') !== '') { $r['ganti']++; if (empty($x['pola'])) $r['gantiExtra']++; } }
            elseif (isset($r[strtolower($a['status'])])) $r[strtolower($a['status'])]++;
            $r['lembur'] += (int)$a['lembur'];
        } elseif ($x['p'] && $x['s']) $r[strtolower($x['p']['jenis'])]++;
        elseif ($x['st'] === 'Alpa') $r['alpa']++;
        elseif ($x['st'] === 'Libur') $r['libur']++;
    }
    $akt = sd_set_all()['lemburAktual'];
    foreach (rows("SELECT * FROM pengajuan WHERE karyawan_id = ? AND jenis = 'Lembur' AND status = 'Disetujui' AND mulai BETWEEN ? AND ?", [$k['id'], $from, $to]) as $p) {
        $r['lemburDisetujui'] += (int)$p['menit'];
        $r['lembur'] += $akt && $p['jam_mulai'] !== '' ? sd_lemburAktual($p, $days[$p['mulai']]['a'] ?? null) : (int)$p['menit'];
    }
    return $r;
}
/** Rentang waktu [awal, akhir] (timestamp) dari tanggal + jam; jam akhir <= jam awal berarti lewat tengah malam. */
function sd_rentang(string $tgl, string $m, string $s): array
{
    $a = strtotime($tgl . ' ' . $m . ':00'); $b = strtotime($tgl . ' ' . $s . ':00');
    if ($b <= $a) $b += 86400;
    return [$a, $b];
}
/** Menit lembur yang benar-benar dijalani: irisan jam lembur yang disetujui dengan jam absen masuk–pulang. */
function sd_lemburAktual(array $p, ?array $a): int
{
    if (!$a || !$a['masuk'] || !$a['pulang']) return 0;
    [$x, $y] = sd_rentang($p['mulai'], $p['jam_mulai'], $p['jam_selesai']);
    $m = strtotime($a['masuk']); $pl = strtotime($a['pulang']);
    return (int)max(0, min((int)$p['menit'], intdiv(max(0, min($y, $pl) - max($x, $m)), 60)));
}
function sd_cutiTerpakai(array $k, string $tahun): int
{
    $n = 0;
    foreach (rows("SELECT mulai, selesai FROM pengajuan WHERE karyawan_id = ? AND jenis = 'Cuti' AND status = 'Disetujui' AND mulai <= ? AND selesai >= ?", [$k['id'], $tahun . '-12-31', $tahun . '-01-01']) as $p)
        for ($d = max($p['mulai'], $tahun . '-01-01'); $d <= min($p['selesai'], $tahun . '-12-31'); $d = add_days($d, 1))
            if (strpos((string)$k['hari_kerja'], date('N', strtotime($d))) !== false) $n++;
    return $n;
}
function sd_kasbonSisa(string $kid): float { return r2((float)val('SELECT COALESCE(SUM(sisa),0) FROM kasbon WHERE karyawan_id = ?', [$kid])); }
function sd_kryOut(array $k, array $U, array $SH, array $L): array
{
    return ['id' => $k['id'], 'kode' => $k['kode'], 'nama' => $k['nama'], 'userId' => $k['user_id'], 'user' => $U[$k['user_id']]['username'] ?? '', 'posisi' => $k['posisi'],
        'lokasi' => $k['lokasi_id'], 'lokasiNama' => $L[$k['lokasi_id']]['nama'] ?? '-', 'hp' => $k['hp'], 'email' => $k['email'], 'alamat' => $k['alamat'], 'tglMasuk' => $k['tgl_masuk'] ?? '',
        'status' => $k['status'], 'tipeGaji' => $k['tipe_gaji'], 'gajiPokok' => jn($k['gaji_pokok']), 'tunjangan' => jn($k['tunjangan']), 'uangMakan' => jn($k['uang_makan']), 'lemburJam' => jn($k['lembur_jam']),
        'dendaTelat' => jn($k['denda_telat']), 'jatahCuti' => (int)$k['jatah_cuti'], 'shift' => $k['shift_id'], 'shiftNama' => $SH[$k['shift_id']]['nama'] ?? '-', 'hariKerja' => $k['hari_kerja'], 'kpi' => $k['kpi_id'],
        'bank' => $k['bank'], 'rekening' => $k['rekening'], 'catatan' => $k['catatan'], 'atasan' => $k['atasan_id'] ?? '', 'atasanNama' => ($k['atasan_id'] ?? '') !== '' ? (sd_namaMap()[$k['atasan_id']] ?? '') : ''];
}
function sd_meta(): array
{
    $L = lokasi_all();
    return ['shift' => array_values(array_map(fn($s) => ['id' => $s['id'], 'nama' => $s['nama'], 'masuk' => $s['masuk'], 'pulang' => $s['pulang'], 'toleransi' => (int)$s['toleransi'], 'warna' => $s['warna'], 'aktif' => (bool)$s['aktif']], sd_shifts())),
        'lokasi' => array_values(array_map(fn($l) => ['id' => $l['id'], 'nama' => $l['nama'], 'lat' => $l['lat'] ?? null, 'lng' => $l['lng'] ?? null, 'radius' => (int)($l['radius'] ?? 100)], array_map(fn($l) => array_merge($l, row('SELECT lat, lng, radius FROM lokasi WHERE id = ?', [$l['id']]) ?: []), $L))),
        'users' => array_map(fn($r) => ['id' => $r['id'], 'nama' => $r['nama'], 'username' => $r['username']], rows("SELECT id, nama, username FROM users WHERE status = 'aktif' ORDER BY nama")),
        'kpi' => array_map(fn($r) => ['id' => $r['id'], 'nama' => $r['nama']], rows('SELECT id, nama FROM kpi_tpl WHERE aktif = 1 ORDER BY nama')),
        'akunKas' => array_map(fn($r) => ['kode' => $r['kode'], 'nama' => $r['nama']], rows("SELECT kode, nama FROM akun WHERE subkategori = 'Kas & Bank' AND status = 'Aktif' ORDER BY kode")),
        'set' => sd_set_all(), 'today' => today_s(), 'periode' => substr(today_s(), 0, 7),
        'jabatan' => array_map(fn($r) => ['id' => $r['id'], 'nama' => $r['nama'], 'admin' => (bool)$r['is_admin']], rows('SELECT id, nama, is_admin FROM jabatan ORDER BY is_admin DESC, nama')),
        'karyawan' => array_map(fn($r) => ['id' => $r['id'], 'kode' => $r['kode'], 'nama' => $r['nama'], 'posisi' => $r['posisi']], rows("SELECT id, kode, nama, posisi FROM karyawan WHERE status = 'Aktif' ORDER BY nama"))];
}
function sd_kryList(array $d): array
{
    $U = []; foreach (rows('SELECT id, username, nama FROM users') as $r) $U[$r['id']] = $r;
    $SH = sd_shifts(); $L = lokasi_all();
    $K = rows('SELECT * FROM karyawan ORDER BY status, nama');
    $act = array_values(array_filter($K, fn($k) => $k['status'] === 'Aktif'));
    $D = sd_days($act, today_s(), today_s()); $th = substr(today_s(), 0, 4);
    return array_map(function ($k) use ($U, $SH, $L, $D, $th) {
        $o = sd_kryOut($k, $U, $SH, $L);
        $x = $D[$k['id']][today_s()] ?? null;
        $o['hariIni'] = $x ? $x['st'] : '-';
        $o['masuk'] = $x && $x['a'] && $x['a']['masuk'] ? substr($x['a']['masuk'], 11, 5) : '';
        $o['sisaCuti'] = max(0, (int)$k['jatah_cuti'] - sd_cutiTerpakai($k, $th));
        $o['kasbon'] = sd_kasbonSisa($k['id']);
        return $o;
    }, $K);
}
function sd_krySave(array $d, array $u): array
{
    $id = (string)($d['id'] ?? '');
    $old = $id ? sd_kryRow($id, true) : null;
    $nama = str($d['nama'] ?? '', 120); if ($nama === '') throw new AppErr('Nama karyawan wajib diisi');
    $kode = strtoupper(str($d['kode'] ?? '', 20));
    if ($kode === '') $kode = $old['kode'] ?? ('K-' . str_pad((string)counter_next('KRY'), 4, '0', STR_PAD_LEFT));
    if (val('SELECT id FROM karyawan WHERE kode = ? AND id <> ?', [$kode, $id])) throw new AppErr('Kode karyawan ' . $kode . ' sudah dipakai');
    $uid = (string)($d['userId'] ?? '');
    if ($uid !== '') {
        if (!val('SELECT id FROM users WHERE id = ?', [$uid])) throw new AppErr('Akun login tidak ditemukan');
        $dup = row('SELECT nama FROM karyawan WHERE user_id = ? AND id <> ?', [$uid, $id]);
        if ($dup) throw new AppErr('Akun login itu sudah dipakai karyawan ' . $dup['nama']);
    }
    $lok = (string)($d['lokasi'] ?? ''); if ($lok !== '' && !isset(lokasi_all()[$lok])) $lok = '';
    $shift = (string)($d['shift'] ?? ''); if ($shift !== '' && !isset(sd_shifts()[$shift])) $shift = '';
    $hk = implode('', array_unique(str_split(preg_replace('/[^1-7]/', '', (string)($d['hariKerja'] ?? '123456')) ?: '')));
    $tipe = ($d['tipeGaji'] ?? '') === 'Harian' ? 'Harian' : 'Bulanan';
    $tm = (string)($d['tglMasuk'] ?? ''); $tm = is_date($tm) ? $tm : null;
    $kpi = (string)($d['kpi'] ?? ''); if ($kpi !== '' && !val('SELECT id FROM kpi_tpl WHERE id = ?', [$kpi])) $kpi = '';
    $n = fn($k) => r2(max(0, num($d[$k] ?? 0)));
    $ats = (string)($d['atasan'] ?? ($old['atasan_id'] ?? ''));
    if ($ats !== '') { if ($ats === $id) throw new AppErr('Atasan tidak boleh diri sendiri'); if (!val('SELECT id FROM karyawan WHERE id = ?', [$ats])) $ats = ''; elseif ($id !== '' && sd_atasanLoop($id, $ats)) throw new AppErr('Susunan atasan berputar (A atasan B, B atasan A). Periksa kembali.'); }
    $vals = [$kode, $nama, $uid, str($d['posisi'] ?? '', 80), $lok, str($d['hp'] ?? '', 30), str($d['email'] ?? '', 120), str($d['alamat'] ?? ''), $tm,
        ($d['status'] ?? 'Aktif') === 'Nonaktif' ? 'Nonaktif' : 'Aktif', $tipe, $n('gajiPokok'), $n('tunjangan'), $n('uangMakan'), $n('lemburJam'), $n('dendaTelat'),
        (int)min(60, max(0, num($d['jatahCuti'] ?? 12))), $shift, $hk, $kpi, str($d['bank'] ?? '', 60), str($d['rekening'] ?? '', 60), str($d['catatan'] ?? ''), $ats];
    $cols = ['kode', 'nama', 'user_id', 'posisi', 'lokasi_id', 'hp', 'email', 'alamat', 'tgl_masuk', 'status', 'tipe_gaji', 'gaji_pokok', 'tunjangan', 'uang_makan', 'lembur_jam', 'denda_telat', 'jatah_cuti', 'shift_id', 'hari_kerja', 'kpi_id', 'bank', 'rekening', 'catatan', 'atasan_id'];
    if ($old) q('UPDATE karyawan SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', array_merge($vals, [$id]));
    else { $id = uid('KR'); q('INSERT INTO karyawan (' . implode(', ', $cols) . ', id, dibuat) VALUES (' . implode(',', array_fill(0, count($cols) + 2, '?')) . ')', array_merge($vals, [$id, now_s()])); }
    log_act($u, $old ? 'Ubah' : 'Tambah', 'Karyawan', $kode . ' ' . $nama);
    return ['id' => $id];
}
function sd_kryDel(array $d, array $u): array
{
    $k = sd_kryRow((string)($d['id'] ?? ''), true);
    if ((int)val('SELECT COUNT(*) FROM absensi WHERE karyawan_id = ?', [$k['id']]) || (int)val('SELECT COUNT(*) FROM gaji WHERE karyawan_id = ?', [$k['id']]) || sd_kasbonSisa($k['id']) > 0)
        throw new AppErr('Karyawan sudah punya riwayat absen/gaji/kasbon. Ubah statusnya menjadi Nonaktif saja agar riwayat tetap tersimpan.');
    q('DELETE FROM jadwal WHERE karyawan_id = ?', [$k['id']]); q('DELETE FROM pengajuan WHERE karyawan_id = ?', [$k['id']]); q('DELETE FROM karyawan WHERE id = ?', [$k['id']]);
    log_act($u, 'Hapus', 'Karyawan', $k['kode'] . ' ' . $k['nama']);
    return ['ok' => 1];
}
function sd_shiftSave(array $d, array $u): array
{
    $nama = str($d['nama'] ?? '', 60); if ($nama === '') throw new AppErr('Nama shift wajib diisi');
    $okJ = fn($j) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$j);
    if (!$okJ($d['masuk'] ?? '') || !$okJ($d['pulang'] ?? '') || $d['masuk'] === $d['pulang']) throw new AppErr('Isi jam masuk dan jam pulang dengan benar (mis. 08:00 – 16:00)');
    $id = (string)($d['id'] ?? '');
    $vals = [$nama, $d['masuk'], $d['pulang'], (int)min(120, max(0, num($d['toleransi'] ?? 10))), preg_match('/^#[0-9a-fA-F]{6}$/', (string)($d['warna'] ?? '')) ? $d['warna'] : '#10A391', !isset($d['aktif']) || !empty($d['aktif']) ? 1 : 0];
    if ($id && val('SELECT id FROM shift WHERE id = ?', [$id])) q('UPDATE shift SET nama = ?, masuk = ?, pulang = ?, toleransi = ?, warna = ?, aktif = ? WHERE id = ?', array_merge($vals, [$id]));
    else { $id = uid('SH', 6); q('INSERT INTO shift (nama, masuk, pulang, toleransi, warna, aktif, id) VALUES (?,?,?,?,?,?,?)', array_merge($vals, [$id])); }
    log_act($u, 'Simpan', 'Shift', $nama);
    return sd_meta();
}
function sd_gpsSave(array $d, array $u): array
{
    $id = (string)($d['lokasi'] ?? ''); if (!isset(lokasi_all()[$id])) throw new AppErr('Lokasi tidak ditemukan');
    $lat = $d['lat'] === '' || $d['lat'] === null ? null : num($d['lat']); $lng = $d['lng'] === '' || $d['lng'] === null ? null : num($d['lng']);
    if (($lat === null) !== ($lng === null)) throw new AppErr('Isi lintang dan bujur sekaligus');
    if ($lat !== null && (abs($lat) > 90 || abs($lng) > 180)) throw new AppErr('Koordinat tidak valid');
    q('UPDATE lokasi SET lat = ?, lng = ?, radius = ? WHERE id = ?', [$lat, $lng, (int)min(5000, max(20, num($d['radius'] ?? 100))), $id]);
    if (isset($d['tanpaGps'])) setting_put(['SdmTanpaGps' => !empty($d['tanpaGps']) ? '1' : '0']);
    log_act($u, 'Titik absen', 'Lokasi', lokasi_all()[$id]['nama']);
    return sd_meta();
}
function sd_set(array $d, array $u): array
{
    $m = (string)($d['mulai'] ?? '');
    setting_put(['SdmHariKerja' => (string)(int)min(31, max(1, num($d['hariKerja'] ?? 26))), 'SdmIzinPotong' => !empty($d['izinPotong']) ? '1' : '0', 'SdmMulai' => is_date($m) ? $m : '']);
    if (array_key_exists('aksesJam', $d)) {
        $jab = array_column(rows('SELECT id FROM jabatan'), 'id');
        setting_put(['SdmAksesJam' => !empty($d['aksesJam']) ? '1' : '0', 'SdmAksesSebelum' => (string)(int)min(240, max(0, num($d['aksesSebelum'] ?? 30))),
            'SdmAksesSesudah' => (string)(int)min(480, max(0, num($d['aksesSesudah'] ?? 60))), 'SdmAksesAbsen' => !empty($d['aksesAbsen']) ? '1' : '0',
            'SdmAksesBebas' => json_encode(array_values(array_intersect($jab, array_map('strval', (array)($d['aksesBebas'] ?? []))))),
            'HpAbsenSaja' => !empty($d['hpAbsen']) ? '1' : '0', 'HpBebas' => json_encode(array_values(array_intersect($jab, array_map('strval', (array)($d['hpBebas'] ?? []))))),
            'SdmLemburAktual' => !empty($d['lemburAktual']) ? '1' : '0', 'SdmGantiBayar' => !empty($d['gantiBayar']) ? '1' : '0', 'SdmNotifWa' => !empty($d['notifWa']) ? '1' : '0']);
    }
    log_act($u, 'Ubah', 'SDM', 'Pengaturan penggajian');
    return sd_meta();
}
function sd_jadwalGet(array $d): array
{
    $from = is_date($d['from'] ?? null) ? $d['from'] : today_s();
    $n = (int)min(31, max(1, num($d['hari'] ?? 7)));
    $to = add_days($from, $n - 1);
    $K = rows("SELECT * FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
    $D = sd_days($K, $from, $to);
    $ex = []; foreach (rows('SELECT karyawan_id, tanggal FROM jadwal WHERE tanggal BETWEEN ? AND ?', [$from, $to]) as $r) $ex[$r['karyawan_id'] . $r['tanggal']] = 1;
    $out = [];
    foreach ($K as $k) {
        $cells = [];
        foreach ($D[$k['id']] as $t => $x) $cells[] = ['t' => $t, 's' => $x['s']['id'] ?? '', 'st' => $x['st'], 'x' => isset($ex[$k['id'] . $t]), 'g' => $x['gn'] ?? ''];
        $out[] = ['id' => $k['id'], 'kode' => $k['kode'], 'nama' => $k['nama'], 'posisi' => $k['posisi'], 'cells' => $cells];
    }
    return ['from' => $from, 'to' => $to, 'rows' => $out];
}
/** Kode isian sel jadwal dari Excel -> id shift | '' (libur) | '*' (ikuti pola) | null (tidak dikenal) */
function sd_shiftParse(string $v, array $SH): ?string
{
    $n = mb_strtolower(preg_replace('/\s+/', ' ', trim($v)));
    if (in_array($n, ['libur', 'l', 'off', 'off day', 'offday', '-', 'x', 'lib', 'free', 'tidak masuk'], true)) return '';
    if (in_array($n, ['pola', '*', 'ikuti pola', 'default', 'normal'], true)) return '*';
    $c = preg_replace('/[^a-z0-9]/', '', $n);
    foreach ($SH as $s) if ($c === preg_replace('/[^a-z0-9]/', '', mb_strtolower($s['nama'])) || $c === mb_strtolower($s['id'])) return $s['id'];
    if (preg_match('/^(\d{1,2})[.:](\d{2})\s*[-–s\/]+\s*(\d{1,2})[.:](\d{2})/', $n, $m)) {
        $a = sprintf('%02d:%s', $m[1], $m[2]); $b = sprintf('%02d:%s', $m[3], $m[4]);
        foreach ($SH as $s) if ($s['masuk'] === $a && $s['pulang'] === $b) return $s['id'];
    }
    // singkatan: huruf awal nama shift (P = Pagi, S = Siang) bila tidak ambigu
    $hit = array_values(array_filter($SH, fn($s) => !empty($s['aktif']) && $c !== '' && strpos(preg_replace('/[^a-z0-9]/', '', mb_strtolower($s['nama'])), $c) === 0));
    return count($hit) === 1 ? $hit[0]['id'] : null;
}
/** Import jadwal shift dari Excel (format tabel: baris = karyawan, kolom = tanggal). Sel kosong = tidak diubah. */
function sd_jadwalImport(array $d, array $u): array
{
    $items = (array)($d['items'] ?? []);
    if (!$items) throw new AppErr('Tidak ada baris jadwal untuk diimpor');
    if (count($items) > 500) throw new AppErr('Maksimal 500 karyawan per import');
    $SH = sd_shifts(); $K = rows('SELECT * FROM karyawan'); $byKode = []; $byNama = [];
    foreach ($K as $k) { if ($k['kode'] !== '') $byKode[mb_strtolower($k['kode'])] = $k; $byNama[mb_strtolower(trim($k['nama']))][] = $k; }
    $err = []; $plan = []; $min = null; $max = null; $sel = 0;
    foreach ($items as $i => $x) {
        $row = (int)($x['_row'] ?? $i + 2); $e = [];
        $kode = mb_strtolower(trim((string)($x['kode'] ?? ''))); $nama = mb_strtolower(trim((string)($x['nama'] ?? '')));
        $k = $kode !== '' ? ($byKode[$kode] ?? null) : null;
        if (!$k && $nama !== '') { $c = $byNama[$nama] ?? []; if (count($c) > 1) $e[] = 'nama "' . $x['nama'] . '" dipakai ' . count($c) . ' karyawan, isi kolom Kode'; else $k = $c[0] ?? null; }
        if (!$k && !$e) $e[] = 'karyawan "' . trim(($x['kode'] ?? '') . ' ' . ($x['nama'] ?? '')) . '" tidak ditemukan';
        foreach ((array)($x['cells'] ?? []) as $t => $v) {
            $v = trim((string)$v); if ($v === '') continue;
            if (!is_date((string)$t)) { $e[] = 'tanggal "' . $t . '" tidak valid'; continue; }
            $sid = sd_shiftParse($v, $SH);
            if ($sid === null) { $e[] = date('d/m', strtotime($t)) . ' shift "' . $v . '" belum ada'; continue; }
            if ($k) { $plan[] = [$k, $t, $sid]; $min = $min === null || $t < $min ? $t : $min; $max = $max === null || $t > $max ? $t : $max; }
        }
        if ($e) $err[] = 'baris ' . $row . ': ' . implode(', ', array_slice(array_unique($e), 0, 3));
    }
    if ($err) throw new AppErr(count($err) . ' baris bermasalah, belum ada yang disimpan — ' . implode(' · ', array_slice($err, 0, 4)) . (count($err) > 4 ? ' · …' : ''));
    if (!$plan) throw new AppErr('Tidak ada sel jadwal yang diisi');
    $kar = []; $ubah = 0; $pola = 0;
    foreach ($plan as [$k, $t, $sid]) {
        $kar[$k['id']] = 1;
        $polaSid = strpos((string)$k['hari_kerja'], date('N', strtotime($t))) !== false ? (string)$k['shift_id'] : '';
        if ($sid === '*' || $sid === $polaSid) { q('DELETE FROM jadwal WHERE karyawan_id = ? AND tanggal = ?', [$k['id'], $t]); $pola++; }
        else { q('INSERT INTO jadwal (karyawan_id, tanggal, shift_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id)', [$k['id'], $t, $sid]); $ubah++; }
    }
    log_act($u, 'Import Excel', 'Jadwal', count($kar) . ' karyawan, ' . $min . ' s.d. ' . $max);
    return ['karyawan' => count($kar), 'sel' => count($plan), 'khusus' => $ubah, 'pola' => $pola, 'from' => $min, 'to' => $max];
}
function sd_jadwalSave(array $d, array $u): array
{
    $n = 0;
    foreach ($d['cells'] ?? [] as $c) {
        $k = (string)($c['k'] ?? ''); $t = (string)($c['t'] ?? ''); $s = (string)($c['s'] ?? '');
        if (!is_date($t) || !val('SELECT id FROM karyawan WHERE id = ?', [$k])) continue;
        if ($s === '*') q('DELETE FROM jadwal WHERE karyawan_id = ? AND tanggal = ?', [$k, $t]);
        else {
            if ($s !== '' && !isset(sd_shifts()[$s])) continue;
            q('INSERT INTO jadwal (karyawan_id, tanggal, shift_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id)', [$k, $t, $s]);
        }
        $n++;
    }
    log_act($u, 'Ubah', 'Jadwal', $n . ' sel');
    return sd_jadwalGet($d);
}
function sd_absenOut(array $x, string $t): array
{
    $a = $x['a'];
    return ['t' => $t, 'st' => $x['st'], 'shift' => $x['s'] ? $x['s']['nama'] . ' ' . $x['s']['masuk'] . '–' . $x['s']['pulang'] : '', 'masuk' => $a && $a['masuk'] ? substr($a['masuk'], 11, 5) : '',
        'pulang' => $a && $a['pulang'] ? substr($a['pulang'], 11, 5) : '', 'telat' => (int)($a['telat'] ?? 0), 'cepat' => (int)($a['pulang_cepat'] ?? 0), 'lembur' => (int)($a['lembur'] ?? 0),
        'fotoMasuk' => $a['foto_masuk'] ?? '', 'fotoPulang' => $a['foto_pulang'] ?? '', 'jarakMasuk' => $a['jarak_masuk'] ?? null, 'jarakPulang' => $a['jarak_pulang'] ?? null,
        'sumber' => $a['sumber'] ?? '', 'catatan' => $a['catatan'] ?? ($x['p']['alasan'] ?? ''), 'ganti' => $x['gn'] ?? ''];
}
function sd_absenHari(array $d): array
{
    $t = is_date($d['tanggal'] ?? null) ? $d['tanggal'] : today_s();
    $K = rows("SELECT * FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
    $D = sd_days($K, $t, $t); $out = []; $sum = [];
    foreach ($K as $k) { $x = $D[$k['id']][$t]; $o = sd_absenOut($x, $t) + ['id' => $k['id'], 'nama' => $k['nama'], 'posisi' => $k['posisi']]; $out[] = $o; $sum[$x['st']] = ($sum[$x['st']] ?? 0) + 1; }
    return ['tanggal' => $t, 'rows' => $out, 'sum' => $sum];
}
function sd_absenRekap(array $d): array
{
    $p = t3_per((string)($d['periode'] ?? substr(today_s(), 0, 7)));
    $f = t3_perAwal($p); $t = t3_perAkhir($p);
    $K = rows("SELECT * FROM karyawan WHERE status = 'Aktif' OR id IN (SELECT karyawan_id FROM absensi WHERE tanggal BETWEEN ? AND ?) ORDER BY nama", [$f, $t]);
    $D = sd_days($K, $f, $t);
    $code = ['Hadir' => 'H', 'Telat' => 'T', 'Izin' => 'I', 'Sakit' => 'S', 'Cuti' => 'C', 'Alpa' => 'A', 'Libur' => '', 'Belum absen' => '?', 'Terjadwal' => '·', '-' => ''];
    $out = [];
    foreach ($K as $k) $out[] = ['id' => $k['id'], 'nama' => $k['nama'], 'posisi' => $k['posisi'], 'r' => sd_rekap1($D[$k['id']], $k, $f, $t),
        'h' => array_values(array_map(fn($x) => $code[$x['st']] ?? '', $D[$k['id']]))];
    return ['periode' => $p, 'from' => $f, 'to' => $t, 'hari' => (int)date('t', strtotime($f)), 'rows' => $out];
}
function sd_absenManual(array $d, array $u): array
{
    $k = sd_kryRow((string)($d['karyawan'] ?? ''));
    $t = (string)($d['tanggal'] ?? ''); if (!is_date($t)) throw new AppErr('Tanggal tidak valid');
    if ($t > today_s()) throw new AppErr('Tidak dapat mengisi absen untuk tanggal yang akan datang');
    $st = (string)($d['status'] ?? 'Hadir');
    if ($st === 'Hapus') { q('DELETE FROM absensi WHERE karyawan_id = ? AND tanggal = ?', [$k['id'], $t]); log_act($u, 'Hapus absen', 'SDM', $k['nama'] . ' ' . $t); return sd_absenHari(['tanggal' => $t]); }
    if (!in_array($st, SD_STATUS, true)) throw new AppErr('Status tidak valid');
    $x = sd_days([$k], $t, $t)[$k['id']][$t]; $s = $x['s'];
    $okJ = fn($j) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$j);
    $m = $st === 'Hadir' && $okJ($d['masuk'] ?? '') ? $t . ' ' . $d['masuk'] . ':00' : null;
    $pl = $st === 'Hadir' && $okJ($d['pulang'] ?? '') ? $t . ' ' . $d['pulang'] . ':00' : null;
    if ($st === 'Hadir' && !$m) throw new AppErr('Isi jam masuk');
    $telat = 0; $cepat = 0;
    if ($s && $m) { $dl = sd_hm(substr($m, 11, 5)) - sd_hm($s['masuk']); $telat = $dl > (int)$s['toleransi'] ? $dl : 0; }
    if ($s && $pl && $s['pulang'] > $s['masuk']) { $dc = sd_hm($s['pulang']) - sd_hm(substr($pl, 11, 5)); $cepat = max(0, $dc); }
    $lembur = (int)min(1440, max(0, num($d['lembur'] ?? 0)));
    $old = row('SELECT id FROM absensi WHERE karyawan_id = ? AND tanggal = ?', [$k['id'], $t]);
    $vals = [$s['id'] ?? '', $s['masuk'] ?? '', $s['pulang'] ?? '', $m, $pl, $st, $telat, $cepat, $lembur, 'Manual', str($d['catatan'] ?? '', 255)];
    if ($old) q('UPDATE absensi SET shift_id = ?, j_masuk = ?, j_pulang = ?, masuk = ?, pulang = ?, status = ?, telat = ?, pulang_cepat = ?, lembur = ?, sumber = ?, catatan = ? WHERE id = ?', array_merge($vals, [$old['id']]));
    else q('INSERT INTO absensi (shift_id, j_masuk, j_pulang, masuk, pulang, status, telat, pulang_cepat, lembur, sumber, catatan, id, karyawan_id, tanggal) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array_merge($vals, [uid('AB'), $k['id'], $t]));
    log_act($u, 'Absen manual', 'SDM', $k['nama'] . ' ' . $t . ' ' . $st);
    return sd_absenHari(['tanggal' => $t]);
}
function sd_pjOut(array $p, array $KN = []): array
{
    $KN = $KN ?: sd_namaMap();
    $hari = in_array($p['jenis'], ['Lembur', 'Akses'], true) ? 0 : diff_days($p['selesai'], $p['mulai']) + 1;
    $ats = (string)val('SELECT atasan_id FROM karyawan WHERE id = ?', [$p['karyawan_id']]);
    return ['id' => $p['id'], 'karyawan' => $p['karyawan_id'], 'nama' => $KN[$p['karyawan_id']] ?? '-', 'jenis' => $p['jenis'], 'mulai' => $p['mulai'], 'selesai' => $p['selesai'], 'hari' => $hari,
        'menit' => (int)$p['menit'], 'jamMulai' => $p['jam_mulai'] ?? '', 'jamSelesai' => $p['jam_selesai'] ?? '', 'alasan' => $p['alasan'], 'lampiran' => $p['lampiran'], 'status' => $p['status'],
        'oleh' => $p['diproses_oleh'], 'pada' => $p['diproses_pada'] ?? '', 'catatan' => $p['catatan'], 'dibuat' => $p['dibuat'],
        'pengganti' => $p['pengganti_id'] ?? '', 'penggantiNama' => ($p['pengganti_id'] ?? '') !== '' ? ($KN[$p['pengganti_id']] ?? '') : '',
        'diajukanOleh' => $p['diajukan_oleh'] ?? '', 'atasan' => $ats, 'atasanNama' => $ats !== '' ? ($KN[$ats] ?? '') : ''];
}
function sd_pjList(array $d): array
{
    $KN = []; foreach (rows('SELECT id, nama FROM karyawan') as $r) $KN[$r['id']] = $r['nama'];
    $st = (string)($d['status'] ?? '');
    $R = $st !== '' ? rows('SELECT * FROM pengajuan WHERE status = ? ORDER BY dibuat DESC LIMIT 300', [$st]) : rows('SELECT * FROM pengajuan ORDER BY dibuat DESC LIMIT 300');
    return ['rows' => array_map(fn($p) => sd_pjOut($p, $KN), $R), 'menunggu' => (int)val("SELECT COUNT(*) FROM pengajuan WHERE status = 'Menunggu'"), 'karyawan' => sd_meta()['karyawan']];
}
function sd_pjProses(array $d, array $u): array
{
    $p = row('SELECT * FROM pengajuan WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$p) throw new AppErr('Pengajuan tidak ditemukan');
    sd_putus($p, !empty($d['setuju']), (string)($d['catatan'] ?? ''), $d['pengganti'] ?? null, $u);
    return sd_pjList(['status' => (string)($d['status'] ?? '')]);
}
function sd_kasbonList(): array
{
    $KN = []; foreach (rows('SELECT id, nama FROM karyawan') as $r) $KN[$r['id']] = $r['nama'];
    return array_map(fn($r) => ['id' => $r['id'], 'karyawan' => $r['karyawan_id'], 'nama' => $KN[$r['karyawan_id']] ?? '-', 'tanggal' => $r['tanggal'], 'jumlah' => jn($r['jumlah']), 'cicilan' => jn($r['cicilan']), 'sisa' => jn($r['sisa']), 'ket' => $r['ket']],
        rows('SELECT * FROM kasbon ORDER BY tanggal DESC LIMIT 300'));
}
function sd_kasbonSave(array $d, array $u): array
{
    $k = sd_kryRow((string)($d['karyawan'] ?? ''));
    $j = r2(num($d['jumlah'] ?? 0)); if ($j <= 0) throw new AppErr('Isi jumlah kasbon');
    $c = r2(num($d['cicilan'] ?? $j)); if ($c <= 0 || $c > $j) $c = $j;
    $t = is_date($d['tanggal'] ?? null) ? $d['tanggal'] : today_s();
    assert_open_period($t, 'Kasbon');
    $kas = t3_kas((string)($d['metode'] ?? 'Kas'), (string)($d['akunKas'] ?? ''));
    $id = uid('KB'); $ket = str($d['ket'] ?? '', 255);
    journal_write($t, 'KSB-' . $k['kode'] . '-' . date('ymdHi'), 'Kasbon Karyawan', [['1202', 'Kasbon ' . $k['nama'] . ($ket ? ' - ' . $ket : ''), $j, 0], [$kas, 'Kasbon ' . $k['nama'], 0, $j]], '', 'KB:' . $id, $u);
    q('INSERT INTO kasbon (id, karyawan_id, tanggal, jumlah, cicilan, sisa, akun_kas, ket, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?,?)', [$id, $k['id'], $t, $j, $c, $j, $kas, $ket, $u['username']]);
    log_act($u, 'Kasbon', 'SDM', $k['nama'] . ' ' . fmt_num($j));
    return sd_kasbonList();
}
/** Hitung slip gaji satu karyawan untuk satu periode. $man = isian manual (bonus, potongan lain). */
function sd_gajiCalc(array $k, string $p, array $man): array
{
    $f = t3_perAwal($p); $t = t3_perAkhir($p); $S = sd_set_all();
    $r = sd_rekap1(sd_days([$k], $f, $t)[$k['id']], $k, $f, $t);
    $gp = (float)$k['gaji_pokok']; $harian = $k['tipe_gaji'] === 'Harian';
    $rate = $harian ? $gp : round($gp / $S['hariKerja']);
    $I = []; $P = [];
    $I[] = ['k' => 'pokok', 'n' => $harian ? 'Upah harian (' . $r['hadir'] . ' hari × ' . fmt_num($gp) . ')' : 'Gaji pokok', 'v' => (int)round($harian ? $gp * $r['hadir'] : $gp)];
    if ((float)$k['tunjangan'] > 0) $I[] = ['k' => 'tunjangan', 'n' => 'Tunjangan', 'v' => (int)round((float)$k['tunjangan'])];
    if ((float)$k['uang_makan'] > 0) $I[] = ['k' => 'makan', 'n' => 'Uang makan (' . $r['hadir'] . ' hari × ' . fmt_num($k['uang_makan']) . ')', 'v' => (int)round((float)$k['uang_makan'] * $r['hadir'])];
    if (!$harian && $S['gantiBayar'] && $r['gantiExtra'] > 0) $I[] = ['k' => 'ganti', 'n' => 'Masuk menggantikan rekan di hari libur (' . $r['gantiExtra'] . ' hari × ' . fmt_num($rate) . ')', 'v' => (int)round($r['gantiExtra'] * $rate)];
    if ($r['lembur'] > 0 && (float)$k['lembur_jam'] > 0) $I[] = ['k' => 'lembur', 'n' => 'Lembur (' . jn(round($r['lembur'] / 60, 2)) . ' jam × ' . fmt_num($k['lembur_jam']) . ')', 'v' => (int)round($r['lembur'] / 60 * (float)$k['lembur_jam'])];
    $kpi = $k['kpi_id'] ? kp_skor1($k, $p) : null;
    if ($kpi && $kpi['bonus'] > 0) $I[] = ['k' => 'kpi', 'n' => 'Bonus KPI (nilai ' . $kpi['skor'] . ', grade ' . $kpi['grade'] . ')', 'v' => (int)$kpi['bonus']];
    $bonus = (int)round(max(0, num($man['bonus'] ?? 0)));
    if ($bonus > 0) $I[] = ['k' => 'bonus', 'n' => str($man['bonusKet'] ?? '', 80) ?: 'Bonus / insentif', 'v' => $bonus];
    if ($r['telat'] > 0 && (float)$k['denda_telat'] > 0) $P[] = ['k' => 'telat', 'n' => 'Potongan terlambat (' . $r['telat'] . '× · ' . $r['telatMenit'] . ' menit)', 'v' => (int)round($r['telat'] * (float)$k['denda_telat'])];
    if (!$harian && $r['alpa'] > 0) $P[] = ['k' => 'alpa', 'n' => 'Potongan alpa (' . $r['alpa'] . ' hari × ' . fmt_num($rate) . ')', 'v' => (int)round($r['alpa'] * $rate)];
    if (!$harian && $S['izinPotong'] && $r['izin'] > 0) $P[] = ['k' => 'izin', 'n' => 'Potongan izin (' . $r['izin'] . ' hari × ' . fmt_num($rate) . ')', 'v' => (int)round($r['izin'] * $rate)];
    $pl = (int)round(max(0, num($man['potLain'] ?? 0)));
    if ($pl > 0) $P[] = ['k' => 'lain', 'n' => str($man['potLainKet'] ?? '', 80) ?: 'Potongan lain', 'v' => $pl];
    $kb = 0;
    foreach (rows('SELECT cicilan, sisa FROM kasbon WHERE karyawan_id = ? AND sisa > 0', [$k['id']]) as $x) $kb += min((float)$x['cicilan'], (float)$x['sisa']);
    if ($kb > 0) $P[] = ['k' => 'kasbon', 'n' => 'Cicilan kasbon (sisa ' . fmt_num(sd_kasbonSisa($k['id'])) . ')', 'v' => (int)round($kb)];
    $pend = array_sum(array_column($I, 'v')); $pot = array_sum(array_column($P, 'v'));
    if ($pot > $pend) { // kasbon dikurangi lebih dulu agar gaji bersih tidak minus
        foreach ($P as &$x) if ($x['k'] === 'kasbon') { $x['v'] = max(0, $x['v'] - ($pot - $pend)); }
        unset($x); $P = array_values(array_filter($P, fn($x) => $x['v'] > 0)); $pot = array_sum(array_column($P, 'v'));
    }
    return ['rekap' => $r, 'items' => $I, 'pot' => $P, 'man' => ['bonus' => $bonus, 'bonusKet' => str($man['bonusKet'] ?? '', 80), 'potLain' => $pl, 'potLainKet' => str($man['potLainKet'] ?? '', 80)],
        'kpi' => $kpi, 'pendapatan' => $pend, 'potongan' => $pot, 'total' => max(0, $pend - $pot),
        'karyawan' => ['kode' => $k['kode'], 'nama' => $k['nama'], 'posisi' => $k['posisi'], 'tipe' => $k['tipe_gaji'], 'bank' => $k['bank'], 'rekening' => $k['rekening']]];
}
function sd_gajiOut(array $g): array
{
    $x = json_decode((string)$g['data'], true) ?: [];
    return ['id' => $g['id'], 'periode' => $g['periode'], 'karyawan' => $g['karyawan_id'], 'status' => $g['status'], 'pendapatan' => jn($g['pendapatan']), 'potongan' => jn($g['potongan']), 'total' => jn($g['total']),
        'dibayar' => $g['dibayar_pada'] ?? '', 'final' => $g['final_pada'] ?? ''] + $x;
}
function sd_gajiList(array $d): array
{
    $p = t3_per((string)($d['periode'] ?? substr(today_s(), 0, 7)));
    $G = rows('SELECT g.* FROM gaji g JOIN karyawan k ON k.id = g.karyawan_id WHERE g.periode = ? ORDER BY k.nama', [$p]);
    $have = array_column($G, 'karyawan_id');
    $belum = array_map(fn($k) => ['id' => $k['id'], 'nama' => $k['nama']], array_values(array_filter(rows("SELECT id, nama FROM karyawan WHERE status = 'Aktif' ORDER BY nama"), fn($k) => !in_array($k['id'], $have, true))));
    $s = settings_all();
    return ['periode' => $p, 'rows' => array_map('sd_gajiOut', $G), 'belum' => $belum, 'toko' => ['nama' => $s['namaToko'], 'alamat' => $s['alamat'], 'telepon' => $s['telepon']]];
}
function sd_gajiPut(array $k, string $p, array $man): void
{
    $id = $p . '-' . $k['id'];
    $c = sd_gajiCalc($k, $p, $man);
    $data = json_encode(['rekap' => $c['rekap'], 'items' => $c['items'], 'pot' => $c['pot'], 'man' => $c['man'], 'kpi' => $c['kpi'], 'kary' => $c['karyawan']], JSON_UNESCAPED_UNICODE);
    q('INSERT INTO gaji (id, periode, karyawan_id, data, pendapatan, potongan, total, status, diubah) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE data = VALUES(data), pendapatan = VALUES(pendapatan), potongan = VALUES(potongan), total = VALUES(total), diubah = VALUES(diubah)',
        [$id, $p, $k['id'], $data, $c['pendapatan'], $c['potongan'], $c['total'], 'Draf', now_s()]);
}
function sd_gajiHitung(array $d, array $u): array
{
    $p = t3_per((string)($d['periode'] ?? ''));
    $ids = array_values(array_filter(array_map('strval', (array)($d['ids'] ?? []))));
    $K = $ids ? rows('SELECT * FROM karyawan WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) : rows("SELECT * FROM karyawan WHERE status = 'Aktif'");
    $n = 0;
    foreach ($K as $k) {
        $g = row('SELECT status, data FROM gaji WHERE periode = ? AND karyawan_id = ?', [$p, $k['id']]);
        if ($g && $g['status'] !== 'Draf') continue;
        sd_gajiPut($k, $p, $g ? (json_decode((string)$g['data'], true)['man'] ?? []) : []);
        $n++;
    }
    log_act($u, 'Hitung gaji', 'SDM', $p . ' · ' . $n . ' karyawan');
    return sd_gajiList(['periode' => $p]);
}
function sd_gajiSave(array $d, array $u): array
{
    $g = row('SELECT * FROM gaji WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$g) throw new AppErr('Data gaji tidak ditemukan');
    if ($g['status'] !== 'Draf') throw new AppErr('Gaji yang sudah final tidak dapat diubah. Batalkan finalnya dulu.');
    sd_gajiPut(sd_kryRow($g['karyawan_id']), $g['periode'], ['bonus' => $d['bonus'] ?? 0, 'bonusKet' => $d['bonusKet'] ?? '', 'potLain' => $d['potLain'] ?? 0, 'potLainKet' => $d['potLainKet'] ?? '']);
    return sd_gajiList(['periode' => $g['periode']]);
}
function sd_gajiFinal(array $d, array $u): array
{
    $p = t3_per((string)($d['periode'] ?? ''));
    $ids = array_values(array_filter(array_map('strval', (array)($d['ids'] ?? []))));
    $G = rows("SELECT * FROM gaji WHERE periode = ? AND status = 'Draf'" . ($ids ? ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')' : '') . ' FOR UPDATE', array_merge([$p], $ids));
    if (!$G) throw new AppErr('Tidak ada slip berstatus Draf untuk difinalkan');
    $tgl = min(t3_perAkhir($p), today_s());
    assert_open_period($tgl, 'Penggajian');
    foreach ($G as $g) {
        $k = sd_kryRow($g['karyawan_id']);
        sd_gajiPut($k, $p, json_decode((string)$g['data'], true)['man'] ?? []);   // hitung ulang dengan data absen terbaru
        $g = row('SELECT * FROM gaji WHERE id = ?', [$g['id']]); $x = json_decode((string)$g['data'], true);
        $kb = 0; foreach ($x['pot'] as $pp) if ($pp['k'] === 'kasbon') $kb = (float)$pp['v'];
        $beban = (float)$g['total'] + $kb;
        $ket = 'Gaji ' . $p . ' - ' . $k['nama'];
        journal_write($tgl, 'GJ-' . str_replace('-', '', $p) . '-' . $k['kode'], 'Penggajian', [['6101', $ket, $beban, 0], ['2102', $ket . ' (terutang)', 0, (float)$g['total']], ['1202', 'Potong kasbon ' . $k['nama'], 0, $kb]], '', 'GJ:F:' . $g['id'], $u);
        // kurangi saldo kasbon (yang paling lama dulu)
        $sisa = $kb;
        foreach (rows('SELECT id, sisa FROM kasbon WHERE karyawan_id = ? AND sisa > 0 ORDER BY tanggal, id', [$k['id']]) as $kbr) { if ($sisa <= 0) break; $t = min($sisa, (float)$kbr['sisa']); q('UPDATE kasbon SET sisa = ROUND(sisa - ?, 2) WHERE id = ?', [$t, $kbr['id']]); $sisa -= $t; }
        $x['kasbonPotong'] = $kb;
        q("UPDATE gaji SET status = 'Final', final_pada = ?, data = ? WHERE id = ?", [now_s(), json_encode($x, JSON_UNESCAPED_UNICODE), $g['id']]);
    }
    log_act($u, 'Final gaji', 'SDM', $p . ' · ' . count($G) . ' slip');
    return sd_gajiList(['periode' => $p]);
}
function sd_gajiBayar(array $d, array $u): array
{
    $ids = array_values(array_filter(array_map('strval', (array)($d['ids'] ?? []))));
    if (!$ids) throw new AppErr('Pilih slip yang dibayar');
    $G = rows("SELECT g.*, k.nama, k.kode FROM gaji g JOIN karyawan k ON k.id = g.karyawan_id WHERE g.status = 'Final' AND g.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ') FOR UPDATE', $ids);
    if (!$G) throw new AppErr('Hanya slip berstatus Final yang dapat dibayar');
    $tgl = is_date($d['tanggal'] ?? null) ? $d['tanggal'] : today_s();
    assert_open_period($tgl, 'Pembayaran gaji');
    $kas = t3_kas((string)($d['metode'] ?? 'Bank'), (string)($d['akunKas'] ?? ''));
    foreach ($G as $g) {
        journal_write($tgl, 'BYG-' . str_replace('-', '', $g['periode']) . '-' . $g['kode'], 'Pembayaran Gaji', [['2102', 'Bayar gaji ' . $g['periode'] . ' - ' . $g['nama'], (float)$g['total'], 0], [$kas, 'Bayar gaji ' . $g['nama'], 0, (float)$g['total']]], '', 'GJ:B:' . $g['id'], $u);
        q("UPDATE gaji SET status = 'Dibayar', dibayar_pada = ?, akun_kas = ? WHERE id = ?", [$tgl, $kas, $g['id']]);
    }
    log_act($u, 'Bayar gaji', 'SDM', count($G) . ' slip');
    return sd_gajiList(['periode' => $G[0]['periode']]);
}
function sd_gajiBatal(array $d, array $u): array
{
    $g = row('SELECT * FROM gaji WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$g) throw new AppErr('Data gaji tidak ditemukan');
    if ($g['status'] === 'Dibayar') {
        assert_open_period((string)$g['dibayar_pada'], 'Pembatalan pembayaran gaji');
        q('DELETE FROM jurnal WHERE ref = ?', ['GJ:B:' . $g['id']]);
        q("UPDATE gaji SET status = 'Final', dibayar_pada = NULL WHERE id = ?", [$g['id']]);
    } elseif ($g['status'] === 'Final') {
        assert_open_period(min(t3_perAkhir($g['periode']), substr((string)$g['final_pada'], 0, 10)), 'Pembatalan gaji');
        $x = json_decode((string)$g['data'], true);
        $kb = (float)($x['kasbonPotong'] ?? 0);
        foreach (rows('SELECT id, jumlah, sisa FROM kasbon WHERE karyawan_id = ? ORDER BY tanggal DESC, id DESC', [$g['karyawan_id']]) as $kbr) { if ($kb <= 0) break; $t = min($kb, (float)$kbr['jumlah'] - (float)$kbr['sisa']); if ($t > 0) { q('UPDATE kasbon SET sisa = ROUND(sisa + ?, 2) WHERE id = ?', [$t, $kbr['id']]); $kb -= $t; } }
        q('DELETE FROM jurnal WHERE ref = ?', ['GJ:F:' . $g['id']]);
        q("UPDATE gaji SET status = 'Draf', final_pada = NULL WHERE id = ?", [$g['id']]);
    } else { q('DELETE FROM gaji WHERE id = ?', [$g['id']]); }
    log_act($u, 'Batal gaji', 'SDM', $g['id'] . ' (' . $g['status'] . ')');
    return sd_gajiList(['periode' => $g['periode']]);
}

/* ======================================================================
 *  KPI KARYAWAN
 *  Sumber otomatis: hadir (% kehadiran), tepat (% tepat waktu), omzet & trx (penjualan kasir),
 *  member (member baru didaftarkan). Manual: dinilai atasan.
 * ==================================================================== */
const KPI_SUMBER = ['hadir' => ['Kehadiran', '%'], 'tepat' => ['Tepat waktu', '%'], 'omzet' => ['Omzet penjualan', 'Rp'], 'trx' => ['Jumlah transaksi', 'trx'],
    'member' => ['Member baru didaftarkan', 'orang'], 'manual' => ['Penilaian manual', '']];
function kp_bonus(): array { $s = settings_all(); $o = []; foreach (['A', 'B', 'C', 'D'] as $g) $o[$g] = (int)($s['KpiBonus' . $g] ?? 0); return $o; }
function kp_grade(float $s): string { return $s >= 95 ? 'A' : ($s >= 80 ? 'B' : ($s >= 65 ? 'C' : 'D')); }
function kp_tplOut(array $r): array { return ['id' => $r['id'], 'nama' => $r['nama'], 'aktif' => (bool)$r['aktif'], 'indikator' => json_decode((string)$r['indikator'], true) ?: [], 'dipakai' => (int)val('SELECT COUNT(*) FROM karyawan WHERE kpi_id = ?', [$r['id']])]; }
function kp_tplList(): array { return ['tpl' => array_map('kp_tplOut', rows('SELECT * FROM kpi_tpl ORDER BY nama')), 'sumber' => KPI_SUMBER, 'bonus' => kp_bonus()]; }
function kp_tplSave(array $d, array $u): array
{
    $nama = str($d['nama'] ?? '', 120); if ($nama === '') throw new AppErr('Nama template KPI wajib diisi');
    $ind = []; $i = 0;
    foreach ($d['indikator'] ?? [] as $x) {
        $n = str($x['nama'] ?? '', 120); $s = (string)($x['sumber'] ?? 'manual'); $t = r2(num($x['target'] ?? 0)); $b = r2(num($x['bobot'] ?? 0));
        if ($n === '' && $t == 0) continue;
        if ($n === '') throw new AppErr('Nama indikator wajib diisi');
        if (!isset(KPI_SUMBER[$s])) throw new AppErr('Sumber data indikator tidak valid');
        if ($t <= 0) throw new AppErr('Target "' . $n . '" harus lebih dari 0');
        if ($b <= 0) throw new AppErr('Bobot "' . $n . '" harus lebih dari 0');
        $ind[] = ['id' => preg_match('/^[a-z0-9]{1,20}$/i', (string)($x['id'] ?? '')) ? $x['id'] : 'i' . (++$i) . substr(uid(''), 0, 4), 'nama' => $n, 'sumber' => $s, 'target' => $t, 'bobot' => $b];
    }
    if (!$ind) throw new AppErr('Tambahkan minimal satu indikator');
    $id = (string)($d['id'] ?? '');
    if ($id && val('SELECT id FROM kpi_tpl WHERE id = ?', [$id])) q('UPDATE kpi_tpl SET nama = ?, indikator = ?, aktif = ? WHERE id = ?', [$nama, json_encode($ind, JSON_UNESCAPED_UNICODE), !isset($d['aktif']) || !empty($d['aktif']) ? 1 : 0, $id]);
    else { $id = uid('KP', 6); q('INSERT INTO kpi_tpl (id, nama, indikator, aktif, dibuat) VALUES (?,?,?,1,?)', [$id, $nama, json_encode($ind, JSON_UNESCAPED_UNICODE), now_s()]); }
    log_act($u, 'Simpan', 'KPI', $nama);
    return kp_tplList();
}
function kp_tplDel(array $d, array $u): array
{
    $id = (string)($d['id'] ?? '');
    if ((int)val('SELECT COUNT(*) FROM karyawan WHERE kpi_id = ?', [$id])) throw new AppErr('Template masih dipakai karyawan. Ganti template karyawan tersebut dulu.');
    q('DELETE FROM kpi_tpl WHERE id = ?', [$id]);
    log_act($u, 'Hapus', 'KPI', $id);
    return kp_tplList();
}
function kp_assign(array $d, array $u): array
{
    $k = sd_kryRow((string)($d['karyawan'] ?? ''));
    $t = (string)($d['kpi'] ?? ''); if ($t !== '' && !val('SELECT id FROM kpi_tpl WHERE id = ?', [$t])) throw new AppErr('Template tidak ditemukan');
    q('UPDATE karyawan SET kpi_id = ? WHERE id = ?', [$t, $k['id']]);
    log_act($u, 'Atur KPI', 'KPI', $k['nama']);
    return ['ok' => 1];
}
function kp_set(array $d, array $u): array
{
    $o = []; foreach (['A', 'B', 'C', 'D'] as $g) $o['KpiBonus' . $g] = (string)(int)max(0, num($d[$g] ?? 0));
    setting_put($o); log_act($u, 'Ubah', 'KPI', 'Bonus per grade');
    return kp_tplList();
}
/** Nilai KPI satu karyawan pada satu periode. */
function kp_skor1(array $k, string $p, ?array $days = null): ?array
{
    $tpl = $k['kpi_id'] ? row('SELECT * FROM kpi_tpl WHERE id = ?', [$k['kpi_id']]) : null;
    if (!$tpl) return null;
    $f = t3_perAwal($p); $t = t3_perAkhir($p); $tE = min($t, today_s());
    $r = sd_rekap1($days ?? sd_days([$k], $f, $t)[$k['id']], $k, $f, $t);
    $un = $k['user_id'] ? (string)val('SELECT username FROM users WHERE id = ?', [$k['user_id']]) : '';
    $wajib = max(0, $r['jadwalLewat'] - $r['cuti'] - $r['sakit']);
    $auto = [
        'hadir' => $wajib > 0 ? round(min($r['hadir'], $wajib) / $wajib * 100, 1) : ($tE < $f ? 0 : 100),
        'tepat' => $r['hadir'] > 0 ? round(($r['hadir'] - $r['telat']) / $r['hadir'] * 100, 1) : 0,
        'omzet' => $un ? (float)val("SELECT COALESCE(SUM(total),0) FROM transaksi WHERE status = 'Selesai' AND kasir = ? AND tanggal BETWEEN ? AND ?", [$un, $f . ' 00:00:00', $t . ' 23:59:59']) : 0,
        'trx' => $un ? (float)val("SELECT COUNT(*) FROM transaksi WHERE status = 'Selesai' AND kasir = ? AND tanggal BETWEEN ? AND ?", [$un, $f . ' 00:00:00', $t . ' 23:59:59']) : 0,
        'member' => $un ? (float)val('SELECT COUNT(*) FROM member WHERE dibuat_oleh = ? AND dibuat BETWEEN ? AND ?', [$un, $f . ' 00:00:00', $t . ' 23:59:59']) : 0,
    ];
    $man = []; foreach (rows('SELECT indikator_id, nilai, catatan FROM kpi_nilai WHERE periode = ? AND karyawan_id = ?', [$p, $k['id']]) as $x) $man[$x['indikator_id']] = $x;
    $I = []; $sb = 0.0; $sw = 0.0;
    foreach (json_decode((string)$tpl['indikator'], true) ?: [] as $x) {
        $isMan = $x['sumber'] === 'manual';
        $v = $isMan ? (isset($man[$x['id']]) ? (float)$man[$x['id']]['nilai'] : null) : (float)$auto[$x['sumber']];
        $sk = $v === null ? 0.0 : min(120.0, $v / max(0.0001, (float)$x['target']) * 100);
        $I[] = ['id' => $x['id'], 'nama' => $x['nama'], 'sumber' => $x['sumber'], 'target' => jn($x['target']), 'bobot' => jn($x['bobot']), 'nilai' => $v === null ? null : jn(round($v, 2)), 'skor' => round($sk, 1),
            'catatan' => $man[$x['id']]['catatan'] ?? ''];
        $sb += $sk * (float)$x['bobot']; $sw += (float)$x['bobot'];
    }
    $skor = $sw > 0 ? round($sb / $sw, 1) : 0; $g = kp_grade($skor);
    return ['tpl' => $tpl['nama'], 'skor' => $skor, 'grade' => $g, 'bonus' => kp_bonus()[$g] ?? 0, 'indikator' => $I, 'rekap' => $r, 'belumDinilai' => count(array_filter($I, fn($x) => $x['nilai'] === null))];
}
function kp_hasil(array $d): array
{
    $p = t3_per((string)($d['periode'] ?? substr(today_s(), 0, 7)));
    $K = rows("SELECT * FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
    $D = sd_days($K, t3_perAwal($p), t3_perAkhir($p));
    $out = [];
    foreach ($K as $k) {
        $s = kp_skor1($k, $p, $D[$k['id']] ?? []);
        $out[] = ['id' => $k['id'], 'nama' => $k['nama'], 'posisi' => $k['posisi'], 'kpi' => $s];
    }
    usort($out, fn($a, $b) => ($b['kpi']['skor'] ?? -1) <=> ($a['kpi']['skor'] ?? -1));
    return ['periode' => $p, 'rows' => $out, 'bonus' => kp_bonus(), 'sumber' => KPI_SUMBER];
}
function kp_nilai(array $d, array $u): array
{
    $p = t3_per((string)($d['periode'] ?? ''));
    $k = sd_kryRow((string)($d['karyawan'] ?? ''));
    if ($k['user_id'] === $u['id'] && ($u['akses']['kpi'] ?? 0) < 3) throw new AppErr('Anda tidak dapat menilai KPI diri sendiri');
    foreach ($d['nilai'] ?? [] as $x) {
        $iid = (string)($x['id'] ?? ''); if ($iid === '') continue;
        if ($x['nilai'] === null || $x['nilai'] === '') { q('DELETE FROM kpi_nilai WHERE periode = ? AND karyawan_id = ? AND indikator_id = ?', [$p, $k['id'], $iid]); continue; }
        q('INSERT INTO kpi_nilai (periode, karyawan_id, indikator_id, nilai, catatan, oleh) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), catatan = VALUES(catatan), oleh = VALUES(oleh)',
            [$p, $k['id'], $iid, r2(max(0, num($x['nilai']))), str($x['catatan'] ?? '', 255), $u['username']]);
    }
    log_act($u, 'Nilai KPI', 'KPI', $k['nama'] . ' ' . $p);
    return kp_hasil(['periode' => $p]);
}

/* ---------- Import Excel KPI ---------- */
function kp_sumberParse(string $v): ?string
{
    $n = preg_replace('/[^a-z]/', '', mb_strtolower($v));
    if ($n === '') return 'manual';
    foreach (KPI_SUMBER as $k => $x) if ($n === $k || $n === preg_replace('/[^a-z]/', '', mb_strtolower($x[0]))) return $k;
    $alias = ['kehadiran' => 'hadir', 'absensi' => 'hadir', 'absen' => 'hadir', 'tepatwaktu' => 'tepat', 'ketepatan' => 'tepat', 'telat' => 'tepat', 'penjualan' => 'omzet', 'omset' => 'omzet', 'omzet' => 'omzet',
        'transaksi' => 'trx', 'jumlahtransaksi' => 'trx', 'struk' => 'trx', 'member' => 'member', 'memberbaru' => 'member', 'pelangganbaru' => 'member',
        'penilaianatasan' => 'manual', 'penilaianmanual' => 'manual', 'atasan' => 'manual', 'manual' => 'manual', 'dinilaiatasan' => 'manual'];
    return $alias[$n] ?? null;
}
/** Import template KPI: satu baris = satu indikator; baris dengan nama template sama digabung. Template yang sudah ada diganti indikatornya (nilai lama tetap tersambung bila nama indikator sama). */
function kp_tplImport(array $d, array $u): array
{
    $items = (array)($d['items'] ?? []); if (!$items) throw new AppErr('Tidak ada baris untuk diimpor');
    $G = []; $err = []; $last = '';
    foreach ($items as $i => $x) {
        $row = (int)($x['_row'] ?? $i + 2);
        $t = trim((string)($x['template'] ?? '')); if ($t === '') $t = $last; $last = $t;
        if ($t === '') { $err[] = 'baris ' . $row . ': nama template kosong'; continue; }
        $sb = kp_sumberParse((string)($x['sumber'] ?? ''));
        if ($sb === null) { $err[] = 'baris ' . $row . ': sumber data "' . $x['sumber'] . '" tidak dikenal (pakai: Kehadiran, Tepat waktu, Omzet penjualan, Jumlah transaksi, Member baru, Penilaian atasan)'; continue; }
        $G[mb_strtolower($t)]['nama'] = $t;
        $G[mb_strtolower($t)]['ind'][] = ['nama' => trim((string)($x['nama'] ?? '')), 'sumber' => $sb, 'target' => t3_num($x['target'] ?? 0), 'bobot' => t3_num($x['bobot'] ?? 0), '_row' => $row];
    }
    $n = ['baru' => 0, 'ubah' => 0];
    foreach ($G as $g) {
        $old = row('SELECT * FROM kpi_tpl WHERE LOWER(nama) = LOWER(?)', [$g['nama']]);
        $oldId = []; if ($old) foreach (json_decode((string)$old['indikator'], true) ?: [] as $o) $oldId[mb_strtolower($o['nama'])] = $o['id'];
        $ind = array_map(fn($x) => ['id' => $oldId[mb_strtolower($x['nama'])] ?? '', 'nama' => $x['nama'], 'sumber' => $x['sumber'], 'target' => $x['target'], 'bobot' => $x['bobot']], $g['ind']);
        try { kp_tplSave(['id' => $old['id'] ?? '', 'nama' => $g['nama'], 'indikator' => $ind, 'aktif' => 1], $u); $n[$old ? 'ubah' : 'baru']++; }
        catch (AppErr $e) { $err[] = 'template "' . $g['nama'] . '": ' . $e->getMessage(); }
    }
    if ($err) throw new AppErr(count($err) . ' baris bermasalah, belum ada yang disimpan — ' . implode(' · ', array_slice($err, 0, 4)) . (count($err) > 4 ? ' · …' : ''));
    log_act($u, 'Import Excel', 'KPI', 'Template: ' . $n['baru'] . ' baru, ' . $n['ubah'] . ' diperbarui');
    return $n + kp_tplList();
}
function kp_perParse($v): ?string
{
    $s = trim((string)$v);
    if (preg_match('/^(\d{4})[-\/.](\d{1,2})/', $s, $m)) return $m[1] . '-' . str_pad($m[2], 2, '0', STR_PAD_LEFT);
    if (preg_match('/^(\d{1,2})[-\/.](\d{4})$/', $s, $m)) return $m[2] . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
    $B = ['januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'jun' => 6, 'jul' => 7, 'agu' => 8, 'agt' => 8, 'sep' => 9, 'okt' => 10, 'nov' => 11, 'des' => 12];
    if (preg_match('/^([a-z]+)\s+(\d{4})$/i', $s, $m) && isset($B[mb_strtolower($m[1])])) return $m[2] . '-' . str_pad((string)$B[mb_strtolower($m[1])], 2, '0', STR_PAD_LEFT);
    return null;
}
/** Data untuk template Excel penilaian: setiap karyawan × indikator manual pada template KPI-nya, berikut nilai yang sudah ada. */
function kp_nilaiTpl(array $d): array
{
    $p = t3_per((string)($d['periode'] ?? substr(today_s(), 0, 7))); $out = [];
    foreach (rows("SELECT k.*, t.nama tnama, t.indikator tind FROM karyawan k JOIN kpi_tpl t ON t.id = k.kpi_id WHERE k.status = 'Aktif' ORDER BY k.nama") as $k) {
        $man = []; foreach (rows('SELECT indikator_id, nilai, catatan FROM kpi_nilai WHERE periode = ? AND karyawan_id = ?', [$p, $k['id']]) as $x) $man[$x['indikator_id']] = $x;
        foreach (json_decode((string)$k['tind'], true) ?: [] as $x) if ($x['sumber'] === 'manual')
            $out[] = ['periode' => $p, 'kode' => $k['kode'], 'nama' => $k['nama'], 'posisi' => $k['posisi'], 'template' => $k['tnama'], 'indikator' => $x['nama'], 'target' => jn($x['target']),
                'nilai' => isset($man[$x['id']]) ? jn($man[$x['id']]['nilai']) : null, 'catatan' => $man[$x['id']]['catatan'] ?? ''];
    }
    return ['periode' => $p, 'rows' => $out];
}
/** Import penilaian KPI (indikator manual): satu baris = karyawan + indikator + nilai. Nilai kosong = tidak diubah. */
function kp_nilaiImport(array $d, array $u): array
{
    $items = (array)($d['items'] ?? []); if (!$items) throw new AppErr('Tidak ada baris untuk diimpor'); if (count($items) > 5000) throw new AppErr('Maksimal 5.000 baris');
    $byK = []; $byN = [];
    foreach (rows('SELECT k.*, t.indikator tind FROM karyawan k LEFT JOIN kpi_tpl t ON t.id = k.kpi_id') as $k) { if ($k['kode'] !== '') $byK[mb_strtolower($k['kode'])] = $k; $byN[mb_strtolower(trim($k['nama']))][] = $k; }
    $err = []; $plan = []; $lewat = 0; $per = [];
    foreach ($items as $i => $x) {
        $row = (int)($x['_row'] ?? $i + 2);
        $v = $x['nilai'] ?? ''; if ($v === '' || $v === null) { $lewat++; continue; }
        $kode = mb_strtolower(trim((string)($x['kode'] ?? ''))); $nm = mb_strtolower(trim((string)($x['nama'] ?? '')));
        $k = $kode !== '' ? ($byK[$kode] ?? null) : null;
        if (!$k && $nm !== '') { $c = $byN[$nm] ?? []; if (count($c) > 1) { $err[] = 'baris ' . $row . ': nama "' . $x['nama'] . '" dipakai ' . count($c) . ' karyawan, isi kolom Kode'; continue; } $k = $c[0] ?? null; }
        if (!$k) { $err[] = 'baris ' . $row . ': karyawan "' . trim(($x['kode'] ?? '') . ' ' . ($x['nama'] ?? '')) . '" tidak ditemukan'; continue; }
        $p = kp_perParse($x['periode'] ?? ($d['periode'] ?? '')); if (!$p) { $err[] = 'baris ' . $row . ': periode "' . ($x['periode'] ?? '') . '" tidak valid (contoh 2026-11)'; continue; }
        if (!$k['kpi_id']) { $err[] = 'baris ' . $row . ': ' . $k['nama'] . ' belum diberi template KPI'; continue; }
        $ind = null; foreach (json_decode((string)$k['tind'], true) ?: [] as $o) if (mb_strtolower(trim($o['nama'])) === mb_strtolower(trim((string)($x['indikator'] ?? '')))) $ind = $o;
        if (!$ind) { $err[] = 'baris ' . $row . ': indikator "' . ($x['indikator'] ?? '') . '" tidak ada di template KPI ' . $k['nama']; continue; }
        if ($ind['sumber'] !== 'manual') { $err[] = 'baris ' . $row . ': "' . $ind['nama'] . '" dihitung otomatis, tidak perlu diisi'; continue; }
        if ($k['user_id'] === $u['id'] && ($u['akses']['kpi'] ?? 0) < 3) { $err[] = 'baris ' . $row . ': Anda tidak dapat menilai KPI diri sendiri'; continue; }
        $nv = t3_num($v); if ($nv < 0) { $err[] = 'baris ' . $row . ': nilai tidak boleh negatif'; continue; }
        $plan[] = [$p, $k['id'], $ind['id'], r2($nv), str($x['catatan'] ?? '', 255)]; $per[$p] = 1;
    }
    if ($err) throw new AppErr(count($err) . ' baris bermasalah, belum ada yang disimpan — ' . implode(' · ', array_slice($err, 0, 4)) . (count($err) > 4 ? ' · …' : ''));
    if (!$plan) throw new AppErr('Belum ada nilai yang diisi');
    foreach ($plan as $x) q('INSERT INTO kpi_nilai (periode, karyawan_id, indikator_id, nilai, catatan, oleh) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), catatan = VALUES(catatan), oleh = VALUES(oleh)', array_merge($x, [$u['username']]));
    log_act($u, 'Import Excel', 'KPI', 'Penilaian ' . implode(', ', array_keys($per)) . ': ' . count($plan) . ' nilai');
    return ['baru' => count($plan), 'ubah' => 0, 'lewat' => $lewat, 'nilai' => count($plan), 'karyawan' => count(array_unique(array_column($plan, 1))), 'periode' => array_keys($per)];
}

/* ======================================================================
 *  TOKO ONLINE — admin
 *  Pesanan dari katalog publik (toko.html) → konfirmasi lewat WhatsApp →
 *  "Selesai" membuat transaksi penjualan (stok + jurnal) dari lokasi toko online.
 * ==================================================================== */
const ON_STATUS = ['Baru', 'Diproses', 'Dikirim', 'Selesai', 'Batal'];
function on_cfg(): array
{
    $s = settings_all();
    $ong = json_decode((string)($s['OnlineOngkir'] ?? ''), true);
    if (!is_array($ong)) $ong = [['nama' => 'Kurir toko (dalam kota)', 'biaya' => 10000]];
    $lok = (string)($s['OnlineLokasi'] ?? ''); if (!isset(lokasi_all()[$lok])) $lok = lok_jual();
    return ['aktif' => ($s['OnlineAktif'] ?? '0') === '1', 'wa' => (string)($s['OnlineWA'] ?? ($s['telepon'] ?? '')), 'lokasi' => $lok, 'ongkir' => array_values(array_filter(array_map(fn($o) => ['nama' => str($o['nama'] ?? '', 60), 'biaya' => (int)max(0, num($o['biaya'] ?? 0))], $ong), fn($o) => $o['nama'] !== '')),
        'ambil' => ($s['OnlineAmbil'] ?? '1') === '1', 'cod' => ($s['OnlineCOD'] ?? '1') === '1', 'rekening' => (string)($s['OnlineRekening'] ?? ''), 'info' => (string)($s['OnlineInfo'] ?? ''),
        'min' => (int)($s['OnlineMin'] ?? 0), 'tampilHabis' => ($s['OnlineTampilHabis'] ?? '0') === '1'];
}
function on_setGet(): array
{
    return ['cfg' => on_cfg(), 'lokasi' => array_values(array_map(fn($l) => ['id' => $l['id'], 'nama' => $l['nama']], lokasi_all())), 'toko' => settings_all()['namaToko'] ?? '',
        'stat' => ['baru' => (int)val("SELECT COUNT(*) FROM pesanan_online WHERE status = 'Baru'"), 'proses' => (int)val("SELECT COUNT(*) FROM pesanan_online WHERE status IN ('Diproses','Dikirim')"),
            'bulanIni' => jn(val("SELECT COALESCE(SUM(total),0) FROM pesanan_online WHERE status = 'Selesai' AND tanggal >= ?", [substr(today_s(), 0, 7) . '-01 00:00:00'])),
            'produk' => (int)val('SELECT COUNT(*) FROM produk WHERE online = 1 AND harga_jual > 0')]];
}
function on_setSave(array $d, array $u): array
{
    $wa = preg_replace('/\D/', '', (string)($d['wa'] ?? ''));
    if (!empty($d['aktif']) && strlen($wa) < 9) throw new AppErr('Isi nomor WhatsApp toko untuk menerima pesanan');
    $lok = (string)($d['lokasi'] ?? ''); if (!isset(lokasi_all()[$lok])) throw new AppErr('Pilih lokasi stok untuk toko online');
    $ong = array_values(array_filter(array_map(fn($o) => ['nama' => str($o['nama'] ?? '', 60), 'biaya' => (int)max(0, num($o['biaya'] ?? 0))], (array)($d['ongkir'] ?? [])), fn($o) => $o['nama'] !== ''));
    setting_put(['OnlineAktif' => !empty($d['aktif']) ? '1' : '0', 'OnlineWA' => $wa, 'OnlineLokasi' => $lok, 'OnlineOngkir' => json_encode($ong, JSON_UNESCAPED_UNICODE),
        'OnlineAmbil' => !empty($d['ambil']) ? '1' : '0', 'OnlineCOD' => !empty($d['cod']) ? '1' : '0', 'OnlineRekening' => str($d['rekening'] ?? '', 500), 'OnlineInfo' => str($d['info'] ?? '', 500),
        'OnlineMin' => (string)(int)max(0, num($d['min'] ?? 0)), 'OnlineTampilHabis' => !empty($d['tampilHabis']) ? '1' : '0']);
    log_act($u, 'Ubah', 'Toko Online', 'Pengaturan');
    return on_setGet();
}
function on_out(array $o): array
{
    return ['no' => $o['no'], 'tanggal' => $o['tanggal'], 'nama' => $o['nama'], 'hp' => $o['hp'], 'alamat' => $o['alamat'], 'catatan' => $o['catatan'], 'kirim' => $o['kirim'], 'ongkir' => jn($o['ongkir']),
        'bayar' => $o['bayar'], 'items' => json_decode((string)$o['items'], true) ?: [], 'subtotal' => jn($o['subtotal']), 'diskon' => jn($o['diskon']), 'total' => jn($o['total']), 'member' => $o['member_id'] !== '',
        'status' => $o['status'], 'trx' => $o['trx_no'], 'dibayar' => $o['dibayar'] ?? null, 'log' => json_decode((string)$o['log'], true) ?: []];
}
function on_list(array $d): array
{
    $st = (string)($d['status'] ?? '');
    $w = []; $a = [];
    if ($st === 'aktif') $w[] = "status IN ('Baru','Diproses','Dikirim')"; elseif (in_array($st, ON_STATUS, true)) { $w[] = 'status = ?'; $a[] = $st; }
    if (($q = str($d['q'] ?? '', 60)) !== '') { $w[] = '(no LIKE ? OR nama LIKE ? OR hp LIKE ?)'; array_push($a, "%$q%", "%$q%", "%$q%"); }
    return ['rows' => array_map('on_out', rows('SELECT * FROM pesanan_online' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY tanggal DESC LIMIT 300', $a))] + on_setGet();
}
/** Akun uang muka pelanggan (pembayaran pesanan online yang diterima sebelum barang diserahkan). */
function on_akunDp(): string
{
    if (!akun_exists('2105')) q("INSERT INTO akun (kode, nama, kategori, subkategori, saldo_normal, saldo_awal, status, keterangan) VALUES ('2105','Uang Muka Pelanggan','Kewajiban','Kewajiban Lancar','Kredit',0,'Aktif','')");
    return '2105';
}
function on_kasAkun(array $o): string { return pos_method_acc($o['bayar'] === 'Transfer' ? 'Transfer' : 'Tunai'); }
function on_status(array $d, array $u): array
{
    $o = row('SELECT * FROM pesanan_online WHERE no = ? FOR UPDATE', [(string)($d['no'] ?? '')]);
    if (!$o) throw new AppErr('Pesanan tidak ditemukan');
    $aksi = (string)($d['aksi'] ?? '');
    $next = ['proses' => 'Diproses', 'kirim' => 'Dikirim', 'selesai' => 'Selesai', 'batal' => 'Batal', 'bayar' => $o['status']][$aksi] ?? null;
    if (!$next) throw new AppErr('Aksi tidak dikenal');
    if (in_array($o['status'], ['Selesai', 'Batal'], true)) throw new AppErr('Pesanan sudah ' . strtolower($o['status']));
    $log = json_decode((string)$o['log'], true) ?: [];
    $trx = ''; $dibayar = $o['dibayar'];
    // ---- pembayaran diterima (transfer masuk / bayar di muka): Dr Kas/Bank, Cr Uang Muka Pelanggan ----
    if (($aksi === 'bayar' || !empty($d['bayar'])) && !$dibayar && $aksi !== 'batal') {
        $tgl = today_s(); assert_open_period($tgl, 'Pembayaran pesanan');
        journal_write($tgl, $o['no'], 'Penjualan Online', [
            [on_kasAkun($o), 'Pembayaran pesanan online ' . $o['no'] . ' - ' . $o['nama'] . ' (' . $o['bayar'] . ')', (float)$o['total'], 0],
            [on_akunDp(), 'Uang muka pesanan online ' . $o['no'] . ' - ' . $o['nama'], 0, (float)$o['total']],
        ], '', 'ONDP:' . $o['no'], $u);
        $dibayar = now_s();
        $log[] = ['t' => $dibayar, 's' => 'Dibayar', 'u' => $u['username'], 'k' => 'Pembayaran ' . fmt_num($o['total']) . ' dicatat ke akuntansi (uang muka)'];
    }
    if ($next === 'Selesai') {
        $cfg = on_cfg(); $items = json_decode((string)$o['items'], true) ?: [];
        $GLOBALS['__lok_jual'] = $cfg['lokasi']; $GLOBALS['__promo_ts'] = strtotime($o['tanggal']);
        $GLOBALS['__pos_online'] = ['no' => $o['no'], 'akun' => $dibayar ? on_akunDp() : null];
        $metode = $o['bayar'] === 'Transfer' ? 'Transfer' : 'Tunai';
        $req = ['items' => array_map(fn($l) => ['id' => $l['id'], 'qty' => $l['qty']], $items), 'metode' => $metode, 'bayar' => (float)$o['total'] - (float)$o['ongkir'],
            'pelanggan' => $o['nama'], 'catatan' => 'Pesanan online ' . $o['no'] . ' · ' . $o['hp'], 'member' => $o['member_id']];
        if (empty($d['terimaHargaBaru'])) $req['expect'] = (float)$o['total'] - (float)$o['ongkir'];
        try { $r = k_checkout($req, $u); }
        finally { unset($GLOBALS['__lok_jual'], $GLOBALS['__promo_ts'], $GLOBALS['__pos_online']); }
        $trx = $r['trx']['no'];
        q("UPDATE transaksi SET sumber = 'online' WHERE no = ?", [$trx]);
        if ((float)$o['ongkir'] > 0) {
            $kas = $dibayar ? on_akunDp() : pos_method_acc($metode);
            journal_write(today_s(), $trx, 'Penjualan Online', [[$kas, 'Ongkir pesanan online ' . $o['no'] . ($dibayar ? ' (dari uang muka)' : ''), (float)$o['ongkir'], 0], [akun_exists('4105') ? '4105' : '4103', 'Ongkir ' . $o['no'] . ' - ' . $o['nama'], 0, (float)$o['ongkir']]], '', 'ON:' . $o['no'], $u);
        }
        if (abs($r['trx']['total'] - ((float)$o['total'] - (float)$o['ongkir'])) > 0.5) {
            q('UPDATE pesanan_online SET subtotal = ?, diskon = ?, total = ? WHERE no = ?', [$r['trx']['subtotal'], $r['trx']['diskon'], $r['trx']['total'] + (float)$o['ongkir'], $o['no']]);
            $log[] = ['t' => now_s(), 's' => 'Harga', 'u' => $u['username'], 'k' => 'Total disesuaikan ke harga terbaru' . ($dibayar ? '; selisih dengan uang yang sudah dibayar tetap di akun Uang Muka Pelanggan' : '')];
        }
    }
    // ---- dibatalkan setelah dibayar: uang dikembalikan ke pelanggan ----
    if ($next === 'Batal' && $dibayar) {
        $tgl = today_s(); assert_open_period($tgl, 'Pembatalan pesanan');
        journal_write($tgl, $o['no'] . '-BTL', 'Penjualan Online', [
            [on_akunDp(), 'Batal pesanan online ' . $o['no'] . ' - uang muka dikembalikan', (float)$o['total'], 0],
            [on_kasAkun($o), 'Pengembalian dana pesanan ' . $o['no'] . ' - ' . $o['nama'], 0, (float)$o['total']],
        ], '', 'ONRF:' . $o['no'], $u);
        $log[] = ['t' => now_s(), 's' => 'Refund', 'u' => $u['username'], 'k' => 'Pengembalian dana ' . fmt_num($o['total']) . ' dicatat'];
    }
    if ($aksi !== 'bayar') $log[] = ['t' => now_s(), 's' => $next, 'u' => $u['username'], 'k' => str($d['ket'] ?? '', 200)];
    q('UPDATE pesanan_online SET status = ?, trx_no = ?, log = ?, dibayar = ? WHERE no = ?', [$next, $trx ?: $o['trx_no'], json_encode($log, JSON_UNESCAPED_UNICODE), $dibayar, $o['no']]);
    log_act($u, $aksi === 'bayar' ? 'Dibayar' : $next, 'Toko Online', $o['no']);
    return on_out(row('SELECT * FROM pesanan_online WHERE no = ?', [$o['no']])) + ['stok' => isset($r) ? $r['stok'] : []];
}
function on_produk(array $d): array
{
    $cfg = on_cfg(); $LQ = [];
    foreach (rows('SELECT pid, qty FROM stok_lokasi WHERE lokasi_id = ?', [$cfg['lokasi']]) as $r) $LQ[$r['pid']] = (float)$r['qty'];
    return array_map(fn($p) => ['id' => $p['id'], 'sku' => (string)$p['sku'], 'nama' => $p['nama'], 'kategori' => $p['kategori'], 'harga' => jn($p['harga_jual']), 'foto' => $p['foto'], 'online' => (bool)$p['online'],
        'deskripsi' => (string)($p['deskripsi'] ?? ''), 'stok' => jn($LQ[$p['id']] ?? 0), 'satuan' => $p['satuan']], rows('SELECT * FROM produk ORDER BY nama'));
}
function on_produkSet(array $d, array $u): array
{
    if (isset($d['deskripsi'])) { q('UPDATE produk SET deskripsi = ? WHERE id = ?', [str($d['deskripsi'], 2000), (string)($d['id'] ?? '')]); return ['ok' => 1]; }
    $ids = array_values(array_filter(array_map('strval', (array)($d['ids'] ?? []))));
    if (!$ids) throw new AppErr('Pilih produk');
    q('UPDATE produk SET online = ? WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([!empty($d['online']) ? 1 : 0], $ids));
    log_act($u, !empty($d['online']) ? 'Tampilkan' : 'Sembunyikan', 'Toko Online', count($ids) . ' produk');
    return ['ok' => 1];
}

/* ---------------- katalog publik (tanpa login) ---------------- */
function on_katUrut(array $k): array
{
    $u = []; foreach (rows('SELECT nama, urut FROM kategori') as $i => $r) $u[$r['nama']] = [(int)$r['urut'], $r['nama']];
    usort($k, fn($a, $b) => ($u[$a] ?? [PHP_INT_MAX, $a]) <=> ($u[$b] ?? [PHP_INT_MAX, $b]));
    return $k;
}
function toko_public(string $a, array $d): array
{
    $cfg = on_cfg(); $s = settings_all();
    if (!$cfg['aktif']) throw new AppErr('Toko online sedang tutup. Silakan hubungi kami langsung.');
    if ($a === 'info') {
        $LQ = []; foreach (rows('SELECT pid, qty FROM stok_lokasi WHERE lokasi_id = ?', [$cfg['lokasi']]) as $r) $LQ[$r['pid']] = (float)$r['qty'];
        $izin = ($s['izinMinus'] ?? '0') === '1';
        $PR = promo_labels(); $out = []; $kat = [];
        foreach (rows('SELECT id, sku, nama, kategori, satuan, harga_jual, foto, galeri, deskripsi FROM produk WHERE online = 1 AND harga_jual > 0 ORDER BY nama') as $p) {
            $q = $LQ[$p['id']] ?? 0;
            if ($q <= 0 && !$izin && !$cfg['tampilHabis']) continue;
            $pr = $PR[$p['id']] ?? null; if ($pr && !empty($pr['m'])) $pr = null;
            $out[] = ['id' => $p['id'], 'sku' => (string)$p['sku'], 'nama' => $p['nama'], 'kategori' => $p['kategori'] ?: 'Lainnya', 'satuan' => $p['satuan'], 'harga' => jn($p['harga_jual']), 'foto' => $p['foto'],
                'fotos' => array_values(array_filter(array_merge([(string)$p['foto']], k_galeri($p['galeri'] ?? null)))), 'deskripsi' => (string)($p['deskripsi'] ?? ''), 'stok' => $izin ? 999 : jn(max(0, $q)), 'promo' => $pr ? ['t' => $pr['t'], 'h' => $pr['h'] ?? null] : null];
            $kat[$p['kategori'] ?: 'Lainnya'] = 1;
        }
        return ['toko' => ['nama' => $s['namaToko'] ?? '', 'alamat' => $s['alamat'] ?? '', 'telepon' => $s['telepon'] ?? '', 'info' => $cfg['info'], 'wa' => $cfg['wa'], 'logo' => ''],
            'kirim' => array_merge($cfg['ambil'] ? [['nama' => 'Ambil di toko', 'biaya' => 0]] : [], $cfg['ongkir']), 'cod' => $cfg['cod'], 'rekening' => $cfg['rekening'], 'min' => $cfg['min'],
            'pajak' => jn(num($s['pajak'] ?? 0)), 'kategori' => on_katUrut(array_keys($kat)), 'produk' => $out];
    }
    if ($a === 'hitung' || $a === 'pesan') {
        $items = array_values(array_filter(array_map(fn($i) => ['id' => (string)($i['id'] ?? ''), 'qty' => r3(num($i['qty'] ?? 0))], (array)($d['items'] ?? [])), fn($i) => $i['id'] !== '' && $i['qty'] > 0));
        if (!$items || count($items) > 100) throw new AppErr('Keranjang kosong');
        foreach ($items as $i) if (!val('SELECT id FROM produk WHERE id = ? AND online = 1', [$i['id']])) throw new AppErr('Ada produk yang sudah tidak dijual online. Muat ulang halaman.');
        $hp = member_hp((string)($d['hp'] ?? ''));
        $mem = strlen($hp) >= 8 ? row('SELECT * FROM member WHERE hp = ? AND aktif = 1', [$hp]) : null;
        $c = k_price($items, [], $mem);
        $kirim = array_merge($cfg['ambil'] ? [['nama' => 'Ambil di toko', 'biaya' => 0]] : [], $cfg['ongkir']);
        $ki = (int)num($d['kirim'] ?? 0); $k = $kirim[$ki] ?? ($kirim[0] ?? ['nama' => 'Ambil di toko', 'biaya' => 0]);
        $tot = $c['total'] + $k['biaya'];
        $lines = array_map(fn($l) => ['id' => $l['id'], 'sku' => (string)($l['p']['sku'] ?? ''), 'nama' => $l['p']['nama'], 'satuan' => $l['p']['satuan'], 'qty' => jn($l['qty']), 'harga' => jn($l['harga']), 'diskon' => $l['dp'], 'promo' => $l['promo'], 'net' => $l['net']], $c['lines']);
        $sum = ['lines' => $lines, 'subtotal' => $c['gross'], 'diskon' => $c['diskon'], 'pajak' => $c['pajak'], 'ongkir' => $k['biaya'], 'kirim' => $k['nama'], 'total' => $tot, 'member' => $mem ? $mem['nama'] : null];
        if ($a === 'hitung') return $sum;
        // ---- buat pesanan ----
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        if ((int)val('SELECT COUNT(*) FROM pesanan_online WHERE ip = ? AND tanggal > ?', [$ip, date('Y-m-d H:i:s', time() - 3600)]) >= 5) throw new AppErr('Terlalu banyak pesanan dari perangkat ini. Coba lagi nanti atau hubungi kami lewat WhatsApp.');
        $nama = str($d['nama'] ?? '', 120); if (mb_strlen($nama) < 2) throw new AppErr('Isi nama Anda');
        if (strlen($hp) < 9 || strlen($hp) > 15) throw new AppErr('Isi nomor WhatsApp yang valid');
        $alamat = str($d['alamat'] ?? '', 255);
        if ($k['nama'] !== 'Ambil di toko' && mb_strlen($alamat) < 8) throw new AppErr('Isi alamat pengiriman lengkap');
        $bayar = ($d['bayar'] ?? '') === 'COD' && $cfg['cod'] ? 'COD' : 'Transfer';
        if ($cfg['min'] > 0 && $c['total'] < $cfg['min']) throw new AppErr('Minimal belanja online ' . fmt_num($cfg['min']));
        $LQ = []; foreach (rows('SELECT pid, qty FROM stok_lokasi WHERE lokasi_id = ?', [$cfg['lokasi']]) as $r) $LQ[$r['pid']] = (float)$r['qty'];
        if (($s['izinMinus'] ?? '0') !== '1') foreach ($c['lines'] as $l) if ($l['qty'] > ($LQ[$l['id']] ?? 0) + 1e-9) throw new AppErr('Stok "' . $l['p']['nama'] . '" tinggal ' . jn(max(0, $LQ[$l['id']] ?? 0)));
        $no = with_write(function () use ($sum, $nama, $hp, $alamat, $d, $k, $bayar, $mem, $ip) {
            $no = k_nextNo('WEB'); $kode = strtoupper(bin2hex(random_bytes(3)));
            q('INSERT INTO pesanan_online (no, kode, tanggal, nama, hp, alamat, catatan, kirim, ongkir, bayar, items, subtotal, diskon, total, member_id, status, ip, log) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$no, $kode, now_s(), $nama, $hp, $alamat, str($d['catatan'] ?? '', 255), $k['nama'], $k['biaya'], $bayar, json_encode($sum['lines'], JSON_UNESCAPED_UNICODE), $sum['subtotal'], $sum['diskon'], $sum['total'], $mem['id'] ?? '', 'Baru', $ip,
                 json_encode([['t' => now_s(), 's' => 'Baru', 'u' => 'pelanggan', 'k' => '']])]);
            bump_rev();
            return $no;
        });
        return ['no' => $no] + $sum + ['bayar' => $bayar, 'nama' => $nama, 'hp' => $hp, 'alamat' => $alamat, 'wa' => $cfg['wa'], 'rekening' => $cfg['rekening'], 'toko' => $s['namaToko'] ?? ''];
    }
    if ($a === 'cek') {
        $o = row('SELECT * FROM pesanan_online WHERE no = ? AND hp = ?', [strtoupper(str($d['no'] ?? '', 30)), member_hp((string)($d['hp'] ?? ''))]);
        if (!$o) throw new AppErr('Pesanan tidak ditemukan. Periksa nomor pesanan dan nomor WhatsApp.');
        $x = on_out($o); unset($x['log'], $x['trx']);
        return $x;
    }
    throw new AppErr('Aksi tidak dikenal');
}

/* ======================================================================
 *  LAYANAN MANDIRI KARYAWAN (absen.html)
 * ==================================================================== */
function sy_kry(array $u): array
{
    $k = row("SELECT * FROM karyawan WHERE user_id = ? AND status = 'Aktif'", [$u['id']]);
    if (!$k) throw new AppErr('Akun Anda belum terhubung dengan data karyawan. Minta admin menghubungkannya di SDM & Penggajian → Karyawan.', 'NOKRY');
    return $k;
}
function sy_lok(array $k): array
{
    $id = $k['lokasi_id'] ?: lok_jual();
    $l = row('SELECT id, nama, lat, lng, radius FROM lokasi WHERE id = ?', [$id]) ?: ['id' => $id, 'nama' => '-', 'lat' => null, 'lng' => null, 'radius' => 100];
    return ['id' => $l['id'], 'nama' => $l['nama'], 'lat' => $l['lat'] !== null ? (float)$l['lat'] : null, 'lng' => $l['lng'] !== null ? (float)$l['lng'] : null, 'radius' => (int)$l['radius']];
}
function sy_jarak(float $a1, float $o1, float $a2, float $o2): int
{
    $R = 6371000; $p1 = deg2rad($a1); $p2 = deg2rad($a2); $dp = deg2rad($a2 - $a1); $dl = deg2rad($o2 - $o1);
    $h = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return (int)round(2 * $R * asin(min(1, sqrt($h))));
}
function sy_home(array $d, array $u): array
{
    $k = sy_kry($u); $td = today_s(); $p = substr($td, 0, 7);
    $y = add_days($td, -1);
    $D = sd_days([$k], t3_perAwal($p) < $y ? t3_perAwal($p) : $y, $td)[$k['id']];
    $x = $D[$td];
    // shift malam kemarin yang belum absen pulang
    $open = row('SELECT tanggal, masuk FROM absensi WHERE karyawan_id = ? AND tanggal = ? AND masuk IS NOT NULL AND pulang IS NULL', [$k['id'], $y]);
    $per = array_filter($D, fn($v, $t) => substr($t, 0, 7) === $p, ARRAY_FILTER_USE_BOTH);
    $slip = array_map(fn($g) => ['id' => $g['id'], 'periode' => $g['periode'], 'total' => jn($g['total']), 'status' => $g['status']], rows("SELECT id, periode, total, status FROM gaji WHERE karyawan_id = ? AND status IN ('Final','Dibayar') ORDER BY periode DESC LIMIT 12", [$k['id']]));
    $kpi = $k['kpi_id'] ? kp_skor1($k, $p) : null;
    return ['karyawan' => ['kode' => $k['kode'], 'nama' => $k['nama'], 'posisi' => $k['posisi']], 'lokasi' => sy_lok($k), 'tanpaGps' => sd_set_all()['tanpaGps'],
        'hariIni' => sd_absenOut($x, $td), 'kemarinBuka' => $open ? ['tanggal' => $y, 'masuk' => substr($open['masuk'], 11, 5)] : null,
        'rekap' => sd_rekap1($per, $k, t3_perAwal($p), t3_perAkhir($p)), 'sisaCuti' => max(0, (int)$k['jatah_cuti'] - sd_cutiTerpakai($k, substr($td, 0, 4))), 'jatahCuti' => (int)$k['jatah_cuti'],
        'pengajuan' => array_map(fn($r) => sd_pjOut($r, [$k['id'] => $k['nama']]), rows('SELECT * FROM pengajuan WHERE karyawan_id = ? ORDER BY dibuat DESC LIMIT 10', [$k['id']])),
        'slip' => $slip, 'kpi' => $kpi ? ['skor' => $kpi['skor'], 'grade' => $kpi['grade'], 'tpl' => $kpi['tpl'], 'indikator' => $kpi['indikator']] : null, 'jam' => date('H:i'), 'tanggal' => $td,
        // v5.5
        'atasan' => $k['atasan_id'] !== '' ? (sd_namaMap()[$k['atasan_id']] ?? '') : '',
        'tim' => (int)val("SELECT COUNT(*) FROM karyawan WHERE atasan_id = ? AND status = 'Aktif'", [$k['id']]),
        'timMenunggu' => (int)val("SELECT COUNT(*) FROM pengajuan p JOIN karyawan k ON k.id = p.karyawan_id WHERE k.atasan_id = ? AND p.status = 'Menunggu'", [$k['id']]),
        'rekan' => array_values(array_filter(sd_meta()['karyawan'], fn($r) => $r['id'] !== $k['id'])),
        'gantiHariIni' => $x['gn'] ?? '',
        'akses' => sd_set_all()['aksesJam'] && !akses_bebas(row('SELECT * FROM users WHERE id = ?', [$u['id']]) ?: []) ? akses_teks(akses_jendela($k, time()), time()) : null];
}
function sy_absen(array $d, array $u): array
{
    $k = sy_kry($u); $jenis = ($d['jenis'] ?? '') === 'pulang' ? 'pulang' : 'masuk';
    if (empty($d['foto'])) throw new AppErr('Ambil foto selfie terlebih dahulu');
    $L = sy_lok($k); $jarak = null; $lat = isset($d['lat']) && $d['lat'] !== null ? num($d['lat']) : null; $lng = isset($d['lng']) && $d['lng'] !== null ? num($d['lng']) : null;
    if ($L['lat'] !== null && !sd_set_all()['tanpaGps']) {
        if ($lat === null || $lng === null) throw new AppErr('Lokasi HP tidak terbaca. Aktifkan GPS / izin lokasi untuk browser, lalu coba lagi.');
        $jarak = sy_jarak($L['lat'], $L['lng'], $lat, $lng);
        $acc = (int)min(50, max(0, num($d['akurasi'] ?? 0)));
        if ($jarak - $acc > $L['radius']) throw new AppErr('Anda berada ' . number_format($jarak, 0, ',', '.') . ' m dari ' . $L['nama'] . ' (batas ' . $L['radius'] . ' m). Absen hanya bisa dilakukan di lokasi kerja.');
    } elseif ($lat !== null && $lng !== null && $L['lat'] !== null) $jarak = sy_jarak($L['lat'], $L['lng'], $lat, $lng);
    $td = today_s(); $now = now_s(); $hm = substr($now, 11, 5);
    if ($jenis === 'masuk') {
        $ex = row('SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = ?', [$k['id'], $td]);
        if ($ex && $ex['masuk']) throw new AppErr('Anda sudah absen masuk hari ini pukul ' . substr($ex['masuk'], 11, 5));
        if ($ex && $ex['status'] !== 'Hadir') throw new AppErr('Hari ini tercatat ' . $ex['status'] . '. Hubungi atasan bila ingin mengubah.');
        $s = sd_days([$k], $td, $td)[$k['id']][$td]['s'];
        $telat = 0; if ($s) { $dl = sd_hm($hm) - sd_hm($s['masuk']); if ($dl > (int)$s['toleransi'] && $dl < 12 * 60) $telat = $dl; }
        $foto = t3_foto((string)$d['foto'], 'absen/' . date('Ym'), $k['kode'] . 'm', 900);
        q('INSERT INTO absensi (id, karyawan_id, tanggal, shift_id, j_masuk, j_pulang, masuk, foto_masuk, lat_masuk, lng_masuk, jarak_masuk, status, telat, sumber) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [uid('AB'), $k['id'], $td, $s['id'] ?? '', $s['masuk'] ?? '', $s['pulang'] ?? '', $now, $foto, $lat, $lng, $jarak, 'Hadir', $telat, 'HP']);
        log_act($u, 'Absen masuk', 'SDM', $k['nama'] . ' ' . $hm . ($telat ? ' (telat ' . $telat . ' mnt)' : ''));
        return ['ok' => 1, 'jam' => $hm, 'telat' => $telat, 'jarak' => $jarak] + sy_home($d, $u);
    }
    $ex = row('SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = ? AND masuk IS NOT NULL AND pulang IS NULL', [$k['id'], $td])
        ?: row('SELECT * FROM absensi WHERE karyawan_id = ? AND tanggal = ? AND masuk IS NOT NULL AND pulang IS NULL', [$k['id'], add_days($td, -1)]);
    if (!$ex) {
        if (row('SELECT id FROM absensi WHERE karyawan_id = ? AND tanggal = ? AND pulang IS NOT NULL', [$k['id'], $td])) throw new AppErr('Anda sudah absen pulang hari ini');
        throw new AppErr('Anda belum absen masuk hari ini');
    }
    $cepat = 0;
    if ($ex['j_pulang'] !== '' && $ex['tanggal'] === $td && $ex['j_pulang'] > $ex['j_masuk']) $cepat = max(0, sd_hm($ex['j_pulang']) - sd_hm($hm));
    $foto = t3_foto((string)$d['foto'], 'absen/' . date('Ym'), $k['kode'] . 'p', 900);
    q('UPDATE absensi SET pulang = ?, foto_pulang = ?, lat_pulang = ?, lng_pulang = ?, jarak_pulang = ?, pulang_cepat = ? WHERE id = ?', [$now, $foto, $lat, $lng, $jarak, $cepat, $ex['id']]);
    log_act($u, 'Absen pulang', 'SDM', $k['nama'] . ' ' . $hm);
    return ['ok' => 1, 'jam' => $hm, 'cepat' => $cepat, 'jarak' => $jarak] + sy_home($d, $u);
}
function sy_riwayat(array $d, array $u): array
{
    $k = sy_kry($u); $p = t3_per((string)($d['periode'] ?? substr(today_s(), 0, 7)));
    $f = t3_perAwal($p); $t = t3_perAkhir($p);
    $D = sd_days([$k], $f, $t)[$k['id']];
    $rows = []; foreach ($D as $tg => $x) if ($tg <= today_s()) $rows[] = sd_absenOut($x, $tg);
    return ['periode' => $p, 'rows' => array_reverse($rows), 'rekap' => sd_rekap1($D, $k, $f, $t)];
}
function sy_ajukan(array $d, array $u): array
{
    $k = sy_kry($u);
    $j = (string)($d['jenis'] ?? ''); if (!in_array($j, PJ_JENIS, true)) throw new AppErr('Jenis pengajuan tidak valid');
    if ($j === 'Lembur' || $j === 'Akses') {
        if ($j === 'Lembur' && empty($d['jamMulai']) && !empty($d['jam'])) {   // cara lama: hanya lama jam
            $m = (string)($d['mulai'] ?? ''); if (!is_date($m)) throw new AppErr('Tanggal tidak valid');
            $menit = (int)round(num($d['jam']) * 60); if ($menit < 15 || $menit > 720) throw new AppErr('Isi lama lembur (0,25 – 12 jam)');
            $al = str($d['alasan'] ?? '', 255); if ($al === '') throw new AppErr('Isi alasan / keterangan');
            $id = uid('PJ'); q('INSERT INTO pengajuan (id, karyawan_id, jenis, mulai, selesai, menit, alasan, status, diajukan_oleh, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?)', [$id, $k['id'], 'Lembur', $m, $m, $menit, $al, 'Menunggu', $u['username'], now_s()]);
        } else $id = sd_buatJam($k, $j, ['tanggal' => $d['tanggal'] ?? $d['mulai'] ?? ''] + $d, $u['username'], 'Menunggu');
    } else {
        $m = (string)($d['mulai'] ?? ''); $s = (string)($d['selesai'] ?? $m);
        if (!is_date($m) || !is_date($s) || $s < $m) throw new AppErr('Tanggal tidak valid');
        if (diff_days($s, $m) > 30) throw new AppErr('Maksimal 31 hari per pengajuan');
        $al = str($d['alasan'] ?? '', 255); if ($al === '') throw new AppErr('Isi alasan / keterangan');
        if ($j === 'Cuti' && $m < today_s()) throw new AppErr('Cuti diajukan untuk hari ini atau yang akan datang');
        if ((int)val("SELECT COUNT(*) FROM pengajuan WHERE karyawan_id = ? AND jenis IN ('Izin','Sakit','Cuti') AND status IN ('Menunggu','Disetujui') AND mulai <= ? AND selesai >= ?", [$k['id'], $s, $m])) throw new AppErr('Sudah ada pengajuan izin/sakit/cuti pada tanggal tersebut');
        $pg = (string)($d['pengganti'] ?? '');
        if ($pg !== '') { if ($pg === $k['id'] || !val("SELECT id FROM karyawan WHERE id = ? AND status = 'Aktif'", [$pg])) throw new AppErr('Pilih rekan pengganti yang aktif'); }
        $lamp = !empty($d['lampiran']) ? t3_foto((string)$d['lampiran'], 'pengajuan', $k['kode'], 2500) : '';
        $id = uid('PJ');
        q('INSERT INTO pengajuan (id, karyawan_id, jenis, mulai, selesai, menit, alasan, lampiran, status, pengganti_id, diajukan_oleh, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [$id, $k['id'], $j, $m, $s, 0, $al, $lamp, 'Menunggu', $pg, $u['username'], now_s()]);
    }
    $p = row('SELECT * FROM pengajuan WHERE id = ?', [$id]);
    log_act($u, 'Ajukan ' . $j, 'SDM', $k['nama'] . ' · ' . sd_pjTeks($p));
    if ($k['atasan_id'] !== '') sd_wa($k['atasan_id'], '📝 Pengajuan baru dari ' . $k['nama'] . ': ' . sd_pjTeks($p) . "\nAlasan: " . $p['alasan']
        . ($p['pengganti_id'] !== '' ? "\nUsulan pengganti: " . (sd_namaMap()[$p['pengganti_id']] ?? '') : '') . "\nSetujui / tolak di " . sd_appUrl() . '/absen.html#tim');
    return sy_home($d, $u);
}
function sy_batalAju(array $d, array $u): array
{
    $k = sy_kry($u);
    $p = row('SELECT * FROM pengajuan WHERE id = ? AND karyawan_id = ?', [(string)($d['id'] ?? ''), $k['id']]);
    if (!$p || $p['status'] !== 'Menunggu') throw new AppErr('Hanya pengajuan yang masih menunggu yang dapat dibatalkan');
    q('DELETE FROM pengajuan WHERE id = ?', [$p['id']]);
    return sy_home($d, $u);
}
function sy_slip(array $d, array $u): array
{
    $k = sy_kry($u);
    $g = row("SELECT * FROM gaji WHERE id = ? AND karyawan_id = ? AND status IN ('Final','Dibayar')", [(string)($d['id'] ?? ''), $k['id']]);
    if (!$g) throw new AppErr('Slip gaji tidak ditemukan');
    $s = settings_all();
    return sd_gajiOut($g) + ['toko' => ['nama' => $s['namaToko'], 'alamat' => $s['alamat'], 'telepon' => $s['telepon']]];
}

/* ======================================================================
 *  MODE OFFLINE KASIR — transaksi yang tersimpan di perangkat saat internet putus
 *  dikirim ulang di sini. Kunci unik mencegah transaksi tercatat dua kali.
 *  Harga yang dibayar pelanggan dipakai apa adanya (promo tidak dihitung ulang),
 *  stok boleh minus karena barang sudah berpindah tangan.
 * ==================================================================== */
function k_ping(): array { return ['t' => now_s()]; }
function k_checkoutOffline(array $d, array $u): array
{
    $k = str($d['kunci'] ?? '', 60);
    if ($k === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $k)) throw new AppErr('Kunci transaksi offline tidak valid');
    $ex = val('SELECT trx_no FROM offline_sync WHERE kunci = ?', [$k]);
    if ($ex) return ['dup' => 1, 'no' => $ex, 'stok' => []];
    $ts = strtotime((string)($d['waktu'] ?? ''));
    $w = (!$ts || $ts > time() + 600 || $ts < time() - 30 * 86400) ? now_s() : date('Y-m-d H:i:s', $ts);
    $GLOBALS['__no_promo'] = 1; $GLOBALS['__izin_minus'] = 1; $GLOBALS['__trx_time'] = $w;
    try {
        $r = k_checkout(['items' => $d['items'] ?? [], 'gd' => $d['gd'] ?? [], 'metode' => $d['metode'] ?? 'Tunai', 'bayar' => $d['bayar'] ?? 0,
            'pelanggan' => $d['pelanggan'] ?? '', 'catatan' => trim('[Offline] ' . str($d['catatan'] ?? '', 400))], $u);
    } finally { unset($GLOBALS['__no_promo'], $GLOBALS['__izin_minus'], $GLOBALS['__trx_time']); }
    q('INSERT INTO offline_sync (kunci, trx_no, dibuat) VALUES (?,?,?)', [$k, $r['trx']['no'], now_s()]);
    q("UPDATE transaksi SET sumber = 'offline' WHERE no = ?", [$r['trx']['no']]);
    log_act($u, 'Sinkron offline', 'Kasir', $k . ' → ' . $r['trx']['no']);
    return ['no' => $r['trx']['no'], 'total' => $r['trx']['total'], 'stok' => $r['stok']];
}

/* ======================================================================
 *  IMPORT EXCEL & DATA CONTOH — Inventaris Aset dan Karyawan
 *  Semua baris divalidasi dalam satu transaksi: bila ada yang salah, tidak ada yang tersimpan.
 * ==================================================================== */
function t3_lokMap(): array { $m = []; foreach (lokasi_all() as $l) { $m[mb_strtolower($l['nama'])] = $l['id']; $m[mb_strtolower((string)$l['kode'])] = $l['id']; $m[mb_strtolower($l['id'])] = $l['id']; } return $m; }
/** Angka dari Excel/teks: "120.000" & "1.250.000,50" (format Indonesia) maupun 120000 */
function t3_num($v): float
{
    if (is_int($v) || is_float($v)) return (float)$v;
    $t = preg_replace('/[^\d.,-]/', '', (string)$v);
    if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $t) || preg_match('/^-?\d+,\d{1,2}$/', $t)) $t = str_replace(',', '.', str_replace('.', '', $t));
    else $t = str_replace(',', '', $t);
    return is_numeric($t) ? (float)$t : 0.0;
}
function t3_tgl($v): string
{
    $v = trim((string)$v); if ($v === '') return '';
    if (is_date($v)) return $v;
    if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $v, $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3])) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    if (preg_match('#^(\d{4})[/.](\d{1,2})[/.](\d{1,2})$#', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    return 'x';
}
function t3_batch(array $items, callable $fn): array
{
    if (!$items) throw new AppErr('Tidak ada baris data untuk diimpor');
    if (count($items) > 1000) throw new AppErr('Maksimal 1.000 baris per import');
    $n = ['baru' => 0, 'ubah' => 0, 'lewat' => 0]; $err = [];
    foreach ($items as $i => $x) {
        try { $n[$fn((array)$x)]++; }
        catch (AppErr $e) { $err[] = 'baris ' . (int)($x['_row'] ?? $i + 2) . ': ' . $e->getMessage(); }
    }
    if ($err) throw new AppErr(count($err) . ' baris bermasalah, belum ada yang disimpan — ' . implode(' · ', array_slice($err, 0, 4)) . (count($err) > 4 ? ' · …' : ''));
    return $n;
}
/** Satu baris aset dari Excel. Aset lama ("Sudah dimiliki") otomatis diberi akumulasi penyusutan s.d. bulan lalu. */
function as_importRow(array $x, array $u, array $LM): string
{
    $kode = strtoupper(str($x['kode'] ?? '', 30));
    if ($kode !== '' && val('SELECT id FROM aset WHERE kode = ?', [$kode])) return 'lewat';
    $nama = str($x['nama'] ?? '', 150); if ($nama === '') throw new AppErr('Nama aset kosong');
    $tgl = t3_tgl($x['tglBeli'] ?? ''); if ($tgl === '' || $tgl === 'x') throw new AppErr('Tanggal perolehan tidak valid (pakai 2025-01-31 atau 31/01/2025)');
    $harga = r2(t3_num($x['harga'] ?? 0)); if ($harga <= 0) throw new AppErr('Harga perolehan "' . $nama . '" kosong');
    $umur = (int)t3_num($x['umur'] ?? 0); if ($umur <= 0) $umur = 48;
    $sisa = r2(max(0, t3_num($x['nilaiSisa'] ?? 0)));
    $sb = mb_strtolower(trim((string)($x['sumber'] ?? '')));
    $sumber = in_array($sb, ['kas', 'tunai', 'cash'], true) ? 'Kas' : (in_array($sb, ['bank', 'transfer'], true) ? 'Bank' : 'Modal');
    $sa = $x['susutAwal'] ?? '';
    if ($sumber === 'Modal' && ($sa === '' || $sa === null)) {
        $base = max(0, $harga - $sisa); $mon = $umur > 0 ? $base / $umur : 0;
        $bln = max(0, ((int)date('Y') - (int)substr($tgl, 0, 4)) * 12 + (int)date('n') - (int)substr($tgl, 5, 2));   // bulan perolehan s.d. bulan lalu
        $sa = (string)min($base, round($mon * $bln));
    } elseif ($sa !== '' && $sa !== null) $sa = (string)t3_num($sa);
    $kond = (string)($x['kondisi'] ?? ''); $kk = array_values(array_filter(ASET_KONDISI, fn($k) => mb_strtolower($k) === mb_strtolower(trim($kond))));
    $kat = str($x['kategori'] ?? '', 60);
    $katM = array_values(array_filter(ASET_KATEGORI, fn($k) => mb_strtolower($k) === mb_strtolower($kat)));
    as_save(['nama' => $nama, 'kode' => $kode, 'kategori' => $katM[0] ?? ($kat ?: 'Lainnya'), 'lokasi' => $LM[mb_strtolower(trim((string)($x['lokasi'] ?? '')))] ?? lok_jual(),
        'merek' => $x['merek'] ?? '', 'noSeri' => $x['noSeri'] ?? '', 'pj' => $x['pj'] ?? '', 'pemasok' => $x['pemasok'] ?? '', 'catatan' => $x['catatan'] ?? '',
        'tglBeli' => $tgl, 'harga' => $harga, 'umur' => $umur, 'nilaiSisa' => $sisa, 'sumber' => $sumber, 'susutAwal' => $sa, 'kondisi' => $kk[0] ?? 'Baik'], $u);
    return 'baru';
}
function as_import(array $d, array $u): array
{
    $LM = t3_lokMap();
    $n = t3_batch((array)($d['items'] ?? []), fn($x) => as_importRow($x, $u, $LM));
    log_act($u, 'Import Excel', 'Aset', $n['baru'] . ' baru, ' . $n['lewat'] . ' dilewati');
    return $n;
}
const ASET_CONTOH = [
    ['kode' => 'AST-C01', 'nama' => 'Mesin kasir POS + printer struk', 'kategori' => 'Mesin & peralatan kasir', 'merek' => 'Epson TM-T82X', 'pj' => 'Kepala toko', 'bulanLalu' => 14, 'harga' => 6500000, 'umur' => 48, 'nilaiSisa' => 0, 'kondisi' => 'Baik'],
    ['kode' => 'AST-C02', 'nama' => 'Rak gondola besi 5 susun (4 unit)', 'kategori' => 'Rak & etalase', 'merek' => '', 'pj' => 'Staf gudang', 'bulanLalu' => 26, 'harga' => 8400000, 'umur' => 96, 'nilaiSisa' => 800000, 'kondisi' => 'Baik'],
    ['kode' => 'AST-C03', 'nama' => 'Etalase kaca 1,5 m', 'kategori' => 'Rak & etalase', 'merek' => '', 'pj' => 'Kepala toko', 'bulanLalu' => 20, 'harga' => 3200000, 'umur' => 60, 'nilaiSisa' => 300000, 'kondisi' => 'Perlu perbaikan'],
    ['kode' => 'AST-C04', 'nama' => 'Kulkas showcase 2 pintu', 'kategori' => 'Elektronik', 'merek' => 'Polytron', 'pj' => 'Kepala toko', 'bulanLalu' => 9, 'harga' => 7800000, 'umur' => 60, 'nilaiSisa' => 500000, 'kondisi' => 'Baik'],
    ['kode' => 'AST-C05', 'nama' => 'Timbangan digital 30 kg', 'kategori' => 'Peralatan gudang', 'merek' => 'Camry', 'pj' => 'Staf gudang', 'bulanLalu' => 5, 'harga' => 950000, 'umur' => 36, 'nilaiSisa' => 0, 'kondisi' => 'Baik'],
];
function as_contoh(array $d, array $u): array
{
    $LM = t3_lokMap(); $n = 0;
    foreach (ASET_CONTOH as $c) {
        $c['tglBeli'] = date('Y-m-d', strtotime(date('Y-m-15') . ' -' . $c['bulanLalu'] . ' month')); $c['sumber'] = 'Sudah dimiliki';
        $c['lokasi'] = $c['pj'] === 'Staf gudang' && isset(lokasi_all()[lok_masuk()]) ? lok_masuk() : lok_jual();
        if (as_importRow($c, $u, $LM) === 'baru') $n++;
    }
    log_act($u, 'Data contoh', 'Aset', $n . ' aset');
    return ['n' => $n];
}
/* ---- karyawan ---- */
function sd_hariParse(string $v): string
{
    $v = mb_strtolower(trim($v)); if ($v === '') return '123456';
    if (preg_match('/^[1-7]+$/', $v)) return implode('', array_unique(str_split($v)));
    if (preg_match('/setiap|tiap hari|semua/', $v)) return '1234567';
    $H = ['sen' => 1, 'sel' => 2, 'rab' => 3, 'kam' => 4, 'jum' => 5, 'sab' => 6, 'min' => 7, 'ahad' => 7];
    $idx = function ($w) use ($H) { foreach ($H as $k => $n) if (strpos($w, $k) === 0) return $n; return 0; };
    if (preg_match('/^([a-z]+)\s*(?:-|–|s\.?d\.?|sampai)\s*([a-z]+)$/u', $v, $m) && ($a = $idx($m[1])) && ($b = $idx($m[2]))) { $o = ''; for ($i = $a; ; $i = $i % 7 + 1) { $o .= $i; if ($i === $b || strlen($o) > 7) break; } return $o; }
    $o = ''; foreach (preg_split('/[\s,;\/]+/', $v) as $w) if ($n = $idx($w)) $o .= $n;
    if ($o === '') throw new AppErr('Hari kerja "' . $v . '" tidak dikenali (contoh: Sen-Sab, atau 123456)');
    return implode('', array_unique(str_split($o)));
}
function sd_kryImportRow(array $x, array $u, array &$M): string
{
    $nama = str($x['nama'] ?? '', 120); if ($nama === '') throw new AppErr('Nama karyawan kosong');
    $kode = strtoupper(str($x['kode'] ?? '', 20));
    $old = $kode !== '' ? row('SELECT * FROM karyawan WHERE kode = ?', [$kode]) : null;
    $tm = t3_tgl($x['tglMasuk'] ?? ''); if ($tm === 'x') throw new AppErr('Tanggal masuk "' . $nama . '" tidak valid');
    $sh = mb_strtolower(trim((string)($x['shift'] ?? '')));
    $shift = $sh === '' ? ($old['shift_id'] ?? ($M['shiftDef'] ?? '')) : ($M['shift'][$sh] ?? null);
    if ($shift === null) throw new AppErr('Shift "' . $x['shift'] . '" belum ada. Buat dulu di Jadwal & shift');
    $un = ltrim(mb_strtolower(trim((string)($x['username'] ?? ''))), '@');
    $uid = $un === '' ? ($old['user_id'] ?? '') : ($M['user'][$un] ?? null);
    if ($uid === null) {
        // akun belum ada: dibuat otomatis (khusus Administrator)
        if (empty($u['admin'])) throw new AppErr('Akun login "' . $un . '" belum ada. Hanya Administrator yang bisa membuat akun lewat import');
        require_once __DIR__ . '/sistem.php';
        $jn = mb_strtolower(trim((string)($x['jabatanLogin'] ?? '')));
        $JM = s_jabMap(); $jab = $jn === '' ? s_jabPosisi((string)($x['posisi'] ?? ($old['posisi'] ?? ''))) : ($JM[$jn] ?? null);
        if ($jab === null) throw new AppErr('Jabatan login "' . $x['jabatanLogin'] . '" tidak ada');
        [$uid, $pw, $jnm] = s_akunBaru($un, $nama, $jab, trim((string)($x['password'] ?? '')), (string)($x['email'] ?? ''), $u);
        $M['user'][$un] = $uid;
        $GLOBALS['__akun_baru'][] = ['username' => $un, 'nama' => $nama, 'jabatan' => $jnm, 'password' => $pw, 'status' => 'Baru · ' . ($kode ?: 'karyawan baru')];
    }
    $kp = mb_strtolower(trim((string)($x['kpi'] ?? '')));
    $kpi = $kp === '' ? ($old['kpi_id'] ?? '') : ($M['kpi'][$kp] ?? null);
    if ($kpi === null) throw new AppErr('Template KPI "' . $x['kpi'] . '" tidak ditemukan');
    $tg = mb_strtolower(trim((string)($x['tipeGaji'] ?? '')));
    $num = fn($k, $def = 0) => ($x[$k] ?? '') === '' || $x[$k] === null ? $def : t3_num($x[$k]);
    sd_krySave(['id' => $old['id'] ?? '', 'kode' => $kode, 'nama' => $nama, 'posisi' => $x['posisi'] ?? ($old['posisi'] ?? ''), 'userId' => $uid,
        'lokasi' => $M['lok'][mb_strtolower(trim((string)($x['lokasi'] ?? '')))] ?? ($old['lokasi_id'] ?? lok_jual()), 'hp' => $x['hp'] ?? ($old['hp'] ?? ''), 'email' => $x['email'] ?? ($old['email'] ?? ''),
        'alamat' => $x['alamat'] ?? ($old['alamat'] ?? ''), 'tglMasuk' => $tm ?: ($old['tgl_masuk'] ?? ''), 'status' => 'Aktif', 'tipeGaji' => strpos($tg, 'hari') !== false ? 'Harian' : ($tg === '' && $old ? $old['tipe_gaji'] : 'Bulanan'),
        'gajiPokok' => $num('gajiPokok', $old['gaji_pokok'] ?? 0), 'tunjangan' => $num('tunjangan', $old['tunjangan'] ?? 0), 'uangMakan' => $num('uangMakan', $old['uang_makan'] ?? 0),
        'lemburJam' => $num('lemburJam', $old['lembur_jam'] ?? 0), 'dendaTelat' => $num('dendaTelat', $old['denda_telat'] ?? 0), 'jatahCuti' => $num('jatahCuti', $old['jatah_cuti'] ?? 12),
        'shift' => $shift, 'hariKerja' => ($x['hariKerja'] ?? '') === '' ? ($old['hari_kerja'] ?? '123456') : sd_hariParse((string)$x['hariKerja']), 'kpi' => $kpi,
        'bank' => $x['bank'] ?? ($old['bank'] ?? ''), 'rekening' => $x['rekening'] ?? ($old['rekening'] ?? ''), 'catatan' => $old['catatan'] ?? ''], $u);
    return $old ? 'ubah' : 'baru';
}
function sd_kryMaps(): array
{
    $M = ['shift' => [], 'user' => [], 'kpi' => [], 'lok' => t3_lokMap()];
    foreach (sd_shifts() as $s) { $M['shift'][mb_strtolower($s['nama'])] = $s['id']; $M['shift'][mb_strtolower($s['id'])] = $s['id']; if (!isset($M['shiftDef']) && $s['aktif']) $M['shiftDef'] = $s['id']; }
    foreach (rows('SELECT id, username FROM users') as $r) $M['user'][mb_strtolower($r['username'])] = $r['id'];
    foreach (rows('SELECT id, nama FROM kpi_tpl') as $r) { $M['kpi'][mb_strtolower($r['nama'])] = $r['id']; $M['kpi'][mb_strtolower($r['id'])] = $r['id']; }
    return $M;
}
function sd_kryImport(array $d, array $u): array
{
    $M = sd_kryMaps(); $GLOBALS['__akun_baru'] = [];
    $n = t3_batch((array)($d['items'] ?? []), function ($x) use ($u, &$M) { return sd_kryImportRow($x, $u, $M); });
    // kolom Atasan diproses setelah semua baris tersimpan (atasan boleh ada di baris bawahnya)
    $err = []; $K = rows('SELECT id, kode, nama FROM karyawan'); $byK = []; $byN = [];
    foreach ($K as $k) { $byK[mb_strtolower($k['kode'])] = $k['id']; $byN[mb_strtolower(trim($k['nama']))][] = $k['id']; }
    $cari = function (string $v) use ($byK, $byN) { $v = mb_strtolower(trim($v)); if (isset($byK[$v])) return $byK[$v]; $c = $byN[$v] ?? []; return count($c) === 1 ? $c[0] : null; };
    foreach ((array)($d['items'] ?? []) as $i => $x) {
        $a = trim((string)($x['atasan'] ?? '')); if ($a === '') continue;
        $kid = $cari((string)($x['kode'] ?? '')) ?? $cari((string)($x['nama'] ?? ''));
        $aid = $cari($a); $row = (int)($x['_row'] ?? $i + 2);
        if (!$aid) { $err[] = 'baris ' . $row . ': atasan "' . $a . '" tidak ditemukan (isi kode atau nama karyawan)'; continue; }
        if (!$kid || $aid === $kid) { $err[] = 'baris ' . $row . ': atasan tidak boleh diri sendiri'; continue; }
        q('UPDATE karyawan SET atasan_id = ? WHERE id = ?', [$aid, $kid]);
    }
    foreach (rows("SELECT id, nama, atasan_id FROM karyawan WHERE atasan_id <> ''") as $k) if (sd_atasanLoop($k['id'], $k['atasan_id'])) $err[] = 'susunan atasan ' . $k['nama'] . ' berputar';
    if ($err) throw new AppErr(count($err) . ' baris bermasalah, belum ada yang disimpan — ' . implode(' · ', array_slice(array_unique($err), 0, 4)));
    log_act($u, 'Import Excel', 'Karyawan', $n['baru'] . ' baru, ' . $n['ubah'] . ' diperbarui, ' . count($GLOBALS['__akun_baru']) . ' akun login');
    return $n + ['akun' => $GLOBALS['__akun_baru']];
}
const KRY_CONTOH = [
    ['kode' => 'K-C01', 'nama' => 'Rina Marlina', 'posisi' => 'Kasir', 'shift' => 'Pagi', 'hariKerja' => 'Sen-Sab', 'tipeGaji' => 'Bulanan', 'gajiPokok' => 2800000, 'tunjangan' => 200000, 'uangMakan' => 15000, 'lemburJam' => 18000, 'dendaTelat' => 10000, 'kpi' => 'KPI Kasir', 'hp' => '081234560001', 'bulanLalu' => 18],
    ['kode' => 'K-C02', 'nama' => 'Sari Wulandari', 'posisi' => 'Kasir', 'shift' => 'Siang', 'hariKerja' => 'Sel-Min', 'tipeGaji' => 'Bulanan', 'gajiPokok' => 2800000, 'tunjangan' => 200000, 'uangMakan' => 15000, 'lemburJam' => 18000, 'dendaTelat' => 10000, 'kpi' => 'KPI Kasir', 'hp' => '081234560002', 'bulanLalu' => 7],
    ['kode' => 'K-C03', 'nama' => 'Dedi Kurniawan', 'posisi' => 'Staf gudang', 'shift' => 'Pagi', 'hariKerja' => 'Sen-Sab', 'tipeGaji' => 'Harian', 'gajiPokok' => 110000, 'tunjangan' => 0, 'uangMakan' => 0, 'lemburJam' => 15000, 'dendaTelat' => 5000, 'kpi' => 'KPI Staf Gudang', 'hp' => '081234560003', 'bulanLalu' => 11, 'gudang' => 1],
    ['kode' => 'K-C04', 'nama' => 'Andi Pratama', 'posisi' => 'Kepala toko', 'shift' => 'Pagi', 'hariKerja' => 'Sen-Jum', 'tipeGaji' => 'Bulanan', 'gajiPokok' => 4500000, 'tunjangan' => 750000, 'uangMakan' => 20000, 'lemburJam' => 0, 'dendaTelat' => 0, 'kpi' => '', 'hp' => '081234560004', 'bulanLalu' => 30],
    ['kode' => 'K-C05', 'nama' => 'Maya Sapitri', 'posisi' => 'Admin & keuangan', 'shift' => 'Pagi', 'hariKerja' => 'Sen-Jum', 'tipeGaji' => 'Bulanan', 'gajiPokok' => 3500000, 'tunjangan' => 300000, 'uangMakan' => 15000, 'lemburJam' => 20000, 'dendaTelat' => 10000, 'kpi' => '', 'hp' => '081234560005', 'bulanLalu' => 15],
];
function sd_kryContoh(array $d, array $u): array
{
    $M = sd_kryMaps(); $n = 0;
    foreach (KRY_CONTOH as $c) {
        if (val('SELECT id FROM karyawan WHERE kode = ?', [$c['kode']])) continue;
        $c['tglMasuk'] = date('Y-m-d', strtotime(date('Y-m-01') . ' -' . $c['bulanLalu'] . ' month'));
        if (!isset($M['shift'][mb_strtolower($c['shift'])])) $c['shift'] = '';
        if ($c['kpi'] !== '' && !isset($M['kpi'][mb_strtolower($c['kpi'])])) $c['kpi'] = '';
        $c['lokasi'] = !empty($c['gudang']) ? lok_masuk() : lok_jual();
        sd_kryImportRow($c, $u, $M); $n++;
    }
    log_act($u, 'Data contoh', 'Karyawan', $n . ' karyawan');
    return ['n' => $n];
}
/** Data contoh untuk instalasi baru (dipanggil install.php). */
function seed_demo_t3(array $u): void { as_contoh([], $u); sd_kryContoh([], $u); }

/* ======================================================================
 *  v5.5 — ATASAN & PERSETUJUAN, LEMBUR PER JAM, KARYAWAN PENGGANTI,
 *         AKSES APLIKASI SESUAI JAM KERJA
 * ==================================================================== */
const PJ_JENIS = ['Izin', 'Sakit', 'Cuti', 'Lembur', 'Akses'];
const PJ_NAMA = ['Izin' => 'Izin', 'Sakit' => 'Sakit', 'Cuti' => 'Cuti', 'Lembur' => 'Lembur', 'Akses' => 'Izin akses di luar jam'];
/** true bila menjadikan $ats atasan $kid membuat rantai atasan berputar. */
function sd_atasanLoop(string $kid, string $ats): bool
{
    $cur = $ats;
    for ($i = 0; $i < 60 && $cur !== ''; $i++) { if ($cur === $kid) return true; $cur = (string)val('SELECT atasan_id FROM karyawan WHERE id = ?', [$cur]); }
    return false;
}
function sd_jam(string $j): string { if (!preg_match('/^([01]?\d|2[0-3])[.:]([0-5]\d)$/', trim($j), $m)) throw new AppErr('Jam tidak valid (contoh 17:00)'); return sprintf('%02d:%s', $m[1], $m[2]); }
function sd_appUrl(): string { $u = (string)(settings_all()['AppUrl'] ?? ''); if ($u === '') { require_once __DIR__ . '/notif.php'; $u = s_baseUrl(); } return rtrim($u, '/'); }
/** Antrean WhatsApp ke karyawan (dikirim setelah jawaban selesai, bila gateway WA sudah diatur). */
function sd_wa(string $kid, string $msg): void
{
    if ($kid === '' || !sd_set_all()['notifWa']) return;
    $hp = preg_replace('/\D/', '', (string)val('SELECT hp FROM karyawan WHERE id = ?', [$kid]));
    if (strlen($hp) < 9 || (string)(settings_all()['WaToken'] ?? '') === '') return;
    if (!isset($GLOBALS['__waq'])) {
        $GLOBALS['__waq'] = [];
        register_shutdown_function(function () {
            try {
                if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
                require_once __DIR__ . '/notif.php'; $c = wa_cfg();
                foreach ($GLOBALS['__waq'] as [$h, $m]) wa_send($c, wa_nomor($h), $m);
            } catch (Throwable $e) { error_log('[Dhyra WA SDM] ' . $e->getMessage()); }
        });
    }
    $GLOBALS['__waq'][] = [$hp, $msg];
}
function sd_pjTeks(array $p): string
{
    $t = fmt_tgl($p['mulai']) . ($p['selesai'] !== $p['mulai'] ? ' – ' . fmt_tgl($p['selesai']) : '');
    if (($p['jam_mulai'] ?? '') !== '') $t .= ' pukul ' . $p['jam_mulai'] . '–' . $p['jam_selesai'];
    return (PJ_NAMA[$p['jenis']] ?? $p['jenis']) . ' ' . $t;
}
function fmt_tgl(string $d): string { $b = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']; return (int)substr($d, 8, 2) . ' ' . $b[(int)substr($d, 5, 2)] . ' ' . substr($d, 0, 4); }
/** Siapa boleh memutuskan: Administrator / SDM (level Kelola) atau atasan langsung pemohon. Pemohon tidak boleh menyetujui dirinya sendiri. */
function sd_bolehPutus(array $p, array $u): bool
{
    $me = row('SELECT id FROM karyawan WHERE user_id = ?', [$u['id']]);
    if ($me && $me['id'] === $p['karyawan_id'] && empty($u['admin'])) return false;
    if (!empty($u['admin']) || (($u['akses']['sdm'] ?? 0) >= 2)) return true;
    return $me && (string)val('SELECT atasan_id FROM karyawan WHERE id = ?', [$p['karyawan_id']]) === $me['id'];
}
/**
 * Rencana karyawan pengganti: untuk setiap tanggal pemohon terjadwal, apa yang terjadi pada jadwal pengganti.
 * aksi: ekstra (pengganti libur → masuk), tukar (pengganti pindah ke shift pemohon), sama (sudah masuk shift yang sama → dilewati), bentrok (pengganti sendiri cuti/izin → dilewati)
 */
function sd_gantiRencana(string $aId, string $bId, string $mulai, string $selesai): array
{
    if ($aId === $bId) throw new AppErr('Pengganti tidak boleh orang yang sama');
    $A = sd_kryRow($aId); $B = sd_kryRow($bId);
    if ($B['status'] !== 'Aktif') throw new AppErr($B['nama'] . ' tidak aktif');
    $DA = sd_days([$A], $mulai, $selesai)[$A['id']]; $DB = sd_days([$B], $mulai, $selesai)[$B['id']];
    $out = [];
    foreach ($DA as $t => $x) {
        if (!$x['s']) continue;
        $y = $DB[$t]; $sA = $x['s'];
        $o = ['t' => $t, 'shift' => $sA['id'], 'shiftNama' => $sA['nama'] . ' ' . $sA['masuk'] . '–' . $sA['pulang'], 'b' => $y['s'] ? $y['s']['nama'] : 'Libur'];
        if ($y['p'] || ($y['a'] && $y['a']['status'] !== 'Hadir')) { $o['aksi'] = 'bentrok'; $o['ket'] = $B['nama'] . ' sedang ' . ($y['p']['jenis'] ?? $y['a']['status']); }
        elseif ($y['s'] && $y['s']['id'] === $sA['id']) { $o['aksi'] = 'sama'; $o['ket'] = $B['nama'] . ' sudah masuk shift yang sama'; }
        elseif ($y['s']) { $o['aksi'] = 'tukar'; $o['ket'] = 'Pindah dari shift ' . $y['s']['nama'] . ' ke ' . $sA['nama'] . ' (shift ' . $y['s']['nama'] . ' jadi kosong)'; }
        else { $o['aksi'] = 'ekstra'; $o['ket'] = 'Masuk di hari liburnya' . ($B['tipe_gaji'] === 'Harian' ? ' (dibayar upah harian)' : (sd_set_all()['gantiBayar'] ? ' (dibayar upah 1 hari)' : '')); }
        $out[] = $o;
    }
    return ['pemohon' => $A['nama'], 'pengganti' => $B['nama'], 'rows' => $out, 'berlaku' => count(array_filter($out, fn($o) => in_array($o['aksi'], ['ekstra', 'tukar'], true)))];
}
/** Setujui / tolak satu pengajuan (dipakai menu SDM dan halaman atasan). */
function sd_putus(array $p, bool $ok, string $catatan, $pengganti, array $u): array
{
    if ($p['status'] !== 'Menunggu') throw new AppErr('Pengajuan ini sudah diproses (' . $p['status'] . ')');
    if (!sd_bolehPutus($p, $u)) throw new AppErr('Anda tidak berwenang memproses pengajuan ini. Persetujuan dilakukan oleh atasan langsung atau bagian SDM.');
    $k = sd_kryRow($p['karyawan_id']);
    $pg = $pengganti === null ? (string)($p['pengganti_id'] ?? '') : (string)$pengganti;
    if (!in_array($p['jenis'], ['Izin', 'Sakit', 'Cuti'], true)) $pg = '';
    $n = 0;
    if ($ok) {
        if ($p['jenis'] === 'Cuti') {
            $minta = 0; for ($x = $p['mulai']; $x <= $p['selesai']; $x = add_days($x, 1)) if (strpos((string)$k['hari_kerja'], date('N', strtotime($x))) !== false) $minta++;
            $sisa = (int)$k['jatah_cuti'] - sd_cutiTerpakai($k, substr($p['mulai'], 0, 4));
            if ($minta > $sisa) throw new AppErr('Sisa cuti ' . $k['nama'] . ' hanya ' . max(0, $sisa) . ' hari (diminta ' . $minta . ' hari kerja)');
        }
        if ($pg !== '') {
            $R = sd_gantiRencana($k['id'], $pg, $p['mulai'], $p['selesai']);
            foreach ($R['rows'] as $o) if (in_array($o['aksi'], ['ekstra', 'tukar'], true)) {
                q('INSERT INTO jadwal (karyawan_id, tanggal, shift_id, ganti_id, pj_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id), ganti_id = VALUES(ganti_id), pj_id = VALUES(pj_id)', [$pg, $o['t'], $o['shift'], $k['id'], $p['id']]);
                $n++;
            }
        }
    }
    q('UPDATE pengajuan SET status = ?, diproses_oleh = ?, diproses_pada = ?, catatan = ?, pengganti_id = ? WHERE id = ?', [$ok ? 'Disetujui' : 'Ditolak', $u['username'], now_s(), str($catatan, 255), $ok ? $pg : (string)($p['pengganti_id'] ?? ''), $p['id']]);
    log_act($u, $ok ? 'Setujui' : 'Tolak', 'Pengajuan', $k['nama'] . ' · ' . sd_pjTeks($p) . ($pg !== '' && $ok ? ' · pengganti ' . (sd_namaMap()[$pg] ?? '') . ' (' . $n . ' hari)' : ''));
    sd_wa($k['id'], ($ok ? '✅ Disetujui' : '❌ Ditolak') . ": pengajuan $p[jenis] Anda (" . sd_pjTeks($p) . ") oleh {$u['nama']}." . ($catatan !== '' ? "\nCatatan: $catatan" : '') . ($ok && $pg !== '' ? "\nPengganti: " . (sd_namaMap()[$pg] ?? '') : ''));
    if ($ok && $pg !== '' && $n) sd_wa($pg, "📅 Anda dijadwalkan menggantikan {$k['nama']} ($p[jenis]) pada " . fmt_tgl($p['mulai']) . ($p['selesai'] !== $p['mulai'] ? ' – ' . fmt_tgl($p['selesai']) : '') . ". Cek jadwal di " . sd_appUrl() . '/absen.html');
    return ['ok' => 1, 'ganti' => $n];
}
function sd_pjCekGanti(array $d): array
{
    $pg = (string)($d['pengganti'] ?? ''); if ($pg === '') return ['rows' => [], 'berlaku' => 0];
    if (!empty($d['id'])) { $p = row('SELECT * FROM pengajuan WHERE id = ?', [(string)$d['id']]); if (!$p) throw new AppErr('Pengajuan tidak ditemukan'); return sd_gantiRencana($p['karyawan_id'], $pg, $p['mulai'], $p['selesai']); }
    $m = (string)($d['mulai'] ?? ''); $s = (string)($d['selesai'] ?? $m); if (!is_date($m) || !is_date($s) || $s < $m || diff_days($s, $m) > 30) throw new AppErr('Tanggal tidak valid');
    return sd_gantiRencana((string)($d['karyawan'] ?? ''), $pg, $m, $s);
}
/** Batalkan pengajuan (mis. cuti tidak jadi). Jadwal pengganti yang dibuat dari pengajuan ini ikut dihapus. */
function sd_pjBatal(array $d, array $u): array
{
    $p = row('SELECT * FROM pengajuan WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$p || !in_array($p['status'], ['Menunggu', 'Disetujui'], true)) throw new AppErr('Pengajuan tidak dapat dibatalkan');
    $n = q('DELETE FROM jadwal WHERE pj_id = ?', [$p['id']])->rowCount();
    q("UPDATE pengajuan SET status = 'Dibatalkan', diproses_oleh = ?, diproses_pada = ?, catatan = ? WHERE id = ?", [$u['username'], now_s(), str($d['catatan'] ?? 'Dibatalkan', 255), $p['id']]);
    log_act($u, 'Batalkan', 'Pengajuan', (sd_namaMap()[$p['karyawan_id']] ?? '') . ' · ' . sd_pjTeks($p) . ($n ? ' · ' . $n . ' jadwal pengganti dihapus' : ''));
    sd_wa($p['karyawan_id'], '↩️ Pengajuan ' . sd_pjTeks($p) . ' dibatalkan oleh ' . $u['nama'] . '.');
    if ($n && $p['pengganti_id'] !== '') sd_wa($p['pengganti_id'], '↩️ Jadwal Anda menggantikan ' . (sd_namaMap()[$p['karyawan_id']] ?? '') . ' (' . sd_pjTeks($p) . ') dibatalkan. Jadwal kembali seperti biasa.');
    return sd_pjList(['status' => (string)($d['status'] ?? '')]);
}
/** Buat pengajuan lembur / izin akses (oleh karyawan sendiri atau perintah atasan). */
function sd_buatJam(array $k, string $jenis, array $d, string $oleh, string $status): string
{
    $t = (string)($d['tanggal'] ?? $d['mulai'] ?? ''); if (!is_date($t)) throw new AppErr('Tanggal tidak valid');
    $jm = sd_jam((string)($d['jamMulai'] ?? '')); $js = sd_jam((string)($d['jamSelesai'] ?? ''));
    [$a, $z] = sd_rentang($t, $jm, $js); $menit = intdiv($z - $a, 60);
    if ($menit < 15 || $menit > 720) throw new AppErr('Lama ' . strtolower(PJ_NAMA[$jenis]) . ' 15 menit – 12 jam');
    if ($jenis === 'Akses' && $z < time()) throw new AppErr('Jam izin akses sudah lewat');
    $al = str($d['alasan'] ?? '', 255); if ($al === '') throw new AppErr($jenis === 'Lembur' ? 'Isi pekerjaan yang dilemburkan' : 'Isi alasan perlu membuka aplikasi di luar jam kerja');
    foreach (rows("SELECT * FROM pengajuan WHERE karyawan_id = ? AND jenis = ? AND status IN ('Menunggu','Disetujui') AND mulai = ?", [$k['id'], $jenis, $t]) as $x) {
        if ($x['jam_mulai'] === '') throw new AppErr('Sudah ada pengajuan ' . $jenis . ' pada tanggal tersebut');
        [$xa, $xz] = sd_rentang($t, $x['jam_mulai'], $x['jam_selesai']); if ($a < $xz && $z > $xa) throw new AppErr('Jam bertabrakan dengan pengajuan ' . $jenis . ' ' . $x['jam_mulai'] . '–' . $x['jam_selesai']);
    }
    $id = uid('PJ');
    q('INSERT INTO pengajuan (id, karyawan_id, jenis, mulai, selesai, menit, jam_mulai, jam_selesai, alasan, status, diajukan_oleh, diproses_oleh, diproses_pada, dibuat) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$id, $k['id'], $jenis, $t, $t, $menit, $jm, $js, $al, $status, $oleh, $status === 'Disetujui' ? $oleh : '', $status === 'Disetujui' ? now_s() : null, now_s()]);
    return $id;
}
/** Perintah lembur dari bagian SDM / atasan: langsung berstatus disetujui. */
function sd_pjPerintah(array $d, array $u): array
{
    $k = sd_kryRow((string)($d['karyawan'] ?? ''));
    $id = sd_buatJam($k, 'Lembur', $d, $u['username'], 'Disetujui');
    $p = row('SELECT * FROM pengajuan WHERE id = ?', [$id]);
    log_act($u, 'Perintah lembur', 'SDM', $k['nama'] . ' · ' . sd_pjTeks($p));
    sd_wa($k['id'], '🕘 Perintah lembur dari ' . $u['nama'] . ': ' . sd_pjTeks($p) . "\nPekerjaan: " . $p['alasan'] . "\nJangan lupa absen masuk & pulang agar jam lembur tercatat.");
    return sd_pjList(['status' => (string)($d['status'] ?? '')]);
}
function sd_aksesLog(array $d): array
{
    $S = sd_set_all();
    $tanpa = rows("SELECT u.id, u.username, u.nama, j.nama jab FROM users u LEFT JOIN jabatan j ON j.id = u.jabatan_id LEFT JOIN karyawan k ON k.user_id = u.id
                   WHERE u.status = 'aktif' AND k.id IS NULL AND COALESCE(j.is_admin, 0) = 0 ORDER BY u.nama");
    $tanpa = array_values(array_filter($tanpa, fn($r) => !in_array((string)val('SELECT jabatan_id FROM users WHERE id = ?', [$r['id']]), $S['aksesBebas'], true)));
    return ['rows' => array_map(fn($r) => ['waktu' => $r['waktu'], 'username' => $r['username'], 'nama' => $r['nama'], 'konteks' => $r['konteks'], 'ket' => $r['ket']], rows('SELECT * FROM akses_log ORDER BY waktu DESC LIMIT 200')),
        'tanpaKaryawan' => array_map(fn($r) => ['username' => $r['username'], 'nama' => $r['nama'], 'jabatan' => (string)$r['jab']], $tanpa)];
}

/* ---------- akses aplikasi sesuai jam kerja ---------- */
/** Jendela waktu boleh memakai aplikasi: shift (± toleransi), lembur disetujui, izin akses disetujui. Memeriksa kemarin & hari ini (shift malam). */
function akses_jendela(array $k, int $now): array
{
    $S = sd_set_all(); $b = $S['aksesSebelum'] * 60; $f = $S['aksesSesudah'] * 60;
    $td = date('Y-m-d', $now); $y = date('Y-m-d', $now - 86400);
    $D = sd_days([$k], $y, $td)[$k['id']]; $W = [];
    foreach ([$y, $td] as $t) {
        $x = $D[$t] ?? null; if (!$x || !$x['s'] || $x['p']) continue;
        if ($x['a'] && $x['a']['status'] !== 'Hadir') continue;
        [$a, $z] = sd_rentang($t, $x['s']['masuk'], $x['s']['pulang']);
        $W[] = ['a' => $a - $b, 'z' => $z + $f, 'jenis' => 'shift', 't' => $t, 'ket' => 'Shift ' . $x['s']['nama'] . ' ' . $x['s']['masuk'] . '–' . $x['s']['pulang'], 'absen' => (bool)($x['a']['masuk'] ?? null)];
    }
    foreach (rows("SELECT * FROM pengajuan WHERE karyawan_id = ? AND jenis IN ('Lembur','Akses') AND status = 'Disetujui' AND jam_mulai <> '' AND mulai BETWEEN ? AND ?", [$k['id'], $y, $td]) as $p) {
        [$a, $z] = sd_rentang($p['mulai'], $p['jam_mulai'], $p['jam_selesai']); $lb = $p['jenis'] === 'Lembur';
        $W[] = ['a' => $a - ($lb ? $b : 0), 'z' => $z + ($lb ? $f : 0), 'jenis' => $lb ? 'lembur' : 'akses', 't' => $p['mulai'], 'ket' => ($lb ? 'Lembur ' : 'Izin akses ') . $p['jam_mulai'] . '–' . $p['jam_selesai']];
    }
    return $W;
}
function akses_bebas(array $raw): bool
{
    if (!empty(user_akses($raw)['admin'])) return true;
    return in_array((string)($raw['jabatan_id'] ?? ''), sd_set_all()['aksesBebas'], true);
}
function akses_catat(array $raw, string $ctx, string $ket): void
{
    if ((int)val('SELECT COUNT(*) FROM akses_log WHERE user_id = ? AND konteks = ? AND waktu > ?', [$raw['id'], $ctx, date('Y-m-d H:i:s', time() - 600)])) return;
    q('INSERT INTO akses_log (waktu, user_id, username, nama, konteks, ket) VALUES (?,?,?,?,?,?)', [now_s(), $raw['id'], $raw['username'], $raw['nama'], str($ctx, 60), str($ket, 255)]);
}
/** Ringkasan jam akses hari ini untuk ditampilkan ke karyawan. */
function akses_teks(array $W, int $now): string
{
    $W = array_values(array_filter($W, fn($w) => $w['z'] >= $now - 3600 && date('Y-m-d', $w['a']) <= date('Y-m-d', $now)));
    usort($W, fn($p, $q) => $p['a'] <=> $q['a']);
    return implode(' · ', array_map(fn($w) => $w['ket'] . ' (akses ' . date('H:i', $w['a']) . '–' . date('H:i', $w['z']) . ')', $W));
}
/** Dipanggil setiap permintaan ke modul kerja. Melempar AppErr kode JAM bila di luar jam kerja. */
function akses_cek(array $raw, string $ctx): void
{
    $S = sd_set_all();
    if (!$S['aksesJam'] || akses_bebas($raw)) return;
    $k = row("SELECT * FROM karyawan WHERE user_id = ? AND status = 'Aktif'", [$raw['id']]);
    if (!$k) return;
    $now = time(); $W = akses_jendela($k, $now);
    $in = array_values(array_filter($W, fn($w) => $now >= $w['a'] && $now <= $w['z']));
    if ($in) {
        if ($S['aksesAbsen'] && !array_filter($in, fn($w) => $w['jenis'] !== 'shift' || $w['absen'])) {
            akses_catat($raw, $ctx, 'Membuka aplikasi sebelum absen masuk');
            throw new AppErr('Anda belum absen masuk. Lakukan absen masuk di menu Absen saya sebelum memakai aplikasi.', 'JAM');
        }
        return;
    }
    $t = akses_teks($W, $now);
    akses_catat($raw, $ctx, 'Di luar jam kerja' . ($t ? ' · ' . $t : ' · tidak ada jadwal'));
    throw new AppErr('Saat ini di luar jam kerja Anda. ' . ($t !== '' ? 'Jadwal: ' . $t . '. ' : 'Hari ini Anda tidak terjadwal masuk. ')
        . 'Bila perlu bekerja, ajukan Lembur atau Izin akses di menu Absen saya dan tunggu persetujuan atasan.', 'JAM');
}
/** Penjualan offline tetap diterima (uangnya nyata), tetapi dicatat bila terjadi di luar jam kerja. */
function akses_cekOffline(array $raw, string $waktu, string $no): void
{
    $S = sd_set_all(); if (!$S['aksesJam'] || akses_bebas($raw)) return;
    $k = row("SELECT * FROM karyawan WHERE user_id = ? AND status = 'Aktif'", [$raw['id']]); if (!$k) return;
    $ts = strtotime($waktu) ?: time();
    foreach (akses_jendela($k, $ts) as $w) if ($ts >= $w['a'] && $ts <= $w['z']) return;
    q('INSERT INTO akses_log (waktu, user_id, username, nama, konteks, ket) VALUES (?,?,?,?,?,?)', [now_s(), $raw['id'], $raw['username'], $raw['nama'], 'Kasir offline', str('Penjualan offline ' . $no . ' pukul ' . date('d/m H:i', $ts) . ' di luar jam kerja', 255)]);
}

/* ---------- layanan atasan (halaman Absen saya → Tim saya) ---------- */
function sy_tim(array $d, array $u): array
{
    $me = sy_kry($u);
    $B = rows("SELECT * FROM karyawan WHERE atasan_id = ? AND status = 'Aktif' ORDER BY nama", [$me['id']]);
    if (!$B) return ['bawahan' => [], 'menunggu' => [], 'riwayat' => [], 'hariIni' => []];
    $ids = array_column($B, 'id'); $ph = implode(',', array_fill(0, count($ids), '?')); $td = today_s();
    $D = sd_days($B, $td, $td);
    return [
        'bawahan' => array_map(fn($k) => ['id' => $k['id'], 'nama' => $k['nama'], 'posisi' => $k['posisi']], $B),
        'menunggu' => array_map('sd_pjOut', rows("SELECT * FROM pengajuan WHERE status = 'Menunggu' AND karyawan_id IN ($ph) ORDER BY mulai", $ids)),
        'riwayat' => array_map('sd_pjOut', rows("SELECT * FROM pengajuan WHERE status <> 'Menunggu' AND karyawan_id IN ($ph) ORDER BY COALESCE(diproses_pada, dibuat) DESC LIMIT 20", $ids)),
        'hariIni' => array_map(fn($k) => sd_absenOut($D[$k['id']][$td], $td) + ['id' => $k['id'], 'nama' => $k['nama']], $B),
        'rekan' => sd_meta()['karyawan'],
    ];
}
function sy_proses(array $d, array $u): array
{
    $p = row('SELECT * FROM pengajuan WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$p) throw new AppErr('Pengajuan tidak ditemukan');
    $u['admin'] = false; $u['akses']['sdm'] = 0;   // dari halaman ini hanya sebagai atasan langsung
    sd_putus($p, !empty($d['setuju']), (string)($d['catatan'] ?? ''), $d['pengganti'] ?? null, $u);
    return sy_tim($d, $u);
}
function sy_cekGanti(array $d, array $u): array
{
    $me = sy_kry($u);
    if (!empty($d['id'])) {
        $p = row('SELECT * FROM pengajuan WHERE id = ?', [(string)$d['id']]);
        if (!$p || (string)val('SELECT atasan_id FROM karyawan WHERE id = ?', [$p['karyawan_id']]) !== $me['id']) throw new AppErr('Pengajuan tidak ditemukan');
        return sd_pjCekGanti($d);
    }
    return sd_pjCekGanti(['karyawan' => $me['id']] + $d);
}
function sy_perintah(array $d, array $u): array
{
    $me = sy_kry($u);
    $k = sd_kryRow((string)($d['karyawan'] ?? ''));
    if ($k['atasan_id'] !== $me['id']) throw new AppErr('Perintah lembur hanya untuk anggota tim Anda');
    $id = sd_buatJam($k, 'Lembur', $d, $u['username'], 'Disetujui');
    $p = row('SELECT * FROM pengajuan WHERE id = ?', [$id]);
    log_act($u, 'Perintah lembur', 'SDM', $k['nama'] . ' · ' . sd_pjTeks($p));
    sd_wa($k['id'], '🕘 Perintah lembur dari ' . $me['nama'] . ': ' . sd_pjTeks($p) . "\nPekerjaan: " . $p['alasan'] . "\nJangan lupa absen masuk & pulang agar jam lembur tercatat.");
    return sy_tim($d, $u);
}
