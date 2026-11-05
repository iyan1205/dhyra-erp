<?php
/**
 * ============================================================
 *  INTEGRASI KASIR -> AKUNTANSI
 *  Setiap kejadian di kasir otomatis menjadi jurnal berpasangan (debit = kredit):
 *
 *  Penjualan kasir  : Dr Kas/Bank (sesuai metode)      Cr Pendapatan Penjualan (bruto)
 *                     Dr Potongan Penjualan (diskon)   Cr PPN Keluaran (pajak)
 *                     Dr Harga Pokok Penjualan         Cr Persediaan Barang
 *  Batal transaksi  : jurnal pembalik pada tanggal pembatalan
 *  Stok masuk tunai : Dr Persediaan                    Cr Kas / Bank
 *  Stok masuk hutang: Purchase Order otomatis (Dr Persediaan, Cr Hutang Usaha)
 *  Stok opname/koreksi : Dr/Cr Persediaan  <->  Selisih Persediaan
 *  Stok awal produk : Dr Persediaan                    Cr Modal (akun stok awal)
 * ============================================================
 */
declare(strict_types=1);

function akun_exists(string $kode): bool
{
    static $m = null;
    if ($m === null) { $m = []; foreach (rows('SELECT kode FROM akun') as $r) $m[$r['kode']] = 1; }
    return isset($m[$kode]);
}

function assert_open_period(string $tgl, string $what = 'Transaksi'): void
{
    $k = settings_all()['KunciPeriode'] ?? '';
    if ($k && $tgl <= $k) throw new AppErr('Periode akuntansi sampai ' . dmy($k) . ' sudah dikunci. ' . $what . ' tidak dapat dicatat/diubah.');
}

function pos_acc(string $key): string
{
    $s = settings_all();
    $kode = (string)($s[$key] ?? (SET_DEFAULT[$key] ?? ''));
    if (!akun_exists($kode)) throw new AppErr('Akun integrasi kasir "' . $key . '" (' . $kode . ') tidak ditemukan. Atur di menu Akuntansi > Pengaturan > Integrasi kasir.');
    return $kode;
}

/** Tulis satu entri jurnal (beberapa baris). $lines = [[kode, ket, debit, kredit], ...] */
function journal_write(string $tgl, string $noBukti, string $jenis, array $lines, string $kontak, string $ref, array $user, ?string $entriId = null): string
{
    $out = []; $td = 0.0; $tk = 0.0;
    foreach ($lines as $l) {
        $d = r2((float)$l[2]); $k = r2((float)$l[3]);
        if (abs($d) < 0.005 && abs($k) < 0.005) continue;
        if ($d < 0) { $k += -$d; $d = 0.0; }
        if ($k < 0) { $d += -$k; $k = 0.0; }
        if ($d > 0 && $k > 0) { $n = r2($d - $k); $d = $n > 0 ? $n : 0.0; $k = $n < 0 ? -$n : 0.0; if (!$d && !$k) continue; }
        $out[] = [$l[0], mb_substr((string)$l[1], 0, 255), $d, $k];
        $td += $d; $tk += $k;
    }
    if (!$out) return '';
    if (abs($td - $tk) > 0.01) throw new AppErr('Jurnal otomatis tidak seimbang (' . $jenis . ' ' . $noBukti . '). Selisih ' . fmt_num($td - $tk));
    $id = $entriId ?: uid('J', 10);
    $now = now_s();
    insert_many('jurnal', ['entri_id', 'tanggal', 'no_bukti', 'jenis', 'kode_akun', 'keterangan', 'debit', 'kredit', 'kontak_id', 'ref', 'dibuat_oleh', 'dibuat_pada'],
        array_map(fn($l) => [$id, $tgl, $noBukti, $jenis, $l[0], $l[1], $l[2], $l[3], $kontak, $ref, $user['username'] ?? '-', $now], $out));
    return $id;
}

function pos_method_acc(string $metode): string
{
    return pos_acc(['Tunai' => 'PosAkunTunai', 'QRIS' => 'PosAkunQRIS', 'Transfer' => 'PosAkunTransfer', 'Debit' => 'PosAkunDebit'][$metode] ?? 'PosAkunTunai');
}

/** $t: no, tanggal (Y-m-d H:i:s), metode, subtotal (bruto), diskon, pajak, total, hpp, pelanggan */
function pos_sale_lines(array $t): array
{
    $on = $GLOBALS['__pos_online'] ?? null;   // pesanan toko online: label jelas + (bila sudah dibayar) ambil dari uang muka
    $ket = ($on ? 'Penjualan online ' . $on['no'] . ' / ' : 'Penjualan kasir ') . $t['no'] . ($t['pelanggan'] ? ' - ' . $t['pelanggan'] : '');
    return [
        [$on && $on['akun'] ? $on['akun'] : pos_method_acc($t['metode']), $ket . ($on && $on['akun'] ? ' (dari uang muka)' : ' (' . $t['metode'] . ')'), $t['total'], 0],
        [pos_acc('PosAkunPotongan'), 'Diskon penjualan ' . $t['no'], $t['diskon'], 0],
        [pos_acc('PosAkunPenjualan'), $ket, 0, $t['subtotal']],
        [settings_all()['AkunPPNKeluaran'] ?? '2103', 'PPN keluaran ' . $t['no'], 0, $t['pajak']],
        [pos_acc('PosAkunHPP'), 'HPP ' . $t['no'], $t['hpp'], 0],
        [pos_acc('PosAkunPersediaan'), 'Persediaan keluar ' . $t['no'], 0, $t['hpp']],
    ];
}

function post_pos_sale(array $t, array $user): void
{
    $tgl = substr($t['tanggal'], 0, 10);
    assert_open_period($tgl, 'Penjualan');
    if ((float)$t['pajak'] > 0 && !akun_exists(settings_all()['AkunPPNKeluaran'] ?? '')) throw new AppErr('Akun PPN keluaran belum diatur di Pengaturan akuntansi.');
    journal_write($tgl, $t['no'], !empty($GLOBALS['__pos_online']) ? 'Penjualan Online' : 'Penjualan Kasir', pos_sale_lines($t), '', 'POS:' . $t['no'], $user);
}

/** Jurnal pembalik: baris jurnal penjualan asli dibalik (debit <-> kredit) pada tanggal pembatalan. */
function post_pos_void(string $no, array $user, string $alasan): void
{
    $orig = rows('SELECT kode_akun, keterangan, debit, kredit FROM jurnal WHERE ref = ? ORDER BY id', ['POS:' . $no]);
    if (!$orig || val('SELECT COUNT(*) FROM jurnal WHERE ref = ?', ['POSV:' . $no])) return;
    $tgl = today_s();
    assert_open_period($tgl, 'Pembatalan');
    $lines = array_map(fn($l) => [$l['kode_akun'], 'Batal: ' . $l['keterangan'], (float)$l['kredit'], (float)$l['debit']], $orig);
    if ($alasan) $lines[0][1] = mb_substr($lines[0][1] . ' | ' . $alasan, 0, 255);
    journal_write($tgl, $no . '-BTL', 'Batal Penjualan Kasir', $lines, '', 'POSV:' . $no, $user);
}

/** Stok masuk dibayar dari kas/bank. */
function post_stock_in_cash(string $no, string $tgl, float $total, string $akunKas, string $supplier, string $kontakId, array $user): void
{
    if ($total <= 0) return;
    if (!akun_exists($akunKas)) throw new AppErr('Akun kas/bank untuk pembayaran stok masuk tidak valid.');
    $ket = 'Pembelian stok ' . $no . ($supplier ? ' - ' . $supplier : '');
    journal_write($tgl, $no, 'Stok Masuk', [
        [pos_acc('PosAkunPersediaan'), $ket, $total, 0],
        [$akunKas, $ket, 0, $total],
    ], $kontakId, 'SM:' . $no, $user);
}

/**
 * Penyesuaian nilai persediaan. $items: [{id, nama, lama, baru, hb, ket}]
 * $mode: 'awal' (stok awal produk baru -> akun modal), 'koreksi' / 'opname' (-> selisih persediaan)
 */
function post_stock_adjust(array $items, array $user, string $mode): void
{
    $plus = 0.0; $minus = 0.0; $nP = 0; $nM = 0;
    foreach ($items as $it) {
        $v = r2(((float)$it['baru'] - (float)$it['lama']) * (float)$it['hb']);
        if ($v > 0) { $plus += $v; $nP++; } elseif ($v < 0) { $minus += -$v; $nM++; }
    }
    $plus = r2($plus); $minus = r2($minus);
    if ($plus <= 0 && $minus <= 0) return;
    $tgl = today_s();
    assert_open_period($tgl, 'Penyesuaian stok');
    $per = pos_acc('PosAkunPersediaan');
    $lawan = $mode === 'awal' ? pos_acc('PosAkunStokAwal') : pos_acc('PosAkunSelisih');
    $label = ['awal' => 'Stok awal produk', 'koreksi' => 'Koreksi stok (edit produk)', 'opname' => 'Stok opname'][$mode] ?? 'Penyesuaian stok';
    $names = implode(', ', array_slice(array_map(fn($i) => $i['nama'], $items), 0, 3)) . (count($items) > 3 ? ', dll' : '');
    $lines = [];
    if ($plus > 0) { $lines[] = [$per, $label . ' (+' . $nP . ' produk): ' . $names, $plus, 0]; $lines[] = [$lawan, $label . ' - kelebihan', 0, $plus]; }
    if ($minus > 0) { $lines[] = [$lawan, $label . ' - kekurangan', $minus, 0]; $lines[] = [$per, $label . ' (-' . $nM . ' produk): ' . $names, 0, $minus]; }
    $no = 'ADJ-' . date('ymd') . '-' . str_pad((string)counter_next('ADJ_' . date('ymd')), 4, '0', STR_PAD_LEFT);
    journal_write($tgl, $no, 'Penyesuaian Stok', $lines, '', 'ADJ:' . $no, $user);
}
