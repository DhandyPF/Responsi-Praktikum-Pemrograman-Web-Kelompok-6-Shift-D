<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';

require_login('pekerja');
$user = current_user();
$id = (int)$user['id'];

$q = $pdo->prepare('SELECT COUNT(*) FROM lamaran WHERE pekerja_id = ?');
$q->execute([$id]);
$totalLamaran = (int)$q->fetchColumn();

$q = $pdo->prepare('SELECT COUNT(*) FROM lamaran WHERE pekerja_id = ? AND status = ?');
$q->execute([$id, 'Diterima']);
$diterima = (int)$q->fetchColumn();

$q = $pdo->prepare('SELECT COUNT(*) FROM jasa WHERE pekerja_id = ?');
$q->execute([$id]);
$totalJasa = (int)$q->fetchColumn();

$q = $pdo->prepare('SELECT l.status, l.tanggal_lamar, lo.id AS lowongan_id, lo.judul, u.nama AS penyedia
                    FROM lamaran l
                    JOIN lowongan lo ON lo.id = l.lowongan_id
                    JOIN users u ON u.id = lo.penyedia_id
                    WHERE l.pekerja_id = ?
                    ORDER BY l.tanggal_lamar DESC
                    LIMIT 5');
$q->execute([$id]);
$recent = $q->fetchAll();

// Tahap 3: ringkasan pesanan, pesan, dan notifikasi (aman bila migrasi 003 belum dijalankan).
require_once __DIR__ . '/../config/stage3.php';
$ordersNew = $ordersActive = $ordersDone = 0;
try {
    $q = $pdo->prepare('SELECT status, COUNT(*) AS n FROM pesanan WHERE pekerja_id = ? GROUP BY status');
    $q->execute([$id]);
    foreach ($q->fetchAll() as $r) {
        if ($r['status'] === 'Menunggu') {
            $ordersNew = (int)$r['n'];
        } elseif (in_array($r['status'], ['Dikonfirmasi', 'Dikerjakan'], true)) {
            $ordersActive += (int)$r['n'];
        } elseif ($r['status'] === 'Selesai') {
            $ordersDone = (int)$r['n'];
        }
    }
    $q = $pdo->prepare('SELECT COUNT(*) AS n, AVG(rating) AS a FROM ulasan WHERE pekerja_id = ?');
    $q->execute([$id]);
    $rv = $q->fetch();
    $reviewCount = (int)($rv['n'] ?? 0);
    $reviewAvg = $rv && $rv['a'] !== null ? (float)$rv['a'] : 0.0;
} catch (Throwable $e) {
    error_log('dashboard pekerja tahap3: ' . $e->getMessage());
    $reviewCount = 0;
    $reviewAvg = 0.0;
}

$unreadMsg = unread_message_count($pdo, $id);
$unreadNotif = unread_notification_count($pdo, $id);

$pageTitle = 'Dashboard Pekerja';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Ruang talenta</p>
    <h1 class="dashboard-title">Halo, <?= e($user['nama']) ?>!</h1>
    <p class="text-sm text-slate-500 mt-2">Pantau peluang, lamaran, dan jasa yang kamu tawarkan.</p>

    <section class="grid sm:grid-cols-3 gap-4 mt-7">
        <div class="surface-card p-5">
            <p class="text-xs text-slate-500">Total lamaran</p>
            <strong class="block text-3xl text-ink mt-2"><?= $totalLamaran ?></strong>
        </div>
        <div class="surface-card p-5">
            <p class="text-xs text-slate-500">Lamaran diterima</p>
            <strong class="block text-3xl text-ink mt-2"><?= $diterima ?></strong>
        </div>
        <div class="surface-card p-5">
            <p class="text-xs text-slate-500">Jasa dipublikasikan</p>
            <strong class="block text-3xl text-ink mt-2"><?= $totalJasa ?></strong>
        </div>
    </section>

    <section class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
        <a class="surface-card p-5" href="<?= BASE_URL ?>/pekerja/pesanan.php?status=Menunggu">
            <p class="text-xs text-slate-500">Pesanan menunggu konfirmasi</p>
            <strong class="block text-3xl text-ink mt-2"><?= $ordersNew ?></strong>
        </a>
        <a class="surface-card p-5" href="<?= BASE_URL ?>/pekerja/pesanan.php">
            <p class="text-xs text-slate-500">Pesanan berjalan</p>
            <strong class="block text-3xl text-ink mt-2"><?= $ordersActive ?></strong>
        </a>
        <a class="surface-card p-5" href="<?= BASE_URL ?>/pekerja/pesanan.php?status=Selesai">
            <p class="text-xs text-slate-500">Pesanan selesai</p>
            <strong class="block text-3xl text-ink mt-2"><?= $ordersDone ?></strong>
        </a>
        <div class="surface-card p-5">
            <p class="text-xs text-slate-500">Rating rata-rata</p>
            <strong
                class="block text-3xl text-ink mt-2"><?= $reviewCount ? number_format($reviewAvg, 1, ',', '.') . ' ★' : '–' ?></strong>
            <p class="text-xs text-slate-500 mt-1"><?= $reviewCount ?> ulasan</p>
        </div>
    </section>

    <section class="dashboard-panel mt-7">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2>Menu pekerja</h2>
            <a class="text-link" href="<?= BASE_URL ?>/lowongan.php">Cari lowongan →</a>
        </div>
        <div class="dashboard-grid mt-4">
            <a class="surface-card p-5" href="<?= BASE_URL ?>/pekerja/profil.php">
                <b>Profil saya</b>
                <p class="text-xs text-slate-500 mt-2">Perbarui skill dan informasi diri.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/pekerja/lamaran.php">
                <b>Lamaran saya</b>
                <p class="text-xs text-slate-500 mt-2">Pantau proses lamaran pekerjaan.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/pekerja/portfolio.php">
                <b>Portfolio saya</b>
                <p class="text-xs text-slate-500 mt-2">Kelola karya, tautan, dan gambar hasil pekerjaan.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/pekerja/jasa.php">
                <b>Kelola jasa</b>
                <p class="text-xs text-slate-500 mt-2">Publikasikan keahlian yang kamu tawarkan.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/pekerja/pesanan.php">
                <b>Pesanan masuk</b>
                <p class="text-xs text-slate-500 mt-2">Terima dan perbarui status pesanan jasa.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/pesan/index.php">
                <b>Pesan<?php if ($unreadMsg): ?> <span class="nav-badge"><?= $unreadMsg ?></span><?php endif; ?></b>
                <p class="text-xs text-slate-500 mt-2">Chat dengan pembeli jasa.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/notifikasi.php">
                <b>Notifikasi<?php if ($unreadNotif): ?> <span
                        class="nav-badge"><?= $unreadNotif ?></span><?php endif; ?></b>
                <p class="text-xs text-slate-500 mt-2">Pesan, pesanan, dan ulasan terbaru.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/pekerja/rekomendasi.php">
                <b>Rekomendasi pekerjaan</b>
                <p class="text-xs text-slate-500 mt-2">Temukan peluang yang relevan.</p>
            </a>
        </div>
    </section>

    <section class="dashboard-panel mt-6">
        <h2>Aktivitas lamaran terbaru</h2>
        <?php if ($recent): ?>
        <div class="table-wrapper mt-4">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Lowongan</th>
                        <th>Penyedia</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $item): ?>
                    <tr>
                        <th>
                            <a class="text-link"
                                href="<?= BASE_URL ?>/detail-lowongan.php?id=<?= (int)$item['lowongan_id'] ?>"><?= e($item['judul']) ?></a>
                        </th>
                        <td><?= e($item['penyedia']) ?></td>
                        <td><?= e(date('d M Y', strtotime($item['tanggal_lamar']))) ?></td>
                        <td><?= e($item['status']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state mt-4">Kamu belum mengirim lamaran. Yuk, cari peluang pertamamu.</div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>