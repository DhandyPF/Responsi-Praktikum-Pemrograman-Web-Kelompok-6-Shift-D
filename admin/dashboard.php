<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';
require_login('admin');
$counts=[];
foreach (['users'=>'Pengguna','lowongan'=>'Lowongan','jasa'=>'Jasa','lamaran'=>'Lamaran','pelatihan'=>'Pelatihan'] as $table=>$label) { $counts[$table]=(int)$pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn(); }
$pesananTotal=0;$ulasanTotal=0;
try{$pesananTotal=(int)$pdo->query('SELECT COUNT(*) FROM pesanan')->fetchColumn();$ulasanTotal=(int)$pdo->query('SELECT COUNT(*) FROM ulasan')->fetchColumn();}catch(mysqli_sql_exception $e){error_log('admin tahap3: '.$e->getMessage());}
$pending=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='penyedia' AND is_verified=0")->fetchColumn();
$pageTitle='Dashboard Admin'; require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <div class="surface-card p-5 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-4">
            <img src="<?= user_avatar($user) ?>" alt="<?= e($user['nama'] ?? 'Admin') ?>" class="w-16 h-16 rounded-full object-cover ring-4 ring-sky-500/20 shadow-sm">
            <div>
                <p class="eyebrow">Pusat kendali platform</p>
                <h1 class="text-xl font-bold text-ink">Dashboard Administrator</h1>
                <p class="text-xs text-slate-500 mt-1"><?= e($user['nama'] ?? 'Admin') ?> &bull; <span class="text-sky-600 font-semibold">Administrator Platform</span></p>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/admin/profil.php" class="btn-secondary text-xs px-4 py-2">Ubah Profil & Foto</a>
    </div>
    <p class="text-sm text-slate-500 mt-2">Ringkasan aktivitas ekosistem NusaKarya.</p>
    <section class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-7"><?php foreach($counts as $key=>$value): ?><div
            class="surface-card p-5">
            <p class="text-xs text-slate-500">
                <?= e(['users'=>'Total pengguna','lowongan'=>'Total lowongan','jasa'=>'Total jasa','lamaran'=>'Total lamaran','pelatihan'=>'Total pelatihan'][$key]) ?>
            </p><strong class="block text-3xl text-ink mt-2"><?= number_format($value) ?></strong>
        </div><?php endforeach; ?><div class="surface-card p-5">
            <p class="text-xs text-slate-500">Total pesanan jasa</p><strong
                class="block text-3xl text-ink mt-2"><?= number_format($pesananTotal) ?></strong>
        </div>
        <div class="surface-card p-5">
            <p class="text-xs text-slate-500">Total ulasan</p><strong
                class="block text-3xl text-ink mt-2"><?= number_format($ulasanTotal) ?></strong>
        </div>
        <div class="surface-card p-5">
            <p class="text-xs text-slate-500">Penyedia menunggu verifikasi</p><strong
                class="block text-3xl text-ink mt-2"><?= number_format($pending) ?></strong>
        </div>
    </section>
    <section class="dashboard-panel mt-7">
        <h2>Administrasi</h2>
        <div class="dashboard-grid mt-4"><a class="surface-card p-5" href="<?= BASE_URL ?>/admin/profil.php"><b>Profil
                    saya</b>
                <p class="text-xs text-slate-500 mt-2">Kelola informasi akun dan foto profil admin.</p>
            </a><a class="surface-card p-5" href="<?= BASE_URL ?>/admin/pengguna.php"><b>Kelola
                    pengguna</b>
                <p class="text-xs text-slate-500 mt-2">Lihat dan verifikasi akun terdaftar.</p>
            </a><a class="surface-card p-5" href="<?= BASE_URL ?>/admin/statistik.php"><b>Statistik platform</b>
                <p class="text-xs text-slate-500 mt-2">Lihat ringkasan aktivitas NusaKarya.</p>
            </a></div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>