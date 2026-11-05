<?php
// Arahkan ke wizard instalasi bila belum terinstal. Mode SaaS: domain utama → halaman daftar, alamat toko → Beranda.
$f = __DIR__ . '/app/config.php';
if (!is_file($f)) { header('Location: install.php'); exit; }
$c = require $f;
$dest = 'beranda.html';
if (!empty($c['saas']['domain'])) {
    $h = preg_replace('/:\d+$/', '', strtolower((string)($_SERVER['HTTP_HOST'] ?? '')));
    $d = strtolower($c['saas']['domain']);
    if ($h === $d || $h === 'www.' . $d) $dest = 'daftar.html';
}
header('Location: ' . $dest);
