<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/stage3.php';
require_login('penyedia');
$user = current_user();
$filter = $_GET['status'] ?? '';
if (!in_array($filter, ORDER_STATUSES, true)) $filter = '';
$sql = 'SELECT o.id,o.nama_jasa,o.harga,o.status,o.created_at,o.jasa_id,w.nama AS nama_pekerja,r.rating FROM pesanan o JOIN users w ON w.id=o.pekerja_id LEFT JOIN ulasan r ON r.pesanan_id=o.id WHERE o.pembeli_id=?';
$params = [(int)$user['id']];
if ($filter !== '') { $sql .= ' AND o.status=?'; $params[] = $filter; }
$sql .= ' ORDER BY o.id DESC LIMIT 200';
$q = $pdo->prepare($sql);
$q->execute($params);
$items = $q->fetchAll();
$success = flash('success');
$errorMsg = flash('error');
$pageTitle = 'Pesanan Jasa Saya';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
 <p class="eyebrow">Penyedia usaha / pembeli</p><h1 class="dashboard-title">Pesanan jasa saya</h1>
 <p class="text-sm text-slate-500 mt-2">Pantau jasa yang kamu pesan dari talenta lokal.</p>
 <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif ?>
 <?php if ($errorMsg): ?><div class="alert alert-error"><?= e($errorMsg) ?></div><?php endif ?>
 <section class="dashboard-panel">
  <div class="flex flex-wrap justify-between items-center gap-3"><h2>Daftar pesanan</h2><a class="btn-primary" href="<?= BASE_URL ?>/jasa.php">Cari jasa →</a></div>
  <div class="status-tabs" role="navigation" aria-label="Filter status">
   <a class="<?= $filter === '' ? 'active' : '' ?>" href="?">Semua</a>
   <?php foreach (ORDER_STATUSES as $s): ?><a class="<?= $filter === $s ? 'active' : '' ?>" href="?status=<?= e($s) ?>"><?= e($s) ?></a><?php endforeach ?>
  </div>
  <?php if ($items): ?>
  <div class="table-wrapper" role="region" aria-label="Daftar pesanan" tabindex="0"><table class="dashboard-table"><thead><tr><th>Jasa</th><th>Pekerja</th><th>Harga</th><th>Tanggal</th><th>Status</th></tr></thead><tbody>
   <?php foreach ($items as $x): ?><tr>
    <th><a class="text-link" href="<?= BASE_URL ?>/penyedia/pesanan-detail.php?id=<?= (int)$x['id'] ?>">#<?= (int)$x['id'] ?> · <?= e($x['nama_jasa']) ?></a><?php if ($x['status'] === 'Selesai' && $x['rating'] === null): ?><small>Belum diulas</small><?php endif ?></th>
    <td><?= e($x['nama_pekerja']) ?></td><td><?= rupiah($x['harga']) ?></td><td><?= e(date('d M Y', strtotime($x['created_at']))) ?></td>
    <td><span class="<?= order_status_badge($x['status']) ?>"><?= e($x['status']) ?></span></td></tr><?php endforeach ?>
  </tbody></table></div>
  <?php else: ?><div class="empty-state mt-4">Belum ada pesanan<?= $filter !== '' ? ' dengan status ' . e($filter) : '' ?>. Jelajahi marketplace jasa untuk mulai memesan.</div><?php endif ?>
 </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
