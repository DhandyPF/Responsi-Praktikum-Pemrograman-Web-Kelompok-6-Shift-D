<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/stage3.php';

require_login('penyedia');
$user = current_user();
$uid = (int)$user['id'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$order = $id > 0 ? fetch_order_for_user($pdo, $id, $uid, 'penyedia') : null;
if (!$order) {
    http_response_code(404);
    exit('Pesanan tidak ditemukan.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'cancel') {
            [$ok, $msg] = change_order_status($pdo, $id, 'Dibatalkan', $uid, 'penyedia', trim((string)($_POST['catatan'] ?? '')));
            if ($ok) {
                flash('success', 'Pesanan berhasil dibatalkan.');
                redirect('/penyedia/pesanan-detail.php?id=' . $id);
            }
            $error = $msg;
        } elseif ($action === 'review') {
            submit_review($pdo, $id, $uid, $_POST['rating'] ?? '', trim((string)($_POST['komentar'] ?? '')));
            flash('success', 'Terima kasih! Ulasan Anda telah dikirim.');
            redirect('/penyedia/pesanan-detail.php?id=' . $id);
        } else {
            $error = 'Aksi tidak dikenal.';
        }
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        error_log('pesanan-detail penyedia: ' . $e->getMessage());
        $error = 'Terjadi kesalahan. Silakan coba lagi.';
    }
    $order = fetch_order_for_user($pdo, $id, $uid, 'penyedia'); // muat ulang setelah aksi gagal
}

$history = fetch_order_history($pdo, $id);
$canCancel = in_array('Dibatalkan', order_allowed_transitions('penyedia', $order['status']), true);
$canReview = $order['status'] === 'Selesai' && $order['ulasan_id'] === null;
$success = flash('success');

$pageTitle = 'Detail Pesanan #' . $id;
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Penyedia usaha / pesanan</p>
    <h1 class="dashboard-title">Pesanan #<?= (int)$order['id'] ?></h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel mt-5">
        <div class="flex flex-wrap justify-between gap-3">
            <div>
                <h2><?= e($order['nama_jasa']) ?></h2>
                <p class="text-sm text-slate-500 mt-1">Pekerja: <b><?= e($order['nama_pekerja']) ?></b></p>
            </div>
            <span class="<?= order_status_badge($order['status']) ?>"><?= e($order['status']) ?></span>
        </div>

        <div class="grid md:grid-cols-3 gap-4 mt-6">
            <div class="p-4 rounded-xl info-tile">
                <p class="text-xs text-slate-500">Harga disepakati (harga mulai)</p>
                <b class="block text-ink mt-2"><?= rupiah($order['harga']) ?></b>
            </div>
            <div class="p-4 rounded-xl info-tile">
                <p class="text-xs text-slate-500">Dipesan pada</p>
                <b class="block text-ink mt-2"><?= e(time_ago_label($order['created_at'])) ?></b>
            </div>
            <div class="p-4 rounded-xl info-tile">
                <p class="text-xs text-slate-500">Dibutuhkan paling lambat</p>
                <b class="block text-ink mt-2"><?= $order['tanggal_dibutuhkan'] ? e(date('d M Y', strtotime($order['tanggal_dibutuhkan']))) : 'Fleksibel' ?></b>
            </div>
        </div>

        <h3 class="font-bold text-ink mt-6">Catatan pesanan</h3>
        <p class="text-sm mt-2"><?= nl2br(e($order['catatan'] ?: 'Tidak ada catatan tambahan.')) ?></p>

        <div class="flex flex-wrap gap-3 mt-6">
            <form method="post" action="<?= BASE_URL ?>/pesan/mulai.php">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="pesanan_id" value="<?= (int)$order['id'] ?>">
                <button class="btn-secondary" type="submit">💬 Kirim pesan ke pekerja</button>
            </form>
            <?php if ($order['jasa_id']): ?>
                <a class="btn-secondary" href="<?= BASE_URL ?>/detail-jasa.php?id=<?= (int)$order['jasa_id'] ?>">Lihat halaman jasa</a>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($canCancel): ?>
        <section class="dashboard-panel mt-6">
            <h2>Batalkan pesanan</h2>
            <p class="text-xs text-slate-500 mt-2">Pesanan hanya dapat dibatalkan saat berstatus Menunggu atau Dikonfirmasi (sebelum mulai dikerjakan).</p>
            <form method="post" class="grid md:grid-cols-[2fr_auto] gap-3 mt-4" data-confirm="Batalkan pesanan ini?">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

                <div>
                    <label class="form-label" for="catatan">Alasan pembatalan (opsional)</label>
                    <input class="form-control" id="catatan" name="catatan" maxlength="1000">
                </div>

                <div class="flex items-end">
                    <button class="btn-primary danger-btn" type="submit">Batalkan pesanan</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($order['status'] === 'Selesai'): ?>
        <section class="dashboard-panel mt-6">
            <h2>Rating &amp; ulasan</h2>
            <?php if ($order['ulasan_id'] !== null): ?>
                <p class="mt-3"><?= stars_html((float)$order['rating']) ?> <b class="text-ink"><?= (int)$order['rating'] ?>/5</b></p>
                <p class="text-sm mt-2"><?= nl2br(e($order['komentar'] ?: 'Tanpa komentar.')) ?></p>
                <p class="text-xs text-slate-500 mt-2">Diulas pada <?= e(time_ago_label($order['ulasan_at'])) ?>. Satu pesanan hanya dapat diulas satu kali.</p>
            <?php elseif ($canReview): ?>
                <form method="post" class="mt-4">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">

                    <fieldset>
                        <legend class="form-label">Rating *</legend>
                        <div class="rating-input">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                                <input type="radio" id="rate<?= $i ?>" name="rating" value="<?= $i ?>" required>
                                <label for="rate<?= $i ?>" title="<?= $i ?> bintang">
                                    <span class="sr-only"><?= $i ?> bintang</span>★
                                </label>
                            <?php endfor; ?>
                        </div>
                    </fieldset>

                    <label class="form-label mt-4" for="komentar">Komentar (opsional)</label>
                    <textarea class="form-control" id="komentar" name="komentar" rows="4" maxlength="1500" placeholder="Ceritakan pengalamanmu menggunakan jasa ini"></textarea>

                    <button class="btn-primary mt-4" type="submit">Kirim ulasan</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="dashboard-panel mt-6">
        <h2>Riwayat status</h2>
        <ol class="mt-4 space-y-4">
            <?php foreach ($history as $h): ?>
                <li class="border-l-2 border-teal pl-4">
                    <p class="font-semibold text-ink"><?= $h['status_sebelumnya'] ? e($h['status_sebelumnya']) . ' → ' : '' ?><?= e($h['status_terbaru']) ?></p>
                    <p class="text-xs text-slate-500 mt-1"><?= e(time_ago_label($h['created_at'])) ?> · <?= e($h['pengubah'] ?? 'Sistem') ?></p>
                    <?php if ($h['catatan']): ?>
                        <p class="text-sm mt-2"><?= nl2br(e($h['catatan'])) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <a class="text-link inline-block mt-5" href="<?= BASE_URL ?>/penyedia/pesanan.php">← Kembali ke pesanan</a>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
