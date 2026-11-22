<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/stage2.php';
require_once __DIR__ . '/../config/tahap4.php';
require_login('penyedia');
$user = current_user();
$error = '';

function nk_whatsapp_url(?string $phone, string $message = ''): ?string {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return null;
    if (str_starts_with($digits, '0')) $digits = '62' . substr($digits, 1);
    elseif (str_starts_with($digits, '8')) $digits = '62' . $digits;
    if (strlen($digits) < 9 || strlen($digits) > 15) return null;
    return 'https://wa.me/' . $digits . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'status';
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($action === 'agreement') {
            $wage = (float)($_POST['upah'] ?? 0);
            $mode = $_POST['jenis_pelaksanaan'] ?? '';
            if ($wage <= 0 || !in_array($mode, ['online', 'offline'], true)) throw new RuntimeException('Masukkan upah kesepakatan lebih dari nol dan pilih jenis pelaksanaan.');
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT p.*,l.pekerja_id,l.status lamaran_status,lo.penyedia_id FROM pesanan p JOIN lamaran l ON l.id=p.lamaran_id JOIN lowongan lo ON lo.id=l.lowongan_id WHERE p.id=? AND lo.penyedia_id=? FOR UPDATE");
            $q->execute([$id, (int)$user['id']]);
            $order = $q->fetch();
            if (!$order || $order['lamaran_status'] !== 'Diterima' || $order['status_pekerjaan'] !== 'Menunggu Konfirmasi') throw new RuntimeException('Kesepakatan hanya dapat disimpan untuk pekerjaan yang baru diterima.');
            $pdo->prepare("UPDATE pesanan SET harga=?,harga_disepakati=?,jenis_pelaksanaan=?,status='Dikonfirmasi',status_pekerjaan='Dikerjakan',accepted_at=COALESCE(accepted_at,NOW()),started_at=NOW() WHERE id=?")->execute([$wage,$wage,$mode,$id]);
            $pdo->prepare("INSERT INTO riwayat_pesanan (pesanan_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,'Dikonfirmasi','Dikonfirmasi','Upah dan jenis pelaksanaan disepakati setelah komunikasi WhatsApp.',?)")->execute([$id,(int)$user['id']]);
            notify($pdo,(int)$order['pekerja_id'],'pesanan','Kesepakatan pekerjaan dicatat','Upah dan jenis pekerjaan telah dicatat oleh penyedia. Pekerjaan dapat dilanjutkan.','/pekerja/detail-lamaran.php?id='.(int)$order['lamaran_id']);
            $pdo->commit();
            t4_flash_redirect('Kesepakatan pekerjaan berhasil dicatat. Pekerjaan berstatus Dikerjakan.','/penyedia/kandidat.php');
        }
        if ($action === 'complete') {
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT p.*, l.pekerja_id, l.status lamaran_status, lo.penyedia_id FROM pesanan p JOIN lamaran l ON l.id=p.lamaran_id JOIN lowongan lo ON lo.id=l.lowongan_id WHERE p.id=? AND lo.penyedia_id=? FOR UPDATE");
            $q->execute([$id, (int)$user['id']]);
            $order = $q->fetch();
            if (!$order || $order['lamaran_status'] !== 'Diterima') throw new RuntimeException('Pekerjaan tidak ditemukan atau lamaran belum diterima.');
            if ($order['status_pekerjaan'] !== 'Dikerjakan') throw new RuntimeException('Pekerjaan hanya dapat diselesaikan dari status Dikerjakan.');
            if ((float)$order['harga_disepakati'] <= 0) throw new RuntimeException('Upah yang disepakati harus lebih dari nol.');
            if ($order['jenis_pelaksanaan'] === 'online') {
                $invoiceId = t4_create_invoice($pdo, $id, (int)$user['id']);
                $pdo->prepare("UPDATE pesanan SET status='Selesai',status_pekerjaan='Menunggu Pembayaran',submitted_at=NOW() WHERE id=?")->execute([$id]);
                notify($pdo, (int)$order['pekerja_id'], 'pembayaran', 'Pekerjaan selesai — menunggu pembayaran', 'Penyedia telah menyelesaikan pekerjaan. Pendapatan akan masuk setelah pembayaran invoice diverifikasi.', '/pekerja/detail-lamaran.php?id=' . (int)$order['lamaran_id']);
                $pdo->commit();
                t4_flash_redirect('Pekerjaan ditandai selesai. Invoice dibuat untuk penyedia.', '/penyedia/pembayaran.php?invoice=' . $invoiceId);
            }
            $pdo->prepare("UPDATE pesanan SET status='Selesai',status_pekerjaan='Selesai',completed_at=NOW(),submitted_at=NOW() WHERE id=?")->execute([$id]);
            $pdo->prepare("INSERT INTO riwayat_pesanan (pesanan_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,?,'Selesai','Pekerjaan offline diselesaikan penyedia; tidak ada invoice atau kredit dompet.',?)")->execute([$id, $order['status'], (int)$user['id']]);
            notify($pdo, (int)$order['pekerja_id'], 'pesanan', 'Pekerjaan offline selesai', 'Penyedia menyatakan pekerjaan offline telah selesai. Tidak ada pembayaran melalui dompet NusaKarya.', '/pekerja/detail-lamaran.php?id=' . (int)$order['lamaran_id']);
            $pdo->commit();
            t4_flash_redirect('Pekerjaan offline berhasil diselesaikan.', '/penyedia/kandidat.php');
        }

        $status = $_POST['status'] ?? '';
        $note = trim($_POST['catatan'] ?? '');
        $mode = $_POST['jenis_pelaksanaan'] ?? 'online';
        $wage = (float)($_POST['upah'] ?? 0);
        if (!in_array($status, ['Menunggu', 'Diproses', 'Diterima', 'Ditolak'], true)) throw new RuntimeException('Status tidak valid.');
        if (mb_strlen($note) > 2000) throw new RuntimeException('Catatan maksimal 2.000 karakter.');

        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT l.*,lo.judul,lo.penyedia_id,u.nama nama_pekerja FROM lamaran l JOIN lowongan lo ON lo.id=l.lowongan_id JOIN users u ON u.id=l.pekerja_id WHERE l.id=? AND lo.penyedia_id=? FOR UPDATE');
        $q->execute([$id, (int)$user['id']]);
        $app = $q->fetch();
        if (!$app) throw new RuntimeException('Lamaran tidak ditemukan atau bukan milik Anda.');
        $old = $app['status'];
        if ($old === 'Diterima' && $status !== 'Diterima') throw new RuntimeException('Lamaran yang sudah diterima tidak dapat dikembalikan ke tahap seleksi.');
        if ($status === 'Diterima') {
            $q = $pdo->prepare('SELECT id FROM pesanan WHERE lamaran_id=? LIMIT 1');
            $q->execute([$id]);
            $existingOrder = $q->fetchColumn();
            if (!$existingOrder) {
                $code = 'NK-LAM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                $ins = $pdo->prepare("INSERT INTO pesanan (kode_pesanan,pembeli_id,pekerja_id,jasa_id,nama_jasa,harga,harga_disepakati,catatan,status,status_pembayaran,status_pekerjaan,accepted_at,jenis_pelaksanaan,lamaran_id) VALUES (?,?,?,NULL,?,0,0,?,'Dikonfirmasi','Belum Ditagihkan','Menunggu Konfirmasi',NOW(),'online',?)");
                $ins->execute([$code, (int)$user['id'], (int)$app['pekerja_id'], $app['judul'], $note ?: 'Pekerjaan dari lamaran lowongan #' . $id, $id]);
                $orderId = (int)$pdo->lastInsertId();
                $pdo->prepare("INSERT INTO riwayat_pesanan (pesanan_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,NULL,'Dikonfirmasi','Lamaran diterima. Lanjutkan komunikasi WhatsApp untuk menyepakati upah dan metode pelaksanaan.',?)")->execute([$orderId, (int)$user['id']]);
            } else {
                $orderId = (int)$existingOrder;
            }
        }
        if ($old !== $status || $note !== '') {
            $pdo->prepare('UPDATE lamaran SET status=? WHERE id=?')->execute([$status, $id]);
            $pdo->prepare('INSERT INTO riwayat_lamaran (lamaran_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,?,?,?,?)')->execute([$id, $old, $status, $note !== '' ? $note : null, (int)$user['id']]);
            if ($old !== $status) {
                $title = ['Diproses' => 'Lamaran sedang diproses', 'Diterima' => 'Lamaran Anda diterima', 'Ditolak' => 'Lamaran belum dapat diterima', 'Menunggu' => 'Status lamaran diperbarui'][$status];
                $body = $status === 'Diterima'
                    ? 'Selamat! Lamaran untuk ' . $app['judul'] . ' diterima. Hubungi penyedia melalui WhatsApp untuk koordinasi pekerjaan.'
                    : 'Status lamaran untuk ' . $app['judul'] . ' berubah menjadi ' . $status . ($note !== '' ? '. Catatan: ' . $note : '.');
                notify($pdo, (int)$app['pekerja_id'], 'lamaran', $title, $body, '/pekerja/detail-lamaran.php?id=' . $id);
            }
        }
        $pdo->commit();
        flash('success', $status === 'Diterima' ? 'Kandidat diterima. Silakan hubungi pekerja untuk menyepakati upah dan metode pekerjaan.' : 'Status kandidat berhasil diperbarui.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $e instanceof RuntimeException ? $e->getMessage() : 'Gagal memproses perubahan kandidat.';
        error_log('candidate workflow: ' . $e->getMessage());
    }
    redirect('/penyedia/kandidat.php' . ($error !== '' ? '?error=' . rawurlencode($error) : ''));
}

if (isset($_GET['error'])) $error = (string)$_GET['error'];
$stmt = $pdo->prepare("SELECT l.*,u.nama,u.email,u.telepon,u.lokasi,lo.judul,p.id pesanan_id,p.kode_pesanan,p.jenis_pelaksanaan,p.harga_disepakati,p.status_pekerjaan,p.status_pembayaran,pes.invoice_id,pes.invoice_status,pes.invoice_code,pes.total_tagihan FROM lamaran l JOIN users u ON u.id=l.pekerja_id JOIN lowongan lo ON lo.id=l.lowongan_id LEFT JOIN pesanan p ON p.lamaran_id=l.id LEFT JOIN (SELECT i.pesanan_id,i.id invoice_id,i.status invoice_status,i.kode_invoice invoice_code,i.total_tagihan FROM invoice i JOIN (SELECT pesanan_id,MAX(id) max_id FROM invoice GROUP BY pesanan_id) x ON x.max_id=i.id) pes ON pes.pesanan_id=p.id WHERE lo.penyedia_id=? ORDER BY l.tanggal_lamar DESC");
$stmt->execute([(int)$user['id']]);
$items = $stmt->fetchAll();
$pageTitle = 'Kelola Kandidat';
require __DIR__ . '/../includes/header.php';
$success = flash('success');
?>
<main class="dashboard-main">
    <p class="eyebrow">Penyedia usaha</p>
    <h1 class="dashboard-title">Kelola kandidat</h1>
    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
    <section class="dashboard-panel">
        <h2>Pelamar masuk</h2>
        <?php if ($items): ?><div class="space-y-5 mt-4">
            <?php foreach ($items as $x):
   $wa = nk_whatsapp_url($x['telepon'], 'Halo ' . $x['nama'] . ', saya menghubungi Anda terkait lamaran ' . $x['judul'] . ' di NusaKarya.');
   $statusClass = $x['status'] === 'Diterima' ? 'badge-green' : ($x['status'] === 'Ditolak' ? 'badge-amber' : 'badge-blue');
 ?>
            <article class="surface-card p-4">
                <div class="flex flex-wrap justify-between gap-3">
                    <div><b><?= e($x['nama']) ?></b>
                        <p class="text-xs text-slate-500 mt-1"><?= e($x['email']) ?> · <?= e($x['lokasi']) ?> ·
                            <?= e($x['telepon'] ?: 'Nomor WhatsApp belum tersedia') ?></p>
                        <p class="text-sm mt-2">Melamar: <b><?= e($x['judul']) ?></b> ·
                            <?= e(date('d M Y', strtotime($x['tanggal_lamar']))) ?></p>
                    </div><span class="<?= $statusClass ?>"><?= e($x['status']) ?></span>
                </div>
                <?php if ($x['status'] === 'Diterima' && $x['pesanan_id']): ?>
                <div class="mt-4 rounded-xl bg-slate-50 p-3">
                    <p class="font-semibold">Pekerjaan <?= e($x['kode_pesanan']) ?></p>
                    <p class="text-sm mt-1">Mode: <?= e(strtoupper((string)$x['jenis_pelaksanaan'])) ?> · Upah
                        disepakati: <b><?= rupiah($x['harga_disepakati']) ?></b></p>
                    <p class="text-sm">Status pekerjaan: <b><?= e($x['status_pekerjaan']) ?></b> · Pembayaran:
                        <?= e($x['status_pembayaran']) ?></p>
                    <?php if ($x['invoice_id']): ?><p class="text-sm mt-1">Invoice <?= e($x['invoice_code']) ?> ·
                        <?= e($x['invoice_status']) ?> · <?= rupiah($x['total_tagihan']) ?></p><a
                        class="btn-secondary inline-flex mt-2"
                        href="<?= BASE_URL ?>/penyedia/pembayaran.php?invoice=<?= (int)$x['invoice_id'] ?>">Buka
                        invoice/pembayaran</a><?php endif ?>
                    <?php if ($wa): ?><a class="btn-secondary inline-flex mt-2" target="_blank"
                        rel="noopener noreferrer" href="<?= e($wa) ?>">Hubungi pekerja via WhatsApp</a><?php endif ?>
                    <?php if ($x['status_pekerjaan'] === 'Menunggu Konfirmasi'): ?>
                    <p class="text-sm mt-3">Hubungi pekerja terlebih dahulu untuk menyepakati upah dan apakah pekerjaan
                        dilakukan online atau offline. Setelah sepakat, catat detailnya di sini.</p>
                    <form method="post" class="grid md:grid-cols-3 gap-3 mt-3"><input type="hidden" name="_csrf"
                            value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="agreement"><input
                            type="hidden" name="id" value="<?= (int)$x['pesanan_id'] ?>"><label class="form-label">Jenis
                            pelaksanaan<select class="form-control" name="jenis_pelaksanaan" required>
                                <option value="online">Freelance online</option>
                                <option value="offline">Freelance offline</option>
                            </select></label><label class="form-label">Upah disepakati (Rp)<input class="form-control"
                                name="upah" type="number" min="1" step="1000" required></label>
                        <div class="flex items-end"><button class="btn-primary">Simpan kesepakatan</button></div>
                    </form>
                    <?php endif ?>
                    <?php if ($x['status_pekerjaan'] === 'Dikerjakan'): ?><form method="post" class="mt-3"
                        data-confirm="Tandai pekerjaan ini selesai?"><?php endif ?>
                        <?php if ($x['status_pekerjaan'] === 'Dikerjakan'): ?><input type="hidden" name="_csrf"
                            value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="complete"><input
                            type="hidden" name="id" value="<?= (int)$x['pesanan_id'] ?>"><button
                            class="btn-primary">Tandai pekerjaan selesai</button></form><?php endif ?>
                </div>
                <?php elseif ($x['status'] !== 'Diterima'): ?>
                <form method="post" class="grid md:grid-cols-[1fr_2fr_auto] gap-3 mt-4 items-end">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id"
                        value="<?= (int)$x['id'] ?>">
                    <label class="form-label">Status<select class="form-control"
                            name="status"><?php foreach (['Menunggu','Diproses','Diterima','Ditolak'] as $st): ?><option
                                value="<?= e($st) ?>" <?= $x['status'] === $st ? 'selected' : '' ?>><?= e($st) ?>
                            </option><?php endforeach ?></select></label>
                    <label class="form-label">Catatan<input class="form-control" name="catatan" maxlength="2000"
                            placeholder="Opsional"></label>
                    <button class="btn-primary" type="submit">Simpan</button>
                </form>
                <?php endif ?>
            </article>
            <?php endforeach ?>
        </div><?php else: ?><div class="empty-state">Belum ada kandidat yang melamar lowongan Anda.</div><?php endif ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>