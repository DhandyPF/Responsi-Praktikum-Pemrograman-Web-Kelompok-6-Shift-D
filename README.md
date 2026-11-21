# NusaKarya — Tahap 3

Platform ekosistem pekerjaan lokal, marketplace jasa mikro, dan pelatihan talenta lokal. Tahap 3 menambah pesanan jasa, rating & ulasan, chat, dan notifikasi di atas fitur Tahap 1–2.

## Teknologi
- PHP Native 8.1+ (disarankan PHP 8.2+), PDO
- MySQL 8+ / MariaDB (XAMPP)
- HTML, Tailwind CSS CDN, CSS tambahan, JavaScript Vanilla
- Google Fonts: Plus Jakarta Sans dan Inter

## Instalasi XAMPP (instalasi baru)

1. Ekstrak proyek ke `C:\xampp\htdocs\nusakarya` (atau sesuaikan `BASE_URL` di `config/app.php`).
2. Jalankan Apache dan MySQL melalui XAMPP.
3. Buka phpMyAdmin → tab **Import** → pilih `database.sql` → **Import**. File ini sudah berisi seluruh skema dari Tahap 1 sampai Tahap 4.4 beserta data demo.
4. Periksa koneksi database di `config/koneksi.php` (default XAMPP: host `localhost`, user `root`, password kosong).
5. Salin `config/midtrans.local.php.example` menjadi `config/midtrans.local.php` dan isi kredensial Midtrans Sandbox. Jika memakai transfer manual, salin `config/manual-payment.php.example` menjadi `config/manual-payment.php` lalu isi rekening penerima. Jangan commit kedua file konfigurasi lokal tersebut.
6. Pastikan folder `uploads/portfolio` dan `uploads/payments` dapat ditulisi Apache. Jangan commit file upload pengguna ke GitHub.
7. Buka `http://localhost/nusakarya/`.

### Penting untuk database yang sudah berjalan

`database.sql` adalah **full schema untuk instalasi baru**, bukan skrip upgrade. Jangan import ulang ke database lama karena dapat bentrok dengan tabel/kolom dan berisiko pada data. Backup database lama terlebih dahulu dan gunakan migrasi yang sesuai dengan versi database yang sedang dipakai.

## Akun demo
Semua akun demo menggunakan kata sandi `password`:
- Admin: `admin@nusakarya.test`
- Penyedia: `usaha@nusakarya.test`
- Pekerja: `nadia@nusakarya.test`

## Fitur Tahap 3

**Pemetaan peran:** "pembeli" = akun role `penyedia` (Penyedia Usaha/UMKM), "pekerja" = akun role `pekerja`. Admin tidak ikut chat, notifikasi, atau memesan; admin hanya melihat ringkasan di dashboard/statistik. Tidak ada role baru, sehingga data dan registrasi Tahap 1–2 tidak berubah.

### 1. Pesanan jasa (marketplace)
- Pembeli membuka **Marketplace Jasa → Detail jasa → Pesan jasa ini** (catatan + tanggal dibutuhkan opsional).
- Nama jasa dan harga disimpan sebagai *snapshot* di pesanan, sehingga riwayat tetap utuh walau pekerja menghapus jasanya.
- Status: `Menunggu → Dikonfirmasi → Dikerjakan → Selesai`, dan `Dibatalkan`.

| Pelaku | Dari | Ke |
|---|---|---|
| Pekerja | Menunggu | Dikonfirmasi, Dibatalkan (tolak) |
| Pekerja | Dikonfirmasi | Dikerjakan, Dibatalkan |
| Pekerja | Dikerjakan | Selesai |
| Pembeli | Menunggu / Dikonfirmasi | Dibatalkan |
| — | Selesai / Dibatalkan | final (tidak dapat diubah) |

- Setiap perubahan dicatat di `riwayat_pesanan` (siapa, kapan, catatan) dan dikunci dengan transaksi `SELECT … FOR UPDATE`.
- Halaman: pembeli `penyedia/pesanan.php` & `penyedia/pesanan-detail.php`; pekerja `pekerja/pesanan.php` & `pekerja/pesanan-detail.php`.

### 2. Rating & ulasan
- Hanya pembeli pemilik pesanan, hanya saat status **Selesai**, rating 1–5 + komentar opsional.
- Satu pesanan satu ulasan: dicek di server dan dijamin `UNIQUE (pesanan_id)` di database.
- Rata-rata rating dan daftar ulasan tampil di **detail jasa**; rata-rata juga tampil pada kartu di daftar jasa dan dashboard pekerja.

### 3. Chat / Pesan
- Satu percakapan per pasangan pembeli–pekerja. Dimulai dari detail jasa ("Kirim pesan dulu") atau dari detail pesanan.
- Daftar percakapan (pesan terakhir, jumlah belum dibaca), riwayat pesan, dan status **✓ Terkirim / ✓✓ Dibaca**.
- Pembaruan hampir real-time lewat *polling* tiap 4 detik (`pesan/ambil.php`, `assets/js/chat.js`). Tanpa JavaScript, halaman tetap berfungsi dengan muat ulang.
- **Keamanan akses:** setiap query percakapan dibatasi pada dua peserta; selain peserta (termasuk admin) mendapat 404/403, baik untuk membuka, mengirim, maupun polling.

### 4. Notifikasi
- Dibuat otomatis untuk: pesan baru (digabung per percakapan selama belum dibaca), pesanan baru, perubahan status pesanan, dan ulasan baru.
- Halaman `notifikasi.php`: filter *Belum dibaca*, tombol **Tandai dibaca**, **Tandai semua sudah dibaca**, dan **Buka →** (menandai dibaca lalu menuju halaman terkait). Tautan hanya path internal (anti *open redirect*).
- Lencana jumlah belum dibaca muncul di navigasi dan dashboard.

### 5. Integrasi & navigasi
- Navigasi atas (sesuai role): Pekerja → *Pesanan Masuk, Pesan, Notifikasi*; Penyedia → *Pesanan Saya, Pesan, Notifikasi*. Admin tidak melihat menu tersebut.
- Dashboard pekerja: kartu pesanan menunggu/berjalan/selesai + rating rata-rata + menu Pesanan/Pesan/Notifikasi.
- Dashboard penyedia: pesanan menunggu/berjalan/menunggu ulasan + menu Pesanan/Cari jasa/Pesan/Notifikasi.
- Admin: total pesanan & ulasan di dashboard, rincian status pesanan di statistik.

### Keamanan (ringkas)
- PDO *prepared statements* di seluruh query baru; `ATTR_EMULATE_PREPARES=false` sehingga memakai placeholder posisi.
- CSRF token pada semua POST baru; semua aksi mengubah data hanya lewat POST.
- Otorisasi per baris: pesanan, percakapan, dan notifikasi selalu difilter dengan ID pengguna dari session, bukan dari input.
- Validasi server: status/transisi, rating 1–5, panjang teks, tanggal, role pengirim/penerima. Output di-escape `htmlspecialchars`; JavaScript chat memakai `textContent`.

### Perbaikan kecil pada kode lama (ditemukan saat audit)
- **Tahap 2 – Smart Job Matching:** skill dan deskripsi profil tidak pernah tersimpan di session sehingga skor skill/kategori/kata kunci selalu 0. Kini dimuat dari database di `pekerja/rekomendasi.php`.
- **Tahap 1 – Kelola Jasa:** kategori yang tidak valid memicu galat 500; kini divalidasi dan dialihkan ke `Lainnya`.

## Pengujian manual Tahap 3
1. Login **usaha@nusakarya.test** → *Marketplace Jasa* → buka "Desain UI Landing Page" → **Pesan jasa ini**.
2. Login **nadia@nusakarya.test** → lihat lencana Notifikasi → *Pesanan Masuk* → Konfirmasi → Dikerjakan → Selesai.
3. Kembali sebagai penyedia → lihat notifikasi status → buka pesanan → beri rating & ulasan → cek di halaman detail jasa.
4. Kirim pesan dari kedua sisi; periksa jumlah belum dibaca dan status ✓✓ Dibaca.
5. Daftarkan akun penyedia/pekerja kedua, lalu coba buka URL `pesan/chat.php?id=1` atau `penyedia/pesanan-detail.php?id=1` → harus 404.

## Batasan yang diketahui
- Chat memakai polling, bukan WebSocket. Cocok untuk XAMPP/PHP native, tetapi bukan *real-time* sejati.
- Pembayaran belum ada; harga pesanan = harga mulai jasa (disepakati lewat chat).
- Satu percakapan per pasangan pengguna (bukan per pesanan).
- Tidak ada pagination lanjutan: daftar dibatasi 100–200 baris terbaru.

## Fitur Tahap 2

### Portfolio pekerja
- Tambah, edit, dan hapus portfolio dengan judul, deskripsi, kategori, tautan, dan gambar.
- Validasi upload di server menggunakan `finfo` dan `getimagesize`; hanya JPEG, PNG, WebP, maksimal 3 MB dan dimensi maksimal 6000 × 6000.
- Nama file dibuat acak, dan folder upload menolak eksekusi skrip.

### Detail dan riwayat lamaran
- Pekerja dapat membuka detail setiap lamaran dari menu Lamaran Saya.
- Riwayat menyimpan status sebelumnya, status terbaru, catatan penyedia, pelaku perubahan, dan waktu.
- Perubahan status di halaman Penyedia > Kelola Kandidat dilakukan dalam transaksi dan hanya untuk lamaran milik penyedia tersebut.
- Riwayat awal untuk lamaran yang sudah ada dibuat saat migrasi.

### Smart Job Matching
Skor dihitung secara deterministik dengan bobot:
- Keterampilan: 45% (skill profil yang ditemukan pada judul, deskripsi, atau persyaratan lowongan).
- Lokasi: 20% (sama = 100; teks lokasi serupa/beririsan = 60; selainnya = 0).
- Kategori: 20% (kategori lowongan disebut pada deskripsi profil).
- Kata kunci deskripsi: 15% (kata bermakna dari deskripsi profil yang ditemukan pada informasi lowongan).

Skor ditampilkan bersama alasan dan rincian komponen. Semua lowongan aktif yang belum melewati batas lamaran dinilai, tidak hanya lowongan dengan lokasi identik. Untuk hasil terbaik, lengkapi skill, lokasi, dan deskripsi pada Profil Saya.

### Dashboard dan akses
- Dashboard pekerja memiliki akses Portfolio Saya, Lamaran Saya, rekomendasi, profil, dan kelola jasa.
- Halaman Tahap 2 membatasi akses berdasarkan role pekerja/penyedia menggunakan pemeriksaan login yang sudah ada.

## Pengujian singkat
1. Login sebagai pekerja, buka Dashboard > Portfolio Saya, tambah/edit/hapus karya.
2. Login sebagai pekerja dan kirim lamaran ke lowongan aktif. Buka Lamaran Saya > judul lowongan untuk melihat detail.
3. Login sebagai penyedia, buka Kelola Kandidat, ubah status dan tambahkan catatan.
4. Login kembali sebagai pekerja untuk memeriksa riwayat status dan catatan.
5. Lengkapi skill, lokasi, dan deskripsi profil pekerja, lalu buka Smart Job Matching untuk melihat skor serta alasannya.

## Catatan teknis
- Semua operasi database menggunakan PDO prepared statements.
- Form POST memakai CSRF token yang telah tersedia di Tahap 1.
- Output teks di-escape dengan `htmlspecialchars`.
- Jangan jalankan migrasi Tahap 2 berulang kali jika tabel sudah dibuat (walaupun perintah `CREATE TABLE IF NOT EXISTS` aman, backfill tidak menggandakan riwayat awal).
- Upload menerima file gambar saja, maksimum 3 MB; sesuaikan `upload_max_filesize` dan `post_max_size` di `php.ini` bila diperlukan.
- Tailwind CSS dan Google Fonts dimuat dari CDN, sehingga tampilan CDN memerlukan koneksi internet.


## Tahap 4 — Marketplace & transaksi
Source code tahap 4 menambahkan alur marketplace berbasis paket, penawaran custom, invoice, pembayaran manual, verifikasi admin, progres pekerjaan, pengiriman hasil, dan revisi dasar.

- Marketplace baru: `marketplace.php`
- Kelola jasa/paket pekerja: `pekerja/paket-jasa.php`
- Pesanan masuk pekerja: `pekerja/tahap4-pesanan.php`
- Pesanan pembeli: `penyedia/tahap4-pesanan.php`
- Pembayaran: `penyedia/pembayaran.php`
- Pengaturan komisi/verifikasi admin: `admin/tahap4-transaksi.php`

Untuk instalasi baru, seluruh skema database tersedia dalam satu file `database.sql`. Jangan impor file tersebut ke database lama yang sudah berisi data.


## Tahap 4.4 — Pembayaran dan pencairan

Untuk instalasi baru, fitur pembayaran Tahap 4.4 sudah tercakup dalam `database.sql`. Midtrans memerlukan kredensial di `config/midtrans.local.php` dan URL webhook HTTPS publik. Pencairan pekerja pada versi ini dicatat dan ditransfer manual oleh admin.

Untuk setup Midtrans, salin `config/midtrans.local.php.example` menjadi `config/midtrans.local.php`, isi Server Key dan Signature Secret Key sesuai environment Sandbox/Production, lalu daftarkan URL `pembayaran/midtrans-notification.php` sebagai HTTPS webhook di dashboard Midtrans. Uji transaksi di Sandbox terlebih dahulu.
