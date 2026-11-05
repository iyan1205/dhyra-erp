<?php
/**
 * ============================================================
 *  LAPORAN HARIAN OTOMATIS KE WHATSAPP PEMILIK
 *  Gateway: Fonnte (api.fonnte.com) atau Wablas (domain server Wablas Anda)
 *  Jadwal : cron.php (disarankan, tiap 15 menit) — atau otomatis menumpang
 *           permintaan pertama setelah jam kirim bila cron belum dipasang.
 * ============================================================
 */
declare(strict_types=1);
require_once __DIR__ . '/kasir.php';

const WA_ISI = ['penjualan' => 'Penjualan & laba', 'produk' => 'Produk terlaris', 'stok' => 'Stok habis / menipis', 'absen' => 'Kehadiran karyawan',
    'online' => 'Pesanan online', 'hutang' => 'Hutang pemasok jatuh tempo', 'kas' => 'Saldo kas & bank'];

function wa_cfg(): array
{
    $s = settings_all();
    $isi = json_decode((string)($s['WaIsi'] ?? ''), true);
    if (!is_array($isi)) $isi = array_keys(WA_ISI);
    if (empty($s['CronKey'])) { $k = bin2hex(random_bytes(12)); setting_put(['CronKey' => $k]); $s['CronKey'] = $k; }
    return ['aktif' => ($s['WaAktif'] ?? '0') === '1', 'provider' => ($s['WaProvider'] ?? 'fonnte') === 'wablas' ? 'wablas' : 'fonnte',
        'token' => (string)($s['WaToken'] ?? ''), 'secret' => (string)($s['WaSecret'] ?? ''), 'domain' => (string)($s['WaDomain'] ?? ''),
        'tujuan' => (string)($s['WaTujuan'] ?? ''), 'jam' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)($s['WaJam'] ?? '')) ? $s['WaJam'] : '21:00',
        'isi' => array_values(array_intersect(array_keys(WA_ISI), $isi)), 'last' => (string)($s['WaLast'] ?? ''), 'status' => (string)($s['WaStatus'] ?? ''),
        'cronKey' => (string)$s['CronKey'], 'cronLast' => (string)($s['CronLast'] ?? ''), 'appUrl' => (string)($s['AppUrl'] ?? '')];
}
function wa_nomor(string $list): array
{
    $o = [];
    foreach (preg_split('/[,;\n\/]+/', $list) as $n) {
        $n = preg_replace('/\D/', '', $n);
        if ($n === '') continue;
        if (strpos($n, '0') === 0) $n = '62' . substr($n, 1);
        if (strlen($n) >= 10 && strlen($n) <= 15) $o[$n] = 1;
    }
    return array_keys($o);
}
/** Kirim pesan; mengembalikan [ok(bool), keterangan]. */
function wa_send(array $cfg, array $to, string $msg): array
{
    if ($cfg['token'] === '') return [false, 'Token gateway belum diisi'];
    if (!$to) return [false, 'Nomor tujuan belum diisi'];
    $s = settings_all();
    if ($cfg['provider'] === 'wablas') {
        $dom = rtrim($cfg['domain'], '/'); if ($dom === '') return [false, 'Isi domain server Wablas (mis. https://jkt.wablas.com)'];
        if (!preg_match('#^https?://#', $dom)) $dom = 'https://' . $dom;
        $url = $dom . '/api/send-message'; $auth = $cfg['secret'] !== '' ? $cfg['token'] . '.' . $cfg['secret'] : $cfg['token'];
        $ok = 0; $last = '';
        foreach ($to as $n) { [$code, $body] = wa_http($url, ['phone' => $n, 'message' => $msg], ['Authorization: ' . $auth]); $j = json_decode((string)$body, true); if ($code < 300 && !empty($j['status'])) $ok++; else $last = $j['message'] ?? ('HTTP ' . $code); }
        return [$ok === count($to), $ok . '/' . count($to) . ' terkirim' . ($last ? ' · ' . $last : '')];
    }
    $url = (string)($s['WaFonnteUrl'] ?? '') ?: 'https://api.fonnte.com/send';
    [$code, $body] = wa_http($url, ['target' => implode(',', $to), 'message' => $msg, 'countryCode' => '62'], ['Authorization: ' . $cfg['token']]);
    $j = json_decode((string)$body, true);
    if ($code < 300 && !empty($j['status'])) return [true, 'Terkirim ke ' . count($to) . ' nomor'];
    return [false, 'Fonnte: ' . ($j['reason'] ?? $j['detail'] ?? ('HTTP ' . $code . ($code ? '' : ' (tidak dapat terhubung)')))];
}
function wa_http(string $url, array $form, array $hdr): array
{
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($form), CURLOPT_HTTPHEADER => $hdr, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10]);
        $b = curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
        return [$code, $b === false ? '' : $b];
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", array_merge($hdr, ['Content-Type: application/x-www-form-urlencoded'])), 'content' => http_build_query($form), 'timeout' => 25, 'ignore_errors' => true]]);
    $b = @file_get_contents($url, false, $ctx); $code = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $code = (int)$m[1];
    return [$code, $b === false ? '' : $b];
}
function wa_rp($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
/** Teks laporan harian (format WhatsApp: *tebal*, _miring_). */
function wa_laporan(string $tgl, ?array $isi = null): string
{
    $isi = $isi ?? wa_cfg()['isi']; $s = settings_all();
    $HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']; $BLN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($tgl); $a = $tgl . ' 00:00:00'; $b = $tgl . ' 23:59:59'; $y = add_days($tgl, -1);
    $L = ['📊 *Laporan Harian ' . ($s['namaToko'] ?? 'Toko') . '*', $HARI[(int)date('w', $ts)] . ', ' . (int)date('j', $ts) . ' ' . $BLN[(int)date('n', $ts)] . ' ' . date('Y', $ts), ''];
    if (in_array('penjualan', $isi, true)) {
        $r = row("SELECT COUNT(*) n, COALESCE(SUM(total),0) o, COALESCE(SUM(laba),0) l, COALESCE(SUM(diskon),0) d FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?", [$a, $b]);
        $ry = (float)val("SELECT COALESCE(SUM(total),0) FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ?", [$y . ' 00:00:00', $y . ' 23:59:59']);
        $bt = (int)val("SELECT COUNT(*) FROM transaksi WHERE status = 'Batal' AND tanggal BETWEEN ? AND ?", [$a, $b]);
        $dl = $ry > 0 ? round(((float)$r['o'] - $ry) / $ry * 100, 1) : null;
        $L[] = '💰 *Penjualan*';
        $L[] = 'Omzet: *' . wa_rp($r['o']) . '*' . ($dl !== null ? ' (' . ($dl >= 0 ? '▲' : '▼') . ' ' . str_replace('.', ',', (string)abs($dl)) . '% vs kemarin)' : '');
        $L[] = 'Transaksi: ' . (int)$r['n'] . ($r['n'] ? ' · rata-rata ' . wa_rp((float)$r['o'] / (int)$r['n']) : '');
        $L[] = 'Laba kotor: ' . wa_rp($r['l']) . ((float)$r['o'] > 0 ? ' (margin ' . str_replace('.', ',', (string)round((float)$r['l'] / (float)$r['o'] * 100, 1)) . '%)' : '');
        if ((float)$r['d'] > 0) $L[] = 'Diskon & promo: ' . wa_rp($r['d']);
        $met = rows("SELECT metode, SUM(total) o FROM transaksi WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ? GROUP BY metode ORDER BY o DESC", [$a, $b]);
        if ($met) $L[] = implode(' · ', array_map(fn($m) => $m['metode'] . ' ' . wa_rp($m['o']), $met));
        if ($bt) $L[] = '⚠️ Transaksi dibatalkan: ' . $bt;
        $off = (int)val("SELECT COUNT(*) FROM transaksi WHERE sumber = 'offline' AND tanggal BETWEEN ? AND ?", [$a, $b]);
        if ($off) $L[] = '📴 Transaksi saat offline: ' . $off;
        $L[] = '';
    }
    if (in_array('produk', $isi, true)) {
        $top = rows("SELECT nama, SUM(qty) q, SUM(subtotal) o FROM transaksi_detail WHERE status = 'Selesai' AND tanggal BETWEEN ? AND ? GROUP BY pid, nama ORDER BY o DESC LIMIT 5", [$a, $b]);
        if ($top) { $L[] = '🏆 *Terlaris*'; foreach ($top as $i => $t) $L[] = ($i + 1) . '. ' . $t['nama'] . ' — ' . jn($t['q']) . ' (' . wa_rp($t['o']) . ')'; $L[] = ''; }
    }
    if (in_array('stok', $isi, true)) {
        $minL = []; foreach (rows('SELECT pid, SUM(min) m FROM stok_lokasi GROUP BY pid') as $r) $minL[$r['pid']] = (float)$r['m'];
        $habis = []; $tipis = [];
        foreach (rows('SELECT id, nama, stok, stok_min, satuan FROM produk ORDER BY nama') as $p) { $mn = max((float)$p['stok_min'], $minL[$p['id']] ?? 0); if ((float)$p['stok'] <= 0) $habis[] = $p['nama']; elseif ($mn > 0 && (float)$p['stok'] <= $mn) $tipis[] = $p['nama'] . ' (' . jn($p['stok']) . ')'; }
        $L[] = '📦 *Stok*';
        if (!$habis && !$tipis) $L[] = 'Semua stok aman ✅';
        if ($habis) $L[] = 'Habis (' . count($habis) . '): ' . implode(', ', array_slice($habis, 0, 6)) . (count($habis) > 6 ? ', …' : '');
        if ($tipis) $L[] = 'Menipis (' . count($tipis) . '): ' . implode(', ', array_slice($tipis, 0, 6)) . (count($tipis) > 6 ? ', …' : '');
        $L[] = '';
    }
    if (in_array('absen', $isi, true)) {
        $K = rows("SELECT * FROM karyawan WHERE status = 'Aktif' ORDER BY nama");
        if ($K) {
            $D = sd_days($K, $tgl, $tgl); $g = [];
            foreach ($K as $k) { $x = $D[$k['id']][$tgl]; $st = $x['st'] === 'Belum absen' && $tgl < today_s() ? 'Alpa' : $x['st']; $g[$st][] = $k['nama'] . ($st === 'Telat' ? ' (' . (int)$x['a']['telat'] . ' mnt)' : ''); }
            $hadir = count($g['Hadir'] ?? []) + count($g['Telat'] ?? []); $wajib = count($K) - count($g['Libur'] ?? []) - count($g['-'] ?? []);
            $L[] = '👥 *Kehadiran*'; $L[] = 'Hadir ' . $hadir . ' dari ' . max(0, $wajib) . ' karyawan terjadwal';
            foreach (['Telat' => 'Terlambat', 'Belum absen' => 'Belum absen', 'Alpa' => 'Tidak hadir', 'Izin' => 'Izin', 'Sakit' => 'Sakit', 'Cuti' => 'Cuti'] as $k => $lbl) if (!empty($g[$k])) $L[] = $lbl . ': ' . implode(', ', $g[$k]);
            $pj = (int)val("SELECT COUNT(*) FROM pengajuan WHERE status = 'Menunggu'"); if ($pj) $L[] = '📝 ' . $pj . ' pengajuan izin/cuti/lembur menunggu persetujuan';
            $L[] = '';
        }
    }
    if (in_array('online', $isi, true) && (($s['OnlineAktif'] ?? '0') === '1' || (int)val('SELECT COUNT(*) FROM pesanan_online'))) {
        $nb = (int)val("SELECT COUNT(*) FROM pesanan_online WHERE tanggal BETWEEN ? AND ?", [$a, $b]);
        $pr = (int)val("SELECT COUNT(*) FROM pesanan_online WHERE status IN ('Baru','Diproses','Dikirim')");
        $L[] = '🛒 *Toko online*'; $L[] = 'Pesanan masuk hari ini: ' . $nb . ' · belum selesai: ' . $pr; $L[] = '';
    }
    if (in_array('hutang', $isi, true)) {
        $h7 = add_days($tgl, 7); $tel = 0.0; $min = 0.0; $n = 0;
        foreach (rows("SELECT f.total, f.jatuh_tempo, COALESCE((SELECT SUM(jumlah) FROM pembayaran b WHERE b.faktur_id = f.id),0) paid FROM faktur f WHERE f.tipe = 'Pembelian'") as $r) {
            $sisa = (float)$r['total'] - (float)$r['paid']; if ($sisa <= 0.5) continue;
            if ($r['jatuh_tempo'] < $tgl) { $tel += $sisa; $n++; } elseif ($r['jatuh_tempo'] <= $h7) $min += $sisa;
        }
        if ($tel > 0 || $min > 0) { $L[] = '🧾 *Hutang pemasok*'; if ($tel > 0) $L[] = 'Lewat jatuh tempo: ' . wa_rp($tel) . ' (' . $n . ' tagihan)'; if ($min > 0) $L[] = 'Jatuh tempo 7 hari ke depan: ' . wa_rp($min); $L[] = ''; }
    }
    if (in_array('kas', $isi, true)) {
        $K = rows("SELECT a.nama, a.saldo_awal + COALESCE((SELECT SUM(j.debit - j.kredit) FROM jurnal j WHERE j.kode_akun = a.kode AND j.tanggal <= ?),0) s FROM akun a WHERE a.subkategori = 'Kas & Bank' AND a.status = 'Aktif' ORDER BY a.kode", [$tgl]);
        if ($K) { $L[] = '🏦 *Saldo kas & bank*'; foreach ($K as $k) if (abs((float)$k['s']) >= 1) $L[] = $k['nama'] . ': ' . wa_rp($k['s']); $L[] = 'Total: *' . wa_rp(array_sum(array_column($K, 's'))) . '*'; $L[] = ''; }
    }
    $url = (string)($s['AppUrl'] ?? '');
    if ($url !== '') $L[] = 'Detail: ' . rtrim($url, '/') . '/laporan.html#ringkasan';
    $L[] = '_Dikirim otomatis oleh Dhyra_';
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $L)));
}
/** Kirim laporan bila sudah waktunya dan belum terkirim hari ini. Aman dipanggil berkali-kali. */
function wa_due(bool $force = false): ?array
{
    $c = wa_cfg();
    if (!$c['aktif'] && !$force) return null;
    $td = today_s();
    if (!$force && ($c['last'] === $td || date('H:i') < $c['jam'])) return null;
    $s = settings_all();
    if (!$force && ($s['WaTryDate'] ?? '') === $td && (int)($s['WaTry'] ?? 0) >= 3) return null;      // gagal 3x hari ini: berhenti
    if (!$force && ($s['WaTryAt'] ?? '') !== '' && time() - (int)$s['WaTryAt'] < 600) return null;   // jeda 10 menit antar percobaan
    $got = (int)val('SELECT GET_LOCK(?, 0)', [lock_name() . '_wa']);
    if (!$got) return null;
    try {
        if (!$force && (settings_all(true)['WaLast'] ?? '') === $td) return null;
        [$ok, $info] = wa_send($c, wa_nomor($c['tujuan']), wa_laporan($td, $c['isi']));
        $put = ['WaStatus' => date('d/m H:i') . ' · ' . ($ok ? '✅ ' : '❌ ') . $info];
        if ($ok) { $put['WaLast'] = $td; $put['WaTryAt'] = ''; $put['WaTry'] = '0'; } else { $put['WaTryAt'] = (string)time(); $put['WaTry'] = (string)((($s['WaTryDate'] ?? '') === $td ? (int)($s['WaTry'] ?? 0) : 0) + 1); $put['WaTryDate'] = $td; }
        setting_put($put);
        return ['ok' => $ok, 'info' => $info];
    } finally { q('SELECT RELEASE_LOCK(?)', [lock_name() . '_wa']); }
}
/* ---------------- aksi pengaturan (dipanggil dari sistem.php, khusus admin) ---------------- */
function wa_out(): array
{
    $c = wa_cfg(); $base = rtrim($c['appUrl'] ?: s_baseUrl(), '/');
    return ['cfg' => array_merge($c, ['token' => $c['token'] !== '' ? str_repeat('•', 6) . substr($c['token'], -4) : '', 'adaToken' => $c['token'] !== '', 'secret' => $c['secret'] !== '' ? '••••' : '']),
        'isiOpsi' => WA_ISI, 'preview' => wa_laporan(today_s(), $c['isi']), 'cronUrl' => $base . '/cron.php?key=' . $c['cronKey'], 'cronCli' => 'php ' . dirname(__DIR__) . '/cron.php',
        'curl' => function_exists('curl_init'), 'jamServer' => date('H:i')];
}
function s_baseUrl(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}
function s_waGet(): array { if (empty(settings_all()['AppUrl'])) setting_put(['AppUrl' => s_baseUrl()]); return wa_out(); }
function s_waSave(array $d, array $u): array
{
    $put = ['WaAktif' => !empty($d['aktif']) ? '1' : '0', 'WaProvider' => ($d['provider'] ?? '') === 'wablas' ? 'wablas' : 'fonnte',
        'WaTujuan' => implode(', ', wa_nomor((string)($d['tujuan'] ?? ''))), 'WaDomain' => str($d['domain'] ?? '', 120),
        'WaIsi' => json_encode(array_values(array_intersect(array_keys(WA_ISI), (array)($d['isi'] ?? [])))), 'AppUrl' => s_baseUrl(), 'WaTry' => '0', 'WaTryAt' => ''];
    $j = (string)($d['jam'] ?? '21:00'); if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $j)) throw new AppErr('Jam kirim tidak valid');
    $put['WaJam'] = $j;
    if (isset($d['token']) && $d['token'] !== '' && strpos((string)$d['token'], '•') === false) $put['WaToken'] = str($d['token'], 200);
    if (isset($d['secret']) && strpos((string)$d['secret'], '•') === false) $put['WaSecret'] = str($d['secret'], 200);
    if ($put['WaAktif'] === '1' && !wa_nomor($put['WaTujuan'])) throw new AppErr('Isi minimal satu nomor WhatsApp tujuan');
    if ($put['WaAktif'] === '1' && ($put['WaToken'] ?? (settings_all()['WaToken'] ?? '')) === '') throw new AppErr('Isi token gateway ' . ucfirst($put['WaProvider']));
    if (!empty($d['ulang'])) $put['WaLast'] = '';
    setting_put($put);
    log_act($u, 'Ubah', 'Laporan WhatsApp', ($put['WaAktif'] === '1' ? 'aktif ' : 'nonaktif ') . $j);
    return wa_out();
}
function s_waTest(array $d, array $u): array
{
    $c = wa_cfg();
    $msg = !empty($d['laporan']) ? wa_laporan(today_s(), $c['isi']) : "✅ Tes Dhyra: WhatsApp gateway " . ucfirst($c['provider']) . " berhasil terhubung.\nLaporan harian akan dikirim setiap pukul " . $c['jam'] . ".";
    [$ok, $info] = wa_send($c, wa_nomor($c['tujuan']), $msg);
    setting_put(['WaStatus' => date('d/m H:i') . ' · ' . ($ok ? '✅ ' : '❌ ') . 'Tes: ' . $info]);
    log_act($u, 'Tes WhatsApp', 'Laporan WhatsApp', $info);
    if (!$ok) throw new AppErr('Gagal mengirim: ' . $info);
    return ['info' => $info] + wa_out();
}
