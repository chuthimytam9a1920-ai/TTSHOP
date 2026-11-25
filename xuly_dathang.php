<?php
// TT SHOP - Bước 8: xác nhận, tính lại và lưu đơn hàng COD.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validate_helpers.php';
require_once __DIR__ . '/voucher_helpers.php';
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
    if (($_POST['phuong_thuc'] ?? '') !== 'COD') {
        dh_fail(validation_message('payment_required'));
    }
    $reviewId = $_POST['review_id'] ?? '';
    if (!is_string($reviewId) || !preg_match('/^[a-f0-9]{64}$/D', $reviewId)) {
        dh_fail('Phiên xác nhận đơn hàng không hợp lệ. Vui lòng kiểm tra lại.');
    }
    // Mỗi mã review tạo đúng một mã đơn xác định, không phụ thuộc thời gian gửi lại.
    // 50 ký tự, vừa với cột donhang.ma_don VARCHAR(50).
    $ma_don = 'TT'.substr(hash('sha256', $userId.':'.$reviewId), 0, 48);
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
        $stmt = $conn->prepare('SELECT id, ma_don, ma_giam_gia, tien_giam_gia, tong_cong
            FROM donhang WHERE user_id = ? AND ma_don = ? LIMIT 1');
        try {
            $stmt->bind_param('is', $userId, $ma_don); $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
        } finally { $stmt->close(); }
        if ($existing) {
            $conn->commit(); $inTransaction = false;
            // Không xóa giỏ hàng mới nếu đây là request cũ được gửi lại.
            if ($matchesReview) dh_clear_checkout();
            $_SESSION['checkout_receipt'] = array_merge($existing,
                ['user_id'=>$userId,'email'=>$email,'created_at'=>time()]);
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
        $payment = 'COD'; $status = 'Chờ xử lý';
        $subtotal = (int)$v['subtotal']; $discount = (int)$v['discount'];
        $shipping = (int)$v['shipping']; $total = (int)$v['total'];
        $tier = $v['tier']; $savedCode = $code === '' ? null : $code;
        
        // Lưu đơn hàng, bao gồm các cột đã thêm từ update_voucher_fix.sql.
        $stmt = $conn->prepare('INSERT INTO donhang
            (user_id, ma_don, ho_ten, sdt, email, dia_chi, tinh_thanh, quan_huyen,
             phuong_xa, phuong_thuc, tam_tinh, phi_van_chuyen, tong_cong, trang_thai,
             ma_giam_gia, tien_giam_gia, hang_thanh_vien)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        try {
            $stmt->bind_param('isssssssssiiissis', $userId, $ma_don, $ho, $sdt, $email,
                $diachi, $tinh, $huyen, $xa, $payment, $subtotal, $shipping, $total,
                $status, $savedCode, $discount, $tier);
            $stmt->execute(); $orderId = (int)$conn->insert_id;
        } finally { $stmt->close(); }
        if ($orderId <= 0) throw new RuntimeException('Không xác định được ID đơn hàng.');

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
    <title>Đặt hàng thành công</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .success-box {
            text-align: center;
            width: min(560px, 100%);
            box-sizing: border-box;
            padding: 45px;
        }

        .check-icon {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            border: 5px solid #dff4d8;
            margin: 0 auto 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7fd56f;
            font-size: 82px;
            font-weight: bold;
        }

        .success-box h1 {
            font-size: 42px;
            color: #555;
            margin: 0 0 18px;
        }

        .success-box p {
            font-size: 23px;
            color: #666;
            line-height: 1.55;
            margin: 8px 0;
        }

        .order-code {
            color: #15985d;
            font-weight: bold;
            overflow-wrap: anywhere;
        }

        .ok-btn {
            margin-top: 32px;
            width: 130px;
            height: 58px;
            border: none;
            border-radius: 10px;
            background: #2458ff;
            color: #fff;
            font-size: 24px;
            font-weight: 800;
            cursor: pointer;
        }

        .ok-btn:hover {
            background: #0c42f2;
        }
    </style>
</head>

<body>

<div class="success-box">

    <div class="check-icon">✓</div>

    <h1>Thông báo</h1>

    <p>Đã đặt đơn hàng thành công!</p>

    <p>Cảm ơn quý khách đã mua hàng tại TT SHOP ❤️</p>

    <p>
        Mã đơn hàng:
        <span class="order-code"><?= dh_escape($ma_don) ?></span>
    </p>

    <?php if (!empty($receipt['ma_giam_gia'])): ?>
        <p>Mã giảm giá: <?= dh_escape($receipt['ma_giam_gia']) ?></p>
        <p>Giảm giá sản phẩm: <?= dh_money($receipt['tien_giam_gia']) ?></p>
    <?php endif; ?>
    <p>Tổng thanh toán: <strong><?= dh_money($receipt['tong_cong']) ?></strong></p>

    <button class="ok-btn" onclick="window.location.href='taikhoan.php'">
        Đồng ý
    </button>

</div>

</body>
</html>
