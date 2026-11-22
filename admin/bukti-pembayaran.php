<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';

require_login('admin');

$id = (int)($_GET['id'] ?? 0);
$q = $pdo->prepare('SELECT bukti_path FROM pembayaran WHERE id = ?');
$q->execute([$id]);
$rel = $q->fetchColumn();

if (!$rel) {
    http_response_code(404);
    exit('Bukti tidak ditemukan.');
}

$base = realpath(__DIR__ . '/../uploads/payments');
$file = realpath(__DIR__ . '/../' . $rel);

if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file);

if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) {
    http_response_code(415);
    exit('Tipe file tidak didukung.');
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="bukti-' . $id . '.' . ($mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : 'pdf')) . '"');
readfile($file);