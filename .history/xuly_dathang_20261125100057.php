
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "connect.php";
require_once __DIR__ . '/validate_helpers.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangky.php");
    exit();
}

// Kiểm tra phương thức gửi dữ liệu
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: phuongthucthanhtoan.php');
    exit();
}

// Lấy thông tin giỏ hàng và giao hàng
$cart = $_SESSION['cart'] ?? [];
$checkout = $_SESSION['checkout_info'] ?? [];

if (empty($cart) || empty($checkout)) {
    header("Location: giohang.php");
    exit();
}

// Lấy thông tin khách hàng
$user_id = $_SESSION['user_id'] ?? 0;

$ho_ten     = trim($checkout['ho_ten'] ?? '');
$sdt        = normalize_vn_phone($checkout['sdt'] ?? '');
$dia_chi    = trim($checkout['dia_chi'] ?? '');
$tinh_thanh = $checkout['tinh_thanh'] ?? '';
$quan_huyen = $checkout['quan_huyen'] ?? '';
$phuong_xa  = $checkout['phuong_xa'] ?? '';

// Kiểm tra thông tin giao hàng
if ($ho_ten === '' || !is_valid_vn_phone($sdt) || $dia_chi === '') {

    $_SESSION['checkout_error'] = $ho_ten === ''
        ? validation_message('recipient_required')
        : (!is_valid_vn_phone($sdt)
            ? phone_validation_message()
            : validation_message('address_required'));

    $_SESSION['checkout_old'] = $checkout;

    header('Location: thanhtoan.php');
    exit();
}

// Kiểm tra phương thức thanh toán
$phuong_thuc = $_POST['phuong_thuc'] ?? '';

if ($phuong_thuc !== 'COD') {
    $_SESSION['checkout_error'] = validation_message('payment_required');
    header('Location: phuongthucthanhtoan.php');
    exit();
}

// Tính phí vận chuyển
$phi_van_chuyen = 30000;

// Tính tổng tiền sản phẩm
$tam_tinh = 0;

foreach ($cart as $item) {

    $price = (int)($item['price'] ?? 0);
    $qty   = (int)($item['qty'] ?? 1);

    if ($qty < 1) {
        $qty = 1;
    }

    $tam_tinh += $price * $qty;
}

// Tính tổng giá trị đơn hàng
$tong_cong = $tam_tinh + $phi_van_chuyen;

// Tạo mã đơn hàng
$ma_don = "TT" . date("YmdHis") . rand(100, 999);

// Trạng thái đơn hàng mặc định
$trang_thai = "Chờ xử lý";

// Bắt đầu transaction
mysqli_begin_transaction($conn);

try {

    // Lấy email từ tài khoản khách hàng
    $stmtEmail = mysqli_prepare(
        $conn,
        "SELECT email FROM users WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmtEmail,
        "i",
        $user_id
    );

    mysqli_stmt_execute($stmtEmail);

    $account = mysqli_fetch_assoc(
        mysqli_stmt_get_result($stmtEmail)
    );

    mysqli_stmt_close($stmtEmail);

    // Kiểm tra tài khoản hợp lệ
    if (
        !$account ||
        empty($account['email']) ||
        (
            isset($_SESSION['user_email']) &&
            strcasecmp(
                $account['email'],
                $_SESSION['user_email']
            ) !== 0
        )
    ) {
        throw new RuntimeException(
            'Tài khoản khách hàng không hợp lệ.'
        );
    }

    $email = $account['email'];

    // Thêm đơn hàng vào database
    $sqlOrder = "INSERT INTO donhang (
        user_id,
        ma_don,
        ho_ten,
        sdt,
        email,
        dia_chi,
        tinh_thanh,
        quan_huyen,
        phuong_xa,
        phuong_thuc,
        tam_tinh,
        phi_van_chuyen,
        tong_cong,
        trang_thai
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmtOrder = mysqli_prepare($conn, $sqlOrder);

    if (!$stmtOrder) {
        throw new Exception(
            "Lỗi prepare donhang: " . mysqli_error($conn)
        );
    }

    mysqli_stmt_bind_param(
        $stmtOrder,
        "isssssssssiiis",
        $user_id,
        $ma_don,
        $ho_ten,
        $sdt,
        $email,
        $dia_chi,
        $tinh_thanh,
        $quan_huyen,
        $phuong_xa,
        $phuong_thuc,
        $tam_tinh,
        $phi_van_chuyen,
        $tong_cong,
        $trang_thai
    );

    if (!mysqli_stmt_execute($stmtOrder)) {
        throw new Exception(
            "Lỗi lưu đơn hàng: " .
            mysqli_stmt_error($stmtOrder)
        );
    }

    // Lấy ID đơn hàng vừa tạo
    $donhang_id = mysqli_insert_id($conn);

    // Thêm chi tiết đơn hàng
    $sqlDetail = "INSERT INTO chitiet_donhang (
        donhang_id,
        product_id,
        ten_san_pham,
        hinh_anh,
        gia,
        so_luong,
        thanh_tien
    ) VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmtDetail = mysqli_prepare($conn, $sqlDetail);

    if (!$stmtDetail) {
        throw new Exception(
            "Lỗi prepare chitiet_donhang: " .
            mysqli_error($conn)
        );
    }

    // Lưu từng sản phẩm trong giỏ hàng
    foreach ($cart as $item) {

        $product_id   = (int)($item['id'] ?? 0);
        $ten_san_pham = $item['name'] ?? '';
        $hinh_anh     = $item['img'] ?? '';
        $gia          = (int)($item['price'] ?? 0);
        $so_luong     = (int)($item['qty'] ?? 1);

        if ($so_luong < 1) {
            $so_luong = 1;
        }

        $thanh_tien = $gia * $so_luong;

        mysqli_stmt_bind_param(
            $stmtDetail,
            "iissiii",
            $donhang_id,
            $product_id,
            $ten_san_pham,
            $hinh_anh,
            $gia,
            $so_luong,
            $thanh_tien
        );

        if (!mysqli_stmt_execute($stmtDetail)) {
            throw new Exception(
                "Lỗi lưu chi tiết đơn hàng: " .
                mysqli_stmt_error($stmtDetail)
            );
        }
    }

    // Xác nhận lưu đơn hàng thành công
    mysqli_commit($conn);

    // Xóa giỏ hàng sau khi đặt hàng thành công
    unset($_SESSION['cart']);

    // Xóa thông tin thanh toán cũ
    unset(
        $_SESSION['checkout_info'],
        $_SESSION['checkout_old'],
        $_SESSION['checkout_error']
    );

} catch (Throwable $e) {

    // Hủy toàn bộ giao dịch nếu xảy ra lỗi
    mysqli_rollback($conn);

    // Ghi lỗi vào nhật ký PHP
    error_log(
        sprintf(
            '%s:%d: %s',
            __FILE__,
            __LINE__,
            $e->getMessage()
        )
    );

    $_SESSION['checkout_error'] =
        'Không lưu được đơn hàng. Vui lòng thử lại hoặc liên hệ hỗ trợ.';

    $_SESSION['checkout_old'] = $checkout;

    header('Location: thanhtoan.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <title>Đặt hàng thành công</title>

    <style>

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #ffffff;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .success-box {
            text-align: center;
            width: 560px;
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

        <span class="order-code">
            <?php echo htmlspecialchars($ma_don); ?>
        </span>
    </p>

    <button
        class="ok-btn"
        onclick="window.location.href='taikhoan.php'"
    >
        Đồng ý
    </button>

</div>

</body>

</html>
