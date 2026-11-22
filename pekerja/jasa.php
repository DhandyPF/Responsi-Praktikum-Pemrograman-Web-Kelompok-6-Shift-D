<?php
require_once __DIR__.'/../config/koneksi.php';require_once __DIR__.'/../config/functions.php';require_login('pekerja');
$user=current_user();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();
 if(isset($_POST['delete'])){$stmt=$pdo->prepare('DELETE FROM jasa WHERE id=:id AND pekerja_id=:user');$stmt->execute(['id'=>(int)$_POST['delete'],'user'=>(int)$user['id']]);}
 else{$nama=trim($_POST['nama_jasa']??'');$lokasi=trim($_POST['lokasi']??'');$deskripsi=trim($_POST['deskripsi']??'');
  $kategoriIn=$_POST['kategori']??'Lainnya';if(!in_array($kategoriIn,['Desain','Konten','Teknologi','Administrasi','Lainnya'],true))$kategoriIn='Lainnya';
  if($nama===''||$lokasi===''||$deskripsi===''){$error='Nama jasa, lokasi, dan deskripsi wajib diisi.';}else{$stmt=$pdo->prepare('INSERT INTO jasa (pekerja_id,nama_jasa,kategori,lokasi,harga_mulai,deskripsi) VALUES (:user,:nama,:kategori,:lokasi,:harga,:deskripsi)');$stmt->execute(['user'=>(int)$user['id'],'nama'=>$nama,'kategori'=>$kategoriIn,'lokasi'=>$lokasi,'harga'=>(float)($_POST['harga_mulai']?:0),'deskripsi'=>$deskripsi]);}}
 if($error==='')redirect('/pekerja/jasa.php');
}
$stmt=$pdo->prepare('SELECT * FROM jasa WHERE pekerja_id=:user ORDER BY created_at DESC');$stmt->execute(['user'=>(int)$user['id']]);$items=$stmt->fetchAll();
$pageTitle='Kelola Jasa';require __DIR__.'/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Pekerja / talenta</p>
    <h1 class="dashboard-title">Kelola jasa</h1><?php if($error):?><div class="alert alert-error"><?=e($error)?></div>
    <?php endif?><section class="dashboard-panel">
        <h2>Publikasikan jasa</h2>
        <form method="post" class="dashboard-form"><input type="hidden" name="_csrf"
                value="<?=e(csrf_token())?>"><label>Nama jasa<input class="form-control" name="nama_jasa"
                    required></label><label>Kategori<select class="form-control"
                    name="kategori"><?php foreach(['Desain','Konten','Teknologi','Administrasi','Lainnya'] as $v):?>
                    <option><?=e($v)?></option><?php endforeach?>
                </select></label><label>Lokasi<input class="form-control" name="lokasi"
                    value="<?=e($user['lokasi']??'')?>" required></label><label>Harga
                mulai<input class="form-control" name="harga_mulai" type="number" min="0"></label><label
                class="dashboard-span">Deskripsi<textarea class="form-control" name="deskripsi" rows="4"
                    required></textarea></label><button class="btn-primary dashboard-span" type="submit">Publikasikan
                jasa</button></form>
    </section>
    <section class="dashboard-panel">
        <h2>Jasa Anda</h2><?php if($items):?><div class="card-grid"><?php foreach($items as $x):?><article
                class="job-card">
                <p class="eyebrow"><?=e($x['kategori'])?></p>
                <h3 class="font-bold text-ink mt-2"><?=e($x['nama_jasa'])?></h3>
                <p class="text-xs text-slate-500 mt-2"><?=e($x['lokasi'])?></p>
                <p class="text-sm text-ink font-bold mt-5"><?=rupiah($x['harga_mulai'])?></p>
                <form method="post" data-confirm="Hapus jasa ini?"><input type="hidden" name="_csrf"
                        value="<?=e(csrf_token())?>"><button class="text-danger mt-4" name="delete"
                        value="<?=(int)$x['id']?>" type="submit">Hapus jasa</button></form>
            </article><?php endforeach?></div><?php else:?><div class="empty-state">Belum ada jasa. Publikasikan
            keahlian pertama Anda.</div><?php endif?>
    </section>
</main><?php require __DIR__.'/../includes/footer.php'; ?>