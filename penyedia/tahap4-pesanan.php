<?php
require_once __DIR__.'/../config/koneksi.php';require_once __DIR__.'/../config/tahap4.php';require_login('penyedia');$u=current_user();$err='';
// Beri petunjuk migrasi yang spesifik, bukan membiarkan query tabel yang belum ada menjadi fatal error.
$requiredT4=['penawaran','penawaran_riwayat','aturan_komisi','invoice','komisi_transaksi','pembayaran','pengiriman_hasil','permintaan_revisi','audit_log'];
$missingT4=[];$checkT4=$pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
foreach($requiredT4 as $tableT4){$checkT4->execute([$tableT4]);if(!(int)$checkT4->fetchColumn())$missingT4[]=$tableT4;}
if($missingT4){http_response_code(503);$pageTitle='Pesanan & transaksi';require __DIR__.'/../includes/header.php';echo '<main class="dashboard-main"><section class="dashboard-panel"><h1 class="dashboard-title">Modul transaksi belum siap</h1><p>Tabel database berikut belum ditemukan: <b>'.htmlspecialchars(implode(', ',$missingT4),ENT_QUOTES,'UTF-8').'</b>.</p><p>Backup database, jalankan <code>database/migrations/004_tahap4_perbaikan.sql</code>, kemudian <code>database/migrasi_lamaran_pekerjaan.sql</code>, lalu muat ulang halaman ini.</p></section></main>';require __DIR__.'/../includes/footer.php';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();try{$act=$_POST['action']??'';$oid=(int)($_POST['order_id']??0);$pdo->beginTransaction();$q=$pdo->prepare('SELECT * FROM pesanan WHERE id=? AND pembeli_id=? FOR UPDATE');$q->execute([$oid,$u['id']]);$o=$q->fetch();if(!$o)throw new RuntimeException('Pesanan tidak ditemukan.');if($act==='quote_accept') {if($o['status']!=='Menunggu')throw new RuntimeException('Pesanan tidak lagi menunggu penawaran.');$qid=(int)$_POST['quote_id'];$q=$pdo->prepare("SELECT * FROM penawaran WHERE id=? AND pesanan_id=? AND status='Menunggu' AND (berlaku_sampai IS NULL OR berlaku_sampai>=NOW()) FOR UPDATE");$q->execute([$qid,$oid]);$quote=$q->fetch();if(!$quote||(int)$quote['dibuat_oleh']!==(int)$o['pekerja_id'])throw new RuntimeException('Penawaran sudah tidak aktif.');$payer='pembeli';$rate=10.0;$pdo->prepare("UPDATE penawaran SET status='Diterima',diterima_at=NOW() WHERE id=?")->execute([$qid]);$pdo->prepare("UPDATE penawaran SET status='Digantikan' WHERE pesanan_id=? AND id<>? AND status='Menunggu'")->execute([$oid,$qid]);$pdo->prepare("UPDATE pesanan SET harga_disepakati=?,estimasi_hari=?,jumlah_revisi=?,status='Dikonfirmasi',status_pekerjaan='Menunggu Pembayaran',accepted_at=NOW() WHERE id=?")->execute([$quote['harga'],$quote['estimasi_hari'],$quote['jumlah_revisi'],$oid]);$iv=t4_create_invoice($pdo,$oid,(int)$u['id'],(float)$rate,$payer);$pdo->commit();t4_flash_redirect('Penawaran diterima dan invoice dibuat.','/penyedia/tahap4-pesanan.php?id='.$oid.'&invoice='.$iv);}elseif($act==='accept_result' && $o['status_pekerjaan']==='Menunggu Review'){$pdo->prepare("UPDATE pengiriman_hasil SET status='Diterima',ditinjau_at=NOW() WHERE pesanan_id=? AND status='Dikirim'")->execute([$oid]);$pdo->prepare("UPDATE pesanan SET status='Selesai',status_pekerjaan='Selesai',completed_at=NOW() WHERE id=?")->execute([$oid]);$pdo->prepare("INSERT INTO riwayat_pesanan (pesanan_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,?,'Selesai','Hasil diterima pembeli',?)")->execute([$oid,$o['status'],$u['id']]);t4_credit_worker_after_completion($pdo,$oid,(int)$u['id']);notify($pdo,(int)$o['pekerja_id'],'pesanan','Pesanan selesai','Pembeli menerima hasil pekerjaan '.$o['kode_pesanan'],'/pekerja/tahap4-pesanan.php?id='.$oid);}elseif($act==='request_revision' && $o['status_pekerjaan']==='Menunggu Review'){$reason=trim($_POST['alasan']??'');if($reason===''||mb_strlen($reason)>2000)throw new RuntimeException('Alasan revisi wajib diisi (maksimal 2.000 karakter).');$q=$pdo->prepare('SELECT COALESCE(MAX(nomor_revisi),0)+1 FROM permintaan_revisi WHERE pesanan_id=?');$q->execute([$oid]);$num=(int)$q->fetchColumn();if($num>(int)$o['jumlah_revisi'])throw new RuntimeException('Batas revisi pada paket telah habis.');$q=$pdo->prepare("SELECT id FROM pengiriman_hasil WHERE pesanan_id=? AND status='Dikirim' ORDER BY versi DESC LIMIT 1");$q->execute([$oid]);$hid=$q->fetchColumn();$pdo->prepare("INSERT INTO permintaan_revisi (pesanan_id,pengiriman_hasil_id,diminta_oleh,nomor_revisi,alasan) VALUES (?,?,?,?,?)")->execute([$oid,$hid,$u['id'],$num,$reason]);$pdo->prepare("UPDATE pengiriman_hasil SET status='Perlu Revisi',ditinjau_at=NOW() WHERE id=?")->execute([$hid]);$pdo->prepare("UPDATE pesanan SET status_pekerjaan='Revisi' WHERE id=?")->execute([$oid]);notify($pdo,(int)$o['pekerja_id'],'pesanan','Permintaan revisi baru','Pembeli meminta revisi untuk '.$o['kode_pesanan'],'/pekerja/tahap4-pesanan.php?id='.$oid);}elseif($act==='cancel'&&in_array($o['status'],['Menunggu','Dikonfirmasi'],true)){$pdo->prepare("UPDATE pesanan SET status='Dibatalkan',status_pekerjaan='Dibatalkan',status_pembayaran='Dibatalkan' WHERE id=?")->execute([$oid]);$pdo->prepare("INSERT INTO riwayat_pesanan (pesanan_id,status_sebelumnya,status_terbaru,catatan,diubah_oleh) VALUES (?,?,'Dibatalkan','Dibatalkan pembeli',?)")->execute([$oid,$o['status'],$u['id']]);$pdo->commit();t4_flash_redirect('Pesanan dibatalkan.','/penyedia/tahap4-pesanan.php');}else throw new RuntimeException('Aksi tidak diizinkan.');}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$err=$e instanceof RuntimeException?$e->getMessage():'Aksi gagal diproses.';}}
$oid=(int)($_GET['id']??0);$params=[$u['id']];$sql='SELECT o.*,w.nama nama_pekerja FROM pesanan o JOIN users w ON w.id=o.pekerja_id WHERE o.pembeli_id=?';if($oid){$sql.=' AND o.id=?';$params[]=$oid;}$sql.=' ORDER BY o.created_at DESC LIMIT 80';$q=$pdo->prepare($sql);$q->execute($params);$orders=$q->fetchAll();$selected=$orders[0]??null;if($oid&&$selected){$q=$pdo->prepare('SELECT * FROM penawaran WHERE pesanan_id=? ORDER BY created_at DESC');$q->execute([$oid]);$quotes=$q->fetchAll();$q=$pdo->prepare('SELECT * FROM invoice WHERE pesanan_id=? ORDER BY versi DESC');$q->execute([$oid]);$invoices=$q->fetchAll();$q=$pdo->prepare('SELECT * FROM pengiriman_hasil WHERE pesanan_id=? ORDER BY versi DESC');$q->execute([$oid]);$deliveries=$q->fetchAll();}
$pageTitle='Pesanan saya';require __DIR__.'/../includes/header.php';?>
<main class="dashboard-main">
    <p class="eyebrow">Penyedia / pembeli</p>
    <h1 class="dashboard-title">Pesanan & transaksi</h1><?php if($err):?><div class="alert alert-error mb-4">
        <?=e($err)?></div><?php endif?><?php if($selected):?><section class="dashboard-panel mb-6">
        <div class="flex flex-wrap justify-between gap-3">
            <div>
                <p class="text-xs text-slate-500"><?=e($selected['kode_pesanan'])?></p>
                <h2 class="font-bold text-xl"><?=e($selected['nama_jasa'])?></h2>
                <p class="text-sm text-slate-500">Talenta: <?=e($selected['nama_pekerja'])?></p>
            </div><span class="badge-blue"><?=e($selected['status_pekerjaan'])?></span>
        </div>
        <p class="mt-3">Harga disepakati: <b><?=rupiah($selected['harga_disepakati'])?></b> · Pembayaran:
            <?=e($selected['status_pembayaran'])?></p>
        <?php foreach(($quotes??[]) as $qt):?><div class="border rounded-xl p-4 mt-4">
            <div class="flex justify-between"><b>Penawaran #<?=(int)$qt['id']?></b><b><?=rupiah($qt['harga'])?></b>
            </div>
            <p class="text-sm mt-2"><?=nl2br(e($qt['deskripsi_pekerjaan']))?></p>
            <p class="text-xs text-slate-500 mt-2"><?=$qt['estimasi_hari']?> hari · <?=$qt['jumlah_revisi']?> revisi ·
                <?=e($qt['status'])?></p><?php if($qt['status']==='Menunggu'&&$selected['status']==='Menunggu'):?><form
                method="post" class="mt-3 flex flex-wrap gap-2 items-end"><input type="hidden" name="_csrf"
                    value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="quote_accept"><input
                    type="hidden" name="order_id" value="<?=(int)$selected['id']?>"><input type="hidden" name="quote_id"
                    value="<?=(int)$qt['id']?>">
                <p class="text-sm text-slate-600">Komisi NusaKarya 10% dibagi: 5% pembeli + 5% pekerja.</p><button
                    class="btn-primary">Terima penawaran & buat invoice</button>
            </form><?php endif?>
        </div><?php endforeach?>
        <?php foreach(($invoices??[]) as $iv):?><div class="border rounded-xl p-4 mt-4">
            <div class="flex justify-between"><b>Invoice
                    <?=e($iv['kode_invoice'])?></b><span><?=e($iv['status'])?></span></div>
            <p class="text-sm mt-1">Total tagihan: <b><?=rupiah($iv['total_tagihan'])?></b></p><a
                class="btn-secondary mt-3 inline-flex"
                href="<?=BASE_URL?>/penyedia/pembayaran.php?invoice=<?=(int)$iv['id']?>">Lihat pembayaran</a>
        </div><?php endforeach?>
        <?php foreach(($deliveries??[]) as $d):?><div class="border rounded-xl p-4 mt-4"><b>Hasil versi
                <?=$d['versi']?></b>
            <p class="text-sm mt-2"><?=nl2br(e($d['catatan']))?></p><?php if($d['tautan_hasil']):?><a
                class="text-teal-700 underline" target="_blank" rel="noopener" href="<?=e($d['tautan_hasil'])?>">Buka
                hasil</a><?php endif?><?php if($selected['status_pekerjaan']==='Menunggu Review'&&$d['status']==='Dikirim'):?>
            <div class="flex flex-wrap gap-3 mt-3">
                <form method="post"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input type="hidden"
                        name="action" value="accept_result"><input type="hidden" name="order_id"
                        value="<?=(int)$selected['id']?>"><button class="btn-primary">Terima hasil & selesaikan</button>
                </form>
                <form method="post" class="flex gap-2"><input type="hidden" name="_csrf"
                        value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="request_revision"><input
                        type="hidden" name="order_id" value="<?=(int)$selected['id']?>"><input class="form-control"
                        name="alasan" placeholder="Alasan revisi" required><button class="btn-secondary">Minta
                        revisi</button></form>
            </div><?php endif?>
        </div><?php endforeach?>
        <?php if($selected['status_pekerjaan']==='Selesai'):?><a class="btn-primary inline-flex mt-4"
            href="<?=BASE_URL?>/penyedia/pesanan-detail.php?id=<?=(int)$selected['id']?>">Beri ulasan untuk jasa
            ini</a><?php endif?>
        <?php if(in_array($selected['status'],['Menunggu','Dikonfirmasi'],true)):?><form method="post" class="mt-4"
            data-confirm="Batalkan pesanan ini?"><input type="hidden" name="_csrf" value="<?=e(csrf_token())?>"><input
                type="hidden" name="action" value="cancel"><input type="hidden" name="order_id"
                value="<?=(int)$selected['id']?>"><button class="text-red-600">Batalkan pesanan</button></form>
        <?php endif?>
    </section><?php endif?>
    <section class="dashboard-panel">
        <h2 class="font-bold text-xl mb-3">Daftar pesanan</h2><?php foreach($orders as $o):?><a
            class="block border rounded-xl p-4 mb-2 hover:border-teal-500" href="?id=<?=(int)$o['id']?>">
            <div class="flex justify-between gap-3"><b><?=e($o['nama_jasa'])?></b><span
                    class="text-sm"><?=e($o['status_pekerjaan'])?></span></div>
            <p class="text-xs text-slate-500 mt-1"><?=e($o['kode_pesanan'])?> · <?=e($o['nama_pekerja'])?> ·
                <?=rupiah($o['harga_disepakati'])?></p>
        </a><?php endforeach?><?php if(!$orders):?><p class="empty-state">Belum ada pesanan. Jelajahi marketplace untuk
            memulai.</p><?php endif?>
    </section>
</main><?php require __DIR__.'/../includes/footer.php';?>