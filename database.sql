-- NusaKarya database
-- Import melalui phpMyAdmin / MySQL CLI.
CREATE DATABASE IF NOT EXISTS db_nusakarya
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_nusakarya;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS user_badges;
DROP TABLE IF EXISTS lamaran;
DROP TABLE IF EXISTS jasa;
DROP TABLE IF EXISTS pelatihan;
DROP TABLE IF EXISTS lowongan;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
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

CREATE TABLE lowongan (
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

CREATE TABLE lamaran (
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

CREATE TABLE jasa (
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

CREATE TABLE pelatihan (
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

CREATE TABLE user_badges (
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
INSERT INTO users (id,nama,email,password,role,telepon,lokasi,deskripsi,skills,is_verified) VALUES
(1,'Admin NusaKarya','admin@nusakarya.test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin','081234567890','Purwokerto','Administrator platform NusaKarya',NULL,1),
(2,'Kreasi Nusantara','usaha@nusakarya.test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','penyedia','081298765432','Purwokerto','UMKM kreatif yang bergerak di bidang produk lokal dan digital.',NULL,1),
(3,'Nadia Arum','nadia@nusakarya.test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','pekerja','081377766655','Purwokerto','UI/UX Designer yang tertarik membantu bisnis lokal berkembang.','Figma, UI Design, UX Research, Branding',1);

INSERT INTO lowongan (penyedia_id,judul,kategori,jenis_pekerjaan,lokasi,gaji_min,gaji_max,deskripsi,persyaratan,batas_lamaran,status) VALUES
(2,'Junior UI/UX Designer','Desain','Paruh waktu','Purwokerto',5000000,7000000,'Membantu merancang pengalaman digital untuk produk dan layanan UMKM.','Memahami Figma, dasar UI/UX, dan mampu bekerja dalam tim.','2027-02-28','Aktif'),
(2,'Content Creator UMKM','Konten','Freelance','Banyumas',2500000,4000000,'Membuat konten media sosial untuk kampanye produk lokal.','Memiliki portofolio konten dan memahami media sosial.','2027-03-15','Aktif'),
(2,'Staf Administrasi','Administrasi','Penuh waktu','Purwokerto',3000000,4000000,'Mendukung pencatatan dan administrasi operasional usaha.','Teliti, komunikatif, dan menguasai aplikasi perkantoran.','2027-03-20','Aktif');

INSERT INTO jasa (pekerja_id,nama_jasa,kategori,lokasi,harga_mulai,deskripsi) VALUES
(3,'Desain UI Landing Page','Desain','Purwokerto',750000,'Desain antarmuka website yang rapi, responsif, dan mudah digunakan.'),
(3,'Desain Konten Instagram','Konten','Banyumas',150000,'Paket desain konten media sosial untuk bisnis lokal.');

INSERT INTO pelatihan (penyedia_id,judul_pelatihan,kategori,jumlah_modul,nama_badge,deskripsi) VALUES
(2,'UI Design Essentials','Desain',6,'UI Design Essentials','Fondasi desain antarmuka, layout, warna, tipografi, dan prototyping.'),
(2,'Digital Content untuk UMKM','Konten',4,'Digital Content Creator','Membuat konten promosi yang relevan untuk usaha lokal.'),
(2,'Administrasi Digital Dasar','Administrasi',5,'Digital Administration','Pengelolaan dokumen, data, dan administrasi digital.');
