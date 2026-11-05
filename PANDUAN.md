# Dhyra — Stok pas, kas cocok, untung kelihatan.

**Dhyra 5.6.2** adalah aplikasi web (PHP + MySQL) untuk toko: satu database, satu login,
dan setiap karyawan hanya melihat modul yang diizinkan.

> **Baru di 5.6.2 — mode SaaS (banyak toko, satu server).** Toko mendaftar sendiri di `domainanda.id`,
> mendapat alamat `namatoko.domainanda.id` + database sendiri, lalu berlangganan paket (Basic/Pro/Bisnis —
> harga, menu, dan batas jumlahnya Anda atur sendiri di Panel Pengelola). Lengkap dengan verifikasi WhatsApp,
> masa coba, tagihan + unggah bukti transfer/QRIS, voucher, referral, pengingat WA, masa tenggang → baca-saja,
> pengumuman, dan "masuk sebagai toko". Cara pasang di VPS: lihat **PANDUAN-SAAS.md**.
> Mode satu toko (hosting sendiri) tetap berjalan seperti biasa.

| Modul | File | Isi |
|---|---|---|
| **Beranda** | `beranda.html` | Halaman login + peluncur modul (seperti menu aplikasi Odoo) |
| **Dashboard Pemilik** | `laporan.html#ringkasan` | Omzet, laba, transaksi, stok kritis, hutang, kas & bank, produk terlaris |
| **Kasir (POS)** | `kasir.html` | Jualan, promo otomatis, member & poin, riwayat, laporan, pengaturan struk |
| **Inventaris & Gudang** | `inventaris.html` | Produk + SKU, kartu stok, stok masuk, mutasi, isi ulang rak, opname, rekap, lokasi |
| **Pembelian** | `pembelian.html` | Pesanan pembelian (PO), terima barang, retur ke pemasok, pemasok, saran beli |
| **Member & CRM** | `member.html` | Data member, riwayat belanja, poin, ulang tahun, pesan WhatsApp |
| **Promo** | `promo.html` | Diskon produk (harga coret), beli X gratis Y, harga grosir, diskon belanja |
| **Toko Online** | `online.html` + `toko.html` | Katalog publik untuk pelanggan, pesanan via WhatsApp, kelola pesanan |
| **Akuntansi** | `akuntansi.html` | Jurnal & kas, faktur, purchase order, laporan keuangan |
| **Inventaris Aset** | `aset.html` | Peralatan toko, kondisi, perbaikan, penyusutan, pelepasan |
| **SDM & Gaji** | `sdm.html` + `absen.html` | Karyawan, absen selfie + GPS, jadwal, izin/cuti/lembur, kasbon, penggajian, slip |
| **KPI Karyawan** | `kpi.html` | Template KPI, nilai otomatis & penilaian atasan, bonus per grade |
| **Aplikasi HP** | `app.html` | Pasang Dhyra di layar HP (PWA) |
| **Laporan** | `laporan.html#lap` | 12 laporan siap unduh Excel/PDF |
| **Pengguna & Akses** | `pengguna.html` | Pengguna, jabatan & hak akses, log aktivitas *(khusus admin)* |
| **Pengaturan** | `pengaturan.html` | Profil usaha, struk & pajak, SKU, program poin, cadangan *(khusus admin)* |
| **Langganan** *(mode SaaS)* | `langganan.html` | Paket aktif, pemakaian vs batas, pilih/perpanjang paket, tagihan & bukti bayar, kode referral *(khusus admin)* |

Tombol **Beranda** di setiap modul membawa kembali ke peluncur tanpa login ulang.

## Kebutuhan hosting

- PHP **8.0 atau lebih baru** dengan ekstensi `pdo_mysql` dan `mbstring` (bawaan di Hostinger & Rumahweb)
- MySQL 5.7+ / MariaDB 10.3+
- Tidak perlu Composer, Node, atau library tambahan

## Cara pasang di Hostinger (hPanel)

1. **Buat database**: hPanel → *Databases* → *Management* → isi nama database, user, dan password → *Create*.
   Catat nama database, user, dan password (formatnya seperti `u123456789_kasir`).
2. **Unggah file**: hPanel → *Files* → *File Manager* → buka `public_html` (atau subfolder, mis. `public_html/kasir`)
   → *Upload* file ZIP → klik kanan → *Extract*. Pastikan `beranda.html`, `api.php`, `install.php` berada langsung di folder itu.
3. **Versi PHP**: hPanel → *Advanced* → *PHP Configuration* → pilih PHP 8.1 / 8.2.
4. Buka `https://domain-anda/install.php` (atau `/kasir/install.php`), isi:
   - Host database: `localhost`
   - Nama database, user, password dari langkah 1
   - Nama toko dan **username + password admin** Anda
5. Klik **Instal sekarang**. Setelah berhasil, **hapus file `install.php`** lewat File Manager.
6. Buka `https://domain-anda/` → halaman Beranda Dhyra. Masuk dengan akun admin yang tadi dibuat.

## Cara pasang di Rumahweb (cPanel)

1. cPanel → **MySQL® Database Wizard** → buat database → buat user → centang **ALL PRIVILEGES** → *Next Step*.
2. cPanel → **File Manager** → `public_html` → *Upload* ZIP → *Extract*.
3. cPanel → **Select PHP Version** (atau *MultiPHP Manager*) → pilih PHP 8.1+ dan pastikan `pdo_mysql` & `mbstring` dicentang.
4. Buka `https://domain-anda/install.php`, isi data database (host `localhost`) dan akun admin → **Instal sekarang**.
5. Hapus `install.php` setelah selesai.

> Aktifkan SSL (HTTPS) gratis di panel hosting agar password dan data terenkripsi saat dikirim.

## Upgrade dari versi lama (KasirPro v2 / Dhyra 3 / 4 / 5.0)

1. Cadangkan dulu: *Akuntansi → Pengaturan → Unduh cadangan (.sql)*.
2. Unggah / salin semua file baru **menimpa** file lama, **kecuali** `app/config.php` dan folder `uploads/` (jangan ditimpa/dihapus).
3. Buka aplikasi seperti biasa. Database diperbarui **otomatis** saat pertama kali dibuka:
   - pengguna lama dipetakan ke jabatan (admin → Administrator, akuntan → Akuntan, kasir → Kasir, viewer → Pemilik),
   - (dari v2) lokasi **Toko Utama** dan **Gudang Utama** dibuat, dan **seluruh stok lama dipindahkan ke Toko Utama**,
   - (dari v2/v3) setiap produk otomatis diberi **SKU**, dan jabatan lama mendapat level akses untuk modul baru,
   - (v5) akun akuntansi baru ditambahkan (1504 Peralatan toko, 4104/6111 laba/rugi pelepasan aset, 4105 pendapatan ongkir, 6110 beban perbaikan), shift Pagi & Siang serta template KPI Kasir & Staf Gudang dibuat.
4. Setelah upgrade tekan **Ctrl+F5** sekali agar browser memuat tampilan terbaru.
5. Bila stok sebenarnya ada di gudang, pindahkan lewat *Inventaris → Mutasi stok → Pindahkan langsung*.

## Jabatan & hak akses

Setiap pengguna punya satu **jabatan**. Jabatan menentukan level akses per modul:

| Level | Artinya |
|---|---|
| **Tidak ada** | Modul tidak tampil di Beranda |
| **Lihat** | Hanya melihat data & laporan |
| **Petugas** | Mengerjakan tugas harian (jualan, stok masuk, mutasi, opname) |
| **Manajer** | Semua menu modul: ubah produk, batal transaksi, pengaturan, lokasi |

Jabatan bawaan (bisa diubah atau ditambah di *Pengguna & Hak Akses → Jabatan*):

| Jabatan | Kasir | Inventaris | Pembelian | Member | Promo | Online | Akuntansi | Aset | SDM | KPI | Laporan |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Administrator** | M | M | M | M | M | M | M | M | M | M | M |
| **Pemilik (pantau)** | L | L | L | L | L | L | L | L | L | L | M |
| **Kepala Toko** | M | M | M | M | M | M | – | P | P | P | M |
| **Kasir** | P | – | – | – | – | – | – | – | – | – | – |
| **Staf Gudang** | – | P | P | – | – | – | – | L | – | – | – |
| **Akuntan** | L | L | L | – | – | L | P | M | M | L | M |

M = Manajer · P = Petugas · L = Lihat · – = tidak ada. **Semua pengguna** bisa membuka *Absen saya* dan *Aplikasi HP*.
Laporan level **Lihat** menyembunyikan laba, HPP, nilai persediaan, dan saldo kas; level **Manajer** menampilkan semuanya.
Angka di tabel adalah bawaan untuk instalasi baru — selalu cek dan sesuaikan di *Pengguna & Akses → Jabatan*.

- Hanya jabatan **Administrator** yang bisa membuka modul *Pengguna & Hak Akses*; minimal harus ada satu admin aktif.
- Bila seseorang hanya punya satu modul (mis. kasir), setelah login ia langsung masuk ke modul itu.

## Lokasi stok, mutasi & isi ulang

- Stok dicatat **per lokasi** (toko / gudang). Tambah lokasi di *Inventaris → Lokasi*, sekaligus atur
  **lokasi penjualan** (stok yang dipotong kasir) dan **lokasi stok masuk** bawaan.
- Setiap produk bisa diberi **nomor rak** dan **batas minimum** per lokasi (*Produk → Ubah produk*).
- **Mutasi stok** (pindah antar lokasi):
  1. *Permintaan* — dibuat, misalnya oleh toko yang butuh barang (bisa dicetak sebagai daftar ambil).
  2. *Dikirim* — gudang mengirim; stok keluar dari gudang dan berstatus "dalam perjalanan".
  3. *Diterima* — lokasi tujuan mengonfirmasi jumlah yang benar-benar datang.
     Bila kurang, selisihnya otomatis dicatat sebagai selisih stok + jurnal koreksi.
  - Atau pilih **Pindahkan langsung** untuk memindahkan dalam satu langkah.
- **Saran isi ulang**: daftar produk yang stok tokonya di bawah minimum beserta jumlah yang disarankan
  diambil dari gudang; bisa langsung dibuat menjadi mutasi.
- **Rekap stok**: stok per lokasi untuk semua produk, bisa diunduh CSV.

## SKU produk

- Setiap produk **wajib punya SKU** (kode unik). Kosongkan kolom SKU saat menambah produk / import Excel, maka Dhyra membuatkannya:
  dari kategori (`Minuman` → `MNM-0001`) atau dengan awalan tetap (`DHY-0001`) — atur di *Pengaturan → SKU produk*.
- SKU bisa di-scan/diketik di kasir, mutasi, pesanan pembelian, dan retur; tampil di daftar produk, ekspor, dan template Excel.

## Pembelian

1. **Pesanan pembelian (PO)**: pilih pemasok, lokasi tujuan, produk & harga → simpan sebagai *Draf* → *Tandai dipesan* (bisa dicetak / dikirim lewat WhatsApp).
2. **Terima barang**: isi jumlah yang datang (boleh sebagian, sisanya ditunggu). Stok lokasi bertambah dan dicatat sebagai stok masuk;
   bayar **Tempo** → tagihan otomatis di Akuntansi (Hutang usaha), atau **Kas/Bank** langsung.
3. **Retur ke pemasok**: barang rusak/kedaluwarsa dikembalikan; pilih *potong tagihan* atau *uang kembali*. Stok dan jurnal ikut terkoreksi.
4. **Saran pembelian** menampilkan produk yang stok totalnya di bawah minimum, dikelompokkan per pemasok terakhir — sekali klik jadi draf PO.

## Member & poin

- Daftarkan member dari kasir (tombol **Member** di keranjang) atau dari modul Member, cukup nama + nomor HP.
- Poin dihitung dari total yang dibayar (atur di *Pengaturan → Program poin*), dan bisa **ditukar sebagai potongan** saat bayar.
  Transaksi yang dibatalkan otomatis mengembalikan poin.
- Daftar member bisa difilter: ulang tahun bulan ini, belum belanja > 30 hari, belanja terbanyak — lalu kirim pesan **WhatsApp** sekali klik.

## Promo

| Jenis | Contoh |
|---|---|
| Diskon produk | Air mineral diskon 10%, potongan Rp 500, atau harga promo Rp 3.000 (tampil sebagai harga coret di kasir) |
| Beli X gratis Y | Kopi beli 2 gratis 1 |
| Harga grosir | Beli ≥ 12 harga Rp 3.000/pcs, ≥ 24 harga Rp 2.800/pcs |
| Diskon belanja | Belanja min. Rp 50.000 potong Rp 5.000 (bisa khusus member) |

Promo bisa dibatasi tanggal, hari, jam (happy hour), kategori/produk tertentu, dan khusus member.
Kasir **tidak perlu menghitung**: bila beberapa promo cocok, dipilih yang paling hemat untuk pelanggan. Potongan promo tercatat sebagai *Potongan penjualan* di jurnal.

## Dashboard pemilik & laporan

- **Dashboard**: omzet, laba kotor, transaksi, rata-rata belanja, persentase belanja member (dibanding periode sebelumnya), peringatan stok, hutang jatuh tempo,
  PO terbuka, saldo kas & bank, grafik harian, omzet per lokasi, produk & member teratas, promo terlaris.
- **Laporan**: penjualan per hari/produk/kategori/kasir/lokasi/metode bayar/jam, pemakaian promo, belanja member, pembelian per pemasok,
  nilai persediaan per lokasi, produk tidak laku — semuanya bisa diunduh **Excel** atau **PDF**.

## Kartu stok

*Inventaris → Kartu stok* (atau tombol 📖 di daftar produk): riwayat masuk–keluar satu produk per lokasi — stok awal, stok masuk, penjualan,
batal, mutasi, retur, opname — dengan **saldo berjalan**, bisa diunduh Excel/PDF. Saldo akhir selalu sama dengan stok di sistem.

## Kartu member

*Member & CRM → Cetak kartu member* (semua) atau tombol *Kartu member* di detail member. Ukuran kartu ATM/KTP (85,6 × 54 mm),
depan berisi nama, nomor member, dan **barcode** (bisa di-scan di kasir), belakang berisi info toko. Pilih kertas A4 (10 kartu, tinggal
digunting & dilaminasi), printer kartu PVC, atau depan saja; warna toska, gelap, atau emas.

## Kategori produk (untuk berbagai jenis usaha)

*Inventaris → Produk → tombol **Kategori*** (hanya Administrator):
- **Tambah**, **ubah nama**, **urutkan** (▲▼) dan **hapus** kategori. Urutan ini dipakai di tombol kategori kasir dan toko online.
- **Template jenis usaha**: Restoran/Kafe, Warung, Minimarket, Toko Peralatan, Fashion, Elektronik/Konter HP, Apotek, Kosmetik, Bengkel.
  Kategori template *ditambahkan* ke daftar (yang lama tidak dihapus). Saat instalasi baru, jenis usaha bisa langsung dipilih.
- Menghapus kategori yang masih dipakai: pilih kategori tujuan, produknya dipindahkan otomatis.
- **Hapus kategori kosong** sekaligus membersihkan kategori template yang tidak dipakai.
- Di form produk ada tombol **+** di samping pilihan kategori untuk membuat kategori baru tanpa menutup form.
- Di layar kasir hanya kategori yang punya produk yang ditampilkan.

## Label barcode & harga produk

*Inventaris → Produk* → **Cetak label** (semua produk yang tampil sesuai filter), centang beberapa produk → **Cetak label**, atau ikon 🏷 di baris produk.
Bila ada produk tanpa barcode, muncul pengingat **"… produk belum punya barcode / label"** di atas daftar produk.
- Produk tanpa barcode bisa: **pakai SKU sebagai barcode** (langsung bisa discan di kasir), **dibuatkan barcode EAN-13 otomatis**
  (awalan 20 = kode internal toko, disimpan ke produk; hanya Administrator), atau dicetak tanpa barcode.
- Ukuran: stiker 50×30, 40×30, 38×25 mm, gulungan label harga **33×15 mm 3 kolom**, atau **A4 3×8**.
- Isi: nama toko, nama produk, harga jual, satuan, barcode, SKU (bisa dipilih). Jumlah label per produk bisa diatur, atau **sesuai stok**.

## Foto produk (maks. 8) & SKU di kasir

- Form produk: **Tambah foto** (bisa pilih beberapa sekaligus, maks. 8). Foto pertama = **foto utama** (kasir, katalog);
  geser urutan dengan ‹ ›, jadikan utama dengan ★, hapus dengan ✕.
- Toko online: pembeli bisa **menggeser (swipe)** foto, menekan panah, atau memilih thumbnail. Kartu produk menampilkan jumlah foto 📷.
- Foto disimpan di `uploads/produk/` (dikecilkan otomatis ±900 px). Foto yang dihapus/diganti ikut terhapus dari server.
- Kasir: SKU tampil di setiap kartu produk dan di keranjang; ketik/scan SKU lalu Enter langsung masuk keranjang.

## Inventaris aset

1. *Aset baru*: nama, kategori, lokasi, harga perolehan, tanggal, umur manfaat, nilai sisa, dan dari mana dibayar
   (Kas / Bank, atau **Sudah dimiliki** untuk peralatan lama — dicatat sebagai tambahan modal beserta akumulasi penyusutannya).
2. **Kondisi** (Baik / Perlu perbaikan / Rusak / Hilang) dan **perbaikan** (biaya → Beban perbaikan) tercatat di riwayat aset.
3. *Penyusutan*: setiap akhir bulan klik **Bukukan** — metode garis lurus, jurnal *Beban penyusutan ↔ Akumulasi penyusutan*.
4. *Lepas / jual*: aset keluar dari daftar, laba/rugi pelepasan dihitung otomatis dari nilai buku.

### Label barcode aset
*Daftar aset* → centang aset (atau tanpa centang = semua yang tampil sesuai filter) → **Cetak label**. Bisa juga dari detail aset → **Label**.
- Ukuran: stiker **50×30**, **40×30**, **60×40 mm** (printer label thermal gulungan) atau **A4 3×8** (24 label; bisa melewati label yang sudah terpakai).
- Isi label: nama toko, nama aset, barcode (kode aset), lokasi, tanggal perolehan, tulisan "Milik toko · jangan dilepas" — bisa dipilih.
- Printer label: pilih ukuran kertas yang sama di dialog cetak, skala 100%, margin None.
- **Audit / stock opname aset**: scan label di kolom pencarian *Daftar aset* lalu Enter → detail aset langsung terbuka.

### Import aset dari Excel
*Inventaris Aset → Import Excel* → **Unduh template** (berisi 5 contoh + petunjuk) → isi → pilih file → periksa pratinjau → **Import**.
- Wajib: Nama aset, Tanggal perolehan, Harga perolehan. Kode yang sudah ada dilewati.
- Peralatan lama: isi *Sumber dana* = **Sudah dimiliki** dan kosongkan *Akumulasi penyusutan awal* — penyusutan sampai bulan lalu dihitung otomatis
  dan dicatat sebagai modal, jadi tidak membebani laba bulan ini. Pakai *Kas* / *Bank* hanya untuk aset yang baru dibeli.
- Semua baris diperiksa dulu; bila ada yang salah, tidak ada yang tersimpan dan baris bermasalah ditunjukkan.
- Tombol **Isi 5 data contoh** tersedia di halaman Aset (saat masih kosong) dan di jendela import.

## SDM & penggajian

**Persiapan (sekali):**
1. *SDM → Titik absen & aturan*: buka halaman ini **di toko** lalu klik *Pakai lokasi saya* → *Simpan* (atau isi koordinat dari Google Maps). Radius bawaan 100 m.
2. *Pengguna & Akses*: buat akun login untuk setiap karyawan (jabatan Kasir / Staf Gudang / dll.).
3. *SDM → Karyawan*: tambah karyawan, **hubungkan akun login**, pilih shift, hari kerja, gaji pokok/upah harian, tunjangan, uang makan, upah lembur, denda telat, template KPI.

**Akun login massal (tanpa input satu per satu):**
- *SDM → Karyawan → Buat akun login*: semua karyawan yang belum punya akun tampil dengan username (dari nama) dan jabatan (dari posisi)
  yang sudah disarankan → centang → **Buat akun terpilih**.
- Import Excel karyawan: isi kolom *Username login* (+ *Jabatan login*, *Password awal*) — akun yang belum ada langsung dibuatkan dan dihubungkan.
- *Pengguna & Akses → Import Excel*: import daftar akun (Username, Nama, Jabatan, Password, Email, Status); akun baru otomatis
  terhubung ke karyawan bernama sama.
- Password yang dikosongkan dibuatkan otomatis. Setelah dibuat, daftar username & password tampil **sekali** — **Cetak kartu akun**
  (2 kolom per A4, berisi alamat login & absen), **Unduh daftar**, atau kirim **WA** ke tiap karyawan. Minta karyawan mengganti password setelah login pertama.
- Jabatan baru **Karyawan (absen saja)**: hanya bisa absen, ajukan izin/cuti/lembur, serta melihat slip gaji & KPI — cocok untuk karyawan yang tidak memegang kasir/gudang.

**Import karyawan dari Excel:** *SDM → Karyawan → Import Excel* → unduh template (5 contoh) → isi → import. Karyawan dengan kode yang sama
diperbarui, yang baru ditambahkan. Kolom *Username login* menghubungkan ke akun Pengguna; *Hari kerja* bisa ditulis Sen-Sab, Sel-Min, atau 123456.
Ada juga tombol **Isi 5 data contoh**.

**Sehari-hari:**
- Karyawan membuka **absen.html** (atau tombol *Absen saya*) di HP → foto selfie → absen masuk / pulang. Absen ditolak bila di luar radius toko.
  Keterlambatan dihitung dari jam shift + toleransi.
- Izin, sakit (bisa lampir surat dokter), cuti, dan lembur diajukan dari HP; atasan menyetujui di *SDM → Izin, cuti & lembur*. Sisa cuti terpotong otomatis.
- *Jadwal & shift*: ubah jadwal hari tertentu (tukar shift, libur). Absen manual untuk karyawan yang lupa / HP rusak.

**Gajian (akhir bulan):**
1. *Penggajian → Hitung gaji*: otomatis dari kehadiran, lembur, telat, alpa, izin, bonus KPI, dan cicilan kasbon. Tambahkan bonus / potongan lain bila perlu.
2. **Finalkan** → jurnal *Beban gaji* (Dr) ↔ *Hutang gaji* & *Piutang karyawan/kasbon* (Cr).
3. **Bayar** → *Hutang gaji* ↔ *Kas/Bank*. Slip bisa dicetak (A5) dan dilihat karyawan di HP.

> Kamera & GPS di HP hanya bekerja bila Dhyra dibuka lewat **https://** (aktifkan SSL gratis di hosting). Di XAMPP, uji dari komputer yang sama (localhost).
> Hari sebelum tanggal *Absensi mulai dihitung sejak* tidak dianggap alpa.

### Import jadwal shift dari Excel
*SDM → Jadwal & shift* → **Import Excel** (atau **Unduh jadwal Excel** untuk sekadar melihat/mencetak).
1. Pilih periode (minggu yang tampil atau satu bulan) → **Unduh template**. File berisi semua karyawan aktif dan jadwal yang berlaku saat ini,
   lengkap dengan pilihan shift (dropdown) di setiap sel.
2. Ubah sel: nama shift (**Pagi**, **Siang**, …), **Libur**, atau **Pola** (kembali ke pola mingguan). Boleh singkatan (P / S) atau jam (08:00-16:00).
   Sel kosong = tidak diubah. Baris karyawan yang tidak perlu diubah boleh dihapus.
3. Pilih file → periksa pratinjau berwarna → **Import**. Bila ada shift yang tidak dikenal, tidak ada yang tersimpan dan selnya ditandai merah.
- Sel yang sama dengan pola mingguan karyawan tidak dicatat sebagai jadwal khusus, jadi perubahan pola di menu Karyawan tetap berlaku.
- Maksimal 62 hari per file. Karyawan dicocokkan dari kolom Kode, lalu Nama.

## Atasan & persetujuan (izin, sakit, cuti, lembur, izin akses)

1. *SDM → Karyawan → ubah karyawan* → isi **Atasan langsung** (atau kolom *Atasan langsung* di Excel karyawan, berisi kode/nama atasan).
2. Karyawan mengajukan dari HP: **Absen saya → Izin & cuti** (Izin, Sakit, Cuti, Lembur, Akses).
3. Atasan mendapat notifikasi WhatsApp (bila gateway WA & No. HP diisi) dan menyetujui dari HP-nya di **Absen saya → Tim saya**.
   Karyawan tanpa atasan disetujui oleh bagian SDM / pemilik di *SDM → Izin, cuti & lembur*.
4. Pemohon tidak bisa menyetujui pengajuannya sendiri; atasan hanya bisa memproses anggota timnya. Administrator dan jabatan dengan akses SDM *Kelola* bisa memproses semuanya.
5. Pengajuan yang sudah disetujui bisa **Dibatalkan** dari menu SDM (mis. cuti tidak jadi) — jadwal pengganti ikut dihapus.

## Lembur (semua posisi: kasir, gudang, akuntan, dll.)

- **Diajukan karyawan**: tanggal + jam mulai–selesai + pekerjaan → disetujui atasan.
- **Perintah lembur**: atasan (Tim saya → *Perintah lembur*) atau SDM (*Izin, cuti & lembur → Perintah lembur*) — langsung disetujui, karyawan diberi tahu lewat WA.
- Karyawan tetap **absen masuk & pulang**. Upah lembur = jam yang benar-benar dijalani (irisan jam disetujui dengan jam absen) × upah lembur per jam.
  Contoh: disetujui 16.00–19.00, absen pulang 18.30 → dibayar 2,5 jam. Bisa diubah menjadi "dibayar penuh" di *Aturan absen & akses*.
- Lembur yang disetujui otomatis **membuka akses aplikasi** pada jam tersebut (lihat di bawah).

## Karyawan pengganti (backup) saat cuti / izin / sakit

- Karyawan boleh mengusulkan rekan pengganti saat mengajukan; atasan / SDM bisa menggantinya saat menyetujui. Sebelum disetujui tampil rencana per tanggal:
  - **Masuk (hari libur)** — pengganti masuk di hari liburnya.
  - **Tukar shift** — pengganti pindah ke shift pemohon (shift lamanya jadi kosong).
  - **Dilewati** — pengganti sudah masuk shift yang sama atau sedang cuti/izin.
- Setelah disetujui, jadwal pengganti otomatis dibuat (tanda **↔ ganti …** di Jadwal & shift dan di Absen saya). Pengganti absen seperti biasa; pemohon tercatat Cuti/Izin/Sakit.
- Upah: karyawan **harian** otomatis dibayar karena terhitung hadir. Karyawan **bulanan** yang masuk di hari liburnya mendapat tambahan **upah 1 hari**
  (gaji pokok ÷ hari kerja per bulan) per hari — bisa dimatikan di *Aturan absen & akses*.

## Akses aplikasi sesuai jam kerja (anti buka kasir di luar jam)

Rekomendasi pengaturan (*SDM → Aturan absen & akses*):
1. Nyalakan **Batasi akses sesuai jadwal shift**. Toleransi bawaan: boleh dibuka 30 menit sebelum shift dan 60 menit setelah shift (untuk tutup kasir/rekap).
2. Nyalakan **Wajib absen masuk dulu** agar kasir hanya bisa dipakai oleh karyawan yang benar-benar sudah di toko (selfie + GPS).
3. Bebaskan hanya jabatan yang perlu (bawaan: Pemilik). Administrator selalu bebas.
4. Pastikan semua akun karyawan **terhubung ke data karyawan** — akun yang tidak terhubung tidak dibatasi (daftarnya ditampilkan di halaman aturan).
5. Butuh bekerja di luar jam? Karyawan mengajukan **Lembur** (dibayar) atau **Izin akses** (tanpa upah, mis. rekap/terima barang) → atasan menyetujui → akses terbuka hanya pada jam itu.

Yang terjadi bila dibuka di luar jam: layar **"Di luar jam kerja"** dengan jadwalnya dan tombol untuk mengajukan lembur / izin akses.
*Absen saya* tetap bisa dibuka kapan saja. Setiap percobaan tercatat di *Percobaan akses di luar jam kerja* (nama, waktu, modul).
Penjualan **kasir offline** yang terjadi di luar jam tetap disimpan (uangnya nyata) tetapi ikut tercatat di daftar itu untuk diperiksa.
Berlaku untuk Kasir, Inventaris, Pembelian, Member, Promo, Laporan, Aset, SDM, KPI, Toko Online, dan Akuntansi.

## HP karyawan: hanya Absen saya

Bawaan aktif: bila akun karyawan dibuka dari **HP**, hanya halaman **Absen saya** yang bisa dipakai (absen selfie + GPS, izin/cuti/lembur,
izin akses, slip gaji, KPI, dan Tim saya untuk atasan). Aplikasi Dhyra yang dipasang di HP karyawan langsung membuka halaman absen.
Kasir, gudang, akuntansi, dan menu lain menampilkan layar **"Khusus komputer / tablet kasir"**.
- Tablet dan komputer tidak terpengaruh — tablet kasir tetap bisa dipakai.
- Administrator dan Pemilik tetap bisa membuka semua menu dari HP (untuk memantau).
- Toko yang memakai **HP sebagai mesin kasir**: *SDM → Aturan absen & akses → Akses dari HP* → centang jabatan **Kasir**.
- HP dikenali dari jenis perangkat dan ukuran layar, jadi tetap terkunci walau browser HP diubah ke "mode desktop".

## Reset password karyawan

Password tidak bisa dilihat lagi (disimpan terenkripsi). Bila karyawan lupa: *Pengguna & Akses* → ikon 🔑 di baris pengguna, atau centang
beberapa pengguna → **Reset password**. Dhyra membuat password sementara baru dan menampilkan kartu akun (cetak / CSV / WhatsApp).
Password lama langsung tidak berlaku, pengguna keluar dari semua perangkat, dan saat login berikutnya diminta mengganti password.

## KPI karyawan

- Template per jabatan berisi indikator dengan **target** dan **bobot**. Sumber data otomatis: kehadiran (%), tepat waktu (%), omzet penjualan & jumlah transaksi
  (dari akun kasir karyawan), member baru; atau **penilaian manual** oleh atasan.
- Skor indikator = pencapaian ÷ target × 100 (maks. 120); skor akhir = rata-rata berbobot. Grade A ≥ 95, B ≥ 80, C ≥ 65, D di bawahnya.
- Bonus per grade otomatis masuk slip gaji bulan yang sama. Karyawan bisa melihat KPI-nya sendiri di halaman Absen.

### Import KPI dari Excel
**Template KPI** — *KPI → Template & bonus → Import Excel*. Satu baris = satu indikator: Nama template, Indikator, Sumber data
(Kehadiran, Tepat waktu, Omzet penjualan, Jumlah transaksi, Member baru, Penilaian atasan), Target, Bobot. Template bernama sama diperbarui;
nilai penilaian lama tetap tersambung bila nama indikatornya sama.

**Penilaian bulanan** — *KPI → Hasil KPI → Import nilai Excel*:
1. Pilih bulan → **Unduh template**. File berisi semua karyawan yang punya KPI, hanya indikator *Penilaian atasan*, plus nilai yang sudah ada.
2. Isi kolom **Nilai** (kuning) dan *Catatan* bila perlu. Nilai kosong = tidak diubah.
3. Pilih file → periksa pratinjau → **Import**. Indikator otomatis (kehadiran, omzet, dll.) tidak perlu diisi.
Bila ada baris salah (karyawan / indikator tidak dikenal), tidak ada yang tersimpan dan barisnya ditunjukkan.

## Reset data per menu

*Pengaturan → Reset data* (khusus Administrator) menghapus data per menu: Kasir, Toko Online, Inventaris (stok & riwayat / produk),
Pembelian, Member, Promo, Akuntansi (jurnal manual & faktur), Inventaris Aset, SDM (transaksi / data karyawan), KPI, dan Log.
Jurnal akuntansi yang terkait ikut dihapus sehingga neraca tetap seimbang; stok barang yang terjual bisa dikembalikan.
Wajib mengetik **RESET** + password, dan cadangan database diunduh otomatis lebih dulu. Pengguna, jabatan, pengaturan, akun
perkiraan, dan lokasi tidak ikut terhapus.

## Toko online

1. *Toko Online → Pengaturan toko*: centang **Toko online buka**, isi nomor WhatsApp penerima pesanan, lokasi stok, pilihan ongkir, rekening, COD.
2. Bagikan **link toko** (`https://domain-anda/toko.html`) ke pelanggan. Harga, promo, dan stok mengikuti data kasir; pelanggan yang nomornya
   terdaftar sebagai member otomatis mendapat harga member.
3. Pelanggan memesan → pesanan masuk ke *Pesanan online* dan pelanggan diarahkan mengirim pesanan ke WhatsApp toko.
4. Proses → kirim info pembayaran lewat tombol WhatsApp.
5. Begitu transfer masuk, klik **💰 Sudah dibayar** → langsung tercatat di Akuntansi (kas/bank bertambah, akun *2105 Uang Muka Pelanggan*).
6. **Selesai & catat penjualan**: stok berkurang, jurnal *Penjualan Online* (pendapatan, HPP, ongkir) tercatat — diambil dari uang muka bila sudah dibayar.
   COD / bayar di toko cukup langsung **Selesai** (uang dicatat masuk kas saat itu). Pesanan yang sudah dibayar lalu **dibatalkan**
   otomatis dicatat sebagai pengembalian dana. Semua jurnal ini muncul di Akuntansi dengan jenis **Penjualan Online**.
- Atur produk yang tampil dan deskripsinya di *Produk di toko*.

## Aplikasi HP

Buka **app.html** dari HP: Android/Chrome → *Pasang aplikasi*; iPhone/Safari → *Bagikan → Tambah ke Layar Utama*.
Dhyra tampil seperti aplikasi (ikon sendiri, layar penuh) tanpa Play Store. Butuh alamat **https://**.

## Printer struk (cetak langsung)

Menu **Kasir → Printer struk** — diatur **di setiap perangkat kasir** (tersimpan di perangkat itu):

| Cara | Cocok untuk |
|---|---|
| Dialog cetak browser | Semua printer yang sudah terpasang di komputer (cara lama) |
| Printer Bluetooth | Printer thermal Bluetooth 58/80 mm — Chrome di Android, Windows, Mac |
| Printer USB langsung | Printer thermal USB — Chrome / Edge di komputer |
| Printer USB-Serial / COM | Printer yang terbaca sebagai port COM |
| Aplikasi RawBT | HP / tablet Android: pasang RawBT dari Play Store, sambungkan printer di sana sekali |

Klik **Hubungkan printer** → pilih printer → **Cetak tes**. Bisa diatur: cetak otomatis setelah bayar, potong kertas,
buka laci uang saat bayar tunai, jumlah salinan, lebar 58/80 mm. Bila printer gagal, Dhyra otomatis memakai dialog cetak browser.
iPhone/iPad belum mendukung Bluetooth dari browser.

## Mode offline kasir

- Buka halaman Kasir **sekali saat online** di setiap perangkat — data produk, harga, dan promo harga coret tersimpan di perangkat.
- Bila internet putus, kasir tetap bisa berjualan; muncul penanda **Offline** di pojok layar. Halaman kasir tetap bisa dibuka ulang walau tanpa internet.
- Transaksi disimpan di perangkat dan **dikirim otomatis** saat koneksi kembali (tanpa dobel). Tanggal & jam transaksi tetap sesuai waktu jualan,
  harga yang dibayar pelanggan dipakai apa adanya, dan stok boleh minus sementara.
- Saat offline: member & tukar poin, promo beli X gratis Y / grosir / diskon belanja tidak dihitung (promo harga coret tetap berlaku).
- Ketuk penanda di pojok untuk melihat antrean, kirim sekarang, atau cetak ulang struk offline. Jangan hapus data browser sebelum antrean terkirim.

## Laporan harian WhatsApp

1. Daftar di **fonnte.com** (atau wablas.com), hubungkan nomor WhatsApp pengirim lewat scan QR, salin **token**.
2. **Pengaturan → Laporan WhatsApp**: centang *Kirim otomatis*, pilih gateway, tempel token, isi nomor tujuan (pisahkan dengan koma),
   jam kirim, dan isi laporan (penjualan & laba, terlaris, stok, kehadiran, pesanan online, hutang jatuh tempo, saldo kas & bank).
3. Klik **Kirim pesan tes**.
4. Pasang **Cron Job** di hosting setiap 15 menit (perintah / URL tercantum di halaman pengaturan) — hPanel: *Lanjutan → Cron Jobs*;
   cPanel: *Cron Jobs*. Tanpa cron, laporan tetap terkirim saat ada yang membuka Dhyra setelah jam kirim.
- Laporan dikirim sekali sehari. Bila gagal, dicoba lagi tiap 10 menit (maksimal 3 kali); status terakhir tampil di halaman pengaturan.
- Gunakan nomor WA khusus untuk gateway dan jangan dipakai untuk pesan massal, agar tidak diblokir WhatsApp.

## Integrasi otomatis Kasir → Akuntansi

| Kejadian di kasir | Jurnal otomatis |
|---|---|
| Penjualan (checkout) | Dr Kas/Bank (sesuai metode bayar) · Dr Potongan penjualan (diskon) · Cr Pendapatan penjualan · Cr PPN keluaran · Dr HPP · Cr Persediaan |
| Batal transaksi | Jurnal pembalik pada tanggal pembatalan (jurnal asli tetap ada sebagai jejak audit) |
| Retur ke pemasok | Dr Hutang usaha (potong tagihan) atau Dr Kas/Bank · Cr Persediaan |
| Promo & tukar poin | Masuk ke Potongan penjualan, sehingga pendapatan tetap tercatat bruto |
| Aset: perolehan / penyusutan / perbaikan / pelepasan | Dr Aset tetap · Cr Kas/Bank/Modal — Dr Beban penyusutan · Cr Akumulasi — Dr Beban perbaikan · Cr Kas — laba/rugi pelepasan otomatis |
| Kasbon / gaji final / bayar gaji | Dr Piutang karyawan · Cr Kas — Dr Beban gaji · Cr Hutang gaji & Piutang karyawan — Dr Hutang gaji · Cr Kas/Bank |
| Pesanan online selesai | Jurnal penjualan kasir biasa + Dr Kas · Cr Pendapatan ongkos kirim |
| Stok masuk dibayar **Kas/Bank** | Dr Persediaan · Cr Kas/Bank |
| Stok masuk secara **Hutang** | **Purchase Order** otomatis (Dr Persediaan · Cr Hutang usaha). Bayar dari menu *Purchase order* → *Catat pembayaran* |
| Stok opname / koreksi stok di edit produk | Dr/Cr Persediaan ↔ Selisih persediaan (5103) |
| Stok awal produk baru | Dr Persediaan · Cr Modal disetor (bisa diganti) |

- Akun yang dipakai bisa diubah di **Akuntansi → Pengaturan → Integrasi kasir → akuntansi**.
- Nilai persediaan dan HPP memakai **harga beli** produk saat transaksi.
- Jurnal hasil kasir dan PO hasil stok masuk **tidak bisa diedit/dihapus dari modul Akuntansi** supaya stok dan pembukuan tetap sinkron.
  Koreksi dilakukan dari kasir (batalkan transaksi / stok opname).
- Pemasok yang diketik di stok masuk otomatis ditambahkan ke *Pelanggan & pemasok*.
- **Kunci periode** di pengaturan akuntansi juga berlaku untuk transaksi kasir.

## Fitur foto di keranjang

- Foto produk tampil di setiap baris keranjang (produk tanpa foto memakai inisial nama).
- Saat produk diklik, fotonya "terbang" ke keranjang; di HP, ke bilah keranjang di bawah layar
  yang juga menampilkan jumlah item dan foto produk terakhir.
- Foto diunggah dari menu **Inventaris → Produk → Ubah produk**, otomatis dikecilkan, dan disimpan di folder `uploads/produk/`.

## Import produk dari Excel

Menu **Inventaris → Produk → Import Excel** (level Manajer). Stok hasil import masuk ke lokasi yang sedang aktif:
1. Klik **Unduh template Excel**, isi data mulai baris ke-2 (wajib: Nama & Harga Jual).
2. Pilih file **.xlsx** atau **.csv** (file .xls lama: *Save As* ke .xlsx dulu).
3. Periksa pratinjau: baris baru, diperbarui, dan bermasalah ditandai sebelum disimpan.
4. Produk yang sudah ada dicocokkan dari barcode lalu nama; pilih apakah stoknya ikut diperbarui.

Judul kolom tidak harus persis template (mis. "Nama Produk", "Kode", "Harga Modal" dikenali), dan file ekspor produk dari aplikasi ini bisa diimpor kembali.

## Cadangan data

- **Akuntansi → Pengaturan → Unduh cadangan (.sql)** (jabatan Administrator) — file bisa diimpor ulang lewat phpMyAdmin.
- Salin juga folder `uploads/produk/` (foto produk) bila pindah hosting.
- Hostinger dan Rumahweb juga menyediakan backup otomatis di panel masing-masing.

## Struktur folder

```
beranda.html       ← login + peluncur modul
kasir.html  inventaris.html  pembelian.html  member.html  promo.html  laporan.html
online.html  aset.html  sdm.html  kpi.html   ← modul (satu tampilan, isi sesuai hak akses)
akuntansi.html  pengguna.html  pengaturan.html
absen.html         ← absen & layanan karyawan (HP)
toko.html          ← halaman toko online untuk pelanggan (tanpa login)
app.html  manifest.webmanifest  sw.js   ← aplikasi HP (PWA) & mode offline
cron.php           ← tugas terjadwal (laporan WhatsApp) — pasang di Cron Jobs hosting
api.php            ← satu pintu API (JSON)
install.php        ← wizard instalasi (HAPUS setelah instal)
assets/            ← logo, ikon, gaya, dan skrip bersama
app/               ← kode server + config.php (diblokir dari browser)
uploads/           ← foto produk, aset, selfie absen, lampiran izin
```

## Pemecahan masalah

- **"Aplikasi belum diinstal"** → jalankan `install.php`.
- **"Kesalahan database"** → periksa data di `app/config.php` (host, nama DB, user, password).
  Untuk melihat pesan lengkap sementara, ubah `'debug' => false` menjadi `true`, lalu kembalikan.
- **Foto gagal disimpan** → ubah izin folder `uploads` dan `uploads/produk` menjadi 755.
- **Absen: "Lokasi HP tidak terbaca" / kamera tidak muncul** → buka lewat https://, izinkan Lokasi & Kamera untuk situs ini di pengaturan browser.
- **Absen ditolak padahal di toko** → periksa titik absen (koordinat) dan perbesar radius di *SDM → Titik absen & aturan*.
- **Tampilan belum berubah setelah upgrade** → tekan Ctrl+F5.
- **Grafik dashboard tidak muncul** → grafik memakai pustaka Chart.js dari internet; pastikan komputer kasir/pemilik tersambung internet.
- **PDF** dibuat lewat dialog cetak browser: pilih tujuan **Simpan sebagai PDF**.
- **Modul tidak muncul di Beranda** → cek jabatan pengguna di *Pengguna & Hak Akses*; level modul itu mungkin "Tidak ada".
- **Lupa password admin** → di phpMyAdmin jalankan:
  `UPDATE users SET pass_hash='$2y$10$XDEYC2faStV1QLA9/ASewOom1A/B2Fn5HBkcC35fXuul02RLf6QKW', status='aktif' WHERE username='admin';`
  lalu masuk dengan password `admin123` dan segera ganti.
