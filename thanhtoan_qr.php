<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/csrf.php';
require_once __DIR__.'/payment_helpers.php';
header('Cache-Control: no-store, private');
if (($_SESSION['is_login'] ?? false) !== true || (int)($_SESSION['user_id'] ?? 0)<=0) { header('Location: dangnhap.php'); exit; }
$userId=(int)$_SESSION['user_id'];
$id=filter_var($_GET['order_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>2147483647]]);
if (!$id) { http_response_code(404); exit('Không tìm thấy đơn hàng.'); }
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require __DIR__.'/connect.php';
$o=payos_order($conn,$id,$userId);
if (!$o || $o['phuong_thuc']!=='PAYOS') { http_response_code(404); exit('Không tìm thấy đơn hàng QR.'); }
$token=csrf_token(); $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) { http_response_code(403); exit('Phiên không hợp lệ.'); }
    try {
        if (($_POST['action'] ?? '')==='open') {
            $url=payos_create($conn,$o);
            if ($url!=='') { header('Location: '.$url,true,303); exit; }
        } elseif (($_POST['action'] ?? '')==='cancel' && $o['payment_status']==='PENDING') {
            $o=payos_sync($conn,$o,true);
            if ($o['payment_status']==='PENDING' && (int)$o['payment_amount_received']===0) {
                $d=payos_api('POST','/v2/payment-requests/'.$o['payos_order_code'].'/cancel',['cancellationReason'=>'Khach huy don']);
                payos_apply($conn,$id,$d);
            }
        }
        header('Location: thanhtoan_qr.php?order_id='.$id,true,303); exit;
    } catch (Throwable $e) { error_log('TT SHOP payOS page: '.$e->getMessage()); $error='Chưa thể xử lý yêu cầu. Hãy kiểm tra lại hoặc liên hệ TT SHOP; không chuyển khoản lần hai.'; }
} elseif ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit; }
try { if (in_array($o['payment_status'],['PENDING','REVIEW'],true) && payos_ready()) $o=payos_sync($conn,$o); }
catch(Throwable $e) { error_log('TT SHOP payOS check: '.$e->getMessage()); }
$paid=$o['payment_status']==='PAID'; $pending=$o['payment_status']==='PENDING';
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Thanh toán QR - TT SHOP</title><link rel="stylesheet" href="style.css?v=10"><style>
body.tt-qr{margin:0;padding-top:var(--tt-qr-header,140px);background:#f3f7f5;color:#23352d;font-family:Arial,Helvetica,sans-serif}body.tt-qr>header{position:fixed;top:0;left:0;width:100%;z-index:999999}.tt-qr-main{padding:32px 16px;display:flex;justify-content:center;min-height:65vh}.tt-qr-card{width:min(600px,100%);box-sizing:border-box;padding:28px;border:1px solid #dce9e2;border-radius:18px;background:white;box-shadow:0 8px 30px #123e2410;text-align:center}.tt-qr-card h1{font-family:Arial,sans-serif;color:#08794f;font-size:28px;margin:12px 0}.tt-qr-card p{line-height:1.6}.tt-qr-card .amount{font-size:34px;font-weight:bold;color:#08794f}.tt-qr-card .meta{text-align:left;background:#f5faf7;border:1px solid #dce9e2;border-radius:10px;padding:14px 18px}.tt-qr-card .actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin-top:18px}.tt-qr-card button,.tt-qr-card a.btn{font:700 15px Arial,sans-serif;padding:12px 18px;color:white;background:#08794f;border:1px solid #08794f;border-radius:8px;text-decoration:none;cursor:pointer}.tt-qr-card .secondary{color:#08794f!important;background:white!important}.tt-qr-card .note{color:#5b6d62;font-size:14px}.tt-qr-card .error{color:#a12929}.tt-qr-card .symbol{margin:auto;font-size:36px;width:72px;height:72px;display:grid;place-items:center;border-radius:50%;background:#e1f4e8;color:#08794f}
</style></head><body class="tt-qr"><?php include __DIR__.'/header.php'; ?>
<main class="tt-qr-main"><section class="tt-qr-card"><div class="symbol" aria-hidden="true"><?= $paid ? '✓' : '↗' ?></div>
<h1><?= $paid ? 'Thanh toán thành công!' : ($pending ? 'Thanh toán qua VietQR' : payos_escape(payos_label($o))) ?></h1>
<p><?= $paid ? 'Cảm ơn quý khách! Đơn hàng đã được thanh toán và đang được TT SHOP xử lý.' : ($pending ? 'Quét mã trên trang thanh toán chính thức của payOS. Không cần nhập lại số tiền và nội dung.' : 'Vui lòng kiểm tra trạng thái hoặc liên hệ TT SHOP nếu đã chuyển tiền.') ?></p>
<div class="amount"><?= number_format((int)$o['tong_cong'],0,',','.') ?>đ</div><div class="meta">
<p><b>Mã đơn hàng:</b> <?= payos_escape($o['ma_don']) ?></p><p><b>Trạng thái thanh toán:</b> <span id="pay-state"><?= payos_escape(payos_label($o)) ?></span></p><p><b>Đã nhận:</b> <?= number_format((int)$o['payment_amount_received'],0,',','.') ?>đ</p><p><b>Trạng thái đơn:</b> <?= payos_escape($o['trang_thai']) ?></p>
<?php if ($paid): ?><p><b>Thời điểm xác nhận:</b> <?= payos_escape($o['paid_at']) ?></p><?php endif; ?></div>
<?php if($error): ?><p class="error"><?= payos_escape($error) ?></p><?php endif; ?>
<?php if($pending): ?><p class="note" id="poll-note">Hệ thống tự kiểm tra giao dịch mỗi 10–15 giây. Không thanh toán lại nếu đã chuyển khoản.</p><div class="actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= payos_escape($token) ?>"><button name="action" value="open" type="submit">Mở mã QR payOS</button></form><form method="post" onsubmit="return confirm('Chỉ hủy nếu bạn chưa chuyển khoản. Tiếp tục?')"><input type="hidden" name="csrf_token" value="<?= payos_escape($token) ?>"><button class="secondary" name="action" value="cancel" type="submit">Hủy thanh toán</button></form></div><?php endif; ?>
<div class="actions"><a class="btn secondary" href="taikhoan.php?tab=donhang">Theo dõi đơn hàng</a><a class="btn" href="index.php">Tiếp tục mua sắm</a></div></section></main>
<script>(function(){const h=document.querySelector('body > header');if(h){const set=()=>document.body.style.setProperty('--tt-qr-header',h.offsetHeight+'px');set();window.addEventListener('resize',set);if(window.ResizeObserver)new ResizeObserver(set).observe(h);}})();
<?php if($pending): ?>
const check=async()=>{try{const r=await fetch('payment_status.php?id=<?= (int)$id ?>',{cache:'no-store',credentials:'same-origin'});if(r.ok){const j=await r.json();if(j.status!=='PENDING'||j.received!==<?= (int)$o['payment_amount_received'] ?>){location.reload();return;}}}catch(e){}setTimeout(check,12000);};setTimeout(check,2000);
<?php endif; ?>
</script></body></html>
