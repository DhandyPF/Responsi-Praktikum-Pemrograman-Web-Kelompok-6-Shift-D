<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/stage2.php';

require_login('pekerja');
$user = current_user();
$uid = (int)$user['id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'delete') {
            $q = $pdo->prepare('SELECT gambar FROM portfolio WHERE id = ? AND pekerja_id = ?');
            $q->execute([(int)($_POST['id'] ?? 0), $uid]);
            $old = $q->fetchColumn();
            if ($old !== false) {
                $pdo->prepare('DELETE FROM portfolio WHERE id = ? AND pekerja_id = ?')->execute([(int)$_POST['id'], $uid]);
                delete_portfolio_image($old);
            }
            flash('success', 'Portfolio berhasil dihapus.');
            redirect('/pekerja/portfolio.php');
        }

        $id = (int)($_POST['id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $tautan = trim($_POST['tautan'] ?? '');

        if ($judul === '' || $deskripsi === '' || $kategori === '') {
            throw new RuntimeException('Judul, deskripsi, dan kategori wajib diisi.');
        }
        if (mb_strlen($judul) > 160 || mb_strlen($kategori) > 80) {
            throw new RuntimeException('Judul atau kategori terlalu panjang.');
        }
        if ($tautan !== '' && !filter_var($tautan, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Tautan harus berupa URL lengkap (https://...).');
        }

        $q = $pdo->prepare('SELECT gambar FROM portfolio WHERE id = ? AND pekerja_id = ?');
        $q->execute([$id, $uid]);
        $old = $id ? $q->fetchColumn() : false;
        if ($id && $old === false) {
            throw new RuntimeException('Portfolio tidak ditemukan.');
        }

        $newImage = save_portfolio_image($_FILES['gambar'] ?? []);
        $image = $newImage ?? ($old ?: null);

        if ($id) {
            $q = $pdo->prepare('UPDATE portfolio SET judul = ?, deskripsi = ?, kategori = ?, tautan = ?, gambar = ? WHERE id = ? AND pekerja_id = ?');
            $q->execute([$judul, $deskripsi, $kategori, $tautan ?: null, $image, $id, $uid]);
        } else {
            $q = $pdo->prepare('INSERT INTO portfolio (pekerja_id, judul, deskripsi, kategori, tautan, gambar) VALUES (?, ?, ?, ?, ?, ?)');
            $q->execute([$uid, $judul, $deskripsi, $kategori, $tautan ?: null, $image]);
        }

        if ($newImage && $old) {
            delete_portfolio_image($old);
        }

        flash('success', 'Portfolio berhasil disimpan.');
        redirect('/pekerja/portfolio.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $q = $pdo->prepare('SELECT * FROM portfolio WHERE id = ? AND pekerja_id = ?');
    $q->execute([(int)$_GET['edit'], $uid]);
    $edit = $q->fetch() ?: null;
}

$q = $pdo->prepare('SELECT * FROM portfolio WHERE pekerja_id = ? ORDER BY updated_at DESC, id DESC');
$q->execute([$uid]);
$items = $q->fetchAll();

$pageTitle = 'Portfolio Saya';
require __DIR__ . '/../includes/header.php';
$success = flash('success');
?>
<main class="dashboard-main">
    <p class="eyebrow">Pekerja / talenta</p>
    <h1 class="dashboard-title">Portfolio saya</h1>
    <p class="text-sm text-slate-500 mt-2">Tampilkan hasil karya dan pengalaman terbaikmu.</p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel">
        <h2><?= $edit ? 'Edit portfolio' : 'Tambah portfolio' ?></h2>
        <form method="post" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4 mt-4">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">

            <div>
                <label class="form-label">Judul karya *</label>
                <input class="form-control" name="judul" maxlength="160" required value="<?= e($edit['judul'] ?? '') ?>">
            </div>

            <div>
                <label class="form-label">Kategori *</label>
                <input class="form-control" name="kategori" maxlength="80" required placeholder="Contoh: UI/UX, Fotografi" value="<?= e($edit['kategori'] ?? '') ?>">
            </div>

            <div class="md:col-span-2">
                <label class="form-label">Deskripsi *</label>
                <textarea class="form-control" name="deskripsi" rows="4" required><?= e($edit['deskripsi'] ?? '') ?></textarea>
            </div>

            <div>
                <label class="form-label">Tautan karya</label>
                <input class="form-control" type="url" name="tautan" placeholder="https://" value="<?= e($edit['tautan'] ?? '') ?>">
            </div>

            <div>
                <label class="form-label">Gambar (JPG/PNG/WebP, maks. 3 MB)</label>
                <input class="form-control" type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                <?php if (!empty($edit['gambar'])): ?>
                    <small>Gambar saat ini: <?= e($edit['gambar']) ?></small>
                <?php endif; ?>
            </div>

            <div class="md:col-span-2 flex gap-3">
                <button class="btn-primary" type="submit">Simpan portfolio</button>
                <?php if ($edit): ?>
                    <a class="text-link" href="<?= BASE_URL ?>/pekerja/portfolio.php">Batal edit</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="dashboard-panel mt-6">
        <h2>Karya yang dipublikasikan</h2>
        <?php if ($items): ?>
            <div class="grid md:grid-cols-2 gap-4 mt-4">
                <?php foreach ($items as $x): ?>
                    <article class="surface-card p-4">
                        <?php if ($x['gambar']): ?>
                            <img class="w-full h-48 object-cover rounded-xl mb-3" src="<?= BASE_URL ?>/uploads/portfolio/<?= e($x['gambar']) ?>" alt="<?= e($x['judul']) ?>">
                        <?php endif; ?>
                        <p class="eyebrow"><?= e($x['kategori']) ?></p>
                        <h3 class="font-bold text-ink mt-1"><?= e($x['judul']) ?></h3>
                        <p class="text-sm mt-2"><?= nl2br(e($x['deskripsi'])) ?></p>
                        <?php if ($x['tautan']): ?>
                            <a class="text-link inline-block mt-3" href="<?= e($x['tautan']) ?>" target="_blank" rel="noopener noreferrer">Lihat karya ↗</a>
                        <?php endif; ?>
                        <div class="flex gap-4 mt-4">
                            <a class="text-link" href="?edit=<?= (int)$x['id'] ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Hapus portfolio ini?')">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$x['id'] ?>">
                                <button class="text-link" type="submit">Hapus</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state mt-4">Belum ada portfolio. Tambahkan karya pertamamu.</div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
