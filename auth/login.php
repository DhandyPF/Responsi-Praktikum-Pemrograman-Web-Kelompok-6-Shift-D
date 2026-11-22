<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';

if (current_user()) {
    redirect(dashboard_path());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Masukkan email dan kata sandi yang valid.';
    } else {
        $stmt = $pdo->prepare('SELECT id, nama, email, password, role, lokasi, is_verified FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $account = $stmt->fetch();
        if ($account && password_verify($password, $account['password'])) {
            session_regenerate_id(true);
            unset($account['password']);
            $_SESSION['user'] = $account;
            redirect(dashboard_path());
        }
        $error = 'Email atau kata sandi tidak sesuai.';
    }
}

$pageTitle = 'Masuk';
require __DIR__ . '/../includes/header.php';
?>
<main class="min-h-[65vh] px-4">
    <section class="form-wrap">
        <p class="eyebrow">Selamat datang kembali</p>
        <h1 class="text-2xl font-extrabold text-ink mt-2">Masuk ke NusaKarya</h1>
        <p class="text-xs text-slate-500 mt-2">Akses profil, lamaran, pelatihan, dan peluangmu.</p>

        <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

            <label class="form-label" for="email">Email</label>
            <input class="form-control" id="email" name="email" type="email" autocomplete="email" required
                value="<?= e($_POST['email'] ?? '') ?>">

            <label class="form-label" for="password">Kata sandi</label>
            <input class="form-control" id="password" name="password" type="password" autocomplete="current-password"
                required>

            <button class="btn-primary w-full mt-6" type="submit">Masuk</button>
        </form>

        <p class="text-center text-xs text-slate-500 mt-5">Belum punya akun? <a class="font-bold text-teal"
                href="<?= BASE_URL ?>/auth/register.php">Daftar gratis</a></p>

        <div class="mt-6 border-t pt-4 text-[11px] text-slate-500 leading-6">
            <b>Akun demo (kata sandi: password)</b><br>
            Admin: admin@nusakarya.test<br>
            Penyedia: usaha@nusakarya.test<br>
            Pekerja: nadia@nusakarya.test
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>