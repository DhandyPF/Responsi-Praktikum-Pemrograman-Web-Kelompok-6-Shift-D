<?php
// Endpoint JSON polling pesan baru untuk satu percakapan (GET, hanya peserta).
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/stage3.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user = current_user();
if (!$user || !in_array($user['role'], ['pekerja', 'penyedia'], true)) { http_response_code(401); echo json_encode(['error' => 'Tidak diizinkan']); exit; }
$uid = (int)$user['id'];
$convId = (int)($_GET['id'] ?? 0);
$after = max(0, (int)($_GET['after'] ?? 0));
$conv = $convId > 0 ? conversation_for_user($pdo, $convId, $uid) : null;
if (!$conv) { http_response_code(404); echo json_encode(['error' => 'Percakapan tidak ditemukan']); exit; }
mark_conversation_read($pdo, $convId, $uid);
$q = $pdo->prepare('SELECT id,pengirim_id,isi,created_at FROM pesan WHERE percakapan_id=? AND id>? ORDER BY id ASC LIMIT 100');
$q->execute([$convId, $after]);
$out = [];
foreach ($q->fetchAll() as $m) {
    $out[] = ['id' => (int)$m['id'], 'mine' => (int)$m['pengirim_id'] === $uid, 'isi' => $m['isi'], 'waktu' => date('d M H:i', strtotime($m['created_at']))];
}
$r = $pdo->prepare('SELECT id FROM pesan WHERE percakapan_id=? AND pengirim_id=? AND dibaca_at IS NOT NULL ORDER BY id DESC LIMIT 200');
$r->execute([$convId, $uid]);
echo json_encode(['messages' => $out, 'read_ids' => array_map('intval', $r->fetchAll(PDO::FETCH_COLUMN))], JSON_UNESCAPED_UNICODE);
