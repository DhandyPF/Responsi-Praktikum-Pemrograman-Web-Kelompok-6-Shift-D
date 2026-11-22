<?php
require_once __DIR__ . "/../config/koneksi.php";
require_once __DIR__ . "/../config/stage3.php";
require_once __DIR__ . "/../config/tahap4.php";
require_login();
$user = current_user();
if (!in_array($user["role"], ["pekerja", "penyedia"], true)) {
    http_response_code(403);
    exit("Akses ditolak.");
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect("/pesan/index.php");
}
verify_csrf();
$uid = (int) $user["id"];
$cid = (int) ($_POST["conversation_id"] ?? 0);
$action = (string) ($_POST["action"] ?? "");
$conv = conversation_for_user($pdo, $cid, $uid);
if (!$conv) {
    http_response_code(404);
    exit("Percakapan tidak ditemukan.");
}
try {
    if ($action === "request" && $user["role"] === "penyedia") {
        $desc = trim((string) ($_POST["deskripsi"] ?? ""));
        $days = (int) ($_POST["hari"] ?? 5);
        if (
            $desc === "" ||
            mb_strlen($desc) > 2000 ||
            $days < 1 ||
            $days > 365
        ) {
            throw new RuntimeException(
                "Isi kebutuhan dan estimasi waktu dengan benar.",
            );
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare(
            "SELECT id FROM pesanan WHERE pembeli_id=? AND pekerja_id=? AND jasa_id <=> ? AND status='Menunggu' ORDER BY id DESC LIMIT 1 FOR UPDATE",
        );
        $q->execute([
            $conv["pembeli_id"],
            $conv["pekerja_id"],
            $conv["jasa_id"],
        ]);
        $existing = (int) $q->fetchColumn();
        if ($existing) {
            $oid = $existing;
            $pdo->prepare("UPDATE pesanan SET catatan=? WHERE id=?")->execute([
                $desc,
                $oid,
            ]);
        } else {
            $q = $pdo->prepare(
                "SELECT nama_jasa,harga_mulai FROM jasa WHERE id=? AND pekerja_id=?",
            );
            $q->execute([$conv["jasa_id"], $conv["pekerja_id"]]);
            $svc = $q->fetch();
            if (!$svc) {
                throw new RuntimeException(
                    "Jasa tidak tersedia untuk penawaran custom.",
                );
            }
            $code =
                "NK-" .
                date("ymd") .
                "-" .
                strtoupper(bin2hex(random_bytes(4)));
            $i = $pdo->prepare(
                "INSERT INTO pesanan (kode_pesanan,pembeli_id,pekerja_id,jasa_id,nama_jasa,harga,harga_disepakati,estimasi_hari,jumlah_revisi,catatan,status,status_pembayaran,status_pekerjaan) VALUES (?,?,?,?,?,?,0,?,0,?,'Menunggu','Belum Ditagihkan','Menunggu Konfirmasi')",
            );
            $i->execute([
                $code,
                $conv["pembeli_id"],
                $conv["pekerja_id"],
                $conv["jasa_id"],
                $svc["nama_jasa"] . " — Penawaran custom",
                $svc["harga_mulai"],
                $days,
                $desc,
            ]);
            $oid = (int) $pdo->lastInsertId();
            $pdo->prepare(
                "INSERT INTO riwayat_pesanan (pesanan_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,NULL,'Menunggu','Permintaan penawaran custom dari chat',?)",
            )->execute([$oid, $uid]);
        }
        notify(
            $pdo,
            (int) $conv["pekerja_id"],
            "pesanan",
            "Permintaan penawaran custom",
            "Pembeli meminta penawaran untuk jasa Anda.",
            "/pesan/chat.php?id=" . $cid,
        );
        $pdo->commit();
    } elseif ($action === "quote" && $user["role"] === "pekerja") {
        $oid = (int) ($_POST["order_id"] ?? 0);
        $price = (float) ($_POST["harga"] ?? 0);
        $days = (int) ($_POST["hari"] ?? 0);
        $revs = (int) ($_POST["revisi"] ?? 0);
        $desc = trim((string) ($_POST["deskripsi"] ?? ""));
        if (
            $price <= 0 ||
            $days < 1 ||
            $days > 365 ||
            $revs < 0 ||
            $revs > 50 ||
            $desc === "" ||
            mb_strlen($desc) > 2000
        ) {
            throw new RuntimeException(
                "Lengkapi harga, estimasi, revisi, dan ruang lingkup penawaran.",
            );
        }
        $pdo->beginTransaction();
        $q = $pdo->prepare(
            "SELECT * FROM pesanan WHERE id=? AND pekerja_id=? AND pembeli_id=? AND jasa_id <=> ? AND status='Menunggu' FOR UPDATE",
        );
        $q->execute([$oid, $uid, $conv["pembeli_id"], $conv["jasa_id"]]);
        $order = $q->fetch();
        if (!$order) {
            throw new RuntimeException(
                "Permintaan custom tidak ditemukan atau sudah diproses.",
            );
        }
        $pdo->prepare(
            "UPDATE penawaran SET status='Digantikan' WHERE pesanan_id=? AND status='Menunggu'",
        )->execute([$oid]);
        $q = $pdo->prepare(
            "INSERT INTO penawaran (pesanan_id,dibuat_oleh,harga,estimasi_hari,jumlah_revisi,deskripsi_pekerjaan,berlaku_sampai,status) VALUES (?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 72 HOUR),'Menunggu')",
        );
        $q->execute([$oid, $uid, $price, $days, $revs, $desc]);
        $qid = (int) $pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO penawaran_riwayat (penawaran_id,versi,diubah_oleh,harga,estimasi_hari,jumlah_revisi,deskripsi_pekerjaan,status) VALUES (?,1,?,?,?,?,?,'Diajukan')",
        )->execute([$qid, $uid, $price, $days, $revs, $desc]);
        notify(
            $pdo,
            (int) $conv["pembeli_id"],
            "pesanan",
            "Penawaran harga baru",
            "Pekerja mengirim penawaran sebesar Rp" .
                number_format($price, 0, ",", ".") .
                ".",
            "/pesan/chat.php?id=" . $cid,
        );
        t4_audit($pdo, $uid, "quote.created", "penawaran", $qid);
        $pdo->commit();
    } elseif ($action === "accept" && $user["role"] === "penyedia") {
        $oid = (int) ($_POST["order_id"] ?? 0);
        $qid = (int) ($_POST["quote_id"] ?? 0);
        $pdo->beginTransaction();
        $q = $pdo->prepare(
            "SELECT * FROM pesanan WHERE id=? AND pembeli_id=? AND pekerja_id=? AND jasa_id <=> ? AND status='Menunggu' FOR UPDATE",
        );
        $q->execute([$oid, $uid, $conv["pekerja_id"], $conv["jasa_id"]]);
        $order = $q->fetch();
        if (!$order) {
            throw new RuntimeException(
                "Permintaan ini sudah tidak menunggu persetujuan.",
            );
        }
        $q = $pdo->prepare(
            "SELECT * FROM penawaran WHERE id=? AND pesanan_id=? AND dibuat_oleh=? AND status='Menunggu' AND (berlaku_sampai IS NULL OR berlaku_sampai>=NOW()) FOR UPDATE",
        );
        $q->execute([$qid, $oid, $conv["pekerja_id"]]);
        $quote = $q->fetch();
        if (!$quote) {
            throw new RuntimeException(
                "Penawaran sudah kedaluwarsa atau tidak aktif.",
            );
        }
        $pdo->prepare(
            "UPDATE penawaran SET status='Diterima',diterima_at=NOW() WHERE id=?",
        )->execute([$qid]);
        $pdo->prepare(
            "UPDATE penawaran SET status='Digantikan' WHERE pesanan_id=? AND id<>? AND status='Menunggu'",
        )->execute([$oid, $qid]);
        $pdo->prepare(
            "UPDATE pesanan SET harga=?,harga_disepakati=?,estimasi_hari=?,jumlah_revisi=?,status='Dikonfirmasi',status_pekerjaan='Menunggu Pembayaran',accepted_at=NOW() WHERE id=?",
        )->execute([
            $quote["harga"],
            $quote["harga"],
            $quote["estimasi_hari"],
            $quote["jumlah_revisi"],
            $oid,
        ]);
        $invoice = t4_create_invoice($pdo, $oid, $uid, 10.0, "pembeli");
        notify(
            $pdo,
            (int) $conv["pekerja_id"],
            "pesanan",
            "Penawaran disepakati",
            "Pembeli menerima penawaran. Silakan tunggu pembayaran.",
            "/pesan/chat.php?id=" . $cid,
        );
        $pdo->commit();
        t4_flash_redirect(
            "Deal! Harga disepakati dan invoice berhasil dibuat.",
            "/penyedia/pembayaran.php?invoice=" . $invoice,
        );
    } else {
        throw new RuntimeException("Aksi tidak diizinkan.");
    }
    t4_flash_redirect(
        "Perubahan penawaran berhasil disimpan.",
        "/pesan/chat.php?id=" . $cid,
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash(
        "error",
        $e instanceof RuntimeException
            ? $e->getMessage()
            : "Penawaran gagal diproses.",
    );
    redirect("/pesan/chat.php?id=" . $cid);
}