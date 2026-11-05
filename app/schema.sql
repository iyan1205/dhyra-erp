-- ============================================================
--  KasirPro Terpadu (Kasir POS + Akuntansi) — skema MySQL/MariaDB
--  Dijalankan otomatis oleh install.php. Boleh juga diimpor manual lewat phpMyAdmin.
-- ============================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  username VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL DEFAULT '',
  role VARCHAR(10) NOT NULL DEFAULT 'kasir',          -- admin | akuntan | kasir | viewer
  pass_hash VARCHAR(255) NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'aktif',         -- aktif | nonaktif
  login_terakhir DATETIME NULL,
  dibuat DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sesi (
  token CHAR(64) NOT NULL PRIMARY KEY,
  user_id VARCHAR(20) NOT NULL,
  dibuat DATETIME NOT NULL,
  kedaluwarsa DATETIME NOT NULL,
  KEY ix_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_gagal (
  username VARCHAR(60) NOT NULL PRIMARY KEY,
  n INT NOT NULL DEFAULT 0,
  sampai DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pengaturan (
  k VARCHAR(40) NOT NULL PRIMARY KEY,
  v TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS penomoran (
  k VARCHAR(40) NOT NULL PRIMARY KEY,
  n INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ------------------------------ KASIR ------------------------------ */
CREATE TABLE IF NOT EXISTS kategori (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  nama VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS produk (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  barcode VARCHAR(60) NULL UNIQUE,
  sku VARCHAR(40) NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  kategori VARCHAR(80) NOT NULL DEFAULT '',
  satuan VARCHAR(20) NOT NULL DEFAULT 'pcs',
  harga_beli DECIMAL(18,2) NOT NULL DEFAULT 0,
  harga_jual DECIMAL(18,2) NOT NULL DEFAULT 0,
  stok DECIMAL(18,3) NOT NULL DEFAULT 0,
  stok_min DECIMAL(18,3) NOT NULL DEFAULT 0,
  foto VARCHAR(255) NOT NULL DEFAULT '',
  dibuat DATETIME NOT NULL,
  diubah DATETIME NOT NULL,
  KEY ix_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transaksi (
  no VARCHAR(30) NOT NULL PRIMARY KEY,
  tanggal DATETIME NOT NULL,
  kasir VARCHAR(30) NOT NULL,
  pelanggan VARCHAR(120) NOT NULL DEFAULT '',
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
  diskon DECIMAL(18,2) NOT NULL DEFAULT 0,
  pajak DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  metode VARCHAR(20) NOT NULL DEFAULT 'Tunai',
  bayar DECIMAL(18,2) NOT NULL DEFAULT 0,
  kembali DECIMAL(18,2) NOT NULL DEFAULT 0,
  laba DECIMAL(18,2) NOT NULL DEFAULT 0,
  hpp DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'Selesai',       -- Selesai | Batal
  catatan TEXT NOT NULL,
  items MEDIUMTEXT NOT NULL,
  KEY ix_tgl (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS transaksi_detail (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  no VARCHAR(30) NOT NULL,
  tanggal DATETIME NOT NULL,
  pid VARCHAR(20) NOT NULL,
  barcode VARCHAR(60) NOT NULL DEFAULT '',
  nama VARCHAR(150) NOT NULL,
  kategori VARCHAR(80) NOT NULL DEFAULT '',
  satuan VARCHAR(20) NOT NULL DEFAULT '',
  qty DECIMAL(18,3) NOT NULL,
  harga DECIMAL(18,2) NOT NULL,
  diskon DECIMAL(18,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(18,2) NOT NULL,
  hpp DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'Selesai',
  KEY ix_no (no), KEY ix_tgl (tanggal), KEY ix_pid (pid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS opname (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  tanggal DATETIME NOT NULL,
  user VARCHAR(30) NOT NULL,
  pid VARCHAR(20) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  sistem DECIMAL(18,3) NOT NULL,
  fisik DECIMAL(18,3) NOT NULL,
  selisih DECIMAL(18,3) NOT NULL,
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  KEY ix_tgl (tanggal), KEY ix_pid (pid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stok_masuk_doc (
  no VARCHAR(30) NOT NULL PRIMARY KEY,
  tanggal DATETIME NOT NULL,
  user VARCHAR(30) NOT NULL,
  supplier VARCHAR(120) NOT NULL DEFAULT '',
  kontak_id VARCHAR(20) NOT NULL DEFAULT '',
  bayar VARCHAR(10) NOT NULL DEFAULT 'Kas',            -- Kas | Bank | Hutang
  akun_kas VARCHAR(12) NOT NULL DEFAULT '',
  faktur_id VARCHAR(20) NOT NULL DEFAULT '',
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  catatan VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stok_masuk (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  no VARCHAR(30) NOT NULL,
  tanggal DATETIME NOT NULL,
  user VARCHAR(30) NOT NULL,
  supplier VARCHAR(120) NOT NULL DEFAULT '',
  pid VARCHAR(20) NOT NULL,
  barcode VARCHAR(60) NOT NULL DEFAULT '',
  nama VARCHAR(150) NOT NULL,
  satuan VARCHAR(20) NOT NULL DEFAULT '',
  qty DECIMAL(18,3) NOT NULL,
  harga DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  sebelum DECIMAL(18,3) NOT NULL DEFAULT 0,
  sesudah DECIMAL(18,3) NOT NULL DEFAULT 0,
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  KEY ix_no (no), KEY ix_tgl (tanggal), KEY ix_pid (pid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ---------------------------- AKUNTANSI ---------------------------- */
CREATE TABLE IF NOT EXISTS akun (
  kode VARCHAR(12) NOT NULL PRIMARY KEY,
  nama VARCHAR(120) NOT NULL,
  kategori VARCHAR(12) NOT NULL,                      -- Aset | Kewajiban | Ekuitas | Pendapatan | Beban
  subkategori VARCHAR(60) NOT NULL DEFAULT '',
  saldo_normal VARCHAR(6) NOT NULL DEFAULT 'Debit',
  saldo_awal DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'Aktif',
  keterangan VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kontak (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  nama VARCHAR(150) NOT NULL,
  tipe VARCHAR(12) NOT NULL DEFAULT 'Pelanggan',       -- Pelanggan | Pemasok | Keduanya
  telepon VARCHAR(40) NOT NULL DEFAULT '',
  email VARCHAR(120) NOT NULL DEFAULT '',
  alamat VARCHAR(255) NOT NULL DEFAULT '',
  npwp VARCHAR(40) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'Aktif',
  KEY ix_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jurnal (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  entri_id VARCHAR(30) NOT NULL,
  tanggal DATE NOT NULL,
  no_bukti VARCHAR(40) NOT NULL DEFAULT '',
  jenis VARCHAR(40) NOT NULL DEFAULT 'Jurnal Umum',
  kode_akun VARCHAR(12) NOT NULL,
  keterangan VARCHAR(255) NOT NULL DEFAULT '',
  debit DECIMAL(18,2) NOT NULL DEFAULT 0,
  kredit DECIMAL(18,2) NOT NULL DEFAULT 0,
  kontak_id VARCHAR(20) NOT NULL DEFAULT '',
  ref VARCHAR(40) NOT NULL DEFAULT '',
  dibuat_oleh VARCHAR(30) NOT NULL DEFAULT '',
  dibuat_pada DATETIME NOT NULL,
  KEY ix_entri (entri_id), KEY ix_tgl (tanggal), KEY ix_akun (kode_akun, tanggal), KEY ix_ref (ref), KEY ix_kontak (kontak_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faktur (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  nomor VARCHAR(40) NOT NULL,
  tipe VARCHAR(10) NOT NULL,                           -- Penjualan | Pembelian
  tanggal DATE NOT NULL,
  jatuh_tempo DATE NOT NULL,
  kontak_id VARCHAR(20) NOT NULL,
  keterangan VARCHAR(255) NOT NULL DEFAULT '',
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
  persen_ppn DECIMAL(6,2) NOT NULL DEFAULT 0,
  ppn DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  akun_lawan VARCHAR(12) NOT NULL,
  item MEDIUMTEXT NOT NULL,
  sumber VARCHAR(40) NOT NULL DEFAULT '',              -- 'SM:<no>' bila dibuat dari Stok Masuk kasir
  dibuat_oleh VARCHAR(30) NOT NULL DEFAULT '',
  dibuat_pada DATETIME NOT NULL,
  KEY ix_tgl (tanggal), KEY ix_kontak (kontak_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pembayaran (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  faktur_id VARCHAR(20) NOT NULL,
  tanggal DATE NOT NULL,
  jumlah DECIMAL(18,2) NOT NULL,
  akun_kas VARCHAR(12) NOT NULL,
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  dibuat_oleh VARCHAR(30) NOT NULL DEFAULT '',
  KEY ix_faktur (faktur_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_aktivitas (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  waktu DATETIME NOT NULL,
  user VARCHAR(30) NOT NULL DEFAULT '-',
  aksi VARCHAR(60) NOT NULL,
  modul VARCHAR(30) NOT NULL,
  detail VARCHAR(255) NOT NULL DEFAULT '',
  KEY ix_waktu (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* --------------------- Dhyra v3: hak akses & lokasi --------------------- */
CREATE TABLE IF NOT EXISTS jabatan (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  nama VARCHAR(60) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  akses TEXT NOT NULL,                                  -- JSON {"kasir":0-3,"inventaris":0-3,"akuntansi":0-3}
  sistem TINYINT(1) NOT NULL DEFAULT 0,
  dibuat DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lokasi (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  kode VARCHAR(12) NOT NULL,
  nama VARCHAR(80) NOT NULL,
  tipe VARCHAR(10) NOT NULL DEFAULT 'gudang',           -- toko | gudang
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  urut INT NOT NULL DEFAULT 9
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stok_lokasi (
  pid VARCHAR(20) NOT NULL,
  lokasi_id VARCHAR(20) NOT NULL,
  qty DECIMAL(18,3) NOT NULL DEFAULT 0,
  min DECIMAL(18,3) NOT NULL DEFAULT 0,
  rak VARCHAR(30) NOT NULL DEFAULT '',
  PRIMARY KEY (pid, lokasi_id),
  KEY ix_lok (lokasi_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mutasi (
  no VARCHAR(30) NOT NULL PRIMARY KEY,
  tanggal DATETIME NOT NULL,
  dari VARCHAR(20) NOT NULL,
  ke VARCHAR(20) NOT NULL,
  status VARCHAR(12) NOT NULL DEFAULT 'Permintaan',     -- Permintaan | Dikirim | Diterima | Batal
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  dibuat_oleh VARCHAR(30) NOT NULL DEFAULT '',
  dikirim_oleh VARCHAR(30) NOT NULL DEFAULT '',
  dikirim_pada DATETIME NULL,
  diterima_oleh VARCHAR(30) NOT NULL DEFAULT '',
  diterima_pada DATETIME NULL,
  KEY ix_tgl (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mutasi_item (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  no VARCHAR(30) NOT NULL,
  pid VARCHAR(20) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  satuan VARCHAR(20) NOT NULL DEFAULT '',
  qty_minta DECIMAL(18,3) NOT NULL DEFAULT 0,
  qty_kirim DECIMAL(18,3) NOT NULL DEFAULT 0,
  qty_terima DECIMAL(18,3) NOT NULL DEFAULT 0,
  KEY ix_no (no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ======================= DHYRA v4 — TAHAP 2 ======================= */
CREATE TABLE IF NOT EXISTS member (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(120) NOT NULL,
  hp VARCHAR(30) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL DEFAULT '',
  tgl_lahir DATE NULL,
  jk VARCHAR(1) NOT NULL DEFAULT '',
  alamat VARCHAR(255) NOT NULL DEFAULT '',
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  poin INT NOT NULL DEFAULT 0,
  total_belanja DECIMAL(18,2) NOT NULL DEFAULT 0,
  jml_trx INT NOT NULL DEFAULT 0,
  terakhir DATETIME NULL,
  aktif TINYINT NOT NULL DEFAULT 1,
  dibuat DATETIME NOT NULL,
  dibuat_oleh VARCHAR(30) NOT NULL DEFAULT '',
  KEY ix_nama (nama)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS member_poin (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  member_id VARCHAR(20) NOT NULL,
  tanggal DATETIME NOT NULL,
  jenis VARCHAR(12) NOT NULL,                          -- Dapat | Tukar | Batal | Koreksi
  ref VARCHAR(30) NOT NULL DEFAULT '',
  poin INT NOT NULL,
  saldo INT NOT NULL DEFAULT 0,
  ket VARCHAR(255) NOT NULL DEFAULT '',
  user VARCHAR(30) NOT NULL DEFAULT '',
  KEY ix_m (member_id, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promo (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  nama VARCHAR(120) NOT NULL,
  tipe VARCHAR(10) NOT NULL,                           -- diskon | bxgy | grosir | nota
  nilai_tipe VARCHAR(1) NOT NULL DEFAULT 'p',          -- p persen | r potongan Rp | h harga khusus
  nilai DECIMAL(18,2) NOT NULL DEFAULT 0,
  target VARCHAR(10) NOT NULL DEFAULT 'semua',         -- semua | kategori | produk
  target_ids TEXT NOT NULL,
  beli INT NOT NULL DEFAULT 0,
  gratis INT NOT NULL DEFAULT 0,
  tingkat TEXT NOT NULL,
  min_belanja DECIMAL(18,2) NOT NULL DEFAULT 0,
  maks_diskon DECIMAL(18,2) NOT NULL DEFAULT 0,
  mulai DATE NULL,
  selesai DATE NULL,
  hari VARCHAR(7) NOT NULL DEFAULT '1234567',
  jam_mulai VARCHAR(5) NOT NULL DEFAULT '',
  jam_selesai VARCHAR(5) NOT NULL DEFAULT '',
  khusus_member TINYINT NOT NULL DEFAULT 0,
  aktif TINYINT NOT NULL DEFAULT 1,
  dibuat DATETIME NOT NULL,
  dibuat_oleh VARCHAR(30) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS promo_pakai (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  promo_id VARCHAR(20) NOT NULL,
  no VARCHAR(30) NOT NULL,
  tanggal DATETIME NOT NULL,
  diskon DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'Selesai',
  KEY ix_p (promo_id, tanggal), KEY ix_no (no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS po (
  no VARCHAR(30) NOT NULL PRIMARY KEY,
  tanggal DATE NOT NULL,
  kontak_id VARCHAR(20) NOT NULL DEFAULT '',
  pemasok VARCHAR(150) NOT NULL DEFAULT '',
  lokasi_id VARCHAR(20) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'Draf',          -- Draf | Dipesan | Sebagian | Selesai | Batal
  perkiraan DATE NULL,
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  dibuat_oleh VARCHAR(30) NOT NULL DEFAULT '',
  dibuat_pada DATETIME NOT NULL,
  dipesan_oleh VARCHAR(30) NOT NULL DEFAULT '',
  dipesan_pada DATETIME NULL,
  KEY ix_tgl (tanggal), KEY ix_k (kontak_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS po_item (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  no VARCHAR(30) NOT NULL,
  pid VARCHAR(20) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  satuan VARCHAR(20) NOT NULL DEFAULT '',
  qty DECIMAL(18,3) NOT NULL,
  harga DECIMAL(18,2) NOT NULL DEFAULT 0,
  qty_terima DECIMAL(18,3) NOT NULL DEFAULT 0,
  KEY ix_no (no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS retur_beli (
  no VARCHAR(30) NOT NULL PRIMARY KEY,
  tanggal DATETIME NOT NULL,
  kontak_id VARCHAR(20) NOT NULL DEFAULT '',
  pemasok VARCHAR(150) NOT NULL DEFAULT '',
  lokasi_id VARCHAR(20) NOT NULL DEFAULT '',
  po_no VARCHAR(30) NOT NULL DEFAULT '',
  metode VARCHAR(10) NOT NULL DEFAULT 'Tagihan',       -- Tagihan (potong hutang) | Kas | Bank | Ganti (barang diganti)
  faktur_id VARCHAR(20) NOT NULL DEFAULT '',
  bayar_id VARCHAR(20) NOT NULL DEFAULT '',
  akun_kas VARCHAR(12) NOT NULL DEFAULT '',
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  alasan VARCHAR(255) NOT NULL DEFAULT '',
  user VARCHAR(30) NOT NULL DEFAULT '',
  KEY ix_tgl (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS retur_item (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  no VARCHAR(30) NOT NULL,
  pid VARCHAR(20) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  satuan VARCHAR(20) NOT NULL DEFAULT '',
  qty DECIMAL(18,3) NOT NULL,
  harga DECIMAL(18,2) NOT NULL DEFAULT 0,
  KEY ix_no (no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ======================= DHYRA v5 — TAHAP 3 ======================= */
CREATE TABLE IF NOT EXISTS aset (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  kode VARCHAR(30) NOT NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  kategori VARCHAR(60) NOT NULL DEFAULT '',
  lokasi_id VARCHAR(20) NOT NULL DEFAULT '',
  pj VARCHAR(120) NOT NULL DEFAULT '',
  merek VARCHAR(120) NOT NULL DEFAULT '',
  no_seri VARCHAR(120) NOT NULL DEFAULT '',
  tgl_beli DATE NOT NULL,
  harga DECIMAL(18,2) NOT NULL DEFAULT 0,
  nilai_sisa DECIMAL(18,2) NOT NULL DEFAULT 0,
  umur_bulan INT NOT NULL DEFAULT 48,
  susut_awal DECIMAL(18,2) NOT NULL DEFAULT 0,
  kondisi VARCHAR(20) NOT NULL DEFAULT 'Baik',
  status VARCHAR(10) NOT NULL DEFAULT 'Aktif',
  sumber VARCHAR(10) NOT NULL DEFAULT 'Kas',
  akun_kas VARCHAR(12) NOT NULL DEFAULT '',
  akun_aset VARCHAR(12) NOT NULL DEFAULT '',
  pemasok VARCHAR(150) NOT NULL DEFAULT '',
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  foto VARCHAR(255) NOT NULL DEFAULT '',
  lepas_tgl DATE NULL,
  lepas_nilai DECIMAL(18,2) NOT NULL DEFAULT 0,
  lepas_alasan VARCHAR(255) NOT NULL DEFAULT '',
  dibuat DATETIME NOT NULL,
  dibuat_oleh VARCHAR(30) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aset_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  aset_id VARCHAR(20) NOT NULL,
  tanggal DATETIME NOT NULL,
  jenis VARCHAR(20) NOT NULL,
  kondisi VARCHAR(20) NOT NULL DEFAULT '',
  biaya DECIMAL(18,2) NOT NULL DEFAULT 0,
  ket VARCHAR(255) NOT NULL DEFAULT '',
  user VARCHAR(30) NOT NULL DEFAULT '',
  KEY ix_a (aset_id, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aset_susut (
  aset_id VARCHAR(20) NOT NULL,
  periode CHAR(7) NOT NULL,
  nilai DECIMAL(18,2) NOT NULL,
  entri_id VARCHAR(20) NOT NULL DEFAULT '',
  PRIMARY KEY (aset_id, periode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS karyawan (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  kode VARCHAR(20) NOT NULL UNIQUE,
  nama VARCHAR(120) NOT NULL,
  user_id VARCHAR(20) NOT NULL DEFAULT '',
  posisi VARCHAR(80) NOT NULL DEFAULT '',
  lokasi_id VARCHAR(20) NOT NULL DEFAULT '',
  hp VARCHAR(30) NOT NULL DEFAULT '',
  email VARCHAR(120) NOT NULL DEFAULT '',
  alamat VARCHAR(255) NOT NULL DEFAULT '',
  tgl_masuk DATE NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'Aktif',
  tipe_gaji VARCHAR(10) NOT NULL DEFAULT 'Bulanan',
  gaji_pokok DECIMAL(18,2) NOT NULL DEFAULT 0,
  tunjangan DECIMAL(18,2) NOT NULL DEFAULT 0,
  uang_makan DECIMAL(18,2) NOT NULL DEFAULT 0,
  lembur_jam DECIMAL(18,2) NOT NULL DEFAULT 0,
  denda_telat DECIMAL(18,2) NOT NULL DEFAULT 0,
  jatah_cuti INT NOT NULL DEFAULT 12,
  shift_id VARCHAR(20) NOT NULL DEFAULT '',
  hari_kerja VARCHAR(7) NOT NULL DEFAULT '123456',
  kpi_id VARCHAR(20) NOT NULL DEFAULT '',
  bank VARCHAR(60) NOT NULL DEFAULT '',
  rekening VARCHAR(60) NOT NULL DEFAULT '',
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  dibuat DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shift (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  nama VARCHAR(60) NOT NULL,
  masuk VARCHAR(5) NOT NULL,
  pulang VARCHAR(5) NOT NULL,
  toleransi INT NOT NULL DEFAULT 10,
  warna VARCHAR(10) NOT NULL DEFAULT '#10A391',
  aktif TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jadwal (
  karyawan_id VARCHAR(20) NOT NULL,
  tanggal DATE NOT NULL,
  shift_id VARCHAR(20) NOT NULL DEFAULT '',
  PRIMARY KEY (karyawan_id, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS absensi (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  karyawan_id VARCHAR(20) NOT NULL,
  tanggal DATE NOT NULL,
  shift_id VARCHAR(20) NOT NULL DEFAULT '',
  j_masuk VARCHAR(5) NOT NULL DEFAULT '',
  j_pulang VARCHAR(5) NOT NULL DEFAULT '',
  masuk DATETIME NULL,
  pulang DATETIME NULL,
  foto_masuk VARCHAR(255) NOT NULL DEFAULT '',
  foto_pulang VARCHAR(255) NOT NULL DEFAULT '',
  lat_masuk DECIMAL(10,7) NULL, lng_masuk DECIMAL(10,7) NULL, jarak_masuk INT NULL,
  lat_pulang DECIMAL(10,7) NULL, lng_pulang DECIMAL(10,7) NULL, jarak_pulang INT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'Hadir',
  telat INT NOT NULL DEFAULT 0,
  pulang_cepat INT NOT NULL DEFAULT 0,
  lembur INT NOT NULL DEFAULT 0,
  sumber VARCHAR(10) NOT NULL DEFAULT 'HP',
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  UNIQUE KEY ux_kt (karyawan_id, tanggal), KEY ix_t (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pengajuan (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  karyawan_id VARCHAR(20) NOT NULL,
  jenis VARCHAR(10) NOT NULL,
  mulai DATE NOT NULL,
  selesai DATE NOT NULL,
  menit INT NOT NULL DEFAULT 0,
  alasan VARCHAR(255) NOT NULL DEFAULT '',
  lampiran VARCHAR(255) NOT NULL DEFAULT '',
  status VARCHAR(10) NOT NULL DEFAULT 'Menunggu',
  diproses_oleh VARCHAR(30) NOT NULL DEFAULT '',
  diproses_pada DATETIME NULL,
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  dibuat DATETIME NOT NULL,
  KEY ix_k (karyawan_id, mulai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kasbon (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  karyawan_id VARCHAR(20) NOT NULL,
  tanggal DATE NOT NULL,
  jumlah DECIMAL(18,2) NOT NULL,
  cicilan DECIMAL(18,2) NOT NULL,
  sisa DECIMAL(18,2) NOT NULL,
  akun_kas VARCHAR(12) NOT NULL DEFAULT '',
  ket VARCHAR(255) NOT NULL DEFAULT '',
  dibuat_oleh VARCHAR(30) NOT NULL DEFAULT '',
  KEY ix_k (karyawan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gaji (
  id VARCHAR(30) NOT NULL PRIMARY KEY,
  periode CHAR(7) NOT NULL,
  karyawan_id VARCHAR(20) NOT NULL,
  data MEDIUMTEXT NOT NULL,
  pendapatan DECIMAL(18,2) NOT NULL DEFAULT 0,
  potongan DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'Draf',
  final_pada DATETIME NULL,
  dibayar_pada DATE NULL,
  akun_kas VARCHAR(12) NOT NULL DEFAULT '',
  diubah DATETIME NOT NULL,
  UNIQUE KEY ux_pk (periode, karyawan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kpi_tpl (
  id VARCHAR(20) NOT NULL PRIMARY KEY,
  nama VARCHAR(120) NOT NULL,
  indikator MEDIUMTEXT NOT NULL,
  aktif TINYINT NOT NULL DEFAULT 1,
  dibuat DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kpi_nilai (
  periode CHAR(7) NOT NULL,
  karyawan_id VARCHAR(20) NOT NULL,
  indikator_id VARCHAR(20) NOT NULL,
  nilai DECIMAL(18,2) NOT NULL DEFAULT 0,
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  oleh VARCHAR(30) NOT NULL DEFAULT '',
  PRIMARY KEY (periode, karyawan_id, indikator_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesanan_online (
  no VARCHAR(30) NOT NULL PRIMARY KEY,
  kode VARCHAR(16) NOT NULL,
  tanggal DATETIME NOT NULL,
  nama VARCHAR(120) NOT NULL,
  hp VARCHAR(30) NOT NULL,
  alamat VARCHAR(255) NOT NULL DEFAULT '',
  catatan VARCHAR(255) NOT NULL DEFAULT '',
  kirim VARCHAR(60) NOT NULL DEFAULT '',
  ongkir DECIMAL(18,2) NOT NULL DEFAULT 0,
  bayar VARCHAR(20) NOT NULL DEFAULT 'Transfer',
  items MEDIUMTEXT NOT NULL,
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
  diskon DECIMAL(18,2) NOT NULL DEFAULT 0,
  total DECIMAL(18,2) NOT NULL DEFAULT 0,
  member_id VARCHAR(20) NOT NULL DEFAULT '',
  status VARCHAR(12) NOT NULL DEFAULT 'Baru',
  trx_no VARCHAR(30) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  log TEXT NOT NULL,
  KEY ix_t (tanggal), KEY ix_s (status), KEY ix_ip (ip, tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ======================= DHYRA v5.1 ======================= */
CREATE TABLE IF NOT EXISTS offline_sync (
  kunci VARCHAR(60) NOT NULL PRIMARY KEY,
  trx_no VARCHAR(30) NOT NULL,
  dibuat DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ------------------------------ v9: percobaan akses di luar jam kerja ------------------------------ */
CREATE TABLE IF NOT EXISTS akses_log (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  waktu DATETIME NOT NULL,
  user_id VARCHAR(20) NOT NULL DEFAULT '',
  username VARCHAR(30) NOT NULL DEFAULT '',
  nama VARCHAR(120) NOT NULL DEFAULT '',
  konteks VARCHAR(60) NOT NULL DEFAULT '',
  ket VARCHAR(255) NOT NULL DEFAULT '',
  KEY ix_w (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
