<?php
require __DIR__.'/check_admin.php';
require __DIR__.'/../connect.php';
require __DIR__.'/../phananh_helpers.php';
header('Cache-Control: private, no-store');
$page='feedback'; $error=''; $flash=''; $items=[]; $detail=null; $replies=[]; $total=0; $counts=array_fill_keys(array_keys(pa_statuses()),0);
$id=pa_id($_GET['id']??null); $p=max(1,min(100000,pa_id($_GET['p']??null)?:1));
$filter=pa_text($_GET['status']??''); if(!isset(pa_statuses()[$filter])) $filter='';
$q=pa_text($_GET['q']??''); if(!preg_match('//u',$q)||pa_len($q)>150) $q='';
$reply=pa_text($_POST['noi_dung']??'');
try {
    $aid=pa_admin($conn);
    if (!$aid) { http_response_code(403); exit('Phiên Admin không hợp lệ. Vui lòng đăng nhập lại.'); }
    $flash=pa_get_flash('pa_admin_flash',$aid);
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        try {
            if (!csrf_check($_POST['csrf_token']??null)) throw new InvalidArgumentException('Phiên gửi không hợp lệ. Vui lòng tải lại trang.');
            $id=pa_id($_POST['id']??null); $status=pa_text($_POST['trang_thai']??'');
            if (!$id || !isset(pa_statuses()[$status]) || !preg_match('//u',$reply) || pa_len($reply)>2000 || ($reply!=='' && pa_len($reply)<5)) throw new InvalidArgumentException('Kiểm tra trạng thái và nội dung phản hồi (5–2.000 ký tự nếu có).');
            $conn->begin_transaction();
            try {
                if (!pa_one($conn,'SELECT id FROM phan_anh WHERE id=? FOR UPDATE','i',[$id])) throw new InvalidArgumentException('Phản ánh không tồn tại.');
                if ($status==='resolved' && $reply==='' && !pa_one($conn,"SELECT id FROM phan_anh_tra_loi WHERE phan_anh_id=? AND nguoi_gui='admin' LIMIT 1",'i',[$id])) throw new InvalidArgumentException('Hãy gửi ít nhất một phản hồi cho khách trước khi đánh dấu đã xử lý.');
                if ($reply!=='') pa_exec($conn,"INSERT INTO phan_anh_tra_loi(phan_anh_id,nguoi_gui,admin_id,noi_dung) VALUES (?,'admin',?,?)",'iis',[$id,$aid,$reply]);
                pa_exec($conn,'UPDATE phan_anh SET trang_thai=?,updated_at=NOW() WHERE id=?','si',[$status,$id]);
                $conn->commit();
            } catch(Throwable $e) { $conn->rollback(); throw $e; }
            pa_flash('pa_admin_flash',$aid,$reply!==''?'Đã lưu phản hồi và trạng thái. Khách hàng có thể xem ngay trên website.':'Đã cập nhật trạng thái phản ánh.');
            header('Location: feedback.php?id='.$id); exit;
        } catch(InvalidArgumentException $e) { $error=$e->getMessage(); }
    }
    foreach(pa_rows($conn,'SELECT trang_thai,COUNT(*) n FROM phan_anh GROUP BY trang_thai') as $r) $counts[$r['trang_thai']]=(int)$r['n'];
    $where=' WHERE 1=1'; $types=''; $args=[];
    if($filter!=='') { $where.=' AND f.trang_thai=?'; $types.='s'; $args[]=$filter; }
    if($q!=='') { $where.=" AND (f.tieu_de LIKE ? OR f.ma_don LIKE ? OR CONCAT_WS(' ',u.ho,u.ten) LIKE ? OR u.email LIKE ? OR CONCAT('PA-',LPAD(f.id,GREATEST(5,CHAR_LENGTH(f.id)),'0')) LIKE ? OR CAST(f.id AS CHAR)=?)"; $like='%'.$q.'%'; $types.='ssssss'; array_push($args,$like,$like,$like,$like,$like,$q); }
    $from=' FROM phan_anh f JOIN users u ON u.id=f.user_id';
    $total=(int)pa_one($conn,'SELECT COUNT(*) n'.$from.$where,$types,$args)['n']; $p=min($p,max(1,(int)ceil($total/15)));
    $items=pa_rows($conn,"SELECT f.id,f.tieu_de,f.loai,f.ma_don,f.trang_thai,f.updated_at,CONCAT_WS(' ',u.ho,u.ten) AS customer,u.email".$from.$where." ORDER BY f.updated_at DESC,f.id DESC LIMIT 15 OFFSET ?",$types.'i',array_merge($args,[($p-1)*15]));
    if($id) {
        $detail=pa_one($conn,"SELECT f.id,f.tieu_de,f.loai,f.ma_don,f.noi_dung,f.trang_thai,f.created_at,f.updated_at,f.anh IS NOT NULL AS has_image,CONCAT_WS(' ',u.ho,u.ten) AS customer,u.email,u.sdt,(SELECT d.id FROM donhang d WHERE CONVERT(d.ma_don USING utf8mb4) COLLATE utf8mb4_unicode_ci=f.ma_don AND d.user_id=f.user_id LIMIT 1) AS order_id".$from.' WHERE f.id=?','i',[$id]);
        if(!$detail) { http_response_code(404); $error='Không tìm thấy phản ánh.'; }
        else $replies=pa_rows($conn,'SELECT nguoi_gui,noi_dung,created_at FROM phan_anh_tra_loi WHERE phan_anh_id=? ORDER BY id','i',[$id]);
    }
} catch(Throwable $e) { $error=pa_error($e); }
function pa_admin_url(array $extra=[]): string { global $filter,$q,$p; return 'feedback.php?'.http_build_query(array_merge(['status'=>$filter,'q'=>$q,'p'=>$p],$extra)); }
?>
<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quản lý phản ánh - TT SHOP</title><link rel="stylesheet" href="admin.css"><link rel="stylesheet" href="../phananh.css?v=1"></head><body><div class="admin-layout"><?php require __DIR__.'/sidebar.php'; ?>
<main class="main pa-ui pa-admin"><div class="topbar"><div><p class="eyebrow">CHĂM SÓC KHÁCH HÀNG</p><h1>Quản lý phản ánh & góp ý</h1><p class="topbar-subtitle">Tiếp nhận, theo dõi và phản hồi khách hàng trực tiếp.</p></div><a class="pa-btn pa-secondary" href="feedback.php">Làm mới</a></div>
<?php if($error): ?><div class="pa-alert pa-error" role="alert"><?=pa_h($error)?></div><?php endif; ?><?php if($flash): ?><div class="pa-alert pa-ok" role="status"><?=pa_h($flash)?></div><?php endif; ?>
<div class="pa-metrics"><?php foreach(pa_statuses() as $key=>$label): ?><a class="pa-panel pa-metric" href="feedback.php?status=<?=pa_h($key)?>"><?=pa_status_html($key)?><strong><?=$counts[$key]?></strong><small>Phản ánh</small></a><?php endforeach; ?></div>
<?php if($detail): ?><section class="pa-panel pa-admin-detail"><a class="pa-back" href="<?=pa_h(pa_admin_url())?>">‹ Danh sách phản ánh</a><div class="pa-detail-head"><div><small><?=pa_h(pa_code($detail['id']))?> · <?=pa_h(pa_date($detail['created_at']))?></small><h2><?=pa_h($detail['tieu_de'])?></h2></div><?=pa_status_html($detail['trang_thai'])?></div>
<div class="pa-meta"><div><small>Khách hàng</small><strong><?=pa_h($detail['customer'])?></strong><span><?=pa_h($detail['email'])?> · <?=pa_h($detail['sdt'])?></span></div><div><small>Loại phản ánh</small><strong><?=pa_h($detail['loai'])?></strong><span>Đơn hàng: <?php if($detail['order_id']): ?><a href="order_detail.php?id=<?=(int)$detail['order_id']?>"><?=pa_h($detail['ma_don'])?></a><?php else: ?><?=pa_h($detail['ma_don']??'Không liên kết')?><?php endif; ?></span></div></div>
<div class="pa-message from-customer"><strong>Nội dung ban đầu</strong><div class="pa-text"><?=pa_h($detail['noi_dung'])?></div><?php if($detail['has_image']): ?><a href="../phananh_anh.php?id=<?=$id?>" target="_blank" rel="noopener"><img class="pa-image" src="../phananh_anh.php?id=<?=$id?>" alt="Ảnh phản ánh của khách"></a><?php endif; ?></div>
<h3>Lịch sử trao đổi</h3><?php if(!$replies): ?><p class="pa-muted">Chưa có phản hồi.</p><?php endif; ?><?php pa_history($replies); ?>
<form method="post" class="pa-form" action="<?=pa_h(pa_admin_url(['id'=>$id]))?>"><input type="hidden" name="csrf_token" value="<?=pa_h(csrf_token())?>"><input type="hidden" name="id" value="<?=$id?>"><label for="status">Trạng thái xử lý</label><select id="status" name="trang_thai"><?php foreach(pa_statuses() as $key=>$label): ?><option value="<?=pa_h($key)?>" <?=($error?pa_text($_POST['trang_thai']??$detail['trang_thai']):$detail['trang_thai'])===$key?'selected':''?>><?=pa_h($label)?></option><?php endforeach; ?></select><label for="answer">Nội dung phản hồi (nếu có)</label><textarea id="answer" name="noi_dung" rows="5" maxlength="2000" placeholder="Nhập câu trả lời cho khách hàng..."><?=pa_h($error?$reply:'')?></textarea><p class="pa-muted">Phản hồi được lưu thành từng tin nhắn. Khách hàng xem trong Tài khoản → Phản ánh & góp ý.</p><button type="submit" class="pa-btn">Lưu trạng thái & phản hồi</button></form></section><?php endif; ?>
<section class="pa-panel"><h2>Danh sách phản ánh <small class="pa-muted">(<?=$total?>)</small></h2><form class="pa-filters" method="get" action="feedback.php"><input type="search" name="q" value="<?=pa_h($q)?>" maxlength="150" placeholder="Mã phản ánh, đơn hàng, tên, email..." aria-label="Tìm kiếm"><select name="status" aria-label="Trạng thái"><option value="">Tất cả trạng thái</option><?php foreach(pa_statuses() as $key=>$label): ?><option value="<?=pa_h($key)?>" <?=$filter===$key?'selected':''?>><?=pa_h($label)?></option><?php endforeach; ?></select><button class="pa-btn">Tìm kiếm</button><a class="pa-btn pa-secondary" href="feedback.php">Xóa lọc</a></form>
<div class="pa-table-scroll"><table class="pa-table"><thead><tr><th>Mã / Tiêu đề</th><th>Khách hàng</th><th>Đơn hàng</th><th>Trạng thái</th><th>Cập nhật</th><th>Thao tác</th></tr></thead><tbody><?php if(!$items): ?><tr><td colspan="6" class="pa-muted">Không có phản ánh phù hợp.</td></tr><?php endif; ?><?php foreach($items as $r): ?><tr><td><strong><?=pa_h(pa_code($r['id']))?></strong><br><?=pa_h($r['tieu_de'])?><br><small><?=pa_h($r['loai'])?></small></td><td><?=pa_h($r['customer'])?><br><small><?=pa_h($r['email'])?></small></td><td><?=pa_h($r['ma_don']??'—')?></td><td><?=pa_status_html($r['trang_thai'])?></td><td><?=pa_h(pa_date($r['updated_at']))?></td><td><a class="pa-link" href="<?=pa_h(pa_admin_url(['id'=>(int)$r['id']]))?>">Xem & xử lý</a></td></tr><?php endforeach; ?></tbody></table></div><div class="pa-pages"><?php if($p>1): ?><a href="<?=pa_h(pa_admin_url(['p'=>$p-1]))?>">‹ Trước</a><?php endif; ?><span>Trang <?=$p?> / <?=max(1,(int)ceil($total/15))?></span><?php if($p*15<$total): ?><a href="<?=pa_h(pa_admin_url(['p'=>$p+1]))?>">Sau ›</a><?php endif; ?></div></section>
</main></div></body></html>
