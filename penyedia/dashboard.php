<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';

require_login('penyedia');
$user = current_user();
$id = (int)$user['id'];

$q = $pdo->prepare('SELECT COUNT(*) FROM lowongan WHERE penyedia_id = ?');
$q->execute([$id]);
$totalLowongan = (int)$q->fetchColumn();

$q = $pdo->prepare("SELECT COUNT(*) FROM lowongan WHERE penyedia_id = ? AND status = 'Aktif'");
$q->execute([$id]);
$aktif = (int)$q->fetchColumn();

$q = $pdo->prepare('SELECT COUNT(*) FROM lamaran l JOIN lowongan lo ON lo.id = l.lowongan_id WHERE lo.penyedia_id = ?');
$q->execute([$id]);
$pelamar = (int)$q->fetchColumn();

$q = $pdo->prepare('SELECT lo.id, lo.judul, lo.status, COUNT(l.id) AS jumlah_pelamar
                    FROM lowongan lo
                    LEFT JOIN lamaran l ON l.lowongan_id = lo.id
                    WHERE lo.penyedia_id = ?
                    GROUP BY lo.id
                    ORDER BY lo.created_at DESC
                    LIMIT 6');
$q->execute([$id]);
$jobs = $q->fetchAll();

// Tahap 3: ringkasan pesanan jasa (sebagai pembeli), pesan, dan notifikasi.
require_once __DIR__ . '/../config/stage3.php';
$ordersWait = $ordersActive = $ordersToReview = 0;
try {
    $q = $pdo->prepare('SELECT status, COUNT(*) AS n FROM pesanan WHERE pembeli_id = ? GROUP BY status');
    $q->execute([$id]);
    foreach ($q->fetchAll() as $r) {
        if ($r['status'] === 'Menunggu') {
            $ordersWait = (int)$r['n'];
        } elseif (in_array($r['status'], ['Dikonfirmasi', 'Dikerjakan'], true)) {
            $ordersActive += (int)$r['n'];
        }
    }
    $q = $pdo->prepare("SELECT COUNT(*) FROM pesanan o LEFT JOIN ulasan r ON r.pesanan_id = o.id WHERE o.pembeli_id = ? AND o.status = 'Selesai' AND r.id IS NULL");
    $q->execute([$id]);
    $ordersToReview = (int)$q->fetchColumn();
} catch (Throwable $e) {
    error_log('dashboard penyedia tahap3: ' . $e->getMessage());
}

$unreadMsg = unread_message_count($pdo, $id);
$unreadNotif = unread_notification_count($pdo, $id);

$pageTitle = 'Dashboard Penyedia';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Ruang mitra usaha</p>
    <h1 class="dashboard-title">Selamat datang, <?= e($user['nama']) ?>!</h1>
    <p class="text-sm text-slate-500 mt-2">Kelola kebutuhan tenaga kerja dan pantau kandidat usahamu.</p>

    <section class="grid sm:grid-cols-3 gap-4 mt-7">
        <div class="surface-card p-5">
            <p class="text-xs text-slate-500">Total lowongan</p>
            <strong class="block text-3xl text-ink mt-2"><?= $totalLowongan ?></strong>
        </div>
        <div class="surface-card p-5">
            <p class="text-xs text-slate-500">Lowongan aktif</p>
            <strong class="block text-3xl text-ink mt-2"><?= $aktif ?></strong>
        </div>
        <div class="surface-card p-5">
            <p class="text-xs text-slate-500">Total pelamar</p>
            <strong class="block text-3xl text-ink mt-2"><?= $pelamar ?></strong>
        </div>
    </section>

    <section class="grid sm:grid-cols-3 gap-4 mt-4">
        <a class="surface-card p-5" href="<?= BASE_URL ?>/penyedia/pesanan.php?status=Menunggu">
            <p class="text-xs text-slate-500">Pesanan jasa menunggu pekerja</p>
            <strong class="block text-3xl text-ink mt-2"><?= $ordersWait ?></strong>
        </a>
        <a class="surface-card p-5" href="<?= BASE_URL ?>/penyedia/pesanan.php">
            <p class="text-xs text-slate-500">Pesanan jasa berjalan</p>
            <strong class="block text-3xl text-ink mt-2"><?= $ordersActive ?></strong>
        </a>
        <a class="surface-card p-5" href="<?= BASE_URL ?>/penyedia/pesanan.php?status=Selesai">
            <p class="text-xs text-slate-500">Menunggu ulasan Anda</p>
            <strong class="block text-3xl text-ink mt-2"><?= $ordersToReview ?></strong>
        </a>
    </section>

    <section class="dashboard-panel mt-7">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2>Menu penyedia</h2>
            <a class="btn-primary" href="<?= BASE_URL ?>/penyedia/lowongan.php">Kelola lowongan</a>
        </div>
        <div class="dashboard-grid mt-4">
            <a class="surface-card p-5" href="<?= BASE_URL ?>/penyedia/kandidat.php">
                <b>Kelola kandidat</b>
                <p class="text-xs text-slate-500 mt-2">Tinjau pelamar dan perbarui status seleksi.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/penyedia/pesanan.php">
                <b>Pesanan jasa saya</b>
                <p class="text-xs text-slate-500 mt-2">Pantau pesanan, batalkan, dan beri ulasan.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/jasa.php">
                <b>Cari &amp; pesan jasa</b>
                <p class="text-xs text-slate-500 mt-2">Temukan talenta untuk kebutuhan usahamu.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/pesan/index.php">
                <b>Pesan<?php if ($unreadMsg): ?> <span class="nav-badge"><?= $unreadMsg ?></span><?php endif; ?></b>
                <p class="text-xs text-slate-500 mt-2">Chat dengan pekerja.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/notifikasi.php">
                <b>Notifikasi<?php if ($unreadNotif): ?> <span
                        class="nav-badge"><?= $unreadNotif ?></span><?php endif; ?></b>
                <p class="text-xs text-slate-500 mt-2">Status pesanan dan pesan terbaru.</p>
            </a>
            <a class="surface-card p-5" href="<?= BASE_URL ?>/penyedia/profil.php">
                <b>Profil usaha</b>
                <p class="text-xs text-slate-500 mt-2">Perbarui informasi usaha.</p>
            </a>
        </div>
    </section>

    <section class="dashboard-panel mt-6">
        <h2>Lowongan terbaru</h2>
        <?php if ($jobs): ?>
        <div class="table-wrapper mt-4">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Posisi</th>
                        <th>Status</th>
                        <th>Pelamar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                    <tr>
                        <th>
                            <a class="text-link"
                                href="<?= BASE_URL ?>/detail-lowongan.php?id=<?= (int)$job['id'] ?>"><?= e($job['judul']) ?></a>
                        </th>
                        <td><?= e($job['status']) ?></td>
                        <td><?= (int)$job['jumlah_pelamar'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state mt-4">Belum ada lowongan. Buat lowongan pertamamu untuk mulai menjaring kandidat.</div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>