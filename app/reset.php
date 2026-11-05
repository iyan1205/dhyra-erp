<?php
/**
 * ============================================================
 *  RESET DATA PER MENU  (Pengaturan → Reset data, khusus Administrator)
 *  Menghapus data transaksi satu menu beserta jurnal akuntansinya, sehingga
 *  neraca tetap seimbang. Pengguna, jabatan, pengaturan, akun perkiraan,
 *  lokasi, dan data menu lain tidak tersentuh.
 * ============================================================
 */
declare(strict_types=1);

const RESET_GRUP = [
    'kasir' => ['Kasir', 'Semua transaksi penjualan kasir (termasuk yang dibatalkan), struk, riwayat, poin member dari belanja, dan antrean offline.', 'Stok barang yang terjual dikembalikan.'],
    'online' => ['Toko Online', 'Semua pesanan online beserta penjualan & pembayarannya.', 'Stok barang dari pesanan yang sudah selesai dikembalikan.'],
    'stok' => ['Inventaris & Gudang: stok & riwayat', 'Stok semua produk di semua lokasi menjadi 0; riwayat stok masuk (beserta tagihan pemasoknya), mutasi, opname, dan kartu stok dihapus.', 'Produk, harga, SKU, dan kategori tetap ada.'],
    'produk' => ['Inventaris & Gudang: produk', 'Semua produk, foto produk, dan kategori dihapus (sekaligus stok & riwayat stoknya).', 'Riwayat penjualan lama tetap menyimpan nama produk.'],
    'pembelian' => ['Pembelian', 'Semua PO (pesanan pembelian) dan retur ke pemasok beserta jurnalnya.', 'Data pemasok & barang yang sudah diterima di gudang tetap.'],
    'member' => ['Member & CRM', 'Semua member dan riwayat poinnya.', 'Transaksi lama tetap ada, hanya tidak terhubung ke member.'],
    'promo' => ['Promo', 'Semua promo dan riwayat pemakaiannya.', ''],
    'akuntansi' => ['Akuntansi', 'Jurnal yang diinput manual, faktur & pembayarannya yang dibuat di menu Akuntansi, dan saldo awal akun.', 'Jurnal otomatis dari menu lain tetap (hapus lewat menu masing-masing).'],
    'aset' => ['Inventaris Aset', 'Semua aset, foto, riwayat perbaikan, penyusutan, dan pelepasan beserta jurnalnya.', ''],
    'sdm' => ['SDM & Gaji: transaksi', 'Absensi & selfie, jadwal shift, izin/cuti/lembur, kasbon, dan penggajian beserta jurnalnya.', 'Data karyawan & pola shift tetap.'],
    'karyawan' => ['SDM & Gaji: data karyawan', 'Semua data karyawan (sekaligus transaksi SDM & nilai KPI-nya).', 'Akun login tetap ada, hanya tidak terhubung ke data karyawan.'],
    'kpi' => ['KPI Karyawan', 'Nilai KPI semua periode.', 'Template KPI tetap.'],
    'log' => ['Log aktivitas', 'Log aktivitas pengguna dan catatan percobaan akses di luar jam kerja.', ''],
];

function rs_n(string $sql, array $a = []): int { try { return (int)val($sql, $a); } catch (Throwable $e) { return 0; } }
/** Jumlah data per grup (ditampilkan sebelum reset). */
function rs_info(): array
{
    $c = [
        'kasir' => rs_n("SELECT COUNT(*) FROM transaksi WHERE sumber <> 'online'"),
        'online' => rs_n('SELECT COUNT(*) FROM pesanan_online'),
        'stok' => rs_n('SELECT COUNT(*) FROM stok_masuk_doc') + rs_n('SELECT COUNT(*) FROM mutasi') + rs_n('SELECT COUNT(*) FROM opname') + rs_n('SELECT COUNT(*) FROM stok_lokasi WHERE qty <> 0'),
        'produk' => rs_n('SELECT COUNT(*) FROM produk'),
        'pembelian' => rs_n('SELECT COUNT(*) FROM po') + rs_n('SELECT COUNT(*) FROM retur_beli'),
        'member' => rs_n('SELECT COUNT(*) FROM member'),
        'promo' => rs_n('SELECT COUNT(*) FROM promo'),
        'akuntansi' => rs_n("SELECT COUNT(DISTINCT entri_id) FROM jurnal WHERE ref = ''") + rs_n("SELECT COUNT(*) FROM faktur WHERE sumber = ''"),
        'aset' => rs_n('SELECT COUNT(*) FROM aset'),
        'sdm' => rs_n('SELECT COUNT(*) FROM absensi') + rs_n('SELECT COUNT(*) FROM pengajuan') + rs_n('SELECT COUNT(*) FROM kasbon') + rs_n('SELECT COUNT(*) FROM gaji') + rs_n('SELECT COUNT(*) FROM jadwal'),
        'karyawan' => rs_n('SELECT COUNT(*) FROM karyawan'),
        'kpi' => rs_n('SELECT COUNT(*) FROM kpi_nilai'),
        'log' => rs_n('SELECT COUNT(*) FROM log_aktivitas') + rs_n('SELECT COUNT(*) FROM akses_log'),
    ];
    $sat = ['kasir' => 'transaksi', 'online' => 'pesanan', 'stok' => 'catatan', 'produk' => 'produk', 'pembelian' => 'dokumen', 'member' => 'member', 'promo' => 'promo', 'akuntansi' => 'entri',
        'aset' => 'aset', 'sdm' => 'catatan', 'karyawan' => 'karyawan', 'kpi' => 'nilai', 'log' => 'catatan'];
    $o = [];
    foreach (RESET_GRUP as $k => $g) $o[] = ['id' => $k, 'nama' => $g[0], 'isi' => $g[1], 'catatan' => $g[2], 'jumlah' => $c[$k], 'satuan' => $sat[$k]];
    return ['grup' => $o];
}

/* ---------------- helper ---------------- */
function rs_in(array $v): string { return implode(',', array_fill(0, max(1, count($v)), '?')); }
/** Hapus baris jurnal berdasarkan daftar ref (dipecah per 300). */
function rs_jurnalRef(array $refs): int
{
    $n = 0;
    foreach (array_chunk(array_values(array_unique(array_filter($refs))), 300) as $ch) $n += q('DELETE FROM jurnal WHERE ref IN (' . rs_in($ch) . ')', $ch)->rowCount();
    return $n;
}
function rs_jurnalLike(array $prefix): int { $n = 0; foreach ($prefix as $p) $n += q('DELETE FROM jurnal WHERE ref LIKE ?', [$p . '%'])->rowCount(); return $n; }
/** Faktur + pembayarannya + jurnal F:/P: */
function rs_faktur(array $ids): int
{
    if (!$ids) return 0;
    $n = 0;
    foreach (array_chunk($ids, 300) as $ch) {
        $pay = array_column(rows('SELECT id FROM pembayaran WHERE faktur_id IN (' . rs_in($ch) . ')', $ch), 'id');
        rs_jurnalRef(array_merge(array_map(fn($i) => 'F:' . $i, $ch), array_map(fn($i) => 'P:' . $i, $pay)));
        q('DELETE FROM pembayaran WHERE faktur_id IN (' . rs_in($ch) . ')', $ch);
        $n += q('DELETE FROM faktur WHERE id IN (' . rs_in($ch) . ')', $ch)->rowCount();
    }
    return $n;
}
/** Hapus transaksi penjualan (dan kembalikan stok barang yang terjual). */
function rs_transaksi(array $nos, bool $kembalikanStok): int
{
    if (!$nos) return 0;
    foreach (array_chunk($nos, 300) as $ch) {
        if ($kembalikanStok)
            foreach (rows("SELECT d.pid, t.lokasi_id, SUM(d.qty) q FROM transaksi_detail d JOIN transaksi t ON t.no = d.no WHERE d.no IN (" . rs_in($ch) . ") AND t.status = 'Selesai' GROUP BY d.pid, t.lokasi_id", $ch) as $r)
                if ($r['lokasi_id'] !== '' && val('SELECT id FROM produk WHERE id = ?', [$r['pid']])) lok_move($r['pid'], $r['lokasi_id'], (float)$r['q']);
        rs_jurnalRef(array_merge(array_map(fn($n) => 'POS:' . $n, $ch), array_map(fn($n) => 'POSV:' . $n, $ch)));
        q('DELETE FROM promo_pakai WHERE no IN (' . rs_in($ch) . ')', $ch);
        q('DELETE FROM member_poin WHERE ref IN (' . rs_in($ch) . ')', $ch);
        q('DELETE FROM transaksi_detail WHERE no IN (' . rs_in($ch) . ')', $ch);
        q('DELETE FROM transaksi WHERE no IN (' . rs_in($ch) . ')', $ch);
    }
    // ringkasan member dihitung ulang dari data yang tersisa
    q("UPDATE member m SET poin = COALESCE((SELECT SUM(p.poin) FROM member_poin p WHERE p.member_id = m.id), 0),
        total_belanja = COALESCE((SELECT SUM(t.total) FROM transaksi t WHERE t.member_id = m.id AND t.status = 'Selesai'), 0),
        jml_trx = COALESCE((SELECT COUNT(*) FROM transaksi t WHERE t.member_id = m.id AND t.status = 'Selesai'), 0)");
    return count($nos);
}
function rs_hapusFolder(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { if ($f->getFilename() === 'index.html' && $f->getPath() === $dir) continue; $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
}
/** Selisih nilai stok nyata (stok × harga beli) terhadap saldo akun Persediaan. */
function rs_selisihPersediaan(): float
{
    $per = pos_acc('PosAkunPersediaan');
    $buku = (float)val('SELECT COALESCE(SUM(debit - kredit), 0) FROM jurnal WHERE kode_akun = ?', [$per]) + (float)val('SELECT COALESCE(saldo_awal, 0) FROM akun WHERE kode = ?', [$per]);
    $nyata = (float)val('SELECT COALESCE(SUM(s.qty * p.harga_beli), 0) FROM stok_lokasi s JOIN produk p ON p.id = s.pid');
    return r2($nyata - $buku);
}
/** Setelah reset, kembalikan selisih stok-vs-buku ke keadaan sebelum reset (hanya menetralkan efek reset). */
function rs_selaraskanPersediaan(array $u, float $sebelum): float
{
    $per = pos_acc('PosAkunPersediaan');
    $sel = r2(rs_selisihPersediaan() - $sebelum);
    if (in_array('stok', $GLOBALS['__rs_grup'] ?? [], true)) $sel = rs_selisihPersediaan();   // stok dikosongkan: saldo persediaan ikut 0
    if (abs($sel) < 1) return 0.0;
    $lawan = pos_acc('PosAkunSelisih');
    $no = 'ADJ-' . date('ymd') . '-RST' . str_pad((string)counter_next('ADJRST_' . date('ymd')), 2, '0', STR_PAD_LEFT);
    $ket = 'Penyesuaian nilai persediaan setelah reset data';
    journal_write(today_s(), $no, 'Penyesuaian Stok', $sel > 0 ? [[$per, $ket, $sel, 0], [$lawan, $ket, 0, $sel]] : [[$lawan, $ket, -$sel, 0], [$per, $ket, 0, -$sel]], '', 'ADJ:' . $no, $u);
    return $sel;
}

/* ---------------- jalankan ---------------- */
function rs_run(array $d, array $u): array
{
    if (!password_verify((string)($d['password'] ?? ''), (string)($u['pass_hash'] ?? ''))) throw new AppErr('Password Anda salah. Reset dibatalkan.');
    if (strtoupper(trim((string)($d['konfirmasi'] ?? ''))) !== 'RESET') throw new AppErr('Ketik RESET untuk konfirmasi.');
    $g = array_values(array_intersect(array_keys(RESET_GRUP), (array)($d['grup'] ?? [])));
    if (!$g) throw new AppErr('Pilih minimal satu data yang akan direset.');
    // ketergantungan
    if (in_array('produk', $g, true) && !in_array('stok', $g, true)) $g[] = 'stok';
    if (in_array('karyawan', $g, true)) foreach (['sdm', 'kpi'] as $x) if (!in_array($x, $g, true)) $g[] = $x;
    $stokBalik = !isset($d['kembalikanStok']) || !empty($d['kembalikanStok']);
    $has = fn($k) => in_array($k, $g, true);
    $hasil = [];
    require_once __DIR__ . '/posting.php';
    $GLOBALS['__rs_grup'] = $g;
    $selAwal = rs_selisihPersediaan();

    if ($has('online')) {
        $nos = array_column(rows("SELECT no FROM transaksi WHERE sumber = 'online'"), 'no');
        rs_transaksi($nos, $stokBalik && !$has('stok'));
        rs_jurnalLike(['ON:', 'ONDP:', 'ONRF:']);
        $hasil['online'] = q('DELETE FROM pesanan_online')->rowCount();
    }
    if ($has('kasir')) {
        $nos = array_column(rows("SELECT no FROM transaksi WHERE sumber <> 'online'"), 'no');
        $hasil['kasir'] = rs_transaksi($nos, $stokBalik && !$has('stok'));
        q('DELETE FROM offline_sync');
    }
    if ($has('pembelian')) {
        $bay = array_values(array_filter(array_column(rows("SELECT bayar_id FROM retur_beli WHERE bayar_id <> ''"), 'bayar_id')));
        if ($bay) { rs_jurnalRef(array_map(fn($i) => 'P:' . $i, $bay)); q('DELETE FROM pembayaran WHERE id IN (' . rs_in($bay) . ')', $bay); }
        rs_jurnalLike(['RB:']);
        $n = q('DELETE FROM retur_beli')->rowCount(); q('DELETE FROM retur_item');
        $n += q('DELETE FROM po')->rowCount(); q('DELETE FROM po_item');
        $hasil['pembelian'] = $n;
    }
    if ($has('stok')) {
        rs_faktur(array_column(rows("SELECT id FROM faktur WHERE sumber LIKE 'SM:%'"), 'id'));
        rs_jurnalLike(['SM:', 'ADJ:']);
        $n = q('DELETE FROM stok_masuk_doc')->rowCount(); q('DELETE FROM stok_masuk');
        $n += q('DELETE FROM mutasi')->rowCount(); q('DELETE FROM mutasi_item');
        $n += q('DELETE FROM opname')->rowCount();
        q('UPDATE stok_lokasi SET qty = 0'); q('UPDATE produk SET stok = 0');
        $hasil['stok'] = $n;
    }
    if ($has('produk')) {
        $hasil['produk'] = q('DELETE FROM produk')->rowCount();
        q('DELETE FROM stok_lokasi'); q('DELETE FROM kategori');
        q("DELETE FROM penomoran WHERE k LIKE 'SKU\\_%'");
        rs_hapusFolder(upload_dir('produk'));
    }
    if ($has('member')) {
        $hasil['member'] = q('DELETE FROM member')->rowCount();
        q('DELETE FROM member_poin'); q("UPDATE transaksi SET member_id = ''");
    }
    if ($has('promo')) { $hasil['promo'] = q('DELETE FROM promo')->rowCount(); q('DELETE FROM promo_pakai'); }
    if ($has('akuntansi')) {
        $n = rs_faktur(array_column(rows("SELECT id FROM faktur WHERE sumber = ''"), 'id'));
        $n += (int)val("SELECT COUNT(DISTINCT entri_id) FROM jurnal WHERE ref = ''");
        q("DELETE FROM jurnal WHERE ref = ''");
        q('UPDATE akun SET saldo_awal = 0');
        $hasil['akuntansi'] = $n;
    }
    if ($has('aset')) {
        rs_jurnalLike(['AST:']);
        $hasil['aset'] = q('DELETE FROM aset')->rowCount(); q('DELETE FROM aset_log'); q('DELETE FROM aset_susut');
        rs_hapusFolder(upload_dir('aset'));
    }
    if ($has('sdm')) {
        rs_jurnalLike(['GJ:', 'KB:']);
        $n = 0;
        foreach (['absensi', 'jadwal', 'pengajuan', 'kasbon', 'gaji'] as $t) $n += q('DELETE FROM ' . $t)->rowCount();
        rs_hapusFolder(upload_dir('absen')); rs_hapusFolder(upload_dir('pengajuan'));
        $hasil['sdm'] = $n;
    }
    if ($has('karyawan')) $hasil['karyawan'] = q('DELETE FROM karyawan')->rowCount();
    if ($has('kpi')) $hasil['kpi'] = q('DELETE FROM kpi_nilai')->rowCount();
    if ($has('log')) { $hasil['log'] = q('DELETE FROM log_aktivitas')->rowCount() + q('DELETE FROM akses_log')->rowCount(); }

    // nilai persediaan di Akuntansi disamakan dengan stok yang ada
    $adj = 0.0;
    if (array_intersect($g, ['kasir', 'online', 'stok', 'produk', 'pembelian'])) $adj = rs_selaraskanPersediaan($u, $selAwal);
    $nama = array_map(fn($k) => RESET_GRUP[$k][0], $g);
    log_act($u, 'Reset data', 'Pengaturan', mb_substr(implode(', ', $nama), 0, 240));
    return ['ok' => true, 'dipilih' => $g, 'nama' => $nama, 'hasil' => $hasil, 'penyesuaian' => $adj] + rs_info();
}
