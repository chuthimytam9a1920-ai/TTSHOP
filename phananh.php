<?php
session_start();
require __DIR__.'/connect.php';
require __DIR__.'/phananh_helpers.php';
header('Cache-Control: private, no-store');
$user=null; $error=''; $flash=''; $orders=[]; $items=[]; $detail=null; $replies=[]; $total=0;
$id=pa_id($_GET['id']??null); $p=max(1,min(100000,pa_id($_GET['p']??null)?:1));
$kind=pa_text($_POST['loai']??''); $subject=pa_text($_POST['tieu_de']??''); $body=pa_text($_POST['noi_dung']??''); $order=pa_text($_POST['ma_don']??'');
try {
    $user=pa_customer($conn);
    if ($user) {
        $uid=(int)$user['id'];
        $flash=pa_get_flash('pa_customer_flash',$uid);
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            try {
                if (!csrf_check($_POST['csrf_token']??null)) throw new InvalidArgumentException('Phiên gửi không hợp lệ. Hãy tải lại trang và thử lại.');
                if (!preg_match('//u',$body) || pa_len($body)<5 || pa_len($body)>2000) throw new InvalidArgumentException('Nội dung phải từ 5 đến 2.000 ký tự.');
                $action=pa_text($_POST['action']??'');
                if ($action==='create') {
                    if (!in_array($kind,pa_types(),true) || !preg_match('//u',$subject) || pa_len($subject)<3 || pa_len($subject)>150) throw new InvalidArgumentException('Chọn loại phản ánh và nhập tiêu đề từ 3 đến 150 ký tự.');
                    if ($order!=='' && !pa_one($conn,'SELECT id FROM donhang WHERE user_id=? AND ma_don=?','is',[$uid,$order])) throw new InvalidArgumentException('Mã đơn hàng không thuộc tài khoản của bạn.');
                    [$mime,$bytes]=pa_upload();
                     $insert=pa_stmt($conn,'INSERT INTO phan_anh(user_id,ma_don,loai,tieu_de,noi_dung,anh_mime,anh) VALUES (?,?,?,?,?,?,?)','issssss',[$uid,$order===''?null:$order,$kind,$subject,$body,$mime,$bytes]);
                    $newId=(int)$insert->insert_id; $insert->close();
                    pa_flash('pa_customer_flash',$uid,'Đã gửi phản ánh '.pa_code($newId).'. Bạn có thể theo dõi phản hồi tại đây.');
                    header('Location: phananh.php?id='.$newId); exit;
                }
                if ($action==='reply') {
                    $id=pa_id($_POST['id']??null);
                    $conn->begin_transaction();
                    try {
                        if (!pa_one($conn,'SELECT id FROM phan_anh WHERE id=? AND user_id=? FOR UPDATE','ii',[$id,$uid])) throw new InvalidArgumentException('Không tìm thấy phản ánh của bạn.');
                        pa_exec($conn,"INSERT INTO phan_anh_tra_loi(phan_anh_id,nguoi_gui,noi_dung) VALUES (?,'customer',?)",'is',[$id,$body]);
                        pa_exec($conn,"UPDATE phan_anh SET trang_thai='pending',updated_at=NOW() WHERE id=? AND user_id=?",'ii',[$id,$uid]);
                        $conn->commit();
                    } catch (Throwable $e) { $conn->rollback(); throw $e; }
                    pa_flash('pa_customer_flash',$uid,'Đã gửi nội dung bổ sung. Phản ánh được chuyển về chờ xử lý.');
                    header('Location: phananh.php?id='.$id); exit;
                }
                throw new InvalidArgumentException('Yêu cầu không hợp lệ.');
            } catch (InvalidArgumentException $e) { $error=$e->getMessage(); }
        }
        $orders=pa_rows($conn,'SELECT ma_don,created_at FROM donhang WHERE user_id=? ORDER BY id DESC','i',[$uid]);
        $total=(int)pa_one($conn,'SELECT COUNT(*) AS n FROM phan_anh WHERE user_id=?','i',[$uid])['n'];
        $p=min($p,max(1,(int)ceil($total/10)));
        $items=pa_rows($conn,'SELECT id,tieu_de,loai,trang_thai,updated_at FROM phan_anh WHERE user_id=? ORDER BY updated_at DESC,id DESC LIMIT 10 OFFSET ?','ii',[$uid,($p-1)*10]);
        if ($id) {
            $detail=pa_one($conn,'SELECT id,ma_don,loai,tieu_de,noi_dung,trang_thai,created_at,updated_at,anh IS NOT NULL AS has_image FROM phan_anh WHERE id=? AND user_id=?','ii',[$id,$uid]);
            if (!$detail) { http_response_code(404); $error='Không tìm thấy phản ánh thuộc tài khoản của bạn.'; }
            else $replies=pa_rows($conn,'SELECT nguoi_gui,noi_dung,created_at FROM phan_anh_tra_loi WHERE phan_anh_id=? ORDER BY id','i',[$id]);
        }
    } elseif ($_SERVER['REQUEST_METHOD']==='POST') { http_response_code(403); $error='Vui lòng đăng nhập lại trước khi gửi.'; }
} catch (Throwable $e) { $error=pa_error($e); }
?>
<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Phản ánh & góp ý - TT SHOP</title><link rel="stylesheet" href="style.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><link rel="stylesheet" href="phananh.css?v=1"></head>
<body class="pa-shop"><?php require __DIR__.'/header.php'; ?>
<main class="pa-wrap pa-ui">
<div class="pa-crumb"><a href="index.php">Trang chủ</a> / Trung tâm hỗ trợ / Phản ánh & góp ý</div>
<section class="pa-hero"><div class="pa-kicker">TT SHOP · TRUNG TÂM HỖ TRỢ</div><h1>Phản ánh & góp ý khách hàng</h1><p>Chia sẻ vấn đề bạn gặp phải và theo dõi câu trả lời từ cửa hàng ngay trên website.</p></section>
<?php if ($error): ?><div role="alert" class="pa-alert pa-error"><?=pa_h($error)?></div><?php endif; ?>
<?php if ($flash): ?><div role="status" class="pa-alert pa-ok"><?=pa_h($flash)?></div><?php endif; ?>
<?php if (!$user): ?><section class="pa-panel"><h2>Đăng nhập để gửi phản ánh</h2><p>Chúng tôi sử dụng tài khoản TT SHOP để bảo vệ nội dung phản ánh và giúp bạn xem lại phản hồi của Admin.</p><a class="pa-btn" href="dangnhap.php">Đăng nhập</a> <a class="pa-btn pa-secondary" href="dangky.php">Đăng ký</a></section>
<?php else: ?>
<div class="pa-grid">
<aside class="pa-panel pa-list"><h2>Phản ánh của tôi</h2><a class="pa-btn" href="phananh.php">+ Tạo phản ánh mới</a>
<?php if (!$items): ?><p class="pa-muted">Bạn chưa có phản ánh nào.</p><?php endif; ?>
<?php foreach($items as $item): ?><a class="pa-item <?= $id===(int)$item['id']?'selected':'' ?>" href="phananh.php?id=<?=(int)$item['id']?>&amp;p=<?=$p?>"><small><?=pa_h(pa_code($item['id']))?> · <?=pa_h(pa_date($item['updated_at']))?></small><strong><?=pa_h($item['tieu_de'])?></strong><?=pa_status_html($item['trang_thai'])?></a><?php endforeach; ?>
<div class="pa-pages"><?php if($p>1): ?><a href="phananh.php?p=<?=$p-1?>">‹ Trước</a><?php endif; ?><span>Trang <?=$p?> / <?=max(1,(int)ceil($total/10))?></span><?php if($p*10<$total): ?><a href="phananh.php?p=<?=$p+1?>">Sau ›</a><?php endif; ?></div><a class="pa-back" href="taikhoan.php">‹ Tài khoản của tôi</a></aside>
<section class="pa-panel">
<?php if($detail): ?>
<div class="pa-detail-head"><div><small><?=pa_h(pa_code($detail['id']))?> · <?=pa_h(pa_date($detail['created_at']))?></small><h2><?=pa_h($detail['tieu_de'])?></h2></div><?=pa_status_html($detail['trang_thai'])?></div><p class="pa-muted"><?=pa_h($detail['loai'])?> · Đơn hàng: <?=pa_h($detail['ma_don']??'Không liên kết')?></p>
<div class="pa-message from-customer"><strong>Nội dung ban đầu</strong><div class="pa-text"><?=pa_h($detail['noi_dung'])?></div><?php if($detail['has_image']): ?><a href="phananh_anh.php?id=<?=$id?>" target="_blank" rel="noopener"><img class="pa-image" src="phananh_anh.php?id=<?=$id?>" alt="Ảnh phản ánh"></a><?php endif; ?></div>
<h3>Lịch sử trao đổi</h3><?php if(!$replies): ?><p class="pa-muted">Chưa có phản hồi. TT SHOP sẽ tiếp nhận phản ánh của bạn.</p><?php endif; ?><?php pa_history($replies); ?>
<form class="pa-form" method="post" action="phananh.php?id=<?=$id?>"><input type="hidden" name="csrf_token" value="<?=pa_h(csrf_token())?>"><input type="hidden" name="action" value="reply"><input type="hidden" name="id" value="<?=$id?>"><label for="reply">Bổ sung thông tin</label><textarea id="reply" name="noi_dung" rows="4" minlength="5" maxlength="2000" required><?=pa_h(($error&&($_POST['action']??'')==='reply')?$body:'')?></textarea><p class="pa-muted">Nội dung bổ sung sẽ chuyển phản ánh về trạng thái chờ xử lý.</p><button class="pa-btn" type="submit">Gửi bổ sung</button></form>
<?php else: ?>
<h2>Gửi phản ánh mới</h2><p class="pa-muted">Thông tin được lưu trực tiếp vào hệ thống, không gửi qua email.</p>
<form class="pa-form" method="post" enctype="multipart/form-data" action="phananh.php"><input type="hidden" name="csrf_token" value="<?=pa_h(csrf_token())?>"><input type="hidden" name="action" value="create">
<label>Tài khoản</label><div class="pa-readonly"><?=pa_h(trim($user['ho'].' '.$user['ten']))?> · <?=pa_h($user['email'])?></div>
<label for="order">Đơn hàng (không bắt buộc)</label><select id="order" name="ma_don"><option value="">Không liên quan đến đơn hàng</option><?php foreach($orders as $o): ?><option value="<?=pa_h($o['ma_don'])?>" <?=$order===$o['ma_don']?'selected':''?>><?=pa_h($o['ma_don'])?> · <?=pa_h(pa_date($o['created_at']))?></option><?php endforeach; ?></select>
<label for="kind">Loại phản ánh *</label><select name="loai" id="kind" required><option value="">Chọn loại phản ánh</option><?php foreach(pa_types() as $t): ?><option <?=$kind===$t?'selected':''?> value="<?=pa_h($t)?>"><?=pa_h($t)?></option><?php endforeach; ?></select>
<label for="subject">Tiêu đề *</label><input id="subject" name="tieu_de" required minlength="3" maxlength="150" value="<?=pa_h($subject)?>" placeholder="Ví dụ: Sản phẩm bị hư hỏng khi nhận hàng">
<label for="body">Nội dung *</label><textarea id="body" name="noi_dung" required minlength="5" maxlength="2000" rows="6" placeholder="Mô tả vấn đề và mong muốn được hỗ trợ..."><?=pa_h(($_POST['action']??'')==='create'?$body:'')?></textarea>
<label for="image">Ảnh minh chứng (không bắt buộc)</label><input type="file" id="image" name="anh" accept="image/jpeg,image/png,image/webp"><p class="pa-muted">Một ảnh JPG, PNG hoặc WEBP, tối đa 2 MB. Không gửi hình ảnh chứa thông tin nhạy cảm.</p>
<button type="submit" class="pa-btn">Gửi phản ánh</button></form>
<?php endif; ?>
</section></div><?php endif; ?>
</main><?php require __DIR__.'/footer.php'; ?></body></html>
