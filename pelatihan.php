<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/functions.php';
$keyword = trim($_GET['q'] ?? '');
if ($keyword !== '') {
    $stmt = $pdo->prepare('SELECT p.*, u.nama AS nama_penyedia FROM pelatihan p JOIN users u ON u.id=p.penyedia_id WHERE p.judul_pelatihan LIKE ? OR p.kategori LIKE ? OR p.deskripsi LIKE ? ORDER BY p.created_at DESC');
    $like = '%' . $keyword . '%';
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query('SELECT p.*, u.nama AS nama_penyedia FROM pelatihan p JOIN users u ON u.id=p.penyedia_id ORDER BY p.created_at DESC');
}
$items = $stmt->fetchAll();
$pageTitle = 'Pelatihan';
require __DIR__ . '/includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Belajar dan berkembang</p>
    <h1 class="dashboard-title">Pelatihan keterampilan</h1>
    <p class="text-slate-500 mb-6">Temukan materi belajar dari penyedia lokal untuk mengembangkan keterampilanmu.</p>
    <form method="get" class="flex flex-wrap gap-2 mb-6"><input class="form-control flex-1 min-w-[220px]" name="q"
            value="<?= e($keyword) ?>" placeholder="Cari judul, kategori, atau materi"><button class="btn-primary"
            type="submit">Cari</button><?php if ($keyword !== ''): ?><a class="btn-secondary"
            href="<?= BASE_URL ?>/pelatihan.php">Reset</a><?php endif; ?></form>
    <?php if ($items): ?><div class="card-grid">
        <?php foreach ($items as $item): ?><article class="job-card">
            <p class="eyebrow"><?= e($item['kategori']) ?></p>
            <h2 class="font-bold text-xl text-ink mt-2"><?= e($item['judul_pelatihan']) ?></h2>
            <p class="text-sm text-slate-500 mt-2">Penyedia: <?= e($item['nama_penyedia']) ?></p>
            <p class="text-sm leading-6 mt-4"><?= e($item['deskripsi']) ?></p>
            <div class="mt-5 flex flex-wrap justify-between gap-2 text-sm"><span><?= (int)$item['jumlah_modul'] ?>
                    modul</span><span class="badge-green">Badge: <?= e($item['nama_badge']) ?></span></div>
        </article><?php endforeach; ?>
    </div><?php else: ?><div class="empty-state">Belum ada pelatihan yang sesuai.</div><?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>