<?php
require_once __DIR__.'/../config/koneksi.php';require_once __DIR__.'/../config/functions.php';require_login('pekerja');
$user=current_user();
// Perbaikan Tahap 3: skill/deskripsi/lokasi tidak disimpan di session, jadi dimuat dari database agar skor akurat.
$pq=$pdo->prepare('SELECT lokasi,deskripsi,skills FROM users WHERE id=?');$pq->execute([(int)$user['id']]);$user=array_merge($user,$pq->fetch()?:[]);
$skills=preg_split('/[,;\\n]+/',mb_strtolower((string)($user['skills']??'')),-1,PREG_SPLIT_NO_EMPTY);$skills=array_values(array_filter(array_map('trim',$skills)));
$stmt=$pdo->query("SELECT l.*,u.nama penyedia,u.is_verified FROM lowongan l JOIN users u ON u.id=l.penyedia_id WHERE l.status='Aktif' AND (l.batas_lamaran IS NULL OR l.batas_lamaran>=CURDATE()) ORDER BY l.created_at DESC");$jobs=$stmt->fetchAll();
function match_score(array $job,array $user,array $skills):array{
 $reasons=[];$skillHits=[];$hay=mb_strtolower($job['judul'].' '.$job['deskripsi'].' '.($job['persyaratan']??''));
 foreach($skills as $skill){if(mb_strlen($skill)>=2&&mb_strpos($hay,$skill)!==false)$skillHits[]=$skill;}
 $skillScore=$skills?min(100,count(array_unique($skillHits))/count($skills)*100):0;
 if($skillHits)$reasons[]='Skill cocok: '.implode(', ',array_slice(array_unique($skillHits),0,5));
 $loc=trim(mb_strtolower((string)($user['lokasi']??'')));$jobLoc=trim(mb_strtolower((string)$job['lokasi']));
 $locScore=($loc!==''&&$jobLoc!=='')?($loc===$jobLoc?100:(str_contains($jobLoc,$loc)||str_contains($loc,$jobLoc)?60:0)):0;
 if($locScore>=60)$reasons[]=$locScore===100?'Lokasi sama dengan profil':'Lokasi berdekatan/serupa';
 $userText=mb_strtolower((string)($user['deskripsi']??''));$jobCategory=mb_strtolower((string)$job['kategori']);
 $catScore=($userText!==''&&str_contains($userText,$jobCategory))?100:0;
 if($catScore)$reasons[]='Kategori '.$job['kategori'].' disebut pada deskripsi profil';
 $words=array_unique(array_filter(preg_split('/[^\\pL\\pN]+/u',mb_strtolower($userText),-1,PREG_SPLIT_NO_EMPTY),fn($w)=>mb_strlen($w)>=4));
 $descHits=[];foreach($words as $word)if(mb_strpos($hay,$word)!==false)$descHits[]=$word;
 $descScore=$words?min(100,count($descHits)/min(count($words),10)*100):0;
 if($descHits)$reasons[]='Kata kunci profil relevan: '.implode(', ',array_slice($descHits,0,5));
 $score=(int)round($skillScore*.45+$locScore*.20+$catScore*.20+$descScore*.15);
 if(!$skills&&!$loc&&!$userText)$reasons[]='Lengkapi skill, lokasi, dan deskripsi profil agar rekomendasi lebih personal';
 if(!$reasons)$reasons[]='Belum ada kecocokan kuat; peluang tetap ditampilkan agar bisa dieksplorasi';
 return ['score'=>$score,'reasons'=>$reasons,'parts'=>['Skill 45%'=>$skillScore,'Lokasi 20%'=>$locScore,'Kategori 20%'=>$catScore,'Deskripsi 15%'=>$descScore]];
}
foreach($jobs as &$job){$m=match_score($job,$user,$skills);$job['match']=$m;}unset($job);
usort($jobs,fn($a,$b)=>$b['match']['score']<=>$a['match']['score']);
$pageTitle='Rekomendasi Pekerjaan';require __DIR__.'/../includes/header.php';
?>
<main class="dashboard-main">
    <p class="eyebrow">Pekerja / talenta</p>
    <h1 class="dashboard-title">Smart Job Matching</h1>
    <p class="text-sm text-slate-500 mt-3">Skor transparan dari skill (45%), lokasi (20%), kategori (20%), dan kata
        kunci deskripsi (15%). Lowongan aktif dinilai menggunakan data profilmu.</p>
    <section class="dashboard-panel mt-5">
        <h2>Peluang paling relevan</h2><?php if($jobs):?><div class="space-y-4 mt-4"><?php foreach($jobs as $x):?>
            <article class="surface-card p-5">
                <div class="flex flex-wrap justify-between gap-3">
                    <div>
                        <p class="text-xs text-slate-500"><?=e($x['penyedia'])?> · <?=e($x['lokasi'])?></p>
                        <h3 class="font-bold text-ink mt-1"><?=e($x['judul'])?></h3>
                        <p class="text-xs text-slate-500 mt-1"><?=e($x['kategori'])?> · <?=e($x['jenis_pekerjaan'])?>
                        </p>
                    </div>
                    <div class="text-right"><b class="text-2xl text-teal"><?=($x['match']['score'])?>%</b>
                        <p class="text-xs text-slate-500">kecocokan</p>
                    </div>
                </div>
                <div class="w-full h-2 rounded-full bg-slate-100 mt-4">
                    <div class="h-2 rounded-full bg-teal" style="width:<?=$x['match']['score']?>%"></div>
                </div>
                <p class="font-semibold text-sm mt-4">Mengapa direkomendasikan?</p>
                <ul class="list-disc pl-5 text-sm mt-2 space-y-1"><?php foreach($x['match']['reasons'] as $reason):?>
                    <li><?=e($reason)?></li><?php endforeach?>
                </ul>
                <details class="text-xs text-slate-500 mt-3">
                    <summary class="cursor-pointer">Rincian perhitungan skor</summary>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                        <?php foreach($x['match']['parts'] as $label=>$value):?><div class="bg-slate-50 rounded-lg p-2">
                            <?=e($label)?>: <b><?=round($value)?>/100</b></div><?php endforeach?></div>
                </details>
                <div class="border-t mt-4 pt-4 flex flex-wrap justify-between gap-3"><b
                        class="text-sm text-ink"><?=rupiah($x['gaji_min'])?>–<?=rupiah($x['gaji_max'])?></b>
                    <div class="flex gap-4"><a class="text-link"
                            href="<?=BASE_URL?>/detail-lowongan.php?id=<?=(int)$x['id']?>">Detail</a>
                        <form method="post" action="<?=BASE_URL?>/pekerja/lamar.php"><input type="hidden" name="_csrf"
                                value="<?=e(csrf_token())?>"><input type="hidden" name="lowongan_id"
                                value="<?=(int)$x['id']?>"><button class="text-link" type="submit">Lamar →</button>
                        </form>
                    </div>
                </div>
            </article><?php endforeach?>
        </div><?php else:?><div class="empty-state mt-4">Belum ada lowongan aktif yang
            sesuai untuk ditampilkan.</div><?php endif?>
    </section>
</main><?php require __DIR__.'/../includes/footer.php';?>