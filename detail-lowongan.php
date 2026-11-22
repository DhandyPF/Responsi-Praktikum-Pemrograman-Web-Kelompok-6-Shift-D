<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/functions.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(404);
    exit('Lowongan tidak ditemukan.');
}

$stmt = $pdo->prepare("SELECT l.*, u.nama AS penyedia, u.lokasi AS lokasi_penyedia, u.deskripsi AS deskripsi_penyedia, u.is_verified
                       FROM lowongan l
                       JOIN users u ON u.id = l.penyedia_id
                       WHERE l.id = :id
                       LIMIT 1");
$stmt->execute(['id' => $id]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    exit('Lowongan tidak ditemukan atau sudah dihapus.');
}

$user = current_user();
$alreadyApplied = false;
if ($user && $user['role'] === 'pekerja') {
    $check = $pdo->prepare('SELECT id FROM lamaran WHERE lowongan_id = ? AND pekerja_id = ? LIMIT 1');
    $check->execute([$id, (int)$user['id']]);
    $alreadyApplied = (bool)$check->fetchColumn();
}

$expired = !empty($job['batas_lamaran']) && $job['batas_lamaran'] < date('Y-m-d');
$canApply = $job['status'] === 'Aktif' && !$expired;

$pageTitle = 'Detail ' . $job['judul'];
require __DIR__ . '/includes/header.php';
?>
<main class="max-w-5xl mx-auto px-5 py-12 min-h-[65vh]">
    <a class="text-link" href="<?= BASE_URL ?>/lowongan.php">← Kembali ke Bursa Kerja</a>

    <section class="surface-card p-6 md:p-9 mt-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow">Detail lowongan pekerjaan</p>
                <h1 class="text-3xl md:text-4xl font-extrabold text-ink mt-3"><?= e($job['judul']) ?></h1>
                <div class="flex flex-wrap gap-2 mt-4">
                    <span class="badge-green"><?= e($job['kategori']) ?></span>
                    <span class="badge-green"><?= e($job['jenis_pekerjaan']) ?></span>
                    <span class="badge-green"><?= e($job['lokasi']) ?></span>
                </div>
            </div>
            <span
                class="<?= $canApply ? 'badge-green' : 'badge-amber' ?>"><?= $canApply ? 'Sedang dibuka' : 'Tidak menerima lamaran' ?></span>
        </div>

        <div class="grid md:grid-cols-3 gap-4 mt-8">
            <div class="p-4 rounded-xl bg-slate-50">
                <p class="text-xs text-slate-500">Rentang gaji</p>
                <b
                    class="block text-ink mt-2"><?= $job['gaji_min'] !== null ? rupiah($job['gaji_min']) : 'Negosiasi' ?><?= $job['gaji_max'] !== null ? ' – ' . rupiah($job['gaji_max']) : '' ?></b>
            </div>
            <div class="p-4 rounded-xl bg-slate-50">
                <p class="text-xs text-slate-500">Lokasi kerja</p>
                <b class="block text-ink mt-2"><?= e($job['lokasi']) ?></b>
            </div>
            <div class="p-4 rounded-xl bg-slate-50">
                <p class="text-xs text-slate-500">Batas lamaran</p>
                <b
                    class="block text-ink mt-2"><?= $job['batas_lamaran'] ? e(date('d F Y', strtotime($job['batas_lamaran']))) : 'Tidak ditentukan' ?></b>
            </div>
        </div>

        <div class="grid md:grid-cols-[1fr_280px] gap-9 mt-9">
            <article>
                <h2 class="text-xl font-bold text-ink">Deskripsi pekerjaan</h2>
                <div class="text-sm leading-7 text-slate-600 mt-3 whitespace-pre-line"><?= e($job['deskripsi']) ?></div>

                <h2 class="text-xl font-bold text-ink mt-8">Persyaratan</h2>
                <div class="text-sm leading-7 text-slate-600 mt-3 whitespace-pre-line">
                    <?= e($job['persyaratan'] ?: 'Persyaratan akan diinformasikan oleh penyedia.') ?></div>
            </article>

            <aside class="surface-card p-5 h-fit">
                <p class="text-xs text-slate-500">Dipublikasikan oleh</p>
                <div class="flex items-center gap-3 mt-3">
                    <div class="company-icon"><?= e(strtoupper(substr($job['penyedia'], 0, 1))) ?></div>
                    <div>
                        <b class="text-ink"><?= e($job['penyedia']) ?></b>
                        <?php if ($job['is_verified']): ?>
                        <p class="text-xs text-emerald-700 mt-1">✓ Penyedia terverifikasi</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($job['deskripsi_penyedia']): ?>
                <p class="text-xs leading-6 text-slate-500 mt-4"><?= e($job['deskripsi_penyedia']) ?></p>
                <?php endif; ?>

                <?php if ($user && $user['role'] === 'pekerja'): ?>
                <?php if ($alreadyApplied): ?>
                <div class="alert alert-success mt-5">Kamu sudah mengirim lamaran untuk lowongan ini.</div>
                <a class="btn-primary block text-center mt-3" href="<?= BASE_URL ?>/pekerja/lamaran.php">Lihat
                    lamaran</a>
                <?php elseif ($canApply): ?>
                <form method="post" action="<?= BASE_URL ?>/pekerja/lamar.php" class="mt-5">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="lowongan_id" value="<?= (int)$job['id'] ?>">
                    <button class="btn-primary w-full" type="submit">Kirim lamaran →</button>
                </form>
                <?php else: ?>
                <p class="text-xs text-slate-500 mt-5">Lowongan ini sudah ditutup atau melewati batas lamaran.</p>
                <?php endif; ?>
                <?php elseif (!$user): ?>
                <a class="btn-primary block text-center mt-5" href="<?= BASE_URL ?>/auth/login.php">Masuk untuk
                    melamar</a>
                <?php else: ?>
                <p class="text-xs text-slate-500 mt-5">Akun penyedia dan admin tidak dapat melamar pekerjaan.</p>
                <?php endif; ?>
            </aside>
        </div>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>