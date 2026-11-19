# NusaKarya

Starter aplikasi ekosistem bursa kerja, marketplace jasa mikro, dan pelatihan talenta lokal.

## Teknologi
- PHP Native 8.1+ dan PDO
- MySQL 8+ / MariaDB
- Tailwind CSS CDN, CSS tambahan, JavaScript Vanilla
- Google Fonts: Plus Jakarta Sans dan Inter

## Instalasi XAMPP
1. Ekstrak folder `nusakarya` ke `C:\xampp\htdocs\nusakarya`.
2. Jalankan Apache dan MySQL.
3. Buka `http://localhost/phpmyadmin`, buat/import database dengan mengimpor `database.sql`.
4. Periksa `config/koneksi.php` (default XAMPP: user `root`, password kosong).
5. Periksa `config/app.php`; `BASE_URL` default `/nusakarya`.
6. Buka `http://localhost/nusakarya/`.

## Akun demo
Semua akun menggunakan kata sandi `password`:
- Admin: `admin@nusakarya.test`
- Penyedia: `usaha@nusakarya.test`
- Pekerja: `nadia@nusakarya.test`

## Catatan implementasi
- Login menggunakan `password_verify()`; registrasi memakai `password_hash()`.
- Query dinamis memakai PDO prepared statements.
- Form autentikasi memakai token CSRF dan session ID diregenerasi setelah login.
- Output database di-escape menggunakan `htmlspecialchars`.
- Katalog lowongan, jasa, dan pelatihan membaca data MySQL.
- Filter pencarian katalog dan kalkulator estimasi upah berjalan di sisi klien.
- `dashboard.php` adalah dashboard awal berbasis role. Folder `admin/`, `penyedia/`, dan `pekerja/` disiapkan untuk pengembangan CRUD modul lanjutan.

## Struktur
Lihat struktur folder pada jawaban pendamping. File dashboard lanjutan, CRUD penuh, kuis/badge, dan manajemen kandidat merupakan tahap implementasi berikutnya; skema database sudah disiapkan.
