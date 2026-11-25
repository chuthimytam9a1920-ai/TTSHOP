
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/validate_helpers.php';

// Kiểm tra đăng nhập
if (($_SESSION['is_login'] ?? false) !== true) {
    header('Location: dangky.php');
    exit();
}

// Kiểm tra giỏ hàng
$cart = $_SESSION['cart'] ?? [];

if (empty($cart) || !is_array($cart)) {
    header('Location: giohang.php');
    exit();
}

// Nhận dữ liệu từ trang thông tin giao hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $info = [];

    foreach ([
        'ho_ten',
        'sdt',
        'dia_chi',
        'tinh_thanh',
        'quan_huyen',
        'phuong_xa'
    ] as $field) {

        $value = $_POST[$field] ?? '';

        $info[$field] = is_string($value)
            ? trim($value)
            : '';
    }

    $_SESSION['checkout_info'] = $info;
}

// Lấy thông tin giao hàng
$checkout = $_SESSION['checkout_info'] ?? [];

if (empty($checkout)) {
    header('Location: thanhtoan.php');
    exit();
}

// Chuẩn hóa thông tin giao hàng
$checkout['ho_ten'] = trim($checkout['ho_ten'] ?? '');

$checkout['sdt'] = normalize_vn_phone(
    $checkout['sdt'] ?? ''
);

$checkout['dia_chi'] = trim(
    $checkout['dia_chi'] ?? ''
);

// Kiểm tra thông tin trước khi đặt hàng
$error = '';

if ($checkout['ho_ten'] === '') {

    $error = validation_message('recipient_required');

} elseif (!is_valid_vn_phone($checkout['sdt'])) {

    $error = phone_validation_message();

} elseif ($checkout['dia_chi'] === '') {

    $error = validation_message('address_required');
}

// Nếu thông tin không hợp lệ
if ($error !== '') {

    $_SESSION['checkout_error'] = $error;

    $_SESSION['checkout_old'] = $checkout;

    unset($_SESSION['checkout_info']);

    header('Location: thanhtoan.php');

    exit();
}

// Lưu lại thông tin đã chuẩn hóa
$_SESSION['checkout_info'] = $checkout;

// Lấy thông báo lỗi nếu có
$checkoutError = $_SESSION['checkout_error'] ?? '';

unset($_SESSION['checkout_error']);

// Phí vận chuyển mặc định
$shippingFee = 30000;

// Tính tổng tiền sản phẩm
$tongTien = 0;

foreach ($cart as $item) {

    $price = (int)($item['price'] ?? 0);

    $qty = max(1, (int)($item['qty'] ?? 1));

    $tongTien += $price * $qty;
}

// Tổng tiền cần thanh toán
$tongCong = $tongTien + $shippingFee;

// Hàm bảo vệ dữ liệu khi hiển thị HTML
function checkout_escape($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

// Hàm định dạng tiền Việt Nam
function checkout_money($value) {
    return number_format(
        $value,
        0,
        ',',
        '.'
    ) . 'đ';
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Phương thức thanh toán - TT SHOP</title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #333;
            background: #fff;
        }

        .checkout-page {
            display: grid;
            grid-template-columns: minmax(0,58fr) minmax(0,42fr);
            min-height: 100vh;
        }

        .checkout-left {
            padding: 55px 70px;
            border-right: 1px solid #ddd;
            min-width: 0;
        }

        .checkout-right {
            padding: 45px 60px;
            background: #fafafa;
            min-width: 0;
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
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            margin-bottom: 45px;
        }

        .step-link {
            color: #333;
            text-decoration: none;
        }

        .step-link:hover {
            color: #15985d;
        }

        .step-active {
            color: #111;
            font-weight: 800;
        }

        .arrow {
            color: #999;
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
        }

        .check-square {
            width: 18px;
            height: 18px;
            background: #15985d;
            display: grid;
            place-items: center;
            color: white;
        }

        .payment-row {
            background: #f0fff8;
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
            accent-color: #15985d;
        }

        .pay-icon {
            font-size: 28px;
            color: #15985d;
        }

        .ship-price {
            margin-left: auto;
            font-weight: 700;
        }

        .checkout-action {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 25px;
        }

        .back-cart {
            color: #333;
            text-decoration: none;
        }

        .complete-btn {
            background: #15985d;
            color: #fff;
            border: none;
            padding: 22px 38px;
            border-radius: 7px;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
        }

        .complete-btn:hover {
            background: #0d7d4b;
        }

        .complete-btn:disabled {
            opacity: .65;
            cursor: wait;
        }

        .error-box {
            color: #a12020;
            background: #fff0f0;
            padding: 12px;
            border-radius: 6px;
        }

        .delivery-info {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px 20px;
            overflow-wrap: anywhere;
            line-height: 1.6;
        }

        .delivery-info a {
            color: #15985d;
        }

        .product-item {
            display: grid;
            grid-template-columns: 85px minmax(0,1fr) auto;
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
            right: 0;
            background: #999;
            color: #fff;
            min-width: 25px;
            height: 25px;
            padding: 4px;
            border-radius: 2px;
            text-align: center;
            font-weight: bold;
        }

        .product-name,
        .product-price {
            font-size: 16px;
            font-weight: 700;
            line-height: 1.35;
        }

        .product-price {
            text-align: right;
            white-space: nowrap;
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
            min-width: 0;
            height: 56px;
            border: 1px solid #9bbcff;
            padding: 0 15px;
            font-size: 18px;
        }

        .coupon-box button {
            padding: 0 20px;
            background: #15985d;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 18px;
            font-weight: bold;
        }

        .coupon-box button:disabled {
            opacity: .6;
            cursor: not-allowed;
        }

        .coupon-note {
            color: #777;
            font-size: 14px;
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
            gap: 12px;
        }

        .grand-total {
            border-top: 1px solid #ddd;
            padding-top: 25px;
            margin-top: 25px;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
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

            .complete-btn {
                width: 100%;
            }

            .product-item {
                gap: 10px;
            }

        }

    </style>

</head>

<body>

<div class="checkout-page">

    <!-- CỘT BÊN TRÁI -->

    <div class="checkout-left">

        <a href="index.php" class="logo">
            TT SHOP
        </a>

        <!-- Thanh điều hướng -->

        <div class="breadcrumb">

            <a href="giohang.php" class="step-link">
                Giỏ hàng
            </a>

            <span class="arrow">›</span>

            <a href="thanhtoan.php" class="step-link">
                Thông tin giao hàng
            </a>

            <span class="arrow">›</span>

            <span class="step-active">
                Phương thức thanh toán
            </span>

        </div>

        <!-- Hiển thị lỗi nếu có -->

        <?php if ($checkoutError !== ''): ?>

            <p class="error-box" role="alert">
                <?= checkout_escape($checkoutError) ?>
            </p>

        <?php endif; ?>

        <!-- Hiển thị thông tin giao hàng -->

        <h2>Thông tin giao hàng</h2>

        <div class="delivery-info">

            <strong>
                <?= checkout_escape($checkout['ho_ten']) ?>
            </strong>

            <br>

            <?= checkout_escape($checkout['sdt']) ?>

            <br>

            <?= checkout_escape($checkout['dia_chi']) ?>

            <br>

            <a href="thanhtoan.php">
                Thay đổi thông tin
            </a>

        </div>

        <!-- FORM HOÀN TẤT ĐƠN HÀNG -->

        <form
            id="paymentForm"
            action="xuly_dathang.php"
            method="POST"
        >

            <!-- Phương thức vận chuyển -->

            <h2>Phương thức vận chuyển</h2>

            <div class="shipping-box">

                <div class="shipping-row">

                    <span class="check-square">✓</span>

                    <span>Giao hàng tận nơi</span>

                    <span class="ship-price">

                        <?= checkout_money($shippingFee) ?>

                    </span>

                </div>

            </div>

            <!-- Phương thức thanh toán -->

            <h2>Phương thức thanh toán</h2>

            <div class="payment-box">

                <div class="payment-row">

                    <label>

                        <input
                            type="radio"
                            name="phuong_thuc"
                            value="COD"
                            checked
                            required
                        >

                        <i
                            class="fas fa-money-bill-wave pay-icon"
                            aria-hidden="true"
                        ></i>

                        Thanh toán khi giao hàng (COD)

                    </label>

                </div>

            </div>

            <!-- Nút hoàn tất đơn hàng -->

            <div class="checkout-action">

                <a
                    href="giohang.php"
                    class="back-cart"
                >
                    Giỏ hàng
                </a>

                <button
                    type="submit"
                    id="completeBtn"
                    class="complete-btn"
                >
                    Hoàn tất đơn hàng
                </button>

            </div>

        </form>

    </div>

    <!-- CỘT BÊN PHẢI -->

    <div class="checkout-right">

        <!-- Danh sách sản phẩm -->

        <?php foreach ($cart as $item):

            $name = $item['name'] ?? '';

            $img = $item['img'] ?? '';

            $price = (int)($item['price'] ?? 0);

            $qty = max(1, (int)($item['qty'] ?? 1));

            $thanhTien = $price * $qty;

        ?>

            <div class="product-item">

                <div class="product-img">

                    <img
                        src="<?= checkout_escape($img) ?>"
                        alt="<?= checkout_escape($name) ?>"
                    >

                    <span class="qty-badge">
                        <?= $qty ?>
                    </span>

                </div>

                <div class="product-name">

                    <?= checkout_escape($name) ?>

                </div>

                <div class="product-price">

                    <?= checkout_money($thanhTien) ?>

                </div>

            </div>

        <?php endforeach; ?>

        <!-- Mã giảm giá -->

        <div class="coupon-box">

            <input
                type="text"
                placeholder="Mã giảm giá"
                disabled
            >

            <button type="button" disabled>
                Sử dụng
            </button>

        </div>

        <p class="coupon-note">
            Chức năng mã giảm giá chưa được hỗ trợ.
        </p>

        <!-- Khách hàng thân thiết -->

        <div class="loyal-title">

            Chương trình khách hàng thân thiết

        </div>

        <!-- Tạm tính -->

        <div class="total-row">

            <span>Tạm tính</span>

            <strong>

                <?= checkout_money($tongTien) ?>

            </strong>

        </div>

        <!-- Phí vận chuyển -->

        <div class="total-row">

            <span>Phí vận chuyển</span>

            <strong>

                <?= checkout_money($shippingFee) ?>

            </strong>

        </div>

        <!-- Tổng thanh toán -->

        <div class="grand-total">

            <span>Tổng cộng</span>

            <div>

                <span class="vnd">VND</span>

                <strong>

                    <?= checkout_money($tongCong) ?>

                </strong>

            </div>

        </div>

    </div>

</div>

<!-- Hạn chế việc bấm đặt hàng nhiều lần -->

<script>

    const paymentForm = document.getElementById('paymentForm');

    const completeBtn = document.getElementById('completeBtn');

    paymentForm.addEventListener('submit', function (event) {

        if (completeBtn.disabled) {

            event.preventDefault();

            return;
        }

        completeBtn.disabled = true;

        completeBtn.textContent = 'Đang xử lý đơn hàng...';

    });

    window.addEventListener('pageshow', function () {

        completeBtn.disabled = false;

        completeBtn.textContent = 'Hoàn tất đơn hàng';

    });

</script>

</body>

</html>
