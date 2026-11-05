<?php
/**
 * ============================================================
 *  DHYRA v4 — MODUL TAHAP 2
 *  Pembelian (PO, terima barang, retur, pemasok, saran beli)
 *  Member & CRM · Promo · Dashboard Pemilik & Laporan
 *  Dipanggil lewat api.php { m:'kasir', mod:'pembelian'|'member'|'promo'|'laporan', a, t, d }
 * ============================================================
 */
declare(strict_types=1);

const T2_ACTIONS = [
    // ---- Pembelian ----
    'pbMeta' => ['f' => 'pb_meta', 'perm' => [['pembelian', 1]]],
    'poList' => ['f' => 'pb_poList', 'perm' => [['pembelian', 1]]],
    'poGet' => ['f' => 'pb_poGet', 'perm' => [['pembelian', 1]]],
    'poSave' => ['f' => 'pb_poSave', 'write' => 1, 'perm' => [['pembelian', 2]]],
    'poPesan' => ['f' => 'pb_poPesan', 'write' => 1, 'perm' => [['pembelian', 3]]],
    'poBatal' => ['f' => 'pb_poBatal', 'write' => 1, 'perm' => [['pembelian', 3]]],
    'poTerima' => ['f' => 'pb_poTerima', 'write' => 1, 'perm' => [['pembelian', 2]]],
    'returList' => ['f' => 'pb_returList', 'perm' => [['pembelian', 1]]],
    'returSave' => ['f' => 'pb_returSave', 'write' => 1, 'perm' => [['pembelian', 3]]],
    'pemasokList' => ['f' => 'pb_pemasokList', 'perm' => [['pembelian', 1]]],
    'pemasokSave' => ['f' => 'pb_pemasokSave', 'write' => 1, 'perm' => [['pembelian', 2]]],
    'saranBeli' => ['f' => 'pb_saran', 'perm' => [['pembelian', 1]]],
    'tagihanPemasok' => ['f' => 'pb_tagihan', 'perm' => [['pembelian', 1]]],
    // ---- Member & CRM ----
    'mbrList' => ['f' => 'mb_list', 'perm' => [['member', 1]]],
    'mbrGet' => ['f' => 'mb_get', 'perm' => [['member', 1]]],
    'mbrSave' => ['f' => 'mb_save', 'write' => 1, 'perm' => [['member', 2]]],
    'mbrPoin' => ['f' => 'mb_poin', 'write' => 1, 'perm' => [['member', 3]]],
    'poinSet' => ['f' => 'mb_poinSet', 'write' => 1, 'perm' => [['member', 3]]],
    // ---- Promo ----
    'prmList' => ['f' => 'pr_list', 'perm' => [['promo', 1]]],
    'prmSave' => ['f' => 'pr_save', 'write' => 1, 'perm' => [['promo', 2]]],
    'prmToggle' => ['f' => 'pr_toggle', 'write' => 1, 'perm' => [['promo', 2]]],
    'prmDel' => ['f' => 'pr_del', 'write' => 1, 'perm' => [['promo', 3]]],
    // ---- Dashboard & Laporan ----
    'dash' => ['f' => 'lp_dash', 'perm' => [['laporan', 1]]],
    'lapRun' => ['f' => 'lp_run', 'perm' => [['laporan', 1]]],
];

function t2_range(array $d): array
{
    $f = (string)($d['from'] ?? ''); $t = (string)($d['to'] ?? '');
    if (!is_date($f) || !is_date($t)) throw new AppErr('Rentang tanggal tidak valid');
    if ($f > $t) throw new AppErr('Tanggal awal melebihi tanggal akhir');
    return [$f, $t, $f . ' 00:00:00', $t . ' 23:59:59'];
}
function t2_in(array $ids): string { return implode(',', array_fill(0, max(1, count($ids)), '?')); }

/* ======================================================================
 *  PEMBELIAN
 * ==================================================================== */
const PO_STATUS = ['Draf', 'Dipesan', 'Sebagian', 'Selesai', 'Batal'];

function pb_meta(): array
{
    $s = settings_all();
    return [
        'pemasok' => array_map(fn($r) => ['id' => $r['id'], 'nama' => $r['nama'], 'telepon' => $r['telepon']], rows("SELECT id, nama, telepon FROM kontak WHERE tipe IN ('Pemasok','Keduanya') AND status = 'Aktif' ORDER BY nama")),
        'kas' => array_map(fn($r) => ['kode' => $r['kode'], 'nama' => $r['nama']], rows("SELECT kode, nama FROM akun WHERE subkategori = 'Kas & Bank' AND status = 'Aktif' ORDER BY kode")),
        'akunKas' => $s['AkunKas'] ?: '1101', 'akunBank' => $s['PosAkunBank'] ?: '1102',
    ];
}
function pb_po(string $no, bool $lock = false): array
{
    $p = row('SELECT * FROM po WHERE no = ?' . ($lock ? ' FOR UPDATE' : ''), [$no]);
    if (!$p) throw new AppErr('Pesanan pembelian tidak ditemukan');
    return $p;
}
function pb_poOut(array $p): array
{
    $L = lokasi_all();
    return ['no' => $p['no'], 'tanggal' => $p['tanggal'], 'kontakId' => $p['kontak_id'], 'pemasok' => $p['pemasok'], 'lokasi' => $p['lokasi_id'], 'lokasiNama' => $L[$p['lokasi_id']]['nama'] ?? '-',
        'status' => $p['status'], 'perkiraan' => $p['perkiraan'] ?? '', 'catatan' => $p['catatan'], 'total' => jn($p['total']), 'dibuat' => $p['dibuat_oleh'], 'dipesan' => $p['dipesan_oleh'], 'dipesanPada' => $p['dipesan_pada'] ?? ''];
}
function pb_poList(array $d): array
{
    $w = []; $a = [];
    if (is_date($d['from'] ?? null)) { $w[] = 'p.tanggal >= ?'; $a[] = $d['from']; }
    if (is_date($d['to'] ?? null)) { $w[] = 'p.tanggal <= ?'; $a[] = $d['to']; }
    if (!empty($d['status'])) {
        if ($d['status'] === 'Terbuka') $w[] = "p.status IN ('Draf','Dipesan','Sebagian')";
        else { $w[] = 'p.status = ?'; $a[] = (string)$d['status']; }
    }
    $sql = 'SELECT p.*, COUNT(i.id) n, COALESCE(SUM(i.qty),0) q, COALESCE(SUM(i.qty_terima),0) qt FROM po p LEFT JOIN po_item i ON i.no = p.no'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' GROUP BY p.no ORDER BY p.tanggal DESC, p.no DESC LIMIT 1000';
    return array_map(fn($r) => pb_poOut($r) + ['n' => (int)$r['n'], 'progres' => (float)$r['q'] > 0 ? round((float)$r['qt'] / (float)$r['q'] * 100) : 0], rows($sql, $a));
}
function pb_fakturSisa(array $ids): array
{
    $ids = array_values(array_filter($ids));
    if (!$ids) return [];
    $out = [];
    foreach (rows('SELECT f.id, f.nomor, f.total, f.jatuh_tempo, COALESCE((SELECT SUM(jumlah) FROM pembayaran b WHERE b.faktur_id = f.id),0) paid FROM faktur f WHERE f.id IN (' . t2_in($ids) . ')', $ids) as $f)
        $out[$f['id']] = ['id' => $f['id'], 'nomor' => $f['nomor'], 'total' => jn($f['total']), 'sisa' => jn(max(0, r2((float)$f['total'] - (float)$f['paid']))), 'jt' => $f['jatuh_tempo']];
    return $out;
}
function pb_poGet(array $d): array
{
    $p = pb_po((string)($d['no'] ?? ''));
    $o = pb_poOut($p);
    $o['items'] = array_map(fn($r) => ['pid' => $r['pid'], 'sku' => (string)($r['sku'] ?? ''), 'nama' => $r['nama'], 'satuan' => $r['satuan'], 'qty' => jn($r['qty']), 'harga' => jn($r['harga']),
        'terima' => jn($r['qty_terima']), 'sisa' => jn(max(0, r3((float)$r['qty'] - (float)$r['qty_terima']))), 'stok' => jn($r['stok'] ?? 0)],
        rows('SELECT i.*, pr.sku, sl.qty stok FROM po_item i LEFT JOIN produk pr ON pr.id = i.pid LEFT JOIN stok_lokasi sl ON sl.pid = i.pid AND sl.lokasi_id = ? WHERE i.no = ? ORDER BY i.id', [$p['lokasi_id'], $p['no']]));
    $docs = rows('SELECT * FROM stok_masuk_doc WHERE po_no = ? ORDER BY tanggal', [$p['no']]);
    $F = pb_fakturSisa(array_column($docs, 'faktur_id'));
    $o['terima'] = array_map(fn($x) => ['no' => $x['no'], 'tanggal' => $x['tanggal'], 'user' => $x['user'], 'bayar' => $x['bayar'], 'total' => jn($x['total']), 'faktur' => $F[$x['faktur_id']] ?? null], $docs);
    $o['retur'] = array_map(fn($x) => ['no' => $x['no'], 'tanggal' => $x['tanggal'], 'metode' => $x['metode'], 'total' => jn($x['total'])], rows('SELECT * FROM retur_beli WHERE po_no = ? ORDER BY tanggal', [$p['no']]));
    $k = $p['kontak_id'] ? row('SELECT telepon, alamat FROM kontak WHERE id = ?', [$p['kontak_id']]) : null;
    $o['telepon'] = $k['telepon'] ?? ''; $o['alamat'] = $k['alamat'] ?? '';
    return $o;
}
/** Cari pemasok dari id / nama; buat baru bila belum ada. */
function pb_kontak(array $d, array $u): array
{
    $id = (string)($d['kontakId'] ?? '');
    if ($id) { $k = row('SELECT id, nama FROM kontak WHERE id = ?', [$id]); if ($k) return $k; }
    $nama = str($d['pemasok'] ?? '', 150);
    if ($nama === '') throw new AppErr('Pilih atau ketik nama pemasok');
    $id = k_findOrCreateSupplier($nama, $u);
    return row('SELECT id, nama FROM kontak WHERE id = ?', [$id]);
}
function pb_poSave(array $d, array $u): array
{
    $no = (string)($d['no'] ?? '');
    $old = $no ? pb_po($no, true) : null;
    if ($old && !in_array($old['status'], ['Draf', 'Dipesan'], true)) throw new AppErr('Pesanan berstatus ' . $old['status'] . ' tidak dapat diubah');
    if ($old && (float)val('SELECT COALESCE(SUM(qty_terima),0) FROM po_item WHERE no = ?', [$no]) > 0) throw new AppErr('Sebagian barang sudah diterima, pesanan tidak dapat diubah');
    if ($old && $old['status'] === 'Dipesan' && ($u['akses']['pembelian'] ?? 0) < 3) throw new AppErr('Pesanan yang sudah dikirim ke pemasok hanya dapat diubah oleh manajer pembelian');
    $tgl = (string)($d['tanggal'] ?? '') ?: today_s();
    if (!is_date($tgl)) throw new AppErr('Tanggal tidak valid');
    $per = (string)($d['perkiraan'] ?? ''); $per = is_date($per) ? $per : null;
    $lok = lok_valid($d['lokasi'] ?? lok_masuk());
    $k = pb_kontak($d, $u);
    $items = []; $total = 0;
    foreach ($d['items'] ?? [] as $it) {
        $p = row('SELECT id, nama, satuan, harga_beli FROM produk WHERE id = ?', [(string)($it['pid'] ?? $it['id'] ?? '')]);
        if (!$p) throw new AppErr('Produk tidak ditemukan atau sudah dihapus');
        $q = r3(num($it['qty'] ?? 0)); $h = max(0, num($it['harga'] ?? $p['harga_beli']));
        if ($q <= 0) throw new AppErr('Jumlah pesanan harus lebih dari 0: ' . $p['nama']);
        if (isset($items[$p['id']])) { $items[$p['id']]['qty'] = r3($items[$p['id']]['qty'] + $q); }
        else $items[$p['id']] = ['p' => $p, 'qty' => $q, 'harga' => $h];
    }
    if (!$items) throw new AppErr('Tambahkan minimal satu produk');
    foreach ($items as $x) $total += (int)round($x['qty'] * $x['harga']);
    $pesan = !empty($d['pesan']) && ($u['akses']['pembelian'] ?? 0) >= 3;
    $cat = str($d['catatan'] ?? '', 255);
    if ($old) {
        q('UPDATE po SET tanggal=?, kontak_id=?, pemasok=?, lokasi_id=?, perkiraan=?, catatan=?, total=? WHERE no=?', [$tgl, $k['id'], $k['nama'], $lok, $per, $cat, $total, $no]);
        q('DELETE FROM po_item WHERE no = ?', [$no]);
    } else {
        $no = k_nextNo('PB');
        q('INSERT INTO po (no, tanggal, kontak_id, pemasok, lokasi_id, status, perkiraan, catatan, total, dibuat_oleh, dibuat_pada) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [$no, $tgl, $k['id'], $k['nama'], $lok, 'Draf', $per, $cat, $total, $u['username'], now_s()]);
    }
    insert_many('po_item', ['no', 'pid', 'nama', 'satuan', 'qty', 'harga', 'qty_terima'], array_map(fn($x) => [$no, $x['p']['id'], $x['p']['nama'], $x['p']['satuan'], $x['qty'], $x['harga'], 0], array_values($items)));
    if ($pesan && (!$old || $old['status'] === 'Draf')) q("UPDATE po SET status = 'Dipesan', dipesan_oleh = ?, dipesan_pada = ? WHERE no = ?", [$u['username'], now_s(), $no]);
    log_act($u, $old ? 'Ubah' : 'Tambah', 'Pembelian', $no . ' ' . $k['nama'] . ' ' . fmt_num($total));
    return pb_poGet(['no' => $no]);
}
function pb_poPesan(array $d, array $u): array
{
    $p = pb_po((string)($d['no'] ?? ''), true);
    if ($p['status'] !== 'Draf') throw new AppErr('Hanya pesanan berstatus Draf yang dapat dipesan');
    q("UPDATE po SET status = 'Dipesan', dipesan_oleh = ?, dipesan_pada = ? WHERE no = ?", [$u['username'], now_s(), $p['no']]);
    log_act($u, 'Pesan', 'Pembelian', $p['no']);
    return pb_poGet(['no' => $p['no']]);
}
function pb_poBatal(array $d, array $u): array
{
    $p = pb_po((string)($d['no'] ?? ''), true);
    if (in_array($p['status'], ['Selesai', 'Batal'], true)) throw new AppErr('Pesanan sudah ' . strtolower($p['status']));
    $got = (float)val('SELECT COALESCE(SUM(qty_terima),0) FROM po_item WHERE no = ?', [$p['no']]);
    $al = str($d['alasan'] ?? '', 150);
    $st = $got > 0 ? 'Selesai' : 'Batal';
    q('UPDATE po SET status = ?, catatan = ? WHERE no = ?', [$st, trim($p['catatan'] . ($p['catatan'] ? ' | ' : '') . ($got > 0 ? 'Ditutup, sisa dibatalkan' : 'Dibatalkan') . ' oleh ' . $u['username'] . ($al ? ': ' . $al : '')), $p['no']]);
    log_act($u, $st === 'Batal' ? 'Batal' : 'Tutup', 'Pembelian', $p['no']);
    return pb_poGet(['no' => $p['no']]);
}
function pb_poTerima(array $d, array $u): array
{
    $p = pb_po((string)($d['no'] ?? ''), true);
    if (!in_array($p['status'], ['Dipesan', 'Sebagian'], true)) throw new AppErr($p['status'] === 'Draf' ? 'Pesanan masih Draf. Tandai "Dipesan" dulu sebelum menerima barang.' : 'Pesanan berstatus ' . $p['status'] . ' tidak dapat menerima barang');
    $PI = []; foreach (rows('SELECT * FROM po_item WHERE no = ? FOR UPDATE', [$p['no']]) as $r) $PI[$r['pid']] = $r;
    $items = [];
    foreach ($d['items'] ?? [] as $it) {
        $pid = (string)($it['pid'] ?? ''); $q = r3(num($it['qty'] ?? 0));
        if ($q <= 0) continue;
        $x = $PI[$pid] ?? null;
        if (!$x) throw new AppErr('Produk tidak ada di pesanan ini');
        $sisa = r3((float)$x['qty'] - (float)$x['qty_terima']);
        if ($q > $sisa + 1e-9) throw new AppErr('Jumlah diterima "' . $x['nama'] . '" melebihi sisa pesanan (' . jn($sisa) . ')');
        $items[] = ['id' => $pid, 'qty' => $q, 'harga' => num($it['harga'] ?? $x['harga'])];
    }
    if (!$items) throw new AppErr('Isi jumlah barang yang diterima');
    $sm = k_smSave(['items' => $items, 'supplier' => $p['pemasok'], 'bayar' => $d['bayar'] ?? 'Hutang', 'akunKas' => $d['akunKas'] ?? '', 'jatuhTempo' => $d['jatuhTempo'] ?? '',
        'tanggal' => $d['tanggal'] ?? '', 'lokasi' => $p['lokasi_id'], 'catatan' => trim('Terima PO ' . $p['no'] . ' ' . str($d['catatan'] ?? '', 150)), 'updHarga' => !empty($d['updHarga'])], $u, $p['no']);
    foreach ($items as $it) q('UPDATE po_item SET qty_terima = ROUND(qty_terima + ?, 3) WHERE no = ? AND pid = ?', [$it['qty'], $p['no'], $it['id']]);
    $open = (float)val('SELECT COALESCE(SUM(GREATEST(qty - qty_terima, 0)),0) FROM po_item WHERE no = ?', [$p['no']]);
    q('UPDATE po SET status = ? WHERE no = ?', [$open > 0.0005 ? 'Sebagian' : 'Selesai', $p['no']]);
    log_act($u, 'Terima barang', 'Pembelian', $p['no'] . ' → ' . $sm['no']);
    return ['po' => pb_poGet(['no' => $p['no']]), 'sm' => $sm, 'stok' => $sm['stok']];
}

/* ---- Retur ke pemasok ---- */
function pb_returList(array $d): array
{
    $L = lokasi_all();
    $w = []; $a = [];
    if (is_date($d['from'] ?? null)) { $w[] = 'tanggal >= ?'; $a[] = $d['from'] . ' 00:00:00'; }
    if (is_date($d['to'] ?? null)) { $w[] = 'tanggal <= ?'; $a[] = $d['to'] . ' 23:59:59'; }
    $R = rows('SELECT * FROM retur_beli' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY tanggal DESC LIMIT 500', $a);
    $I = [];
    if ($R) foreach (rows('SELECT * FROM retur_item WHERE no IN (' . t2_in(array_column($R, 'no')) . ') ORDER BY id', array_column($R, 'no')) as $i)
        $I[$i['no']][] = ['pid' => $i['pid'], 'nama' => $i['nama'], 'satuan' => $i['satuan'], 'qty' => jn($i['qty']), 'harga' => jn($i['harga'])];
    $F = pb_fakturSisa(array_column($R, 'faktur_id'));
    return array_map(fn($r) => ['no' => $r['no'], 'tanggal' => $r['tanggal'], 'pemasok' => $r['pemasok'], 'lokasi' => $L[$r['lokasi_id']]['nama'] ?? '-', 'po' => $r['po_no'], 'metode' => $r['metode'],
        'faktur' => $F[$r['faktur_id']]['nomor'] ?? '', 'total' => jn($r['total']), 'alasan' => $r['alasan'], 'user' => $r['user'], 'items' => $I[$r['no']] ?? []], $R);
}
function pb_returSave(array $d, array $u): array
{
    $k = pb_kontak($d, $u);
    $lok = lok_valid($d['lokasi'] ?? lok_masuk());
    $metode = in_array($d['metode'] ?? '', ['Tagihan', 'Kas', 'Bank'], true) ? $d['metode'] : 'Tagihan';
    $alasan = str($d['alasan'] ?? '', 255);
    if ($alasan === '') throw new AppErr('Isi alasan retur (mis. rusak, kedaluwarsa, salah kirim)');
    $po = str($d['po'] ?? '', 30);
    if ($po !== '' && !val('SELECT no FROM po WHERE no = ?', [$po])) $po = '';
    $izinMinus = (settings_all()['izinMinus'] ?? '0') === '1';
    $lines = []; $total = 0; $pids = [];
    foreach ($d['items'] ?? [] as $it) {
        $p = row('SELECT id, nama, satuan, harga_beli FROM produk WHERE id = ? FOR UPDATE', [(string)($it['pid'] ?? '')]);
        if (!$p) throw new AppErr('Produk tidak ditemukan');
        $q = r3(num($it['qty'] ?? 0)); $h = max(0, num($it['harga'] ?? $p['harga_beli']));
        if ($q <= 0) continue;
        $ada = lok_qty($p['id'], $lok);
        if (!$izinMinus && $q > $ada + 1e-9) throw new AppErr('Stok "' . $p['nama'] . '" di ' . lokasi_all()[$lok]['nama'] . ' hanya ' . jn($ada));
        $lines[] = ['p' => $p, 'qty' => $q, 'harga' => $h, 'ada' => $ada];
        $total += (int)round($q * $h);
    }
    if (!$lines) throw new AppErr('Tambahkan barang yang diretur');
    $tgl = today_s(); $now = now_s();
    assert_open_period($tgl, 'Retur pembelian');
    $no = k_nextNo('RB');
    $per = pos_acc('PosAkunPersediaan');
    $fakturId = ''; $bayarId = ''; $akun = '';
    $ket = 'Retur pembelian ' . $no . ' - ' . $k['nama'] . ($alasan ? ' (' . $alasan . ')' : '');
    if ($metode === 'Tagihan') {
        $fakturId = (string)($d['fakturId'] ?? '');
        $F = pb_fakturSisa([$fakturId])[$fakturId] ?? null;
        $f = $F ? row("SELECT * FROM faktur WHERE id = ? AND tipe = 'Pembelian'", [$fakturId]) : null;
        if (!$f) throw new AppErr('Pilih tagihan (faktur pembelian) yang akan dipotong');
        if ($f['kontak_id'] !== $k['id']) throw new AppErr('Tagihan yang dipilih bukan milik pemasok ini');
        if ($total > $F['sisa'] + 0.005) throw new AppErr('Nilai retur (' . fmt_num($total) . ') melebihi sisa tagihan ' . $F['nomor'] . ' (' . fmt_num($F['sisa']) . '). Pilih pengembalian uang Kas/Bank.');
        $hut = settings_all()['AkunHutang'] ?: '2101';
        $bayarId = uid('P', 10);
        q('INSERT INTO pembayaran (id, faktur_id, tanggal, jumlah, akun_kas, catatan, dibuat_oleh) VALUES (?,?,?,?,?,?,?)', [$bayarId, $fakturId, $tgl, $total, $per, 'Retur ' . $no, $u['username']]);
        journal_write($tgl, $no, 'Retur Pembelian', [[$hut, $ket, $total, 0], [$per, $ket, 0, $total]], $k['id'], 'P:' . $bayarId, $u);
    } else {
        $s = settings_all();
        $akun = (string)($d['akunKas'] ?? '');
        if (!$akun || !akun_exists($akun)) $akun = $metode === 'Bank' ? ($s['PosAkunBank'] ?: '1102') : ($s['AkunKas'] ?: '1101');
        journal_write($tgl, $no, 'Retur Pembelian', [[$akun, $ket . ' - uang kembali', $total, 0], [$per, $ket, 0, $total]], $k['id'], 'RB:' . $no, $u);
    }
    $op = [];
    foreach ($lines as $l) {
        $baru = lok_move($l['p']['id'], $lok, -$l['qty']);
        $op[] = [uid('O'), $now, $u['username'], $l['p']['id'], $l['p']['nama'], $l['ada'], $baru, -$l['qty'], 'Retur pembelian ' . $no, $lok];
        $pids[] = $l['p']['id'];
    }
    insert_many('opname', ['id', 'tanggal', 'user', 'pid', 'nama', 'sistem', 'fisik', 'selisih', 'catatan', 'lokasi_id'], $op);
    q('INSERT INTO retur_beli (no, tanggal, kontak_id, pemasok, lokasi_id, po_no, metode, faktur_id, bayar_id, akun_kas, total, alasan, user) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
        [$no, $now, $k['id'], $k['nama'], $lok, $po, $metode, $fakturId, $bayarId, $akun, $total, $alasan, $u['username']]);
    insert_many('retur_item', ['no', 'pid', 'nama', 'satuan', 'qty', 'harga'], array_map(fn($l) => [$no, $l['p']['id'], $l['p']['nama'], $l['p']['satuan'], $l['qty'], $l['harga']], $lines));
    log_act($u, 'Retur', 'Pembelian', $no . ' ' . $k['nama'] . ' ' . fmt_num($total));
    return ['no' => $no, 'total' => $total, 'stok' => stok_snapshot($pids)];
}

/** Tagihan pembelian yang masih punya sisa untuk satu pemasok (dipakai form retur). */
function pb_tagihan(array $d): array
{
    $k = (string)($d['kontakId'] ?? '');
    $ids = array_column(rows("SELECT id FROM faktur WHERE tipe = 'Pembelian' AND kontak_id = ? ORDER BY tanggal DESC LIMIT 100", [$k]), 'id');
    return array_values(array_filter(pb_fakturSisa($ids), fn($f) => $f['sisa'] > 0));
}

/* ---- Pemasok ---- */
function pb_pemasokList(): array
{
    $K = rows("SELECT * FROM kontak WHERE tipe IN ('Pemasok','Keduanya') ORDER BY status, nama");
    $beli = []; foreach (rows('SELECT kontak_id, COUNT(*) n, SUM(total) t, MAX(tanggal) akhir FROM stok_masuk_doc GROUP BY kontak_id') as $r) $beli[$r['kontak_id']] = $r;
    $po = []; foreach (rows("SELECT kontak_id, COUNT(*) n FROM po WHERE status IN ('Draf','Dipesan','Sebagian') GROUP BY kontak_id") as $r) $po[$r['kontak_id']] = (int)$r['n'];
    $hut = [];
    foreach (rows("SELECT f.kontak_id, SUM(f.total - COALESCE((SELECT SUM(jumlah) FROM pembayaran b WHERE b.faktur_id = f.id),0)) s FROM faktur f WHERE f.tipe = 'Pembelian' GROUP BY f.kontak_id") as $r) $hut[$r['kontak_id']] = max(0, r2((float)$r['s']));
    return array_map(fn($k) => ['id' => $k['id'], 'nama' => $k['nama'], 'tipe' => $k['tipe'], 'telepon' => $k['telepon'], 'email' => $k['email'], 'alamat' => $k['alamat'], 'npwp' => $k['npwp'], 'aktif' => $k['status'] === 'Aktif',
        'dokumen' => (int)($beli[$k['id']]['n'] ?? 0), 'pembelian' => jn($beli[$k['id']]['t'] ?? 0), 'terakhir' => $beli[$k['id']]['akhir'] ?? '', 'poTerbuka' => $po[$k['id']] ?? 0, 'hutang' => jn($hut[$k['id']] ?? 0)], $K);
}
function pb_pemasokSave(array $d, array $u): array
{
    $nama = str($d['nama'] ?? '', 150);
    if ($nama === '') throw new AppErr('Nama pemasok wajib diisi');
    $id = (string)($d['id'] ?? '');
    if (val('SELECT id FROM kontak WHERE LOWER(nama) = LOWER(?) AND id <> ?', [$nama, $id])) throw new AppErr('Nama kontak sudah ada');
    $vals = [$nama, str($d['telepon'] ?? '', 40), str($d['email'] ?? '', 120), str($d['alamat'] ?? ''), str($d['npwp'] ?? '', 40), !isset($d['aktif']) || !empty($d['aktif']) ? 'Aktif' : 'Nonaktif'];
    if ($id) {
        $k = row('SELECT * FROM kontak WHERE id = ?', [$id]);
        if (!$k) throw new AppErr('Pemasok tidak ditemukan');
        $tipe = $k['tipe'] === 'Pelanggan' ? 'Keduanya' : $k['tipe'];
        q('UPDATE kontak SET nama=?, telepon=?, email=?, alamat=?, npwp=?, status=?, tipe=? WHERE id=?', array_merge($vals, [$tipe, $id]));
        q('UPDATE po SET pemasok = ? WHERE kontak_id = ?', [$nama, $id]);
    } else {
        $id = uid('K', 10);
        q("INSERT INTO kontak (nama, telepon, email, alamat, npwp, status, id, tipe) VALUES (?,?,?,?,?,?,?, 'Pemasok')", array_merge($vals, [$id]));
    }
    log_act($u, empty($d['id']) ? 'Tambah' : 'Ubah', 'Pemasok', $nama);
    return ['id' => $id, 'nama' => $nama];
}
/** Saran pembelian: produk yang total stoknya menyentuh batas minimum. */
function pb_saran(): array
{
    $minL = []; foreach (rows('SELECT pid, SUM(min) m FROM stok_lokasi GROUP BY pid') as $r) $minL[$r['pid']] = (float)$r['m'];
    $onOrder = []; foreach (rows("SELECT i.pid, SUM(GREATEST(i.qty - i.qty_terima, 0)) q FROM po_item i JOIN po p ON p.no = i.no WHERE p.status IN ('Draf','Dipesan','Sebagian') GROUP BY i.pid") as $r) $onOrder[$r['pid']] = (float)$r['q'];
    $last = [];
    foreach (rows("SELECT s.pid, d.kontak_id, s.supplier, s.harga FROM stok_masuk s JOIN stok_masuk_doc d ON d.no = s.no WHERE s.supplier <> '' ORDER BY s.tanggal") as $r) $last[$r['pid']] = $r;
    $out = [];
    foreach (rows('SELECT * FROM produk ORDER BY nama') as $p) {
        $min = max((float)$p['stok_min'], $minL[$p['id']] ?? 0);
        if ($min <= 0) continue;
        $st = (float)$p['stok']; $oo = $onOrder[$p['id']] ?? 0;
        if ($st > $min) continue;
        $saran = max(0, ceil($min * 2 - $st - $oo));
        $l = $last[$p['id']] ?? null;
        $out[] = ['pid' => $p['id'], 'sku' => (string)($p['sku'] ?? ''), 'nama' => $p['nama'], 'satuan' => $p['satuan'], 'foto' => $p['foto'], 'stok' => jn($st), 'min' => jn($min), 'dipesan' => jn($oo),
            'saran' => jn($saran), 'harga' => jn($l ? ((float)$l['harga'] ?: $p['harga_beli']) : $p['harga_beli']), 'kontakId' => $l['kontak_id'] ?? '', 'pemasok' => $l['supplier'] ?? ''];
    }
    return $out;
}

/* ======================================================================
 *  MEMBER & CRM
 * ==================================================================== */
function mb_list(array $d): array
{
    $f = (string)($d['f'] ?? 'semua'); $q = str($d['q'] ?? '', 60);
    $w = []; $a = [];
    if ($q !== '') { $w[] = '(nama LIKE ? OR kode LIKE ? OR hp LIKE ? OR email LIKE ?)'; $l = '%' . $q . '%'; $hp = member_hp($q); array_push($a, $l, $l, '%' . ($hp ?: $q) . '%', $l); }
    $order = 'nama';
    if ($f === 'ultah') { $w[] = 'tgl_lahir IS NOT NULL AND MONTH(tgl_lahir) = ?'; $a[] = (int)date('n'); $order = 'DAY(tgl_lahir)'; }
    elseif ($f === 'pasif') { $w[] = 'aktif = 1 AND (terakhir IS NULL OR terakhir < ?)'; $a[] = date('Y-m-d', strtotime('-30 days')) . ' 00:00:00'; $order = 'terakhir'; }
    elseif ($f === 'top') { $order = 'total_belanja DESC'; }
    elseif ($f === 'baru') { $w[] = 'dibuat >= ?'; $a[] = date('Y-m-01') . ' 00:00:00'; $order = 'dibuat DESC'; }
    elseif ($f === 'nonaktif') { $w[] = 'aktif = 0'; }
    $list = array_map('member_out', rows('SELECT * FROM member' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY ' . $order . ' LIMIT 3000', $a));
    $d30 = date('Y-m-d', strtotime('-29 days')) . ' 00:00:00';
    $om = row("SELECT COALESCE(SUM(total),0) t, COALESCE(SUM(CASE WHEN member_id <> '' THEN total ELSE 0 END),0) m FROM transaksi WHERE status = 'Selesai' AND tanggal >= ?", [$d30]);
    return ['list' => $list, 'sum' => [
        'total' => (int)val('SELECT COUNT(*) FROM member WHERE aktif = 1'),
        'baru' => (int)val('SELECT COUNT(*) FROM member WHERE dibuat >= ?', [date('Y-m-01') . ' 00:00:00']),
        'aktif30' => (int)val('SELECT COUNT(*) FROM member WHERE terakhir >= ?', [$d30]),
        'poin' => (int)val('SELECT COALESCE(SUM(poin),0) FROM member WHERE aktif = 1'),
        'ultah' => (int)val('SELECT COUNT(*) FROM member WHERE aktif = 1 AND tgl_lahir IS NOT NULL AND MONTH(tgl_lahir) = ?', [(int)date('n')]),
        'omzetMember' => jn($om['m']), 'omzetTotal' => jn($om['t']),
    ], 'poin' => poin_cfg()];
}
function mb_get(array $d): array
{
    $m = row('SELECT * FROM member WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$m) throw new AppErr('Member tidak ditemukan');
    return ['m' => member_out($m),
        'trx' => array_map(fn($t) => ['no' => $t['no'], 'tanggal' => $t['tanggal'], 'total' => jn($t['total']), 'status' => $t['status'], 'poinDapat' => (int)$t['poin_dapat'], 'poinTukar' => (int)$t['poin_tukar'], 'kasir' => $t['kasir']],
            rows('SELECT no, tanggal, total, status, poin_dapat, poin_tukar, kasir FROM transaksi WHERE member_id = ? ORDER BY tanggal DESC LIMIT 60', [$m['id']])),
        'poin' => array_map(fn($p) => ['tanggal' => $p['tanggal'], 'jenis' => $p['jenis'], 'ref' => $p['ref'], 'poin' => (int)$p['poin'], 'saldo' => (int)$p['saldo'], 'ket' => $p['ket'], 'user' => $p['user']],
            rows('SELECT * FROM member_poin WHERE member_id = ? ORDER BY id DESC LIMIT 100', [$m['id']])),
        'fav' => array_map(fn($r) => ['nama' => $r['nama'], 'qty' => jn($r['q']), 'satuan' => $r['satuan']],
            rows("SELECT d.nama, d.satuan, SUM(d.qty) q FROM transaksi_detail d JOIN transaksi t ON t.no = d.no WHERE t.member_id = ? AND d.status = 'Selesai' GROUP BY d.pid, d.nama, d.satuan ORDER BY q DESC LIMIT 5", [$m['id']])),
    ];
}
function mb_save(array $d, array $u): array { return member_save($d, $u); }
function mb_poin(array $d, array $u): array
{
    $m = row('SELECT * FROM member WHERE id = ? FOR UPDATE', [(string)($d['id'] ?? '')]);
    if (!$m) throw new AppErr('Member tidak ditemukan');
    $n = (int)num($d['poin'] ?? 0); $ket = str($d['ket'] ?? '', 200);
    if ($n === 0) throw new AppErr('Isi jumlah poin (+ menambah, − mengurangi)');
    if ($ket === '') throw new AppErr('Isi keterangan koreksi poin');
    if ((int)$m['poin'] + $n < 0) throw new AppErr('Poin tidak boleh menjadi minus (saldo ' . (int)$m['poin'] . ')');
    $s = member_poin($m['id'], $n, 'Koreksi', '', $ket, $u);
    log_act($u, 'Koreksi poin', 'Member', $m['kode'] . ' ' . ($n > 0 ? '+' : '') . $n);
    return ['poin' => $s];
}
function mb_poinSet(array $d, array $u): array
{
    $per = (int)num($d['per'] ?? 0); $nil = (int)num($d['nilai'] ?? 0); $min = (int)num($d['min'] ?? 0);
    if ($per < 100) throw new AppErr('Kelipatan belanja per poin minimal Rp 100');
    if ($nil < 0 || $nil > $per) throw new AppErr('Nilai tukar 1 poin harus antara 0 dan kelipatan belanjanya');
    if ($min < 1) throw new AppErr('Minimal tukar poin paling sedikit 1');
    setting_put(['MemberAktif' => !empty($d['aktif']) ? '1' : '0', 'PoinPer' => (string)$per, 'PoinNilai' => (string)$nil, 'PoinMin' => (string)$min]);
    log_act($u, 'Ubah', 'Member', 'Program poin: Rp' . $per . '/poin, 1 poin = Rp' . $nil);
    return poin_cfg();
}

/* ======================================================================
 *  PROMO
 * ==================================================================== */
function pr_status(array $p): string
{
    if (!$p['aktif']) return 'Nonaktif';
    $t = today_s();
    if ($p['selesai'] && $t > $p['selesai']) return 'Berakhir';
    if ($p['mulai'] && $t < $p['mulai']) return 'Terjadwal';
    return promo_live($p) ? 'Berjalan' : 'Di luar jam';
}
function pr_out(array $r, array $st): array
{
    $p = promo_norm($r);
    return ['id' => $p['id'], 'nama' => $p['nama'], 'tipe' => $p['tipe'], 'nt' => $p['nt'], 'nilai' => jn($p['nilai']), 'target' => $p['target'], 'ids' => $p['ids'], 'beli' => $p['beli'], 'gratis' => $p['gratis'],
        'tingkat' => $p['tingkat'], 'min' => jn($p['min']), 'maks' => jn($p['maks']), 'mulai' => $p['mulai'] ?? '', 'selesai' => $p['selesai'] ?? '', 'hari' => $p['hari'], 'jam1' => $p['jam1'], 'jam2' => $p['jam2'],
        'member' => $p['member'], 'aktif' => $p['aktif'], 'status' => pr_status($p), 'label' => promo_label($p), 'dibuat' => $r['dibuat_oleh'],
        'dipakai' => (int)($st[$p['id']]['n'] ?? 0), 'diskon' => jn($st[$p['id']]['d'] ?? 0)];
}
function pr_list(): array
{
    $st = []; foreach (rows("SELECT promo_id, COUNT(DISTINCT no) n, SUM(diskon) d FROM promo_pakai WHERE status = 'Selesai' GROUP BY promo_id") as $r) $st[$r['promo_id']] = $r;
    return array_map(fn($r) => pr_out($r, $st), rows('SELECT * FROM promo ORDER BY aktif DESC, dibuat DESC'));
}
function pr_save(array $d, array $u): array
{
    $nama = str($d['nama'] ?? '', 120);
    if ($nama === '') throw new AppErr('Nama promo wajib diisi');
    $tipe = (string)($d['tipe'] ?? '');
    if (!isset(PROMO_TIPE[$tipe])) throw new AppErr('Jenis promo tidak valid');
    $nt = in_array($d['nt'] ?? '', ['p', 'r', 'h'], true) ? $d['nt'] : 'p';
    $nilai = r2(max(0, num($d['nilai'] ?? 0)));
    $beli = (int)num($d['beli'] ?? 0); $gratis = (int)num($d['gratis'] ?? 0);
    $tingkat = []; $min = r2(max(0, num($d['min'] ?? 0))); $maks = r2(max(0, num($d['maks'] ?? 0)));
    if ($tipe === 'diskon') {
        if ($nilai <= 0) throw new AppErr('Isi besar diskon');
        if ($nt === 'p' && $nilai > 100) throw new AppErr('Diskon persen maksimal 100%');
    } elseif ($tipe === 'bxgy') {
        if ($beli < 1 || $gratis < 1) throw new AppErr('Isi jumlah beli dan jumlah gratis (minimal 1)');
        $nt = 'p'; $nilai = 0;
    } elseif ($tipe === 'grosir') {
        foreach ($d['tingkat'] ?? [] as $t) { $m = r3(num($t['min'] ?? 0)); $v = r2(num($t['v'] ?? 0)); if ($m > 0 && $v > 0) { if ($nt === 'p' && $v > 100) throw new AppErr('Diskon persen maksimal 100%'); $tingkat[] = ['min' => $m, 'v' => $v]; } }
        if (!$tingkat) throw new AppErr('Isi minimal satu tingkat harga grosir (jumlah minimum dan harga/diskon)');
        if (count(array_unique(array_column($tingkat, 'min'))) !== count($tingkat)) throw new AppErr('Jumlah minimum tiap tingkat grosir harus berbeda');
        $nilai = 0;
    } else { // nota
        if ($nt === 'h') $nt = 'r';
        if ($nilai <= 0) throw new AppErr('Isi besar diskon belanja');
        if ($nt === 'p' && $nilai > 100) throw new AppErr('Diskon persen maksimal 100%');
        if ($min <= 0) throw new AppErr('Isi minimal belanja untuk promo nota');
    }
    $target = $tipe === 'nota' ? 'semua' : (in_array($d['target'] ?? '', ['semua', 'kategori', 'produk'], true) ? $d['target'] : 'semua');
    $ids = array_values(array_unique(array_filter(array_map(fn($x) => str($x, 80), (array)($d['ids'] ?? [])))));
    if ($target !== 'semua' && !$ids) throw new AppErr('Pilih ' . ($target === 'kategori' ? 'kategori' : 'produk') . ' yang mendapat promo');
    if ($target === 'semua') $ids = [];
    $mulai = (string)($d['mulai'] ?? ''); $selesai = (string)($d['selesai'] ?? '');
    $mulai = is_date($mulai) ? $mulai : null; $selesai = is_date($selesai) ? $selesai : null;
    if ($mulai && $selesai && $selesai < $mulai) throw new AppErr('Tanggal selesai tidak boleh sebelum tanggal mulai');
    $hari = implode('', array_unique(str_split(preg_replace('/[^1-7]/', '', (string)($d['hari'] ?? '1234567')) ?: '')));
    if ($hari === '') throw new AppErr('Pilih minimal satu hari berlaku');
    $j1 = (string)($d['jam1'] ?? ''); $j2 = (string)($d['jam2'] ?? '');
    $okJ = fn($j) => (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $j);
    if (($j1 !== '' || $j2 !== '') && (!$okJ($j1) || !$okJ($j2) || $j1 === $j2)) throw new AppErr('Isi jam mulai dan jam selesai dengan benar (contoh 15:00 – 17:00), atau kosongkan keduanya');
    $vals = [$nama, $tipe, $nt, $nilai, $target, json_encode($ids, JSON_UNESCAPED_UNICODE), $beli, $gratis, json_encode($tingkat), $min, $maks, $mulai, $selesai, $hari, $j1, $j2, !empty($d['member']) ? 1 : 0, !isset($d['aktif']) || !empty($d['aktif']) ? 1 : 0];
    $id = (string)($d['id'] ?? '');
    if ($id) {
        if (!val('SELECT id FROM promo WHERE id = ?', [$id])) throw new AppErr('Promo tidak ditemukan');
        q('UPDATE promo SET nama=?, tipe=?, nilai_tipe=?, nilai=?, target=?, target_ids=?, beli=?, gratis=?, tingkat=?, min_belanja=?, maks_diskon=?, mulai=?, selesai=?, hari=?, jam_mulai=?, jam_selesai=?, khusus_member=?, aktif=? WHERE id=?', array_merge($vals, [$id]));
    } else {
        $id = uid('PR');
        q('INSERT INTO promo (nama, tipe, nilai_tipe, nilai, target, target_ids, beli, gratis, tingkat, min_belanja, maks_diskon, mulai, selesai, hari, jam_mulai, jam_selesai, khusus_member, aktif, id, dibuat, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            array_merge($vals, [$id, now_s(), $u['username']]));
    }
    log_act($u, empty($d['id']) ? 'Tambah' : 'Ubah', 'Promo', $nama);
    return pr_out(row('SELECT * FROM promo WHERE id = ?', [$id]), []);
}
function pr_toggle(array $d, array $u): array
{
    $r = row('SELECT * FROM promo WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$r) throw new AppErr('Promo tidak ditemukan');
    q('UPDATE promo SET aktif = ? WHERE id = ?', [$r['aktif'] ? 0 : 1, $r['id']]);
    log_act($u, $r['aktif'] ? 'Nonaktifkan' : 'Aktifkan', 'Promo', $r['nama']);
    return ['aktif' => !$r['aktif']];
}
function pr_del(array $d, array $u): array
{
    $r = row('SELECT * FROM promo WHERE id = ?', [(string)($d['id'] ?? '')]);
    if (!$r) throw new AppErr('Promo tidak ditemukan');
    if ((int)val('SELECT COUNT(*) FROM promo_pakai WHERE promo_id = ?', [$r['id']])) throw new AppErr('Promo sudah pernah dipakai transaksi sehingga tidak dihapus agar laporan tetap utuh. Nonaktifkan saja.');
    q('DELETE FROM promo WHERE id = ?', [$r['id']]);
    log_act($u, 'Hapus', 'Promo', $r['nama']);
    return ['ok' => 1];
}

/* ======================================================================
 *  DASHBOARD PEMILIK & LAPORAN
 * ==================================================================== */
function lp_cost(array $u): bool { return ($u['akses']['laporan'] ?? 0) >= 3; }
function lp_lokW(array $d, string $col = 'lokasi_id'): array
{
    $l = (string)($d['lokasi'] ?? '');
    return $l !== '' && isset(lokasi_all()[$l]) ? [" AND $col = ?", [$l]] : ['', []];
}
function lp_sales(string $a, string $b, array $d): array
{
    [$lw, $la] = lp_lokW($d);
    return row("SELECT COUNT(*) n, COALESCE(SUM(total),0) omzet, COALESCE(SUM(laba),0) laba, COALESCE(SUM(diskon),0) diskon, COALESCE(SUM(diskon_promo),0) promo, COALESCE(SUM(diskon_poin),0) poin,
        COALESCE(SUM(CASE WHEN member_id <> '' THEN total ELSE 0 END),0) omzetMember, COUNT(DISTINCT NULLIF(member_id,'')) member FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?$lw", array_merge([$a, $b], $la));
}
function lp_dash(array $d, array $u): array
{
    [$f, $t, $a, $b] = t2_range($d);
    $cost = lp_cost($u); $days = diff_days($t, $f) + 1;
    $pf = add_days($f, -$days); $pt = add_days($f, -1);
    $cur = lp_sales($a, $b, $d); $prv = lp_sales($pf . ' 00:00:00', $pt . ' 23:59:59', $d);
    $k = fn($r) => ['omzet' => jn($r['omzet']), 'n' => (int)$r['n'], 'laba' => $cost ? jn($r['laba']) : null, 'diskon' => jn($r['diskon']), 'promo' => jn($r['promo']), 'poin' => jn($r['poin']),
        'avg' => (int)$r['n'] ? (int)round((float)$r['omzet'] / (int)$r['n']) : 0, 'omzetMember' => jn($r['omzetMember']), 'member' => (int)$r['member']];
    [$lw, $la] = lp_lokW($d);
    [$lwt, $lat] = lp_lokW($d, 't.lokasi_id');
    $day = [];
    foreach (rows("SELECT DATE(tanggal) d, COUNT(*) n, SUM(total) o, SUM(laba) l FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?$lw GROUP BY DATE(tanggal) ORDER BY d", array_merge([$a, $b], $la)) as $r)
        $day[] = ['d' => $r['d'], 'n' => (int)$r['n'], 'o' => jn($r['o']), 'l' => $cost ? jn($r['l']) : null];
    $L = lokasi_all();
    $lok = array_map(fn($r) => ['nama' => $L[$r['lokasi_id']]['nama'] ?? 'Lainnya', 'n' => (int)$r['n'], 'o' => jn($r['o']), 'l' => $cost ? jn($r['l']) : null],
        rows("SELECT lokasi_id, COUNT(*) n, SUM(total) o, SUM(laba) l FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ? GROUP BY lokasi_id ORDER BY o DESC", [$a, $b]));
    $top = array_map(fn($r) => ['nama' => $r['nama'], 'satuan' => $r['satuan'], 'q' => jn($r['q']), 'o' => jn($r['o']), 'l' => $cost ? jn(r2((float)$r['o'] - (float)$r['h'])) : null],
        rows("SELECT d.pid, d.nama, d.satuan, SUM(d.qty) q, SUM(d.subtotal) o, SUM(d.hpp * d.qty) h FROM transaksi_detail d JOIN transaksi t ON t.no = d.no WHERE d.status = 'Selesai' AND d.tanggal BETWEEN ? AND ?$lwt GROUP BY d.pid, d.nama, d.satuan ORDER BY o DESC LIMIT 8", array_merge([$a, $b], $lat)));
    $kat = array_map(fn($r) => ['nama' => $r['kategori'] ?: 'Tanpa kategori', 'o' => jn($r['o'])],
        rows("SELECT d.kategori, SUM(d.subtotal) o FROM transaksi_detail d JOIN transaksi t ON t.no = d.no WHERE d.status = 'Selesai' AND d.tanggal BETWEEN ? AND ?$lwt GROUP BY d.kategori ORDER BY o DESC LIMIT 8", array_merge([$a, $b], $lat)));
    $met = array_map(fn($r) => ['nama' => $r['metode'], 'n' => (int)$r['n'], 'o' => jn($r['o'])], rows("SELECT metode, COUNT(*) n, SUM(total) o FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?$lw GROUP BY metode ORDER BY o DESC", array_merge([$a, $b], $la)));
    $jam = array_fill(0, 24, 0);
    foreach (rows("SELECT HOUR(tanggal) h, SUM(total) o FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?$lw GROUP BY HOUR(tanggal)", array_merge([$a, $b], $la)) as $r) $jam[(int)$r['h']] = jn($r['o']);
    $prm = array_map(fn($r) => ['nama' => $r['nama'], 'n' => (int)$r['n'], 'd' => jn($r['d'])],
        rows("SELECT p.nama, COUNT(DISTINCT k.no) n, SUM(k.diskon) d FROM promo_pakai k JOIN promo p ON p.id = k.promo_id WHERE k.status = 'Selesai' AND k.tanggal BETWEEN ? AND ? GROUP BY p.id, p.nama ORDER BY d DESC LIMIT 5", [$a, $b]));
    $mem = array_map(fn($r) => ['kode' => $r['kode'], 'nama' => $r['nama'], 'n' => (int)$r['n'], 'o' => jn($r['o'])],
        rows("SELECT m.kode, m.nama, COUNT(*) n, SUM(t.total) o FROM transaksi t JOIN member m ON m.id = t.member_id WHERE t.status = 'Selesai' AND t.tanggal BETWEEN ? AND ? GROUP BY m.id, m.kode, m.nama ORDER BY o DESC LIMIT 5", [$a, $b]));
    // stok
    $minL = []; foreach (rows('SELECT pid, SUM(min) m FROM stok_lokasi GROUP BY pid') as $r) $minL[$r['pid']] = (float)$r['m'];
    $habis = 0; $tipis = 0; $nilai = 0.0;
    foreach (rows('SELECT id, stok, stok_min, harga_beli FROM produk') as $p) {
        $mn = max((float)$p['stok_min'], $minL[$p['id']] ?? 0); $s = (float)$p['stok'];
        if ($s <= 0) $habis++; elseif ($mn > 0 && $s <= $mn) $tipis++;
        $nilai += max(0, $s) * (float)$p['harga_beli'];
    }
    // pembelian & hutang
    $po = row("SELECT COUNT(*) n, COALESCE(SUM(total),0) t FROM po WHERE status IN ('Draf','Dipesan','Sebagian')");
    $td = today_s(); $h7 = add_days($td, 7);
    $hut = ['sisa' => 0.0, 'telat' => 0.0, 'minggu' => 0.0, 'nTelat' => 0];
    foreach (rows("SELECT f.total, f.jatuh_tempo, COALESCE((SELECT SUM(jumlah) FROM pembayaran b WHERE b.faktur_id = f.id),0) paid FROM faktur f WHERE f.tipe = 'Pembelian'") as $r) {
        $s = r2((float)$r['total'] - (float)$r['paid']); if ($s <= 0.005) continue;
        $hut['sisa'] += $s;
        if ($r['jatuh_tempo'] < $td) { $hut['telat'] += $s; $hut['nTelat']++; } elseif ($r['jatuh_tempo'] <= $h7) $hut['minggu'] += $s;
    }
    $kas = [];
    if ($cost) foreach (rows("SELECT a.kode, a.nama, a.saldo_awal + COALESCE((SELECT SUM(j.debit - j.kredit) FROM jurnal j WHERE j.kode_akun = a.kode),0) s FROM akun a WHERE a.subkategori = 'Kas & Bank' AND a.status = 'Aktif' ORDER BY a.kode") as $r) $kas[] = ['nama' => $r['nama'], 's' => jn(r2((float)$r['s']))];
    return [
        'kpi' => $k($cur), 'prev' => $k($prv), 'cost' => $cost, 'day' => $day, 'lokasi' => $lok, 'top' => $top, 'kategori' => $kat, 'metode' => $met, 'jam' => $jam, 'promo' => $prm, 'member' => $mem,
        'memberBaru' => (int)val('SELECT COUNT(*) FROM member WHERE dibuat BETWEEN ? AND ?', [$a, $b]), 'memberTotal' => (int)val('SELECT COUNT(*) FROM member WHERE aktif = 1'),
        'stok' => ['habis' => $habis, 'tipis' => $tipis, 'nilai' => $cost ? (int)round($nilai) : null, 'produk' => (int)val('SELECT COUNT(*) FROM produk')],
        'po' => ['n' => (int)$po['n'], 't' => jn($po['t'])], 'hutang' => ['sisa' => jn(r2($hut['sisa'])), 'telat' => jn(r2($hut['telat'])), 'minggu' => jn(r2($hut['minggu'])), 'nTelat' => $hut['nTelat']],
        'mutasi' => ['jalan' => (int)val("SELECT COUNT(*) FROM mutasi WHERE status = 'Dikirim'"), 'minta' => (int)val("SELECT COUNT(*) FROM mutasi WHERE status = 'Permintaan'")],
        'kas' => $kas,
    ];
}

const LAP_JENIS = [
    'harian' => 'Penjualan per hari', 'produk' => 'Penjualan per produk', 'kategori' => 'Penjualan per kategori', 'kasir' => 'Penjualan per kasir',
    'lokasi' => 'Penjualan per lokasi (cabang)', 'metode' => 'Penjualan per metode bayar', 'jam' => 'Penjualan per jam', 'promo' => 'Pemakaian promo',
    'member' => 'Belanja member', 'pemasok' => 'Pembelian per pemasok', 'persediaan' => 'Nilai persediaan per lokasi', 'tidaklaku' => 'Produk tidak laku',
];
function lp_run(array $d, array $u): array
{
    $kind = (string)($d['kind'] ?? '');
    if (!isset(LAP_JENIS[$kind])) throw new AppErr('Jenis laporan tidak dikenal');
    $cost = lp_cost($u);
    [$f, $t, $a, $b] = in_array($kind, ['persediaan'], true) ? [today_s(), today_s(), '', ''] : t2_range($d);
    [$lw, $la] = lp_lokW($d); [$lwt, $lat] = lp_lokW($d, 't.lokasi_id');
    $ab = array_merge([$a, $b], $la); $abt = array_merge([$a, $b], $lat);
    $sum = fn(array $rows, int $i) => array_sum(array_map(fn($r) => (float)($r[$i] ?? 0), $rows));
    $sec = ['name' => LAP_JENIS[$kind], 'title' => LAP_JENIS[$kind]];
    switch ($kind) {
        case 'harian':
            $Q = []; foreach (rows("SELECT DATE(d.tanggal) d, SUM(d.qty) q FROM transaksi_detail d JOIN transaksi t ON t.no = d.no WHERE d.status = 'Selesai' AND d.tanggal BETWEEN ? AND ?$lwt GROUP BY DATE(d.tanggal)", $abt) as $r) $Q[$r['d']] = (float)$r['q'];
            $R = rows("SELECT DATE(t.tanggal) d, COUNT(*) n, SUM(t.total) o, SUM(t.diskon) ds, SUM(t.laba) l FROM transaksi t WHERE t.status = 'Selesai' AND t.tanggal BETWEEN ? AND ?$lwt GROUP BY DATE(t.tanggal) ORDER BY d", $abt);
            foreach ($R as &$r) $r['q'] = $Q[$r['d']] ?? 0; unset($r);
            $rows = array_map(fn($r) => array_merge([$r['d'], (int)$r['n'], r3((float)$r['q']), jn($r['ds']), jn($r['o'])], $cost ? [jn($r['l'])] : []), $R);
            $sec['cols'] = array_merge([['h' => 'Tanggal', 'k' => 'date'], ['h' => 'Transaksi', 'k' => 'int'], ['h' => 'Item terjual', 'k' => 'dec'], ['h' => 'Diskon', 'k' => 'money'], ['h' => 'Omzet', 'k' => 'money']], $cost ? [['h' => 'Laba kotor', 'k' => 'money']] : []);
            $sec['total'] = array_merge(['Total', $sum($rows, 1), $sum($rows, 2), $sum($rows, 3), $sum($rows, 4)], $cost ? [$sum($rows, 5)] : []);
            break;
        case 'produk':
            $R = rows("SELECT d.pid, MAX(p.sku) sku, d.nama, d.kategori, d.satuan, SUM(d.qty) q, SUM(d.subtotal) o, SUM(d.hpp * d.qty) h FROM transaksi_detail d JOIN transaksi t ON t.no = d.no LEFT JOIN produk p ON p.id = d.pid WHERE d.status = 'Selesai' AND d.tanggal BETWEEN ? AND ?$lwt GROUP BY d.pid, d.nama, d.kategori, d.satuan ORDER BY o DESC", $abt);
            $rows = array_map(fn($r) => array_merge([(string)$r['sku'], $r['nama'], $r['kategori'], r3((float)$r['q']), $r['satuan'], jn($r['o'])], $cost ? [jn(r2((float)$r['h'])), jn(r2((float)$r['o'] - (float)$r['h']))] : []), $R);
            $sec['cols'] = array_merge([['h' => 'SKU', 'k' => 'text'], ['h' => 'Produk', 'k' => 'text', 'wrap' => 1], ['h' => 'Kategori', 'k' => 'text'], ['h' => 'Qty', 'k' => 'dec'], ['h' => 'Satuan', 'k' => 'c'], ['h' => 'Omzet', 'k' => 'money']], $cost ? [['h' => 'HPP', 'k' => 'money'], ['h' => 'Laba kotor', 'k' => 'money']] : []);
            $sec['total'] = array_merge(['Total', null, null, null, null, $sum($rows, 5)], $cost ? [$sum($rows, 6), $sum($rows, 7)] : []);
            break;
        case 'kategori':
            $R = rows("SELECT d.kategori, SUM(d.qty) q, SUM(d.subtotal) o, SUM(d.hpp * d.qty) h FROM transaksi_detail d JOIN transaksi t ON t.no = d.no WHERE d.status = 'Selesai' AND d.tanggal BETWEEN ? AND ?$lwt GROUP BY d.kategori ORDER BY o DESC", $abt);
            $tot = array_sum(array_map(fn($r) => (float)$r['o'], $R)) ?: 1;
            $rows = array_map(fn($r) => array_merge([$r['kategori'] ?: 'Tanpa kategori', r3((float)$r['q']), jn($r['o']), round((float)$r['o'] / $tot * 100, 1)], $cost ? [jn(r2((float)$r['o'] - (float)$r['h']))] : []), $R);
            $sec['cols'] = array_merge([['h' => 'Kategori', 'k' => 'text'], ['h' => 'Qty', 'k' => 'dec'], ['h' => 'Omzet', 'k' => 'money'], ['h' => 'Porsi (%)', 'k' => 'dec']], $cost ? [['h' => 'Laba kotor', 'k' => 'money']] : []);
            $sec['total'] = array_merge(['Total', $sum($rows, 1), $sum($rows, 2), null], $cost ? [$sum($rows, 4)] : []);
            break;
        case 'kasir':
            $R = rows("SELECT t.kasir, COUNT(*) n, SUM(t.total) o, SUM(t.diskon) ds FROM transaksi t WHERE t.status = 'Selesai' AND t.tanggal BETWEEN ? AND ?$lwt GROUP BY t.kasir ORDER BY o DESC", $abt);
            $btl = []; foreach (rows("SELECT kasir, COUNT(*) n FROM transaksi WHERE status = 'Batal' AND tanggal BETWEEN ? AND ?$lw GROUP BY kasir", $ab) as $r) $btl[$r['kasir']] = (int)$r['n'];
            $rows = array_map(fn($r) => [$r['kasir'], (int)$r['n'], jn($r['o']), (int)round((float)$r['o'] / max(1, (int)$r['n'])), jn($r['ds']), $btl[$r['kasir']] ?? 0], $R);
            $sec['cols'] = [['h' => 'Kasir', 'k' => 'text'], ['h' => 'Transaksi', 'k' => 'int'], ['h' => 'Omzet', 'k' => 'money'], ['h' => 'Rata-rata', 'k' => 'money'], ['h' => 'Diskon', 'k' => 'money'], ['h' => 'Dibatalkan', 'k' => 'int']];
            $sec['total'] = ['Total', $sum($rows, 1), $sum($rows, 2), null, $sum($rows, 4), $sum($rows, 5)];
            break;
        case 'lokasi':
            $L = lokasi_all();
            $R = rows("SELECT lokasi_id, COUNT(*) n, SUM(total) o, SUM(laba) l FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ? GROUP BY lokasi_id ORDER BY o DESC", [$a, $b]);
            $rows = array_map(fn($r) => array_merge([$L[$r['lokasi_id']]['nama'] ?? '-', (int)$r['n'], jn($r['o']), (int)round((float)$r['o'] / max(1, (int)$r['n']))], $cost ? [jn($r['l'])] : []), $R);
            $sec['cols'] = array_merge([['h' => 'Lokasi', 'k' => 'text'], ['h' => 'Transaksi', 'k' => 'int'], ['h' => 'Omzet', 'k' => 'money'], ['h' => 'Rata-rata', 'k' => 'money']], $cost ? [['h' => 'Laba kotor', 'k' => 'money']] : []);
            $sec['total'] = array_merge(['Total', $sum($rows, 1), $sum($rows, 2), null], $cost ? [$sum($rows, 4)] : []);
            break;
        case 'metode':
            $R = rows("SELECT metode, COUNT(*) n, SUM(total) o FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?$lw GROUP BY metode ORDER BY o DESC", $ab);
            $rows = array_map(fn($r) => [$r['metode'], (int)$r['n'], jn($r['o'])], $R);
            $sec['cols'] = [['h' => 'Metode', 'k' => 'text'], ['h' => 'Transaksi', 'k' => 'int'], ['h' => 'Omzet', 'k' => 'money']];
            $sec['total'] = ['Total', $sum($rows, 1), $sum($rows, 2)];
            break;
        case 'jam':
            $R = []; foreach (rows("SELECT HOUR(tanggal) h, COUNT(*) n, SUM(total) o FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?$lw GROUP BY HOUR(tanggal)", $ab) as $r) $R[(int)$r['h']] = $r;
            $rows = [];
            for ($h = 0; $h < 24; $h++) if (isset($R[$h])) $rows[] = [str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':00–' . str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':59', (int)$R[$h]['n'], jn($R[$h]['o'])];
            $sec['cols'] = [['h' => 'Jam', 'k' => 'c'], ['h' => 'Transaksi', 'k' => 'int'], ['h' => 'Omzet', 'k' => 'money']];
            $sec['total'] = ['Total', $sum($rows, 1), $sum($rows, 2)];
            break;
        case 'promo':
            $R = rows("SELECT p.nama, p.tipe, COUNT(DISTINCT k.no) n, SUM(k.diskon) d FROM promo_pakai k JOIN promo p ON p.id = k.promo_id WHERE k.status = 'Selesai' AND k.tanggal BETWEEN ? AND ? GROUP BY p.id, p.nama, p.tipe ORDER BY d DESC", [$a, $b]);
            $rows = array_map(fn($r) => [$r['nama'], PROMO_TIPE[$r['tipe']] ?? $r['tipe'], (int)$r['n'], jn($r['d'])], $R);
            $sec['cols'] = [['h' => 'Promo', 'k' => 'text', 'wrap' => 1], ['h' => 'Jenis', 'k' => 'text'], ['h' => 'Transaksi', 'k' => 'int'], ['h' => 'Total potongan', 'k' => 'money']];
            $sec['total'] = ['Total', null, null, $sum($rows, 3)];
            break;
        case 'member':
            $R = rows("SELECT m.kode, m.nama, m.hp, m.poin, COUNT(*) n, SUM(t.total) o, SUM(t.poin_dapat) pd, SUM(t.poin_tukar) pt FROM transaksi t JOIN member m ON m.id = t.member_id WHERE t.status = 'Selesai' AND t.tanggal BETWEEN ? AND ?$lwt GROUP BY m.id, m.kode, m.nama, m.hp, m.poin ORDER BY o DESC", $abt);
            $rows = array_map(fn($r) => [$r['kode'], $r['nama'], $r['hp'], (int)$r['n'], jn($r['o']), (int)$r['pd'], (int)$r['pt'], (int)$r['poin']], $R);
            $sec['cols'] = [['h' => 'Kode', 'k' => 'text'], ['h' => 'Nama', 'k' => 'text', 'wrap' => 1], ['h' => 'HP', 'k' => 'text'], ['h' => 'Transaksi', 'k' => 'int'], ['h' => 'Belanja', 'k' => 'money'], ['h' => 'Poin didapat', 'k' => 'int'], ['h' => 'Poin ditukar', 'k' => 'int'], ['h' => 'Saldo poin', 'k' => 'int']];
            $sec['total'] = ['Total', null, null, $sum($rows, 3), $sum($rows, 4), $sum($rows, 5), $sum($rows, 6), null];
            break;
        case 'pemasok':
            $R = rows("SELECT kontak_id, MAX(supplier) s, COUNT(*) n, SUM(total) o, SUM(CASE WHEN bayar = 'Hutang' THEN total ELSE 0 END) h FROM stok_masuk_doc WHERE tanggal BETWEEN ? AND ? AND supplier <> '' GROUP BY kontak_id ORDER BY o DESC", [$a, $b]);
            $ret = []; foreach (rows('SELECT kontak_id, SUM(total) t FROM retur_beli WHERE tanggal BETWEEN ? AND ? GROUP BY kontak_id', [$a, $b]) as $r) $ret[$r['kontak_id']] = (float)$r['t'];
            $rows = array_map(fn($r) => [$r['s'], (int)$r['n'], jn($r['o']), jn($r['h']), jn($ret[$r['kontak_id']] ?? 0), jn(r2((float)$r['o'] - ($ret[$r['kontak_id']] ?? 0)))], $R);
            $sec['cols'] = [['h' => 'Pemasok', 'k' => 'text', 'wrap' => 1], ['h' => 'Penerimaan', 'k' => 'int'], ['h' => 'Total pembelian', 'k' => 'money'], ['h' => 'Secara hutang', 'k' => 'money'], ['h' => 'Retur', 'k' => 'money'], ['h' => 'Bersih', 'k' => 'money']];
            $sec['total'] = ['Total', $sum($rows, 1), $sum($rows, 2), $sum($rows, 3), $sum($rows, 4), $sum($rows, 5)];
            break;
        case 'persediaan':
            $L = array_filter(lokasi_all(), fn($l) => $l['aktif']);
            $Q = []; foreach (rows('SELECT pid, lokasi_id, qty FROM stok_lokasi') as $r) $Q[$r['pid']][$r['lokasi_id']] = (float)$r['qty'];
            $rows = [];
            foreach (rows('SELECT * FROM produk ORDER BY kategori, nama') as $p) {
                $r = [(string)$p['sku'], $p['nama'], $p['kategori'], $p['satuan']];
                foreach ($L as $lid => $_) $r[] = r3($Q[$p['id']][$lid] ?? 0);
                $r[] = r3((float)$p['stok']);
                if ($cost) { $r[] = jn($p['harga_beli']); $r[] = (int)round(max(0, (float)$p['stok']) * (float)$p['harga_beli']); }
                $rows[] = $r;
            }
            $sec['cols'] = [['h' => 'SKU', 'k' => 'text'], ['h' => 'Produk', 'k' => 'text', 'wrap' => 1], ['h' => 'Kategori', 'k' => 'text'], ['h' => 'Satuan', 'k' => 'c']];
            foreach ($L as $l) $sec['cols'][] = ['h' => $l['nama'], 'k' => 'dec'];
            $sec['cols'][] = ['h' => 'Total', 'k' => 'dec'];
            if ($cost) { $sec['cols'][] = ['h' => 'Harga beli', 'k' => 'money']; $sec['cols'][] = ['h' => 'Nilai persediaan', 'k' => 'money']; }
            $tot = array_fill(0, count($sec['cols']), null); $tot[0] = 'Total';
            $n = 4 + count($L); for ($i = 4; $i <= $n; $i++) $tot[$i] = $sum($rows, $i);
            if ($cost) $tot[$n + 2] = $sum($rows, $n + 2);
            $sec['total'] = $tot;
            $sec['note'] = 'Stok per lokasi saat laporan dibuat. Selisih antara total dan jumlah per lokasi adalah barang dalam perjalanan (mutasi yang belum diterima).';
            break;
        case 'tidaklaku':
            $sold = []; foreach (rows("SELECT DISTINCT pid FROM transaksi_detail WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?", [$a, $b]) as $r) $sold[$r['pid']] = 1;
            $last = []; foreach (rows("SELECT pid, MAX(tanggal) t FROM transaksi_detail WHERE status = 'Selesai' GROUP BY pid") as $r) $last[$r['pid']] = $r['t'];
            $rows = [];
            foreach (rows('SELECT * FROM produk WHERE stok > 0 ORDER BY nama') as $p) {
                if (isset($sold[$p['id']])) continue;
                $rows[] = array_merge([(string)$p['sku'], $p['nama'], $p['kategori'], r3((float)$p['stok']), $p['satuan'], $last[$p['id']] ?? '-'], $cost ? [(int)round((float)$p['stok'] * (float)$p['harga_beli'])] : []);
            }
            $sec['cols'] = array_merge([['h' => 'SKU', 'k' => 'text'], ['h' => 'Produk', 'k' => 'text', 'wrap' => 1], ['h' => 'Kategori', 'k' => 'text'], ['h' => 'Stok', 'k' => 'dec'], ['h' => 'Satuan', 'k' => 'c'], ['h' => 'Terakhir terjual', 'k' => 'text']], $cost ? [['h' => 'Nilai stok', 'k' => 'money']] : []);
            $sec['total'] = $cost ? ['Total', null, null, null, null, null, $sum($rows, 6)] : null;
            $sec['note'] = 'Produk yang masih punya stok tetapi tidak terjual sama sekali pada periode ini — kandidat promo atau berhenti dibeli.';
            break;
    }
    $sec['rows'] = $rows;
    if (empty($sec['total'])) unset($sec['total']);
    return ['title' => LAP_JENIS[$kind], 'from' => $f, 'to' => $t, 'section' => $sec, 'cost' => $cost];
}
