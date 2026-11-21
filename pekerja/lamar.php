<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/functions.php';
require_login('pekerja');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/lowongan.php');

verify_csrf();
$user = current_user();
$lowonganId = (int)($_POST['lowongan_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id FROM lowongan WHERE id=:id AND status='Aktif' LIMIT 1");
$stmt->execute(['id' => $lowonganId]);
$lowongan = $stmt->fetch();

if (!$lowongan) {
    flash('success', 'Lowongan tidak aktif atau tidak ditemukan.');
    redirect('/pekerja/lamaran.php');
}

$check = $pdo->prepare('SELECT id FROM lamaran WHERE lowongan_id=:lowongan AND pekerja_id=:pekerja LIMIT 1');
$check->execute(['lowongan' => $lowonganId, 'pekerja' => (int)$user['id']]);
if ($check->fetch()) {
    flash('success', 'Anda sudah melamar lowongan ini.');
    redirect('/pekerja/lamaran.php');
}

$insert = $pdo->prepare('INSERT INTO lamaran (lowongan_id,pekerja_id,pesan_singkat,status) VALUES (:lowongan,:pekerja,NULL,"Menunggu")');
$insert->execute(['lowongan' => $lowonganId, 'pekerja' => (int)$user['id']]);
$applicationId = (int)$pdo->lastInsertId();
$history = $pdo->prepare('INSERT INTO riwayat_lamaran (lamaran_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,?,?,?,NULL)');
$history->execute([$applicationId, 'Menunggu', 'Menunggu', 'Lamaran dikirim',]);
flash('success', 'Lamaran berhasil dikirim.');
redirect('/pekerja/lamaran.php');
