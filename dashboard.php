<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/functions.php';
require_login();
$user = current_user();
$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<main class="max-w-6xl mx-auto px-5 py-16 min-h-[65vh]">
 <p class="eyebrow">Ruang kerja Anda</p><h1 class="text-3xl font-extrabold text-ink mt-2">Halo, <?= e($user['nama']) ?>!</h1><p class="text-sm text-slate-500 mt-2">Anda masuk sebagai <?= e(ucfirst($user['role'])) ?>.</p>
 <div class="dashboard-grid mt-9">
 <?php if($user['role']==='pekerja'): ?>
  <a class="surface-card p-6" href="<?= BASE_URL ?>/pekerja/profil.php"><b class="text-ink">Lengkapi profil</b><p class="text-xs text-slate-500 mt-2">Tambahkan skill dan informasi agar lebih mudah ditemukan.</p></a>
  <a class="surface-card p-6" href="<?= BASE_URL ?>/pekerja/lamaran.php"><b class="text-ink">Lamaran saya</b><p class="text-xs text-slate-500 mt-2">Pantau status lamaran pekerjaan.</p></a>
  <a class="surface-card p-6" href="<?= BASE_URL ?>/pekerja/jasa.php"><b class="text-ink">Kelola jasa</b><p class="text-xs text-slate-500 mt-2">Publikasikan keahlian yang dapat Anda tawarkan.</p></a>
  <a class="surface-card p-6" href="<?= BASE_URL ?>/pekerja/rekomendasi.php"><b class="text-ink">Rekomendasi pekerjaan</b><p class="text-xs text-slate-500 mt-2">Temukan pekerjaan yang sesuai dengan skill Anda.</p></a>
 <?php elseif($user['role']==='penyedia'): ?>
  <a class="surface-card p-6" href="<?= BASE_URL ?>/penyedia/lowongan.php"><b class="text-ink">Kelola lowongan</b><p class="text-xs text-slate-500 mt-2">Publikasikan dan kelola kebutuhan tenaga kerja.</p></a>
  <a class="surface-card p-6" href="<?= BASE_URL ?>/penyedia/kandidat.php"><b class="text-ink">Kelola kandidat</b><p class="text-xs text-slate-500 mt-2">Lihat pelamar dan perbarui status seleksi.</p></a>
  <a class="surface-card p-6" href="<?= BASE_URL ?>/penyedia/profil.php"><b class="text-ink">Profil usaha</b><p class="text-xs text-slate-500 mt-2">Perbarui informasi usaha Anda.</p></a>
 <?php else: ?>
  <a class="surface-card p-6" href="<?= BASE_URL ?>/admin/pengguna.php"><b class="text-ink">Kelola pengguna</b><p class="text-xs text-slate-500 mt-2">Lihat, verifikasi, dan kelola akun terdaftar.</p></a>
  <a class="surface-card p-6" href="<?= BASE_URL ?>/admin/statistik.php"><b class="text-ink">Statistik platform</b><p class="text-xs text-slate-500 mt-2">Pantau aktivitas NusaKarya.</p></a>
 <?php endif; ?>
 </div>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
