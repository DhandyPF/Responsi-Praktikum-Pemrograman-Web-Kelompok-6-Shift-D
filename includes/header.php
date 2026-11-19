<?php
require_once __DIR__ . '/../config/functions.php';
$pageTitle = $pageTitle ?? 'NusaKarya';
$user = current_user();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="NusaKarya menghubungkan talenta lokal, UMKM, pekerjaan, jasa, dan pelatihan.">
  <title><?= e($pageTitle) ?> | NusaKarya</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    (() => {
      const saved = localStorage.getItem("nusakarya-theme");
      const theme = saved || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
      document.documentElement.dataset.theme = theme;
    })();
  </script>
  <script>
    tailwind.config = { theme: { extend: { colors: {
      ink:'#0F172A', teal:'#0F766E', emerald:'#059669', mist:'#F8FAFC'
    }, fontFamily: { sans:['Inter','sans-serif'], heading:['Plus Jakarta Sans','sans-serif'] } } } };
  </script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-mist text-slate-700 antialiased">
<header class="site-header">
  <nav class="nav-shell max-w-6xl mx-auto" aria-label="Navigasi utama">
    <a href="<?= BASE_URL ?>/index.php" class="brand" aria-label="NusaKarya Beranda">
      <span class="brand-mark">N</span><span>Nusa<span class="text-teal">Karya</span></span>
    </a>
    <button class="mobile-menu-btn" id="menuToggle" aria-label="Buka navigasi" aria-expanded="false">☰</button>
    <div class="nav-links" id="navLinks">
      <a href="<?= BASE_URL ?>/index.php">Beranda</a>
      <a href="<?= BASE_URL ?>/lowongan.php">Bursa Kerja</a>
      <a href="<?= BASE_URL ?>/jasa.php">Marketplace Jasa</a>
      <a href="<?= BASE_URL ?>/pelatihan.php">Pelatihan</a>
      <?php if ($user): ?>
        <a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a>
        <a class="btn-primary nav-cta" href="<?= BASE_URL ?>/auth/logout.php">Keluar</a>
      <?php else: ?>
        <a class="nav-login" href="<?= BASE_URL ?>/auth/login.php">Masuk</a>
        <a class="btn-primary nav-cta" href="<?= BASE_URL ?>/auth/register.php">Daftar Gratis <span aria-hidden="true">→</span></a>
      <?php endif; ?>
      <button class="theme-toggle" id="themeToggle" type="button" aria-label="Aktifkan mode gelap" aria-pressed="false">Mode gelap</button>
    </div>
  </nav>
</header>
