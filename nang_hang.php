<?php
session_start();
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/upgrade_helpers.php';
if(($_SESSION['is_login']??false)!==true || (int)($_SESSION['user_id']??0)<=0){header('Location: dangnhap.php');exit;}
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
require __DIR__.'/connect.php';
$uid=(int)$_SESSION['user_id'];$token=csrf_token();$error='';$o=null;$member=null;$history=[];
function uh($v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function um($v): string {return number_format((int)$v,0,',','.').'đ';}
$tiers=array_keys(membership_rules());$prices=upgrade_prices();
try {
    $s=$conn->prepare('SELECT email FROM users WHERE id=?');$s->bind_param('i',$uid);$s->execute();$u=$s->get_result()->fetch_assoc();$s->close();
    if(!$u || (isset($_SESSION['user_email'])&&strcasecmp((string)$_SESSION['user_email'],(string)$u['email'])!==0)){header('Location: dangnhap.php');exit;}
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!csrf_check($_POST['csrf_token']??null)){http_response_code(403);throw new DomainException('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');}
        $action=$_POST['action']??'';
        if($action==='create'){
            $target=filter_var($_POST['target']??null,FILTER_VALIDATE_INT);$expected=filter_var($_POST['from_rank']??null,FILTER_VALIDATE_INT);
            if($target===false||$expected===false||$expected<0||$expected>4) throw new DomainException('Yêu cầu nâng hạng không hợp lệ.');
            $o=upgrade_new($conn,$uid,$target,$expected);
            header('Location: nang_hang.php?id='.$o['id'],true,303);exit;
        }
        $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
        $o=$id?upgrade_find($conn,$id,$uid):null;
        if(!$o){http_response_code(404);throw new DomainException('Không tìm thấy giao dịch của bạn.');}
        if($action==='pay'){
            $url=upgrade_checkout($conn,$o);
            if($url!==''){header('Location: '.$url,true,303);exit;}
        }elseif($action==='check'){
            $o=upgrade_sync($conn,$o);
        }else throw new DomainException('Yêu cầu không hợp lệ.');
        header('Location: nang_hang.php?id='.$o['id'],true,303);exit;
    }
}catch(DomainException $e){$error=$e->getMessage();}catch(Throwable $e){error_log('TT SHOP upgrade: '.$e->getMessage());$error='Chưa thể xử lý giao dịch. Vui lòng thử lại hoặc liên hệ hỗ trợ; không chuyển tiền lần thứ hai khi chưa đối soát.';}
try {
    $member=membership_get($conn,$uid);
    $active=upgrade_active($conn,$uid);
    $id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
    if($id){$o=upgrade_find($conn,$id,$uid);if(!$o){http_response_code(404);$error='Không tìm thấy giao dịch của bạn.';}}
    if(!$o)$o=$active;
    $s=$conn->prepare('SELECT * FROM membership_upgrades WHERE user_id=? ORDER BY id DESC LIMIT 20');$s->bind_param('i',$uid);$s->execute();$history=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}catch(Throwable $e){error_log('TT SHOP upgrade view: '.$e->getMessage());$error='Chưa thể tải thông tin nâng hạng. Kiểm tra migration SQL và kết nối database.'; $member=null;}
$labels=['PENDING'=>'Chờ thanh toán','PAID'=>'Đã thanh toán','CANCELLED'=>'Đã hủy','EXPIRED'=>'Hết hạn','REVIEW'=>'Cần đối soát'];
header('Cache-Control: no-store, private');
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Nâng hạng thành viên | TT SHOP</title><style>
*{box-sizing:border-box}body{margin:0;background:#edf5f0;color:#23372d;font:16px/1.55 Arial,sans-serif}.up-head{background:#109868;color:white;padding:16px max(20px,calc((100% - 1040px)/2));font-size:25px;font-weight:bold;white-space:nowrap}.up-wrap{max-width:1040px;margin:28px auto;padding:0 18px}.up-wrap a{color:#087e50}.up-card{background:white;border:1px solid #d6e9dd;border-radius:16px;padding:24px;margin:18px 0}.up-card h1,.up-card h2{margin:0 0 12px;color:#086641}.up-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}.up-option{border:1px solid #c9e3d3;border-radius:12px;padding:18px;background:#f8fcfa}.up-option h3{margin:0;color:#086641}.up-price{font-size:27px;font-weight:bold;color:#008c51}.up-btn{background:#09965f;color:white!important;border:0;padding:12px 18px;border-radius:9px;font:inherit;font-weight:bold;cursor:pointer;display:inline-block;text-decoration:none}.up-btn.secondary{background:#e1f1e9;color:#126140!important}.up-btn:disabled{opacity:.5;cursor:not-allowed}.up-error{background:#fff2e9;border:1px solid #edc4aa;padding:14px;border-radius:10px}.up-note{color:#52665b;font-size:14px}.up-actions{display:flex;gap:10px;flex-wrap:wrap}.up-table{width:100%;border-collapse:collapse}.up-table td,.up-table th{padding:9px;text-align:left;border-bottom:1px solid #e0eae4}.up-scroll{overflow:auto}.up-status{font-weight:bold;color:#087e50}form{margin:0}
</style></head><body><header class="up-head">TT SHOP</header><main class="up-wrap"><a href="taikhoan.php?tab=thanhvien">← Quay lại chương trình thành viên</a>
<section class="up-card"><h1>NÂNG CẤP CÁC HẠNG</h1><p>Nâng hạng ngay bằng thanh toán QR hoặc tiếp tục mua hàng để nâng hạng miễn phí.</p>
<?php if($member): ?><p>Hạng hiện tại: <strong class="up-price"><?=uh($member['tier'])?></strong></p><?php endif; ?>
<?php if($error): ?><p class="up-error"><?=uh($error)?></p><?php endif; ?>
<?php if(!payos_ready()): ?><p class="up-error">Thanh toán PayOS chưa được cấu hình. Không thể tạo yêu cầu thanh toán.</p><?php endif; ?></section>
<?php if($o): ?><section class="up-card"><h2>Giao dịch nâng hạng #<?= (int)$o['id'] ?></h2><p><?=uh($tiers[(int)$o['from_rank']])?> → <strong><?=uh($tiers[(int)$o['target_rank']])?></strong> · <strong><?=um($o['amount'])?></strong></p><p class="up-status"><?=uh($labels[$o['status']]??$o['status'])?></p>
<?php if($o['status']==='PAID'): ?><p>Thanh toán thành công. Hạng đã được mở khóa; voucher vẫn áp dụng theo điều kiện của từng mã.</p><a class="up-btn" href="taikhoan.php?tab=thanhvien#tk-wallet-title">Xem kho voucher</a>
<?php elseif(in_array($o['status'],['PENDING','REVIEW'],true)): ?><p class="up-note">Nếu đã chuyển khoản, hãy kiểm tra thanh toán. Không chuyển tiền lần nữa khi chưa xác nhận trạng thái.</p><div class="up-actions">
<?php foreach(($o['status']==='PENDING'?['pay'=>'Thanh toán QR','check'=>'Kiểm tra thanh toán']:['check'=>'Kiểm tra thanh toán']) as $act=>$label): ?><form method="post"><input type="hidden" name="csrf_token" value="<?=uh($token)?>"><input type="hidden" name="id" value="<?=(int)$o['id']?>"><button class="up-btn" name="action" value="<?=uh($act)?>" <?=!payos_ready()?'disabled':''?>><?=uh($label)?></button></form><?php endforeach; ?></div><?php endif; ?></section><?php endif; ?>
<?php if($member): ?><section class="up-card"><h2>Chọn hạng muốn nâng</h2><?php if($member['rank']===4): ?><p>Bạn đã đạt Diamond – hạng cao nhất.</p><?php elseif($active): ?><p>Vui lòng hoàn tất hoặc đối soát giao dịch đang chờ trước khi mua hạng khác. <a href="nang_hang.php?id=<?=(int)$active['id']?>">Xem giao dịch đang chờ</a></p><?php else: ?><div class="up-grid">
<?php for($i=$member['rank']+1;$i<=4;$i++): $jump=$i-$member['rank'];$price=$prices[$jump]; ?><article class="up-option"><h3><?=uh($tiers[$i])?></h3><p>Nâng <?= $jump ?> bậc</p><div class="up-price"><?=um($price)?></div><p class="up-note">Tiết kiệm <?=um($jump*50000-$price)?> so với mua từng bậc.</p><form method="post"><input type="hidden" name="csrf_token" value="<?=uh($token)?>"><input type="hidden" name="target" value="<?=$i?>"><input type="hidden" name="from_rank" value="<?=(int)$member['rank']?>"><button class="up-btn" name="action" value="create" <?=!payos_ready()?'disabled':''?>>Chọn <?=uh($tiers[$i])?></button></form></article><?php endfor; ?></div><?php endif; ?><p class="up-note">Hạng đã mua không có thời hạn. Phí nâng hạng không tính vào tổng chi tiêu mua sản phẩm. Hạng chỉ được mở khi PayOS xác nhận đủ tiền.</p></section><?php endif; ?>
<section class="up-card"><h2>Bảng giá nâng hạng</h2><div class="up-scroll"><table class="up-table"><thead><tr><th>Số bậc</th><th>Giá</th><th>Mua từng bậc</th><th>Tiết kiệm</th></tr></thead><tbody><?php foreach($prices as $jump=>$price): ?><tr><td><?=$jump?></td><td><?=um($price)?></td><td><?=um($jump*50000)?></td><td><?=um($jump*50000-$price)?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="up-card"><h2>Lịch sử nâng hạng</h2><?php if(!$history): ?><p>Chưa có giao dịch.</p><?php else: ?><div class="up-scroll"><table class="up-table"><thead><tr><th>Thời gian</th><th>Hạng</th><th>Phí</th><th>Trạng thái</th></tr></thead><tbody><?php foreach($history as $r): ?><tr><td><?=uh($r['created_at'])?></td><td><?=uh($tiers[(int)$r['from_rank']].' → '.$tiers[(int)$r['target_rank']])?></td><td><?=um($r['amount'])?></td><td><a href="nang_hang.php?id=<?=(int)$r['id']?>"><?=uh($labels[$r['status']]??$r['status'])?></a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section></main></body></html>
