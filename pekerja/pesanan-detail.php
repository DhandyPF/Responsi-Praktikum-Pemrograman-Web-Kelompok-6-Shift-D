<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/stage3.php';
require_login('pekerja');
$user = current_user();
$uid = (int)$user['id'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$order = $id > 0 ? fetch_order_for_user($pdo, $id, $uid, 'pekerja') : null;
if (!$order) { http_response_code(404); exit('Pesanan tidak ditemukan.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $status = (string)($_POST['status'] ?? '');
    try {
        [$ok, $msg] = change_order_status($pdo, $id, $status, $uid, 'pekerja', trim((string)($_POST['catatan'] ?? '')));
        if ($ok) { flash('success', $msg); redirect('/pekerja/pesanan-detail.php?id=' . $id); }
        $error = $msg;
    } catch (Throwable $e) {
        error_log('pesanan-detail pekerja: ' . $e->getMessage());
        $error = 'Gagal memperbarui status pesanan.';
    }
    $order = fetch_order_for_user($pdo, $id, $uid, 'pekerja');
}
$history = fetch_order_history($pdo, $id);
$next = order_allowed_transitions('pekerja', $order['status']);
$labels = ['Dikonfirmasi' => 'Terima / konfirmasi pesanan', 'Dikerjakan' => 'Mulai dikerjakan', 'Selesai' => 'Tandai selesai', 'Dibatalkan' => 'Batalkan / tolak'];
$success = flash('success');
$pageTitle = 'Detail Pesanan #' . $id;
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
 <p class="eyebrow">Pekerja / pesanan</p><h1 class="dashboard-title">Pesanan #<?= (int)$order['id'] ?></h1>
 <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif ?>
 <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
 <section class="dashboard-panel mt-5">
  <div class="flex flex-wrap justify-between gap-3"><div><h2><?= e($order['nama_jasa']) ?></h2><p class="text-sm text-slate-500 mt-1">Pembeli: <b><?= e($order['nama_pembeli']) ?></b></p></div><span class="<?= order_status_badge($order['status']) ?>"><?= e($order['status']) ?></span></div>
  <div class="grid md:grid-cols-3 gap-4 mt-6">
   <div class="p-4 rounded-xl info-tile"><p class="text-xs text-slate-500">Harga mulai</p><b class="block text-ink mt-2"><?= rupiah($order['harga']) ?></b></div>
   <div class="p-4 rounded-xl info-tile"><p class="text-xs text-slate-500">Dipesan pada</p><b class="block text-ink mt-2"><?= e(time_ago_label($order['created_at'])) ?></b></div>
   <div class="p-4 rounded-xl info-tile"><p class="text-xs text-slate-500">Dibutuhkan paling lambat</p><b class="block text-ink mt-2"><?= $order['tanggal_dibutuhkan'] ? e(date('d M Y', strtotime($order['tanggal_dibutuhkan']))) : 'Fleksibel' ?></b></div>
  </div>
  <h3 class="font-bold text-ink mt-6">Catatan dari pembeli</h3><p class="text-sm mt-2"><?= nl2br(e($order['catatan'] ?: 'Tidak ada catatan tambahan.')) ?></p>
  <form method="post" action="<?= BASE_URL ?>/pesan/mulai.php" class="mt-6"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="pesanan_id" value="<?= (int)$order['id'] ?>"><button class="btn-secondary" type="submit">💬 Kirim pesan ke pembeli</button></form>
 </section>

 <section class="dashboard-panel mt-6"><h2>Perbarui status</h2>
 <?php if ($next): ?>
  <form method="post" class="mt-4"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
   <label class="form-label" for="catatan">Catatan untuk pembeli (opsional)</label><input class="form-control" id="catatan" name="catatan" maxlength="1000" placeholder="Mis. estimasi waktu pengerjaan atau alasan penolakan">
   <div class="flex flex-wrap gap-3 mt-4"><?php foreach ($next as $s): ?><button class="<?= $s === 'Dibatalkan' ? 'btn-primary danger-btn' : 'btn-primary' ?>" type="submit" name="status" value="<?= e($s) ?>"<?= $s === 'Dibatalkan' ? ' onclick="return confirm(\'Batalkan pesanan ini?\')"' : '' ?>><?= e($labels[$s] ?? $s) ?></button><?php endforeach ?></div></form>
 <?php else: ?><div class="empty-state mt-4">Pesanan berstatus <?= e($order['status']) ?>; tidak ada perubahan status lebih lanjut.</div><?php endif ?>
 </section>

 <?php if ($order['ulasan_id'] !== null): ?>
 <section class="dashboard-panel mt-6"><h2>Ulasan dari pembeli</h2><p class="mt-3"><?= stars_html((float)$order['rating']) ?> <b class="text-ink"><?= (int)$order['rating'] ?>/5</b></p><p class="text-sm mt-2"><?= nl2br(e($order['komentar'] ?: 'Tanpa komentar.')) ?></p></section>
 <?php endif ?>

 <section class="dashboard-panel mt-6"><h2>Riwayat status</h2>
  <ol class="mt-4 space-y-4"><?php foreach ($history as $h): ?><li class="border-l-2 border-teal pl-4"><p class="font-semibold text-ink"><?= $h['status_sebelumnya'] ? e($h['status_sebelumnya']) . ' → ' : '' ?><?= e($h['status_terbaru']) ?></p><p class="text-xs text-slate-500 mt-1"><?= e(time_ago_label($h['created_at'])) ?> · <?= e($h['pengubah'] ?? 'Sistem') ?></p><?php if ($h['catatan']): ?><p class="text-sm mt-2"><?= nl2br(e($h['catatan'])) ?></p><?php endif ?></li><?php endforeach ?></ol></section>
 <a class="text-link inline-block mt-5" href="<?= BASE_URL ?>/pekerja/pesanan.php">← Kembali ke pesanan</a>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
