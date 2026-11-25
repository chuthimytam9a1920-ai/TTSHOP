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

$user_id = $_SESSION['user_id'];
$email = $_SESSION['user_email'] ?? '';
$sqlUser = "SELECT * FROM users WHERE id = ?";
$stmtUser = mysqli_prepare($conn, $sqlUser);
mysqli_stmt_bind_param($stmtUser, "i", $user_id);
mysqli_stmt_execute($stmtUser);
$resultUser = mysqli_stmt_get_result($stmtUser);
$user = mysqli_fetch_assoc($resultUser);

$ho = $user['ho'] ?? '';
$ten = $user['ten'] ?? '';
$email = $user['email'] ?? '';
$sdt = $user['sdt'] ?? '';
$diachi = $user['diachi'] ?? '';

$fullName = trim($ho . ' ' . $ten);

$tongTien = 0;
foreach ($cart as $item) {
    $price = (int)($item['price'] ?? 0);
    $qty = (int)($item['qty'] ?? 1);
    $tongTien += $price * $qty;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thanh toán - TT SHOP</title>

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
    font-size: 15px;
    margin-bottom: 45px;
}

.breadcrumb a {
    color: #333;
    text-decoration: none;
}

.breadcrumb span {
    margin: 0 12px;
    color: #999;
}

.breadcrumb .active-step {
    font-weight: 800;
    color: #111;
}

.breadcrumb .inactive-step {
    color: #999;
    font-weight: 500;
}
        h2 {
            font-size: 26px;
            margin-bottom: 20px;
        }

        .user-box {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
        }

        .user-icon {
            width: 58px;
            height: 58px;
            background: #ddd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }

        .user-box p {
            margin: 4px 0;
            font-size: 16px;
        }

        .user-box a {
            color: #111;
            text-decoration: none;
        }

        .checkout-form input,
        .checkout-form select {
            width: 100%;
            height: 58px;
            border: 1px solid #ddd;
            padding: 0 15px;
            font-size: 17px;
            margin-bottom: 14px;
            box-sizing: border-box;
            background: #fff;
        }

        .checkout-form select:disabled {
            background: #f3f3f3;
            color: #999;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }

        .checkout-action {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 28px;
        }

        .back-cart {
            color: #333;
            text-decoration: none;
            font-size: 16px;
        }

        .next-btn {
            background: #15985d;
            color: #fff;
            border: none;
            padding: 22px 38px;
            border-radius: 7px;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
        }

        .next-btn:hover {
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

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="checkout-page">

    <div class="checkout-left">

<a href="index.php" class="logo">TT SHOP</a>
<div class="breadcrumb">

    <a href="giohang.php" class="step-link">
        Giỏ hàng
    </a>

    <span>›</span>

    <a href="thanhtoan.php" class="step-link">
        Thông tin giao hàng
    </a>

    <span>›</span>

    <span class="step-active">
        Phương thức thanh toán
    </span>

</div>
        <h2>Thông tin giao hàng</h2>

        <div class="user-box">
            <div class="user-icon">
                <i class="fas fa-user"></i>
            </div>

            <div>
                <p>
                    <?php echo htmlspecialchars($fullName); ?>
                    (<?php echo htmlspecialchars($email); ?>)
                </p>
                <p><a href="dangxuat.php">Đăng xuất</a></p>
            </div>
        </div>

        <form class="checkout-form" action="phuongthucthanhtoan.php" method="POST">

            <input 
                type="text" 
                name="ho_ten" 
                placeholder="Họ và tên" 
                value="<?php echo htmlspecialchars($fullName); ?>" 
                required
            >

            <input 
                type="text" 
                name="sdt" 
                placeholder="Số điện thoại" 
                value="<?php echo htmlspecialchars($sdt); ?>" 
                required
            >

            <input 
                type="text" 
                name="dia_chi" 
                placeholder="Địa chỉ cụ thể, số nhà, tên đường..." 
                value="<?php echo htmlspecialchars($diachi); ?>" 
                required
            >

            <div class="form-row">
                <select id="province" name="tinh_thanh" required>
                    <option value="">Chọn tỉnh / thành</option>
                </select>

                <select id="district" name="quan_huyen" required disabled>
                    <option value="">Chọn quận / huyện</option>
                </select>

                <select id="ward" name="phuong_xa" required disabled>
                    <option value="">Chọn phường / xã</option>
                </select>
            </div>

            <div class="checkout-action">
                <a href="giohang.php" class="back-cart">Giỏ hàng</a>

                <button type="submit" class="next-btn">
                    Tiếp tục đến phương thức thanh toán
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
            <strong>—</strong>
        </div>

        <div class="grand-total">
            <span>Tổng cộng</span>
            <div>
                <span class="vnd">VND</span>
                <strong><?php echo number_format($tongTien, 0, ',', '.'); ?>đ</strong>
            </div>
        </div>

    </div>

</div>

<script>
const provinceSelect = document.getElementById("province");
const districtSelect = document.getElementById("district");
const wardSelect = document.getElementById("ward");

function resetDistrict() {
    districtSelect.innerHTML = '<option value="">Chọn quận / huyện</option>';
    districtSelect.disabled = true;
}

function resetWard() {
    wardSelect.innerHTML = '<option value="">Chọn phường / xã</option>';
    wardSelect.disabled = true;
}

fetch("https://provinces.open-api.vn/api/p/")
    .then(response => response.json())
    .then(provinces => {
        provinces.forEach(province => {
            const option = document.createElement("option");
            option.value = province.name;
            option.textContent = province.name;
            option.dataset.code = province.code;
            provinceSelect.appendChild(option);
        });
    })
    .catch(() => {
        alert("Không tải được danh sách tỉnh/thành. Vui lòng kiểm tra mạng.");
    });

provinceSelect.addEventListener("change", function () {
    const provinceCode = this.options[this.selectedIndex].dataset.code;

    resetDistrict();
    resetWard();

    if (!provinceCode) return;

    fetch("https://provinces.open-api.vn/api/p/" + provinceCode + "?depth=2")
        .then(response => response.json())
        .then(data => {
            districtSelect.disabled = false;

            data.districts.forEach(district => {
                const option = document.createElement("option");
                option.value = district.name;
                option.textContent = district.name;
                option.dataset.code = district.code;
                districtSelect.appendChild(option);
            });
        })
        .catch(() => {
            alert("Không tải được danh sách quận/huyện.");
        });
});

districtSelect.addEventListener("change", function () {
    const districtCode = this.options[this.selectedIndex].dataset.code;

    resetWard();

    if (!districtCode) return;

    fetch("https://provinces.open-api.vn/api/d/" + districtCode + "?depth=2")
        .then(response => response.json())
        .then(data => {
            wardSelect.disabled = false;

            data.wards.forEach(ward => {
                const option = document.createElement("option");
                option.value = ward.name;
                option.textContent = ward.name;
                wardSelect.appendChild(option);
            });
        })
        .catch(() => {
            alert("Không tải được danh sách phường/xã.");
        });
});
</script>

</body>
</html>