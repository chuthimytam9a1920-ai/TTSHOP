<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "connect.php";

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$order_id = (int)($_GET['id'] ?? 0);

$sqlOrder = "
    SELECT *
    FROM donhang
    WHERE id = ?
    AND user_id = ?
";

$stmtOrder = mysqli_prepare($conn, $sqlOrder);
mysqli_stmt_bind_param($stmtOrder, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmtOrder);

$resultOrder = mysqli_stmt_get_result($stmtOrder);
$order = mysqli_fetch_assoc($resultOrder);

if (!$order) {
    die("Không tìm thấy đơn hàng.");
}

$sqlItems = "
    SELECT *
    FROM chitiet_donhang
    WHERE donhang_id = ?
";

$stmtItems = mysqli_prepare($conn, $sqlItems);
mysqli_stmt_bind_param($stmtItems, "i", $order_id);
mysqli_stmt_execute($stmtItems);

$resultItems = mysqli_stmt_get_result($stmtItems);

$sqlReview = "
    SELECT *
    FROM danhgia
    WHERE donhang_id = ?
    AND user_id = ?
    LIMIT 1
";

$stmtReview = mysqli_prepare($conn, $sqlReview);
mysqli_stmt_bind_param($stmtReview, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmtReview);

$resultReview = mysqli_stmt_get_result($stmtReview);
$review = mysqli_fetch_assoc($resultReview);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết đơn hàng - TT SHOP</title>

    <link rel="stylesheet" href="./style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./styletaikhoan.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            background: #e9f8ef;
            font-family: Arial, sans-serif;
        }

        .detail-order-wrapper {
            max-width: 1200px;
            margin: 40px auto;
            background: #fff;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 8px 20px rgba(0,0,0,.08);
        }

        .detail-order-title {
            color: #ff9500;
            font-size: 38px;
            font-weight: 900;
            margin-bottom: 25px;
        }

        .order-summary {
            background: #f5f5f5;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            line-height: 2;
            font-size: 18px;
        }

        .status-badge {
            display: inline-block;
            margin-top: 10px;
            background: #fff2cc;
            color: #d48806;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: bold;
        }

        .section-title {
            font-size: 26px;
            color: #12a36d;
            margin-bottom: 18px;
        }

        .order-product {
            display: grid;
            grid-template-columns: 120px 1fr 160px;
            gap: 20px;
            align-items: center;
            background: #fff;
            border: 1px solid #eee;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 16px;
            box-shadow: 0 4px 12px rgba(0,0,0,.05);
        }

        .order-product img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 12px;
            background: #f2f2f2;
        }

        .product-name {
            font-size: 21px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .product-meta {
            font-size: 17px;
            line-height: 1.8;
        }

        .product-total {
            text-align: right;
            color: #ff6600;
            font-size: 20px;
            font-weight: bold;
        }

        .grand-total {
            text-align: right;
            margin-top: 25px;
            font-size: 26px;
            color: #ff6600;
            font-weight: 900;
        }

        .review-box {
            margin-top: 35px;
            background: #fffaf0;
            border: 1px solid #ffe0a3;
            border-radius: 18px;
            padding: 25px;
        }

        .review-form label {
            display: block;
            font-size: 18px;
            font-weight: bold;
            margin: 15px 0 8px;
        }

        .star-select {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 6px;
        }

        .star-select input {
            display: none;
        }

        .star-select label {
            font-size: 42px;
            color: #ddd;
            cursor: pointer;
        }

        .star-select label:hover,
        .star-select label:hover ~ label,
        .star-select input:checked ~ label {
            color: #ffb400;
        }

        .review-form textarea {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 12px;
            padding: 14px;
            font-size: 16px;
            resize: vertical;
        }

        .submit-review-btn {
            margin-top: 15px;
            background: #ff9500;
            color: white;
            border: none;
            padding: 13px 24px;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }

        .review-done {
            background: white;
            border-radius: 14px;
            padding: 18px;
            font-size: 17px;
        }

        .review-stars {
            color: #ffb400;
            font-size: 32px;
            margin: 8px 0;
        }

        .back-btn {
            display: inline-block;
            margin-top: 30px;
            background: #12a36d;
            color: white;
            padding: 13px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>

<?php include "header.php"; ?>

<div class="detail-order-wrapper">

    <h1 class="detail-order-title">CHI TIẾT ĐƠN HÀNG</h1>

    <div class="order-summary">
        <p><b>Mã đơn:</b> <?php echo htmlspecialchars($order['ma_don']); ?></p>

        <p>
            <b>Ngày đặt:</b>
            <?php
            $ngayDat = $order['ngay_dat'] ?? $order['created_at'] ?? $order['ngay_tao'] ?? '';
            echo !empty($ngayDat) ? date("d/m/Y H:i", strtotime($ngayDat)) : 'Không có dữ liệu';
            ?>
        </p>

        <p><b>Người nhận:</b> <?php echo htmlspecialchars($order['ho_ten'] ?? ''); ?></p>
        <p><b>Số điện thoại:</b> <?php echo htmlspecialchars($order['sdt'] ?? ''); ?></p>
        <p><b>Địa chỉ:</b> <?php echo htmlspecialchars($order['dia_chi'] ?? ''); ?></p>
        <p><b>Thanh toán:</b> <?php echo htmlspecialchars($order['phuong_thuc'] ?? 'COD'); ?></p>

        <span class="status-badge">
            <?php echo htmlspecialchars($order['trang_thai'] ?? 'Chờ xác nhận'); ?>
        </span>
    </div>

    <h2 class="section-title">Sản phẩm đã mua</h2>

    <?php if (mysqli_num_rows($resultItems) == 0) { ?>

        <p>Đơn hàng này chưa có sản phẩm.</p>

    <?php } else { ?>

        <?php while ($item = mysqli_fetch_assoc($resultItems)) { ?>

            <div class="order-product">

                <img src="<?php echo htmlspecialchars($item['hinh_anh']); ?>" alt="Sản phẩm">

                <div>
                    <div class="product-name">
                        <?php echo htmlspecialchars($item['ten_san_pham']); ?>
                    </div>

                    <div class="product-meta">
                        <p>Giá: <b><?php echo number_format($item['gia'], 0, ',', '.'); ?>đ</b></p>
                        <p>Số lượng: <b><?php echo (int)$item['so_luong']; ?></b></p>
                    </div>
                </div>

                <div class="product-total">
                    <?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>đ
                </div>

            </div>

        <?php } ?>

    <?php } ?>

    <div class="grand-total">
        Tổng tiền: <?php echo number_format($order['tong_cong'], 0, ',', '.'); ?>đ
    </div>

    <?php if (($order['trang_thai'] ?? '') === 'Đã giao') { ?>

        <div class="review-box">
            <h2 class="section-title">Đánh giá đơn hàng</h2>

            <?php if ($review) { ?>

                <div class="review-done">
                    <p><b>Bạn đã đánh giá đơn hàng này:</b></p>

                    <div class="review-stars">
                        <?php echo str_repeat("★", (int)$review['so_sao']); ?>
                        <?php echo str_repeat("☆", 5 - (int)$review['so_sao']); ?>
                    </div>

                    <p><?php echo nl2br(htmlspecialchars($review['nhan_xet'])); ?></p>
                </div>

            <?php } else { ?>

                <form action="luudanhgia.php" method="POST" class="review-form">
                    <input type="hidden" name="donhang_id" value="<?php echo (int)$order_id; ?>">

                    <label>Số sao:</label>

                    <div class="star-select">
                        <input type="radio" name="so_sao" value="5" id="star5" required>
                        <label for="star5">★</label>

                        <input type="radio" name="so_sao" value="4" id="star4">
                        <label for="star4">★</label>

                        <input type="radio" name="so_sao" value="3" id="star3">
                        <label for="star3">★</label>

                        <input type="radio" name="so_sao" value="2" id="star2">
                        <label for="star2">★</label>

                        <input type="radio" name="so_sao" value="1" id="star1">
                        <label for="star1">★</label>
                    </div>

                    <label>Nhận xét:</label>
                    <textarea name="nhan_xet" rows="5" placeholder="Bạn cảm thấy sản phẩm như thế nào?" required></textarea>

                    <button type="submit" class="submit-review-btn">
                        Gửi đánh giá
                    </button>
                </form>

            <?php } ?>
        </div>

    <?php } ?>

    <a href="taikhoan.php?tab=donhang" class="back-btn">
        ← Quay lại tài khoản
    </a>

</div>

<?php include "footer.php"; ?>

</body>
</html>