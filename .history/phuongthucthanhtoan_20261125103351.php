<?php
// phuongthucthanhtoan.php - TT SHOP: bước 7, xác nhận thanh toán.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validate_helpers.php';
require_once __DIR__ . '/voucher_helpers.php';
header('Cache-Control: no-store');

function checkout_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function checkout_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}
function pt_text($v): string { return is_string($v) ? trim($v) : ''; }
function pt_clear_voucher(): void {
    unset($_SESSION['voucher_code'], $_SESSION['voucher_user_id']);
}
function pt_redirect(string $page): void {
    header('Location: '.$page, true, 303); exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (($_SESSION['is_login'] ?? false) !== true || $userId <= 0) {
    pt_clear_voucher(); unset($_SESSION['checkout_review']);
    pt_redirect('dangky.php');
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// Trang thanhtoan.php mới chỉ chuyển sang đây bằng GET sau khi kiểm tra CSRF.
// Không nhận lại thông tin giao hàng qua POST trực tiếp.
if ($method === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        http_response_code(403); exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang.');
    }
    pt_redirect('thanhtoan.php');
}
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD'); http_response_code(405); exit;
}
if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    pt_clear_voucher(); unset($_SESSION['checkout_review']);
    pt_redirect('giohang.php');
}
$checkout = $_SESSION['checkout_info'] ?? [];
if (!is_array($checkout) || !$checkout) {
    unset($_SESSION['checkout_review']); pt_redirect('thanhtoan.php');
}
foreach (['ho_ten','sdt','dia_chi','tinh_thanh','quan_huyen','phuong_xa'] as $field) {
    $checkout[$field] = pt_text($checkout[$field] ?? '');
}
$checkout['sdt'] = normalize_vn_phone($checkout['sdt']);
$error = '';
if ($checkout['ho_ten'] === '') $error = validation_message('recipient_required');
elseif (!is_valid_vn_phone($checkout['sdt'])) $error = phone_validation_message();
elseif ($checkout['dia_chi'] === '') $error = validation_message('address_required');
if ($error !== '') {
    $_SESSION['checkout_error'] = $error;
    $_SESSION['checkout_old'] = $checkout;
    unset($_SESSION['checkout_info'], $_SESSION['checkout_review']);
    pt_redirect('thanhtoan.php');
}
$_SESSION['checkout_info'] = $checkout;
$token = csrf_token();
$checkoutError = pt_text($_SESSION['checkout_error'] ?? '');
unset($_SESSION['checkout_error'], $_SESSION['checkout_review']);

// Lấy giá trực tiếp từ database, hỗ trợ ID thường và cat_ID.
function pt_refresh_cart(mysqli $conn): int {
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


$cart = [];
$member = null;
$code = '';
$voucherNotice = '';
$reviewId = '';
$ready = false;
$totals = voucher_calculate(0, 0);
$shippingBase = 30000;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/connect.php';
    $stmt = $conn->prepare('SELECT email FROM users WHERE id = ?');
    try {
        $stmt->bind_param('i', $userId); $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
    } finally { $stmt->close(); }
    $email = pt_text($_SESSION['user_email'] ?? '');
    if (!$account || $email === '' || strcasecmp($email, (string)$account['email']) !== 0) {
        pt_clear_voucher(); unset($_SESSION['checkout_info']);
        throw new DomainException('Tài khoản không hợp lệ. Vui lòng đăng xuất và đăng nhập lại.');
    }
    $subtotal = pt_refresh_cart($conn);
    $cart = $_SESSION['cart'];
    if (!$cart) { pt_clear_voucher(); pt_redirect('giohang.php'); }
    $member = membership_get($conn, $userId);
    if ((int)($_SESSION['voucher_user_id'] ?? 0) !== $userId) pt_clear_voucher();
    $code = pt_text($_SESSION['voucher_code'] ?? '');
    $result = voucher_validate($conn, $userId, $code, $subtotal, $shippingBase);
    if (!$result['ok']) {
        $voucherNotice = $result['message'].' Mã đã được gỡ. Vui lòng kiểm tra lại tổng tiền.';
        pt_clear_voucher(); $code = '';
        $totals = voucher_calculate($subtotal, $shippingBase);
    } else {
        $totals = voucher_calculate($subtotal, $shippingBase, $result['voucher']);
        if ($result['voucher'] !== null) {
            $code = $result['voucher']['ma_code'];
            $_SESSION['voucher_code'] = $code;
            $_SESSION['voucher_user_id'] = $userId;
        } else { $code = ''; pt_clear_voucher(); }
    }
    // Lưu bản xác nhận phía máy chủ; xuly_dathang.php bước 8 phải đối chiếu
    // lại trong transaction, không dùng số tiền POST làm giá trị thanh toán.
    $reviewId = bin2hex(random_bytes(32));
    $reviewCart = [];
    foreach ($cart as $id => $item) {
        $reviewCart[(string)$id] = ['qty'=>(int)$item['qty'], 'price'=>(int)$item['price']];
    }
    ksort($reviewCart, SORT_STRING);
    $_SESSION['checkout_review'] = [
        'id'=>$reviewId, 'user_id'=>$userId, 'code'=>$code,
        'tier'=>$member['tier'], 'cart'=>$reviewCart, 'delivery'=>$checkout,
        'subtotal'=>$totals['subtotal'], 'discount'=>$totals['discount'],
        'shipping_discount'=>$totals['shipping_discount'], 'shipping'=>$totals['shipping'],
        'total'=>$totals['total'], 'created_at'=>time()
    ];
    $ready = true;
} catch (Throwable $e) {
    unset($_SESSION['checkout_review']);
    $checkoutError = $e instanceof DomainException ? $e->getMessage()
        : 'Không thể tải thông tin thanh toán. Vui lòng thử lại.';
    if (!($e instanceof DomainException)) error_log('TT SHOP phuongthucthanhtoan: '.$e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Phương thức thanh toán - TT SHOP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
          * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #333; background: #fff; }
        .checkout-page { display: grid; grid-template-columns: minmax(0,58fr) minmax(0,42fr); min-height: 100vh; }
        .checkout-left { padding: 55px 70px; border-right: 1px solid #ddd; min-width: 0; }
        .checkout-right { padding: 45px 60px; background: #fafafa; min-width: 0; }
        .logo { display: inline-block; font-size: 42px; font-weight: 900; color: #15985d; margin-bottom: 45px; text-decoration: none; }
        .breadcrumb { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; font-size: 15px; margin-bottom: 45px; }
        .step-link { color: #333; text-decoration: none; }
        .step-link:hover { color: #15985d; }
        .step-active { color: #111; font-weight: 800; }
        .arrow { color: #999; }
        h2 { font-size: 24px; margin: 28px 0 12px; }
        .shipping-box, .payment-box { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; margin-bottom: 25px; }
        .shipping-row, .payment-row { display: flex; align-items: center; gap: 15px; min-height: 72px; padding: 16px 22px; }
        .check-square { width: 18px; height: 18px; background: #15985d; display: grid; place-items: center; color: white; }
        .payment-row { background: #f0fff8; }
        .payment-row label { display: flex; align-items: center; gap: 15px; width: 100%; cursor: pointer; font-size: 17px; }
        .payment-row input { width: 18px; height: 18px; accent-color: #15985d; }
        .pay-icon { font-size: 28px; color: #15985d; }
        .ship-price { margin-left: auto; font-weight: 700; }
        .checkout-action { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 15px; margin-top: 25px; }
        .back-cart { color: #333; text-decoration: none; }
        .complete-btn { background: #15985d; color: #fff; border: none; padding: 22px 38px; border-radius: 7px; font-size: 20px; font-weight: 700; cursor: pointer; }
        .complete-btn:hover { background: #0d7d4b; }
        .complete-btn:disabled { opacity: .65; cursor: wait; }
        .error-box { color: #a12020; background: #fff0f0; padding: 12px; border-radius: 6px; }
        .delivery-info { border: 1px solid #ddd; border-radius: 8px; padding: 15px 20px; overflow-wrap: anywhere; line-height: 1.6; }
        .delivery-info a { color: #15985d; }
        .product-item { display: grid; grid-template-columns: 85px minmax(0,1fr) auto; gap: 15px; align-items: center; padding-bottom: 18px; margin-bottom: 18px; border-bottom: 1px solid #e5e5e5; }
        .product-img { position: relative; }
        .product-img img { width: 78px; height: 78px; object-fit: contain; border: 1px solid #ddd; }
        .qty-badge { position: absolute; top: -10px; right: 0; background: #999; color: #fff; min-width: 25px; height: 25px; padding: 4px; border-radius: 2px; text-align: center; font-weight: bold; }
        .product-name, .product-price { font-size: 16px; font-weight: 700; line-height: 1.35; }
        .product-price { text-align: right; white-space: nowrap; }
        .coupon-box { display: flex; gap: 15px; border: 1px dashed #1d61ff; padding: 18px; margin: 25px 0 15px; }
        .coupon-box input { flex: 1; min-width: 0; height: 56px; border: 1px solid #9bbcff; padding: 0 15px; font-size: 18px; }
        .coupon-box button { padding: 0 20px; background: #15985d; color: white; border: none; border-radius: 7px; font-size: 18px; font-weight: bold; }
        .coupon-box button:disabled { opacity: .6; cursor: not-allowed; }
        .coupon-note { color: #777; font-size: 14px; }
        .voucher-summary { border: 1px dashed #a4cbb5; border-radius: 8px; padding: 15px; margin: 24px 0 15px; line-height: 1.6; }
        .voucher-summary a { color: #15985d; font-weight: bold; }
        .member-box { background: #f1fbf6; border: 1px solid #b9dfca; border-radius: 8px; padding: 14px; line-height: 1.6; }
        .saving { color: #15985d; }
        .warning-box { color: #805b12; background: #fff5da; padding: 12px; border-radius: 6px; }
        .loyal-title { font-size: 24px; font-weight: 800; padding-top: 28px; margin-top: 25px; border-top: 1px solid #ddd; }
        .total-row { display: flex; justify-content: space-between; margin: 18px 0; font-size: 17px; gap: 12px; }
        .grand-total { border-top: 1px solid #ddd; padding-top: 25px; margin-top: 25px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; font-size: 20px; }
        .grand-total strong { font-size: 30px; }
        .vnd { color: #999; font-size: 15px; margin-right: 8px; }
        @media (max-width: 900px) {
            .checkout-page { grid-template-columns: 1fr; }
            .checkout-left, .checkout-right { padding: 30px 20px; }
            .complete-btn { width: 100%; }
            .product-item { gap: 10px; }
                   }
    </style>
</head>
<body>
<div class="checkout-page">
    <div class="checkout-left">
        <a href="index.php" class="logo">TT SHOP</a>
        <div class="breadcrumb">
            <a href="giohang.php" class="step-link">Giỏ hàng</a>
            <span class="arrow">›</span>
            <a href="thanhtoan.php" class="step-link">Thông tin giao hàng</a>
            <span class="arrow">›</span>
            <span class="step-active">Phương thức thanh toán</span>
        </div>
        <?php if ($checkoutError !== ''): ?>
            <p class="error-box" role="alert"><?= checkout_escape($checkoutError) ?></p>
        <?php endif; ?>

        <?php if ($voucherNotice !== ''): ?>
            <p class="warning-box" role="status"><?= checkout_escape($voucherNotice) ?></p>
        <?php endif; ?>
        <h2>Thông tin giao hàng</h2>
        <div class="delivery-info">
            <strong><?= checkout_escape($checkout['ho_ten']) ?></strong><br>
            <?= checkout_escape($checkout['sdt']) ?><br>
            <?= checkout_escape($checkout['dia_chi']) ?><br>
            <a href="thanhtoan.php">Thay đổi thông tin</a>
        </div>

        <form id="paymentForm" action="xuly_dathang.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= checkout_escape($token) ?>">
            <input type="hidden" name="review_id" value="<?= checkout_escape($reviewId) ?>">
            <input type="hidden" name="review_code" value="<?= checkout_escape($code) ?>">
            <input type="hidden" name="review_total" value="<?= (int)$totals['total'] ?>">
            <h2>Phương thức vận chuyển</h2>
            <div class="shipping-box">
                <div class="shipping-row">
                    <span class="check-square">✓</span>
                    <span>Giao hàng tận nơi</span>
                    <span class="ship-price"><?= !$ready ? "—" : ($totals['shipping'] === 0 ? "Miễn phí" : checkout_money($totals['shipping'])) ?></span>
                </div>
            </div>
            <h2>Phương thức thanh toán</h2>
            <div class="payment-box">
                <div class="payment-row">
                    <label>
                        <input type="radio" name="phuong_thuc" value="COD" checked required>
                        <i class="fas fa-money-bill-wave pay-icon" aria-hidden="true"></i>
                        Thanh toán khi giao hàng (COD)
                    </label>
                </div>
            </div>
            <div class="checkout-action">
                <a href="giohang.php" class="back-cart">Giỏ hàng</a>
                <button type="submit" id="completeBtn" class="complete-btn" <?= !$ready ? 'disabled' : '' ?>>Hoàn tất đơn hàng</button>
            </div>
        </form>
    </div>
    <div class="checkout-right">
        <?php foreach ($cart as $item):
            $name = $item['name'] ?? '';
            $img = $item['img'] ?? '';
            $price = (int)($item['price'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));
            $thanhTien = $price * $qty;
        ?>
            <div class="product-item">
                <div class="product-img">
                    <img src="<?= checkout_escape($img) ?>" alt="<?= checkout_escape($name) ?>">
                    <span class="qty-badge"><?= $qty ?></span>
                </div>
                <div class="product-name"><?= checkout_escape($name) ?></div>
                <div class="product-price"><?= checkout_money($thanhTien) ?></div>
            </div>
        <?php endforeach; ?>
        <div class="voucher-summary">
            <strong><i class="fas fa-ticket" aria-hidden="true"></i> Mã giảm giá</strong><br>
            <?php if ($ready && $code !== ''): ?>
                <span class="saving"><?= checkout_escape($code) ?> — Đã áp dụng</span><br>
            <?php else: ?>
                <span>Chưa áp dụng mã giảm giá.</span><br>
            <?php endif; ?>
            <a href="thanhtoan.php">Chọn, thay đổi hoặc gỡ mã giảm giá</a>
        </div>
        <div class="loyal-title">Chương trình khách hàng thân thiết</div>
        <?php if ($member !== null): ?>
        <div class="member-box">
            <strong><i class="fas fa-award" aria-hidden="true"></i> Hạng <?= checkout_escape($member['tier']) ?></strong><br>
            <?= (int)$member['orders'] ?> đơn đã giao · Chi tiêu <?= checkout_money($member['spend']) ?>
        </div>
        <?php endif; ?>
        <div class="total-row"><span>Tạm tính</span><strong><?= $ready ? checkout_money($totals['subtotal']) : '—' ?></strong></div>
        <div class="total-row saving"><span>Giảm giá thành viên</span><strong><?= $ready ? '−'.checkout_money($totals['discount']) : '—' ?></strong></div>
        <div class="total-row"><span>Phí vận chuyển</span><strong><?= !$ready ? '—' : ($totals['shipping'] === 0 ? 'Miễn phí' : checkout_money($totals['shipping'])) ?></strong></div>
        <div class="total-row saving"><span>Tiết kiệm</span><strong><?= $ready ? checkout_money($totals['discount'] + $totals['shipping_discount']) : '—' ?></strong></div>
        <div class="grand-total">
            <span>Tổng cộng</span>
            <div><span class="vnd">VND</span><strong><?= $ready ? checkout_money($totals['total']) : '—' ?></strong></div>
        </div>
    </div>
</div>
<script>
    const ready = <?= $ready ? 'true' : 'false' ?>;
    const paymentForm = document.getElementById('paymentForm');
    const completeBtn = document.getElementById('completeBtn');
    paymentForm.addEventListener('submit', function (event) {
        if (!ready || completeBtn.disabled) {
            event.preventDefault();
            return;
        }
        completeBtn.disabled = true;
        completeBtn.textContent = 'Đang xử lý đơn hàng...';
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { window.location.reload(); return; }
        completeBtn.disabled = !ready;
        completeBtn.textContent = 'Hoàn tất đơn hàng';
    });
</script>
</body>