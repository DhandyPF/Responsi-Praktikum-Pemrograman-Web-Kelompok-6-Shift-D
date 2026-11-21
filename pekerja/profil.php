<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';

require_login('pekerja');
$user = current_user();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nama = trim($_POST['nama'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $skills = trim($_POST['skills'] ?? '');

    if ($nama === '' || $lokasi === '') {
        $error = 'Nama dan lokasi wajib diisi.';
    } else {
        $stmt = $pdo->prepare('UPDATE users SET nama = :nama, lokasi = :lokasi, telepon = :telepon, deskripsi = :deskripsi, skills = :skills WHERE id = :id');
        $stmt->execute([
            'nama'      => $nama,
            'lokasi'    => $lokasi,
            'telepon'   => $telepon,
            'deskripsi' => $deskripsi,
            'skills'    => $skills,
            'id'        => (int)$user['id'],
        ]);
        $_SESSION['user']['nama'] = $nama;
        $_SESSION['user']['lokasi'] = $lokasi;
        flash('success', 'Profil berhasil diperbarui.');
        redirect('/pekerja/profil.php');
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => (int)$user['id']]);
$profile = $stmt->fetch();
$success = flash('success');

$pageTitle = 'Lengkapi Profil';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Pekerja / talenta</p>
    <h1 class="dashboard-title">Lengkapi profil</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel">
        <form method="post" class="dashboard-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

            <label>Nama lengkap
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

            <label class="dashboard-span">Keahlian (pisahkan dengan koma)
                <input class="form-control" name="skills" value="<?= e($profile['skills']) ?>" placeholder="Figma, UI Design, Branding">
            </label>

            <label class="dashboard-span">Deskripsi diri
                <textarea class="form-control" name="deskripsi" rows="5"><?= e($profile['deskripsi']) ?></textarea>
            </label>

            <button class="btn-primary dashboard-span" type="submit">Simpan profil</button>
        </form>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
