<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/tahap4.php';
require_once __DIR__ . '/../config/stage2.php';

require_login('pekerja');
$u = current_user();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['nama_jasa'] ?? ''));
        $desc = trim((string)($_POST['deskripsi'] ?? ''));
        $loc = trim((string)($_POST['lokasi'] ?? ''));
        $cat = (string)($_POST['kategori'] ?? 'Lainnya');
        $price = (float)($_POST['harga_mulai'] ?? 0);

        if ($name === '' || $desc === '' || $loc === '' || $price <= 0 || mb_strlen($name) > 150 || mb_strlen($desc) > 5000) {
            throw new RuntimeException('Lengkapi semua data. Harga harus lebih dari nol.');
        }
        if (!in_array($cat, ['Desain', 'Konten', 'Teknologi', 'Administrasi', 'Lainnya'], true)) {
            $cat = 'Lainnya';
        }

        $newImg = save_portfolio_image($_FILES['gambar'] ?? []);

        if ($id) {
            $q = $pdo->prepare('UPDATE jasa SET nama_jasa = ?, kategori = ?, lokasi = ?, harga_mulai = ?, deskripsi = ? WHERE id = ? AND pekerja_id = ?');
            $q->execute([$name, $cat, $loc, $price, $desc, $id, $u['id']]);
        } else {
            $q = $pdo->prepare("INSERT INTO jasa (pekerja_id, nama_jasa, kategori, lokasi, harga_mulai, deskripsi, status_publikasi) VALUES (?, ?, ?, ?, ?, ?, 'Aktif')");
            $q->execute([$u['id'], $name, $cat, $loc, $price, $desc]);
            $id = (int)$pdo->lastInsertId();
        }

        if ($newImg) {
            $oldImgQ = $pdo->prepare('SELECT file_path FROM jasa_gambar WHERE jasa_id = ? AND is_utama = 1');
            $oldImgQ->execute([$id]);
            foreach ($oldImgQ->fetchAll() as $old) {
                delete_portfolio_image($old['file_path']);
            }
            $pdo->prepare('DELETE FROM jasa_gambar WHERE jasa_id = ? AND is_utama = 1')->execute([$id]);
            $pdo->prepare('INSERT INTO jasa_gambar (jasa_id, file_path, is_utama, urutan) VALUES (?, ?, 1, 0)')->execute([$id, $newImg]);
        }

        t4_audit($pdo, (int)$u['id'], 'service.saved', 'jasa', $id);
        t4_flash_redirect('Jasa berhasil disimpan.', '/pekerja/kelola-jasa.php');
    } catch (Throwable $e) {
        $err = $e instanceof RuntimeException ? $e->getMessage() : 'Gagal menyimpan jasa.';
        error_log('manage service: ' . $e->getMessage());
    }
}

$q = $pdo->prepare('SELECT * FROM jasa WHERE pekerja_id = ? ORDER BY created_at DESC');
$q->execute([$u['id']]);
$services = $q->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    foreach ($services as $s) {
        if ((int)$s['id'] === (int)$_GET['edit']) {
            $edit = $s;
        }
    }
}

$pageTitle = 'Kelola Jasa';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main manage-page">
    <p class="eyebrow">DASHBOARD PEKERJA</p>
    <h1 class="dashboard-title">Kelola jasa</h1>
    <p class="market-lead">Tampilkan layanan yang kamu tawarkan. Harga yang dicantumkan adalah harga awal; rincian akhir
        bisa disepakati melalui penawaran.</p>

    <?php if ($err): ?>
    <div class="alert alert-error mb-4"><?= e($err) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel mb-6">
        <h2><?= $edit ? 'Perbarui layanan' : 'Tambahkan layanan baru' ?></h2>
        <form method="post" enctype="multipart/form-data" class="dashboard-form mt-4">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

            <label>Nama layanan
                <input class="form-control" name="nama_jasa" maxlength="150" required
                    value="<?= e($edit['nama_jasa'] ?? '') ?>" placeholder="Contoh: Desain logo untuk UMKM">
            </label>

            <label>Kategori
                <select class="form-control" name="kategori">
                    <?php foreach (['Desain', 'Konten', 'Teknologi', 'Administrasi', 'Lainnya'] as $c): ?>
                    <option value="<?= e($c) ?>" <?= (($edit['kategori'] ?? '') === $c ? 'selected' : '') ?>>
                        <?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Lokasi
                <input class="form-control" name="lokasi" required
                    value="<?= e($edit['lokasi'] ?? $u['lokasi'] ?? '') ?>">
            </label>

            <label>Foto jasa (opsional)
                <input class="form-control" type="file" name="gambar" accept="image/jpeg,image/png,image/webp">
            </label>

            <label>Harga mulai (Rp)
                <input class="form-control" type="number" name="harga_mulai" min="1" step="1000" required
                    value="<?= e((string)($edit['harga_mulai'] ?? '')) ?>">
            </label>

            <label class="dashboard-span">Deskripsi layanan
                <textarea class="form-control" name="deskripsi" rows="5" maxlength="5000"
                    required><?= e($edit['deskripsi'] ?? '') ?></textarea>
            </label>

            <div class="dashboard-span flex flex-wrap gap-2">
                <button class="btn-primary"><?= $edit ? 'Simpan perubahan' : 'Publikasikan jasa' ?></button>
                <?php if ($edit): ?>
                <a class="btn-secondary" href="<?= BASE_URL ?>/pekerja/kelola-jasa.php">Batal</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="dashboard-panel">
        <h2>Layanan yang kamu tampilkan</h2>
        <div class="service-manage-list mt-4">
            <?php foreach ($services as $s): ?>
            <article>
                <div>
                    <strong><?= e($s['nama_jasa']) ?></strong>
                    <p><?= e($s['kategori']) ?> · <?= rupiah($s['harga_mulai']) ?> · <?= e($s['lokasi']) ?></p>
                    <span class="status-chip"><?= e($s['status_publikasi'] ?? 'Aktif') ?></span>
                </div>
                <a class="btn-secondary" href="?edit=<?= (int)$s['id'] ?>">Edit</a>
            </article>
            <?php endforeach; ?>
            <?php if (!$services): ?>
            <p class="empty-state">Kamu belum memasang layanan.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>