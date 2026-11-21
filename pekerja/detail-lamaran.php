<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';

require_login('pekerja');
$user = current_user();
$id = (int)($_GET['id'] ?? 0);

$q = $pdo->prepare('SELECT l.*, lo.judul, lo.kategori, lo.lokasi, lo.jenis_pekerjaan, lo.deskripsi, lo.persyaratan, u.nama AS penyedia, u.email AS email_penyedia
                    FROM lamaran l
                    JOIN lowongan lo ON lo.id = l.lowongan_id
                    JOIN users u ON u.id = lo.penyedia_id
                    WHERE l.id = ? AND l.pekerja_id = ?');
$q->execute([$id, (int)$user['id']]);
$item = $q->fetch();

if (!$item) {
    http_response_code(404);
    exit('Lamaran tidak ditemukan.');
}

$q = $pdo->prepare('SELECT r.*, u.nama AS pengubah
                    FROM riwayat_lamaran r
                    LEFT JOIN users u ON u.id = r.diubah_oleh
                    WHERE r.lamaran_id = ?
                    ORDER BY r.created_at DESC, r.id DESC');
$q->execute([$id]);
$history = $q->fetchAll();

$pageTitle = 'Detail Lamaran';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Pekerja / Lamaran</p>
    <h1 class="dashboard-title">Detail lamaran</h1>

    <section class="dashboard-panel mt-5">
        <div class="flex flex-wrap justify-between gap-3">
            <div>
                <h2><?= e($item['judul']) ?></h2>
                <p class="text-sm text-slate-500 mt-1"><?= e($item['penyedia']) ?> · <?= e($item['lokasi']) ?> · <?= e($item['kategori']) ?></p>
            </div>
            <span class="badge-green"><?= e($item['status']) ?></span>
        </div>

        <p class="text-xs text-slate-500 mt-3">Dikirim pada <?= e(date('d M Y H:i', strtotime($item['tanggal_lamar']))) ?></p>

        <h3 class="font-bold text-ink mt-6">Deskripsi pekerjaan</h3>
        <p class="text-sm mt-2"><?= nl2br(e($item['deskripsi'])) ?></p>

        <?php if ($item['persyaratan']): ?>
            <h3 class="font-bold text-ink mt-5">Persyaratan</h3>
            <p class="text-sm mt-2"><?= nl2br(e($item['persyaratan'])) ?></p>
        <?php endif; ?>

        <h3 class="font-bold text-ink mt-5">Pesan saat melamar</h3>
        <p class="text-sm mt-2"><?= nl2br(e($item['pesan_singkat'] ?: 'Tidak ada pesan tambahan.')) ?></p>
    </section>

    <section class="dashboard-panel mt-6">
        <h2>Riwayat perubahan status</h2>
        <?php if ($history): ?>
            <ol class="mt-4 space-y-4">
                <?php foreach ($history as $h): ?>
                    <li class="border-l-2 border-teal pl-4">
                        <p class="font-semibold text-ink"><?= e($h['status_sebelumnya']) ?> → <?= e($h['status_terbaru']) ?></p>
                        <p class="text-xs text-slate-500 mt-1"><?= e(date('d M Y H:i', strtotime($h['created_at']))) ?> · <?= e($h['pengubah'] ?? 'Sistem') ?></p>
                        <?php if ($h['catatan']): ?>
                            <p class="text-sm mt-2"><?= nl2br(e($h['catatan'])) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <div class="empty-state mt-4">Belum ada perubahan status yang dicatat. Status awal lamaran: Menunggu.</div>
        <?php endif; ?>
    </section>

    <a class="text-link inline-block mt-5" href="<?= BASE_URL ?>/pekerja/lamaran.php">← Kembali ke lamaran</a>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
