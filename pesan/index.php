<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/stage3.php';
require_login();

$user = current_user();
if (!in_array($user['role'], ['pekerja', 'penyedia'], true)) {
    http_response_code(403);
    exit('Fitur pesan hanya untuk pekerja dan penyedia.');
}

$uid = (int) $user['id'];
$q = $pdo->prepare("SELECT c.id, c.last_message_at, c.created_at, u.nama AS lawan, u.foto AS lawan_foto, u.role AS lawan_role,
    (SELECT p.isi FROM pesan p WHERE p.percakapan_id=c.id ORDER BY p.id DESC LIMIT 1) AS terakhir,
    (SELECT p.pengirim_id FROM pesan p WHERE p.percakapan_id=c.id ORDER BY p.id DESC LIMIT 1) AS pengirim_terakhir,
    (SELECT COUNT(*) FROM pesan p WHERE p.percakapan_id=c.id AND p.pengirim_id<>? AND p.dibaca_at IS NULL) AS belum_dibaca
  FROM percakapan c
  JOIN users u ON u.id = CASE WHEN c.pembeli_id=? THEN c.pekerja_id ELSE c.pembeli_id END
  WHERE c.pembeli_id=? OR c.pekerja_id=?
  ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC LIMIT 100");
$q->execute([$uid, $uid, $uid, $uid]);
$items = $q->fetchAll();

$pageTitle = 'Pesan';
require __DIR__ . '/../includes/header.php';
?>

<main class="dashboard-main">
    <p class="eyebrow"><?= $user['role'] === 'pekerja' ? 'Pekerja / talenta' : 'Penyedia usaha / pembeli' ?></p>
    <h1 class="dashboard-title">Pesan</h1>
    <p class="text-sm text-slate-500 mt-2">Percakapan dengan
        <?= $user['role'] === 'pekerja' ? 'pembeli jasa' : 'pekerja' ?>.</p>

    <section class="dashboard-panel">
        <h2>Daftar percakapan</h2>
        <?php if ($items): ?>
        <ul class="conv-list mt-4">
            <?php foreach ($items as $c): ?>
            <li>
                <a class="conv-item <?= (int) $c['belum_dibaca'] > 0 ? 'unread' : '' ?>"
                    href="<?= BASE_URL ?>/pesan/chat.php?id=<?= (int) $c['id'] ?>">
                    <img src="<?= user_avatar(['foto' => $c['lawan_foto'], 'role' => $c['lawan_role']]) ?>" alt="" class="w-10 h-10 rounded-full object-cover shrink-0 ring-1 ring-slate-200">
                    <span class="conv-body">
                        <b class="text-ink"><?= e($c['lawan']) ?></b>
                        <span
                            class="conv-snippet"><?= $c['terakhir'] !== null ? ((int) $c['pengirim_terakhir'] === $uid ? 'Anda: ' : '') . e(mb_substr($c['terakhir'], 0, 90)) : 'Belum ada pesan. Mulai percakapan.' ?></span>
                    </span>
                    <span class="conv-meta">
                        <span
                            class="text-xs text-slate-500"><?= $c['last_message_at'] ? e(date('d M H:i', strtotime($c['last_message_at']))) : '' ?></span>
                        <?php if ((int) $c['belum_dibaca'] > 0): ?>
                        <span class="nav-badge"
                            aria-label="<?= (int) $c['belum_dibaca'] ?> pesan belum dibaca"><?= (int) $c['belum_dibaca'] ?></span>
                        <?php endif; ?>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="empty-state mt-4">
            <?= $user['role'] === 'penyedia' ? 'Belum ada percakapan. Buka detail sebuah jasa lalu pilih "Kirim pesan" untuk mulai berdiskusi.' : 'Belum ada percakapan. Pembeli yang tertarik pada jasamu akan menghubungimu di sini.' ?>
        </div>
        <?php endif; ?>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>