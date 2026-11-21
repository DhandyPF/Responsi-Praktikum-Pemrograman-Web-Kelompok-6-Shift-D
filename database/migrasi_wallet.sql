-- Migrasi tambahan NusaKarya: jalankan pada database db_nusakarya yang sudah ada.
-- Tidak menghapus tabel atau data yang sudah tersimpan.

CREATE TABLE IF NOT EXISTS `mutasi_dompet` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dompet_id` bigint(20) UNSIGNED NOT NULL,
  `jenis` varchar(80) NOT NULL,
  `arah` enum('Masuk','Keluar') NOT NULL,
  `jumlah` decimal(14,2) NOT NULL DEFAULT 0.00,
  `saldo_setelah` decimal(14,2) NOT NULL DEFAULT 0.00,
  `referensi_tipe` varchar(50) DEFAULT NULL,
  `referensi_id` bigint(20) UNSIGNED DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `dibuat_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mutasi_dompet_dompet` (`dompet_id`,`id`),
  KEY `idx_mutasi_referensi` (`referensi_tipe`,`referensi_id`),
  CONSTRAINT `fk_mutasi_dompet_dompet` FOREIGN KEY (`dompet_id`) REFERENCES `dompet_pekerja` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mutasi_dompet_user` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pencairan_pekerja` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_pencairan` varchar(64) NOT NULL,
  `pekerja_id` bigint(20) UNSIGNED NOT NULL,
  `dompet_id` bigint(20) UNSIGNED NOT NULL,
  `jumlah` decimal(14,2) NOT NULL,
  `nama_bank` varchar(120) NOT NULL,
  `nomor_rekening` varchar(80) NOT NULL,
  `nama_pemilik_rekening` varchar(160) NOT NULL,
  `status` enum('Menunggu','Disetujui','Diproses','Berhasil','Ditolak','Gagal') NOT NULL DEFAULT 'Menunggu',
  `diproses_oleh` bigint(20) UNSIGNED DEFAULT NULL,
  `catatan_admin` text DEFAULT NULL,
  `referensi_transfer` varchar(160) DEFAULT NULL,
  `diminta_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `selesai_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pencairan_kode` (`kode_pencairan`),
  KEY `idx_pencairan_pekerja` (`pekerja_id`,`id`),
  KEY `idx_pencairan_status` (`status`,`diminta_at`),
  CONSTRAINT `fk_pencairan_pekerja_user` FOREIGN KEY (`pekerja_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_pencairan_pekerja_dompet` FOREIGN KEY (`dompet_id`) REFERENCES `dompet_pekerja` (`id`),
  CONSTRAINT `fk_pencairan_admin` FOREIGN KEY (`diproses_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
