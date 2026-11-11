<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "connect.php";

$checkType = $_POST['check_type'] ?? 'phone';
$keyword   = trim($_POST['keyword'] ?? '');
$orders    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $keyword !== '') {

    if ($checkType === 'email') {
        $sql = "
            SELECT *
            FROM donhang
            WHERE email = ?
            ORDER BY id DESC
        ";
    } else {
        $sql = "
            SELECT *
            FROM donhang
            WHERE sdt = ?
            ORDER BY id DESC
        ";
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $keyword);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kiểm tra đơn hàng - TT SHOP</title>

    <link rel="stylesheet" href="./style.css?v=10000">
    <link rel="stylesheet" href="./stylecategory.css?v=10000">
    <link rel="stylesheet" href="./stylekiemtradonhang.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .check-result {
            width: 100%;
            max-width: 1050px;
            margin: 35px auto;
            background: #fff;
            border-radius: 18px;
            padding: 30px;
            box-shadow: 0 8px 22px rgba(0,0,0,.08);
        }

        .check-result-title {
            color: #ff9500;
            font-size: 32px;
            font-weight: 900;
            margin-bottom: 25px;
        }

        .empty-check-result {
            text-align: center;
            font-size: 18px;
            color: #555;
            padding: 35px 0;
        }

        .checked-order-card {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 5px 16px rgba(0,0,0,.07);
        }

        .checked-order-top {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            align-items: center;
            margin-bottom: 15px;
        }

        .checked-order-top h3 {
            color: #ff9500;
            font-size: 24px;
            margin: 0;
        }

        .checked-status {
            background: #fff2cc;
            color: #d48806;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: bold;
            white-space: nowrap;
        }

        .checked-order-info {
            line-height: 1.9;
            font-size: 17px;
        }

        .checked-total {
            color: #ff6600;
            font-size: 22px;
            font-weight: 900;
            margin: 15px 0;
        }

        .checked-products {
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
        }

        .checked-product-item {
            display: grid;
            grid-template-columns: 75px 1fr 130px;
            gap: 15px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f1f1;
        }

        .checked-product-item img {
            width: 75px;
            height: 75px;
            object-fit: cover;
            border-radius: 10px;
            background: #f3f3f3;
        }

        .checked-product-name {
            font-weight: bold;
            font-size: 17px;
        }

        .checked-product-price {
            text-align: right;
            color: #ff6600;
            font-weight: bold;
        }
    </style>
</head>

<body>

<?php include "header.php"; ?>

<section class="order-check-page">

    <div class="breadcrumb">
        <a href="index.php">Trang chủ</a> / 
        <b>Kiểm tra đơn hàng</b>
    </div>

    <div class="order-check-box">
        <form method="POST" class="order-form-card">

            <h2>
                <i class="fas fa-search"></i>
                Kiểm tra đơn hàng của bạn
            </h2>

            <label>Phương thức kiểm tra</label>

            <div class="check-options">
                <label>
                    <input 
                        type="radio" 
                        name="check_type" 
                        value="phone" 
                        <?php echo $checkType === 'phone' ? 'checked' : ''; ?>
                        onclick="changeCheckType('phone')"
                    >
                    Số điện thoại
                </label>

                <label>
                    <input 
                        type="radio" 
                        name="check_type" 
                        value="email" 
                        <?php echo $checkType === 'email' ? 'checked' : ''; ?>
                        onclick="changeCheckType('email')"
                    >
                    Email
                </label>
            </div>

            <label id="inputLabel">
                <?php echo $checkType === 'email' ? 'Email:' : 'Số điện thoại:'; ?>
            </label>

            <input 
                id="checkInput"
                name="keyword"
                type="<?php echo $checkType === 'email' ? 'email' : 'text'; ?>" 
                placeholder="<?php echo $checkType === 'email' ? 'email@gmail.com' : '0909 xxx xxx'; ?>"
                autocomplete="<?php echo $checkType === 'email' ? 'email' : 'tel'; ?>"
                value="<?php echo htmlspecialchars($keyword); ?>"
                required
            >

            <div class="captcha-box">
                <div class="captcha-left">
                    <label style="display:flex;align-items:center;gap:10px;margin:0;">
                        <input type="checkbox" required style="width:24px;height:24px;">
                        <span>Tôi không phải là người máy</span>
                    </label>
                </div>

                <div class="captcha-right">
                    <i class="fas fa-sync-alt"></i>
                    <small>reCAPTCHA</small>
                </div>
            </div>

            <p class="help-text">
                Nếu quý khách có bất kỳ thắc mắc nào, xin vui lòng gọi
                <b>1900 888 999</b>
            </p>

            <button type="submit" class="check-btn">
                Xem ngay
            </button>

        </form>
    </div>

    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST') { ?>

        <div class="check-result">

            <h2 class="check-result-title">KẾT QUẢ KIỂM TRA</h2>

            <?php if (empty($orders)) { ?>

                <div class="empty-check-result">
                    Không tìm thấy đơn hàng nào.
                </div>

            <?php } else { ?>

                <?php foreach ($orders as $order) { ?>

                    <div class="checked-order-card">

                        <div class="checked-order-top">
                            <h3>
                                Mã đơn:
                                <?php echo htmlspecialchars($order['ma_don']); ?>
                            </h3>

                            <span class="checked-status">
                                <?php echo htmlspecialchars($order['trang_thai'] ?? 'Chờ xử lý'); ?>
                            </span>
                        </div>

                        <div class="checked-order-info">

                            <p>
                                <b>Người nhận:</b>
                                <?php echo htmlspecialchars($order['ho_ten'] ?? ''); ?>
                            </p>

                            <p>
                                <b>Ngày đặt:</b>
                                <?php
                                if (!empty($order['created_at'])) {
                                    echo date("d/m/Y H:i", strtotime($order['created_at']));
                                } else {
                                    echo "Không có dữ liệu";
                                }
                                ?>
                            </p>

                            <p>
                                <b>Số điện thoại:</b>
                                <?php echo htmlspecialchars($order['sdt'] ?? ''); ?>
                            </p>

                            <p>
                                <b>Email:</b>
                                <?php echo !empty($order['email']) ? htmlspecialchars($order['email']) : 'Chưa có'; ?>
                            </p>

                            <p>
                                <b>Địa chỉ:</b>
                                <?php echo htmlspecialchars($order['dia_chi'] ?? ''); ?>,
                                <?php echo htmlspecialchars($order['phuong_xa'] ?? ''); ?>,
                                <?php echo htmlspecialchars($order['quan_huyen'] ?? ''); ?>,
                                <?php echo htmlspecialchars($order['tinh_thanh'] ?? ''); ?>
                            </p>

                            <p>
                                <b>Thanh toán:</b>
                                <?php echo htmlspecialchars($order['phuong_thuc'] ?? 'COD'); ?>
                            </p>

                        </div>

                        <div class="checked-total">
                            Tổng tiền:
                            <?php echo number_format($order['tong_cong'], 0, ',', '.'); ?>đ
                        </div>

                        <div class="checked-products">

                            <?php
                            $sqlItems = "
                                SELECT *
                                FROM chitiet_donhang
                                WHERE donhang_id = ?
                            ";

                            $stmtItems = mysqli_prepare($conn, $sqlItems);
                            mysqli_stmt_bind_param($stmtItems, "i", $order['id']);
                            mysqli_stmt_execute($stmtItems);
                            $resultItems = mysqli_stmt_get_result($stmtItems);
                            ?>

                            <?php if (mysqli_num_rows($resultItems) == 0) { ?>

                                <p>Đơn hàng này chưa có sản phẩm.</p>

                            <?php } else { ?>

                                <?php while ($item = mysqli_fetch_assoc($resultItems)) { ?>

                                    <div class="checked-product-item">

                                        <img src="<?php echo htmlspecialchars($item['hinh_anh']); ?>" alt="">

                                        <div>
                                            <div class="checked-product-name">
                                                <?php echo htmlspecialchars($item['ten_san_pham']); ?>
                                            </div>

                                            <div>
                                                Số lượng:
                                                <b><?php echo (int)$item['so_luong']; ?></b>
                                            </div>
                                        </div>

                                        <div class="checked-product-price">
                                            <?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>đ
                                        </div>

                                    </div>

                                <?php } ?>

                            <?php } ?>

                        </div>

                    </div>

                <?php } ?>

            <?php } ?>

        </div>

    <?php } ?>

</section>

<?php include "footer.php"; ?>

<script>
function changeCheckType(type) {
    const label = document.getElementById("inputLabel");
    const input = document.getElementById("checkInput");

    if (type === "email") {
        label.innerText = "Email:";
        input.type = "email";
        input.placeholder = "email@gmail.com";
        input.autocomplete = "email";
        input.value = "";
    } else {
        label.innerText = "Số điện thoại:";
        input.type = "text";
        input.placeholder = "0909 xxx xxx";
        input.autocomplete = "tel";
        input.value = "";
    }
}
</script>

</body>
</html>