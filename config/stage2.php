<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

function ensure_portfolio_upload_dir(): string {
    $dir = dirname(__DIR__) . '/uploads/portfolio';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Folder upload tidak dapat dibuat.');
    }
    return $dir;
}
function save_portfolio_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Upload gambar gagal.');
    if (($file['size'] ?? 0) > 3 * 1024 * 1024) throw new RuntimeException('Ukuran gambar maksimal 3 MB.');
    if (!is_uploaded_file($file['tmp_name'] ?? '')) throw new RuntimeException('Berkas upload tidak valid.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Format gambar harus JPG, PNG, atau WebP.');
    $info = @getimagesize($file['tmp_name']);
    if (!$info || $info[0] > 6000 || $info[1] > 6000) throw new RuntimeException('Dimensi gambar tidak valid (maksimal 6000 × 6000 piksel).');
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], ensure_portfolio_upload_dir() . '/' . $name)) {
        throw new RuntimeException('Gambar tidak berhasil disimpan.');
    }
    return $name;
}
function delete_portfolio_image(?string $name): void {
    if (!$name || basename($name) !== $name) return;
    $path = dirname(__DIR__) . '/uploads/portfolio/' . $name;
    if (is_file($path)) @unlink($path);
}
function record_application_status(PDO $pdo, int $applicationId, string $newStatus, ?string $note, int $providerId): bool {
    $pdo->beginTransaction();
    try {
        $q = $pdo->prepare('SELECT l.status FROM lamaran l JOIN lowongan lo ON lo.id=l.lowongan_id WHERE l.id=? AND lo.penyedia_id=? FOR UPDATE');
        $q->execute([$applicationId, $providerId]);
        $old = $q->fetchColumn();
        if ($old === false) { $pdo->rollBack(); return false; }
        if ($old !== $newStatus) {
            $u = $pdo->prepare('UPDATE lamaran SET status=? WHERE id=?');
            $u->execute([$newStatus, $applicationId]);
            $h = $pdo->prepare('INSERT INTO riwayat_lamaran (lamaran_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,?,?,?,?)');
            $h->execute([$applicationId, $old, $newStatus, $note !== '' ? $note : null, $providerId]);
        } elseif ($note !== '') {
            $h = $pdo->prepare('INSERT INTO riwayat_lamaran (lamaran_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,?,?,?,?)');
            $h->execute([$applicationId, $old, $newStatus, $note, $providerId]);
        }
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
