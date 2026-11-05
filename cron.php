<?php
/**
 * Dhyra — tugas terjadwal (cron). Pasang di panel hosting / VPS agar berjalan tiap 15 menit:
 *   Perintah : php /home/USER/public_html/cron.php
 *   atau URL : https://domain-anda/cron.php?key=KUNCI   (lihat Pengaturan → Laporan WhatsApp)
 * Mode satu toko : laporan harian otomatis ke WhatsApp pemilik.
 * Mode SaaS      : pengingat tagihan & status langganan semua toko, lalu laporan WhatsApp tiap toko.
 */
declare(strict_types=1);
require __DIR__ . '/app/core.php';
$cli = PHP_SAPI === 'cli';
if (!$cli) { header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store'); }
@set_time_limit(0);
try {
    cfg();
    if (is_saas()) {
        require __DIR__ . '/app/saas.php';
        saas_migrate();
        $arg = []; foreach (array_slice($argv ?? [], 1) as $x) if (preg_match('/^--(\w+)=(.*)$/', $x, $mm)) $arg[$mm[1]] = $mm[2];
        if (isset($arg['toko'])) {                      // satu toko (dipanggil dari loop di bawah)
            if (!$cli) exit("Hanya untuk CLI.\n");
            $t = mrow('SELECT * FROM s_toko WHERE slug = ? AND beku = 0', [$arg['toko']]);
            if (!$t) exit("Toko tidak ditemukan.\n");
            saas_pakai($t);
            migrate();
            require __DIR__ . '/app/notif.php';
            setting_put(['CronLast' => now_s()]);
            $r = wa_due();
            echo $t['slug'] . ' · ' . ($r ? ($r['ok'] ? 'laporan WA terkirim' : 'gagal: ' . $r['info']) : '-') . "\n";
            exit;
        }
        if (!$cli && !hash_equals(sset('cronKey'), (string)($_GET['key'] ?? ''))) { http_response_code(403); exit("Kunci cron salah.\n"); }
        $h = saas_harian();
        echo date('Y-m-d H:i') . ' · pengingat tagihan: ' . count($h) . " pesan\n";
        foreach ($h as $x) echo '  - ' . $x['toko'] . ' · ' . $x['jenis'] . ' · ' . ($x['ok'] ? 'ok' : $x['info']) . "\n";
        // laporan WhatsApp harian tiap toko (hanya toko yang mengaktifkannya)
        $n = 0;
        foreach (mrows('SELECT * FROM s_toko WHERE beku = 0 ORDER BY slug') as $t) {
            if (saas_status($t)['kode'] === 'baca') continue;
            try {
                $aktif = saas_di_toko($t, fn() => (string)(val("SELECT v FROM pengaturan WHERE k = 'WaAktif'") ?? '0'));
                if ($aktif !== '1') continue;
                $n++;
                if ($cli && function_exists('exec') && defined('PHP_BINARY') && PHP_BINARY !== '') {
                    $out = []; exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --toko=' . escapeshellarg($t['slug']) . ' 2>&1', $out);
                    echo '  ' . implode(' ', $out) . "\n";
                }
            } catch (Throwable $e) { echo '  ' . $t['slug'] . ' · ' . $e->getMessage() . "\n"; }
        }
        if (!$cli && $n) echo "Catatan: laporan WhatsApp per toko hanya dikirim bila cron dijalankan lewat perintah (CLI).\n";
        exit;
    }
    migrate();
    require __DIR__ . '/app/notif.php';
    $c = wa_cfg();
    if (!$cli && !hash_equals($c['cronKey'], (string)($_GET['key'] ?? ''))) { http_response_code(403); exit("Kunci cron salah.\n"); }
    setting_put(['CronLast' => now_s()]);
    $r = wa_due();
    echo date('Y-m-d H:i') . ' · ' . ($r ? ($r['ok'] ? 'Laporan WhatsApp terkirim: ' : 'Gagal mengirim: ') . $r['info'] : ($c['aktif'] ? 'Belum waktunya atau sudah terkirim hari ini.' : 'Laporan WhatsApp nonaktif.')) . "\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . ($e instanceof AppErr ? $e->getMessage() : 'kesalahan server') . "\n";
    error_log('[Dhyra cron] ' . $e->getMessage());
}
