<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/stage2.php';

require_login('penyedia');
$user = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['delete'])) {
        $id = (int)$_POST['delete'];
        $q = $pdo->prepare('SELECT gambar FROM pelatihan WHERE id = ? AND penyedia_id = ?');
        $q->execute([$id, (int)$user['id']]);
        $oldImage = $q->fetchColumn();
        if ($oldImage) {
            delete_portfolio_image($oldImage);
        }
        $q = $pdo->prepare('DELETE FROM pelatihan WHERE id = ? AND penyedia_id = ?');
        $q->execute([$id, (int)$user['id']]);
        flash('success', 'Pelatihan berhasil dihapus.');
        redirect('/penyedia/pelatihan.php');
    }

    $id = (int)($_POST['id'] ?? 0);
    $judul = trim((string)($_POST['judul_pelatihan'] ?? ''));
    $kategori = (string)($_POST['kategori'] ?? 'Lainnya');
    $jumlahModul = max(1, (int)($_POST['jumlah_modul'] ?? 1));
    $namaBadge = trim((string)($_POST['nama_badge'] ?? ''));
    $deskripsi = trim((string)($_POST['deskripsi'] ?? ''));

    if (!in_array($kategori, ['Desain', 'Konten', 'Teknologi', 'Administrasi', 'Lainnya'], true)) {
        $kategori = 'Lainnya';
    }
    if ($judul === '' || $namaBadge === '' || $deskripsi === '' || mb_strlen($judul) > 160 || mb_strlen($namaBadge) > 120) {
        $error = 'Judul, nama badge, dan deskripsi wajib diisi dengan format yang valid.';
    } else {
        try {
            $gambar = null;
            if ($id > 0) {
                $q = $pdo->prepare('SELECT gambar FROM pelatihan WHERE id = ? AND penyedia_id = ?');
                $q->execute([$id, (int)$user['id']]);
                $existing = $q->fetch();
                if (!$existing) {
                    throw new RuntimeException('Pelatihan tidak ditemukan.');
                }
                $gambar = $existing['gambar'];
                if (!empty($_FILES['gambar']['name'])) {
                    $newImage = save_portfolio_image($_FILES['gambar'] ?? []);
                    if ($newImage) {
                        if ($gambar) {
                            delete_portfolio_image($gambar);
                        }
                        $gambar = $newImage;
                    }
                }
                $q = $pdo->prepare('UPDATE pelatihan SET judul_pelatihan = ?, kategori = ?, jumlah_modul = ?, nama_badge = ?, deskripsi = ?, gambar = ? WHERE id = ? AND penyedia_id = ?');
                $q->execute([$judul, $kategori, $jumlahModul, $namaBadge, $deskripsi, $gambar, $id, (int)$user['id']]);
                flash('success', 'Pelatihan berhasil diperbarui.');
            } else {
                $gambar = save_portfolio_image($_FILES['gambar'] ?? []);
                $q = $pdo->prepare('INSERT INTO pelatihan (penyedia_id, judul_pelatihan, kategori, jumlah_modul, nama_badge, deskripsi, gambar) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $q->execute([(int)$user['id'], $judul, $kategori, $jumlahModul, $namaBadge, $deskripsi, $gambar]);
                flash('success', 'Pelatihan berhasil dipublikasikan.');
            }
            redirect('/penyedia/pelatihan.php');
        } catch (Throwable $e) {
            $error = $e instanceof RuntimeException ? $e->getMessage() : 'Pelatihan gagal disimpan.';
            error_log('manage training: ' . $e->getMessage());
        }
    }
}

$q = $pdo->prepare('SELECT * FROM pelatihan WHERE penyedia_id = ? ORDER BY created_at DESC');
$q->execute([(int)$user['id']]);
$items = $q->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    foreach ($items as $item) {
        if ((int)$item['id'] === (int)$_GET['edit']) {
            $edit = $item;
            break;
        }
    }
}

$success = flash('success');
$pageTitle = 'Kelola Pelatihan';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Penyedia usaha</p>
    <h1 class="dashboard-title">Kelola pelatihan</h1>

    <?php if ($success): ?>
    <div class="alert alert-success mb-4"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error mb-4"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel mb-6">
        <h2><?= $edit ? 'Perbarui pelatihan' : 'Publikasikan pelatihan' ?></h2>
        <form method="post" enctype="multipart/form-data" class="dashboard-form mt-4">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

            <label>Judul pelatihan
                <input class="form-control" name="judul_pelatihan" maxlength="160" required value="<?= e($edit['judul_pelatihan'] ?? '') ?>">
            </label>

            <label>Kategori
                <select class="form-control" name="kategori">
                    <?php foreach (['Desain', 'Konten', 'Teknologi', 'Administrasi', 'Lainnya'] as $category): ?>
                    <option <?= (($edit['kategori'] ?? '') === $category ? 'selected' : '') ?>><?= e($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Jumlah modul
                <input class="form-control" name="jumlah_modul" type="number" min="1" max="65535" required value="<?= e((string)($edit['jumlah_modul'] ?? '1')) ?>">
            </label>

            <label>Nama badge
                <input class="form-control" name="nama_badge" maxlength="120" required value="<?= e($edit['nama_badge'] ?? '') ?>">
            </label>

            <label>Gambar pelatihan (opsional)
                <input class="form-control" type="file" name="gambar" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($edit['gambar'])): ?>
                <span class="text-xs text-slate-500 mt-1 block">Gambar saat ini: <?= e($edit['gambar']) ?> (unggah baru untuk mengganti)</span>
                <?php endif; ?>
            </label>

            <label class="dashboard-span">Deskripsi
                <textarea class="form-control" name="deskripsi" rows="5" required><?= e($edit['deskripsi'] ?? '') ?></textarea>
            </label>

            <div class="dashboard-span flex flex-wrap gap-2">
                <button class="btn-primary" type="submit"><?= $edit ? 'Simpan perubahan' : 'Publikasikan pelatihan' ?></button>
                <?php if ($edit): ?>
                <a class="btn-secondary" href="<?= BASE_URL ?>/penyedia/pelatihan.php">Batal edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="dashboard-panel">
        <h2>Pelatihan Anda</h2>
        <?php if ($items): ?>
        <div class="table-wrapper mt-4" role="region" aria-label="Daftar pelatihan Anda" tabindex="0">
            <table class="dashboard-table">
                <thead>
                    <tr><th>Judul</th><th>Kategori</th><th>Modul</th><th>Aksi</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <th><?= e($item['judul_pelatihan']) ?></th>
                        <td><?= e($item['kategori']) ?></td>
                        <td><?= (int)$item['jumlah_modul'] ?> modul</td>
                        <td>
                            <div class="table-actions">
                                <a class="text-link" href="?edit=<?= (int)$item['id'] ?>">Edit</a>
                                <form method="post" data-confirm="Hapus pelatihan ini?">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <button class="text-danger" name="delete" value="<?= (int)$item['id'] ?>" type="submit">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="empty-state mt-4">Belum ada pelatihan yang dipublikasikan.</p>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
