<?php
/**
 * ============================================================
 *  PROMO & MEMBER — mesin harga kasir (Dhyra v4)
 *  Satu-satunya tempat perhitungan harga keranjang: dipakai oleh
 *  pratinjau keranjang (promoHitung) dan oleh checkout, sehingga
 *  angka di layar selalu sama dengan yang disimpan.
 *
 *  Urutan potongan:
 *   1. Promo item terbaik per baris (diskon / beli X gratis Y / grosir)
 *   2. Diskon manual kasir per baris
 *   3. Promo nota (minimal belanja) terbaik
 *   4. Diskon manual transaksi
 *   5. Tukar poin member
 *   6. Pajak dihitung dari sisa setelah semua potongan
 * ============================================================
 */
declare(strict_types=1);

const PROMO_TIPE = ['diskon' => 'Diskon produk', 'bxgy' => 'Beli X gratis Y', 'grosir' => 'Harga grosir', 'nota' => 'Diskon belanja'];

/** Ubah baris tabel promo menjadi struktur siap pakai. */
function promo_norm(array $r): array
{
    $t = json_decode((string)$r['tingkat'], true) ?: [];
    $t = array_values(array_filter(array_map(fn($x) => ['min' => r3(num($x['min'] ?? 0)), 'v' => r2(num($x['v'] ?? 0))], $t), fn($x) => $x['min'] > 0 && $x['v'] > 0));
    usort($t, fn($a, $b) => $b['min'] <=> $a['min']);
    return [
        'id' => $r['id'], 'nama' => $r['nama'], 'tipe' => $r['tipe'], 'nt' => $r['nilai_tipe'], 'nilai' => (float)$r['nilai'], 'target' => $r['target'],
        'ids' => array_map('strval', json_decode((string)$r['target_ids'], true) ?: []), 'beli' => (int)$r['beli'], 'gratis' => (int)$r['gratis'], 'tingkat' => $t,
        'min' => (float)$r['min_belanja'], 'maks' => (float)$r['maks_diskon'], 'mulai' => $r['mulai'] ?? null, 'selesai' => $r['selesai'] ?? null, 'hari' => (string)$r['hari'],
        'jam1' => (string)$r['jam_mulai'], 'jam2' => (string)$r['jam_selesai'], 'member' => (bool)$r['khusus_member'], 'aktif' => (bool)$r['aktif'],
    ];
}

/** Apakah promo berlaku pada waktu $ts. */
function promo_live(array $p, ?int $ts = null): bool
{
    $ts = $ts ?? ($GLOBALS['__promo_ts'] ?? time()); $d = date('Y-m-d', $ts);
    if (!$p['aktif']) return false;
    if ($p['mulai'] && $d < $p['mulai']) return false;
    if ($p['selesai'] && $d > $p['selesai']) return false;
    if ($p['hari'] !== '' && strpos($p['hari'], date('N', $ts)) === false) return false;
    if ($p['jam1'] !== '' && $p['jam2'] !== '') {
        $h = date('H:i', $ts);
        $in = $p['jam1'] <= $p['jam2'] ? ($h >= $p['jam1'] && $h < $p['jam2']) : ($h >= $p['jam1'] || $h < $p['jam2']);
        if (!$in) return false;
    }
    return true;
}

function promo_active(bool $member): array
{
    if (!empty($GLOBALS['__no_promo'])) return [];          // transaksi offline: harga sudah final di perangkat kasir
    static $all = null;
    if ($all === null) $all = array_map('promo_norm', rows('SELECT * FROM promo WHERE aktif = 1'));
    return array_values(array_filter($all, fn($p) => promo_live($p) && ($member || !$p['member'])));
}

function promo_hits(array $p, array $prod): bool
{
    if ($p['target'] === 'semua') return true;
    if ($p['target'] === 'kategori') return in_array(mb_strtolower((string)$prod['kategori']), array_map('mb_strtolower', $p['ids']), true);
    return in_array((string)$prod['id'], $p['ids'], true);
}

/** Potongan per unit sesuai jenis nilai (p persen, r rupiah, h harga khusus). */
function promo_unit(string $nt, float $v, float $harga): float
{
    if ($nt === 'p') return $harga * min(100, max(0, $v)) / 100;
    if ($nt === 'h') return max(0, $harga - $v);
    return min($harga, max(0, $v));
}

/** Potongan promo item untuk satu baris. */
function promo_item_disc(array $p, float $harga, float $qty): int
{
    $g = (int)round($harga * $qty);
    if ($p['tipe'] === 'diskon') $d = promo_unit($p['nt'], $p['nilai'], $harga) * $qty;
    elseif ($p['tipe'] === 'bxgy') {
        if ($p['beli'] < 1 || $p['gratis'] < 1) return 0;
        $d = floor($qty / ($p['beli'] + $p['gratis'])) * $p['gratis'] * $harga;
    } elseif ($p['tipe'] === 'grosir') {
        $d = 0;
        foreach ($p['tingkat'] as $t) if ($qty + 1e-9 >= $t['min']) { $d = promo_unit($p['nt'], $t['v'], $harga) * $qty; break; }
    } else return 0;
    return (int)max(0, min($g, round($d)));
}

function promo_nota_disc(array $p, int $sub): int
{
    if ($p['tipe'] !== 'nota' || $sub <= 0 || $sub + 1e-9 < $p['min']) return 0;
    $d = $p['nt'] === 'p' ? $sub * min(100, $p['nilai']) / 100 : $p['nilai'];
    if ($p['nt'] === 'p' && $p['maks'] > 0) $d = min($d, $p['maks']);
    return (int)max(0, min($sub, round($d)));
}

/** Label singkat promo untuk ditampilkan di kartu produk. */
function promo_label(array $p): string
{
    if ($p['tipe'] === 'bxgy') return 'Beli ' . $p['beli'] . ' gratis ' . $p['gratis'];
    if ($p['tipe'] === 'grosir') { $t = end($p['tingkat']); return $t ? 'Grosir mulai ' . jn($t['min']) : 'Grosir'; }
    if ($p['tipe'] === 'diskon') return $p['nt'] === 'p' ? 'Diskon ' . jn($p['nilai']) . '%' : ($p['nt'] === 'h' ? 'Harga promo' : 'Hemat ' . fmt_num($p['nilai']));
    return $p['nama'];
}

/**
 * Harga coret & label untuk kartu produk kasir: {pid: {h: harga promo per unit|null, t: label, m: khusus member}}
 * Promo khusus member ikut ditampilkan dengan tanda "m".
 */
function promo_labels(): array
{
    $act = array_values(array_filter(promo_active(true), fn($p) => $p['tipe'] !== 'nota'));
    if (!$act) return [];
    $out = [];
    foreach (rows('SELECT id, kategori, harga_jual FROM produk') as $pr) {
        $best = null; $bh = null;
        foreach ($act as $p) {
            if (!promo_hits($p, $pr)) continue;
            $h = (float)$pr['harga_jual'];
            if ($p['tipe'] === 'diskon') {
                $nh = round($h - promo_unit($p['nt'], $p['nilai'], $h));
                if ($bh === null || $nh < $bh || ($best && $best['member'] && !$p['member'])) { $bh = $nh; $best = $p; }
            } elseif (!$best) $best = $p;
        }
        if ($best) $out[$pr['id']] = ['h' => $best['tipe'] === 'diskon' ? jn($bh) : null, 't' => promo_label($best), 'm' => $best['member']];
    }
    return $out;
}

/** Info program poin. */
function poin_cfg(): array
{
    $s = settings_all();
    return ['aktif' => ($s['MemberAktif'] ?? '1') === '1', 'per' => max(1, (int)num($s['PoinPer'] ?? 10000)), 'nilai' => max(0, (int)num($s['PoinNilai'] ?? 100)), 'min' => max(1, (int)num($s['PoinMin'] ?? 10))];
}

/**
 * Hitung keranjang. $items: [{id, qty, disc, dt}], $d: {gd:{v,t}, poin}, $mem: baris member atau null.
 * $lock = true dipakai saat checkout (produk dikunci).
 */
function k_price(array $items, array $d, ?array $mem, bool $lock = false): array
{
    $set = settings_all(); $pajakP = min(100, max(0, num($set['pajak'] ?? 0)));
    $ids = array_values(array_unique(array_map(fn($i) => (string)($i['id'] ?? ''), $items)));
    if (!$ids) throw new AppErr('Keranjang kosong');
    $P = [];
    foreach (rows('SELECT * FROM produk WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')' . ($lock ? ' FOR UPDATE' : ''), $ids) as $r) $P[$r['id']] = $r;
    $promos = promo_active($mem !== null);
    $itemP = array_values(array_filter($promos, fn($p) => $p['tipe'] !== 'nota'));
    $notaP = array_values(array_filter($promos, fn($p) => $p['tipe'] === 'nota'));

    $lines = []; $gross = 0; $dpT = 0; $dmT = 0; $sub = 0; $hpp = 0.0; $used = [];
    foreach ($items as $it) {
        $r = $P[(string)($it['id'] ?? '')] ?? null;
        if (!$r) throw new AppErr('Produk tidak ditemukan atau sudah dihapus');
        $qty = r3(num($it['qty'] ?? 0));
        if (!($qty > 0)) throw new AppErr('Jumlah tidak valid: ' . $r['nama']);
        $harga = (float)$r['harga_jual'];
        $g = (int)round($harga * $qty);
        $dp = 0; $bp = null;
        foreach ($itemP as $p) {
            if (!promo_hits($p, $r)) continue;
            $x = promo_item_disc($p, $harga, $qty);
            if ($x > $dp) { $dp = $x; $bp = $p; }
        }
        $base = $g - $dp;
        $dv = max(0, num($it['disc'] ?? 0));
        $dm = ($it['dt'] ?? 'p') === 'r' ? (int)min(round($dv), $base) : (int)round($base * min($dv, 100) / 100);
        $net = $base - $dm;
        if ($bp) $used[$bp['id']] = ($used[$bp['id']] ?? 0) + $dp;
        $gross += $g; $dpT += $dp; $dmT += $dm; $sub += $net; $hpp += (float)$r['harga_beli'] * $qty;
        $lines[] = ['id' => $r['id'], 'p' => $r, 'qty' => $qty, 'harga' => $harga, 'g' => $g, 'dp' => $dp, 'promo' => $bp ? $bp['nama'] : '', 'promoId' => $bp['id'] ?? '', 'dm' => $dm, 'net' => $net];
    }
    // promo nota
    $dn = 0; $np = null;
    foreach ($notaP as $p) { $x = promo_nota_disc($p, $sub); if ($x > $dn) { $dn = $x; $np = $p; } }
    if ($np) $used[$np['id']] = ($used[$np['id']] ?? 0) + $dn;
    $s2 = $sub - $dn;
    $gd = $d['gd'] ?? []; $gv = max(0, num($gd['v'] ?? 0));
    $gdisc = ($gd['t'] ?? 'p') === 'r' ? (int)min(round($gv), $s2) : (int)round($s2 * min($gv, 100) / 100);
    $s3 = $s2 - $gdisc;
    // poin member
    $pc = poin_cfg(); $tukar = 0; $nilaiPoin = 0;
    $minta = (int)max(0, num($d['poin'] ?? 0));
    if ($minta > 0) {
        if (!$mem) throw new AppErr('Pilih member terlebih dahulu untuk menukar poin');
        if (!$pc['aktif'] || $pc['nilai'] <= 0) throw new AppErr('Program poin sedang tidak aktif');
        if ($minta < $pc['min']) throw new AppErr('Penukaran poin minimal ' . $pc['min'] . ' poin');
        if ($minta > (int)$mem['poin']) throw new AppErr('Poin member tidak cukup (sisa ' . (int)$mem['poin'] . ' poin)');
        $tukar = (int)min($minta, floor($s3 / $pc['nilai']));
        $nilaiPoin = $tukar * $pc['nilai'];
    }
    $after = $s3 - $nilaiPoin;
    $tax = (int)round($after * $pajakP / 100);
    $total = $after + $tax;
    $dapat = $mem && $pc['aktif'] ? (int)floor($total / $pc['per']) : 0;
    return [
        'lines' => $lines, 'gross' => $gross, 'promoItem' => $dpT, 'manualItem' => $dmT, 'sub' => $sub,
        'nota' => $np ? ['id' => $np['id'], 'nama' => $np['nama'], 'd' => $dn] : null, 'gd' => $gdisc,
        'poinTukar' => $tukar, 'nilaiPoin' => $nilaiPoin, 'after' => $after, 'pajak' => $tax, 'total' => $total, 'poinDapat' => $dapat,
        'diskon' => $dpT + $dmT + $dn + $gdisc + $nilaiPoin, 'diskonPromo' => $dpT + $dn, 'hpp' => r2($hpp), 'used' => $used,
    ];
}

/** Ringkasan harga untuk tampilan keranjang. */
function price_out(array $c): array
{
    return [
        'lines' => array_map(fn($l) => ['id' => $l['id'], 'g' => $l['g'], 'dp' => $l['dp'], 'promo' => $l['promo'], 'dm' => $l['dm'], 'net' => $l['net']], $c['lines']),
        'gross' => $c['gross'], 'sub' => $c['sub'], 'nota' => $c['nota'], 'gd' => $c['gd'], 'poinTukar' => $c['poinTukar'], 'nilaiPoin' => $c['nilaiPoin'],
        'pajak' => $c['pajak'], 'total' => $c['total'], 'poinDapat' => $c['poinDapat'], 'diskon' => $c['diskon'],
    ];
}

/* ---------------------------- Member: bantu ---------------------------- */
function member_out(array $m): array
{
    return ['id' => $m['id'], 'kode' => $m['kode'], 'nama' => $m['nama'], 'hp' => $m['hp'], 'email' => $m['email'], 'tglLahir' => $m['tgl_lahir'] ?? '', 'jk' => $m['jk'],
        'alamat' => $m['alamat'], 'catatan' => $m['catatan'], 'poin' => (int)$m['poin'], 'total' => jn($m['total_belanja']), 'trx' => (int)$m['jml_trx'], 'terakhir' => $m['terakhir'] ?? '',
        'aktif' => (bool)$m['aktif'], 'dibuat' => $m['dibuat']];
}
function member_hp(string $hp): string
{
    $x = preg_replace('/\D/', '', $hp);
    if (strpos($x, '62') === 0) $x = '0' . substr($x, 2);
    return $x;
}
/** Simpan member baru / ubah. */
function member_save(array $d, array $u): array
{
    $nama = str($d['nama'] ?? '', 120); $hp = member_hp((string)($d['hp'] ?? ''));
    if ($nama === '') throw new AppErr('Nama member wajib diisi');
    if (strlen($hp) < 8 || strlen($hp) > 15) throw new AppErr('Nomor HP tidak valid (8–15 digit)');
    $id = (string)($d['id'] ?? '');
    $dup = row('SELECT id, nama FROM member WHERE hp = ? AND id <> ?', [$hp, $id]);
    if ($dup) throw new AppErr('Nomor HP sudah terdaftar atas nama ' . $dup['nama']);
    $tl = (string)($d['tglLahir'] ?? ''); $tl = is_date($tl) ? $tl : null;
    $jk = in_array($d['jk'] ?? '', ['L', 'P'], true) ? $d['jk'] : '';
    $vals = [$nama, $hp, str($d['email'] ?? '', 120), $tl, $jk, str($d['alamat'] ?? ''), str($d['catatan'] ?? '')];
    if ($id) {
        $old = row('SELECT * FROM member WHERE id = ?', [$id]);
        if (!$old) throw new AppErr('Member tidak ditemukan');
        $aktif = array_key_exists('aktif', $d) ? (!empty($d['aktif']) ? 1 : 0) : (int)$old['aktif'];
        q('UPDATE member SET nama=?, hp=?, email=?, tgl_lahir=?, jk=?, alamat=?, catatan=?, aktif=? WHERE id=?', array_merge($vals, [$aktif, $id]));
        log_act($u, 'Ubah', 'Member', $nama);
    } else {
        $id = uid('M');
        $kode = 'M' . str_pad((string)counter_next('MEMBER'), 5, '0', STR_PAD_LEFT);
        q('INSERT INTO member (nama, hp, email, tgl_lahir, jk, alamat, catatan, id, kode, poin, total_belanja, jml_trx, aktif, dibuat, dibuat_oleh) VALUES (?,?,?,?,?,?,?,?,?,0,0,0,1,?,?)',
            array_merge($vals, [$id, $kode, now_s(), $u['username']]));
        log_act($u, 'Tambah', 'Member', $kode . ' ' . $nama);
    }
    return member_out(row('SELECT * FROM member WHERE id = ?', [$id]));
}
/** Tambah/kurangi poin dan tulis riwayatnya. */
function member_poin(string $mid, int $delta, string $jenis, string $ref, string $ket, array $u): int
{
    if ($delta === 0) return (int)val('SELECT poin FROM member WHERE id = ?', [$mid]);
    q('UPDATE member SET poin = poin + ? WHERE id = ?', [$delta, $mid]);
    $saldo = (int)val('SELECT poin FROM member WHERE id = ?', [$mid]);
    q('INSERT INTO member_poin (member_id, tanggal, jenis, ref, poin, saldo, ket, user) VALUES (?,?,?,?,?,?,?,?)', [$mid, now_s(), $jenis, $ref, $delta, $saldo, mb_substr($ket, 0, 255), $u['username'] ?? '-']);
    return $saldo;
}
