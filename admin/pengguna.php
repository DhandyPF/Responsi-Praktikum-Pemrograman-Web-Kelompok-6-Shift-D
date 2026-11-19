<?php
require_once __DIR__.'/../config/koneksi.php'; require_once __DIR__.'/../config/functions.php'; require_login('admin');
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$id=(int)($_POST['id']??0);
 if(isset($_POST['toggle'])){$pdo->prepare('UPDATE users SET is_verified=NOT is_verified WHERE id=:id')->execute(['id'=>$id]);}
 redirect('/admin/pengguna.php');
}
$items=$pdo->query("SELECT id,nama,email,role,lokasi,telepon,is_verified,created_at FROM users ORDER BY created_at DESC")->fetchAll();
$pageTitle='Kelola Pengguna';require __DIR__.'/../includes/header.php';
?>
<main class="dashboard-main"><p class="eyebrow">Administrator</p><h1 class="dashboard-title">Kelola pengguna</h1><section class="dashboard-panel"><h2>Pengguna terdaftar</h2>
<?php if($items): ?><div class="table-wrapper" role="region" aria-label="Daftar pengguna" tabindex="0"><table class="dashboard-table"><thead><tr><th>Nama</th><th>Peran</th><th>Lokasi</th><th>Verifikasi</th><th>Aksi</th></tr></thead><tbody>
<?php foreach($items as $x): ?><tr><th><?=e($x['nama'])?><small><?=e($x['email'])?></small></th><td><?=e(ucfirst($x['role']))?></td><td><?=e($x['lokasi']?:'-')?></td><td><span class="<?=$x['is_verified']?'badge-green':'badge-amber'?>"><?=$x['is_verified']?'Terverifikasi':'Belum validasi'?></span></td><td><form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="id" value="<?=(int)$x['id']?>"><button class="text-link" name="toggle" type="submit"><?=$x['is_verified']?'Batalkan':'Verifikasi'?></button></form></td></tr><?php endforeach ?>
</tbody></table></div><?php else:?><div class="empty-state">Belum ada pengguna terdaftar.</div><?php endif?>
</section></main><?php require __DIR__.'/../includes/footer.php'; ?>
