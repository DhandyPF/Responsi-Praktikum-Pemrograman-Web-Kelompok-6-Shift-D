<?php
require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/stage3.php';

require_login();
$user = current_user();
if (!in_array($user['role'], ['pekerja', 'penyedia'], true)) {
    http_response_code(403);
    exit('Notifikasi tersedia untuk akun pekerja dan penyedia.');
}

$uid = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $nid = (int)($_POST['id'] ?? 0);

    if ($action === 'read_all') {
        $pdo->prepare('UPDATE notifikasi SET dibaca_at = CURRENT_TIMESTAMP WHERE user_id = ? AND dibaca_at IS NULL')->execute([$uid]);
        flash('success', 'Semua notifikasi ditandai sudah dibaca.');
    } elseif ($action === 'read' || $action === 'open') {
        // Selalu dibatasi user_id sehingga tidak dapat menyentuh notifikasi pengguna lain.
        $q = $pdo->prepare('SELECT tautan FROM notifikasi WHERE id = ? AND user_id = ?');
        $q->execute([$nid, $uid]);
        $row = $q->fetch();
        if ($row) {
            $pdo->prepare('UPDATE notifikasi SET dibaca_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ? AND dibaca_at IS NULL')->execute([$nid, $uid]);
            $to = safe_internal_path($row['tautan']);
            if ($action === 'open' && $to !== null) {
                redirect($to);
            }
        }
    }
    redirect('/notifikasi.php' . (($_POST['filter'] ?? '') === 'belum' ? '?filter=belum' : ''));
}

$onlyUnread = ($_GET['filter'] ?? '') === 'belum';
$sql = 'SELECT id, tipe, judul, isi, tautan, dibaca_at, created_at FROM notifikasi WHERE user_id = ?' . ($onlyUnread ? ' AND dibaca_at IS NULL' : '') . ' ORDER BY id DESC LIMIT 100';
$q = $pdo->prepare($sql);
$q->execute([$uid]);
$items = $q->fetchAll();

$unread = unread_notification_count($pdo, $uid);
$success = flash('success');
$icons = ['pesan' => '💬', 'pesanan' => '🧾', 'status_pesanan' => '🔄', 'ulasan' => '⭐'];

$pageTitle = 'Notifikasi';
require __DIR__ . '/includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow"><?= $user['role'] === 'pekerja' ? 'Pekerja / talenta' : 'Penyedia usaha / pembeli' ?></p>
    <h1 class="dashboard-title">Notifikasi</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2><?= $unread ?> belum dibaca</h2>
            <?php if ($unread > 0): ?>
                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="read_all">
                    <button class="btn-secondary" type="submit">Tandai semua sudah dibaca</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="status-tabs" role="navigation" aria-label="Filter notifikasi">
            <a class="<?= !$onlyUnread ? 'active' : '' ?>" href="?">Semua</a>
            <a class="<?= $onlyUnread ? 'active' : '' ?>" href="?filter=belum">Belum dibaca</a>
        </div>

        <?php if ($items): ?>
            <ul class="notif-list">
                <?php foreach ($items as $n):
                    $isNew = $n['dibaca_at'] === null;
                    $to = safe_internal_path($n['tautan']);
                ?>
                    <li class="notif-item <?= $isNew ? 'unread' : '' ?>">
                        <span class="notif-icon" aria-hidden="true"><?= $icons[$n['tipe']] ?? '🔔' ?></span>
                        <div class="notif-body">
                            <b class="text-ink"><?= e($n['judul']) ?></b>
                            <?php if ($isNew): ?>
                                <span class="badge-amber">Baru</span>
                            <?php endif; ?>
                            <p class="text-sm mt-1"><?= e($n['isi']) ?></p>
                            <p class="text-xs text-slate-500 mt-1"><?= e(time_ago_label($n['created_at'])) ?></p>
                        </div>
                        <div class="notif-actions">
                            <?php if ($to !== null): ?>
                                <form method="post">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="open">
                                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                    <button class="text-link" type="submit">Buka →</button>
                                </form>
                            <?php endif; ?>
                            <?php if ($isNew): ?>
                                <form method="post">
                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="read">
                                    <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
                                    <input type="hidden" name="filter" value="<?= $onlyUnread ? 'belum' : '' ?>">
                                    <button class="text-link" type="submit">Tandai dibaca</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state mt-4"><?= $onlyUnread ? 'Tidak ada notifikasi yang belum dibaca.' : 'Belum ada notifikasi.' ?></div>
        <?php endif; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
