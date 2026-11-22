<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../config/stage3.php";
require_login("penyedia");
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/jasa.php");
}
verify_csrf();
$user = current_user();
$jasaId = (int) ($_POST["jasa_id"] ?? 0);
$catatan = trim((string) ($_POST["catatan"] ?? ""));
$tanggal = trim((string) ($_POST["tanggal_dibutuhkan"] ?? ""));
try {
    $orderId = create_order(
        $pdo,
        (int) $user["id"],
        $jasaId,
        $catatan,
        $tanggal,
    );
    flash(
        "success",
        "Pesanan berhasil dikirim. Menunggu konfirmasi dari pekerja.",
    );
    redirect("/penyedia/pesanan-detail.php?id=" . $orderId);
} catch (RuntimeException $e) {
    flash("error", $e->getMessage());
} catch (Throwable $e) {
    error_log("create_order: " . $e->getMessage());
    flash("error", "Pesanan gagal dibuat. Silakan coba lagi.");
}
redirect($jasaId > 0 ? "/detail-jasa.php?id=" . $jasaId : "/jasa.php");