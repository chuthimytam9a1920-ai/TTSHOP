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