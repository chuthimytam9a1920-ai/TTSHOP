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

if (empty($cart)) {
    header("Location: giohang.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['checkout_info'] = [
        'ho_ten'     => trim($_POST['ho_ten'] ?? ''),
        'sdt'        => trim($_POST['sdt'] ?? ''),
        'dia_chi'    => trim($_POST['dia_chi'] ?? ''),
        'tinh_thanh' => trim($_POST['tinh_thanh'] ?? ''),
        'quan_huyen' => trim($_POST['quan_huyen'] ?? ''),
        'phuong_xa'  => trim($_POST['phuong_xa'] ?? '')
    ];
}

if (empty($_SESSION['checkout_info'])) {
    header("Location: thanhtoan.php");
    exit();
}

$shippingFee = 30000;
$tongTien = 0;

foreach ($cart as $item) {
    $price = (int)($item['price'] ?? 0);
    $qty   = (int)($item['qty'] ?? 1);
    $tongTien += $price * $qty;
}

$tongCong = $tongTien + $shippingFee;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Phương thức thanh toán - TT SHOP</title>

    <link rel="stylesheet" href="./style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #fff;
            color: #333;
        }

        .checkout-page {
            display: grid;
            grid-template-columns: 58% 42%;
            min-height: 100vh;
        }

        .checkout-left {
            padding: 55px 70px;
            border-right: 1px solid #ddd;
        }

        .checkout-right {
            padding: 45px 60px;
            background: #fafafa;
        }

        .logo {
            display: inline-block;
            font-size: 42px;
            font-weight: 900;
            color: #15985d;
            margin-bottom: 45px;
            text-decoration: none;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            margin-bottom: 45px;
        }

        .step-link {
            color: #777;
            text-decoration: none;
            font-weight: 500;
        }

        .step-link:hover {
            color: #15985d;
        }

        .step-active {
            color: #111;
            font-weight: 800;
        }

        .arrow {
            color: #bbb;
        }

        h2 {
            font-size: 24px;
            margin: 28px 0 12px;
        }

        .shipping-box,
        .payment-box {
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .shipping-row,
        .payment-row {
            display: flex;
            align-items: center;
            gap: 15px;
            min-height: 72px;
            padding: 16px 22px;
            border-bottom: 1px solid #ddd;
            box-sizing: border-box;
        }

        .shipping-row:last-child,
        .payment-row:last-child {
            border-bottom: none;
        }

        .check-square {
            width: 18px;
            height: 18px;
            border: 1px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .check-square.active {
            background: #15985d;
            border-color: #15985d;
        }

        .check-square.active::after {
            content: "";
            width: 6px;
            height: 6px;
            background: #fff;
            display: block;
        }

        .payment-row label {
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
            cursor: pointer;
            font-size: 17px;
        }

        .payment-row input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .payment-row:has(input:checked) {
            background: #f0fff8;
        }

        .pay-icon {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .ship-price {
            margin-left: auto;
            font-weight: 700;
            font-size: 17px;
        }

        .checkout-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 25px;
        }

        .back-cart {
            color: #333;
            text-decoration: none;
            font-size: 16px;
        }

        .complete-btn {
            background: #15985d;
            color: #fff;
            border: none;
            padding: 22px 45px;
            border-radius: 7px;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
        }

        .complete-btn:hover {
            background: #0d7d4b;
        }

        .product-item {
            display: grid;
            grid-template-columns: 85px 1fr 100px;
            gap: 15px;
            align-items: center;
            padding-bottom: 18px;
            margin-bottom: 18px;
            border-bottom: 1px solid #e5e5e5;
        }

        .product-img {
            position: relative;
        }

        .product-img img {
            width: 78px;
            height: 78px;
            object-fit: contain;
            border: 1px solid #ddd;
        }

        .qty-badge {
            position: absolute;
            top: -10px;
            right: -5px;
            background: #999;
            color: #fff;
            width: 25px;
            height: 25px;
            border-radius: 2px;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-name {
            font-size: 16px;
            font-weight: 700;
            line-height: 1.35;
        }

        .product-price {
            font-size: 16px;
            text-align: right;
            font-weight: 700;
        }

        .coupon-box {
            display: flex;
            gap: 15px;
            border: 1px dashed #1d61ff;
            padding: 18px;
            margin: 25px 0 15px;
        }

        .coupon-box input {
            flex: 1;
            height: 56px;
            border: 1px solid #9bbcff;
            padding: 0 15px;
            font-size: 18px;
        }

        .coupon-box button {
            width: 120px;
            background: #15985d;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
        }

        .coupon-link {
            color: red;
            margin-bottom: 18px;
            font-size: 16px;
        }

        .voucher-list span {
            display: inline-block;
            border: 1px dashed red;
            color: red;
            padding: 9px 12px;
            margin: 5px 4px 5px 0;
            font-weight: bold;
        }

        .loyal-title {
            font-size: 24px;
            font-weight: 800;
            padding-top: 28px;
            margin-top: 25px;
            border-top: 1px solid #ddd;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin: 18px 0;
            font-size: 17px;
        }

        .grand-total {
            border-top: 1px solid #ddd;
            padding-top: 25px;
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 20px;
        }

        .grand-total strong {
            font-size: 30px;
        }

        .vnd {
            color: #999;
            font-size: 15px;
            margin-right: 8px;
        }

        @media (max-width: 900px) {
            .checkout-page {
                grid-template-columns: 1fr;
            }

            .checkout-left,
            .checkout-right {
                padding: 30px 20px;
            }
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

        <form action="xuly_dathang.php" method="POST">

            <h2>Phương thức vận chuyển</h2>

            <div class="shipping-box">
                <div class="shipping-row">
                    <span class="check-square active"></span>
                    <span>Giao hàng tận nơi</span>
                    <span class="ship-price"><?php echo number_format($shippingFee, 0, ',', '.'); ?>đ</span>
                </div>
            </div>

            <h2>Phương thức thanh toán</h2>

            <div class="payment-box">

                <div class="payment-row">
                    <label>
                        <input type="radio" name="phuong_thuc" value="COD" checked required>
                        <img src="./imagescategory/cod.png" class="pay-icon" onerror="this.style.display='none'">
                        Thanh toán khi giao hàng (COD)
                    </label>
                </div>

                <div class="payment-row">
                    <label>
                        <input type="radio" name="phuong_thuc" value="VNPAY" required>
                        <img src="./imagescategory/vnpay.png" class="pay-icon" onerror="this.style.display='none'">
                        Thẻ ATM/Visa/Master/JCB/QR Pay qua cổng VNPAY
                    </label>
                </div>

                <div class="payment-row">
                    <label>
                        <input type="radio" name="phuong_thuc" value="MOMO" required>
                        <img src="./imagescategory/momo.png" class="pay-icon" onerror="this.style.display='none'">
                        Ví MoMo
                    </label>
                </div>

                <div class="payment-row">
                    <label>
                        <input type="radio" name="phuong_thuc" value="ZALOPAY" required>
                        <img src="./imagescategory/zalopay.png" class="pay-icon" onerror="this.style.display='none'">
                        Ví Zalopay bằng QRCode
                    </label>
                </div>

                <div class="payment-row">
                    <label>
                        <input type="radio" name="phuong_thuc" value="SHOPEEPAY" required>
                        <img src="./imagescategory/shopeepay.png" class="pay-icon" onerror="this.style.display='none'">
                        Ví ShopeePay
                    </label>
                </div>

            </div>

            <input type="hidden" name="phi_van_chuyen" value="<?php echo $shippingFee; ?>">
            <input type="hidden" name="tong_tien" value="<?php echo $tongTien; ?>">
            <input type="hidden" name="tong_cong" value="<?php echo $tongCong; ?>">

            <div class="checkout-action">
                <a href="giohang.php" class="back-cart">Giỏ hàng</a>

                <button type="submit" class="complete-btn">
                    Hoàn tất đơn hàng
                </button>
            </div>

        </form>

    </div>

    <div class="checkout-right">

        <?php foreach ($cart as $item) {
            $name = $item['name'] ?? '';
            $img = $item['img'] ?? '';
            $price = (int)($item['price'] ?? 0);
            $qty = (int)($item['qty'] ?? 1);
            $thanhTien = $price * $qty;
        ?>
            <div class="product-item">
                <div class="product-img">
                    <img src="<?php echo htmlspecialchars($img); ?>" alt="">
                    <span class="qty-badge"><?php echo $qty; ?></span>
                </div>

                <div class="product-name">
                    <?php echo htmlspecialchars($name); ?>
                </div>

                <div class="product-price">
                    <?php echo number_format($thanhTien, 0, ',', '.'); ?>đ
                </div>
            </div>
        <?php } ?>

        <div class="coupon-box">
            <input type="text" placeholder="Mã giảm giá">
            <button type="button">Sử dụng</button>
        </div>

        <div class="coupon-link">
            🎟 Xem thêm mã giảm giá
        </div>

        <div class="voucher-list">
            <span>Giảm 180,000đ</span>
            <span>Giảm 600,000đ</span>
            <span>Giảm 300,000đ</span>
            <span>Giảm 140,000đ</span>
            <span>Giảm 50,000đ</span>
        </div>

        <div class="loyal-title">
            Chương trình khách hàng thân thiết
        </div>

        <div class="total-row">
            <span>Tạm tính</span>
            <strong><?php echo number_format($tongTien, 0, ',', '.'); ?>đ</strong>
        </div>

        <div class="total-row">
            <span>Phí vận chuyển</span>
            <strong><?php echo number_format($shippingFee, 0, ',', '.'); ?>đ</strong>
        </div>

        <div class="grand-total">
            <span>Tổng cộng</span>
            <div>
                <span class="vnd">VND</span>
                <strong><?php echo number_format($tongCong, 0, ',', '.'); ?>đ</strong>
            </div>
        </div>

    </div>

</div>

</body>
</html>