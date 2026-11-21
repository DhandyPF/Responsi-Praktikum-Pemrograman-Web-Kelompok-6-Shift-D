-- NUSAKARYA - DATABASE LENGKAP UNTUK INSTALASI BARU
-- Berisi skema dasar dan seluruh tabel/perubahan Tahap 2 sampai Tahap 4.5.
-- Import satu kali pada database kosong melalui phpMyAdmin.

-- NusaKarya database
-- Import melalui phpMyAdmin / MySQL CLI.
CREATE DATABASE IF NOT EXISTS db_nusakarya
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_nusakarya;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','penyedia','pekerja') NOT NULL DEFAULT 'pekerja',
  telepon VARCHAR(25) DEFAULT NULL,
  lokasi VARCHAR(120) DEFAULT NULL,
  deskripsi TEXT DEFAULT NULL,
  skills TEXT DEFAULT NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_users_role (role),
  INDEX idx_users_lokasi (lokasi)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lowongan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  penyedia_id BIGINT UNSIGNED NOT NULL,
  judul VARCHAR(160) NOT NULL,
  kategori ENUM('Desain','Konten','Teknologi','Administrasi','Lainnya') NOT NULL DEFAULT 'Lainnya',
  jenis_pekerjaan VARCHAR(60) NOT NULL DEFAULT 'Penuh waktu',
  lokasi VARCHAR(120) NOT NULL,
  gaji_min DECIMAL(14,2) DEFAULT NULL,
  gaji_max DECIMAL(14,2) DEFAULT NULL,
  deskripsi TEXT NOT NULL,
  persyaratan TEXT DEFAULT NULL,
  batas_lamaran DATE DEFAULT NULL,
  status ENUM('Aktif','Ditutup') NOT NULL DEFAULT 'Aktif',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lowongan_penyedia FOREIGN KEY (penyedia_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_lowongan_filter (status, kategori, lokasi)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lamaran (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lowongan_id BIGINT UNSIGNED NOT NULL,
  pekerja_id BIGINT UNSIGNED NOT NULL,
  pesan_singkat TEXT DEFAULT NULL,
  status ENUM('Menunggu','Diproses','Diterima','Ditolak') NOT NULL DEFAULT 'Menunggu',
  tanggal_lamar TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lamaran_lowongan FOREIGN KEY (lowongan_id) REFERENCES lowongan(id) ON DELETE CASCADE,
  CONSTRAINT fk_lamaran_pekerja FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_lamaran (lowongan_id, pekerja_id),
  INDEX idx_lamaran_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS jasa (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pekerja_id BIGINT UNSIGNED NOT NULL,
  nama_jasa VARCHAR(160) NOT NULL,
  kategori ENUM('Desain','Konten','Teknologi','Administrasi','Lainnya') NOT NULL DEFAULT 'Lainnya',
  lokasi VARCHAR(120) NOT NULL,
  harga_mulai DECIMAL(14,2) NOT NULL DEFAULT 0,
  deskripsi TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_jasa_pekerja FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_jasa_filter (kategori, lokasi)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pelatihan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  penyedia_id BIGINT UNSIGNED NOT NULL,
  judul_pelatihan VARCHAR(160) NOT NULL,
  kategori VARCHAR(80) NOT NULL,
  jumlah_modul SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  nama_badge VARCHAR(120) NOT NULL,
  deskripsi TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pelatihan_penyedia FOREIGN KEY (penyedia_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_badges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  pelatihan_id BIGINT UNSIGNED NOT NULL,
  tanggal_diraih TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_badge_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_badge_pelatihan FOREIGN KEY (pelatihan_id) REFERENCES pelatihan(id) ON DELETE CASCADE,
  UNIQUE KEY uq_user_badge (user_id, pelatihan_id)
) ENGINE=InnoDB;

-- Password seluruh akun dummy: password
-- Hash bcrypt kompatibel dengan password_verify() PHP.
INSERT IGNORE INTO users (id,nama,email,password,role,telepon,lokasi,deskripsi,skills,is_verified) VALUES
(1,'Admin NusaKarya','admin@nusakarya.test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin','081234567890','Purwokerto','Administrator platform NusaKarya',NULL,1),
(2,'Kreasi Nusantara','usaha@nusakarya.test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','penyedia','081298765432','Purwokerto','UMKM kreatif yang bergerak di bidang produk lokal dan digital.',NULL,1),
(3,'Nadia Arum','nadia@nusakarya.test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','pekerja','081377766655','Purwokerto','UI/UX Designer yang tertarik membantu bisnis lokal berkembang.','Figma, UI Design, UX Research, Branding',1);

INSERT IGNORE INTO lowongan (penyedia_id,judul,kategori,jenis_pekerjaan,lokasi,gaji_min,gaji_max,deskripsi,persyaratan,batas_lamaran,status) VALUES
(2,'Junior UI/UX Designer','Desain','Paruh waktu','Purwokerto',5000000,7000000,'Membantu merancang pengalaman digital untuk produk dan layanan UMKM.','Memahami Figma, dasar UI/UX, dan mampu bekerja dalam tim.','2027-02-28','Aktif'),
(2,'Content Creator UMKM','Konten','Freelance','Banyumas',2500000,4000000,'Membuat konten media sosial untuk kampanye produk lokal.','Memiliki portofolio konten dan memahami media sosial.','2027-03-15','Aktif'),
(2,'Staf Administrasi','Administrasi','Penuh waktu','Purwokerto',3000000,4000000,'Mendukung pencatatan dan administrasi operasional usaha.','Teliti, komunikatif, dan menguasai aplikasi perkantoran.','2027-03-20','Aktif');

INSERT IGNORE INTO jasa (pekerja_id,nama_jasa,kategori,lokasi,harga_mulai,deskripsi) VALUES
(3,'Desain UI Landing Page','Desain','Purwokerto',750000,'Desain antarmuka website yang rapi, responsif, dan mudah digunakan.'),
(3,'Desain Konten Instagram','Konten','Banyumas',150000,'Paket desain konten media sosial untuk bisnis lokal.');

INSERT IGNORE INTO pelatihan (penyedia_id,judul_pelatihan,kategori,jumlah_modul,nama_badge,deskripsi) VALUES
(2,'UI Design Essentials','Desain',6,'UI Design Essentials','Fondasi desain antarmuka, layout, warna, tipografi, dan prototyping.'),
(2,'Digital Content untuk UMKM','Konten',4,'Digital Content Creator','Membuat konten promosi yang relevan untuk usaha lokal.'),
(2,'Administrasi Digital Dasar','Administrasi',5,'Digital Administration','Pengelolaan dokumen, data, dan administrasi digital.');


-- Skema lanjutan Tahap 2 sampai Tahap 4.5 digabung di bawah ini.

-- ============================================================
-- SKEMA LANJUTAN TAHAP 2 - TAHAP 4.5
-- ============================================================

CREATE TABLE IF NOT EXISTS portfolio (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pekerja_id BIGINT UNSIGNED NOT NULL,
 judul VARCHAR(160) NOT NULL,
 deskripsi TEXT NOT NULL,
 kategori VARCHAR(80) NOT NULL,
 tautan VARCHAR(2048) DEFAULT NULL,
 gambar VARCHAR(255) DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_portfolio_pekerja FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_portfolio_pekerja (pekerja_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS riwayat_lamaran (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 lamaran_id BIGINT UNSIGNED NOT NULL,
 status_sebelumnya ENUM('Menunggu','Diproses','Diterima','Ditolak') NOT NULL,
 status_terbaru ENUM('Menunggu','Diproses','Diterima','Ditolak') NOT NULL,
 catatan TEXT DEFAULT NULL,
 diubah_oleh BIGINT UNSIGNED DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_riwayat_lamaran FOREIGN KEY (lamaran_id) REFERENCES lamaran(id) ON DELETE CASCADE,
 CONSTRAINT fk_riwayat_pengubah FOREIGN KEY (diubah_oleh) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_riwayat_lamaran (lamaran_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill status awal agar lamaran yang dibuat sebelum Tahap 2 juga punya jejak awal.
INSERT INTO riwayat_lamaran (lamaran_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh)
SELECT l.id,l.status,l.status,'Status awal saat migrasi Tahap 2',NULL
FROM lamaran l
WHERE NOT EXISTS (SELECT 1 FROM riwayat_lamaran r WHERE r.lamaran_id=l.id);


CREATE TABLE IF NOT EXISTS pesanan (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pembeli_id BIGINT UNSIGNED NOT NULL,
 pekerja_id BIGINT UNSIGNED NOT NULL,
 jasa_id BIGINT UNSIGNED DEFAULT NULL,
 nama_jasa VARCHAR(160) NOT NULL,
 harga DECIMAL(14,2) NOT NULL DEFAULT 0,
 catatan TEXT DEFAULT NULL,
 tanggal_dibutuhkan DATE DEFAULT NULL,
 status ENUM('Menunggu','Dikonfirmasi','Dikerjakan','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_pesanan_pembeli FOREIGN KEY (pembeli_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_pesanan_pekerja FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_pesanan_jasa FOREIGN KEY (jasa_id) REFERENCES jasa(id) ON DELETE SET NULL,
 INDEX idx_pesanan_pembeli (pembeli_id,status,created_at),
 INDEX idx_pesanan_pekerja (pekerja_id,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS riwayat_pesanan (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pesanan_id BIGINT UNSIGNED NOT NULL,
 status_sebelumnya ENUM('Menunggu','Dikonfirmasi','Dikerjakan','Selesai','Dibatalkan') DEFAULT NULL,
 status_terbaru ENUM('Menunggu','Dikonfirmasi','Dikerjakan','Selesai','Dibatalkan') NOT NULL,
 catatan TEXT DEFAULT NULL,
 diubah_oleh BIGINT UNSIGNED DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_rpesanan_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
 CONSTRAINT fk_rpesanan_user FOREIGN KEY (diubah_oleh) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_rpesanan (pesanan_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Ulasan: UNIQUE(pesanan_id) menjamin satu pesanan hanya punya satu ulasan (dijaga di level database).
CREATE TABLE IF NOT EXISTS ulasan (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pesanan_id BIGINT UNSIGNED NOT NULL,
 jasa_id BIGINT UNSIGNED DEFAULT NULL,
 pembeli_id BIGINT UNSIGNED NOT NULL,
 pekerja_id BIGINT UNSIGNED NOT NULL,
 rating TINYINT UNSIGNED NOT NULL,
 komentar TEXT DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT chk_ulasan_rating CHECK (rating BETWEEN 1 AND 5),
 CONSTRAINT fk_ulasan_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE CASCADE,
 CONSTRAINT fk_ulasan_jasa FOREIGN KEY (jasa_id) REFERENCES jasa(id) ON DELETE SET NULL,
 CONSTRAINT fk_ulasan_pembeli FOREIGN KEY (pembeli_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_ulasan_pekerja FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE CASCADE,
 UNIQUE KEY uq_ulasan_pesanan (pesanan_id),
 INDEX idx_ulasan_jasa (jasa_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Chat: satu percakapan per pasangan (pembeli, pekerja).
CREATE TABLE IF NOT EXISTS percakapan (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 pembeli_id BIGINT UNSIGNED NOT NULL,
 pekerja_id BIGINT UNSIGNED NOT NULL,
 jasa_id BIGINT UNSIGNED DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 last_message_at TIMESTAMP NULL DEFAULT NULL,
 CONSTRAINT fk_percakapan_pembeli FOREIGN KEY (pembeli_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_percakapan_pekerja FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_percakapan_jasa FOREIGN KEY (jasa_id) REFERENCES jasa(id) ON DELETE SET NULL,
 UNIQUE KEY uq_percakapan_pasangan (pembeli_id,pekerja_id),
 INDEX idx_percakapan_pekerja (pekerja_id,last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pesan (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 percakapan_id BIGINT UNSIGNED NOT NULL,
 pengirim_id BIGINT UNSIGNED NOT NULL,
 isi TEXT NOT NULL,
 dibaca_at TIMESTAMP NULL DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_pesan_percakapan FOREIGN KEY (percakapan_id) REFERENCES percakapan(id) ON DELETE CASCADE,
 CONSTRAINT fk_pesan_pengirim FOREIGN KEY (pengirim_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_pesan_percakapan (percakapan_id,id),
 INDEX idx_pesan_unread (percakapan_id,pengirim_id,dibaca_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Notifikasi. 'tautan' berupa path relatif aplikasi (mis. /pesan/chat.php?id=3).
CREATE TABLE IF NOT EXISTS notifikasi (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 tipe VARCHAR(30) NOT NULL,
 judul VARCHAR(160) NOT NULL,
 isi VARCHAR(500) NOT NULL,
 tautan VARCHAR(255) DEFAULT NULL,
 dibaca_at TIMESTAMP NULL DEFAULT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_notifikasi_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX idx_notifikasi_user (user_id,dibaca_at,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


SET NAMES utf8mb4;

-- 1. Marketplace: status publikasi jasa dan paket layanan.
ALTER TABLE jasa
  ADD COLUMN status_publikasi ENUM('Draft','Aktif','Nonaktif','Ditinjau') NOT NULL DEFAULT 'Aktif' AFTER deskripsi,
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD INDEX idx_jasa_status_kategori (status_publikasi, kategori, lokasi);

CREATE TABLE paket_jasa (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jasa_id BIGINT UNSIGNED NOT NULL,
  nama_paket ENUM('Basic','Standard','Premium','Kustom') NOT NULL,
  judul_paket VARCHAR(120) NOT NULL,
  deskripsi TEXT NOT NULL,
  harga DECIMAL(14,2) NOT NULL,
  estimasi_hari SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  jumlah_revisi SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  fitur_json LONGTEXT DEFAULT NULL,
  is_aktif TINYINT(1) NOT NULL DEFAULT 1,
  urutan TINYINT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_paket_jasa_jasa FOREIGN KEY (jasa_id) REFERENCES jasa(id) ON DELETE CASCADE,
  CONSTRAINT chk_paket_harga CHECK (harga >= 0),
  CONSTRAINT chk_paket_estimasi CHECK (estimasi_hari >= 1),
  CONSTRAINT chk_paket_fitur_json CHECK (fitur_json IS NULL OR JSON_VALID(fitur_json)),
  INDEX idx_paket_jasa_aktif (jasa_id, is_aktif, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE jasa_gambar (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jasa_id BIGINT UNSIGNED NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  alt_text VARCHAR(180) DEFAULT NULL,
  is_utama TINYINT(1) NOT NULL DEFAULT 0,
  urutan SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_jasa_gambar_jasa FOREIGN KEY (jasa_id) REFERENCES jasa(id) ON DELETE CASCADE,
  INDEX idx_jasa_gambar_urutan (jasa_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorit_jasa (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  jasa_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_favorit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favorit_jasa FOREIGN KEY (jasa_id) REFERENCES jasa(id) ON DELETE CASCADE,
  UNIQUE KEY uq_favorit_user_jasa (user_id, jasa_id),
  INDEX idx_favorit_jasa (jasa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Perluasan pesanan. Kolom status Tahap 3 dipertahankan untuk kompatibilitas.
ALTER TABLE pesanan
  ADD COLUMN kode_pesanan VARCHAR(40) NULL AFTER id,
  ADD COLUMN paket_jasa_id BIGINT UNSIGNED DEFAULT NULL AFTER jasa_id,
  ADD COLUMN harga_disepakati DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER harga,
  ADD COLUMN estimasi_hari SMALLINT UNSIGNED DEFAULT NULL AFTER harga_disepakati,
  ADD COLUMN jumlah_revisi SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER estimasi_hari,
  ADD COLUMN deadline DATE DEFAULT NULL AFTER tanggal_dibutuhkan,
  ADD COLUMN status_pembayaran ENUM('Belum Ditagihkan','Menunggu','Menunggu Verifikasi','Berhasil','Gagal','Kedaluwarsa','Dibatalkan','Refund Sebagian','Refund Penuh') NOT NULL DEFAULT 'Belum Ditagihkan' AFTER status,
  ADD COLUMN status_pekerjaan ENUM('Menunggu Konfirmasi','Menunggu Pembayaran','Siap Dikerjakan','Dikerjakan','Menunggu Review','Revisi','Selesai','Dibatalkan','Sengketa') NOT NULL DEFAULT 'Menunggu Konfirmasi' AFTER status_pembayaran,
  ADD COLUMN accepted_at DATETIME DEFAULT NULL,
  ADD COLUMN started_at DATETIME DEFAULT NULL,
  ADD COLUMN submitted_at DATETIME DEFAULT NULL,
  ADD COLUMN completed_at DATETIME DEFAULT NULL,
  ADD UNIQUE KEY uq_pesanan_kode (kode_pesanan),
  ADD INDEX idx_pesanan_payment_status (status_pembayaran, created_at),
  ADD INDEX idx_pesanan_work_status (status_pekerjaan, created_at),
  ADD CONSTRAINT fk_pesanan_paket FOREIGN KEY (paket_jasa_id) REFERENCES paket_jasa(id) ON DELETE SET NULL,
  ADD CONSTRAINT chk_pesanan_harga_disepakati CHECK (harga_disepakati >= 0);

-- Backfill transaksi Tahap 3: harga dan status lama tetap utuh.
UPDATE pesanan
SET kode_pesanan = CONCAT('NK-LEGACY-', LPAD(id, 12, '0')),
    harga_disepakati = harga,
    status_pekerjaan = CASE status
      WHEN 'Menunggu' THEN 'Menunggu Konfirmasi'
      WHEN 'Dikonfirmasi' THEN 'Siap Dikerjakan'
      WHEN 'Dikerjakan' THEN 'Dikerjakan'
      WHEN 'Selesai' THEN 'Selesai'
      WHEN 'Dibatalkan' THEN 'Dibatalkan'
      ELSE 'Menunggu Konfirmasi'
    END,
    status_pembayaran = 'Belum Ditagihkan';

ALTER TABLE pesanan
  MODIFY COLUMN kode_pesanan VARCHAR(40) NOT NULL;

-- Cegah penghapusan akun yang sudah mempunyai pesanan/transaksi.
-- Akun yang tidak aktif sebaiknya dinonaktifkan, bukan dihapus.
ALTER TABLE pesanan
  DROP FOREIGN KEY fk_pesanan_pembeli,
  DROP FOREIGN KEY fk_pesanan_pekerja,
  ADD CONSTRAINT fk_pesanan_pembeli FOREIGN KEY (pembeli_id) REFERENCES users(id) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_pesanan_pekerja FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE RESTRICT;

-- 3. Penawaran harga dan jejak versi negosiasi.
CREATE TABLE penawaran (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pesanan_id BIGINT UNSIGNED NOT NULL,
  dibuat_oleh BIGINT UNSIGNED NOT NULL,
  versi_aktif SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  harga DECIMAL(14,2) NOT NULL,
  estimasi_hari SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  jumlah_revisi SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  deskripsi_pekerjaan TEXT NOT NULL,
  berlaku_sampai DATETIME DEFAULT NULL,
  status ENUM('Menunggu','Diterima','Ditolak','Kedaluwarsa','Dibatalkan','Digantikan') NOT NULL DEFAULT 'Menunggu',
  diterima_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_penawaran_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_penawaran_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_penawaran_harga CHECK (harga >= 0),
  CONSTRAINT chk_penawaran_estimasi CHECK (estimasi_hari >= 1),
  INDEX idx_penawaran_pesanan (pesanan_id, created_at),
  INDEX idx_penawaran_status (status, berlaku_sampai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE penawaran_riwayat (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  penawaran_id BIGINT UNSIGNED NOT NULL,
  versi SMALLINT UNSIGNED NOT NULL,
  diubah_oleh BIGINT UNSIGNED NOT NULL,
  harga DECIMAL(14,2) NOT NULL,
  estimasi_hari SMALLINT UNSIGNED NOT NULL,
  jumlah_revisi SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  deskripsi_pekerjaan TEXT NOT NULL,
  status ENUM('Diajukan','Diterima','Ditolak','Kedaluwarsa','Dibatalkan','Digantikan') NOT NULL,
  catatan TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_penawaran_riwayat_penawaran FOREIGN KEY (penawaran_id) REFERENCES penawaran(id) ON DELETE RESTRICT,
  CONSTRAINT fk_penawaran_riwayat_user FOREIGN KEY (diubah_oleh) REFERENCES users(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_penawaran_versi (penawaran_id, versi),
  INDEX idx_penawaran_riwayat_tanggal (penawaran_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Aturan komisi. Tidak ada persentase default yang dipaksakan.
-- Admin wajib mengaktifkan satu aturan sebelum invoice produksi diterbitkan.
CREATE TABLE aturan_komisi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(120) NOT NULL,
  persentase DECIMAL(5,2) NOT NULL,
  berlaku_mulai DATETIME NOT NULL,
  berlaku_sampai DATETIME DEFAULT NULL,
  is_aktif TINYINT(1) NOT NULL DEFAULT 0,
  dibuat_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_aturan_komisi_admin FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_aturan_komisi_persen CHECK (persentase >= 0 AND persentase <= 100),
  INDEX idx_aturan_komisi_aktif (is_aktif, berlaku_mulai, berlaku_sampai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Invoice dan ledger komisi. Komisi dan biaya gateway dicatat terpisah.
CREATE TABLE invoice (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode_invoice VARCHAR(40) NOT NULL,
  pesanan_id BIGINT UNSIGNED NOT NULL,
  versi SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  subtotal_jasa DECIMAL(14,2) NOT NULL,
  aturan_komisi_id BIGINT UNSIGNED DEFAULT NULL,
  komisi_persen DECIMAL(5,2) NOT NULL DEFAULT 0,
  komisi_nominal DECIMAL(14,2) NOT NULL DEFAULT 0,
  komisi_dibayar_oleh ENUM('pekerja','pembeli') NOT NULL,
  biaya_gateway DECIMAL(14,2) NOT NULL DEFAULT 0,
  biaya_lain DECIMAL(14,2) NOT NULL DEFAULT 0,
  total_tagihan DECIMAL(14,2) NOT NULL,
  mata_uang CHAR(3) NOT NULL DEFAULT 'IDR',
  snapshot_transaksi LONGTEXT DEFAULT NULL,
  status ENUM('Draft','Belum Dibayar','Menunggu Verifikasi','Lunas','Kedaluwarsa','Dibatalkan','Refund Sebagian','Refund Penuh') NOT NULL DEFAULT 'Draft',
  diterbitkan_at DATETIME DEFAULT NULL,
  jatuh_tempo_at DATETIME DEFAULT NULL,
  lunas_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_invoice_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_invoice_aturan_komisi FOREIGN KEY (aturan_komisi_id) REFERENCES aturan_komisi(id) ON DELETE SET NULL,
  CONSTRAINT chk_invoice_subtotal CHECK (subtotal_jasa >= 0),
  CONSTRAINT chk_invoice_komisi_persen CHECK (komisi_persen >= 0 AND komisi_persen <= 100),
  CONSTRAINT chk_invoice_komisi CHECK (komisi_nominal >= 0),
  CONSTRAINT chk_invoice_total CHECK (total_tagihan >= 0),
  CONSTRAINT chk_invoice_snapshot_json CHECK (snapshot_transaksi IS NULL OR JSON_VALID(snapshot_transaksi)),
  UNIQUE KEY uq_invoice_kode (kode_invoice),
  UNIQUE KEY uq_invoice_versi (pesanan_id, versi),
  INDEX idx_invoice_status (status, jatuh_tempo_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE komisi_transaksi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  dasar_perhitungan DECIMAL(14,2) NOT NULL,
  persentase_snapshot DECIMAL(5,2) NOT NULL,
  nominal_komisi DECIMAL(14,2) NOT NULL,
  dibayar_oleh ENUM('pekerja','pembeli') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_komisi_invoice FOREIGN KEY (invoice_id) REFERENCES invoice(id) ON DELETE RESTRICT,
  CONSTRAINT chk_komisi_dasar CHECK (dasar_perhitungan >= 0),
  CONSTRAINT chk_komisi_persen CHECK (persentase_snapshot >= 0 AND persentase_snapshot <= 100),
  CONSTRAINT chk_komisi_nominal CHECK (nominal_komisi >= 0),
  UNIQUE KEY uq_komisi_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Pembayaran manual maupun gateway. Satu invoice dapat memiliki beberapa percobaan.
CREATE TABLE pembayaran (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  kode_pembayaran VARCHAR(64) NOT NULL,
  metode ENUM('manual','gateway') NOT NULL,
  provider VARCHAR(60) DEFAULT NULL,
  provider_reference VARCHAR(160) DEFAULT NULL,
  jumlah DECIMAL(14,2) NOT NULL,
  status ENUM('Menunggu','Menunggu Verifikasi','Berhasil','Gagal','Kedaluwarsa','Dibatalkan','Refund Sebagian','Refund Penuh') NOT NULL DEFAULT 'Menunggu',
  instruksi_pembayaran TEXT DEFAULT NULL,
  bukti_path VARCHAR(500) DEFAULT NULL,
  dibayar_at DATETIME DEFAULT NULL,
  diverifikasi_at DATETIME DEFAULT NULL,
  diverifikasi_oleh BIGINT UNSIGNED DEFAULT NULL,
  catatan_verifikasi TEXT DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pembayaran_invoice FOREIGN KEY (invoice_id) REFERENCES invoice(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pembayaran_verifikator FOREIGN KEY (diverifikasi_oleh) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_pembayaran_jumlah CHECK (jumlah >= 0),
  UNIQUE KEY uq_pembayaran_kode (kode_pembayaran),
  INDEX idx_pembayaran_invoice (invoice_id, created_at),
  INDEX idx_pembayaran_status (status, created_at),
  INDEX idx_pembayaran_provider_ref (provider, provider_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pembayaran_webhook (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pembayaran_id BIGINT UNSIGNED DEFAULT NULL,
  provider VARCHAR(60) NOT NULL,
  provider_event_id VARCHAR(180) NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  signature_valid TINYINT(1) NOT NULL DEFAULT 0,
  payload LONGTEXT NOT NULL,
  status_proses ENUM('Menunggu','Diproses','Berhasil','Gagal','Diabaikan') NOT NULL DEFAULT 'Menunggu',
  error_message TEXT DEFAULT NULL,
  received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_webhook_pembayaran FOREIGN KEY (pembayaran_id) REFERENCES pembayaran(id) ON DELETE SET NULL,
  UNIQUE KEY uq_webhook_provider_event (provider, provider_event_id),
  INDEX idx_webhook_status (status_proses, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Progres pekerjaan, hasil, lampiran, dan revisi.
CREATE TABLE milestone (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pesanan_id BIGINT UNSIGNED NOT NULL,
  judul VARCHAR(160) NOT NULL,
  deskripsi TEXT DEFAULT NULL,
  urutan SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  bobot_persen DECIMAL(5,2) NOT NULL DEFAULT 0,
  status ENUM('Belum Dimulai','Dikerjakan','Menunggu Review','Selesai','Dibatalkan') NOT NULL DEFAULT 'Belum Dimulai',
  deadline DATETIME DEFAULT NULL,
  selesai_at DATETIME DEFAULT NULL,
  dibuat_oleh BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_milestone_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_milestone_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT chk_milestone_bobot CHECK (bobot_persen >= 0 AND bobot_persen <= 100),
  INDEX idx_milestone_pesanan (pesanan_id, urutan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pengiriman_hasil (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pesanan_id BIGINT UNSIGNED NOT NULL,
  milestone_id BIGINT UNSIGNED DEFAULT NULL,
  versi SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  catatan TEXT DEFAULT NULL,
  tautan_hasil VARCHAR(1000) DEFAULT NULL,
  status ENUM('Dikirim','Diterima','Perlu Revisi','Digantikan') NOT NULL DEFAULT 'Dikirim',
  dikirim_oleh BIGINT UNSIGNED NOT NULL,
  dikirim_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ditinjau_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_hasil_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_hasil_milestone FOREIGN KEY (milestone_id) REFERENCES milestone(id) ON DELETE SET NULL,
  CONSTRAINT fk_hasil_user FOREIGN KEY (dikirim_oleh) REFERENCES users(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_hasil_versi (pesanan_id, versi),
  INDEX idx_hasil_pesanan (pesanan_id, dikirim_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lampiran_pesanan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pesanan_id BIGINT UNSIGNED NOT NULL,
  pengiriman_hasil_id BIGINT UNSIGNED DEFAULT NULL,
  diunggah_oleh BIGINT UNSIGNED NOT NULL,
  nama_asli VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  ukuran_bytes BIGINT UNSIGNED NOT NULL,
  kategori ENUM('Brief','Referensi','Hasil','Lainnya') NOT NULL DEFAULT 'Lainnya',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_lampiran_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_lampiran_hasil FOREIGN KEY (pengiriman_hasil_id) REFERENCES pengiriman_hasil(id) ON DELETE SET NULL,
  CONSTRAINT fk_lampiran_user FOREIGN KEY (diunggah_oleh) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_lampiran_pesanan (pesanan_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permintaan_revisi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pesanan_id BIGINT UNSIGNED NOT NULL,
  pengiriman_hasil_id BIGINT UNSIGNED DEFAULT NULL,
  diminta_oleh BIGINT UNSIGNED NOT NULL,
  nomor_revisi SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  alasan TEXT NOT NULL,
  status ENUM('Diminta','Dikerjakan','Dikirim Ulang','Diselesaikan','Ditolak') NOT NULL DEFAULT 'Diminta',
  diminta_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  selesai_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_revisi_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_revisi_hasil FOREIGN KEY (pengiriman_hasil_id) REFERENCES pengiriman_hasil(id) ON DELETE SET NULL,
  CONSTRAINT fk_revisi_user FOREIGN KEY (diminta_oleh) REFERENCES users(id) ON DELETE RESTRICT,
  INDEX idx_revisi_pesanan (pesanan_id, diminta_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Refund dan sengketa.
CREATE TABLE transaksi_refund (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pembayaran_id BIGINT UNSIGNED NOT NULL,
  pesanan_id BIGINT UNSIGNED NOT NULL,
  kode_refund VARCHAR(64) NOT NULL,
  jumlah DECIMAL(14,2) NOT NULL,
  alasan TEXT NOT NULL,
  status ENUM('Diajukan','Disetujui','Ditolak','Diproses','Berhasil','Gagal') NOT NULL DEFAULT 'Diajukan',
  diajukan_oleh BIGINT UNSIGNED NOT NULL,
  diproses_oleh BIGINT UNSIGNED DEFAULT NULL,
  provider_refund_id VARCHAR(180) DEFAULT NULL,
  catatan_admin TEXT DEFAULT NULL,
  diajukan_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diproses_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_refund_pembayaran FOREIGN KEY (pembayaran_id) REFERENCES pembayaran(id) ON DELETE RESTRICT,
  CONSTRAINT fk_refund_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_refund_pengaju FOREIGN KEY (diajukan_oleh) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_refund_admin FOREIGN KEY (diproses_oleh) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_refund_jumlah CHECK (jumlah > 0),
  UNIQUE KEY uq_refund_kode (kode_refund),
  INDEX idx_refund_status (status, diajukan_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sengketa (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pesanan_id BIGINT UNSIGNED NOT NULL,
  dibuka_oleh BIGINT UNSIGNED NOT NULL,
  subjek VARCHAR(180) NOT NULL,
  deskripsi TEXT NOT NULL,
  status ENUM('Dibuka','Dalam Peninjauan','Menunggu Bukti','Diputuskan','Ditutup') NOT NULL DEFAULT 'Dibuka',
  keputusan ENUM('Refund Penuh','Refund Sebagian','Lanjutkan Pekerjaan','Tidak Ada Refund','Batal') DEFAULT NULL,
  catatan_keputusan TEXT DEFAULT NULL,
  ditangani_oleh BIGINT UNSIGNED DEFAULT NULL,
  dibuka_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  diselesaikan_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_sengketa_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sengketa_pembuka FOREIGN KEY (dibuka_oleh) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_sengketa_admin FOREIGN KEY (ditangani_oleh) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_sengketa_status (status, dibuka_at),
  INDEX idx_sengketa_pesanan (pesanan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Audit log untuk aksi sensitif dan perubahan status transaksi.
CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  aksi VARCHAR(100) NOT NULL,
  entitas VARCHAR(80) NOT NULL,
  entitas_id BIGINT UNSIGNED DEFAULT NULL,
  data_sebelum LONGTEXT DEFAULT NULL,
  data_sesudah LONGTEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_entitas (entitas, entitas_id, created_at),
  INDEX idx_audit_user (user_id, created_at),
  INDEX idx_audit_aksi (aksi, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Konteks pesanan pada chat. Struktur percakapan per pasangan Tahap 3
-- tetap dipertahankan; pesan dapat ditautkan ke pesanan tertentu.
ALTER TABLE percakapan
  ADD COLUMN pesanan_id BIGINT UNSIGNED DEFAULT NULL AFTER jasa_id,
  ADD INDEX idx_percakapan_pesanan (pesanan_id),
  ADD CONSTRAINT fk_percakapan_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE SET NULL;

ALTER TABLE pesan
  ADD COLUMN pesanan_id BIGINT UNSIGNED DEFAULT NULL AFTER percakapan_id,
  ADD INDEX idx_pesan_pesanan (pesanan_id, created_at),
  ADD CONSTRAINT fk_pesan_pesanan FOREIGN KEY (pesanan_id) REFERENCES pesanan(id) ON DELETE SET NULL;


SET NAMES utf8mb4;

ALTER TABLE invoice
  ADD COLUMN komisi_pembeli_persen DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER komisi_nominal,
  ADD COLUMN komisi_pembeli_nominal DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER komisi_pembeli_persen,
  ADD COLUMN komisi_pekerja_persen DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER komisi_pembeli_nominal,
  ADD COLUMN komisi_pekerja_nominal DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER komisi_pekerja_persen,
  ADD COLUMN biaya_gateway_ditanggung ENUM('platform','pembeli','pekerja') NOT NULL DEFAULT 'platform' AFTER biaya_gateway;

ALTER TABLE komisi_transaksi
  ADD COLUMN komisi_pembeli_nominal DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER nominal_komisi,
  ADD COLUMN komisi_pekerja_nominal DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER komisi_pembeli_nominal,
  ADD COLUMN biaya_gateway_platform DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER komisi_pekerja_nominal;

ALTER TABLE pembayaran
  ADD COLUMN snap_token VARCHAR(180) DEFAULT NULL AFTER provider_reference,
  ADD COLUMN snap_redirect_url VARCHAR(1000) DEFAULT NULL AFTER snap_token,
  ADD COLUMN payment_type VARCHAR(80) DEFAULT NULL AFTER snap_redirect_url,
  ADD COLUMN raw_status VARCHAR(80) DEFAULT NULL AFTER payment_type;

-- Wallet is a ledger-backed balance, not an escrow account. The platform must only
-- credit a worker after buyer acceptance and must process bank payout separately.
CREATE TABLE dompet_pekerja (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pekerja_id BIGINT UNSIGNED NOT NULL,
  saldo_tersedia DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  saldo_diproses DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_pendapatan DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  total_dicairkan DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_dompet_pekerja_user FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE RESTRICT,
  UNIQUE KEY uq_dompet_pekerja (pekerja_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mutasi_dompet (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dompet_id BIGINT UNSIGNED NOT NULL,
  jenis ENUM('Pendapatan Pesanan','Permintaan Pencairan','Pencairan Berhasil','Pencairan Gagal','Penyesuaian','Refund') NOT NULL,
  arah ENUM('Masuk','Keluar') NOT NULL,
  jumlah DECIMAL(14,2) NOT NULL,
  saldo_setelah DECIMAL(14,2) NOT NULL,
  referensi_tipe VARCHAR(40) DEFAULT NULL,
  referensi_id BIGINT UNSIGNED DEFAULT NULL,
  keterangan VARCHAR(500) DEFAULT NULL,
  dibuat_oleh BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mutasi_dompet FOREIGN KEY (dompet_id) REFERENCES dompet_pekerja(id) ON DELETE RESTRICT,
  CONSTRAINT fk_mutasi_user FOREIGN KEY (dibuat_oleh) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_mutasi_jumlah CHECK (jumlah >= 0),
  UNIQUE KEY uq_mutasi_referensi (jenis, referensi_tipe, referensi_id),
  INDEX idx_mutasi_dompet_tanggal (dompet_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pencairan_pekerja (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode_pencairan VARCHAR(50) NOT NULL,
  pekerja_id BIGINT UNSIGNED NOT NULL,
  dompet_id BIGINT UNSIGNED NOT NULL,
  jumlah DECIMAL(14,2) NOT NULL,
  nama_bank VARCHAR(100) NOT NULL,
  nomor_rekening VARCHAR(80) NOT NULL,
  nama_pemilik_rekening VARCHAR(160) NOT NULL,
  status ENUM('Menunggu','Disetujui','Diproses','Berhasil','Ditolak','Gagal') NOT NULL DEFAULT 'Menunggu',
  catatan_admin TEXT DEFAULT NULL,
  referensi_transfer VARCHAR(160) DEFAULT NULL,
  bukti_transfer VARCHAR(500) DEFAULT NULL,
  diproses_oleh BIGINT UNSIGNED DEFAULT NULL,
  diminta_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  selesai_at DATETIME DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_pencairan_pekerja FOREIGN KEY (pekerja_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pencairan_dompet FOREIGN KEY (dompet_id) REFERENCES dompet_pekerja(id) ON DELETE RESTRICT,
  CONSTRAINT fk_pencairan_admin FOREIGN KEY (diproses_oleh) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_pencairan_jumlah CHECK (jumlah > 0),
  UNIQUE KEY uq_pencairan_kode (kode_pencairan),
  INDEX idx_pencairan_status (status, diminta_at),
  INDEX idx_pencairan_pekerja (pekerja_id, diminta_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preserve legacy invoice economics: old one-sided commission remains assigned to its historical payer.
UPDATE invoice SET
  komisi_pembeli_persen = CASE WHEN komisi_dibayar_oleh='pembeli' THEN komisi_persen ELSE 0 END,
  komisi_pembeli_nominal = CASE WHEN komisi_dibayar_oleh='pembeli' THEN komisi_nominal ELSE 0 END,
  komisi_pekerja_persen = CASE WHEN komisi_dibayar_oleh='pekerja' THEN komisi_persen ELSE 0 END,
  komisi_pekerja_nominal = CASE WHEN komisi_dibayar_oleh='pekerja' THEN komisi_nominal ELSE 0 END,
  biaya_gateway_ditanggung = 'platform';
UPDATE komisi_transaksi c JOIN invoice i ON i.id=c.invoice_id SET
  c.komisi_pembeli_nominal = CASE WHEN i.komisi_dibayar_oleh='pembeli' THEN c.nominal_komisi ELSE 0 END,
  c.komisi_pekerja_nominal = CASE WHEN i.komisi_dibayar_oleh='pekerja' THEN c.nominal_komisi ELSE 0 END;

UPDATE aturan_komisi SET is_aktif=0 WHERE is_aktif=1;
INSERT INTO aturan_komisi (nama,persentase,berlaku_mulai,is_aktif)
VALUES ('Komisi NusaKarya 10% (5% pembeli + 5% pekerja)',10.00,NOW(),1);

-- ======================= END database/migrations/005_pembayaran_midtrans_wallet.sql =======================
