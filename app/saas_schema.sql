-- ============================================================
--  Dhyra SaaS — database PUSAT (daftar toko, paket, tagihan, voucher, pengumuman)
--  Dibuat otomatis oleh install.php (mode SaaS). Data tiap toko ada di database terpisah.
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS s_paket (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  nama VARCHAR(40) NOT NULL,
  deskripsi VARCHAR(255) NOT NULL DEFAULT '',
  harga_bulan DECIMAL(14,0) NOT NULL DEFAULT 0,
  harga_tahun DECIMAL(14,0) NOT NULL DEFAULT 0,          -- 0 = 12 x harga bulanan
  modul TEXT NULL,                                       -- JSON daftar modul yang aktif
  max_outlet INT NOT NULL DEFAULT 0,                     -- 0 = tanpa batas
  max_user INT NOT NULL DEFAULT 0,
  max_produk INT NOT NULL DEFAULT 0,
  aktif TINYINT NOT NULL DEFAULT 1,
  unggulan TINYINT NOT NULL DEFAULT 0,
  urut INT NOT NULL DEFAULT 0,
  dibuat DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_toko (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  slug VARCHAR(40) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  pemilik VARCHAR(100) NOT NULL DEFAULT '',
  wa VARCHAR(20) NOT NULL DEFAULT '',
  email VARCHAR(120) NOT NULL DEFAULT '',
  jenis VARCHAR(20) NOT NULL DEFAULT '',
  paket_id VARCHAR(20) NOT NULL DEFAULT '',
  trial TINYINT NOT NULL DEFAULT 1,
  berakhir DATE NOT NULL,
  beku TINYINT NOT NULL DEFAULT 0,
  beku_alasan VARCHAR(255) NOT NULL DEFAULT '',
  db_name VARCHAR(64) NOT NULL,
  ref_kode VARCHAR(20) NOT NULL UNIQUE,
  ref_oleh VARCHAR(20) NOT NULL DEFAULT '',
  bayar_pertama DATETIME NULL,
  aktif_terakhir DATETIME NULL,
  catatan TEXT NULL,
  dibuat DATETIME NOT NULL,
  KEY ix_berakhir (berakhir),
  KEY ix_ref (ref_oleh)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_tagihan (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  no VARCHAR(30) NOT NULL UNIQUE,
  toko_id VARCHAR(20) NOT NULL,
  paket_id VARCHAR(20) NOT NULL,
  paket_nama VARCHAR(40) NOT NULL DEFAULT '',
  bulan INT NOT NULL DEFAULT 1,
  harga DECIMAL(14,0) NOT NULL DEFAULT 0,
  diskon DECIMAL(14,0) NOT NULL DEFAULT 0,
  diskon_ket VARCHAR(160) NOT NULL DEFAULT '',
  total DECIMAL(14,0) NOT NULL DEFAULT 0,
  voucher VARCHAR(30) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'menunggu',       -- menunggu | dibayar | batal
  bukti VARCHAR(120) NOT NULL DEFAULT '',
  bukti_pada DATETIME NULL,
  bukti_ket VARCHAR(255) NOT NULL DEFAULT '',
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  dibuat DATETIME NOT NULL,
  dibuat_oleh VARCHAR(40) NOT NULL DEFAULT '',
  dibayar DATETIME NULL,
  dikonfirmasi VARCHAR(40) NOT NULL DEFAULT '',
  periode_mulai DATE NULL,
  periode_sampai DATE NULL,
  KEY ix_toko (toko_id),
  KEY ix_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_voucher (
  kode VARCHAR(30) NOT NULL PRIMARY KEY,
  jenis VARCHAR(10) NOT NULL DEFAULT 'persen',          -- persen | nominal
  nilai DECIMAL(14,0) NOT NULL DEFAULT 0,
  kuota INT NOT NULL DEFAULT 0,                          -- 0 = tanpa batas
  terpakai INT NOT NULL DEFAULT 0,
  sampai DATE NULL,
  paket TEXT NULL,                                       -- JSON daftar paket (kosong = semua)
  min_bulan INT NOT NULL DEFAULT 1,
  aktif TINYINT NOT NULL DEFAULT 1,
  ket VARCHAR(160) NOT NULL DEFAULT '',
  dibuat DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_otp (
  wa VARCHAR(20) NOT NULL PRIMARY KEY,
  kode_hash VARCHAR(255) NOT NULL,
  sampai DATETIME NOT NULL,
  coba INT NOT NULL DEFAULT 0,
  kirim INT NOT NULL DEFAULT 0,
  kirim_pada DATETIME NOT NULL,
  jam_mulai DATETIME NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  lolos TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_pengumuman (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  judul VARCHAR(120) NOT NULL,
  isi TEXT NULL,
  tipe VARCHAR(10) NOT NULL DEFAULT 'info',             -- info | penting | promo
  target VARCHAR(40) NOT NULL DEFAULT 'semua',          -- semua | trial | bayar | paket:<id>
  mulai DATE NOT NULL,
  sampai DATE NULL,
  aktif TINYINT NOT NULL DEFAULT 1,
  dibuat DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_admin (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL,
  aktif TINYINT NOT NULL DEFAULT 1,
  login_terakhir DATETIME NULL,
  dibuat DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_sesi (
  token CHAR(64) NOT NULL PRIMARY KEY,
  admin_id VARCHAR(20) NOT NULL,
  kedaluwarsa DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_gagal (
  k VARCHAR(80) NOT NULL PRIMARY KEY,
  n INT NOT NULL DEFAULT 0,
  sampai DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_masuk (
  kode CHAR(48) NOT NULL PRIMARY KEY,
  toko_id VARCHAR(20) NOT NULL,
  oleh VARCHAR(40) NOT NULL DEFAULT '',
  sampai DATETIME NOT NULL,
  dipakai TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_log (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  waktu DATETIME NOT NULL,
  oleh VARCHAR(40) NOT NULL DEFAULT '',
  toko_id VARCHAR(20) NOT NULL DEFAULT '',
  aksi VARCHAR(60) NOT NULL,
  detail VARCHAR(255) NOT NULL DEFAULT '',
  KEY ix_toko (toko_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_kirim (
  toko_id VARCHAR(20) NOT NULL,
  jenis VARCHAR(30) NOT NULL,
  tanggal DATE NOT NULL,
  PRIMARY KEY (toko_id, jenis, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_nomor (
  k VARCHAR(40) NOT NULL PRIMARY KEY,
  n INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS s_set (
  k VARCHAR(40) NOT NULL PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
