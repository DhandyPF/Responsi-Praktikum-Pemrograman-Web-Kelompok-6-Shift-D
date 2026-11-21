<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

/*
 * NusaKarya Tahap 3 — helper untuk pesanan jasa, ulasan, chat, dan notifikasi.
 *
 * Pemetaan peran: "pembeli" = akun role `penyedia` (UMKM/usaha yang memesan jasa),
 * "pekerja" = akun role `pekerja` (pemilik jasa). Role admin tidak ikut chat/pesanan.
 *
 * Catatan PDO: koneksi memakai ATTR_EMULATE_PREPARES=false, sehingga satu named
 * placeholder tidak boleh dipakai dua kali dalam satu query. Karena itu seluruh query
 * di sini memakai placeholder posisi (?).
 */

const ORDER_STATUSES = ['Menunggu', 'Dikonfirmasi', 'Dikerjakan', 'Selesai', 'Dibatalkan'];

/* ---------- Umum ---------- */

function is_duplicate_key(Throwable $e): bool {
    return $e instanceof PDOException && (string)$e->getCode() === '23000';
}

function time_ago_label(?string $datetime): string {
    if (!$datetime) return '';
    return date('d M Y H:i', strtotime($datetime));
}

/** Hanya izinkan path internal aplikasi ("/x/y.php?id=1"), mencegah open redirect. */
function safe_internal_path(?string $path): ?string {
    if ($path === null || $path === '') return null;
    if ($path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\') || preg_match('/[\r\n]/', $path)) return null;
    return $path;
}

/* ---------- Notifikasi ---------- */

function notify(PDO $pdo, int $userId, string $type, string $title, string $body, ?string $link = null): void {
    $body = mb_substr($body, 0, 500);
    $title = mb_substr($title, 0, 160);
    if ($type === 'pesan' && $link !== null) {
        // Gabungkan notifikasi pesan baru dari percakapan yang sama selama belum dibaca.
        $q = $pdo->prepare("SELECT id FROM notifikasi WHERE user_id=? AND tipe='pesan' AND tautan=? AND dibaca_at IS NULL ORDER BY id DESC LIMIT 1");
        $q->execute([$userId, $link]);
        $existing = $q->fetchColumn();
        if ($existing !== false) {
            $u = $pdo->prepare('UPDATE notifikasi SET judul=?, isi=?, created_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?');
            $u->execute([$title, $body, (int)$existing, $userId]);
            return;
        }
    }
    $i = $pdo->prepare('INSERT INTO notifikasi (user_id,tipe,judul,isi,tautan) VALUES (?,?,?,?,?)');
    $i->execute([$userId, $type, $title, $body, $link]);
}

function unread_notification_count(PDO $pdo, int $userId): int {
    try {
        $q = $pdo->prepare('SELECT COUNT(*) FROM notifikasi WHERE user_id=? AND dibaca_at IS NULL');
        $q->execute([$userId]);
        return (int)$q->fetchColumn();
    } catch (Throwable $e) {
        error_log('unread_notification_count: ' . $e->getMessage());
        return 0;
    }
}

function unread_message_count(PDO $pdo, int $userId): int {
    try {
        $q = $pdo->prepare('SELECT COUNT(*) FROM pesan p JOIN percakapan c ON c.id=p.percakapan_id WHERE (c.pembeli_id=? OR c.pekerja_id=?) AND p.pengirim_id<>? AND p.dibaca_at IS NULL');
        $q->execute([$userId, $userId, $userId]);
        return (int)$q->fetchColumn();
    } catch (Throwable $e) {
        error_log('unread_message_count: ' . $e->getMessage());
        return 0;
    }
}

/* ---------- Pesanan jasa ---------- */

function order_status_badge(string $status): string {
    return match ($status) {
        'Selesai' => 'badge-green',
        'Dikonfirmasi', 'Dikerjakan' => 'badge-blue',
        'Dibatalkan' => 'badge-red',
        default => 'badge-amber',
    };
}

/**
 * Aturan transisi status.
 *  - Pekerja: Menunggu → Dikonfirmasi | Dibatalkan (menolak); Dikonfirmasi → Dikerjakan | Dibatalkan; Dikerjakan → Selesai.
 *  - Pembeli: hanya boleh membatalkan saat Menunggu atau Dikonfirmasi (belum dikerjakan).
 *  - Selesai dan Dibatalkan bersifat final.
 */
function order_allowed_transitions(string $role, string $status): array {
    if ($role === 'pekerja') {
        return match ($status) {
            'Menunggu' => ['Dikonfirmasi', 'Dibatalkan'],
            'Dikonfirmasi' => ['Dikerjakan', 'Dibatalkan'],
            'Dikerjakan' => ['Selesai'],
            default => [],
        };
    }
    if ($role === 'penyedia') {
        return in_array($status, ['Menunggu', 'Dikonfirmasi'], true) ? ['Dibatalkan'] : [];
    }
    return [];
}

/** @throws RuntimeException pesan galat aman ditampilkan ke pengguna */
function create_order(PDO $pdo, int $buyerId, int $jasaId, string $note, ?string $needDate): int {
    if (mb_strlen($note) > 2000) throw new RuntimeException('Catatan pesanan maksimal 2.000 karakter.');
    if ($needDate !== null && $needDate !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $needDate);
        if (!$d || $d->format('Y-m-d') !== $needDate) throw new RuntimeException('Format tanggal tidak valid.');
        if ($needDate < date('Y-m-d')) throw new RuntimeException('Tanggal dibutuhkan tidak boleh di masa lalu.');
    } else {
        $needDate = null;
    }
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT id,pekerja_id,nama_jasa,harga_mulai FROM jasa WHERE id=? LIMIT 1');
        $q->execute([$jasaId]);
        $jasa = $q->fetch();
        if (!$jasa) throw new RuntimeException('Jasa tidak ditemukan atau sudah dihapus.');
        if ((int)$jasa['pekerja_id'] === $buyerId) throw new RuntimeException('Anda tidak dapat memesan jasa milik sendiri.');
        $i = $pdo->prepare('INSERT INTO pesanan (pembeli_id,pekerja_id,jasa_id,nama_jasa,harga,catatan,tanggal_dibutuhkan,status) VALUES (?,?,?,?,?,?,?,?)');
        $i->execute([$buyerId, (int)$jasa['pekerja_id'], (int)$jasa['id'], $jasa['nama_jasa'], $jasa['harga_mulai'], $note !== '' ? $note : null, $needDate, 'Menunggu']);
        $orderId = (int)$pdo->lastInsertId();
        $h = $pdo->prepare('INSERT INTO riwayat_pesanan (pesanan_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,NULL,?,?,?)');
        $h->execute([$orderId, 'Menunggu', 'Pesanan dibuat', $buyerId]);
        $b = $pdo->prepare('SELECT nama FROM users WHERE id=?');
        $b->execute([$buyerId]);
        $buyerName = (string)$b->fetchColumn();
        notify($pdo, (int)$jasa['pekerja_id'], 'pesanan', 'Pesanan jasa baru',
            $buyerName . ' memesan "' . $jasa['nama_jasa'] . '" (pesanan #' . $orderId . ').', '/pekerja/pesanan-detail.php?id=' . $orderId);
        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Ubah status pesanan oleh pekerja (pemilik jasa) atau pembeli (pembatalan).
 * Dikunci dengan SELECT ... FOR UPDATE agar dua perubahan bersamaan tidak saling menimpa.
 * @return array{0:bool,1:string} [berhasil, pesan]
 */
function change_order_status(PDO $pdo, int $orderId, string $newStatus, int $actorId, string $actorRole, string $note): array {
    if (!in_array($newStatus, ORDER_STATUSES, true)) return [false, 'Status tidak valid.'];
    if (mb_strlen($note) > 1000) return [false, 'Catatan maksimal 1.000 karakter.'];
    if (!in_array($actorRole, ['pekerja', 'penyedia'], true)) return [false, 'Peran tidak diizinkan.'];
    $ownerCol = $actorRole === 'pekerja' ? 'pekerja_id' : 'pembeli_id';
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare("SELECT id,status,pembeli_id,pekerja_id,nama_jasa FROM pesanan WHERE id=? AND {$ownerCol}=? FOR UPDATE");
        $q->execute([$orderId, $actorId]);
        $order = $q->fetch();
        if (!$order) { $pdo->rollBack(); return [false, 'Pesanan tidak ditemukan.']; }
        $old = (string)$order['status'];
        if (!in_array($newStatus, order_allowed_transitions($actorRole, $old), true)) {
            $pdo->rollBack();
            return [false, 'Perubahan status dari "' . $old . '" ke "' . $newStatus . '" tidak diperbolehkan.'];
        }
        $u = $pdo->prepare('UPDATE pesanan SET status=? WHERE id=?');
        $u->execute([$newStatus, $orderId]);
        $h = $pdo->prepare('INSERT INTO riwayat_pesanan (pesanan_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,?,?,?,?)');
        $h->execute([$orderId, $old, $newStatus, $note !== '' ? $note : null, $actorId]);

        $a = $pdo->prepare('SELECT nama FROM users WHERE id=?');
        $a->execute([$actorId]);
        $actorName = (string)$a->fetchColumn();
        if ($actorRole === 'pekerja') {
            $target = (int)$order['pembeli_id'];
            $link = '/penyedia/pesanan-detail.php?id=' . $orderId;
        } else {
            $target = (int)$order['pekerja_id'];
            $link = '/pekerja/pesanan-detail.php?id=' . $orderId;
        }
        $text = $actorName . ' mengubah pesanan #' . $orderId . ' "' . $order['nama_jasa'] . '" dari ' . $old . ' menjadi ' . $newStatus . '.';
        if ($note !== '') $text .= ' Catatan: ' . $note;
        notify($pdo, $target, 'status_pesanan', 'Status pesanan: ' . $newStatus, $text, $link);
        $pdo->commit();
        return [true, 'Status pesanan diperbarui menjadi ' . $newStatus . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Ambil pesanan milik pengguna (sebagai pembeli atau pekerja). null jika bukan miliknya. */
function fetch_order_for_user(PDO $pdo, int $orderId, int $userId, string $role): ?array {
    $col = $role === 'pekerja' ? 'o.pekerja_id' : 'o.pembeli_id';
    $q = $pdo->prepare("SELECT o.*, b.nama AS nama_pembeli, w.nama AS nama_pekerja, r.id AS ulasan_id, r.rating, r.komentar, r.created_at AS ulasan_at
        FROM pesanan o
        JOIN users b ON b.id=o.pembeli_id
        JOIN users w ON w.id=o.pekerja_id
        LEFT JOIN ulasan r ON r.pesanan_id=o.id
        WHERE o.id=? AND {$col}=? LIMIT 1");
    $q->execute([$orderId, $userId]);
    return $q->fetch() ?: null;
}

function fetch_order_history(PDO $pdo, int $orderId): array {
    $q = $pdo->prepare('SELECT r.*, u.nama AS pengubah FROM riwayat_pesanan r LEFT JOIN users u ON u.id=r.diubah_oleh WHERE r.pesanan_id=? ORDER BY r.id DESC');
    $q->execute([$orderId]);
    return $q->fetchAll();
}

/* ---------- Ulasan ---------- */

/** @throws RuntimeException pesan galat aman ditampilkan ke pengguna */
function submit_review(PDO $pdo, int $orderId, int $buyerId, $rating, string $comment): void {
    $rating = filter_var($rating, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    if ($rating === false) throw new RuntimeException('Rating harus berupa angka 1 sampai 5.');
    if (mb_strlen($comment) > 1500) throw new RuntimeException('Komentar maksimal 1.500 karakter.');
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT id,status,jasa_id,pekerja_id,nama_jasa FROM pesanan WHERE id=? AND pembeli_id=? FOR UPDATE');
        $q->execute([$orderId, $buyerId]);
        $order = $q->fetch();
        if (!$order) throw new RuntimeException('Pesanan tidak ditemukan.');
        if ($order['status'] !== 'Selesai') throw new RuntimeException('Ulasan hanya dapat diberikan setelah pesanan berstatus Selesai.');
        $c = $pdo->prepare('SELECT COUNT(*) FROM ulasan WHERE pesanan_id=?');
        $c->execute([$orderId]);
        if ((int)$c->fetchColumn() > 0) throw new RuntimeException('Pesanan ini sudah diulas.');
        $i = $pdo->prepare('INSERT INTO ulasan (pesanan_id,jasa_id,pembeli_id,pekerja_id,rating,komentar) VALUES (?,?,?,?,?,?)');
        try {
            $i->execute([$orderId, $order['jasa_id'] !== null ? (int)$order['jasa_id'] : null, $buyerId, (int)$order['pekerja_id'], $rating, $comment !== '' ? $comment : null]);
        } catch (PDOException $e) {
            if (is_duplicate_key($e)) throw new RuntimeException('Pesanan ini sudah diulas.');
            throw $e;
        }
        $b = $pdo->prepare('SELECT nama FROM users WHERE id=?');
        $b->execute([$buyerId]);
        notify($pdo, (int)$order['pekerja_id'], 'ulasan', 'Ulasan baru ' . $rating . '/5',
            (string)$b->fetchColumn() . ' memberi ulasan untuk "' . $order['nama_jasa'] . '" (pesanan #' . $orderId . ').',
            '/pekerja/pesanan-detail.php?id=' . $orderId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** @return array{avg:float,count:int} */
function jasa_rating_summary(PDO $pdo, int $jasaId): array {
    $q = $pdo->prepare('SELECT COUNT(*) AS n, AVG(rating) AS a FROM ulasan WHERE jasa_id=?');
    $q->execute([$jasaId]);
    $r = $q->fetch();
    return ['avg' => $r && $r['a'] !== null ? (float)$r['a'] : 0.0, 'count' => (int)($r['n'] ?? 0)];
}

function stars_html(float $rating): string {
    $full = (int)round($rating);
    return '<span class="stars" aria-hidden="true">' . str_repeat('★', max(0, min(5, $full))) . '<span class="stars-off">' . str_repeat('★', 5 - max(0, min(5, $full))) . '</span></span>';
}

/* ---------- Chat ---------- */

/** Peran lawan bicara harus kebalikan: satu pembeli (penyedia) dan satu pekerja. */
function get_or_create_conversation(PDO $pdo, int $buyerId, int $workerId, ?int $jasaId = null): int {
    $q = $pdo->prepare('SELECT id FROM percakapan WHERE pembeli_id=? AND pekerja_id=? LIMIT 1');
    $q->execute([$buyerId, $workerId]);
    $id = $q->fetchColumn();
    if ($id !== false) return (int)$id;
    try {
        $i = $pdo->prepare('INSERT INTO percakapan (pembeli_id,pekerja_id,jasa_id) VALUES (?,?,?)');
        $i->execute([$buyerId, $workerId, $jasaId]);
        return (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        if (!is_duplicate_key($e)) throw $e;
        $q->execute([$buyerId, $workerId]);
        return (int)$q->fetchColumn();
    }
}

/** Percakapan hanya terlihat oleh dua pesertanya. null jika bukan peserta / tidak ada. */
function conversation_for_user(PDO $pdo, int $convId, int $userId): ?array {
    $q = $pdo->prepare('SELECT c.*, ub.nama AS nama_pembeli, uw.nama AS nama_pekerja FROM percakapan c JOIN users ub ON ub.id=c.pembeli_id JOIN users uw ON uw.id=c.pekerja_id WHERE c.id=? AND (c.pembeli_id=? OR c.pekerja_id=?) LIMIT 1');
    $q->execute([$convId, $userId, $userId]);
    $c = $q->fetch();
    if (!$c) return null;
    $c['lawan_id'] = (int)$c['pembeli_id'] === $userId ? (int)$c['pekerja_id'] : (int)$c['pembeli_id'];
    $c['lawan_nama'] = (int)$c['pembeli_id'] === $userId ? $c['nama_pekerja'] : $c['nama_pembeli'];
    return $c;
}

/** @throws RuntimeException */
function send_message(PDO $pdo, array $conversation, int $senderId, string $text): int {
    $text = trim($text);
    if ($text === '') throw new RuntimeException('Pesan tidak boleh kosong.');
    if (mb_strlen($text) > 2000) throw new RuntimeException('Pesan maksimal 2.000 karakter.');
    $convId = (int)$conversation['id'];
    if ($senderId !== (int)$conversation['pembeli_id'] && $senderId !== (int)$conversation['pekerja_id']) {
        throw new RuntimeException('Anda bukan peserta percakapan ini.');
    }
    $recipient = $senderId === (int)$conversation['pembeli_id'] ? (int)$conversation['pekerja_id'] : (int)$conversation['pembeli_id'];
    $pdo->beginTransaction();
    try {
        $i = $pdo->prepare('INSERT INTO pesan (percakapan_id,pengirim_id,isi) VALUES (?,?,?)');
        $i->execute([$convId, $senderId, $text]);
        $mid = (int)$pdo->lastInsertId();
        $u = $pdo->prepare('UPDATE percakapan SET last_message_at=CURRENT_TIMESTAMP WHERE id=?');
        $u->execute([$convId]);
        $n = $pdo->prepare('SELECT nama FROM users WHERE id=?');
        $n->execute([$senderId]);
        notify($pdo, $recipient, 'pesan', 'Pesan baru dari ' . $n->fetchColumn(), mb_substr($text, 0, 140), '/pesan/chat.php?id=' . $convId);
        $pdo->commit();
        return $mid;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Tandai pesan lawan bicara sebagai terbaca + notifikasi pesan percakapan ini. */
function mark_conversation_read(PDO $pdo, int $convId, int $userId): void {
    $u = $pdo->prepare('UPDATE pesan SET dibaca_at=CURRENT_TIMESTAMP WHERE percakapan_id=? AND pengirim_id<>? AND dibaca_at IS NULL');
    $u->execute([$convId, $userId]);
    $n = $pdo->prepare("UPDATE notifikasi SET dibaca_at=CURRENT_TIMESTAMP WHERE user_id=? AND tipe='pesan' AND tautan=? AND dibaca_at IS NULL");
    $n->execute([$userId, '/pesan/chat.php?id=' . $convId]);
}

/** Pembeli = role penyedia. Jalur chat selalu pembeli ↔ pekerja. */
function chat_pair_for(array $user, int $otherId, PDO $pdo): ?array {
    $q = $pdo->prepare('SELECT id,role FROM users WHERE id=? LIMIT 1');
    $q->execute([$otherId]);
    $other = $q->fetch();
    if (!$other) return null;
    if ($user['role'] === 'penyedia' && $other['role'] === 'pekerja') return ['buyer' => (int)$user['id'], 'worker' => (int)$other['id']];
    if ($user['role'] === 'pekerja' && $other['role'] === 'penyedia') return ['buyer' => (int)$other['id'], 'worker' => (int)$user['id']];
    return null;
}
