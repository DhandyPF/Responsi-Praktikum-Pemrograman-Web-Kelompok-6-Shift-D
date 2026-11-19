<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/functions.php';
$jobs = $pdo->query("SELECT l.*, u.nama AS penyedia, u.is_verified FROM lowongan l JOIN users u ON u.id=l.penyedia_id WHERE l.status='Aktif' ORDER BY l.created_at DESC LIMIT 3")->fetchAll();
$stats = [
 'talenta' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='pekerja'")->fetchColumn(),
 'lowongan' => (int)$pdo->query("SELECT COUNT(*) FROM lowongan WHERE status='Aktif'")->fetchColumn(),
 'pelatihan' => (int)$pdo->query("SELECT COUNT(*) FROM pelatihan")->fetchColumn(),
 'penyedia' => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='penyedia' AND is_verified=1")->fetchColumn()
];
$pageTitle = 'Temukan peluang dan kembangkan potensi lokal';
require __DIR__ . '/includes/header.php';
?>
<main>
<section class="hero max-w-6xl mx-auto">
 <div class="hero-grid">
  <div>
   <p class="eyebrow">Ekosistem peluang untuk talenta lokal</p>
   <h1>Temukan kerja yang <span>bertumbuh</span> bersama skill-mu.</h1>
   <p class="hero-copy">NusaKarya menghubungkan talenta, UMKM, peluang kerja, jasa mikro, dan pelatihan terverifikasi dalam satu ruang yang lebih adil dan mudah diakses.</p>
   <div class="flex flex-wrap gap-3 mt-7">
    <a class="btn-primary" href="<?= BASE_URL ?>/lowongan.php">Jelajahi lowongan <span>→</span></a>
    <a class="btn-secondary" href="<?= BASE_URL ?>/pelatihan.php">Mulai belajar</a>
   </div>
   <div class="flex items-center gap-3 mt-7 text-xs text-slate-500">
    <div class="flex -space-x-2"><span class="avatar !w-8 !h-8 !rounded-full border-2 border-white text-[10px]">NA</span><span class="avatar !w-8 !h-8 !rounded-full !bg-blue-100 !text-blue-700 !rounded-full border-2 border-white text-[10px]">KR</span><span class="avatar !w-8 !h-8 !rounded-full !bg-amber-100 !text-amber-700 !rounded-full border-2 border-white text-[10px]">UM</span></div>
    <span>Ruang tumbuh untuk talenta dan usaha lokal.</span>
   </div>
  </div>
  <div class="profile-stage rounded-[28px]">
   <div class="profile-card">
    <div class="flex items-center gap-3"><div class="avatar">NA</div><div><p class="font-bold text-ink text-sm">Nadia Arum</p><p class="text-xs text-slate-500 mt-1">UI/UX Designer · Purwokerto</p></div><span class="ml-auto badge-green">✓ Terverifikasi</span></div>
    <div class="mt-7 flex justify-between text-xs font-semibold text-slate-600"><span>Kelengkapan profil</span><span>82%</span></div>
    <div class="progress mt-2"><span></span></div>
    <div class="flex flex-wrap gap-2 mt-5"><span class="skill">Figma</span><span class="skill">UI Design</span><span class="skill">Branding</span><span class="skill">+2 skill</span></div>
    <div class="border-t border-slate-100 mt-6 pt-4 flex justify-between text-xs"><span class="text-slate-500">Profil siap ditemukan penyedia</span><span class="font-bold text-teal">Aktif</span></div>
    <div class="float-badge">✦ Badge baru!<br><span class="text-slate-300 font-normal">UI Design Essentials</span></div>
    <div class="float-match"><b class="text-ink">3 peluang cocok</b><br><span class="text-slate-500">Berdasarkan skill kamu</span></div>
   </div>
  </div>
 </div>
 <div class="stats-grid mt-14">
  <div class="stat"><strong><?= number_format($stats['talenta']) ?></strong><span>Talenta terdaftar</span></div>
  <div class="stat"><strong><?= number_format($stats['lowongan']) ?></strong><span>Lowongan aktif</span></div>
  <div class="stat"><strong><?= number_format($stats['pelatihan']) ?></strong><span>Pelatihan tersedia</span></div>
  <div class="stat"><strong><?= number_format($stats['penyedia']) ?></strong><span>Penyedia tervalidasi</span></div>
 </div>
</section>

<section class="section section-soft">
 <div class="max-w-6xl mx-auto">
  <div class="section-heading"><div><p class="eyebrow">Peluang terbaru</p><h2>Langkah berikutnya ada di sini.</h2><p class="text-sm text-slate-500 mt-2">Lowongan dari penyedia lokal dengan informasi yang jelas dan relevan.</p></div><a class="text-link" href="<?= BASE_URL ?>/lowongan.php">Lihat semua →</a></div>
  <div class="card-grid">
   <?php foreach ($jobs as $job): ?>
   <article class="job-card">
    <div class="flex items-center gap-3"><div class="company-icon"><?= e(strtoupper(substr($job['penyedia'],0,1))) ?></div><div><h3 class="font-bold text-ink text-sm"><?= e($job['penyedia']) ?></h3><p class="text-xs text-slate-500 mt-1"><?= e($job['lokasi']) ?></p></div><?php if($job['is_verified']): ?><span class="badge-green ml-auto">Terverifikasi</span><?php endif; ?></div>
    <h3 class="font-bold text-ink mt-6"><?= e($job['judul']) ?></h3><p class="text-xs text-slate-500 mt-2"><?= e($job['kategori']) ?> · <?= e($job['jenis_pekerjaan']) ?></p>
    <div class="border-t border-slate-100 mt-5 pt-4 flex justify-between items-center"><b class="text-sm text-ink"><?= rupiah($job['gaji_min']) ?>–<?= rupiah($job['gaji_max']) ?></b><a class="text-link" href="<?= BASE_URL ?>/lowongan.php">Detail →</a></div>
   </article>
   <?php endforeach; ?>
   <?php if (!$jobs): ?><div class="empty-state col-span-full">Belum ada lowongan aktif. Silakan kembali lagi nanti.</div><?php endif; ?>
  </div>
 </div>
</section>

<section class="section">
 <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-12 items-center">
  <div><p class="eyebrow">Bukan sekadar melamar</p><h2 class="text-3xl md:text-4xl font-extrabold text-ink mt-3">Bangun bukti, buka lebih banyak peluang.</h2><p class="text-sm leading-7 text-slate-500 mt-4">Pelajari skill yang dibutuhkan industri, dapatkan badge setelah menyelesaikan pelatihan, lalu tampil lebih meyakinkan di mata penyedia.</p><a class="btn-primary mt-6" href="<?= BASE_URL ?>/auth/register.php">Buat profil gratis</a></div>
  <div class="space-y-3">
   <?php foreach ([['✓','Skill yang relevan','Rekomendasi kerja berdasarkan skill dan minatmu.'],['✓','Badge terverifikasi','Tunjukkan hasil belajar dari pelatihan penyedia.'],['✓','Upah lebih transparan','Gunakan kalkulator upah sebagai estimasi awal.']] as $item): ?>
   <div class="surface-card p-5 flex gap-4 items-start"><span class="badge-green !rounded-full"><?= $item[0] ?></span><div><b class="text-sm text-ink"><?= $item[1] ?></b><p class="text-xs text-slate-500 mt-1"><?= $item[2] ?></p></div></div>
   <?php endforeach; ?>
  </div>
 </div>
</section>

<section class="section section-soft">
 <div class="max-w-6xl mx-auto grid md:grid-cols-2 gap-10 items-center">
  <div><p class="eyebrow">Rencanakan penghasilan</p><h2 class="text-3xl font-extrabold text-ink mt-3">Kalkulator estimasi upah.</h2><p class="text-sm text-slate-500 leading-7 mt-3">Hitung perkiraan pendapatan berdasarkan tarif per jam dan jumlah jam kerja. Hasil merupakan simulasi, bukan penawaran upah resmi.</p></div>
  <form id="wageCalculator" class="surface-card p-6 grid grid-cols-2 gap-4">
   <label class="text-xs font-semibold text-slate-600 col-span-2">Tarif per jam (Rp)<input id="rateHour" class="form-control mt-2" type="number" min="1" placeholder="Contoh: 25000" required></label>
   <label class="text-xs font-semibold text-slate-600">Jam per hari<input id="hoursDay" class="form-control mt-2" type="number" min="1" max="24" value="8" required></label>
   <label class="text-xs font-semibold text-slate-600">Hari per bulan<input id="daysMonth" class="form-control mt-2" type="number" min="1" max="31" value="22" required></label>
   <button class="btn-primary col-span-2" type="submit">Hitung estimasi</button>
   <div class="col-span-2 bg-teal-50 rounded-xl p-4"><p class="text-xs text-teal-800">Estimasi pendapatan per bulan</p><p id="wageResult" class="text-xl font-extrabold text-teal mt-1">Rp0</p></div>
  </form>
 </div>
</section>
<section class="section"><div class="max-w-6xl mx-auto rounded-3xl bg-teal-50 border border-teal-100 p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-5"><div><h2 class="text-2xl font-extrabold text-ink">Siap membuat langkahmu lebih berarti?</h2><p class="text-sm text-slate-500 mt-2">Daftar gratis dan mulai temukan peluang yang sesuai.</p></div><a href="<?= BASE_URL ?>/auth/register.php" class="btn-primary !bg-ink">Mulai sekarang →</a></div></section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
