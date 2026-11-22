<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../config/tahap4.php";
require_login("penyedia");
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/marketplace.php");
}
verify_csrf();
$u = current_user();
$pid = (int) ($_POST["paket_id"] ?? 0);
$note = trim((string) ($_POST["catatan"] ?? ""));
try {
    if (mb_strlen($note) > 2000) {
        throw new RuntimeException("Catatan maksimal 2.000 karakter.");
    }
    $pdo->beginTransaction();
    $q = $pdo->prepare(
        "SELECT p.*,j.pekerja_id,j.nama_jasa,j.id jasa_id FROM paket_jasa p JOIN jasa j ON j.id=p.jasa_id WHERE p.id=? AND p.is_aktif=1 AND j.status_publikasi='Aktif' FOR UPDATE",
    );
    $q->execute([$pid]);
    $p = $q->fetch();
    if (!$p) {
        throw new RuntimeException("Paket tidak tersedia.");
    }
    if ((int) $p["pekerja_id"] === (int) $u["id"]) {
        throw new RuntimeException("Tidak bisa memesan jasa sendiri.");
    }
    $code = "NK-" . date("ymd") . "-" . strtoupper(bin2hex(random_bytes(4)));
    $i = $pdo->prepare(
        "INSERT INTO pesanan (kode_pesanan,pembeli_id,pekerja_id,jasa_id,paket_jasa_id,nama_jasa,harga,harga_disepakati,estimasi_hari,jumlah_revisi,catatan,status,status_pembayaran,status_pekerjaan) VALUES (?,?,?,?,?,?,?,?,?,?,?,'Menunggu','Belum Ditagihkan','Menunggu Konfirmasi')",
    );
    $i->execute([
        $code,
        $u["id"],
        $p["pekerja_id"],
        $p["jasa_id"],
        $pid,
        $p["nama_jasa"] . " — " . $p["judul_paket"],
        $p["harga"],
        $p["harga"],
        $p["estimasi_hari"],
        $p["jumlah_revisi"],
        $note ?: null,
    ]);
    $oid = (int) $pdo->lastInsertId();
    $h = $pdo->prepare(
        "INSERT INTO riwayat_pesanan (pesanan_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,NULL,'Menunggu','Pesanan paket dibuat',?)",
    );
    $h->execute([$oid, $u["id"]]);
    notify(
        $pdo,
        (int) $p["pekerja_id"],
        "pesanan",
        "Pesanan paket baru",
        $u["nama"] . " memesan " . $p["judul_paket"],
        "/pekerja/tahap4-pesanan.php",
    );
    t4_audit($pdo, (int) $u["id"], "order.created", "pesanan", $oid, [
        "package_id" => $pid,
    ]);
    $pdo->commit();
    t4_flash_redirect(
        "Pesanan berhasil dibuat. Menunggu konfirmasi pekerja.",
        "/penyedia/tahap4-pesanan.php?id=" . $oid,
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash(
        "error",
        $e instanceof RuntimeException
            ? $e->getMessage()
            : "Pesanan gagal dibuat.",
    );
    redirect("/marketplace.php");
}