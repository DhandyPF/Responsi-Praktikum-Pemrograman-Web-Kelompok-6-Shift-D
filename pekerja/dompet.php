<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/tahap4.php';

require_login('pekerja');
$u = current_user();
$err = '';

$pdo->prepare('INSERT IGNORE INTO dompet_pekerja (pekerja_id) VALUES (?)')->execute([$u['id']]);
$q = $pdo->prepare('SELECT * FROM dompet_pekerja WHERE pekerja_id = ?');
$q->execute([$u['id']]);
$wallet = $q->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $amount = (float)($_POST['jumlah'] ?? 0);
        $bank = trim($_POST['nama_bank'] ?? '');
        $account = trim($_POST['nomor_rekening'] ?? '');
        $holder = trim($_POST['nama_pemilik_rekening'] ?? '');

        if ($amount < 10000) {
            throw new RuntimeException('Minimal pencairan Rp10.000.');
        }
        if ($bank === '' || $account === '' || $holder === '') {
            throw new RuntimeException('Lengkapi bank, nomor rekening, dan nama pemilik.');
        }
        if (strlen($account) > 80 || strlen($holder) > 160) {
            throw new RuntimeException('Data rekening terlalu panjang.');
        }

        $pdo->beginTransaction();
        $q = $pdo->prepare('SELECT * FROM dompet_pekerja WHERE pekerja_id = ? FOR UPDATE');
        $q->execute([$u['id']]);
        $w = $q->fetch();
        if (!$w || (float)$w['saldo_tersedia'] < $amount) {
            throw new RuntimeException('Saldo tersedia tidak mencukupi.');
        }

        $code = 'WD-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare('INSERT INTO pencairan_pekerja (kode_pencairan, pekerja_id, dompet_id, jumlah, nama_bank, nomor_rekening, nama_pemilik_rekening) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$code, $u['id'], $w['id'], $amount, $bank, $account, $holder]);
        $pid = (int)$pdo->lastInsertId();

        $new = (float)$w['saldo_tersedia'] - $amount;
        $pending = (float)$w['saldo_diproses'] + $amount;
        $pdo->prepare('UPDATE dompet_pekerja SET saldo_tersedia = ?, saldo_diproses = ? WHERE id = ?')->execute([$new, $pending, $w['id']]);

        $pdo->prepare("INSERT INTO mutasi_dompet (dompet_id, jenis, arah, jumlah, saldo_setelah, referensi_tipe, referensi_id, keterangan, dibuat_oleh) VALUES (?, 'Permintaan Pencairan', 'Keluar', ?, ?, ?, ?, ?, ?)")
            ->execute([$w['id'], $amount, $new, 'pencairan', $pid, 'Permintaan pencairan ' . $code, $u['id']]);

        t4_audit($pdo, (int)$u['id'], 'payout.requested', 'pencairan_pekerja', $pid, ['amount' => $amount]);
        $pdo->commit();
        t4_flash_redirect('Permintaan pencairan dikirim ke admin.', '/pekerja/dompet.php');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $err = $e instanceof RuntimeException ? $e->getMessage() : 'Permintaan pencairan gagal.';
        error_log('worker wallet: ' . $e->getMessage());
    }
}

$q = $pdo->prepare('SELECT * FROM dompet_pekerja WHERE pekerja_id = ?');
$q->execute([$u['id']]);
$wallet = $q->fetch();

$q = $pdo->prepare('SELECT * FROM mutasi_dompet WHERE dompet_id = ? ORDER BY id DESC LIMIT 30');
$q->execute([$wallet['id']]);
$ledger = $q->fetchAll();

$q = $pdo->prepare('SELECT * FROM pencairan_pekerja WHERE pekerja_id = ? ORDER BY id DESC LIMIT 20');
$q->execute([$u['id']]);
$payouts = $q->fetchAll();

$pageTitle = 'Keuangan Saya';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Keuangan pekerja</p>
    <h1 class="dashboard-title">Dompet &amp; pencairan</h1>

    <?php if ($err): ?>
    <div class="alert alert-error mb-4"><?= e($err) ?></div>
    <?php endif; ?>

    <div class="grid sm:grid-cols-3 gap-4 mb-6">
        <section class="dashboard-panel">
            <p class="text-sm text-slate-500">Saldo tersedia</p>
            <h2 class="text-2xl font-bold mt-2"><?= rupiah($wallet['saldo_tersedia']) ?></h2>
        </section>
        <section class="dashboard-panel">
            <p class="text-sm text-slate-500">Sedang diproses</p>
            <h2 class="text-2xl font-bold mt-2"><?= rupiah($wallet['saldo_diproses']) ?></h2>
        </section>
        <section class="dashboard-panel">
            <p class="text-sm text-slate-500">Total dicairkan</p>
            <h2 class="text-2xl font-bold mt-2"><?= rupiah($wallet['total_dicairkan']) ?></h2>
        </section>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
        <section class="dashboard-panel">
            <h2 class="font-bold text-xl mb-3">Ajukan pencairan</h2>
            <p class="text-sm text-slate-500 mb-4">Dana tersedia setelah pembeli menyetujui hasil pekerjaan. Pencairan
                ditransfer admin ke rekening yang kamu daftarkan.</p>
            <form method="post" class="space-y-3">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

                <label class="block text-sm">Jumlah pencairan
                    <input class="form-control mt-1" type="number" name="jumlah" min="10000" step="1000"
                        max="<?= e((string)$wallet['saldo_tersedia']) ?>" required>
                </label>

                <label class="block text-sm">Bank / penyedia rekening
                    <input class="form-control mt-1" name="nama_bank" placeholder="BCA / BRI / Mandiri / e-wallet"
                        required>
                </label>

                <label class="block text-sm">Nomor rekening / akun
                    <input class="form-control mt-1" name="nomor_rekening" required>
                </label>

                <label class="block text-sm">Nama pemilik rekening
                    <input class="form-control mt-1" name="nama_pemilik_rekening" required>
                </label>

                <button class="btn-primary" <?= ((float)$wallet['saldo_tersedia'] < 10000) ? 'disabled' : '' ?>>Ajukan
                    pencairan</button>
            </form>
        </section>

        <section class="dashboard-panel">
            <h2 class="font-bold text-xl mb-3">Riwayat pencairan</h2>
            <?php foreach ($payouts as $p): ?>
            <article class="border-b py-3">
                <div class="flex justify-between gap-2">
                    <b class="text-sm"><?= e($p['kode_pencairan']) ?></b>
                    <span class="text-xs"><?= e($p['status']) ?></span>
                </div>
                <p class="text-sm mt-1"><?= rupiah($p['jumlah']) ?> · <?= e($p['nama_bank']) ?> ·
                    <?= e($p['nomor_rekening']) ?></p>
                <?php if ($p['catatan_admin']): ?>
                <p class="text-xs text-slate-500 mt-1"><?= e($p['catatan_admin']) ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
            <?php if (!$payouts): ?>
            <p class="empty-state">Belum ada permintaan pencairan.</p>
            <?php endif; ?>
        </section>
    </div>

    <section class="dashboard-panel mt-6">
        <h2 class="font-bold text-xl mb-3">Mutasi saldo</h2>
        <?php foreach ($ledger as $l): ?>
        <div class="flex flex-wrap justify-between gap-2 border-b py-3">
            <div>
                <b class="text-sm"><?= e($l['jenis']) ?></b>
                <p class="text-xs text-slate-500"><?= e($l['keterangan'] ?? '') ?> · <?= e($l['created_at']) ?></p>
            </div>
            <div class="text-right">
                <b class="<?= ($l['arah'] === 'Masuk' ? 'text-emerald-700' : 'text-slate-700') ?>">
                    <?= $l['arah'] === 'Masuk' ? '+' : '−' ?><?= rupiah($l['jumlah']) ?>
                </b>
                <p class="text-xs text-slate-500">Saldo tersedia: <?= rupiah($l['saldo_setelah']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$ledger): ?>
        <p class="empty-state">Belum ada mutasi saldo.</p>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>