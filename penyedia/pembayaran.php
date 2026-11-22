<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/tahap4.php';

require_login('penyedia');
$u = current_user();
$iid = (int)($_GET['invoice'] ?? 0);

$q = $pdo->prepare('SELECT i.*, o.id AS order_id, o.kode_pesanan, o.nama_jasa
                    FROM invoice i
                    JOIN pesanan o ON o.id = i.pesanan_id
                    WHERE i.id = ? AND o.pembeli_id = ?');
$q->execute([$iid, $u['id']]);
$iv = $q->fetch();

if (!$iv) {
    http_response_code(404);
    exit('Invoice tidak ditemukan.');
}

$err = '';
$success = flash('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'midtrans') {
            $result = t4_midtrans_create_snap($pdo, $iid, (int)$u['id']);
            header('Location: ' . $result['redirect_url']);
            exit;
        }

        if ($action === 'manual_new') {
            if (in_array($iv['status'], ['Lunas', 'Refund Penuh', 'Dibatalkan', 'Menunggu Verifikasi'], true)) {
                throw new RuntimeException('Invoice tidak dapat membuat pembayaran manual baru.');
            }
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE pembayaran SET status = 'Dibatalkan', raw_status = 'replaced_by_manual' WHERE invoice_id = ? AND provider = 'midtrans' AND status = 'Menunggu'")->execute([$iid]);
            $code = 'PAY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $pdo->prepare("INSERT INTO pembayaran (invoice_id, kode_pembayaran, metode, provider, jumlah, status, instruksi_pembayaran, expires_at) VALUES (?, ?, 'manual', 'manual', ?, 'Menunggu', 'Transfer ke rekening resmi NusaKarya.', DATE_ADD(NOW(), INTERVAL 3 DAY))")
                ->execute([$iid, $code, $iv['total_tagihan']]);
            $pdo->commit();
            t4_flash_redirect('Metode transfer manual dipilih. Silakan unggah bukti setelah transfer.', '/penyedia/pembayaran.php?invoice=' . $iid);
        }

        if ($action === 'manual_proof') {
            $pid = (int)($_POST['payment_id'] ?? 0);
            $q = $pdo->prepare("SELECT * FROM pembayaran WHERE id = ? AND invoice_id = ? AND metode = 'manual' AND status IN ('Menunggu', 'Gagal', 'Kedaluwarsa') FOR UPDATE");
            $q->execute([$pid, $iid]);
            $pay = $q->fetch();
            if (!$pay) {
                throw new RuntimeException('Pembayaran manual tidak tersedia.');
            }
            if (empty($_FILES['bukti']['tmp_name']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Pilih file bukti pembayaran.');
            }
            if ($_FILES['bukti']['size'] > 4 * 1024 * 1024) {
                throw new RuntimeException('Ukuran file maksimal 4 MB.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['bukti']['tmp_name']);
            $ext = match ($mime) {
                'image/jpeg'      => 'jpg',
                'image/png'       => 'png',
                'application/pdf' => 'pdf',
                default           => null,
            };
            if (!$ext) {
                throw new RuntimeException('Format bukti harus JPG, PNG, atau PDF.');
            }

            $dir = __DIR__ . '/../uploads/payments';
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
            $filename = bin2hex(random_bytes(18)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['bukti']['tmp_name'], $dir . '/' . $filename)) {
                throw new RuntimeException('Gagal menyimpan bukti.');
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE pembayaran SET bukti_path = ?, status = 'Menunggu Verifikasi', dibayar_at = NOW() WHERE id = ? AND invoice_id = ?")
                ->execute(['uploads/payments/' . $filename, $pid, $iid]);
            $pdo->prepare("UPDATE pembayaran SET status = 'Dibatalkan', raw_status = 'replaced_by_manual' WHERE invoice_id = ? AND provider = 'midtrans' AND status = 'Menunggu'")->execute([$iid]);
            $pdo->prepare("UPDATE invoice SET status = 'Menunggu Verifikasi' WHERE id = ?")->execute([$iid]);
            $pdo->prepare("UPDATE pesanan SET status_pembayaran = 'Menunggu Verifikasi' WHERE id = ?")->execute([$iv['order_id']]);
            t4_audit($pdo, (int)$u['id'], 'payment.proof_uploaded', 'pembayaran', $pid);
            $pdo->commit();
            t4_flash_redirect('Bukti pembayaran terkirim dan menunggu verifikasi admin.', '/penyedia/pembayaran.php?invoice=' . $iid);
        }

        throw new RuntimeException('Aksi tidak dikenal.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $err = $e instanceof RuntimeException ? $e->getMessage() : 'Pembayaran gagal diproses.';
        error_log('payment page: ' . $e->getMessage());
    }
}

$q = $pdo->prepare('SELECT * FROM pembayaran WHERE invoice_id = ? ORDER BY id DESC');
$q->execute([$iid]);
$payments = $q->fetchAll();

$manualPay = null;
foreach ($payments as $p) {
    if ($p['metode'] === 'manual' && in_array($p['status'], ['Menunggu', 'Gagal', 'Kedaluwarsa'], true)) {
        $manualPay = $p;
        break;
    }
}

$cfg = t4_midtrans_config();
$manualFile = __DIR__ . '/../config/manual-payment.php';
$manualAccounts = is_file($manualFile) ? (require $manualFile) : [];
if (!is_array($manualAccounts)) {
    $manualAccounts = [];
}

$pageTitle = 'Pembayaran';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Checkout aman · NusaKarya</p>
    <h1 class="dashboard-title">Pembayaran pesanan</h1>

    <?php if ($success): ?>
    <div class="alert alert-success mb-4"><?= e($success) ?></div>
    <?php endif; ?>

    <?php if ($err): ?>
    <div class="alert alert-error mb-4"><?= e($err) ?></div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-[1.2fr_.8fr] gap-6">
        <section class="dashboard-panel">
            <p class="text-sm text-slate-500"><?= e($iv['kode_invoice']) ?> · <?= e($iv['kode_pesanan']) ?></p>
            <h2 class="font-bold text-xl mt-2"><?= e($iv['nama_jasa']) ?></h2>

            <div class="space-y-3 my-5 text-sm">
                <div class="flex justify-between">
                    <span>Harga jasa</span>
                    <b><?= rupiah($iv['subtotal_jasa']) ?></b>
                </div>
                <div class="flex justify-between">
                    <span>Komisi pembeli (5%)</span>
                    <b><?= rupiah($iv['komisi_pembeli_nominal']) ?></b>
                </div>
                <div class="flex justify-between">
                    <span>Komisi pekerja (5%)</span>
                    <b><?= rupiah($iv['komisi_pekerja_nominal']) ?></b>
                </div>
                <div class="flex justify-between">
                    <span>Biaya gateway</span>
                    <b>Ditanggung NusaKarya</b>
                </div>
                <hr>
                <div class="flex justify-between text-lg">
                    <b>Total pembayaran</b>
                    <b class="text-teal-700"><?= rupiah($iv['total_tagihan']) ?></b>
                </div>
            </div>

            <p class="text-sm">Status invoice: <b><?= e($iv['status']) ?></b></p>

            <?php if (!in_array($iv['status'], ['Lunas', 'Refund Penuh', 'Dibatalkan', 'Menunggu Verifikasi'], true)): ?>
            <div class="border rounded-xl p-4 mt-5">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="font-bold">Bayar online dengan Midtrans</h3>
                    <span class="text-xs rounded-full bg-emerald-50 text-emerald-700 px-2 py-1">Direkomendasikan</span>
                </div>
                <p class="text-sm text-slate-500 mt-2">Pilih QRIS, Virtual Account, e-wallet, atau kartu debit/kredit
                    pada halaman checkout Midtrans.</p>
                <form method="post" class="mt-4">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="midtrans">
                    <button class="btn-primary"
                        <?= !$cfg['server_key'] ? 'disabled title="Midtrans belum dikonfigurasi"' : '' ?>>Lanjutkan ke
                        pembayaran Midtrans</button>
                </form>
                <?php if (!$cfg['server_key']): ?>
                <p class="text-xs text-amber-700 mt-2">Admin perlu mengisi kredensial Midtrans terlebih dahulu.
                    Pembayaran manual tetap tersedia.</p>
                <?php endif; ?>
            </div>

            <div class="border rounded-xl p-4 mt-4">
                <h3 class="font-bold">Transfer bank manual</h3>
                <p class="text-sm text-slate-500 mt-1">Transfer hanya ke rekening resmi NusaKarya. Pembayaran
                    diverifikasi secara manual.</p>

                <?php if ($manualAccounts): ?>
                <div class="rounded-lg bg-slate-50 p-3 mt-3 space-y-2">
                    <?php foreach ($manualAccounts as $account): ?>
                    <p class="text-sm">
                        <b><?= e($account['bank'] ?? 'Bank') ?></b><br>
                        <?= e($account['account'] ?? '') ?> · <?= e($account['holder'] ?? '') ?>
                    </p>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-sm text-amber-700 mt-2">Rekening resmi belum diatur. Hubungi admin NusaKarya sebelum
                    melakukan transfer.</p>
                <?php endif; ?>

                <?php if ($manualPay): ?>
                <form method="post" enctype="multipart/form-data" class="mt-4 space-y-3">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="manual_proof">
                    <input type="hidden" name="payment_id" value="<?= (int)$manualPay['id'] ?>">

                    <label class="block text-sm">Unggah bukti transfer (JPG/PNG/PDF, maks. 4 MB)
                        <input class="form-control mt-2" type="file" name="bukti"
                            accept="image/jpeg,image/png,application/pdf" required>
                    </label>

                    <button class="btn-secondary">Kirim bukti untuk diverifikasi</button>
                </form>
                <?php else: ?>
                <form method="post" class="mt-3">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="manual_new">
                    <button class="btn-secondary">Gunakan transfer manual</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <aside class="dashboard-panel">
            <h3 class="font-bold text-lg mb-3">Riwayat pembayaran</h3>
            <?php foreach ($payments as $p): ?>
            <article class="border-b py-3 last:border-0">
                <div class="flex justify-between gap-2">
                    <b class="text-sm"><?= e(strtoupper($p['provider'] ?: $p['metode'])) ?></b>
                    <span class="text-xs"><?= e($p['status']) ?></span>
                </div>
                <p class="text-sm mt-1"><?= rupiah($p['jumlah']) ?> · <?= e($p['kode_pembayaran']) ?></p>
                <?php if ($p['snap_redirect_url'] && $p['status'] === 'Menunggu'): ?>
                <a class="text-sm text-teal-700 underline" href="<?= e($p['snap_redirect_url']) ?>">Lanjutkan
                    pembayaran</a>
                <?php endif; ?>
                <?php if ($p['catatan_verifikasi']): ?>
                <p class="text-xs text-slate-500 mt-1"><?= e($p['catatan_verifikasi']) ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </aside>
    </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>