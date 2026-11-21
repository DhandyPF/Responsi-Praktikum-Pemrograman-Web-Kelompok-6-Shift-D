<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/tahap4.php';

require_login('pekerja');
$u = current_user();
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $act = $_POST['action'] ?? '';
        if ($act === 'service') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['nama_jasa'] ?? '');
            $desc = trim($_POST['deskripsi'] ?? '');
            $loc = trim($_POST['lokasi'] ?? '');
            $cat = $_POST['kategori'] ?? 'Lainnya';
            $price = (float)($_POST['harga_mulai'] ?? 0);

            if ($name === '' || $desc === '' || $loc === '' || $price < 0) {
                throw new RuntimeException('Lengkapi data jasa dengan benar.');
            }
            if (!in_array($cat, ['Desain', 'Konten', 'Teknologi', 'Administrasi', 'Lainnya'], true)) {
                $cat = 'Lainnya';
            }

            if ($id) {
                $q = $pdo->prepare('UPDATE jasa SET nama_jasa = ?, kategori = ?, lokasi = ?, harga_mulai = ?, deskripsi = ? WHERE id = ? AND pekerja_id = ?');
                $q->execute([$name, $cat, $loc, $price, $desc, $id, $u['id']]);
            } else {
                $q = $pdo->prepare("INSERT INTO jasa (pekerja_id, nama_jasa, kategori, lokasi, harga_mulai, deskripsi, status_publikasi) VALUES (?, ?, ?, ?, ?, ?, 'Aktif')");
                $q->execute([$u['id'], $name, $cat, $loc, $price, $desc]);
                $id = (int)$pdo->lastInsertId();
            }

            t4_audit($pdo, (int)$u['id'], 'service.saved', 'jasa', $id);
            t4_flash_redirect('Jasa berhasil disimpan.', '/pekerja/paket-jasa.php');
        }

        if ($act === 'package') {
            $jid = (int)$_POST['jasa_id'];
            $name = $_POST['nama_paket'] ?? 'Basic';
            $title = trim($_POST['judul_paket'] ?? '');
            $desc = trim($_POST['deskripsi'] ?? '');
            $price = (float)($_POST['harga'] ?? 0);
            $days = max(1, (int)($_POST['estimasi_hari'] ?? 1));
            $revs = max(0, (int)($_POST['jumlah_revisi'] ?? 0));

            if (!in_array($name, ['Basic', 'Standard', 'Premium'], true) || $title === '' || $desc === '' || $price <= 0) {
                throw new RuntimeException('Data paket belum valid. Harga harus lebih dari nol.');
            }

            $own = $pdo->prepare('SELECT id FROM jasa WHERE id = ? AND pekerja_id = ?');
            $own->execute([$jid, $u['id']]);
            if (!$own->fetchColumn()) {
                throw new RuntimeException('Jasa tidak ditemukan.');
            }

            $q = $pdo->prepare('INSERT INTO paket_jasa (jasa_id, nama_paket, judul_paket, deskripsi, harga, estimasi_hari, jumlah_revisi, is_aktif) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
            $q->execute([$jid, $name, $title, $desc, $price, $days, $revs]);
            t4_audit($pdo, (int)$u['id'], 'package.created', 'paket_jasa', (int)$pdo->lastInsertId());
            t4_flash_redirect('Paket berhasil ditambahkan.', '/pekerja/paket-jasa.php');
        }

        if ($act === 'toggle') {
            $pid = (int)$_POST['paket_id'];
            $q = $pdo->prepare('UPDATE paket_jasa p JOIN jasa j ON j.id = p.jasa_id SET p.is_aktif = 1 - p.is_aktif WHERE p.id = ? AND j.pekerja_id = ?');
            $q->execute([$pid, $u['id']]);
            t4_flash_redirect('Status paket diperbarui.', '/pekerja/paket-jasa.php');
        }
    } catch (Throwable $e) {
        $err = $e instanceof RuntimeException ? $e->getMessage() : 'Terjadi kesalahan. Periksa log server.';
        error_log('t4 package: ' . $e->getMessage());
    }
}

$q = $pdo->prepare('SELECT * FROM jasa WHERE pekerja_id = ? ORDER BY created_at DESC');
$q->execute([$u['id']]);
$services = $q->fetchAll();

$q = $pdo->prepare('SELECT p.*, j.nama_jasa FROM paket_jasa p JOIN jasa j ON j.id = p.jasa_id WHERE j.pekerja_id = ? ORDER BY j.id, p.urutan, p.id');
$q->execute([$u['id']]);
$packages = $q->fetchAll();

$pageTitle = 'Kelola jasa & paket';
require __DIR__ . '/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Dashboard pekerja</p>
    <h1 class="dashboard-title">Kelola jasa &amp; paket</h1>

    <?php if ($err): ?>
        <div class="alert alert-error mb-4"><?= e($err) ?></div>
    <?php endif; ?>

    <section class="dashboard-panel mb-6">
        <h2 class="font-bold text-xl mb-4">Buat / perbarui jasa</h2>
        <form method="post" class="dashboard-form">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="service">

            <label>Nama jasa
                <input class="form-control" name="nama_jasa" required>
            </label>

            <label>Kategori
                <select class="form-control" name="kategori">
                    <?php foreach (['Desain', 'Konten', 'Teknologi', 'Administrasi', 'Lainnya'] as $c): ?>
                        <option><?= e($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Lokasi
                <input class="form-control" name="lokasi" value="<?= e($u['lokasi'] ?? '') ?>" required>
            </label>

            <label>Harga mulai
                <input class="form-control" name="harga_mulai" type="number" min="0" step="1000" required>
            </label>

            <label class="dashboard-span">Deskripsi
                <textarea class="form-control" name="deskripsi" rows="4" required></textarea>
            </label>

            <button class="btn-primary dashboard-span">Simpan jasa</button>
        </form>
    </section>

    <section class="dashboard-panel mb-6">
        <h2 class="font-bold text-xl mb-4">Tambahkan paket layanan</h2>
        <?php if (!$services): ?>
            <p class="text-slate-500">Buat jasa terlebih dahulu sebelum menambahkan paket.</p>
        <?php else: ?>
            <form method="post" class="dashboard-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="package">

                <label>Pilih jasa
                    <select class="form-control" name="jasa_id">
                        <?php foreach ($services as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= e($s['nama_jasa']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Tipe paket
                    <select class="form-control" name="nama_paket">
                        <option>Basic</option>
                        <option>Standard</option>
                        <option>Premium</option>
                    </select>
                </label>

                <label>Nama paket
                    <input class="form-control" name="judul_paket" required placeholder="Contoh: Logo Starter">
                </label>

                <label>Harga (Rp)
                    <input class="form-control" name="harga" type="number" min="1" step="1000" required>
                </label>

                <label>Estimasi hari
                    <input class="form-control" name="estimasi_hari" type="number" min="1" value="3" required>
                </label>

                <label>Jumlah revisi
                    <input class="form-control" name="jumlah_revisi" type="number" min="0" value="1" required>
                </label>

                <label class="dashboard-span">Deskripsi / fitur
                    <textarea class="form-control" name="deskripsi" rows="3" required></textarea>
                </label>

                <button class="btn-primary dashboard-span">Tambah paket</button>
            </form>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel">
        <h2 class="font-bold text-xl mb-4">Paket aktif dan nonaktif</h2>
        <div class="space-y-3">
            <?php foreach ($packages as $p): ?>
                <div class="border rounded-xl p-4 flex flex-wrap justify-between gap-3">
                    <div>
                        <strong><?= e($p['nama_jasa']) ?> — <?= e($p['judul_paket']) ?></strong>
                        <p class="text-sm text-slate-500"><?= e($p['nama_paket']) ?> · <?= rupiah($p['harga']) ?> · <?= $p['estimasi_hari'] ?> hari · <?= $p['jumlah_revisi'] ?> revisi</p>
                        <p class="text-sm mt-1"><?= e($p['deskripsi']) ?></p>
                    </div>
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="paket_id" value="<?= (int)$p['id'] ?>">
                        <button class="btn-secondary"><?= $p['is_aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$packages): ?>
                <p class="text-slate-500">Belum ada paket yang dibuat.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
