# Dhyra SaaS: panduan pasang & kelola (v5.6.2)

Mode SaaS membuat satu server Dhyra melayani banyak toko. Setiap toko:

- **berlangganan** dan bisa mendaftar sendiri;
- punya **alamat sendiri**, misalnya `warungbusri.dhyra.id`;
- punya **database sendiri**, sehingga data antar-toko tidak pernah tercampur.

Anda sebagai pemilik layanan mengatur semuanya dari **Panel Pengelola** (`dhyra.id/pusat.html`).

Mode satu toko (dipasang di hosting milik toko) tetap tersedia seperti sebelumnya. Pilih modenya saat menjalankan `install.php`.

---

## 1. Cara kerja singkat

| Alamat | Isi |
|---|---|
| `dhyra.id` | Halaman depan + harga + **pendaftaran toko** (`daftar.html`) |
| `dhyra.id/pusat.html` | **Panel Pengelola** (khusus Anda) |
| `namatoko.dhyra.id` | Aplikasi Dhyra milik toko tersebut |

- **Database pusat** (`dhyra_pusat`) menyimpan daftar toko, paket, tagihan, voucher, dan pengumuman.
- **Database toko** (`dhyra_t_namatoko`) menyimpan semua data toko: produk, transaksi, karyawan, akuntansi.
- **Foto** tiap toko disimpan di `uploads/t/namatoko/`. **Bukti transfer** disimpan di `app/data/bukti/` dan tidak bisa dibuka dari internet.

---

## 2. Yang perlu disiapkan

1. **Domain**, misalnya `dhyra.id` (perlu KTP) atau `dhyra.com`.
2. **VPS Linux** (Ubuntu 24.04), minimal 2 CPU dan 4 GB RAM, sebaiknya dengan lokasi server di Jakarta. Contohnya Hostinger KVM 2 atau IDCloudHost. Spesifikasi ini cukup untuk puluhan toko kecil, dan bisa dinaikkan nanti.
3. **Akun Cloudflare** (gratis), untuk DNS dan SSL semua subdomain.
4. **Akun gateway WhatsApp**: Fonnte (fonnte.com) atau Wablas. Dipakai untuk kode verifikasi pendaftaran, pengingat tagihan, dan notifikasi.

> Shared hosting **tidak cocok** untuk mode SaaS. Alasannya: jumlah database biasanya dibatasi, user database tidak boleh membuat database baru, dan subdomain wildcard sering tidak didukung.

---

## 3. Arahkan domain (Cloudflare)

1. Tambahkan domain ke Cloudflare, lalu ganti nameserver di tempat Anda membeli domain sesuai petunjuk Cloudflare.
2. Di **DNS → Records**, buat dua record yang mengarah ke IP VPS Anda, keduanya dengan status *Proxied* (awan oranye):

   | Type | Name | Content |
   |---|---|---|
   | A | `@` | IP VPS |
   | A | `*` | IP VPS |

3. Di **SSL/TLS**, pilih mode **Full (strict)**.
4. Di **SSL/TLS → Origin Server → Create Certificate**, isi hostname `dhyra.id, *.dhyra.id`, lalu simpan *Origin Certificate* dan *Private Key*. Keduanya dipakai di langkah 4.

---

## 4. Pasang server (sekali saja)

Masuk ke VPS lewat SSH (`ssh root@IP-VPS`), lalu jalankan:

```bash
apt update && apt -y upgrade
apt -y install nginx mariadb-server php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-curl php8.3-gd php8.3-xml php8.3-zip unzip
mysql_secure_installation          # buat password root MariaDB, jawab Y untuk semua
```

**User database.** User ini hanya boleh mengelola database yang namanya diawali `dhyra_`:

```bash
mysql -e "CREATE USER 'dhyra'@'localhost' IDENTIFIED BY 'GANTI-PASSWORD-KUAT';
GRANT ALL PRIVILEGES ON \`dhyra\\_%\`.* TO 'dhyra'@'localhost'; FLUSH PRIVILEGES;"
```

**Upload aplikasi** ke `/var/www/dhyra`, misalnya dengan WinSCP/FileZilla atau `scp dhyra-v5.6.2.zip root@IP:/var/www/`:

```bash
mkdir -p /var/www/dhyra && cd /var/www/dhyra && unzip ../dhyra-v5.6.2.zip
mkdir -p uploads/t uploads/pusat app/data
chown -R www-data:www-data /var/www/dhyra
```

**Sertifikat SSL dari Cloudflare.** Tempel isi *Origin Certificate* dan *Private Key* dari langkah 3:

```bash
mkdir -p /etc/ssl/dhyra
nano /etc/ssl/dhyra/cert.pem       # tempel Origin Certificate
nano /etc/ssl/dhyra/key.pem        # tempel Private Key
```

**Konfigurasi Nginx.** Buat file `/etc/nginx/sites-available/dhyra`, ganti `dhyra.id` dengan domain Anda:

```nginx
server {
    listen 80;
    listen 443 ssl http2;
    server_name dhyra.id *.dhyra.id;
    root /var/www/dhyra;
    index index.php index.html;

    ssl_certificate     /etc/ssl/dhyra/cert.pem;
    ssl_certificate_key /etc/ssl/dhyra/key.pem;
    client_max_body_size 20M;

    location ^~ /app/      { deny all; }          # konfigurasi, kode, bukti transfer
    location ~* \.(sql|md)$ { deny all; }
    location ~* ^/uploads/.*\.(php|phtml|phar)$ { deny all; }   # skrip tidak boleh jalan dari folder unggahan

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location / { try_files $uri $uri/ =404; }
}
```

Aktifkan konfigurasinya:

```bash
ln -s /etc/nginx/sites-available/dhyra /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

---

## 5. Instalasi Dhyra (mode SaaS)

1. Buka `https://dhyra.id/install.php`.
2. Pilih **SaaS (banyak toko)**, lalu isi formulirnya:
   - **Database**: host `localhost`, user `dhyra`, password dari langkah 4.
   - **Database pusat**: `dhyra_pusat`. **Awalan database toko**: `dhyra_t_`.
   - **Domain utama**: `dhyra.id`. **Protokol**: `https`.
   - **Akun pengelola**: username dan password minimal 8 karakter. Akun ini untuk Anda, bukan untuk toko.
3. Klik **Instal sekarang**, lalu **hapus `install.php`** dari server:

   ```bash
   rm /var/www/dhyra/install.php
   ```

4. Pasang **cron** agar pengingat tagihan dan laporan WhatsApp berjalan otomatis. Jalankan `crontab -e`, lalu tambahkan baris:

   ```
   */15 * * * * php /var/www/dhyra/cron.php >/dev/null 2>&1
   ```

5. **Cadangan harian** semua database. Simpan sebagai `/etc/cron.daily/dhyra-backup`, lalu jalankan `chmod +x` pada file itu:

   ```bash
   #!/bin/sh
   D=/root/backup/$(date +%F); mkdir -p $D
   for db in $(mysql -N -e "SHOW DATABASES LIKE 'dhyra\_%'"); do mysqldump --single-transaction $db | gzip > $D/$db.sql.gz; done
   tar czf $D/uploads.tgz -C /var/www/dhyra uploads app/data
   find /root/backup -maxdepth 1 -mtime +14 -exec rm -rf {} \;
   ```

   Sebaiknya salin folder `/root/backup` ke tempat lain secara berkala, misalnya Google Drive dengan `rclone`.

---

## 6. Panel Pengelola (`dhyra.id/pusat.html`)

| Menu | Kegunaan |
|---|---|
| **Ringkasan** | Jumlah toko berbayar & masa coba, pendapatan bulanan (MRR), uang masuk bulan ini, konversi coba → bayar, grafik 12 bulan, toko yang segera berakhir, pendaftar terbaru. |
| **Toko** | Cari & saring toko. Klik satu toko untuk melihat pemakaian (outlet, pengguna, produk, transaksi, omzet 30 hari) dan riwayatnya. Aksi yang tersedia ada di tabel di bawah. |
| **Tagihan** | Tab *Perlu diperiksa* berisi bukti transfer yang masuk. Pilih **Lihat → Konfirmasi lunas** (masa aktif langsung diperpanjang dan toko mendapat WA), atau **Tolak** dengan alasan (toko diminta unggah ulang). Bisa juga membuat tagihan manual, langsung lunas bila sudah dibayar. |
| **Paket & harga** | **Harga bulanan dan tahunan bebas diatur**, misalnya Basic Rp60.000. Atur juga **menu yang terbuka** per paket (centang Kasir, Inventaris, Akuntansi, SDM, dan seterusnya), dan **batas jumlah**: outlet, pengguna aktif, produk (0 = tanpa batas). Perubahan langsung berlaku untuk semua toko di paket itu. Paket bisa ditambah, dinonaktifkan, atau ditandai "Paling populer". |
| **Voucher** | Kode potongan persen atau rupiah, dengan kuota, tanggal berlaku, paket tertentu, dan minimal lama langganan. |
| **Pengumuman** | Tampil di Beranda toko. Bisa ditujukan ke semua toko, toko masa coba, toko berbayar, atau paket tertentu, dengan jadwal tayang. |
| **Pengaturan** | Nama layanan, nomor WA, lama masa coba & paketnya, masa tenggang, hari pengingat, rekening & gambar QRIS, token WhatsApp (dengan tombol kirim tes), buka/tutup pendaftaran, wajib verifikasi WA, program referral, info cron, dan tambah pengelola lain. |
| **Log** | Semua tindakan pengelola dan kejadian penting (pembayaran, pembekuan, masuk sebagai toko, pengingat terkirim). |

Aksi yang tersedia di detail toko:

- ubah data atau paket;
- tambah atau kurangi masa aktif;
- buat tagihan;
- **Masuk sebagai toko** untuk membantu pelanggan. Toko melihat penanda "Mode dukungan", dan tindakannya tercatat di log toko.
- **Bekukan**: semua pengguna toko langsung keluar;
- **Hapus permanen**: harus mengetik alamat toko untuk konfirmasi.

---

## 7. Alur pelanggan

1. Calon pelanggan membuka `dhyra.id`, melihat harga, lalu klik **Coba gratis**.
2. Ia mengisi formulir pendaftaran:
   - nama toko;
   - alamat toko (dicek otomatis apakah masih tersedia);
   - jenis usaha (kategori produk terisi otomatis);
   - data pemilik, nomor WA, username & password admin;
   - kode referral (opsional).
3. Ia memasukkan **kode verifikasi WhatsApp**. Toko langsung dibuat dan ia **masuk otomatis**. Beranda menampilkan langkah awal: profil, produk, akun kasir, transaksi pertama.
4. Selama **masa coba**, semua menu paket masa coba terbuka. Beranda menampilkan sisa hari.
5. Sebelum masa coba atau langganan berakhir, admin toko membuka menu **Langganan** dan:
   - memilih paket dan lama langganan (1, 3, 6, atau 12 bulan);
   - memasukkan voucher bila punya;
   - membuat tagihan;
   - **transfer atau scan QRIS**;
   - **mengunggah bukti**.

   Anda menerima WA, lalu cukup klik konfirmasi di panel.
6. **Pengingat WA otomatis** dikirim H-7, H-3, dan H-1 (bisa diatur). Lewat tanggal berakhir, toko masuk **masa tenggang**: masih bisa dipakai normal, dan Beranda menampilkan peringatan. Setelah tenggang habis, toko menjadi **baca-saja**: data aman dan laporan bisa dilihat, tetapi transaksi dan perubahan data dikunci sampai pembayaran dikonfirmasi. Login dan menu Langganan tetap bisa dibuka.
7. **Ganti paket.**
   - Saat naik paket, sisa hari paket lama dihitung menjadi hari tambahan di paket baru.
   - Bila membayar saat masih masa coba, langganan dimulai setelah masa coba habis, sehingga sisa hari coba tidak hilang.
   - Menu di luar paket tampil terkunci di Beranda dengan label "Paket Pro" / "Paket Bisnis", dan membukanya menampilkan ajakan upgrade.
   - Bila jumlah outlet, pengguna, atau produk sudah mencapai batas, penambahan baru ditolak dengan pesan yang jelas. Data lama yang sudah melebihi batas tetap aman.
8. **Referral.**
   - Setiap toko punya kode, misalnya `WARUNG37`, dan tautan daftar sendiri.
   - Toko baru yang memakai kode itu mendapat **diskon % di tagihan pertama**.
   - Toko pengajak mendapat **bonus hari** saat toko baru itu membayar pertama kali.

---

## 8. Upgrade versi

1. Cadangkan dulu database dan folder `uploads` serta `app/data` (lihat langkah 5.5).
2. Timpa semua file dengan versi baru, **kecuali `app/config.php`, folder `uploads/`, dan folder `app/data/`**.
3. Pembaruan struktur database berjalan otomatis: database pusat saat panel dibuka, dan database tiap toko saat toko itu dibuka.

---

## 9. Tips keamanan

- Pastikan `install.php` sudah dihapus. Biarkan `'debug' => false` di `app/config.php`.
- Gunakan password pengelola yang kuat. Tambahkan pengelola lain lewat **Pengaturan → Pengelola**, jangan berbagi akun.
- Jangan membuka port MariaDB (3306) ke internet. Bawaannya hanya bisa diakses dari localhost.
- Periksa **Log** di panel secara berkala.
