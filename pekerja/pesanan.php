<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/stage3.php';
require_login('pekerja');
$user = current_user();
$filter = $_GET['status'] ?? '';
if (!in_array($filter, ORDER_STATUSES, true)) $filter = '';
$sql = 'SELECT o.id,o.nama_jasa,o.harga,o.status,o.created_at,b.nama AS nama_pembeli,r.rating FROM pesanan o JOIN users b ON b.id=o.pembeli_id LEFT JOIN ulasan r ON r.pesanan_id=o.id WHERE o.pekerja_id=?';
$params = [(int)$user['id']];
if ($filter !== '') { $sql .= ' AND o.status=?'; $params[] = $filter; }
$sql .= ' ORDER BY o.id DESC LIMIT 200';
$q = $pdo->prepare($sql);
$q->execute($params);
$items = $q->fetchAll();
$success = flash('success');
$errorMsg = flash('error');
$pageTitle = 'Pesanan Masuk';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
 <p class="eyebrow">Pekerja / talenta</p><h1 class="dashboard-title">Pesanan masuk</h1>
 <p class="text-sm text-slate-500 mt-2">Terima, kerjakan, dan selesaikan pesanan jasa dari pembeli.</p>
 <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif ?>
 <?php if ($errorMsg): ?><div class="alert alert-error"><?= e($errorMsg) ?></div><?php endif ?>
 <section class="dashboard-panel">
  <h2>Daftar pesanan</h2>
  <div class="status-tabs" role="navigation" aria-label="Filter status">
   <a class="<?= $filter === '' ? 'active' : '' ?>" href="?">Semua</a>
   <?php foreach (ORDER_STATUSES as $s): ?><a class="<?= $filter === $s ? 'active' : '' ?>" href="?status=<?= e($s) ?>"><?= e($s) ?></a><?php endforeach ?>
  </div>
  <?php if ($items): ?>
  <div class="table-wrapper" role="region" aria-label="Daftar pesanan masuk" tabindex="0"><table class="dashboard-table"><thead><tr><th>Jasa</th><th>Pembeli</th><th>Harga</th><th>Tanggal</th><th>Status</th></tr></thead><tbody>
   <?php foreach ($items as $x): ?><tr>
    <th><a class="text-link" href="<?= BASE_URL ?>/pekerja/pesanan-detail.php?id=<?= (int)$x['id'] ?>">#<?= (int)$x['id'] ?> · <?= e($x['nama_jasa']) ?></a><?php if ($x['rating'] !== null): ?><small>Ulasan: <?= (int)$x['rating'] ?>/5</small><?php endif ?></th>
    <td><?= e($x['nama_pembeli']) ?></td><td><?= rupiah($x['harga']) ?></td><td><?= e(date('d M Y', strtotime($x['created_at']))) ?></td>
    <td><span class="<?= order_status_badge($x['status']) ?>"><?= e($x['status']) ?></span></td></tr><?php endforeach ?>
  </tbody></table></div>
  <?php else: ?><div class="empty-state mt-4">Belum ada pesanan<?= $filter !== '' ? ' dengan status ' . e($filter) : '' ?>. Publikasikan jasa agar pembeli dapat memesan.</div><?php endif ?>
 </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
