<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/tahap4.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    exit('Jasa tidak ditemukan.');
}

$q = $pdo->prepare("SELECT j.*, u.nama AS pekerja, u.lokasi AS lokasi_pekerja, u.deskripsi AS deskripsi_pekerja, u.skills, u.telepon, u.is_verified
                    FROM jasa j
                    JOIN users u ON u.id = j.pekerja_id
                    WHERE j.id = ? AND j.status_publikasi = 'Aktif'
                    LIMIT 1");
$q->execute([$id]);
$service = $q->fetch();

if (!$service) {
    http_response_code(404);
    exit('Jasa tidak ditemukan atau tidak aktif.');
}

$user = current_user();

$q = $pdo->prepare('SELECT * FROM paket_jasa WHERE jasa_id = ? AND is_aktif = 1 ORDER BY FIELD(nama_paket, \'Basic\', \'Standard\', \'Premium\'), harga');
$q->execute([$id]);
$packages = $q->fetchAll();

$rating = ['avg' => 0, 'count' => 0];
$reviews = [];
try {
    $rating = jasa_rating_summary($pdo, $id);
    $q = $pdo->prepare('SELECT r.rating, r.komentar, r.created_at, u.nama AS pembeli
                        FROM ulasan r
                        JOIN users u ON u.id = r.pembeli_id
                        WHERE r.jasa_id = ?
                        ORDER BY r.id DESC
                        LIMIT 20');
    $q->execute([$id]);
    $reviews = $q->fetchAll();
} catch (Throwable $e) {
    error_log($e->getMessage());
}

$pageTitle = 'Detail jasa ' . $service['nama_jasa'];
require __DIR__ . '/includes/header.php';
?>
<main class="dashboard-main">
    <a class="text-link" href="<?= BASE_URL ?>/marketplace.php">← Kembali ke marketplace</a>

    <section class="dashboard-panel mt-5">
        <div class="grid md:grid-cols-[1fr_300px] gap-8">
            <article>
                <p class="eyebrow"><?= e($service['kategori']) ?> · <?= e($service['lokasi']) ?></p>
                <h1 class="dashboard-title"><?= e($service['nama_jasa']) ?></h1>
                <p class="text-sm text-slate-500 mt-2">
                    Oleh <?= e($service['pekerja']) ?>
                    <?php if ($service['is_verified']): ?>
                    · Akun terverifikasi
                    <?php endif; ?>
                </p>
                <p class="mt-3 text-sm">★ <?= number_format((float)$rating['avg'], 1) ?> · <?= (int)$rating['count'] ?>
                    ulasan</p>

                <h2 class="font-bold text-xl mt-8">Tentang jasa</h2>
                <p class="whitespace-pre-line leading-7 mt-3"><?= e($service['deskripsi']) ?></p>

                <?php if ($service['deskripsi_pekerja']): ?>
                <p class="mt-4 text-sm text-slate-500"><?= e($service['deskripsi_pekerja']) ?></p>
                <?php endif; ?>

                <?php if ($service['skills']): ?>
                <p class="mt-3 text-sm"><b>Keahlian:</b> <?= e($service['skills']) ?></p>
                <?php endif; ?>
            </article>

            <aside class="bg-slate-50 rounded-xl p-5 h-fit">
                <p class="text-xs text-slate-500">Harga mulai</p>
                <b
                    class="text-2xl text-ink"><?= rupiah($packages ? min(array_column($packages, 'harga')) : $service['harga_mulai']) ?></b>
                <p class="text-sm text-slate-500 mt-3">Pilih paket atau ajukan penawaran harga custom melalui chat.</p>

                <?php if ($user && $user['role'] === 'pekerja' && (int)$user['id'] === (int)$service['pekerja_id']): ?>
                <a class="btn-primary mt-4 block text-center" href="<?= BASE_URL ?>/pekerja/paket-jasa.php">Kelola
                    jasa</a>
                <?php elseif (!$user): ?>
                <a class="btn-primary mt-4 block text-center" href="<?= BASE_URL ?>/auth/login.php">Masuk untuk
                    memesan</a>
                <?php elseif ($user['role'] === 'penyedia'): ?>
                <form method="post" action="<?= BASE_URL ?>/pesan/mulai.php" class="mt-4">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="jasa_id" value="<?= $id ?>">
                    <button class="btn-secondary w-full">Negosiasikan harga via chat</button>
                </form>
                <?php endif; ?>
            </aside>
        </div>

        <h2 class="font-bold text-xl mt-10 mb-4">Pilih paket layanan</h2>
        <?php if ($packages): ?>
        <div class="grid md:grid-cols-3 gap-4">
            <?php foreach ($packages as $p): ?>
            <article class="border rounded-xl p-5 flex flex-col">
                <p class="eyebrow"><?= e($p['nama_paket']) ?></p>
                <h3 class="font-bold text-lg mt-2"><?= e($p['judul_paket']) ?></h3>
                <b class="text-2xl mt-3"><?= rupiah($p['harga']) ?></b>
                <p class="text-sm text-slate-500 mt-2"><?= (int)$p['estimasi_hari'] ?> hari ·
                    <?= (int)$p['jumlah_revisi'] ?> revisi</p>
                <p class="text-sm whitespace-pre-line mt-4 flex-1"><?= e($p['deskripsi']) ?></p>

                <?php if ($user && $user['role'] === 'penyedia'): ?>
                <form method="post" action="<?= BASE_URL ?>/penyedia/pesan-paket.php" class="mt-5">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="paket_id" value="<?= (int)$p['id'] ?>">
                    <textarea class="form-control mb-2" name="catatan" maxlength="2000" rows="2"
                        placeholder="Catatan kebutuhan (opsional)"></textarea>
                    <button class="btn-primary w-full">Pesan paket ini</button>
                </form>
                <?php elseif (!$user): ?>
                <a class="btn-primary mt-5 text-center" href="<?= BASE_URL ?>/auth/login.php">Masuk untuk memesan</a>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">Pekerja belum menambahkan paket. Silakan hubungi melalui chat untuk meminta penawaran
            custom.</div>
        <?php endif; ?>

        <h2 class="font-bold text-xl mt-10 mb-4">Rating &amp; ulasan</h2>
        <?php foreach ($reviews as $r): ?>
        <div class="border-t py-3">
            <b>★ <?= (int)$r['rating'] ?>/5 · <?= e($r['pembeli']) ?></b>
            <p class="text-sm mt-1"><?= e($r['komentar'] ?? '') ?></p>
        </div>
        <?php endforeach; ?>
        <?php if (!$reviews): ?>
        <p class="text-sm text-slate-500">Belum ada ulasan.</p>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>