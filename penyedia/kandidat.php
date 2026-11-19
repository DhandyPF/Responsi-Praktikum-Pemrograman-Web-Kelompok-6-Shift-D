<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';
require_login('penyedia');
$user=current_user();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $stmt=$pdo->prepare("UPDATE lamaran l JOIN lowongan lo ON lo.id=l.lowongan_id SET l.status=:status WHERE l.id=:id AND lo.penyedia_id=:user");
 $status=$_POST['status'] ?? 'Menunggu';
 if(in_array($status,['Menunggu','Diproses','Diterima','Ditolak'],true)) $stmt->execute(['status'=>$status,'id'=>(int)$_POST['id'],'user'=>(int)$user['id']]);
 redirect('/penyedia/kandidat.php');
}
$stmt=$pdo->prepare("SELECT l.*,u.nama,u.email,u.telepon,u.lokasi,lo.judul FROM lamaran l JOIN users u ON u.id=l.pekerja_id JOIN lowongan lo ON lo.id=l.lowongan_id WHERE lo.penyedia_id=:user ORDER BY l.tanggal_lamar DESC");
$stmt->execute(['user'=>(int)$user['id']]); $items=$stmt->fetchAll();
$pageTitle='Kelola Kandidat'; require __DIR__.'/../includes/header.php';
?>
<main class="dashboard-main"><p class="eyebrow">Penyedia usaha</p><h1 class="dashboard-title">Kelola kandidat</h1><section class="dashboard-panel"><h2>Pelamar masuk</h2>
<?php if($items): ?><div class="table-wrapper" role="region" aria-label="Daftar kandidat" tabindex="0"><table class="dashboard-table"><thead><tr><th>Kandidat</th><th>Lowongan</th><th>Status</th><th>Perbarui</th></tr></thead><tbody>
<?php foreach($items as $x): ?><tr><th><?= e($x['nama']) ?><small><?= e($x['lokasi']) ?> · <?= e($x['telepon']) ?></small></th><td><?= e($x['judul']) ?></td><td><span class="badge-green"><?= e($x['status']) ?></span></td><td><form method="post" class="inline-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$x['id'] ?>"><select class="form-control" name="status"><?php foreach(['Menunggu','Diproses','Diterima','Ditolak'] as $s): ?><option <?= $x['status']===$s?'selected':'' ?>><?= e($s) ?></option><?php endforeach ?></select><button class="text-link" type="submit">Simpan</button></form></td></tr><?php endforeach ?>
</tbody></table></div><?php else: ?><div class="empty-state">Belum ada kandidat yang melamar lowongan Anda.</div><?php endif ?>
</section></main><?php require __DIR__.'/../includes/footer.php'; ?>
