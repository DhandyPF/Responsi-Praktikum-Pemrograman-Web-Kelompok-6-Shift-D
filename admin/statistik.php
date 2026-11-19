<?php
require_once __DIR__.'/../config/koneksi.php'; require_once __DIR__.'/../config/functions.php'; require_login('admin');
$stats=[
'Talenta'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='pekerja'")->fetchColumn(),
'Penyedia'=>(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='penyedia'")->fetchColumn(),
'Lowongan aktif'=>(int)$pdo->query("SELECT COUNT(*) FROM lowongan WHERE status='Aktif'")->fetchColumn(),
'Lamaran'=>(int)$pdo->query("SELECT COUNT(*) FROM lamaran")->fetchColumn(),
'Jasa'=>(int)$pdo->query("SELECT COUNT(*) FROM jasa")->fetchColumn(),
'Pelatihan'=>(int)$pdo->query("SELECT COUNT(*) FROM pelatihan")->fetchColumn()
];
try{$statusRows=$pdo->query("SELECT status,COUNT(*) jumlah FROM lamaran GROUP BY status")->fetchAll();}catch(PDOException $e){$statusRows=[];}
$pageTitle='Statistik Platform';require __DIR__.'/../includes/header.php';
?>
<main class="dashboard-main"><p class="eyebrow">Administrator</p><h1 class="dashboard-title">Statistik platform</h1><div class="stat-card-grid">
<?php foreach($stats as $label=>$value):?><div class="stat-card"><strong><?=number_format($value)?></strong><span><?=e($label)?></span></div><?php endforeach?>
</div><section class="dashboard-panel"><h2>Status lamaran</h2>
<?php if($statusRows):?><div class="table-wrapper" role="region" aria-label="Statistik status lamaran" tabindex="0"><table class="dashboard-table"><thead><tr><th>Status</th><th>Jumlah</th></tr></thead><tbody><?php foreach($statusRows as $row):?><tr><th><?=e($row['status'])?></th><td><?=number_format((int)$row['jumlah'])?></td></tr><?php endforeach?></tbody></table></div><?php else:?><div class="empty-state">Belum ada data lamaran untuk ditampilkan.</div><?php endif?>
</section></main><?php require __DIR__.'/../includes/footer.php'; ?>
