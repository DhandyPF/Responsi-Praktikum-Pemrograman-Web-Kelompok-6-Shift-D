<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/tahap4.php';

$search = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['kategori'] ?? ''));
$params = [];

$sql = "SELECT j.*, u.nama AS nama_pekerja, u.lokasi AS lokasi_pekerja,
        (SELECT MIN(p.harga) FROM paket_jasa p WHERE p.jasa_id = j.id AND p.is_aktif = 1) AS harga_paket,
        (SELECT AVG(r.rating) FROM ulasan r WHERE r.jasa_id = j.id) AS rating,
        (SELECT COUNT(*) FROM ulasan r WHERE r.jasa_id = j.id) AS total_ulasan
        FROM jasa j
        JOIN users u ON u.id = j.pekerja_id
        WHERE j.status_publikasi = 'Aktif'";

if ($search !== '') {
    $sql .= ' AND (j.nama_jasa LIKE ? OR j.deskripsi LIKE ? OR u.nama LIKE ?)';
    $v = '%' . $search . '%';
    array_push($params, $v, $v, $v);
}
if ($cat !== '' && in_array($cat, ['Desain', 'Konten', 'Teknologi', 'Administrasi', 'Lainnya'], true)) {
    $sql .= ' AND j.kategori = ?';
    $params[] = $cat;
}
$sql .= ' ORDER BY j.created_at DESC LIMIT 60';

$q = $pdo->prepare($sql);
$q->execute($params);
$items = $q->fetchAll();

$pageTitle = 'Marketplace Jasa';
require __DIR__ . '/includes/header.php';
?>
<main class="dashboard-main">
    <div class="flex flex-wrap items-end justify-between gap-4 mb-7">
        <div>
            <p class="eyebrow">NusaKarya Marketplace</p>
            <h1 class="dashboard-title">Temukan talenta lokal</h1>
            <p class="text-slate-500">Pilih jasa, bandingkan paket, lalu pesan dengan alur transaksi yang jelas.</p>
        </div>
        <?php if (current_user() && current_user()['role'] === 'pekerja'): ?>
        <a class="btn-primary" href="<?= BASE_URL ?>/pekerja/paket-jasa.php">Kelola jasa &amp; paket</a>
        <?php endif; ?>
    </div>

    <form class="dashboard-panel grid md:grid-cols-[1fr_220px_auto] gap-3 mb-6" method="get">
        <input class="form-control" name="q" placeholder="Cari jasa atau nama talenta..." value="<?= e($search) ?>">
        <select class="form-control" name="kategori">
            <option value="">Semua kategori</option>
            <?php foreach (['Desain', 'Konten', 'Teknologi', 'Administrasi', 'Lainnya'] as $c): ?>
            <option <?= ($cat === $c ? 'selected' : '') ?> value="<?= e($c) ?>"><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn-primary">Cari jasa</button>
    </form>

    <div class="card-grid">
        <?php foreach ($items as $x): ?>
        <article class="job-card">
            <div class="flex justify-between gap-2">
                <span class="eyebrow"><?= e($x['kategori']) ?></span>
                <span class="text-xs text-amber-600">★
                    <?= $x['rating'] ? number_format((float)$x['rating'], 1) : 'Baru' ?> <span
                        class="text-slate-400">(<?= (int)$x['total_ulasan'] ?>)</span></span>
            </div>
            <h2 class="font-bold text-ink text-lg mt-3"><?= e($x['nama_jasa']) ?></h2>
            <p class="text-sm text-slate-500 mt-1">oleh <?= e($x['nama_pekerja']) ?> · <?= e($x['lokasi']) ?></p>
            <p class="text-sm mt-3 line-clamp-3"><?= e(mb_strimwidth($x['deskripsi'], 0, 150, '…')) ?></p>
            <div class="mt-5 flex justify-between items-center">
                <div>
                    <p class="text-xs text-slate-500">Mulai dari</p>
                    <strong class="text-ink"><?= rupiah($x['harga_paket'] ?? $x['harga_mulai']) ?></strong>
                </div>
                <a class="btn-primary" href="<?= BASE_URL ?>/detail-jasa.php?id=<?= (int)$x['id'] ?>">Lihat detail</a>
            </div>
        </article>
        <?php endforeach; ?>
    </div>

    <?php if (!$items): ?>
    <div class="empty-state">Belum ada jasa yang cocok. Coba kata kunci atau kategori lain.</div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>