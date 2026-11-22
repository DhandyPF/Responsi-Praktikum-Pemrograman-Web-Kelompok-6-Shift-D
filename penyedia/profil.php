<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';

require_login('penyedia');
$user = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nama = trim($_POST['nama'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if ($nama === '' || $lokasi === '') {
        $error = 'Nama usaha dan lokasi wajib diisi.';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET nama = :nama, lokasi = :lokasi, telepon = :telepon, deskripsi = :deskripsi WHERE id = :id');
        $stmt->execute([
            'nama'      => $nama,
            'lokasi'    => $lokasi,
            'telepon'   => $telepon,
            'deskripsi' => $deskripsi,
            'id'        => (int)$user['id'],
        ]);
        $_SESSION['user']['nama'] = $nama;
        $_SESSION['user']['lokasi'] = $lokasi;
        flash('success', 'Profil usaha berhasil diperbarui.');
        redirect('/penyedia/profil.php');
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => (int)$user['id']]);
$profile = $stmt->fetch();
$success = flash('success');

$pageTitle = 'Profil Usaha';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Penyedia usaha</p>
    <h1 class="dashboard-title">Profil usaha</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel">
        <form method="post" class="dashboard-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

            <label>Nama usaha
                <input class="form-control" name="nama" value="<?= e($profile['nama']) ?>" required>
            </label>

            <label>Email
                <input class="form-control" value="<?= e($profile['email']) ?>" disabled>
            </label>

            <label>Lokasi
                <input class="form-control" name="lokasi" value="<?= e($profile['lokasi']) ?>" required>
            </label>

            <label>Telepon
                <input class="form-control" name="telepon" value="<?= e($profile['telepon']) ?>">
            </label>

            <label class="dashboard-span">Deskripsi
                <textarea class="form-control" name="deskripsi" rows="5"><?= e($profile['deskripsi']) ?></textarea>
            </label>

            <button class="btn-primary dashboard-span" type="submit">Simpan profil</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
