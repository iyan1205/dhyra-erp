<?php
/**
 * Dhyra — satu pintu API (JSON POST).
 *   body: { "m": "kasir" | "sistem" | "akun", "a": "<aksi>", "t": "<token>", "d": { ...data } }
 */
declare(strict_types=1);
require __DIR__ . '/app/core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors', '0');
@set_time_limit(120);

$GLOBALS['__trash'] = [];
$req = json_decode((string)file_get_contents('php://input'), true);
$m = is_array($req) ? (string)($req['m'] ?? '') : '';
$a = is_array($req) ? (string)($req['a'] ?? '') : '';
$t = is_array($req) ? ($req['t'] ?? null) : null;
$d = is_array($req) && is_array($req['d'] ?? null) ? $req['d'] : [];

function out(array $x): void { echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$m) {
    http_response_code(400);
    out(['ok' => false, 'err' => 'Permintaan tidak valid', 'error' => 'Permintaan tidak valid']);
}

try {
    cfg();
    $saas = is_saas();
    if ($saas) { require __DIR__ . '/app/saas.php'; saas_boot($m); }
    if (!$saas || saas_ctx()) migrate();
    if ($saas) register_shutdown_function(function () {
        // tugas harian SaaS (pengingat tagihan) bila cron belum dipasang
        try { if (date('H') >= '07' && sset('harianTerakhir') !== today_s()) { if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request(); saas_harian(); } } catch (Throwable $e) { error_log('[Dhyra SaaS harian] ' . $e->getMessage()); }
    });
    // Laporan WhatsApp harian: bila cron belum dipasang, dikirim setelah jawaban permintaan pertama lewat jam kirim.
    if (!$saas || saas_ctx()) register_shutdown_function(function () {
        try {
            $s = settings_all();
            if (($s['WaAktif'] ?? '0') !== '1' || ($s['WaLast'] ?? '') === today_s() || date('H:i') < ($s['WaJam'] ?? '21:00')) return;
            if (!empty($s['CronLast']) && time() - strtotime((string)$s['CronLast']) < 3600) return;   // cron aktif: biar cron yang mengirim
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); elseif (function_exists('litespeed_finish_request')) litespeed_finish_request();
            require_once __DIR__ . '/app/notif.php';
            wa_due();
        } catch (Throwable $e) { error_log('[Dhyra WA] ' . $e->getMessage()); }
    });
    if ($m === 'kasir') {
        require __DIR__ . '/app/kasir.php';
        $mod = (string)($req['mod'] ?? 'kasir');
        if (!in_array($mod, ['kasir', 'inventaris', 'pembelian', 'member', 'promo', 'laporan', 'aset', 'sdm', 'kpi', 'online', 'saya'], true)) $mod = 'kasir';
        $res = kasir_dispatch($a, $d, is_string($t) ? $t : null, $mod);
    } elseif ($m === 'sistem') {
        require __DIR__ . '/app/sistem.php';
        $res = sistem_dispatch($a, $d, is_string($t) ? $t : null);
    } elseif ($m === 'toko') {
        require __DIR__ . '/app/kasir.php';
        paket_cek_modul('online');
        $res = toko_public($a, $d);
    } elseif ($m === 'saas') {
        if (!$saas) throw new AppErr('Mode SaaS tidak aktif di server ini.');
        $res = saas_dispatch($a, $d, is_string($t) ? $t : null);
    } elseif ($m === 'akun') {
        require __DIR__ . '/app/akun.php';
        $res = akun_dispatch($a, $d, is_string($t) ? $t : null);
    } else throw new AppErr('Modul tidak dikenal');
    // hapus file foto lama setelah data berhasil disimpan
    if (function_exists('k_trashFoto')) foreach ($GLOBALS['__trash'] as $f) k_trashFoto($f);
    out($res);
} catch (Throwable $e) {
    $msg = $e instanceof AppErr ? $e->getMessage() : 'Terjadi kesalahan server.';
    $tag = $e instanceof AppErr ? $e->tag : '';
    if (!($e instanceof AppErr)) {
        error_log('[Dhyra] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        if ($e instanceof PDOException) $msg = 'Kesalahan database: ' . (cfg_debug() ? $e->getMessage() : 'periksa koneksi / tabel (lihat error log hosting).');
        elseif (cfg_debug()) $msg .= ' ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
    }
    if ($m === 'kasir') {
        // format KasirPro: {ok:false, err}
        out(['ok' => false, 'err' => $msg === 'SESSION' || $tag === 'AUTH' ? 'SESSION' : $msg, 'code' => $tag]);
    }
    out(['error' => $msg, 'code' => $tag]);
}

function cfg_debug(): bool { try { return !empty(cfg()['debug']); } catch (Throwable $e) { return false; } }
