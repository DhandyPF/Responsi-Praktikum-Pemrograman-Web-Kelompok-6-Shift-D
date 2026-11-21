<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/stage3.php';
require_login();
$user = current_user();
if (!in_array($user['role'], ['pekerja', 'penyedia'], true)) { http_response_code(403); exit('Fitur pesan hanya untuk pekerja dan penyedia.'); }
$uid = (int)$user['id'];
$convId = (int)($_GET['id'] ?? 0);
// Hanya peserta yang dapat membuka percakapan; selain itu 404 agar keberadaan percakapan tidak bocor.
$conv = $convId > 0 ? conversation_for_user($pdo, $convId, $uid) : null;
if (!$conv) { http_response_code(404); exit('Percakapan tidak ditemukan.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        send_message($pdo, $conv, $uid, (string)($_POST['isi'] ?? ''));
        redirect('/pesan/chat.php?id=' . $convId . '#bawah');
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('send_message: ' . $e->getMessage());
        $error = 'Pesan gagal dikirim. Silakan coba lagi.';
    }
}
mark_conversation_read($pdo, $convId, $uid);
$q = $pdo->prepare('SELECT * FROM (SELECT id,pengirim_id,isi,dibaca_at,created_at FROM pesan WHERE percakapan_id=? ORDER BY id DESC LIMIT 200) t ORDER BY id ASC');
$q->execute([$convId]);
$messages = $q->fetchAll();
$lastId = $messages ? (int)end($messages)['id'] : 0;
// Ringkasan permintaan custom dan penawaran harga yang terkait dengan percakapan ini.
$offerOrder = null; $chatQuotes = [];
$q = $pdo->prepare("SELECT * FROM pesanan WHERE pembeli_id=? AND pekerja_id=? AND jasa_id <=> ? AND status IN ('Menunggu','Dikonfirmasi') ORDER BY id DESC LIMIT 1");
$q->execute([$conv['pembeli_id'], $conv['pekerja_id'], $conv['jasa_id']]);
$offerOrder = $q->fetch() ?: null;
if ($offerOrder) { $q=$pdo->prepare('SELECT * FROM penawaran WHERE pesanan_id=? ORDER BY created_at DESC'); $q->execute([$offerOrder['id']]); $chatQuotes=$q->fetchAll(); }
$dealInvoiceId = 0;
if ($offerOrder && $offerOrder['status']==='Dikonfirmasi') { $q=$pdo->prepare('SELECT id FROM invoice WHERE pesanan_id=? ORDER BY versi DESC LIMIT 1'); $q->execute([$offerOrder['id']]); $dealInvoiceId=(int)$q->fetchColumn(); }
$pageTitle = 'Chat dengan ' . $conv['lawan_nama'];
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
 <a class="text-link" href="<?= BASE_URL ?>/pesan/index.php">← Semua percakapan</a>
 <section class="dashboard-panel mt-4 chat-panel">
  <div class="chat-head"><span class="avatar"><?= e(strtoupper(mb_substr($conv['lawan_nama'], 0, 1))) ?></span><div><h2><?= e($conv['lawan_nama']) ?></h2><p class="text-xs text-slate-500"><?= $user['role'] === 'pekerja' ? 'Pembeli jasa' : 'Pekerja / talenta' ?></p></div></div>
  <?php if ($conv['jasa_id']): ?>
  <div class="chat-deal-panel">
   <div class="flex flex-wrap items-center justify-between gap-2"><div><p class="eyebrow">Penawaran jasa</p><b><?= e($offerOrder['nama_jasa'] ?? 'Jasa dari marketplace') ?></b></div><?php if ($offerOrder && $offerOrder['status']==='Dikonfirmasi'): ?><span class="deal-status">Harga disepakati</span><?php elseif ($offerOrder): ?><span class="deal-status pending">Menunggu kesepakatan</span><?php endif ?></div>
   <?php if (!$offerOrder && $user['role']==='penyedia'): ?>
    <form method="post" action="<?= BASE_URL ?>/pesan/penawaran.php" class="mt-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="conversation_id" value="<?= $convId ?>"><input type="hidden" name="action" value="request"><label class="text-sm font-semibold" for="dealRequest">Ceritakan kebutuhan proyek</label><textarea class="form-control mt-2" id="dealRequest" name="deskripsi" rows="2" maxlength="2000" required placeholder="Contoh: desain landing page untuk toko online, termasuk versi mobile..."></textarea><label class="text-xs text-slate-500 block mt-2">Target pengerjaan (hari)<input class="form-control mt-1" type="number" name="hari" min="1" max="365" value="7" required></label><button class="btn-primary mt-3">Kirim permintaan penawaran</button></form>
   <?php elseif ($offerOrder && $offerOrder['status']==='Menunggu' && $user['role']==='pekerja'): ?>
    <p class="text-sm text-slate-600 mt-2">Pembeli meminta penawaran custom. Masukkan nominal dan ruang lingkup di bawah.</p>
    <form method="post" action="<?= BASE_URL ?>/pesan/penawaran.php" class="grid sm:grid-cols-2 gap-3 mt-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="conversation_id" value="<?= $convId ?>"><input type="hidden" name="order_id" value="<?= (int)$offerOrder['id'] ?>"><input type="hidden" name="action" value="quote"><label class="text-sm">Harga penawaran (Rp)<input class="form-control mt-1" type="number" name="harga" min="1" step="1000" required placeholder="1500000"></label><label class="text-sm">Estimasi pengerjaan (hari)<input class="form-control mt-1" type="number" name="hari" min="1" max="365" value="7" required></label><label class="text-sm">Jumlah revisi<input class="form-control mt-1" type="number" name="revisi" min="0" max="50" value="2" required></label><label class="text-sm sm:col-span-2">Ruang lingkup pekerjaan<textarea class="form-control mt-1" name="deskripsi" rows="2" maxlength="2000" required placeholder="Jelaskan hasil yang akan diterima pembeli"></textarea></label><button class="btn-primary sm:col-span-2">Kirim penawaran harga</button></form>
   <?php endif ?>
   <?php foreach ($chatQuotes as $qt): ?>
    <div class="chat-quote-card"><div class="flex flex-wrap justify-between gap-2"><span class="text-sm font-semibold">Penawaran <?= e($qt['status']) ?></span><b class="text-lg"><?= rupiah($qt['harga']) ?></b></div><p class="text-sm mt-2"><?= nl2br(e($qt['deskripsi_pekerjaan'])) ?></p><p class="text-xs text-slate-500 mt-2"><?= (int)$qt['estimasi_hari'] ?> hari · <?= (int)$qt['jumlah_revisi'] ?> revisi<?php if($qt['berlaku_sampai']): ?> · Berlaku sampai <?= e(date('d/m/Y H:i',strtotime($qt['berlaku_sampai']))) ?><?php endif ?></p>
     <?php if ($user['role']==='penyedia' && $offerOrder && $offerOrder['status']==='Menunggu' && $qt['status']==='Menunggu'): ?><form method="post" action="<?= BASE_URL ?>/pesan/penawaran.php" class="mt-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="conversation_id" value="<?= $convId ?>"><input type="hidden" name="order_id" value="<?= (int)$offerOrder['id'] ?>"><input type="hidden" name="quote_id" value="<?= (int)$qt['id'] ?>"><input type="hidden" name="action" value="accept"><button class="btn-primary">Setujui & deal di <?= rupiah($qt['harga']) ?></button></form><?php endif ?>
    </div>
   <?php endforeach ?>
   <?php if ($offerOrder && $offerOrder['status']==='Dikonfirmasi'): ?><p class="deal-confirmed mt-3">Deal disepakati di <b><?= rupiah($offerOrder['harga_disepakati']) ?></b>. Invoice sudah dibuat. Lanjutkan pembayaran untuk memulai pekerjaan.</p><a class="btn-primary mt-3" href="<?= BASE_URL ?>/penyedia/pembayaran.php?invoice=<?= $dealInvoiceId ?>">Lanjut ke pembayaran</a><?php endif ?>
  </div>
  <?php endif ?>
  <div id="chatLog" class="chat-log" tabindex="0" aria-live="polite" aria-label="Riwayat pesan" data-url="<?= BASE_URL ?>/pesan/ambil.php?id=<?= (int)$convId ?>" data-last="<?= $lastId ?>" data-me="<?= $uid ?>">
   <?php if (!$messages): ?><p class="chat-empty text-xs text-slate-500">Belum ada pesan. Sapa <?= e($conv['lawan_nama']) ?> untuk memulai.</p><?php endif ?>
   <?php foreach ($messages as $m): $mine = (int)$m['pengirim_id'] === $uid; ?>
   <div class="msg <?= $mine ? 'mine' : 'theirs' ?>" data-id="<?= (int)$m['id'] ?>"><div class="bubble"><?= nl2br(e($m['isi'])) ?></div><div class="msg-meta"><?= e(date('d M H:i', strtotime($m['created_at']))) ?><?php if ($mine): ?> · <span class="msg-status"><?= $m['dibaca_at'] ? '✓✓ Dibaca' : '✓ Terkirim' ?></span><?php endif ?></div></div>
   <?php endforeach ?>
   <span id="bawah"></span>
  </div>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif ?>
  <form method="post" class="chat-form" id="chatForm"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
   <label class="sr-only" for="isi">Tulis pesan</label><textarea class="form-control" id="isi" name="isi" rows="2" maxlength="2000" required placeholder="Tulis pesan… (Enter untuk kirim, Shift+Enter baris baru)"><?= e($_POST['isi'] ?? '') ?></textarea>
   <button class="btn-primary" type="submit">Kirim</button></form>
 </section>
</main>
<script src="<?= BASE_URL ?>/assets/js/chat.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
