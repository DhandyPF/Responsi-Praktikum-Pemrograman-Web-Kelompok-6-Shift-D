<?php
require_once __DIR__ . '/../config/functions.php';
$pageTitle = $pageTitle ?? 'NusaKarya';
$user = current_user();
// Tahap 3: lencana pesan/notifikasi belum dibaca untuk navigasi (aman bila migrasi belum dijalankan → 0).
$navPesan = 0; $navNotif = 0; $navPesananUrl = null;
if ($user && in_array($user['role'] ?? '', ['pekerja', 'penyedia'], true)) {
    $navPesananUrl = BASE_URL . ($user['role'] === 'pekerja' ? '/pekerja/tahap4-pesanan.php' : '/penyedia/tahap4-pesanan.php');
    if (isset($pdo) && $pdo instanceof Database) {
        require_once __DIR__ . '/../config/stage3.php';
        $navPesan = unread_message_count($pdo, (int)$user['id']);
        $navNotif = unread_notification_count($pdo, (int)$user['id']);
    }
}
?>
<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="NusaKarya menghubungkan talenta lokal, UMKM, pekerjaan, jasa, dan pelatihan.">
    <title><?= e($pageTitle) ?> | NusaKarya</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/nusakarya-n.svg">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/nusakarya-n.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    (() => {
        const saved = localStorage.getItem("nusakarya-theme");
        const theme = saved || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
        document.documentElement.dataset.theme = theme;
    })();
    </script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    ink: '#0F172A',
                    teal: '#0F766E',
                    emerald: '#059669',
                    mist: '#F8FAFC'
                },
                fontFamily: {
                    sans: ['Inter', 'sans-serif'],
                    heading: ['Plus Jakarta Sans', 'sans-serif']
                }
            }
        }
    };
    </script>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>

<body class="bg-mist text-slate-700 antialiased">
    <header class="site-header">
        <nav class="nav-shell max-w-6xl mx-auto" aria-label="Navigasi utama">
            <a href="<?= BASE_URL ?>/index.php" class="brand" aria-label="NusaKarya Beranda">
                <img src="<?= BASE_URL ?>/assets/img/nusakarya-n.svg" alt="" class="brand-logo">
                <span>Nusa<span class="text-teal">Karya</span></span>
            </a>
            <button class="mobile-menu-btn" id="menuToggle" aria-label="Buka navigasi" aria-expanded="false">☰</button>
            <div class="nav-links" id="navLinks">
                <a href="<?= BASE_URL ?>/index.php">Beranda</a>
                <a href="<?= BASE_URL ?>/lowongan.php">Bursa Kerja</a>
                <a href="<?= BASE_URL ?>/marketplace.php">Marketplace</a>
                <a href="<?= BASE_URL ?>/pelatihan.php">Pelatihan</a>
                <?php if ($user): ?>
                <a href="<?= BASE_URL . dashboard_path($user) ?>">Dashboard</a>
                <details class="nav-more">
                    <summary>Aktivitas <span aria-hidden="true">⌄</span></summary>
                    <div class="nav-more-menu">
                        <?php if (($user['role'] ?? '') === 'pekerja'): ?>
                        <span class="nav-menu-label">Aktivitas pekerjaan</span>
                        <a href="<?= BASE_URL ?>/pekerja/lamaran.php">Lamaran</a>
                        <a href="<?= BASE_URL ?>/pekerja/tahap4-pesanan.php">Pesanan</a>
                        <a href="<?= BASE_URL ?>/pekerja/kelola-jasa.php">Kelola Jasa</a>
                        <a href="<?= BASE_URL ?>/pekerja/dompet.php">Dompet</a>
                        <span class="nav-menu-label">Komunikasi & profil</span>
                        <a href="<?= BASE_URL ?>/pesan/index.php">Pesan<?php if ($navPesan > 0): ?> <span
                                class="nav-badge"><?= $navPesan > 99 ? '99+' : (int)$navPesan ?></span><?php endif ?></a>
                        <a href="<?= BASE_URL ?>/notifikasi.php">Notifikasi<?php if ($navNotif > 0): ?> <span
                                class="nav-badge"><?= $navNotif > 99 ? '99+' : (int)$navNotif ?></span><?php endif ?></a>
                        <a href="<?= BASE_URL ?>/pekerja/profil.php">Profil & Portofolio</a>
                        <a href="<?= BASE_URL ?>/pekerja/rekomendasi.php">Rekomendasi Lowongan</a>
                        <?php elseif (($user['role'] ?? '') === 'penyedia'): ?>
                        <span class="nav-menu-label">Kelola usaha & pekerjaan</span>
                        <a href="<?= BASE_URL ?>/penyedia/lowongan.php">Lowongan Saya</a>
                        <a href="<?= BASE_URL ?>/penyedia/kandidat.php">Kandidat</a>
                        <a href="<?= BASE_URL ?>/penyedia/tahap4-pesanan.php">Transaksi & Pesanan</a>
                        <span class="nav-menu-label">Komunikasi & profil</span>
                        <a href="<?= BASE_URL ?>/pesan/index.php">Pesan<?php if ($navPesan > 0): ?> <span
                                class="nav-badge"><?= $navPesan > 99 ? '99+' : (int)$navPesan ?></span><?php endif ?></a>
                        <a href="<?= BASE_URL ?>/notifikasi.php">Notifikasi<?php if ($navNotif > 0): ?> <span
                                class="nav-badge"><?= $navNotif > 99 ? '99+' : (int)$navNotif ?></span><?php endif ?></a>
                        <a href="<?= BASE_URL ?>/penyedia/profil.php">Profil Usaha</a>
                        <?php elseif (($user['role'] ?? '') === 'admin'): ?>
                        <span class="nav-menu-label">Akun Administrator</span>
                        <a href="<?= BASE_URL ?>/admin/profil.php">Profil Saya</a>
                        <span class="nav-menu-label">Administrasi</span>
                        <a href="<?= BASE_URL ?>/admin/tahap4-transaksi.php">Transaksi</a>
                        <a href="<?= BASE_URL ?>/admin/pencairan.php">Pencairan</a>
                        <a href="<?= BASE_URL ?>/admin/pengguna.php">Pengguna</a>
                        <span class="nav-menu-label">Monitoring</span>
                        <a href="<?= BASE_URL ?>/admin/statistik.php">Statistik</a>
                        <a href="<?= BASE_URL ?>/admin/bukti-pembayaran.php">Bukti Pembayaran</a>
                        <?php endif ?>
                    </div>
                </details>
                <button class="theme-toggle" id="themeToggle" type="button" aria-label="Aktifkan mode gelap"
                    aria-pressed="false" title="Ganti mode tampilan">
                    <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8a8.5 8.5 0 1 0 11.3 11.3Z"></path>
                    </svg>
                    <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path
                            d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42">
                        </path>
                    </svg>
                </button>
                <a class="btn-primary nav-cta" href="<?= BASE_URL ?>/auth/logout.php">Keluar</a>
                <?php else: ?>
                <button class="theme-toggle" id="themeToggle" type="button" aria-label="Aktifkan mode gelap"
                    aria-pressed="false" title="Ganti mode tampilan">
                    <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8a8.5 8.5 0 1 0 11.3 11.3Z"></path>
                    </svg>
                    <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="12" r="4"></circle>
                        <path
                            d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.65 17.65l1.42 1.42M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.65 6.35l1.42-1.42">
                        </path>
                    </svg>
                </button>
                <a class="nav-login" href="<?= BASE_URL ?>/auth/login.php">Masuk</a>
                <a class="btn-primary nav-cta" href="<?= BASE_URL ?>/auth/register.php">Daftar</a>
                <?php endif ?>
            </div>
        </nav>
    </header>