<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "connect.php";

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangky.php");
    exit();
}

$cart = $_SESSION['cart'] ?? [];
$checkout = $_SESSION['checkout_info'] ?? [];

if (empty($cart) || empty($checkout)) {
    header("Location: giohang.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;

$phuong_thuc = $_POST['phuong_thuc'] ?? 'COD';
$phi_van_chuyen = 30000;

$ho_ten     = $checkout['ho_ten'] ?? '';
$sdt        = $checkout['sdt'] ?? '';
$dia_chi    = $checkout['dia_chi'] ?? '';
$tinh_thanh = $checkout['tinh_thanh'] ?? '';
$quan_huyen = $checkout['quan_huyen'] ?? '';
$phuong_xa  = $checkout['phuong_xa'] ?? '';

$tam_tinh = 0;

foreach ($cart as $item) {
    $price = (int)($item['price'] ?? 0);
    $qty   = (int)($item['qty'] ?? 1);

    if ($qty < 1) {
        $qty = 1;
    }

    $tam_tinh += $price * $qty;
}

$tong_cong = $tam_tinh + $phi_van_chuyen;
$ma_don = "TT" . date("YmdHis") . rand(100, 999);
$trang_thai = "Chờ xử lý";

mysqli_begin_transaction($conn);

try {
    $sqlOrder = "INSERT INTO donhang (
        user_id,
        ma_don,
        ho_ten,
        sdt,
        dia_chi,
        tinh_thanh,
        quan_huyen,
        phuong_xa,
        phuong_thuc,
        tam_tinh,
        phi_van_chuyen,
        tong_cong,
        trang_thai
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmtOrder = mysqli_prepare($conn, $sqlOrder);

    if (!$stmtOrder) {
        throw new Exception("Lỗi prepare donhang: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param(
        $stmtOrder,
        "issssssssiiis",
        $user_id,
        $ma_don,
        $ho_ten,
        $sdt,
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
        throw new Exception("Lỗi lưu đơn hàng: " . mysqli_stmt_error($stmtOrder));
    }

    $donhang_id = mysqli_insert_id($conn);

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
        throw new Exception("Lỗi prepare chitiet_donhang: " . mysqli_error($conn));
    }

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
            throw new Exception("Lỗi lưu chi tiết đơn hàng: " . mysqli_stmt_error($stmtDetail));
        }
    }

    mysqli_commit($conn);

    unset($_SESSION['cart']);
    unset($_SESSION['checkout_info']);

} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Đặt hàng thất bại: " . $e->getMessage());
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
        <span class="order-code"><?php echo htmlspecialchars($ma_don); ?></span>
    </p>

    <button class="ok-btn" onclick="window.location.href='taikhoan.php'">
        OK
    </button>

</div>

</body>
</html>