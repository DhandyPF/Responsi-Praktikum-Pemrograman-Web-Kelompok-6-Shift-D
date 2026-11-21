# NusaKarya

**NusaKarya** adalah platform digital yang menghubungkan pencari kerja/talenta lokal dengan UMKM dan penyedia jasa. Aplikasi menyediakan informasi lowongan, pengelolaan lamaran, marketplace jasa, komunikasi, pelatihan, dan transaksi jasa.

## Latar Belakang

Talenta lokal dan UMKM membutuhkan akses yang lebih mudah untuk menemukan peluang kerja, menawarkan keterampilan, serta mengembangkan kemampuan digital. Informasi lowongan, jasa, dan pelatihan yang tersebar dapat menyulitkan proses pencarian dan kerja sama.

NusaKarya menggabungkan kebutuhan tersebut dalam satu aplikasi agar penyedia dapat mempublikasikan peluang dan jasa, sementara pekerja dapat membangun profil, melamar pekerjaan, menawarkan layanan, dan berkomunikasi dengan penyedia.

## Keterkaitan dengan SDGs

- **SDG 8 – Pekerjaan Layak dan Pertumbuhan Ekonomi:** NusaKarya mendukung akses terhadap informasi lowongan, peluang jasa/freelance, dan pelatihan keterampilan. Marketplace jasa juga membantu pekerja menawarkan kemampuan kepada UMKM. Aplikasi mendukung akses peluang; aplikasi ini tidak menjamin status kelayakan atau kondisi kerja dari setiap lowongan.
- **SDG 9 – Industri, Inovasi, dan Infrastruktur:** NusaKarya menyediakan sarana digital yang mempertemukan talenta dan UMKM, mendukung pemanfaatan teknologi untuk pemasaran jasa, pencarian kandidat, komunikasi, dan pengelolaan transaksi. Kontribusinya berada pada inovasi dan infrastruktur digital, bukan pembangunan infrastruktur fisik.

## Modul Logika Bisnis

Implementasi berikut merupakan modul yang sudah ada pada source code, bukan fitur tambahan yang dibuat khusus untuk dokumentasi.

1. **Autentikasi dan profil pengguna** — pendaftaran, login, logout, pengelolaan profil, dan pembagian hak akses admin, penyedia, serta pekerja.
2. **Manajemen lowongan** — penyedia membuat, melihat, memperbarui status, dan menghapus lowongan.
3. **Lamaran dan seleksi kandidat** — pekerja mengirim lamaran; penyedia melihat kandidat dan memperbarui status lamaran beserta riwayatnya.
4. **Portfolio pekerja** — pekerja menambahkan, mengubah, melihat, dan menghapus karya.
5. **Rekomendasi lowongan** — sistem mencocokkan keterampilan, lokasi, kategori, dan kata kunci profil dengan lowongan.
6. **Pelatihan dan badge** — pengguna melihat pelatihan dan memperoleh badge setelah menyelesaikannya sesuai alur aplikasi.
7. **Marketplace jasa dan paket** — pekerja mengelola jasa/paket; penyedia menelusuri dan memesan layanan.
8. **Chat dan penawaran custom** — penyedia mengirim permintaan, pekerja mengajukan harga, dan penyedia menyetujui penawaran melalui percakapan.
9. **Pesanan, pembayaran, dan pencairan** — pengelolaan progres pesanan, invoice, pembayaran, verifikasi, saldo pekerja, serta permintaan pencairan.

Fitur pendukung yang terintegrasi meliputi notifikasi, rating/ulasan, pengiriman hasil, revisi, dan pengelolaan transaksi oleh admin.

## Pemetaan Materi Praktikum Modul 1–6

| Modul praktikum | Penerapan pada NusaKarya |
|---|---|
| 1. HTML | Struktur dokumen HTML, heading, teks, tautan, gambar, dan daftar pada halaman aplikasi. |
| 2. HTML Lanjut | Elemen semantic seperti `header`, `nav`, `main`, `section`, dan `footer`; hyperlink; form input; serta tabel data. |
| 3. CSS | Stylesheet eksternal `assets/css/style.css`, selector tag/class/id, layout, warna, tipografi, jarak, border, dan styling form. |
| 4. JavaScript | Event klik dan input, filter daftar, toggle navigasi/tema, konfirmasi tindakan, kalkulasi estimasi upah, dan interaksi chat. |
| 5. PHP | Variabel, tipe data, array, operator, percabangan, perulangan, fungsi, `include/require`, pemrosesan form, dan validasi sisi server. |
| 6. PHP CRUD | Koneksi MySQLi dan operasi tambah, baca, ubah, serta hapus pada data aplikasi seperti lowongan, jasa, portfolio, paket, dan pengelolaan data terkait. |

## Teknologi

- PHP Native 8.1+ (disarankan PHP 8.2+)
- MySQL 8+ atau MariaDB melalui XAMPP
- MySQLi dan prepared statements
- HTML5, CSS, Tailwind CSS CDN, dan JavaScript Vanilla
- Midtrans Snap untuk pembayaran online (opsional, perlu kredensial akun sendiri)

## Struktur Folder

- `admin/` — halaman administrasi.
- `auth/` — autentikasi pengguna.
- `assets/` — CSS, JavaScript, dan aset tampilan.
- `config/` — konfigurasi aplikasi, koneksi database, fungsi, serta logika tahap aplikasi.
- `includes/` — komponen layout bersama.
- `pekerja/` — fitur untuk pekerja/talenta.
- `penyedia/` — fitur untuk penyedia/UMKM.
- `pesan/` — percakapan dan penawaran.
- `pembayaran/` — notifikasi pembayaran dari Midtrans.
- `uploads/` — lokasi penyimpanan berkas pengguna; isi unggahan tidak disertakan di repository.

## Instalasi Lokal dengan XAMPP

1. Pasang XAMPP dengan PHP 8.1+ dan MySQL/MariaDB.
2. Letakkan folder proyek di `C:\xampp\htdocs\nusakarya`.
3. Jalankan Apache dan MySQL dari XAMPP.
4. Buat database baru bernama `db_nusakarya`, lalu import `database.sql` pada database kosong. File tersebut adalah skema lengkap untuk instalasi baru. Jangan mengimpor ulang ke database yang sebelumnya sudah pernah diproses sebagian; jika database lama perlu dipertahankan, ekspor/backup dahulu dan lakukan migrasi terencana. Skrip menggunakan nama foreign key khusus `fk_nk_final_*` agar tidak bertabrakan dengan constraint bernama sama di tabel lain.
5. Periksa pengaturan koneksi pada `config/koneksi.php` (default XAMPP: host `localhost`, database `db_nusakarya`, user `root`, password kosong).
6. Periksa `BASE_URL` pada `config/app.php`; nilai default adalah `/nusakarya`.
7. Pastikan `uploads/portfolio` dan `uploads/payments` dapat ditulisi Apache. Pertahankan file `.htaccess` di dalam folder upload.
8. Buka `http://localhost/nusakarya/`.

### Konfigurasi pembayaran

- Untuk Midtrans, salin `config/midtrans.local.php.example` menjadi `config/midtrans.local.php`, lalu isi kredensial Sandbox milik sendiri.
- Untuk transfer manual, salin `config/manual-payment.php.example` menjadi `config/manual-payment.php`, lalu isi informasi rekening yang digunakan.
- Jangan commit file konfigurasi lokal yang berisi kredensial atau informasi rekening.
- Webhook Midtrans memerlukan URL HTTPS publik yang diatur pada dashboard Midtrans. Pengujian di localhost saja tidak dapat menerima notifikasi langsung dari Midtrans.

### Penting untuk database yang sudah berisi data

`database.sql` digunakan untuk **instalasi baru pada database kosong**. Jangan import ulang ke database proyek lama karena file ini membuat tabel dan menjalankan perubahan skema. Backup database sebelum melakukan upgrade; gunakan prosedur migrasi yang sesuai dengan versi database yang sudah berjalan.

## Akun Demo

Database menyertakan akun demo dengan kata sandi `password`:

- Admin: `admin@nusakarya.test`
- Penyedia: `usaha@nusakarya.test`
- Pekerja: `nadia@nusakarya.test`

Ganti atau hapus akun demo sebelum aplikasi dipublikasikan untuk penggunaan nyata.

## Pengujian Fitur

Gunakan akun demo untuk memeriksa alur utama:

1. Login sebagai penyedia, buat lowongan, lalu login sebagai pekerja untuk mengirim lamaran.
2. Login sebagai penyedia untuk memeriksa kandidat dan memperbarui status lamaran.
3. Login sebagai pekerja untuk mengelola profil, portfolio, jasa, dan paket.
4. Buka detail jasa sebagai penyedia, mulai percakapan, minta penawaran custom, lalu kirim penawaran harga dari akun pekerja.
5. Terima penawaran dari akun penyedia dan periksa invoice yang dibuat dari harga kesepakatan.
6. Uji pembayaran menggunakan Midtrans Sandbox atau alur transfer manual yang telah dikonfigurasi.
7. Periksa progres pesanan, pengiriman hasil, ulasan, saldo pekerja, dan proses pencairan sesuai status transaksi.

## Keamanan dan Catatan

- Query database menggunakan MySQLi prepared statements. Adapter di `config/database.php` mengubah placeholder bernama seperti `:email` menjadi placeholder `?` yang didukung MySQLi.
- Form yang mengubah data menggunakan token CSRF dan validasi server.
- Halaman dan data dibatasi berdasarkan role serta kepemilikan pengguna.
- Output teks di-escape sebelum ditampilkan.
- File unggahan dibatasi berdasarkan tipe dan ukuran serta disimpan pada folder upload yang dilindungi.
- Chat menggunakan polling berkala, bukan WebSocket.
- Pencairan saldo pekerja dilakukan secara manual oleh admin; bukan fitur payout otomatis Midtrans.
