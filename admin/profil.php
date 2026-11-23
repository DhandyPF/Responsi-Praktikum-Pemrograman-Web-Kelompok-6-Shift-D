<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';

require_login('admin');
$user = current_user();
$error = '';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute(['id' => (int)$user['id']]);
$profile = $stmt->fetch() ?: $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nama = trim($_POST['nama'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $removeFoto = isset($_POST['hapus_foto']) && $_POST['hapus_foto'] === '1';

    if ($nama === '') {
        $error = 'Nama administrator wajib diisi.';
    } else {
        $currentFoto = $profile['foto'] ?? null;
        try {
            if ($removeFoto) {
                delete_profile_photo($currentFoto);
                $currentFoto = null;
            }

            if (!empty($_FILES['foto']['name'])) {
                $newFoto = save_profile_photo($_FILES['foto']);
                if ($newFoto) {
                    delete_profile_photo($currentFoto);
                    $currentFoto = $newFoto;
                }
            }

            $stmt = $pdo->prepare('UPDATE users SET nama = :nama, lokasi = :lokasi, telepon = :telepon, deskripsi = :deskripsi, foto = :foto WHERE id = :id');
            $stmt->execute([
                'nama'      => $nama,
                'lokasi'    => $lokasi,
                'telepon'   => $telepon,
                'deskripsi' => $deskripsi,
                'foto'      => $currentFoto,
                'id'        => (int)$user['id'],
            ]);

            $_SESSION['user']['nama'] = $nama;
            $_SESSION['user']['lokasi'] = $lokasi;
            $_SESSION['user']['foto'] = $currentFoto;

            flash('success', 'Profil Administrator dan foto berhasil diperbarui.');
            redirect('/admin/profil.php');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$success = flash('success');
$pageTitle = 'Profil Administrator';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Pusat kendali platform</p>
    <h1 class="dashboard-title">Profil Administrator</h1>

    <?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel">
        <form method="post" enctype="multipart/form-data" class="dashboard-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

            <!-- Foto Profil Administrator Section -->
            <div class="dashboard-span surface-card p-5 rounded-2xl flex flex-col sm:flex-row items-center gap-6 bg-slate-50 border border-slate-200">
                <div class="relative shrink-0">
                    <img id="avatarPreview" src="<?= user_avatar($profile) ?>" alt="Foto Profil Admin"
                         class="w-24 h-24 rounded-full object-cover ring-4 ring-sky-500/20 shadow-md">
                    <span class="absolute bottom-0 right-0 bg-sky-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow">
                        Admin
                    </span>
                </div>
                <div class="flex-1 w-full">
                    <label class="block font-semibold text-ink text-sm mb-1" for="foto">Foto Profil Administrator</label>
                    <p class="text-xs text-slate-500 mb-3">
                        Gunakan foto profil resmi administrator. Jika belum diunggah, otomatis menggunakan avatar role <b>Admin</b>.
                    </p>
                    <input class="form-control text-sm" id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp"
                           onchange="previewImage(this)">
                    <p class="text-[11px] text-slate-400 mt-1">Format didukung: JPG, PNG, WebP (Maksimal 3 MB).</p>
                    <?php if (!empty($profile['foto'])): ?>
                    <label class="inline-flex items-center gap-2 text-xs text-rose-600 mt-2 cursor-pointer font-medium">
                        <input type="checkbox" name="hapus_foto" value="1">
                        Hapus foto profil (kembali ke avatar role Admin)
                    </label>
                    <?php endif; ?>
                </div>
            </div>

            <label>Nama Administrator
                <input class="form-control" name="nama" value="<?= e($profile['nama']) ?>" required>
            </label>

            <label>Email
                <input class="form-control" value="<?= e($profile['email']) ?>" disabled>
            </label>

            <label>Lokasi / Kantor
                <input class="form-control" name="lokasi" value="<?= e($profile['lokasi'] ?? '') ?>" placeholder="Misal: Purwokerto">
            </label>

            <label>Telepon
                <input class="form-control" name="telepon" value="<?= e($profile['telepon'] ?? '') ?>">
            </label>

            <label class="dashboard-span">Deskripsi / Catatan internal
                <textarea class="form-control" name="deskripsi" rows="4"><?= e($profile['deskripsi'] ?? '') ?></textarea>
            </label>

            <button class="btn-primary dashboard-span" type="submit">Simpan profil</button>
        </form>
    </section>
</main>
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
