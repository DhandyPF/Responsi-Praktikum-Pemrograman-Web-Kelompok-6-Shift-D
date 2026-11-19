<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';
if (current_user()) redirect('/dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $nama = trim($_POST['nama'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'pekerja';
    $telepon = trim($_POST['telepon'] ?? '');
    $lokasi = trim($_POST['lokasi'] ?? '');
    $allowedRoles = ['pekerja','penyedia'];
    if (mb_strlen($nama) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($password) < 8 || !in_array($role, $allowedRoles, true) ||
        $telepon === '' || $lokasi === '') {
        $error = 'Periksa kembali data. Kata sandi minimal 8 karakter dan seluruh kolom wajib diisi.';
    } else {
        $check = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $check->execute(['email' => $email]);
        if ($check->fetch()) {
            $error = 'Email sudah terdaftar. Silakan masuk atau gunakan email lain.';
        } else {
            $insert = $pdo->prepare('INSERT INTO users (nama,email,password,role,telepon,lokasi) VALUES (:nama,:email,:password,:role,:telepon,:lokasi)');
            $insert->execute([
                'nama'=>$nama, 'email'=>$email, 'password'=>password_hash($password, PASSWORD_DEFAULT),
                'role'=>$role, 'telepon'=>$telepon, 'lokasi'=>$lokasi
            ]);
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'=>(int)$pdo->lastInsertId(),'nama'=>$nama,'email'=>$email,'role'=>$role,
                'lokasi'=>$lokasi,'is_verified'=>0
            ];
            redirect('/dashboard.php');
        }
    }
}
$pageTitle = 'Daftar Gratis';
require __DIR__ . '/../includes/header.php';
?>
<main class="min-h-[65vh] px-4">
 <section class="form-wrap">
  <p class="eyebrow">Mulai langkah baru</p><h1 class="text-2xl font-extrabold text-ink mt-2">Buat akun gratis</h1><p class="text-xs text-slate-500 mt-2">Bergabung dengan ekosistem peluang ekonomi lokal.</p>
  <?php if($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" id="registerForm">
   <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
   <label class="form-label" for="nama">Nama lengkap / nama usaha</label><input class="form-control" id="nama" name="nama" required minlength="3" value="<?= e($_POST['nama'] ?? '') ?>">
   <label class="form-label" for="email">Email</label><input class="form-control" id="email" name="email" type="email" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>">
   <label class="form-label" for="password">Kata sandi (minimal 8 karakter)</label><input class="form-control" id="password" name="password" type="password" minlength="8" autocomplete="new-password" required>
   <label class="form-label" for="role">Jenis akun</label><select class="form-control" id="role" name="role" required><option value="pekerja" <?= ($_POST['role'] ?? '')==='pekerja'?'selected':'' ?>>Pekerja / Talenta</option><option value="penyedia" <?= ($_POST['role'] ?? '')==='penyedia'?'selected':'' ?>>Penyedia Usaha / UMKM</option></select>
   <label class="form-label" for="telepon">Nomor telepon</label><input class="form-control" id="telepon" name="telepon" type="tel" required value="<?= e($_POST['telepon'] ?? '') ?>" placeholder="08xxxxxxxxxx">
   <label class="form-label" for="lokasi">Lokasi / Kecamatan</label><input class="form-control" id="lokasi" name="lokasi" required value="<?= e($_POST['lokasi'] ?? '') ?>" placeholder="Contoh: Purwokerto">
   <button class="btn-primary w-full mt-6" type="submit">Lanjutkan pendaftaran →</button>
  </form>
  <p class="text-center text-xs text-slate-500 mt-5">Sudah punya akun? <a class="font-bold text-teal" href="<?= BASE_URL ?>/auth/login.php">Masuk</a></p>
 </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
