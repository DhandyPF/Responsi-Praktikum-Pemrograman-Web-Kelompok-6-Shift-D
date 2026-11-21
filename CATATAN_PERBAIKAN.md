# NusaKarya – perbaikan lanjutan

## Yang diperbaiki
- `config/database.php`: MySQLi prepared statement tidak lagi menyimpan `false` dari `get_result()` ke properti `?mysqli_result`; `fetch()` mengubah akhir hasil (`null`) menjadi `false`; operasi non-SELECT ditangani tanpa result set; jumlah parameter diperiksa; hasil lama dan statement ditutup.
- `database/migrasi_wallet.sql`: migrasi aditif untuk tabel `mutasi_dompet` dan `pencairan_pekerja` yang dipanggil oleh fitur dompet, tetapi tidak terdapat dalam dump `db_nusakarya.sql` yang diberikan.
- `pelatihan.php`: halaman daftar pelatihan ditambahkan karena header/footer/index menautkan ke file tersebut tetapi file tidak ada di arsip source.
- Tidak mengganti MySQLi menjadi PDO. Gaya PHP Native, HTML, CSS, JavaScript, dan CRUD dipertahankan mengikuti materi modul.

## Pemasangan
1. Backup folder proyek dan export database aktif terlebih dahulu.
2. Salin isi folder `nusakarya` dari ZIP ini ke `D:\Xampp\htdocs\nusakarya`.
3. Buka phpMyAdmin, pilih `db_nusakarya`, lalu tab Import dan jalankan `database/migrasi_wallet.sql`. Migrasi memakai `CREATE TABLE IF NOT EXISTS`, tidak menghapus tabel atau data lama.
4. Restart Apache dan MySQL.

## Hasil pemeriksaan lokal
- Seluruh file PHP diperiksa menggunakan `php -l` (lihat laporan di bawah).
- SQL dump dibandingkan dengan nama tabel yang dipanggil oleh source.
- Tidak ada koneksi ke MySQL XAMPP pengguna dalam lingkungan ini, jadi operasi database dan seluruh alur browser belum dapat dinyatakan lulus uji runtime.

## Catatan error.log yang diunggah
File `Pasted text.txt` yang tersedia berisi log startup Apache, peringatan sertifikat SSL contoh `www.example.com`, dan beberapa catatan shutdown tidak normal. File itu tidak memuat PHP Fatal error, stack trace, atau SQL exception. Jadi, untuk error runtime terbaru, gunakan `xampp/php/logs/php_error_log` (jika tersedia) atau aktifkan logging PHP, lalu kirim log setelah halaman error dibuka.
