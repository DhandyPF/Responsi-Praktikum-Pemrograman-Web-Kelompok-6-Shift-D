<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';
require_login('penyedia');
$user = current_user();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['delete'])) {
        $stmt = $pdo->prepare('DELETE FROM lowongan WHERE id=:id AND penyedia_id=:user');
        $stmt->execute(['id'=>(int)$_POST['delete'],'user'=>(int)$user['id']]);
        flash('success', 'Lowongan berhasil dihapus.');
    } elseif (isset($_POST['toggle'])) {
        $stmt = $pdo->prepare("UPDATE lowongan SET status=IF(status='Aktif','Ditutup','Aktif') WHERE id=:id AND penyedia_id=:user");
        $stmt->execute(['id'=>(int)$_POST['toggle'],'user'=>(int)$user['id']]);
    } else {
        $judul = trim($_POST['judul'] ?? '');
        $lokasi = trim($_POST['lokasi'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        if ($judul === '' || $lokasi === '' || $deskripsi === '') {
            $error = 'Judul, lokasi, dan deskripsi wajib diisi.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO lowongan (penyedia_id,judul,kategori,jenis_pekerjaan,lokasi,gaji_min,gaji_max,deskripsi,persyaratan,batas_lamaran,status) VALUES (:user,:judul,:kategori,:jenis,:lokasi,:gaji_min,:gaji_max,:deskripsi,:persyaratan,:batas,"Aktif")');
            $stmt->execute([
                'user'=>$user['id'],'judul'=>$judul,'kategori'=>$_POST['kategori'] ?? 'Lainnya',
                'jenis'=>$_POST['jenis_pekerjaan'] ?? 'Penuh waktu','lokasi'=>$lokasi,
                'gaji_min'=>(float)($_POST['gaji_min'] ?: 0),'gaji_max'=>(float)($_POST['gaji_max'] ?: 0),
                'deskripsi'=>$deskripsi,'persyaratan'=>trim($_POST['persyaratan'] ?? ''),
                'batas'=>$_POST['batas_lamaran'] ?: null
            ]);
            flash('success', 'Lowongan berhasil dipublikasikan.');
        }
    }
    redirect('/penyedia/lowongan.php');
}
$stmt = $pdo->prepare('SELECT * FROM lowongan WHERE penyedia_id=:user ORDER BY created_at DESC');
$stmt->execute(['user'=>(int)$user['id']]);
$items = $stmt->fetchAll();
$success = flash('success');
$pageTitle='Kelola Lowongan';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
 <p class="eyebrow">Penyedia usaha</p><h1 class="dashboard-title">Kelola lowongan</h1>
 <?php if($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif ?>
 <?php if($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
 <section class="dashboard-panel">
  <h2>Publikasikan lowongan</h2>
  <form method="post" class="dashboard-form">
   <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
   <label>Judul<input class="form-control" name="judul" required></label>
   <label>Kategori<select class="form-control" name="kategori"><?php foreach(['Desain','Konten','Teknologi','Administrasi','Lainnya'] as $v): ?><option><?= e($v) ?></option><?php endforeach ?></select></label>
   <label>Jenis pekerjaan<input class="form-control" name="jenis_pekerjaan" value="Penuh waktu"></label>
   <label>Lokasi<input class="form-control" name="lokasi" required></label>
   <label>Gaji minimum<input class="form-control" name="gaji_min" type="number" min="0"></label>
   <label>Gaji maksimum<input class="form-control" name="gaji_max" type="number" min="0"></label>
   <label>Batas lamaran<input class="form-control" name="batas_lamaran" type="date"></label>
   <label class="dashboard-span">Deskripsi<textarea class="form-control" name="deskripsi" rows="4" required></textarea></label>
   <label class="dashboard-span">Persyaratan<textarea class="form-control" name="persyaratan" rows="3"></textarea></label>
   <button class="btn-primary dashboard-span" type="submit">Publikasikan lowongan</button>
  </form>
 </section>
 <section class="dashboard-panel">
  <h2>Lowongan Anda</h2>
  <?php if($items): ?>
  <div class="table-wrapper" role="region" aria-label="Daftar lowongan Anda" tabindex="0">
   <table class="dashboard-table"><thead><tr><th>Judul</th><th>Kategori</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
   <?php foreach($items as $x): ?><tr><th><?= e($x['judul']) ?><small><?= e($x['lokasi']) ?></small></th><td><?= e($x['kategori']) ?></td><td><span class="<?= $x['status']==='Aktif'?'badge-green':'badge-amber' ?>"><?= e($x['status']) ?></span></td><td><div class="table-actions"><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="text-link" name="toggle" value="<?= (int)$x['id'] ?>" type="submit"><?= $x['status']==='Aktif'?'Tutup':'Aktifkan' ?></button></form><form method="post" data-confirm="Hapus lowongan ini?"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><button class="text-danger" name="delete" value="<?= (int)$x['id'] ?>" type="submit">Hapus</button></form></div></td></tr><?php endforeach ?>
   </tbody></table>
  </div>
  <?php else: ?><div class="empty-state">Belum ada lowongan. Publikasikan lowongan pertama Anda di atas.</div><?php endif ?>
 </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
