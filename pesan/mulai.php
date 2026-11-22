<?php
// Memulai (atau membuka kembali) percakapan. Hanya POST + CSRF.
// Dua jalur: dari halaman jasa (pembeli → pemilik jasa) atau dari pesanan (kedua pihak pesanan itu).
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../config/stage3.php";
require_login();
$user = current_user();
if (!in_array($user["role"], ["pekerja", "penyedia"], true)) {
    http_response_code(403);
    exit("Fitur pesan hanya untuk pekerja dan penyedia.");
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/pesan/index.php");
}
verify_csrf();
$uid = (int) $user["id"];
$buyer = $worker = 0;
$jasaId = null;
if (!empty($_POST["pesanan_id"])) {
    $q = $pdo->prepare(
        "SELECT pembeli_id,pekerja_id,jasa_id FROM pesanan WHERE id=? AND (pembeli_id=? OR pekerja_id=?)",
    );
    $q->execute([(int) $_POST["pesanan_id"], $uid, $uid]);
    $o = $q->fetch();
    if (!$o) {
        http_response_code(404);
        exit("Pesanan tidak ditemukan.");
    }
    $buyer = (int) $o["pembeli_id"];
    $worker = (int) $o["pekerja_id"];
    $jasaId = $o["jasa_id"] !== null ? (int) $o["jasa_id"] : null;
} elseif (!empty($_POST["jasa_id"])) {
    if ($user["role"] !== "penyedia") {
        http_response_code(403);
        exit("Hanya pembeli yang dapat memulai chat dari halaman jasa.");
    }
    $q = $pdo->prepare("SELECT id,pekerja_id FROM jasa WHERE id=?");
    $q->execute([(int) $_POST["jasa_id"]]);
    $j = $q->fetch();
    if (!$j) {
        http_response_code(404);
        exit("Jasa tidak ditemukan.");
    }
    $buyer = $uid;
    $worker = (int) $j["pekerja_id"];
    $jasaId = (int) $j["id"];
} else {
    http_response_code(400);
    exit("Permintaan tidak valid.");
}
$convId = get_or_create_conversation($pdo, $buyer, $worker, $jasaId);
if ($jasaId !== null) {
    $u = $pdo->prepare("UPDATE percakapan SET jasa_id=? WHERE id=?");
    $u->execute([$jasaId, $convId]);
}
redirect("/pesan/chat.php?id=" . $convId);