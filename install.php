<?php
/**
 * Dhyra — wizard instalasi.
 * 1. Buat database MySQL kosong di panel hosting (hPanel Hostinger / cPanel Rumahweb).
 * 2. Buka https://domain-anda/install.php, isi data database & akun admin, klik Instal.
 * 3. Setelah berhasil, HAPUS file install.php ini dari hosting.
 */
declare(strict_types=1);
require __DIR__ . '/app/core.php';
ini_set('display_errors', '0');

$installed = is_file(__DIR__ . '/app/config.php');
$msg = ''; $ok = false;
$v = fn($k, $d = '') => htmlspecialchars((string)($_POST[$k] ?? $d), ENT_QUOTES, 'UTF-8');

$checks = [
    ['PHP 8.0 atau lebih baru', version_compare(PHP_VERSION, '8.0.0', '>='), 'Versi Anda ' . PHP_VERSION . '. Ubah versi PHP di panel hosting.'],
    ['Ekstensi PDO MySQL', extension_loaded('pdo_mysql'), 'Aktifkan pdo_mysql di pengaturan PHP hosting.'],
    ['Ekstensi mbstring', extension_loaded('mbstring'), 'Aktifkan mbstring di pengaturan PHP hosting.'],
    ['Folder app/ dapat ditulis', is_writable(__DIR__ . '/app'), 'Ubah izin folder app menjadi 755.'],
    ['Folder uploads/ dapat ditulis', is_dir(__DIR__ . '/uploads') ? is_writable(__DIR__ . '/uploads') : is_writable(__DIR__), 'Ubah izin folder uploads menjadi 755.'],
];
$allOk = !in_array(false, array_column($checks, 1), true);

$saasMode = ($_POST['mode'] ?? '') === 'saas';
$saasInfo = null;
if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST' && $allOk && $saasMode) {
    // ---------------- MODE SaaS: database pusat + panel pengelola ----------------
    $dom = strtolower(trim((string)($_POST['s_domain'] ?? '')));
    $dom = preg_replace('#^https?://#', '', rtrim($dom, '/'));
    $c = [
        'db_host' => trim($_POST['db_host'] ?? 'localhost') ?: 'localhost', 'db_port' => (int)($_POST['db_port'] ?? 3306) ?: 3306,
        'db_name' => preg_replace('/[^A-Za-z0-9_]/', '', (string)($_POST['s_master'] ?? 'dhyra_pusat')), 'db_user' => trim($_POST['db_user'] ?? ''), 'db_pass' => (string)($_POST['db_pass'] ?? ''),
        'timezone' => in_array($_POST['timezone'] ?? '', ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'], true) ? $_POST['timezone'] : 'Asia/Jakarta', 'debug' => false,
        'saas' => ['domain' => $dom, 'master' => '', 'prefix' => preg_replace('/[^a-z0-9_]/', '', strtolower((string)($_POST['s_prefix'] ?? 'dhyra_t_'))) ?: 'dhyra_t_',
            'scheme' => ($_POST['s_scheme'] ?? 'https') === 'http' ? 'http' : 'https'],
    ];
    $c['saas']['master'] = $c['db_name'];
    if (preg_match('/:(\d+)$/', (string)($_SERVER['HTTP_HOST'] ?? ''), $pm) && !in_array((int)$pm[1], [80, 443], true)) $c['saas']['port'] = (int)$pm[1];
    $au = strtolower(trim($_POST['s_user'] ?? '')); $ap = (string)($_POST['s_pass'] ?? '');
    try {
        if (!preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $dom)) throw new Exception('Isi domain utama tanpa http://, misalnya dhyra.id');
        if ($c['db_name'] === '' || $c['db_user'] === '') throw new Exception('Nama database pusat dan user database wajib diisi.');
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $au)) throw new Exception('Username pengelola 3-30 karakter: huruf kecil, angka, titik, strip.');
        if (strlen($ap) < 8) throw new Exception('Password pengelola minimal 8 karakter.');
        if ($ap !== ($_POST['s_pass2'] ?? '')) throw new Exception('Ulangi password pengelola tidak sama.');
        date_default_timezone_set($c['timezone']);
        $raw = new PDO('mysql:host=' . $c['db_host'] . ';port=' . $c['db_port'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $raw->exec('CREATE DATABASE IF NOT EXISTS `' . $c['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        // uji hak membuat database toko
        $tes = $c['saas']['prefix'] . 'cek' . random_int(1000, 9999);
        try { $raw->exec('CREATE DATABASE `' . $tes . '`'); $raw->exec('DROP DATABASE `' . $tes . '`'); }
        catch (Throwable $e) { throw new Exception('User database belum punya izin membuat database baru (CREATE). Di VPS, beri izin: GRANT ALL ON *.* TO user@localhost. Detail: ' . $e->getMessage()); }
        $php = "<?php\n// Konfigurasi Dhyra SaaS (dibuat oleh install.php " . date('Y-m-d H:i') . ")\nreturn " . var_export($c, true) . ";\n";
        if (@file_put_contents(__DIR__ . '/app/config.php', $php) === false) throw new Exception('Gagal menulis app/config.php. Periksa izin folder app.');
        @chmod(__DIR__ . '/app/config.php', 0640);
        require_once __DIR__ . '/app/saas.php';
        saas_migrate();
        if (!mval('SELECT id FROM s_admin WHERE username = ?', [$au]))
            mq('INSERT INTO s_admin (id, username, nama, pass_hash, aktif, dibuat) VALUES (?,?,?,?,1,?)', [uid('A'), $au, trim($_POST['s_nama'] ?? '') ?: 'Pengelola', password_hash($ap, PASSWORD_DEFAULT), now_s()]);
        $brand = trim((string)($_POST['s_brand'] ?? '')) ?: 'Dhyra';
        sset_put(['brand' => mb_substr($brand, 0, 40), 'otpAktif' => '0']);
        foreach (['uploads/t', 'uploads/pusat', 'app/data'] as $dd) if (!is_dir(__DIR__ . '/' . $dd)) @mkdir(__DIR__ . '/' . $dd, 0755, true);
        @file_put_contents(__DIR__ . '/app/data/.htaccess', "Require all denied\n");
        $saasInfo = ['url' => saas_url(), 'cron' => 'php ' . __DIR__ . '/cron.php'];
        $ok = true; $installed = true;
    } catch (Throwable $e) {
        @unlink(__DIR__ . '/app/config.php');
        $msg = $e instanceof PDOException ? 'Database: ' . $e->getMessage() : $e->getMessage();
    }
}
if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST' && $allOk && !$saasMode) {
    $c = [
        'db_host' => trim($_POST['db_host'] ?? 'localhost') ?: 'localhost', 'db_port' => (int)($_POST['db_port'] ?? 3306) ?: 3306,
        'db_name' => trim($_POST['db_name'] ?? ''), 'db_user' => trim($_POST['db_user'] ?? ''), 'db_pass' => (string)($_POST['db_pass'] ?? ''),
        'timezone' => in_array($_POST['timezone'] ?? '', ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'], true) ? $_POST['timezone'] : 'Asia/Jakarta', 'debug' => false,
    ];
    $au = strtolower(trim($_POST['adm_user'] ?? 'admin')); $ap = (string)($_POST['adm_pass'] ?? ''); $toko = trim($_POST['toko'] ?? '') ?: 'Toko Saya';
    try {
        if ($c['db_name'] === '' || $c['db_user'] === '') throw new Exception('Nama database dan user database wajib diisi.');
        if (!preg_match('/^[a-z0-9._-]{3,30}$/', $au)) throw new Exception('Username admin 3-30 karakter: huruf kecil, angka, titik, strip.');
        if (strlen($ap) < 6) throw new Exception('Password admin minimal 6 karakter.');
        if ($ap !== ($_POST['adm_pass2'] ?? '')) throw new Exception('Ulangi password admin tidak sama.');
        date_default_timezone_set($c['timezone']);
        $pdo = pdo_connect($c);
        $sql = (string)file_get_contents(__DIR__ . '/app/schema.sql');
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) $pdo->exec($stmt);
        // tulis konfigurasi
        $php = "<?php\n// Konfigurasi Dhyra (dibuat oleh install.php " . date('Y-m-d H:i') . ")\nreturn " . var_export($c, true) . ";\n";
        if (@file_put_contents(__DIR__ . '/app/config.php', $php) === false) throw new Exception('Gagal menulis app/config.php. Periksa izin folder app.');
        @chmod(__DIR__ . '/app/config.php', 0640);
        if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
        seed_defaults(['username' => $au, 'password' => $ap, 'nama' => 'Administrator']);
        migrate();
        setting_put(['namaToko' => $toko, 'NamaPerusahaan' => $toko, 'FakturCatatan' => 'Pembayaran mohon ditransfer ke: Bank ... a/n ' . $toko . ', No. Rek. ...']);
        if (!empty($_POST['jenis'])) {
            require_once __DIR__ . '/app/kasir.php';
            if (isset(KATEGORI_PRESET[$_POST['jenis']])) k_catPreset(['jenis' => (string)$_POST['jenis']]);
        }
        if (!empty($_POST['demo'])) {
            seed_demo_products();
            require_once __DIR__ . '/app/kasir.php';
            $adm = row('SELECT * FROM users WHERE username = ?', [$au]);
            if ($adm) with_write(function () use ($adm) { seed_demo_t3($adm); });
        }
        $ok = true; $installed = true;
    } catch (Throwable $e) {
        @unlink(__DIR__ . '/app/config.php');
        $msg = $e instanceof PDOException ? 'Tidak dapat terhubung / membuat tabel di database: ' . $e->getMessage() : $e->getMessage();
    }
}
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalasi Dhyra</title>
<link rel="icon" type="image/png" href="assets/favicon-32.png">
<style>
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f2f5f4;color:#0d2321;line-height:1.5}
.w{max-width:620px;margin:32px auto;padding:0 16px}.card{background:#fff;border:1px solid #e0e8e6;border-radius:14px;padding:22px;margin-bottom:14px;box-shadow:0 6px 20px rgba(13,35,33,.06)}
h1{font-size:22px;margin:0 0 4px}h2{font-size:15px;margin:0 0 12px}p{margin:0 0 10px}.mut{color:#5f7773;font-size:13px}
label{display:block;font-size:13px;font-weight:600;margin:10px 0 4px}input,select{width:100%;padding:10px 12px;border:1px solid #cfdcd9;border-radius:9px;font:inherit;font-size:14px}
.g2{display:grid;grid-template-columns:2fr 1fr;gap:10px}.g2b{display:grid;grid-template-columns:1fr 1fr;gap:10px}
button{margin-top:16px;width:100%;padding:12px;border:0;border-radius:10px;background:#0f766e;color:#fff;font-weight:700;font-size:15px;cursor:pointer}
.err{background:#fde7e7;color:#b91c1c;padding:10px 12px;border-radius:9px;margin-bottom:12px;font-size:14px}.ok{background:#dff5ec;color:#0b6b48;padding:12px;border-radius:9px}
.chk{display:flex;justify-content:space-between;font-size:14px;padding:5px 0;border-bottom:1px dashed #e0e8e6}.chk b{color:#0f9d6b}.chk b.x{color:#dc3b3b}
.cb{display:flex;gap:8px;align-items:center;font-weight:500}.mode{border:1px solid #cfdcd9;border-radius:10px;padding:10px 12px;align-items:flex-start;cursor:pointer}.mode input{margin-top:4px}.cb input{width:auto}a.btn{display:inline-block;margin-top:12px;padding:10px 16px;border-radius:10px;background:#0f766e;color:#fff;text-decoration:none;font-weight:700;margin-right:6px}
code{background:#eef3f2;padding:1px 5px;border-radius:5px}
</style></head><body><div class="w">
<div class="card" style="display:flex;gap:16px;align-items:center"><img src="assets/icon-512.png" alt="" width="56" height="56" style="border-radius:14px"><div><h1>Dhyra</h1><p class="mut" style="margin:0">Kasir · Inventaris · Akuntansi · SDM · Toko Online — versi <?= APP_VERSION ?></p></div></div>
<?php if ($installed): ?>
  <div class="card">
    <?php if ($ok && $saasInfo): ?><div class="ok"><b>Dhyra SaaS siap!</b> Database pusat dan akun pengelola sudah dibuat.</div>
    <p style="margin-top:12px">Langkah berikutnya:</p>
    <ol style="margin:0 0 10px 18px;padding:0;font-size:14px"><li>Masuk ke <b>panel pengelola</b>, atur harga paket, rekening/QRIS, dan token WhatsApp.</li><li>Pasang cron tiap 15 menit: <code><?= htmlspecialchars($saasInfo['cron']) ?></code></li><li>Bagikan halaman pendaftaran <code><?= htmlspecialchars($saasInfo['url']) ?></code> ke calon pelanggan.</li></ol>
    <p><b>Penting:</b> hapus file <code>install.php</code> dari server sekarang.</p>
    <a class="btn" href="pusat.html">Buka panel pengelola</a><a class="btn" href="daftar.html" style="background:#fff;color:#0f766e;border:1px solid #0f766e">Lihat halaman daftar</a>
    <?php else: ?>
    <?php if ($ok): ?><div class="ok"><b>Instalasi berhasil!</b> Database dan akun admin sudah dibuat.</div><?php else: ?><div class="ok">Aplikasi sudah terinstal.</div><?php endif; ?>
    <p style="margin-top:12px"><b>Penting:</b> hapus file <code>install.php</code> dari hosting sekarang (File Manager → hapus), agar tidak bisa dijalankan orang lain.</p>
    <a class="btn" href="index.php">Buka Dhyra</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="card"><h2>1. Pemeriksaan server</h2>
    <?php foreach ($checks as $c): ?><div class="chk"><span><?= htmlspecialchars($c[0]) ?><?= $c[1] ? '' : '<br><small class="mut">' . htmlspecialchars($c[2]) . '</small>' ?></span><b class="<?= $c[1] ? '' : 'x' ?>"><?= $c[1] ? 'OK' : 'Gagal' ?></b></div><?php endforeach; ?>
  </div>
  <form method="post" class="card" autocomplete="off" id="f">
    <h2>Jenis pemasangan</h2>
    <div class="g2b" style="margin-bottom:8px">
      <label class="cb mode"><input type="radio" name="mode" value="toko" <?= $saasMode ? '' : 'checked' ?>> <span><b>Satu toko</b><br><small class="mut">Dipasang di hosting milik toko</small></span></label>
      <label class="cb mode"><input type="radio" name="mode" value="saas" <?= $saasMode ? 'checked' : '' ?>> <span><b>SaaS (banyak toko)</b><br><small class="mut">VPS + domain sendiri, toko berlangganan</small></span></label>
    </div>
    <h2>2. Database MySQL</h2>
    <p class="mut">Buat database dulu di panel hosting (Hostinger: hPanel → Databases → MySQL Databases; Rumahweb: cPanel → MySQL® Database Wizard), lalu isi datanya di sini.</p>
    <?php if ($msg): ?><div class="err"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <div class="g2"><div><label>Host database</label><input name="db_host" value="<?= $v('db_host', 'localhost') ?>"></div><div><label>Port</label><input name="db_port" value="<?= $v('db_port', '3306') ?>"></div></div>
    <div class="m-toko"><label>Nama database</label><input name="db_name" value="<?= $v('db_name') ?>" placeholder="mis. u123456789_kasir"></div>
    <div class="g2b"><div><label>User database</label><input name="db_user" value="<?= $v('db_user') ?>" required></div><div><label>Password database</label><input name="db_pass" type="password" value="<?= $v('db_pass') ?>"></div></div>
    <label>Zona waktu</label><select name="timezone"><option value="Asia/Jakarta">WIB (Asia/Jakarta)</option><option value="Asia/Makassar" <?= ($_POST['timezone'] ?? '') === 'Asia/Makassar' ? 'selected' : '' ?>>WITA (Asia/Makassar)</option><option value="Asia/Jayapura" <?= ($_POST['timezone'] ?? '') === 'Asia/Jayapura' ? 'selected' : '' ?>>WIT (Asia/Jayapura)</option></select>
    <div class="m-saas">
    <p class="mut">Mode SaaS: setiap toko mendapat database sendiri, jadi user database harus punya izin membuat database (CREATE). Di VPS biasanya user <code>root</code> atau user khusus dengan <code>GRANT ALL</code>.</p>
    <div class="g2b"><div><label>Database pusat</label><input name="s_master" value="<?= $v('s_master', 'dhyra_pusat') ?>"></div><div><label>Awalan database toko</label><input name="s_prefix" value="<?= $v('s_prefix', 'dhyra_t_') ?>"></div></div>
    <h2 style="margin-top:22px">3. Domain & pengelola</h2>
    <label>Nama layanan</label><input name="s_brand" value="<?= $v('s_brand', 'Dhyra') ?>">
    <div class="g2"><div><label>Domain utama</label><input name="s_domain" value="<?= $v('s_domain') ?>" placeholder="dhyra.id"></div><div><label>Protokol</label><select name="s_scheme"><option value="https">https (SSL)</option><option value="http" <?= ($_POST['s_scheme'] ?? '') === 'http' ? 'selected' : '' ?>>http</option></select></div></div>
    <p class="mut" style="margin-top:6px">Arahkan DNS <code>domain</code> dan <code>*.domain</code> (wildcard) ke IP server ini. Toko akan beralamat <code>namatoko.domain</code>.</p>
    <label>Nama pengelola</label><input name="s_nama" value="<?= $v('s_nama', 'Ivan Hadian') ?>">
    <label>Username pengelola</label><input name="s_user" value="<?= $v('s_user', 'admin') ?>" autocapitalize="off">
    <div class="g2b"><div><label>Password pengelola (min. 8)</label><input name="s_pass" type="password"></div><div><label>Ulangi password</label><input name="s_pass2" type="password"></div></div>
    </div>
    <div class="m-toko">
    <h2 style="margin-top:22px">3. Toko & akun admin</h2>
    <label>Nama toko / perusahaan</label><input name="toko" value="<?= $v('toko', 'Toko Saya') ?>">
    <label>Username admin</label><input name="adm_user" value="<?= $v('adm_user', 'admin') ?>" autocapitalize="off">
    <div class="g2b"><div><label>Password admin (min. 6)</label><input name="adm_pass" type="password"></div><div><label>Ulangi password</label><input name="adm_pass2" type="password"></div></div>
    <label>Jenis usaha <small style="font-weight:400;color:#6b7a80">(untuk kategori produk awal, bisa diubah nanti)</small></label><select name="jenis"><option value="">– Isi kategori sendiri nanti –</option><?php foreach (['restoran' => 'Restoran / Kafe', 'warung' => 'Warung / Kelontong', 'ritel' => 'Minimarket / Toko Ritel', 'peralatan' => 'Toko Peralatan / Bangunan', 'fashion' => 'Fashion / Butik', 'elektronik' => 'Elektronik / Konter HP', 'apotek' => 'Apotek / Toko Kesehatan', 'kosmetik' => 'Kosmetik / Kecantikan', 'bengkel' => 'Bengkel / Sparepart'] as $k => $t) echo '<option value="' . $k . '"' . (($_POST['jenis'] ?? '') === $k ? ' selected' : '') . '>' . $t . '</option>'; ?></select>
    <label class="cb"><input type="checkbox" name="demo" value="1" <?= $_SERVER['REQUEST_METHOD'] !== 'POST' || !empty($_POST['demo']) ? 'checked' : '' ?>> Isi data contoh: 7 produk, 5 aset, 5 karyawan (bisa dihapus nanti)</label>
    </div>
    <button <?= $allOk ? '' : 'disabled style="background:#9fb3af"' ?>>Instal sekarang</button>
  </form>
  <script>
  (function(){var f=document.getElementById('f');function up(){var s=f.querySelector('input[name=mode]:checked').value==='saas';
    f.querySelectorAll('.m-saas').forEach(function(e){e.style.display=s?'':'none'});f.querySelectorAll('.m-toko').forEach(function(e){e.style.display=s?'none':''});}
    f.querySelectorAll('input[name=mode]').forEach(function(r){r.onchange=up});up();})();
  </script>
<?php endif; ?>
</div></body></html>
