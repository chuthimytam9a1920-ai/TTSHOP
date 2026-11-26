<?php
// TT SHOP - Bước 8: xác nhận, tính lại và lưu đơn hàng COD.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validate_helpers.php';
require_once __DIR__ . '/voucher_helpers.php';
require_once __DIR__ . '/payment_helpers.php';
header('Cache-Control: no-store');

function dh_text($v): string {
    return is_string($v) ? trim($v) : '';
}
function dh_escape($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function dh_money($v): string {
    return number_format((int)$v, 0, ',', '.') . 'đ';
}
function dh_redirect(string $url): void {
    header('Location: ' . $url, true, 303);
    exit;
}
function dh_fail(string $message, string $page = 'phuongthucthanhtoan.php'): void {
    $_SESSION['checkout_error'] = $message;
    $_SESSION['checkout_old'] = $_SESSION['checkout_info'] ?? [];
    unset($_SESSION['checkout_review']);
    dh_redirect($page);
}
function dh_delivery(array $info): array {
    foreach (['ho_ten','sdt','dia_chi','tinh_thanh','quan_huyen','phuong_xa'] as $field) {
        $info[$field] = dh_text($info[$field] ?? '');
    }
    $info['sdt'] = normalize_vn_phone($info['sdt']);
    return $info;
}
function dh_clear_checkout(): void {
    unset($_SESSION['cart'], $_SESSION['checkout_info'], $_SESSION['checkout_review'],
        $_SESSION['checkout_old'], $_SESSION['checkout_error'],
        $_SESSION['voucher_code'], $_SESSION['voucher_user_id']);
}
// Đọc lại giá và giữ khóa sản phẩm đến khi hoàn tất transaction.
function dh_refresh_cart(mysqli $conn, array $cart): array {
    if (!$cart) throw new DomainException('Giỏ hàng đang trống.');
    ksort($cart, SORT_STRING);
    $items = []; $reviewCart = []; $updated = []; $subtotal = 0;
    foreach ($cart as $key => $item) {
        $id = (string)$key;
        if (!is_array($item)) throw new DomainException('Sản phẩm không hợp lệ.');
        $qty = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT,
            ['options'=>['min_range'=>1,'max_range'=>2147483647]]);
        if ($qty === false) throw new DomainException('Số lượng sản phẩm không hợp lệ.');
        if (preg_match('/^cat_([1-9][0-9]{0,9})$/D', $id, $m)) {
            $table = 'category_products'; $pid = (int)$m[1];
        } elseif (preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
            $table = 'products'; $pid = (int)$id;
        } else throw new DomainException('Mã sản phẩm không hợp lệ.');
        if ($pid > 2147483647) throw new DomainException('Mã sản phẩm không hợp lệ.');
        // Tên bảng chỉ lấy từ hai giá trị cố định ở trên.
        $stmt = $conn->prepare("SELECT name, price, old_price, image FROM `$table` WHERE id = ? FOR UPDATE");
        try {
            $stmt->bind_param('i', $pid); $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        if (!$p || (int)$p['price'] <= 0) {
                       throw new DomainException('Có sản phẩm không còn bán hoặc giá không hợp lệ. Vui lòng cập nhật giỏ hàng.');
        }
        // Các bảng đơn hàng gốc lưu giá nguyên đồng; nhất quán với bước 7.
        $price = (int)$p['price'];
        if ($price > intdiv(2147483647 - 30000 - $subtotal, $qty)) {
            throw new DomainException('Tổng giá trị đơn hàng vượt giới hạn.');
        }
        $line = $price * $qty; $subtotal += $line;
        $name = (string)$p['name'];
        $image = $table === 'products' ? 'images/'.$p['image'] : (string)$p['image'];
        // Giữ quy ước dữ liệu cũ: product_id = 0 với sản phẩm danh mục.
        // catalog_id sẽ phân biệt chúng với sản phẩm từ bảng products.
        $items[] = ['legacy_id'=>$table === 'products' ? $pid : 0,
            'name'=>$name,'image'=>$image,'price'=>$price,'qty'=>$qty,'line'=>$line];
        $reviewCart[$id] = ['qty'=>$qty,'price'=>$price];
        $item['id']=$id; $item['name']=$name; $item['img']=$image;
        $item['price']=$price; $item['old_price']=(int)$p['old_price']; $item['qty']=$qty;
        $updated[$key]=$item;
    }
       ksort($reviewCart, SORT_STRING);
    $_SESSION['cart'] = $updated;
    return ['items'=>$items,'cart'=>$reviewCart,'subtotal'=>$subtotal];
}

// Chèn hoặc lấy ID danh mục đã đặt. Dùng UNIQUE(legacy_product_id, ten_san_pham)
// có sẵn trong ttshop_final.sql để tránh tạo trùng catalog.
function dh_catalog_id(mysqli $conn, int $legacy, string $name): int {
    $stmt = $conn->prepare('INSERT INTO ordered_product_catalog (legacy_product_id, ten_san_pham)
        VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)');
    try {
        $stmt->bind_param('is', $legacy, $name); $stmt->execute();
        $id = (int)$conn->insert_id;
    } finally { $stmt->close(); }
    if ($id <= 0) throw new RuntimeException('Không xác định được catalog_id.');
    return $id;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if (($_SESSION['is_login'] ?? false) !== true || $userId <= 0) dh_redirect('dangky.php');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET chỉ hiển thị biên nhận của chính tài khoản, tuyệt đối không tạo đơn.
if ($method === 'GET') {
    $receipt = $_SESSION['checkout_receipt'] ?? [];
    if (!is_array($receipt) || (int)($receipt['user_id'] ?? 0) !== $userId
        || empty($receipt['ma_don']) || (int)($receipt['created_at'] ?? 0) < time()-86400
        || !isset($_GET['success']) || !is_string($_GET['success'])
        || !hash_equals((string)$receipt['ma_don'], $_GET['success'])
        || strcasecmp(dh_text($_SESSION['user_email'] ?? ''), (string)($receipt['email'] ?? '')) !== 0) {
        dh_redirect(empty($_SESSION['cart']) ? 'giohang.php' : 'phuongthucthanhtoan.php');
    }
} elseif ($method === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang thanh toán.');
    }
    $chosen = $_POST['phuong_thuc'] ?? '';
    if (!in_array($chosen, ['COD','PAYOS'], true)) {
        dh_fail(validation_message('payment_required'));
    }
    $reviewId = $_POST['review_id'] ?? '';
    if (!is_string($reviewId) || !preg_match('/^[a-f0-9]{64}$/D', $reviewId)) {
        dh_fail('Phiên xác nhận đơn hàng không hợp lệ. Vui lòng kiểm tra lại.');
    }
    // Khóa chống đặt trùng tách khỏi mã đơn hàng hiển thị.
    // Giữ công thức cũ để nhận diện cả các đơn đã được chuyển đổi.
    $requestKey = 'TT'.substr(hash('sha256', $userId.':'.$reviewId), 0, 48);
    // Mã tạm chỉ tồn tại trong transaction, không hiển thị cho khách hàng.
    $ma_don = $requestKey;
        $review = $_SESSION['checkout_review'] ?? [];
    $matchesReview = is_array($review) && (int)($review['user_id'] ?? 0) === $userId
        && is_string($review['id'] ?? null) && hash_equals($review['id'], $reviewId);
    $conn = null; $inTransaction = false;
    $errorPage = 'phuongthucthanhtoan.php';
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        require __DIR__ . '/connect.php';
        // Đọc dữ liệu đã commit mới nhất, và giữ khóa tới cuối transaction.
        $conn->query('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $conn->begin_transaction(); $inTransaction = true;

        // Khóa hàng tài khoản để hai yêu cầu của cùng khách không tạo trùng đơn.
        $stmt = $conn->prepare('SELECT email FROM users WHERE id = ? FOR UPDATE');
        try {
            $stmt->bind_param('i', $userId); $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        $email = dh_text($_SESSION['user_email'] ?? '');
        if (!$account || $email === '' || strcasecmp($email, (string)$account['email']) !== 0) {
            $errorPage = 'dangky.php';
            throw new DomainException('Tài khoản không hợp lệ. Vui lòng đăng nhập lại.');
        }
        $email = (string)$account['email'];
        
        // Nếu request đã được lưu trước đó, trả lại biên nhận thay vì INSERT lần nữa.
        $stmt = $conn->prepare('SELECT id, ma_don, ma_giam_gia, tien_giam_gia, tong_cong, phuong_thuc
            FROM donhang WHERE user_id = ? AND checkout_request_key = ? LIMIT 1');
        try {
            $stmt->bind_param('is', $userId, $requestKey); $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        if ($existing) {
            $ma_don = (string)$existing['ma_don'];
            $conn->commit(); $inTransaction = false;
            // Không xóa giỏ hàng mới nếu đây là request cũ được gửi lại.
            if ($matchesReview) dh_clear_checkout();
            $_SESSION['checkout_receipt'] = array_merge($existing,
                ['user_id'=>$userId,'email'=>$email,'created_at'=>time()]);
            if (($existing['phuong_thuc'] ?? '') === 'PAYOS') dh_redirect('thanhtoan_qr.php?order_id='.(int)$existing['id']);
            dh_redirect('xuly_dathang.php?success='.rawurlencode($ma_don));
        }

        if (!$matchesReview || (int)($review['created_at'] ?? 0) < time()-1800) {
            throw new DomainException('Phiên xác nhận đã thay đổi hoặc hết hạn. Vui lòng kiểm tra lại đơn hàng.');
        }
        $cart = $_SESSION['cart'] ?? [];
        if (!is_array($cart) || !$cart) {
            $errorPage = 'giohang.php'; throw new DomainException('Giỏ hàng đang trống.');
        }
        $checkout = $_SESSION['checkout_info'] ?? [];
        if (!is_array($checkout) || !$checkout) {
            $errorPage = 'thanhtoan.php'; throw new DomainException('Vui lòng nhập thông tin giao hàng.');
        }
        $checkout = dh_delivery($checkout);
        if ($checkout['ho_ten'] === '' || !is_valid_vn_phone($checkout['sdt']) || $checkout['dia_chi'] === '') {
            $errorPage = 'thanhtoan.php';
            throw new DomainException($checkout['ho_ten'] === '' ? validation_message('recipient_required')
                : (!is_valid_vn_phone($checkout['sdt']) ? phone_validation_message() : validation_message('address_required')));
        }
        if (($review['delivery'] ?? null) !== $checkout) {
            throw new DomainException('Thông tin giao hàng đã thay đổi. Vui lòng xác nhận lại.');
        }
        $code = dh_text($_SESSION['voucher_code'] ?? '');
        if ($code !== '' && (int)($_SESSION['voucher_user_id'] ?? 0) !== $userId) {
            unset($_SESSION['voucher_code'], $_SESSION['voucher_user_id']);
            throw new DomainException('Mã giảm giá không thuộc tài khoản hiện tại. Vui lòng kiểm tra lại.');
        }
        if ($code !== ($review['code'] ?? null) || ($_POST['review_code'] ?? null) !== $code
            || ($_POST['review_total'] ?? null) !== (string)($review['total'] ?? '')) {
            throw new DomainException('Mã giảm giá hoặc tổng tiền đã thay đổi. Vui lòng xác nhận lại.');
        }
               // Giá và số lượng phải khớp với bản review ở bước 7.
        $fresh = dh_refresh_cart($conn, $cart);
        if ($fresh['cart'] !== ($review['cart'] ?? null)) {
            throw new DomainException('Giá hoặc số lượng sản phẩm đã thay đổi. Vui lòng kiểm tra lại đơn hàng.');
        }
        // true: khóa voucher và đọc lượt sử dụng bên trong transaction.
        $v = voucher_validate($conn, $userId, $code, $fresh['subtotal'], 30000, true);
        if (!$v['ok']) throw new DomainException($v['message'].' Vui lòng kiểm tra lại đơn hàng.');
        foreach (['subtotal','discount','shipping_discount','shipping','total'] as $field) {
            if (!isset($review[$field]) || (int)$review[$field] !== (int)$v[$field]) {
                throw new DomainException('Ưu đãi hoặc tổng thanh toán đã thay đổi. Vui lòng xác nhận lại.');
            }
        }
        if (($review['tier'] ?? null) !== $v['tier'] || ($v['voucher']['ma_code'] ?? '') !== $code) {
            throw new DomainException('Hạng thành viên hoặc mã giảm giá đã thay đổi. Vui lòng xác nhận lại.');
        }

        $ho = $checkout['ho_ten']; $sdt = $checkout['sdt']; $diachi = $checkout['dia_chi'];
        $tinh = $checkout['tinh_thanh']; $huyen = $checkout['quan_huyen']; $xa = $checkout['phuong_xa'];
        $payment = $chosen; $status = 'Chờ xử lý';
        if ($payment === 'PAYOS' && (!payos_ready() || (int)$v['total'] <= 0)) throw new DomainException('Thanh toán QR chưa sẵn sàng hoặc tổng tiền không hợp lệ. Vui lòng chọn COD.');
        $subtotal = (int)$v['subtotal']; $discount = (int)$v['discount'];
        $shipping = (int)$v['shipping']; $total = (int)$v['total'];
        $tier = $v['tier']; $savedCode = $code === '' ? null : $code;
        
        // Lưu đơn hàng, bao gồm các cột đã thêm từ update_voucher_fix.sql.
        $stmt = $conn->prepare('INSERT INTO donhang
            (user_id, ma_don, ho_ten, sdt, email, dia_chi, tinh_thanh, quan_huyen,
             phuong_xa, phuong_thuc, tam_tinh, phi_van_chuyen, tong_cong, trang_thai,
             ma_giam_gia, tien_giam_gia, hang_thanh_vien, checkout_request_key)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        try {
            $stmt->bind_param('isssssssssiiississ', $userId, $ma_don, $ho, $sdt, $email,
                $diachi, $tinh, $huyen, $xa, $payment, $subtotal, $shipping, $total,
                $status, $savedCode, $discount, $tier, $requestKey);
            $stmt->execute(); $orderId = (int)$conn->insert_id;
        } finally { $stmt->close(); }
        if ($orderId <= 0) throw new RuntimeException('Không xác định được ID đơn hàng.');
        if ($payment === 'PAYOS') {
            // Code allocated and committed before any call to the payment provider.
            $s = $conn->prepare("UPDATE donhang SET payment_status='PENDING',payos_order_code=? WHERE id=?");
            try {
                for ($attempt=0; $attempt<5; $attempt++) {
                    $payosCode=random_int(100000000,999999999); $s->bind_param('ii',$payosCode,$orderId);
                    try { $s->execute(); break; } catch (mysqli_sql_exception $ex) { if ($ex->getCode()!==1062 || $attempt===4) throw $ex; }
                }
            } finally { $s->close(); }
        }

        // TT + thời điểm tạo đơn + ID đơn (ít nhất 4 chữ số, không cắt ID lớn).
        // Dùng created_at của database; 4 chữ số phân biệt với mã cũ 3 số ngẫu nhiên.
        $stmt = $conn->prepare("UPDATE donhang SET ma_don = CONCAT('TT',
            DATE_FORMAT(created_at, '%Y%m%d%H%i%s'),
            LPAD(CAST(id AS CHAR), GREATEST(4, CHAR_LENGTH(CAST(id AS CHAR))), '0'))
            WHERE id = ?");
        try {
            $stmt->bind_param('i', $orderId); $stmt->execute();
            if ($stmt->affected_rows !== 1) throw new RuntimeException('Không thể tạo mã đơn hàng.');
        } finally { $stmt->close(); }
        $stmt = $conn->prepare('SELECT ma_don FROM donhang WHERE id = ?');
        try {
            $stmt->bind_param('i', $orderId); $stmt->execute();
            $savedOrder = $stmt->get_result()->fetch_assoc();
            if (!$savedOrder) throw new RuntimeException('Không tìm thấy mã đơn hàng.');
            $ma_don = (string)$savedOrder['ma_don'];
        } finally { $stmt->close(); }


        // Lưu chi tiết từng sản phẩm và catalog_id đúng khóa ngoại.
        $stmt = $conn->prepare('INSERT INTO chitiet_donhang
            (donhang_id, product_id, ten_san_pham, hinh_anh, gia, so_luong, thanh_tien, catalog_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        try {
            foreach ($fresh['items'] as $item) {
                $legacy = $item['legacy_id']; $name = $item['name']; $image = $item['image'];
                $price = $item['price']; $qty = $item['qty']; $line = $item['line'];
                $catalogId = dh_catalog_id($conn, $legacy, $name);
                $stmt->bind_param('iissiiii', $orderId, $legacy, $name, $image,
                    $price, $qty, $line, $catalogId);
                $stmt->execute();
            }
        } finally { $stmt->close(); }
       // Ghi nhận một lượt sử dụng voucher chỉ khi đơn hàng được lưu thành công.
        if ($v['voucher'] !== null) {
            $voucherId = (int)$v['voucher']['id'];
            $shipDiscount = (int)$v['shipping_discount'];
            $stmt = $conn->prepare('INSERT INTO lichsu_magiamgia
                (magiamgia_id, user_id, donhang_id, ma_code, tien_giam_gia, tien_giam_van_chuyen)
                VALUES (?, ?, ?, ?, ?, ?)');
            try {
                $stmt->bind_param('iiisii', $voucherId, $userId, $orderId, $savedCode, $discount, $shipDiscount);
                $stmt->execute();
            } finally { $stmt->close(); }
        }

        // Chỉ xóa giỏ hàng sau khi tất cả truy vấn đã COMMIT thành công.
        $conn->commit(); $inTransaction = false;
        $_SESSION['checkout_receipt'] = ['id'=>$orderId,'ma_don'=>$ma_don,
            'ma_giam_gia'=>$savedCode,'tien_giam_gia'=>$discount,'tong_cong'=>$total,
            'user_id'=>$userId,'email'=>$email,'created_at'=>time()];
        dh_clear_checkout();
        if ($payment === 'PAYOS') {
            // Order is already safely persisted. API failures never recreate the order.
            try {
                $url=payos_create($conn,payos_order($conn,$orderId,$userId));
                if ($url !== '') dh_redirect($url);
            } catch (Throwable $e) { error_log('TT SHOP payOS create: '.$e->getMessage()); }
            dh_redirect('thanhtoan_qr.php?order_id='.$orderId);
        }
        // POST/Redirect/GET: F5 trang thành công không gửi lại yêu cầu đặt hàng.
        dh_redirect('xuly_dathang.php?success='.rawurlencode($ma_don));
    } catch (Throwable $e) {
        if ($inTransaction && $conn instanceof mysqli) {
            try { $conn->rollback(); } catch (Throwable $rollbackError) {
                error_log('TT SHOP rollback: '.$rollbackError->getMessage());
            }
        }
        if (!($e instanceof DomainException)) error_log('TT SHOP xuly_dathang: '.$e->getMessage());
        dh_fail($e instanceof DomainException ? $e->getMessage()
            : 'Không thể lưu đơn hàng. Vui lòng kiểm tra lịch sử đơn hàng và thử lại.', $errorPage);
    }
} else {
    header('Allow: GET, POST'); http_response_code(405); exit;
}
$ma_don = (string)$receipt['ma_don'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt hàng thành công - TT SHOP</title>
    <link rel="stylesheet" href="./style.css?v=10">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body.tt-success-page {
            margin: 0;
            padding-top: var(--tt-header-height, 140px);
            background: #f3f7f5;
            color: #23352d;
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;
        }
        body.tt-success-page > header {
            position: fixed; top: 0; left: 0;
            width: 100%; z-index: 999999;
        }
        body.tt-success-page > header .sub-menu { transition: none; }
        .tt-success-page .tt-receipt-main {
            display: flex; align-items: center; justify-content: center;
            min-height: calc(100vh - var(--tt-header-height, 140px));
            padding: 36px 20px;
        }
        .tt-success-page .tt-receipt-card {
            width: 100%; max-width: 660px;
            background: #fff; border: 1px solid #dce9e2;
            border-radius: 20px; padding: 36px 42px;
            box-shadow: 0 12px 38px rgba(30, 78, 54, .09);
            text-align: center;
        }
        .tt-success-page .tt-receipt-icon {
            width: 84px; height: 84px; margin: 0 auto 20px;
            border-radius: 50%; background: #e7f7ee;
            color: #15985d; display: grid; place-items: center;
        }
        .tt-success-page .tt-receipt-card h1 {
            font-family: Arial, Helvetica, sans-serif;
            color: #08794f; font-size: 30px; font-weight: 700;
            line-height: 1.3; margin: 0 0 12px;
        }
        .tt-success-page .tt-receipt-intro {
            color: #65766d; font-size: 16px; line-height: 1.65;
            margin: 0 auto 24px;
        }
        .tt-success-page .tt-receipt-details {
            padding: 18px 20px; margin-bottom: 24px;
            background: #f7faf8; border: 1px solid #e2ebe5;
            border-radius: 12px; text-align: left;
        }
        .tt-success-page .tt-receipt-row {
            display: flex; justify-content: space-between; align-items: baseline;
            gap: 12px 20px; padding: 10px 0;
            font-size: 15px; line-height: 1.5;
        }
        .tt-success-page .tt-receipt-row dt { color: #596b61; flex-shrink: 0; }
        .tt-success-page .tt-receipt-row dd {
            margin: 0; text-align: right; min-width: 0; overflow-wrap: anywhere;
            font-weight: 700; color: #273b30;
        }
        .tt-success-page .tt-receipt-row .tt-receipt-code,
        .tt-success-page .tt-receipt-row .tt-receipt-saving { color: #08794f; }
        .tt-success-page .tt-receipt-total {
            border-top: 1px solid #dbe7de; margin-top: 8px; padding-top: 16px;
        }
        .tt-success-page .tt-receipt-total dt { font-size: 17px; font-weight: 700; color: #23352d; }
        .tt-success-page .tt-receipt-total dd { font-size: 25px; color: #008b50; }
        .tt-success-page .tt-receipt-actions {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;
        }
        .tt-success-page .tt-receipt-btn {
            display: inline-flex; justify-content: center; align-items: center;
            min-height: 46px; padding: 12px 24px; border-radius: 8px;
            border: 1px solid #15985d; text-decoration: none;
            font-family: Arial, Helvetica, sans-serif; font-size: 16px;
            font-weight: 700; line-height: 1.4; cursor: pointer;
        }
        .tt-success-page .tt-receipt-primary { color: #fff; background: #15985d; }
        .tt-success-page .tt-receipt-primary:hover { background: #0c794c; border-color: #0c794c; }
        .tt-success-page .tt-receipt-secondary { color: #08794f; background: #fff; }
        .tt-success-page .tt-receipt-secondary:hover { background: #eef8f2; }
        .tt-success-page .tt-receipt-btn:focus-visible { outline: 3px solid #79cfa2; outline-offset: 3px; }
        .tt-success-page .tt-receipt-note { margin-top: 18px; color: #65766d; font-size: 13px; line-height: 1.5; }
        @media (max-width: 600px) {
            .tt-success-page .tt-receipt-main { padding: 24px 12px; }
            .tt-success-page .tt-receipt-card { padding: 26px 18px; border-radius: 14px; }
            .tt-success-page .tt-receipt-card h1 { font-size: 25px; }
            .tt-success-page .tt-receipt-details { padding: 12px; }
            .tt-success-page .tt-receipt-row { gap: 10px; font-size: 14px; }
            .tt-success-page .tt-receipt-row dt { flex-shrink: 1; }
            .tt-success-page .tt-receipt-total dd { font-size: 22px; }
            .tt-success-page .tt-receipt-btn { width: 100%; }
        }
    </style>
</head>
<body class="tt-success-page">
<?php include __DIR__ . '/header.php'; ?>
<main class="tt-receipt-main">
    <section class="tt-receipt-card" aria-labelledby="tt-receipt-heading">
        <div class="tt-receipt-icon" aria-hidden="true">
            <svg width="46" height="46" viewBox="0 0 48 48" fill="none">
                <path d="M11 24L20 33L37 15" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <h1 id="tt-receipt-heading">Đặt hàng thành công!</h1>
        <p class="tt-receipt-intro">Cảm ơn quý khách đã mua sắm tại TT SHOP.<br>Đơn hàng của quý khách đã được ghi nhận.</p>
        <dl class="tt-receipt-details">
            <div class="tt-receipt-row">
                <dt>Mã đơn hàng</dt>
                <dd class="tt-receipt-code"><?= dh_escape($ma_don) ?></dd>
            </div>
            <div class="tt-receipt-row">
                <dt>Phương thức thanh toán</dt>
                <dd>COD</dd>
            </div>
            <?php if (!empty($receipt['ma_giam_gia'])): ?>
                <div class="tt-receipt-row">
                    <dt>Mã giảm giá</dt>
                    <dd class="tt-receipt-code"><?= dh_escape($receipt['ma_giam_gia']) ?></dd>
                </div>
                <div class="tt-receipt-row">
                    <dt>Giảm giá sản phẩm</dt>
                    <dd class="tt-receipt-saving">−<?= dh_money($receipt['tien_giam_gia']) ?></dd>
                </div>
            <?php endif; ?>
            <div class="tt-receipt-row tt-receipt-total">
                <dt>Tổng thanh toán</dt>
                <dd><?= dh_money($receipt['tong_cong']) ?></dd>
            </div>
        </dl>
        <div class="tt-receipt-actions">
            <a class="tt-receipt-btn tt-receipt-secondary" href="index.php">Tiếp tục mua sắm</a>
            <a class="tt-receipt-btn tt-receipt-primary" href="taikhoan.php">Đồng ý</a>
        </div>
        <p class="tt-receipt-note">Quý khách có thể theo dõi trạng thái đơn hàng trong tài khoản.</p>
    </section>
</main>
<script>
(function () {
    const header = document.querySelector('body > header');
    if (!header) return;
    function updateHeaderHeight() {
        document.body.style.setProperty('--tt-header-height', header.offsetHeight + 'px');
    }
    updateHeaderHeight();
    window.addEventListener('resize', updateHeaderHeight);
    window.addEventListener('load', updateHeaderHeight);
    if ('ResizeObserver' in window) {
        new ResizeObserver(updateHeaderHeight).observe(header);
    }
})();
</script>
</body>
</html>
