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
        $qOld = $pdo->prepare('SELECT gambar FROM lowongan WHERE id = :id AND penyedia_id = :user');
        $qOld->execute(['id' => (int)$_POST['delete'], 'user' => (int)$user['id']]);
        $oldImg = $qOld->fetchColumn();
        if ($oldImg) {
            delete_portfolio_image($oldImg);
        }
        $stmt = $pdo->prepare('DELETE FROM lowongan WHERE id = :id AND penyedia_id = :user');
        $stmt->execute(['id' => (int)$_POST['delete'], 'user' => (int)$user['id']]);
        flash('success', 'Lowongan berhasil dihapus.');
    } elseif (isset($_POST['toggle'])) {
        $stmt = $pdo->prepare("UPDATE lowongan SET status = IF(status = 'Aktif', 'Ditutup', 'Aktif') WHERE id = :id AND penyedia_id = :user");
        $stmt->execute(['id' => (int)$_POST['toggle'], 'user' => (int)$user['id']]);
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $lokasi = trim($_POST['lokasi'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');

        if ($judul === '' || $lokasi === '' || $deskripsi === '') {
            $error = 'Judul, lokasi, dan deskripsi wajib diisi.';
        } else {
            try {
                if ($id > 0) {
                    $qExisting = $pdo->prepare('SELECT id, gambar FROM lowongan WHERE id = :id AND penyedia_id = :user');
                    $qExisting->execute(['id' => $id, 'user' => (int)$user['id']]);
                    $existing = $qExisting->fetch();
                    if (!$existing) {
                        throw new RuntimeException('Lowongan tidak ditemukan.');
                    }
                    $gambar = $existing['gambar'];
                    if (!empty($_FILES['gambar']['name'])) {
                        $newImg = save_portfolio_image($_FILES['gambar'] ?? []);
                        if ($newImg) {
                            if ($gambar) {
                                delete_portfolio_image($gambar);
                            }
                            $gambar = $newImg;
                        }
                    }
                    $stmt = $pdo->prepare('UPDATE lowongan SET judul = :judul, kategori = :kategori, jenis_pekerjaan = :jenis, lokasi = :lokasi, gaji_min = :gaji_min, gaji_max = :gaji_max, deskripsi = :deskripsi, persyaratan = :persyaratan, batas_lamaran = :batas, gambar = :gambar WHERE id = :id AND penyedia_id = :user');
                    $stmt->execute([
                        'id'          => $id,
                        'user'        => $user['id'],
                        'judul'       => $judul,
                        'kategori'    => $_POST['kategori'] ?? 'Lainnya',
                        'jenis'       => $_POST['jenis_pekerjaan'] ?? 'Penuh waktu',
                        'lokasi'      => $lokasi,
                        'gaji_min'    => (float)($_POST['gaji_min'] ?: 0),
                        'gaji_max'    => (float)($_POST['gaji_max'] ?: 0),
                        'deskripsi'   => $deskripsi,
                        'persyaratan' => trim($_POST['persyaratan'] ?? ''),
                        'batas'       => $_POST['batas_lamaran'] ?: null,
                        'gambar'      => $gambar,
                    ]);
                    flash('success', 'Lowongan berhasil diperbarui.');
                } else {
                    $gambar = save_portfolio_image($_FILES['gambar'] ?? []);
                    $stmt = $pdo->prepare('INSERT INTO lowongan (penyedia_id, judul, kategori, jenis_pekerjaan, lokasi, gaji_min, gaji_max, deskripsi, persyaratan, batas_lamaran, status, gambar) VALUES (:user, :judul, :kategori, :jenis, :lokasi, :gaji_min, :gaji_max, :deskripsi, :persyaratan, :batas, "Aktif", :gambar)');
                    $stmt->execute([
                        'user'        => $user['id'],
                        'judul'       => $judul,
                        'kategori'    => $_POST['kategori'] ?? 'Lainnya',
                        'jenis'       => $_POST['jenis_pekerjaan'] ?? 'Penuh waktu',
                        'lokasi'      => $lokasi,
                        'gaji_min'    => (float)($_POST['gaji_min'] ?: 0),
                        'gaji_max'    => (float)($_POST['gaji_max'] ?: 0),
                        'deskripsi'   => $deskripsi,
                        'persyaratan' => trim($_POST['persyaratan'] ?? ''),
                        'batas'       => $_POST['batas_lamaran'] ?: null,
                        'gambar'      => $gambar,
                    ]);
                    flash('success', 'Lowongan berhasil dipublikasikan.');
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    }
    if ($error === '') {
        redirect('/penyedia/lowongan.php');
    }
}

$stmt = $pdo->prepare('SELECT * FROM lowongan WHERE penyedia_id = :user ORDER BY created_at DESC');
$stmt->execute(['user' => (int)$user['id']]);
$items = $stmt->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($items as $item) {
        if ((int)$item['id'] === $editId) {
            $edit = $item;
            break;
        }
    }
}

$success = flash('success');

$pageTitle = 'Kelola Lowongan';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Penyedia usaha</p>
    <h1 class="dashboard-title">Kelola lowongan</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel">
        <h2><?= $edit ? 'Perbarui lowongan' : 'Publikasikan lowongan' ?></h2>
        <form method="post" enctype="multipart/form-data" class="dashboard-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

            <label>Judul
                <input class="form-control" name="judul" value="<?= e($edit['judul'] ?? '') ?>" required>
            </label>

            <label>Kategori
                <select class="form-control" name="kategori">
                    <?php foreach (['Desain', 'Konten', 'Teknologi', 'Administrasi', 'Lainnya'] as $v): ?>
                        <option <?= (($edit['kategori'] ?? '') === $v ? 'selected' : '') ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Jenis pekerjaan
                <input class="form-control" name="jenis_pekerjaan" value="<?= e($edit['jenis_pekerjaan'] ?? 'Penuh waktu') ?>">
            </label>

            <label>Lokasi
                <input class="form-control" name="lokasi" value="<?= e($edit['lokasi'] ?? '') ?>" required>
            </label>

            <label>Foto lowongan (opsional)
                <input class="form-control" type="file" name="gambar" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($edit['gambar'])): ?>
                <span class="text-xs text-slate-500 mt-1 block">Foto saat ini: <?= e($edit['gambar']) ?> (unggah baru untuk mengganti)</span>
                <?php endif; ?>
            </label>

            <label>Gaji minimum
                <input class="form-control" name="gaji_min" type="number" min="0" value="<?= e($edit && $edit['gaji_min'] !== null ? (string)(int)$edit['gaji_min'] : '') ?>">
            </label>

            <label>Gaji maksimum
                <input class="form-control" name="gaji_max" type="number" min="0" value="<?= e($edit && $edit['gaji_max'] !== null ? (string)(int)$edit['gaji_max'] : '') ?>">
            </label>

            <label>Batas lamaran
                <input class="form-control" name="batas_lamaran" type="date" value="<?= e($edit['batas_lamaran'] ?? '') ?>">
            </label>

            <label class="dashboard-span">Deskripsi
                <textarea class="form-control" name="deskripsi" rows="4" required><?= e($edit['deskripsi'] ?? '') ?></textarea>
            </label>

            <label class="dashboard-span">Persyaratan
                <textarea class="form-control" name="persyaratan" rows="3"><?= e($edit['persyaratan'] ?? '') ?></textarea>
            </label>

            <div class="dashboard-span flex flex-wrap gap-2">
                <button class="btn-primary" type="submit"><?= $edit ? 'Simpan perubahan' : 'Publikasikan lowongan' ?></button>
                <?php if ($edit): ?>
                <a class="btn-secondary" href="<?= BASE_URL ?>/penyedia/lowongan.php">Batal edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="dashboard-panel">
        <h2>Lowongan Anda</h2>
        <?php if ($items): ?>
            <div class="table-wrapper" role="region" aria-label="Daftar lowongan Anda" tabindex="0">
                <table class="dashboard-table">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $x): ?>
                            <tr>
                                <th>
                                    <?= e($x['judul']) ?>
                                    <small><?= e($x['lokasi']) ?></small>
                                </th>
                                <td><?= e($x['kategori']) ?></td>
                                <td>
                                    <span class="<?= $x['status'] === 'Aktif' ? 'badge-green' : 'badge-amber' ?>">
                                        <?= e($x['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a class="text-link" href="?edit=<?= (int)$x['id'] ?>">Edit</a>
                                        <form method="post">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <button class="text-link" name="toggle" value="<?= (int)$x['id'] ?>" type="submit"><?= $x['status'] === 'Aktif' ? 'Tutup' : 'Aktifkan' ?></button>
                                        </form>
                                        <form method="post" data-confirm="Hapus lowongan ini?">
                                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                            <button class="text-danger" name="delete" value="<?= (int)$x['id'] ?>" type="submit">Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">Belum ada lowongan. Publikasikan lowongan pertama Anda di atas.</div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
