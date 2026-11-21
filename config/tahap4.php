<?php
declare(strict_types=1);
require_once __DIR__.'/stage3.php';

function t4_audit(Database $pdo, ?int $uid, string $action, string $entity, ?int $entityId, array $after=[]): void {
    $q=$pdo->prepare('INSERT INTO audit_log (user_id,aksi,entitas,entitas_id,data_sesudah,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)');
    $q->execute([$uid,$action,$entity,$entityId,$after?json_encode($after,JSON_UNESCAPED_UNICODE):null,substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
}
function t4_order(Database $pdo,int $id,int $uid,string $role): ?array {
    $col=$role==='pekerja'?'o.pekerja_id':'o.pembeli_id';
    $q=$pdo->prepare("SELECT o.*,b.nama nama_pembeli,w.nama nama_pekerja,j.nama_jasa AS judul_jasa FROM pesanan o JOIN users b ON b.id=o.pembeli_id JOIN users w ON w.id=o.pekerja_id LEFT JOIN jasa j ON j.id=o.jasa_id WHERE o.id=? AND {$col}=? LIMIT 1");
    $q->execute([$id,$uid]);return $q->fetch()?:null;
}

/** Create a 5% buyer + 5% worker commission invoice. Gateway fee is paid by NusaKarya. */
function t4_create_invoice(Database $pdo,int $orderId,int $buyerId,float $rate=10.0,string $payer='pembeli'): int {
    $q=$pdo->prepare('SELECT * FROM pesanan WHERE id=? AND pembeli_id=? FOR UPDATE');$q->execute([$orderId,$buyerId]);$o=$q->fetch();
    if(!$o)throw new RuntimeException('Pesanan tidak ditemukan.');
    $existing=$pdo->prepare("SELECT id FROM invoice WHERE pesanan_id=? AND status NOT IN ('Dibatalkan','Kedaluwarsa') ORDER BY id DESC LIMIT 1");$existing->execute([$orderId]);$id=$existing->fetchColumn();if($id)return (int)$id;
    $rule=$pdo->prepare('SELECT id FROM aturan_komisi WHERE is_aktif=1 AND berlaku_mulai<=NOW() AND (berlaku_sampai IS NULL OR berlaku_sampai>=NOW()) ORDER BY berlaku_mulai DESC LIMIT 1');$rule->execute();$ruleId=$rule->fetchColumn();
    if(!$ruleId){$pdo->prepare("INSERT INTO aturan_komisi (nama,persentase,berlaku_mulai,is_aktif,dibuat_oleh) VALUES ('Komisi NusaKarya 10% (5% + 5%)',10,NOW(),1,NULL)")->execute();$ruleId=(int)$pdo->lastInsertId();}
    $subtotal=(float)$o['harga_disepakati'];$buyerFee=round($subtotal*0.05,2);$workerFee=round($subtotal*0.05,2);$total=$subtotal+$buyerFee;$commission=$buyerFee+$workerFee;
    $ver=$pdo->prepare('SELECT COALESCE(MAX(versi),0)+1 FROM invoice WHERE pesanan_id=?');$ver->execute([$orderId]);$version=(int)$ver->fetchColumn();
    $code='INV-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
    $snapshot=json_encode(['order_code'=>$o['kode_pesanan'],'service'=>$o['nama_jasa'],'subtotal'=>$subtotal,'commission_total_rate'=>10,'buyer_commission_rate'=>5,'buyer_commission'=>$buyerFee,'worker_commission_rate'=>5,'worker_commission'=>$workerFee,'gateway_fee_bearer'=>'platform'],JSON_UNESCAPED_UNICODE);
    $i=$pdo->prepare("INSERT INTO invoice (kode_invoice,pesanan_id,versi,subtotal_jasa,aturan_komisi_id,komisi_persen,komisi_nominal,komisi_pembeli_persen,komisi_pembeli_nominal,komisi_pekerja_persen,komisi_pekerja_nominal,komisi_dibayar_oleh,biaya_gateway_ditanggung,total_tagihan,snapshot_transaksi,status,diterbitkan_at,jatuh_tempo_at) VALUES (?,?,?,?,?,10,?,?,?,?,?,'pembeli','platform',?,?,'Belum Dibayar',NOW(),DATE_ADD(NOW(),INTERVAL 3 DAY))");
    $i->execute([$code,$orderId,$version,$subtotal,(int)$ruleId, $commission,5,$buyerFee,5,$workerFee,$total,$snapshot]);$invoiceId=(int)$pdo->lastInsertId();
    $c=$pdo->prepare("INSERT INTO komisi_transaksi (invoice_id,dasar_perhitungan,persentase_snapshot,nominal_komisi,dibayar_oleh,komisi_pembeli_nominal,komisi_pekerja_nominal,biaya_gateway_platform) VALUES (?,?,10,?,'pembeli',?,?,0)");$c->execute([$invoiceId,$subtotal,$commission,$buyerFee,$workerFee]);
    $pcode='PAY-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
    $p=$pdo->prepare("INSERT INTO pembayaran (invoice_id,kode_pembayaran,metode,provider,jumlah,status,instruksi_pembayaran,expires_at) VALUES (?,?,'manual','manual',?,'Menunggu','Transfer ke rekening resmi NusaKarya yang ditampilkan admin. Unggah bukti transfer untuk diverifikasi.',DATE_ADD(NOW(),INTERVAL 3 DAY))");$p->execute([$invoiceId,$pcode,$total]);
    $pdo->prepare("UPDATE pesanan SET status_pembayaran='Menunggu',status_pekerjaan='Menunggu Pembayaran' WHERE id=?")->execute([$orderId]);
    t4_audit($pdo,$buyerId,'invoice.created','invoice',$invoiceId,['order_id'=>$orderId,'total'=>$total,'buyer_fee'=>$buyerFee,'worker_fee'=>$workerFee]);return $invoiceId;
}

function t4_midtrans_config(): array {
    $localFile=__DIR__.'/midtrans.local.php';$local=is_file($localFile)?(require $localFile):[];if(!is_array($local))$local=[];
    $server=$local['server_key']??getenv('MIDTRANS_SERVER_KEY');$signature=$local['signature_secret']??getenv('MIDTRANS_SIGNATURE_SECRET_KEY');$client=$local['client_key']??getenv('MIDTRANS_CLIENT_KEY');$production=$local['production']??filter_var(getenv('MIDTRANS_IS_PRODUCTION')?:'false',FILTER_VALIDATE_BOOLEAN);
    return ['server_key'=>(string)($server?:''),'signature_secret'=>(string)($signature?:''),'client_key'=>(string)($client?:''),'production'=>(bool)$production];
}
function t4_midtrans_create_snap(Database $pdo,int $invoiceId,int $buyerId): array {
    $cfg=t4_midtrans_config();if($cfg['server_key']==='')throw new RuntimeException('Midtrans belum dikonfigurasi. Isi MIDTRANS_SERVER_KEY di environment server.');
    $q=$pdo->prepare('SELECT i.*,o.kode_pesanan,o.nama_jasa,u.nama nama_pembeli,u.email FROM invoice i JOIN pesanan o ON o.id=i.pesanan_id JOIN users u ON u.id=o.pembeli_id WHERE i.id=? AND o.pembeli_id=? LIMIT 1');$q->execute([$invoiceId,$buyerId]);$iv=$q->fetch();if(!$iv)throw new RuntimeException('Invoice tidak ditemukan.');if(in_array($iv['status'],['Lunas','Refund Penuh','Dibatalkan','Menunggu Verifikasi'],true))throw new RuntimeException('Invoice ini sudah lunas, dibatalkan, atau sedang menunggu verifikasi pembayaran.');
    $reuse=$pdo->prepare("SELECT id,snap_token,snap_redirect_url FROM pembayaran WHERE invoice_id=? AND provider='midtrans' AND status='Menunggu' AND expires_at>NOW() AND snap_redirect_url IS NOT NULL ORDER BY id DESC LIMIT 1");$reuse->execute([$invoiceId]);$existing=$reuse->fetch();if($existing)return ['payment_id'=>(int)$existing['id'],'redirect_url'=>$existing['snap_redirect_url'],'token'=>$existing['snap_token']];
    $payCode='PAY-'.date('ymdHis').'-'.strtoupper(bin2hex(random_bytes(3)));$amount=(int)round((float)$iv['total_tagihan']);if($amount<1)throw new RuntimeException('Nominal pembayaran tidak valid.');
    $pdo->prepare("INSERT INTO pembayaran (invoice_id,kode_pembayaran,metode,provider,jumlah,status,expires_at) VALUES (?,?,'gateway','midtrans',?,'Menunggu',DATE_ADD(NOW(),INTERVAL 1 DAY))")->execute([$invoiceId,$payCode,$amount]);$paymentId=(int)$pdo->lastInsertId();
    $payload=['transaction_details'=>['order_id'=>$payCode,'gross_amount'=>$amount],'item_details'=>[['id'=>'JASA-'.$iv['pesanan_id'],'price'=>$amount,'quantity'=>1,'name'=>substr((string)$iv['nama_jasa'],0,50)]],'customer_details'=>['first_name'=>substr((string)$iv['nama_pembeli'],0,50),'email'=>(string)($iv['email']??'')],'enabled_payments'=>['qris','bank_transfer','gopay','shopeepay','credit_card'],'expiry'=>['unit'=>'hour','duration'=>24],'callbacks'=>['finish'=>BASE_URL.'/penyedia/pembayaran.php?invoice='.$invoiceId.'&payment='.$paymentId]];
    $url=$cfg['production']?'https://app.midtrans.com/snap/v1/transactions':'https://app.sandbox.midtrans.com/snap/v1/transactions';
    $jsonPayload=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$curlErr='';$body=false;$http=0;
    if(function_exists('curl_init')){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/json'],CURLOPT_USERPWD=>$cfg['server_key'].':',CURLOPT_TIMEOUT=>25,CURLOPT_POSTFIELDS=>$jsonPayload]);$body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$curlErr=curl_error($ch);curl_close($ch);}
    else{$context=stream_context_create(['http'=>['method'=>'POST','header'=>"Accept: application/json\r\nContent-Type: application/json\r\nAuthorization: Basic ".base64_encode($cfg['server_key'].':')."\r\n",'content'=>$jsonPayload,'timeout'=>25,'ignore_errors'=>true]]);$body=@file_get_contents($url,false,$context);$headers=$http_response_header??[];if(isset($headers[0])&&preg_match('/\s(\d{3})\s/',$headers[0],$m))$http=(int)$m[1];$curlErr=$body===false?'HTTP request failed (allow_url_fopen may be disabled)':'';}
    $data=is_string($body)?json_decode($body,true):null;if($http<200||$http>=300||!is_array($data)||empty($data['token'])||empty($data['redirect_url'])){$pdo->prepare("UPDATE pembayaran SET status='Gagal',raw_status=? WHERE id=?")->execute([substr((string)($data['error_messages'][0]??$curlErr?:'Midtrans API error HTTP '.$http),0,75),$paymentId]);throw new RuntimeException('Gagal membuat checkout Midtrans. Periksa kredensial, metode aktif, dan log server.');}
    $pdo->prepare('UPDATE pembayaran SET snap_token=?,snap_redirect_url=?,provider_reference=? WHERE id=?')->execute([$data['token'],$data['redirect_url'],$payCode,$paymentId]);$pdo->prepare("UPDATE pembayaran SET status='Dibatalkan',raw_status='replaced_by_midtrans' WHERE invoice_id=? AND metode='manual' AND status='Menunggu'")->execute([$invoiceId]);return ['payment_id'=>$paymentId,'redirect_url'=>$data['redirect_url'],'token'=>$data['token']];
}

function t4_credit_worker_after_completion(Database $pdo,int $orderId,int $actorId): void {
    $q=$pdo->prepare('SELECT o.pekerja_id,o.kode_pesanan,i.id invoice_id,i.subtotal_jasa,i.komisi_pekerja_nominal,i.status invoice_status FROM pesanan o JOIN invoice i ON i.pesanan_id=o.id WHERE o.id=? ORDER BY i.versi DESC LIMIT 1 FOR UPDATE');$q->execute([$orderId]);$r=$q->fetch();if(!$r||$r['invoice_status']!=='Lunas')throw new RuntimeException('Pesanan hanya dapat menghasilkan saldo setelah invoice lunas.');
    $pdo->prepare('INSERT IGNORE INTO dompet_pekerja (pekerja_id) VALUES (?)')->execute([$r['pekerja_id']]);$q=$pdo->prepare('SELECT * FROM dompet_pekerja WHERE pekerja_id=? FOR UPDATE');$q->execute([$r['pekerja_id']]);$w=$q->fetch();$net=max(0,round((float)$r['subtotal_jasa']-(float)$r['komisi_pekerja_nominal'],2));
    $check=$pdo->prepare("SELECT id FROM mutasi_dompet WHERE jenis='Pendapatan Pesanan' AND referensi_tipe='pesanan' AND referensi_id=?");$check->execute([$orderId]);if($check->fetchColumn())return;
    $new=(float)$w['saldo_tersedia']+$net;$pdo->prepare('UPDATE dompet_pekerja SET saldo_tersedia=?,total_pendapatan=total_pendapatan+? WHERE id=?')->execute([$new,$net,$w['id']]);$pdo->prepare("INSERT INTO mutasi_dompet (dompet_id,jenis,arah,jumlah,saldo_setelah,referensi_tipe,referensi_id,keterangan,dibuat_oleh) VALUES (?,'Pendapatan Pesanan','Masuk',?,?,?,?,?,?)")->execute([$w['id'],$net,$new,'pesanan',$orderId,'Pendapatan bersih pesanan '.$r['kode_pesanan'].' setelah komisi pekerja 5%',$actorId]);
    notify($pdo,(int)$r['pekerja_id'],'pembayaran','Saldo tersedia','Pendapatan bersih pesanan '.$r['kode_pesanan'].' sebesar Rp'.number_format($net,0,',','.').' sudah tersedia untuk dicairkan.','/pekerja/dompet.php');
}

function t4_flash_redirect(string $message,string $path): never { flash('success',$message);redirect($path); }
