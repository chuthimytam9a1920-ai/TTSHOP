<?php
// thanhtoan.php - Thông tin giao hàng và voucher thành viên
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validate_helpers.php';
require_once __DIR__ . '/voucher_helpers.php';
header('Cache-Control: no-store');

function tt_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function tt_money($v): string {
    return number_format((int)$v, 0, ',', '.') . 'đ';
}
function tt_text($v): string {
    return is_string($v) ? trim($v) : '';
}
function tt_clear_voucher(): void {
    unset($_SESSION['voucher_code'], $_SESSION['voucher_user_id']);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (($_SESSION['is_login'] ?? false) !== true || $userId <= 0) {
    header('Location: dangky.php'); exit;
}
if (empty($_SESSION['cart'])) {
    tt_clear_voucher(); header('Location: giohang.php'); exit;
}
$token = csrf_token();
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($isPost && !csrf_check($_POST['csrf_token'] ?? null)) {
    http_response_code(403); exit('Phiên xác thực không hợp lệ. Vui lòng quay lại và tải lại trang.');
}

// Tính lại giá từ hai bảng sản phẩm như giohang.php.
function tt_refresh_cart(mysqli $conn): int {
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) throw new DomainException('Giỏ hàng không hợp lệ.');
    $updated = []; $subtotal = 0;
    foreach ($cart as $key => $item) {
        $id = (string)$key;
        if (!is_array($item)) throw new DomainException('Sản phẩm không hợp lệ.');
        $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT,
            ['options' => ['min_range'=>1, 'max_range'=>2147483647]]);
        if ($qty === false) throw new DomainException('Số lượng không hợp lệ.');
        if (preg_match('/^cat_([1-9][0-9]{0,9})$/D', $id, $m)) {
            $table = 'category_products'; $pid = (int)$m[1];
        } elseif (preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
            $table = 'products'; $pid = (int)$id;
        } else throw new DomainException('Mã sản phẩm không hợp lệ.');
        if ($pid > 2147483647) throw new DomainException('Mã sản phẩm không hợp lệ.');
        $stmt = $conn->prepare("SELECT name, price, old_price, image FROM `$table` WHERE id = ?");
        try {
            $stmt->bind_param('i', $pid); $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        if (!$p || (int)$p['price'] <= 0) {
            throw new DomainException('Sản phẩm không còn bán hoặc giá không hợp lệ. Hãy cập nhật giỏ hàng.');
        }
        $price = (int)$p['price'];
        if ($price > intdiv(2147483647 - 30000 - $subtotal, $qty)) {
            throw new DomainException('Tổng giá trị đơn hàng vượt giới hạn.');
        }
                $subtotal += $price * $qty;
        $item['id'] = $id; $item['qty'] = $qty;
        $item['name'] = $p['name']; $item['price'] = $price;
        $item['old_price'] = (int)$p['old_price'];
        $item['img'] = $table === 'products' ? 'images/'.$p['image'] : $p['image'];
        $updated[$key] = $item;
    }
    $_SESSION['cart'] = $updated;
    return $subtotal;
}

// Cùng cấu trúc kết quả với AJAX snapshot của giohang.php.
function tt_snapshot(mysqli $conn, int $userId): array {
    $subtotal = tt_refresh_cart($conn);
    $cart = $_SESSION['cart'];
    $shipping = $cart ? 30000 : 0;
    $member = membership_get($conn, $userId);
    if (!$cart || (int)($_SESSION['voucher_user_id'] ?? 0) !== $userId) tt_clear_voucher();
    $code = tt_text($_SESSION['voucher_code'] ?? '');
    $result = voucher_validate($conn, $userId, $code, $subtotal, $shipping);
    $message = '';
    if (!$result['ok']) {
        $message = $result['message']; tt_clear_voucher(); $code = '';
    }
    $totals = voucher_calculate($subtotal, $shipping, $result['ok'] ? $result['voucher'] : null);
    $list = [];
    if ($cart) foreach (voucher_list($conn, $userId, $subtotal, $shipping) as $v) {
        $r = $v['voucher'];
        $list[] = ['code'=>$r['ma_code'], 'tier'=>$r['hang_toi_thieu'],
            'rate'=>(int)$r['phan_tram_giam'], 'min'=>(int)$r['don_toi_thieu'],
            'cap'=>(int)$r['giam_toi_da'], 'free'=>(int)$r['mien_phi_van_chuyen']===1,
            'ok'=>$v['ok'], 'message'=>$v['ok'] ? '' : $v['message']];
    }
    return array_merge($totals, ['ok'=>true, 'items'=>array_values($cart),
        'member'=>$member, 'vouchers'=>$list, 'code'=>$code, 'message'=>$message]);
}

$error = tt_text($_SESSION['checkout_error'] ?? '');
$old = $_SESSION['checkout_old'] ?? $_SESSION['checkout_info'] ?? [];
if (!is_array($old)) $old = [];
unset($_SESSION['checkout_error'], $_SESSION['checkout_old']);
$user = [];
$delivery = [];
$state = ['ok'=>false,'items'=>[],'member'=>null,'vouchers'=>[],'code'=>'','message'=>'',
    'subtotal'=>0,'discount'=>0,'shipping'=>0,'shipping_discount'=>0,'total'=>0];
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/connect.php';
    $stmt = $conn->prepare('SELECT ho, ten, email, sdt, diachi FROM users WHERE id = ?');
    try {
        $stmt->bind_param('i', $userId); $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc() ?: [];
    } finally { $stmt->close(); }
    if (!$user || empty($_SESSION['user_email']) ||
        strcasecmp((string)$_SESSION['user_email'], (string)$user['email']) !== 0) {
        tt_clear_voucher();
        unset($_SESSION['checkout_info']);
        throw new DomainException('Tài khoản không hợp lệ. Vui lòng đăng xuất và đăng nhập lại.');
    }
    $fullName = trim(($user['ho'] ?? '').' '.($user['ten'] ?? ''));
    $delivery = [
        'ho_ten'=>tt_text($old['ho_ten'] ?? $fullName),
        'sdt'=>tt_text($old['sdt'] ?? $user['sdt'] ?? ''),
        'dia_chi'=>tt_text($old['dia_chi'] ?? $user['diachi'] ?? '')
    ];
    if ($isPost) {
        foreach ($delivery as $key=>$value) $delivery[$key] = tt_text($_POST[$key] ?? '');
        $delivery['sdt'] = normalize_vn_phone($delivery['sdt']);
    }
    $state = tt_snapshot($conn, $userId);
    if ($isPost) {
        $error = '';
        if ($delivery['ho_ten'] === '') $error = validation_message('recipient_required');
        elseif (!is_valid_vn_phone($delivery['sdt'])) $error = phone_validation_message();
        elseif ($delivery['dia_chi'] === '') $error = validation_message('address_required');
        elseif (!$state['items']) $error = 'Giỏ hàng đang trống.';
        elseif ($state['message'] !== '') $error = $state['message'].' Hãy kiểm tra lại tổng tiền.';
        elseif (tt_text($_POST['review_code'] ?? '') !== $state['code'] ||
            tt_text($_POST['review_total'] ?? '') !== (string)$state['total']) {
            $error = 'Ưu đãi hoặc tổng tiền đã thay đổi. Vui lòng kiểm tra lại trước khi tiếp tục.';
        }
        if ($error === '') {
            // Trang phương thức thanh toán đã hỗ trợ đọc checkout_info từ SESSION.
            $_SESSION['checkout_info'] = array_merge($delivery,
                ['tinh_thanh'=>'','quan_huyen'=>'','phuong_xa'=>'']);
            header('Location: phuongthucthanhtoan.php', true, 303); exit;
        }
        // Không cho dùng lại thông tin giao hàng cũ sau POST thất bại.
        unset($_SESSION['checkout_info']);
    }
    } catch (Throwable $e) {
    $state['ok'] = false;
    $error = $e instanceof DomainException ? $e->getMessage() : 'Không thể tải thông tin thanh toán. Vui lòng thử lại.';
    if (!($e instanceof DomainException)) error_log('TT SHOP thanhtoan: '.$e->getMessage());
}
$fullName = trim(($user['ho'] ?? '').' '.($user['ten'] ?? ''));
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thanh toán - TT SHOP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#fff;color:#333}
.checkout-page{display:grid;grid-template-columns:minmax(0,58fr) minmax(0,42fr);min-height:100vh}
.checkout-left{padding:55px 70px;border-right:1px solid #ddd;min-width:0}
.checkout-right{padding:45px 60px;background:#fafafa;min-width:0}
.logo{display:inline-block;font-size:42px;font-weight:900;color:#15985d;margin-bottom:45px;text-decoration:none}
.breadcrumb{display:flex;flex-wrap:wrap;gap:12px;font-size:15px;margin-bottom:45px}
.breadcrumb a{color:#333;text-decoration:none}.breadcrumb span{color:#999}.breadcrumb .active-step{font-weight:800;color:#111}
h2{font-size:26px;margin-bottom:20px}
.user-box{display:flex;gap:15px;align-items:center;margin-bottom:25px;overflow-wrap:anywhere}
.user-icon{width:58px;height:58px;background:#ddd;color:#fff;display:grid;place-items:center;font-size:32px;flex-shrink:0}
.user-box p{margin:4px 0;font-size:16px}.user-box a,.back-cart{color:#333;text-decoration:none}
.checkout-form input:not([type=hidden]){width:100%;height:58px;border:1px solid #ddd;padding:0 15px;font-size:17px;margin-bottom:14px;background:#fff}
.checkout-action{display:flex;flex-wrap:wrap;gap:15px;align-items:center;justify-content:space-between;margin-top:28px}
.next-btn,.coupon-box button{background:#15985d;color:#fff;border:0;border-radius:7px;font-weight:700;cursor:pointer}
.next-btn{padding:22px 38px;font-size:20px}.next-btn:hover{background:#0d7d4b}
button:disabled{opacity:.5;cursor:not-allowed}
.product-item{display:grid;grid-template-columns:85px minmax(0,1fr) auto;gap:15px;align-items:center;padding-bottom:18px;margin-bottom:18px;border-bottom:1px solid #e5e5e5}
.product-img{position:relative}.product-img img{width:78px;height:78px;object-fit:contain;border:1px solid #ddd}
.qty-badge{position:absolute;top:-10px;right:0;background:#999;color:#fff;min-width:25px;padding:5px;text-align:center;font-weight:bold}
.product-name,.product-price{font-size:16px;font-weight:700;line-height:1.35}.product-price{text-align:right}
.coupon-box{display:flex;flex-wrap:wrap;gap:12px;border:1px dashed #1d61ff;padding:18px;margin:25px 0 15px}
.coupon-box input{flex:1;min-width:0;height:56px;border:1px solid #9bbcff;padding:0 15px;font-size:17px}
.coupon-box button{padding:12px 18px;font-size:16px}
.loyal-title{font-size:24px;font-weight:800;padding-top:28px;margin-top:25px;border-top:1px solid #ddd}
.member-box{background:#f1fbf6;border:1px solid #b9dfca;border-radius:8px;padding:14px;color:#087e49;margin-top:12px}
.voucher-list{display:grid;gap:10px;margin:14px 0}
.voucher-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border:1px dashed #a4cbb5;border-radius:8px}
.voucher-item small{display:block;color:#666;margin-top:5px}.voucher-item button{flex-shrink:0;border:1px solid #15985d;color:#15985d;background:white;padding:8px 12px;border-radius:6px;cursor:pointer}
.notice{background:#fff0f0;color:#a12020;padding:12px;border-radius:6px}.voucher-message{color:#a12020;min-height:20px}.voucher-message.success,.saving{color:#15985d}
.total-row,.grand-total{display:flex;justify-content:space-between;gap:12px;align-items:center;margin:18px 0;font-size:17px}
.grand-total{border-top:1px solid #ddd;padding-top:25px;margin-top:25px;font-size:20px;flex-wrap:wrap}.grand-total strong{font-size:30px}.vnd{color:#999;font-size:15px;margin-right:8px}
@media(max-width:900px){.checkout-page{grid-template-columns:1fr}.checkout-left,.checkout-right{padding:30px 20px}.next-btn{width:100%}}
</style>
</head>
<body>
<div class="checkout-page">
<section class="checkout-left">
    <a href="index.php" class="logo">TT SHOP</a>
    <nav class="breadcrumb"><a href="giohang.php">Giỏ hàng</a><span>›</span><span class="active-step">Thông tin giao hàng</span><span>›</span><span>Phương thức thanh toán</span></nav>
    <h2>Thông tin giao hàng</h2>
    <div class="user-box"><div class="user-icon"><i class="fas fa-user"></i></div><div>
        <p><?= tt_e($fullName) ?> (<?= tt_e($user['email'] ?? '') ?>)</p><p><a href="dangxuat.php">Đăng xuất</a></p>
    </div></div>
        <?php if ($error !== ''): ?><p class="notice" role="alert"><?= tt_e($error) ?></p><?php endif; ?>
    <form id="delivery-form" class="checkout-form" action="thanhtoan.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= tt_e($token) ?>">
        <input type="hidden" name="review_code" id="review-code" value="<?= tt_e($state['code']) ?>">
        <input type="hidden" name="review_total" id="review-total" value="<?= tt_e($state['total']) ?>">
        <input type="text" name="ho_ten" aria-label="Họ và tên" placeholder="Họ và tên" autocomplete="name" value="<?= tt_e($delivery['ho_ten'] ?? '') ?>" required>
        <input type="tel" name="sdt" aria-label="Số điện thoại" placeholder="Số điện thoại" autocomplete="tel" value="<?= tt_e($delivery['sdt'] ?? '') ?>" required>
        <input type="text" name="dia_chi" aria-label="Địa chỉ" placeholder="Địa chỉ cụ thể, số nhà, tên đường..." autocomplete="street-address" value="<?= tt_e($delivery['dia_chi'] ?? '') ?>" required>
        <div class="checkout-action"><a href="giohang.php" class="back-cart">Giỏ hàng</a><button id="next-btn" class="next-btn" type="submit" disabled>Tiếp tục đến phương thức thanh toán</button></div>
    </form>
</section>
<section class="checkout-right">
    <div id="product-list"></div>
    <div class="coupon-box">
        <input id="voucher-code" aria-label="Mã giảm giá" placeholder="Mã giảm giá" maxlength="50" disabled>
        <button id="voucher-apply" type="button" disabled>Sử dụng</button>
        <button id="voucher-remove" type="button" hidden disabled>Gỡ mã</button>
    </div>
    <p id="voucher-message" class="voucher-message" role="status" aria-live="polite"></p>
    <h2 class="loyal-title">Chương trình khách hàng thân thiết</h2>
    <div id="member-info" class="member-box"></div>
    <div id="voucher-list" class="voucher-list"></div>
    <div class="total-row"><span>Tạm tính</span><strong id="subtotal">—</strong></div>
    <div class="total-row saving"><span>Giảm giá thành viên</span><strong id="discount">—</strong></div>
    <div class="total-row"><span>Phí vận chuyển</span><strong id="shipping">—</strong></div>
    <div class="total-row saving"><span>Tiết kiệm</span><strong id="saving">—</strong></div>
    <div class="grand-total"><span>Tổng cộng</span><div><span class="vnd">VND</span><strong id="total">—</strong></div></div>
</section>
</div>
<noscript><p class="notice">Vui lòng bật JavaScript để xem ưu đãi và tiếp tục thanh toán.</p></noscript>
<script>
const $ = id => document.getElementById(id);
const csrf = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let state = <?= json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
let busy = false;
const money = n => Number(n).toLocaleString('vi-VN') + 'đ';
function element(tag, cls, text) {
    const el = document.createElement(tag); if (cls) el.className = cls;
    if (text !== undefined) el.textContent = text; return el;
}
function message(text, ok = false) {
    $('voucher-message').textContent = text || '';
    $('voucher-message').classList.toggle('success', ok);
}
function controls() {
    const disabled = busy || !state.ok || !state.items?.length;
    ['voucher-code','voucher-apply','voucher-remove','next-btn'].forEach(id => $(id).disabled = disabled);
    $('voucher-list').querySelectorAll('button').forEach(b => b.disabled = disabled || b.dataset.allowed !== '1');
}
function render(s) {
    state = s;
    if (!s.ok) {
        ['subtotal','discount','shipping','saving','total'].forEach(id => $(id).textContent = '—');
        $('voucher-remove').hidden = true;
        message(s.message || 'Không thể tải thông tin đơn hàng.'); controls(); return;
    }
    const products = $('product-list'); products.replaceChildren();
    for (const i of s.items) {
        const row = element('div','product-item');
        const imageBox = element('div','product-img');
        const img = element('img'); img.src = i.img; img.alt = i.name;
        imageBox.append(img, element('span','qty-badge',i.qty));
        row.append(imageBox, element('div','product-name',i.name), element('div','product-price',money(i.price*i.qty)));
        products.append(row);
    }
    $('member-info').textContent = s.member ? 'Hạng '+s.member.tier+' · '+s.member.orders+' đơn đã giao · Chi tiêu '+money(s.member.spend) : '';
    $('voucher-code').value = s.code || ''; $('voucher-remove').hidden = !s.code;
    const list = $('voucher-list'); list.replaceChildren();
    if (!s.vouchers.length) list.textContent = 'Hiện chưa có mã giảm giá.';
    for (const v of s.vouchers) {
        const row = element('div','voucher-item'); const info = element('div');
        info.append(element('strong','',v.code+' — Giảm '+v.rate+'%'+(v.free?' + Freeship':'')),
            element('small','','Hạng '+v.tier+' · Đơn từ '+money(v.min)+' · Tối đa '+money(v.cap)));
        if (!v.ok) info.append(element('small','',v.message));
        const b = element('button','',s.code===v.code?'Đã áp dụng':'Áp dụng'); b.type='button';
        b.dataset.allowed = v.ok && s.code!==v.code ? '1':'0';
        b.addEventListener('click',()=>voucher('apply',v.code)); row.append(info,b); list.append(row);
    }
    $('subtotal').textContent = money(s.subtotal); $('discount').textContent = '−'+money(s.discount);
    $('shipping').textContent = s.shipping===0 ? 'Miễn phí' : money(s.shipping);
    $('saving').textContent = money(s.discount+s.shipping_discount); $('total').textContent = money(s.total);
    $('review-code').value = s.code || ''; $('review-total').value = String(s.total);
    controls();
}
async function post(url,action,extra={}) {
    const res = await fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',
        body:new URLSearchParams({csrf_token:csrf,action,...extra})});
    let data; try {data=await res.json();} catch(e){throw new Error('Phản hồi máy chủ không hợp lệ.');}
    if (!res.ok && data.ok!==false) throw new Error('Không thể xử lý yêu cầu.');
    return data;
}
async function voucher(action,code='') {
    if (busy || !state.ok) return;
    busy=true; controls(); message('Đang kiểm tra mã giảm giá...');
    try {
        const result=await post('ap_dung_magiamgia.php',action,{code});
        // Đọc lại giỏ hàng và voucher từ máy chủ, không tự tính giá trong JavaScript.
        const s=await post('giohang.php','snapshot'); render(s);
        if(s.ok) message(s.message || result.message, result.ok===true && !s.message);
    } catch(e) {render({...state,ok:false,message:e.message+' Vui lòng tải lại trang.'});}
    finally {busy=false; controls();}
}
$('voucher-apply').addEventListener('click',()=>voucher('apply',$('voucher-code').value));
$('voucher-remove').addEventListener('click',()=>voucher('remove'));
$('voucher-code').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();voucher('apply',e.target.value);}});
$('delivery-form').addEventListener('submit',e=>{if(busy||!state.ok||!state.items?.length)e.preventDefault();else{busy=true;controls();}});
window.addEventListener('pageshow',e=>{if(e.persisted)location.reload();});