<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Thông tin đơn hàng chỉ được trả về cho tài khoản sở hữu đơn.
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
$lookupUserId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);
if (($_SESSION['is_login'] ?? false) !== true || $lookupUserId === false) {
    $_SESSION['login_error'] = 'Vui lòng đăng nhập để kiểm tra đơn hàng của bạn.';
    header('Location: dangnhap.php', true, 303);
    exit();
}

require_once __DIR__ . '/connect.php';

$checkType = $_POST['check_type'] ?? 'phone';
$rawKeyword = $_POST['keyword'] ?? '';
$keyword = is_string($rawKeyword) ? trim($rawKeyword) : '';
$orders = [];
$lookupError = '';
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
if (!isset($_SESSION['order_lookup_csrf']) || !is_string($_SESSION['order_lookup_csrf'])) {
    $_SESSION['order_lookup_csrf'] = bin2hex(random_bytes(32));
}
$lookupCsrf = $_SESSION['order_lookup_csrf'];

if ($isPost) {
    $token = $_POST['csrf_token'] ?? null;
    if (!is_string($token) || !hash_equals($lookupCsrf, $token)) {
        http_response_code(403);
        $lookupError = 'Phiên kiểm tra không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } elseif (!in_array($checkType, ['phone', 'email'], true) || !is_string($rawKeyword)) {
        $lookupError = 'Thông tin kiểm tra không hợp lệ.';
    } elseif ($keyword === '') {
        $lookupError = 'Vui lòng nhập thông tin kiểm tra.';
    } elseif ($checkType === 'email' && (strlen($keyword) > 255 || !filter_var($keyword, FILTER_VALIDATE_EMAIL))) {
        $lookupError = 'Vui lòng nhập email hợp lệ.';
    } elseif ($checkType === 'phone' && (strlen($keyword) > 30 || !preg_match('/\A[0-9+(). -]{1,30}\z/', $keyword))) {
        $lookupError = 'Vui lòng nhập số điện thoại hợp lệ.';
    } else {
        // Không nhận user_id từ GET/POST. Cả hai truy vấn bắt buộc có user_id từ session.
        $sql = $checkType === 'email'
            ? 'SELECT id, ma_don, ho_ten, sdt, email, dia_chi, phuong_xa, quan_huyen, tinh_thanh, phuong_thuc, tong_cong, trang_thai, created_at FROM donhang WHERE user_id = ? AND email = ? ORDER BY id DESC'
            : 'SELECT id, ma_don, ho_ten, sdt, email, dia_chi, phuong_xa, quan_huyen, tinh_thanh, phuong_thuc, tong_cong, trang_thai, created_at FROM donhang WHERE user_id = ? AND sdt = ? ORDER BY id DESC';
        try {
            if ($checkType === 'email') {
                $keyword = strtolower($keyword);
            }
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'is', $lookupUserId, $keyword);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $orders[] = $row;
            }
            mysqli_stmt_close($stmt);

            if ($orders) {
                // Kiểm tra quyền sở hữu cả khi đọc chi tiết sản phẩm của đơn.
                $stmtItems = mysqli_prepare($conn, 'SELECT ct.hinh_anh, ct.ten_san_pham, ct.so_luong, ct.thanh_tien FROM chitiet_donhang ct INNER JOIN donhang dh ON dh.id = ct.donhang_id WHERE dh.user_id = ? AND dh.id = ? ORDER BY ct.id');
                foreach ($orders as &$order) {
                    $lookupOrderId = (int)$order['id'];
                    mysqli_stmt_bind_param($stmtItems, 'ii', $lookupUserId, $lookupOrderId);
                    mysqli_stmt_execute($stmtItems);
                    $itemsResult = mysqli_stmt_get_result($stmtItems);
                    $order['items'] = [];
                    while ($item = mysqli_fetch_assoc($itemsResult)) {
                        $order['items'][] = $item;
                    }
                    mysqli_free_result($itemsResult);
                }
                unset($order);
                mysqli_stmt_close($stmtItems);
            }
        } catch (Throwable $exception) {
            unset($order);
            $orders = [];
            $lookupError = 'Hệ thống đang bận. Vui lòng thử lại sau.';
            error_log('TT SHOP order lookup: ' . $exception->getMessage());
            http_response_code(503);
        }
    }
}
if (!in_array($checkType, ['phone', 'email'], true)) {
    $checkType = 'phone';
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
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($lookupCsrf, ENT_QUOTES, 'UTF-8'); ?>">

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
                maxlength="<?php echo $checkType === 'email' ? 255 : 30; ?>"
                value="<?php echo htmlspecialchars($keyword); ?>"
                required
            >
            <?php if ($lookupError !== '') { ?>
                <p class="error-msg"><?php echo htmlspecialchars($lookupError); ?></p>
            <?php } ?>

            <p class="help-text" style="margin: 12px 0;">
                <i class="fas fa-lock" aria-hidden="true"></i>
                Bạn chỉ có thể tra cứu đơn hàng thuộc tài khoản đang đăng nhập.
                <a href="donhang.php" style="color:inherit;text-decoration:underline;">Xem tất cả đơn hàng của tôi</a>.
            </p>

            <p class="help-text">
                Nếu quý khách có bất kỳ thắc mắc nào, xin vui lòng gọi
                <b>1900 888 999</b>
            </p>

            <button type="submit" class="check-btn">
                Xem ngay
            </button>

        </form>
    </div>

    <?php if ($isPost && $lookupError === '') { ?>

        <div class="check-result">

            <h2 class="check-result-title">KẾT QUẢ KIỂM TRA</h2>

            <?php if (empty($orders)) { ?>

                <div class="empty-check-result">
                    Không tìm thấy đơn hàng phù hợp trong tài khoản của bạn.
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

                            <?php if (empty($order['items'])) { ?>

                                <p>Đơn hàng này chưa có sản phẩm.</p>

                            <?php } else { ?>

                                <?php foreach ($order['items'] as $item) { ?>

                                    <div class="checked-product-item">

                                        <img src="<?php echo htmlspecialchars($item['hinh_anh'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" alt="">

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
        input.maxLength = 255;
        input.value = "";
    } else {
        label.innerText = "Số điện thoại:";
        input.type = "text";
        input.placeholder = "0909 xxx xxx";
        input.autocomplete = "tel";
        input.maxLength = 30;
        input.value = "";
    }
}
</script>

</body>
</html>