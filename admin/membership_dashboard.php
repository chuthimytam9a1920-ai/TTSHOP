<?php
require_once __DIR__.'/check_admin.php';
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__.'/../connect.php';
function md_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function md_money($v): string { return number_format((int)$v,0,',','.').'đ'; }
function md_get(string $k,string $default=''): string { return isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : $default; }
function md_date(string $v): bool { if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$v)) return false; $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v); return $d && $d->format('Y-m-d')===$v; }
function md_query(mysqli $db,string $sql,string $types='',array $p=[]): array { $s=$db->prepare($sql); try { if($types!=='') $s->bind_param($types,...$p); $s->execute(); return $s->get_result()->fetch_all(MYSQLI_ASSOC); } finally { $s->close(); } }
function md_csv($v): string { $v=(string)$v; return preg_match('/^[\s\x{FEFF}]*[=+\-@]|^[\t\r\n]/u',$v) ? "'".$v : $v; }
$tiers=['Member','Silver','Gold','Platinum','Diamond'];
$colors=['#5d8be2','#dda638','#8966d5','#168653'];
$statuses=['PAID'=>'Đã thanh toán','PENDING'=>'Chờ thanh toán','REVIEW'=>'Cần kiểm tra','CANCELLED'=>'Đã hủy','EXPIRED'=>'Hết hạn','ALL'=>'Tất cả trạng thái'];
$today=new DateTimeImmutable('today'); $defaultFrom=$today->format('Y-m-01'); $defaultTo=$today->format('Y-m-d');
$from=md_get('from',$defaultFrom); $to=md_get('to',$defaultTo); $status=md_get('status','PAID'); $q=md_get('q'); $rank=md_get('rank','all');
$error='';
if(!md_date($from)||!md_date($to)||$from>$to||$to>$defaultTo||$from<'2000-01-01'||($a=new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days>365){ $error='Khoảng ngày không hợp lệ (tối đa 366 ngày, không vượt hôm nay). Đã dùng tháng này.'; $from=$defaultFrom; $to=$defaultTo; }
if(!isset($statuses[$status])) $status='PAID';
if(!in_array($rank,['all','1','2','3','4'],true)) $rank='all';
if(strlen($q)>400) $q='';
$start=$from.' 00:00:00'; $end=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d').' 00:00:00';
$filters=['from'=>$from,'to'=>$to,'status'=>$status,'q'=>$q,'rank'=>$rank];
function md_url(array $changes=[]): string { global $filters; return 'membership_dashboard.php?'.http_build_query(array_merge($filters,$changes)); }
$base=" FROM membership_upgrades m JOIN users u ON u.id=m.user_id ";
$extra='';$xt='';$xp=[];
if($q!==''){ $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%'; $extra.=" AND (CONCAT_WS(' ',u.ho,u.ten) LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!' OR CAST(m.order_code AS CHAR) LIKE ? ESCAPE '!' OR u.sdt LIKE ? ESCAPE '!')"; $xt.='ssss'; $xp=[$like,$like,$like,$like]; }
if($rank!=='all'){ $extra.=' AND m.target_rank=?'; $xt.='i'; $xp[]=(int)$rank; }
$paidWhere=" WHERE m.status='PAID' AND m.paid_at>=? AND m.paid_at<?".$extra;
$rangeP=array_merge([$start,$end],$xp); $rangeT='ss'.$xt;
$dateCol=$status==='PAID'?'m.paid_at':($status==='ALL'?"CASE WHEN m.status='PAID' THEN m.paid_at ELSE m.created_at END":'m.created_at');
$listWhere=" WHERE $dateCol>=? AND $dateCol<?".$extra; $listP=$rangeP;$listT=$rangeT;
if($status!=='ALL'){ $listWhere.=' AND m.status=?'; $listP[]=$status; $listT.='s'; }
$select="SELECT m.id,m.user_id,m.from_rank,m.target_rank,m.amount,m.amount_received,m.order_code,m.status,m.created_at,m.paid_at,CONCAT_WS(' ',u.ho,u.ten) AS customer,u.email,u.sdt";
$dbError='';$rows=[];$daily=[];$dist=[];$total=0;$pages=1;$pageNum=1;$stats=['revenue'=>0,'packages'=>0,'customers'=>0];$created=0;$pending=0;$review=0;
try {
    if(md_get('export')==='csv'){
        if($error!==''){ http_response_code(400); exit('Khoảng ngày không hợp lệ.'); }
        $rows=md_query($conn,$select.$base.$listWhere." ORDER BY $dateCol DESC,m.id DESC LIMIT 10001",$listT,$listP);
        if(count($rows)>10000){ http_response_code(400); exit('Vui lòng thu hẹp bộ lọc để xuất tối đa 10.000 dòng.'); }
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="TTShop-nang-hang-'.$from.'-'.$to.'.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['ID khách','Khách hàng','Email','SĐT','Mã giao dịch','Hạng cũ','Hạng mua','Giá (VND)','Đã nhận (VND)','Trạng thái','Ngày tạo','Ngày thanh toán'],',','"','');
        foreach($rows as $r){ fputcsv($out,array_map('md_csv',[$r['user_id'],$r['customer'],$r['email'],$r['sdt'],$r['order_code'],$tiers[$r['from_rank']]??'?', $tiers[$r['target_rank']]??'?', $r['amount'],$r['amount_received'],$statuses[$r['status']]??$r['status'],$r['created_at'],$r['paid_at']]),',','"',''); }
        fclose($out);exit;
    }
    $stats=md_query($conn,"SELECT COALESCE(SUM(m.amount),0) revenue,COUNT(*) packages,COUNT(DISTINCT m.user_id) customers".$base.$paidWhere,$rangeT,$rangeP)[0];
    $created=(int)md_query($conn,'SELECT COUNT(*) n'.$base.' WHERE m.created_at>=? AND m.created_at<?'.$extra,$rangeT,$rangeP)[0]['n'];
    $pending=(int)md_query($conn,"SELECT COUNT(*) n FROM membership_upgrades WHERE status='PENDING'")[0]['n'];
    $review=(int)md_query($conn,"SELECT COUNT(*) n FROM membership_upgrades WHERE status='REVIEW'")[0]['n'];
    $daily=md_query($conn,'SELECT DATE(m.paid_at) day, SUM(m.amount) revenue'.$base.$paidWhere.' GROUP BY DATE(m.paid_at) ORDER BY day',$rangeT,$rangeP);
    $dist=md_query($conn,'SELECT m.target_rank,COUNT(*) packages,SUM(m.amount) revenue'.$base.$paidWhere.' GROUP BY m.target_rank',$rangeT,$rangeP);
    $total=(int)md_query($conn,'SELECT COUNT(*) n'.$base.$listWhere,$listT,$listP)[0]['n'];
    $pages=max(1,(int)ceil($total/20));$pageNum=min($pages,max(1,(int)md_get('p','1')));$offset=($pageNum-1)*20;
    $rows=md_query($conn,$select.$base.$listWhere." ORDER BY $dateCol DESC,m.id DESC LIMIT ? OFFSET ?",$listT.'ii',array_merge($listP,[20,$offset]));
} catch(Throwable $ex){ error_log('Membership dashboard: '.$ex->getMessage());$dbError='Không đọc được dữ liệu nâng hạng. Kiểm tra bảng membership_upgrades và kết nối database.';if(md_get('export')==='csv'){http_response_code(503);exit($dbError);} }
$series=[]; foreach($daily as $r) $series[$r['day']]=(int)$r['revenue'];
$days=[];for($d=new DateTimeImmutable($from);$d->format('Y-m-d')<=$to;$d=$d->modify('+1 day')){ $key=$d->format('Y-m-d');$days[]=['day'=>$key,'value'=>$series[$key]??0]; }
$max=max(10000,(int)ceil(max(array_column($days,'value'))/10000)*10000);$n=count($days);$points=[];
foreach($days as $i=>$d){$x=93+($n===1?292:$i*584/($n-1));$y=202-($d['value']/$max)*165;$points[]=round($x,2).','.round($y,2);}
$breakdown=[];foreach($dist as $r)$breakdown[(int)$r['target_rank']]=$r;
$segments=[];$pct=0;$rev=(int)$stats['revenue'];foreach($colors as $i=>$color){$v=(int)($breakdown[$i+1]['revenue']??0);$next=$pct+($rev>0?$v/$rev*100:0);$segments[]=$color.' '.$pct.'% '.$next.'%';$pct=$next;}
?><!DOCTYPE html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard nâng hạng | TT SHOP</title><link rel="stylesheet" href="admin.css"><link rel="stylesheet" href="membership_dashboard.css"></head><body><div class="admin-layout">
<?php $page='membership_dashboard';include __DIR__.'/sidebar.php'; ?>
<main class="main md-main">
<header class="topbar"><div><p class="eyebrow">QUẢN LÝ THÀNH VIÊN</p><h1>Dashboard nâng hạng</h1><p class="topbar-subtitle">Theo dõi khách hàng mua gói thành viên và doanh thu đã xác nhận qua PayOS.</p></div><div class="md-clock">Hôm nay: <strong><?= $today->format('d/m/Y') ?></strong><br>Thời điểm tải: <?= date('H:i:s') ?></div></header>
<?php if($error):?><div class="box md-warning"><?=md_e($error)?></div><?php endif;?>
<?php if($dbError):?><div class="box md-warning" role="alert"><?=md_e($dbError)?> <a class="detail" href="membership_dashboard.php">Thử lại</a></div><?php else:?>
<section class="box md-panel"><div class="md-quick"><?php foreach(['Hôm nay'=>$defaultTo,'7 ngày'=>$today->modify('-6 days')->format('Y-m-d'),'30 ngày'=>$today->modify('-29 days')->format('Y-m-d'),'Tháng này'=>$defaultFrom] as $label=>$f):?><a class="md-small <?= $from===$f&&$to===$defaultTo?'md-active':'' ?>" href="<?=md_e(md_url(['from'=>$f,'to'=>$defaultTo]))?>"><?=md_e($label)?></a><?php endforeach;?></div>
<form method="get" class="md-filters"><label>Từ ngày<input type="date" name="from" value="<?=md_e($from)?>" required max="<?=$defaultTo?>"></label><label>Đến ngày<input type="date" name="to" value="<?=md_e($to)?>" required max="<?=$defaultTo?>"></label><label>Trạng thái danh sách<select name="status"><?php foreach($statuses as $k=>$v):?><option value="<?=$k?>" <?=$status===$k?'selected':''?>><?=md_e($v)?></option><?php endforeach;?></select></label><label>Hạng đã mua<select name="rank"><option value="all">Tất cả hạng</option><?php for($i=1;$i<=4;$i++):?><option value="<?=$i?>" <?=$rank===(string)$i?'selected':''?>><?=$tiers[$i]?></option><?php endfor;?></select></label><label class="md-search">Tìm khách / mã giao dịch<input name="q" value="<?=md_e($q)?>" maxlength="100" placeholder="Tên, email, SĐT, mã giao dịch"></label><button type="submit">Lọc dữ liệu</button><a class="md-small" href="membership_dashboard.php">Đặt lại</a></form><p class="md-note">Kỳ xem: <?=date('d/m/Y',strtotime($from))?> – <?=date('d/m/Y',strtotime($to))?>. KPI và biểu đồ luôn tính giao dịch PAID theo ngày xác nhận, không phụ thuộc bộ lọc trạng thái danh sách.</p></section>
<section class="box md-panel md-export"><div><strong>Xác nhận thanh toán tự động</strong><p class="md-note">Chỉ ghi nhận giao dịch PAID do hệ thống xác minh. Toàn hệ thống: <?=number_format($pending)?> chờ thanh toán · <?=number_format($review)?> cần kiểm tra.</p></div><a class="btn md-outline" href="<?=md_e(md_url(['export'=>'csv']))?>">Xuất danh sách ra CSV Excel</a></section>
<section class="md-kpis" aria-label="Chỉ số nâng hạng">
<?php foreach([['Doanh thu xác nhận trong kỳ',md_money($stats['revenue']),'Chỉ tính giao dịch PAID','#168653'],['Gói đã kích hoạt trong kỳ',number_format((int)$stats['packages']),'Theo ngày xác nhận thanh toán','#5d8be2'],['Khách đã nâng hạng',number_format((int)$stats['customers']),'Số khách khác nhau trong kỳ','#dda638'],['Giao dịch tạo trong kỳ',number_format($created),'Tất cả trạng thái, theo ngày tạo','#8966d5']] as $k):?><article class="box md-kpi" style="--accent:<?=$k[3]?>"><span><?=md_e($k[0])?></span><strong><?=md_e($k[1])?></strong><small><?=md_e($k[2])?></small></article><?php endforeach;?></section>
<section class="md-charts"><article class="box md-chart"><h2>Doanh thu theo thời gian</h2><p class="md-note">Doanh thu nâng hạng theo ngày xác nhận thanh toán.</p>
<svg viewBox="0 0 720 245" role="img" aria-label="Biểu đồ doanh thu theo ngày" class="md-line">
<?php for($i=0;$i<=4;$i++):$y=202-$i*165/4;?><line x1="93" y1="<?=$y?>" x2="677" y2="<?=$y?>" stroke="#e5ebe7"/><text x="85" y="<?=$y+4?>" text-anchor="end" font-size="10" fill="#728078"><?=md_money(round($max*$i/4))?></text><?php endfor;?>
<polygon points="<?=md_e(explode(',',$points[0])[0])?>,202 <?=md_e(implode(' ',$points))?> <?=md_e(explode(',',$points[$n-1])[0])?>,202" fill="#e0f2e8"/><polyline points="<?=md_e(implode(' ',$points))?>" fill="none" stroke="#168653" stroke-width="2.5" stroke-linejoin="round"/>
<?php foreach($days as $i=>$d):$xy=explode(',',$points[$i]);if($n<=40||$d['value']>0):?><circle cx="<?=$xy[0]?>" cy="<?=$xy[1]?>" r="<?=$n>40?2:3.5?>" fill="#fff" stroke="#168653" stroke-width="1.4"><title><?=md_e($d['day'].' : '.md_money($d['value']))?></title></circle><?php endif;if($i===0||$i===$n-1||($i%max(1,(int)ceil($n/6))===0&&$i<$n-2)):?><text x="<?=$xy[0]?>" y="222" text-anchor="middle" font-size="10" fill="#728078"><?=date('d/m',strtotime($d['day']))?></text><?php endif;endforeach;?>
</svg><?php if(!$rev):?><p class="md-note">Chưa có doanh thu trong kỳ.</p><?php endif;?></article>
<article class="box md-chart"><h2>Cơ cấu doanh thu theo hạng</h2><p class="md-note">Theo hạng đích của từng giao dịch đã thanh toán.</p><div class="md-donut-layout"><div class="md-donut" role="img" aria-label="<?=md_e('Tổng doanh thu '.md_money($rev))?>" style="background:<?=$rev?'conic-gradient('.implode(',',$segments).')':'#e9eeeb'?>"><div><small>Tổng doanh thu</small><strong><?=md_money($rev)?></strong></div></div><div class="md-legend"><?php foreach($colors as $i=>$color):$r=$breakdown[$i+1]??[];$v=(int)($r['revenue']??0);?><div><span class="md-dot" style="background:<?=$color?>"></span><span><strong><?=$tiers[$i+1]?></strong><small><?=number_format((int)($r['packages']??0))?> gói · <?=md_money($v)?></small></span><b><?=$rev?number_format($v/$rev*100,1,',','.'):0?>%</b></div><?php endforeach;?></div></div></article></section>
<section class="box md-panel"><div class="md-table-title"><div><h2>Danh sách giao dịch nâng hạng</h2><p class="md-note"><?=number_format($total)?> giao dịch phù hợp · <?= $status==='PAID'?'Theo ngày xác nhận':($status==='ALL'?'Theo ngày xác nhận nếu PAID, ngày tạo nếu chưa PAID':'Theo ngày tạo') ?>. Một khách có thể mua nhiều gói.</p></div><a class="md-small" href="<?=md_e(md_url(['status'=>'PAID']))?>">Khách đã thanh toán</a></div><div class="table-wrap"><table><thead><tr><th>Khách hàng</th><th>Mã giao dịch</th><th>Nâng hạng</th><th>Giá gói / đã nhận</th><th>Trạng thái</th><th>Ngày tạo</th><th>Ngày xác nhận</th></tr></thead><tbody>
<?php if(!$rows):?><tr><td colspan="7" class="md-empty">Không có giao dịch phù hợp bộ lọc.</td></tr><?php endif;foreach($rows as $r):?><tr><td><strong><?=md_e(trim($r['customer'])?:'Khách #'.$r['user_id'])?></strong><small class="md-cell-sub"><?=md_e($r['email'])?></small><small class="md-cell-sub"><?=md_e($r['sdt'])?> · ID <?= (int)$r['user_id'] ?></small></td><td><?=md_e($r['order_code'])?></td><td><?=md_e($tiers[$r['from_rank']]??'?')?> → <strong><?=md_e($tiers[$r['target_rank']]??'?')?></strong></td><td><strong><?=md_money($r['amount'])?></strong><small class="md-cell-sub">Đã nhận: <?=md_money($r['amount_received'])?></small></td><td><span class="md-state md-<?=md_e(strtolower($r['status']))?>"><?=md_e($statuses[$r['status']]??$r['status'])?></span></td><td><?=md_e($r['created_at'])?></td><td><?=md_e($r['paid_at']??'—')?></td></tr><?php endforeach;?></tbody></table></div><div class="md-pagination"><span>Trang <?=$pageNum?> / <?=$pages?></span><?php if($pageNum>1):?><a class="md-small" href="<?=md_e(md_url(['p'=>$pageNum-1]))?>">← Trước</a><?php endif;if($pageNum<$pages):?><a class="md-small" href="<?=md_e(md_url(['p'=>$pageNum+1]))?>">Sau →</a><?php endif;?></div></section>
<?php endif;?></main></div></body></html>
