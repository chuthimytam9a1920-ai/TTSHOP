<?php
// Kiểm tra và khởi chạy session nếu chưa được bật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra bảo mật: Nếu khách chưa đăng nhập, lập tức đá về trang đăng nhập
if (($_SESSION['is_login'] ?? false) !== true || (int)($_SESSION['user_id'] ?? 0) <= 0) {
    header("Location: dangnhap.php");
    exit();
}

require_once __DIR__ . "/connect.php";

// Lấy ID của người dùng đang đăng nhập (ép kiểu về số nguyên để an toàn dữ liệu)
$user_id = (int)$_SESSION['user_id'];

// Chuẩn bị câu lệnh SQL lấy toàn bộ đơn hàng của user này, sắp xếp đơn hàng mới nhất lên đầu (ORDER BY id DESC)
$sql = "
    SELECT id, ma_don, created_at, tong_cong, phuong_thuc, trang_thai
    FROM donhang
    WHERE user_id = ?
    ORDER BY id DESC
";

// Thực thi câu lệnh SQL sử dụng Prepared Statement nhằm chống lỗi bảo mật SQL Injection
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

// Lấy bộ kết quả trả về từ cơ sở dữ liệu lưu vào biến $result để sẵn sàng lặp hiển thị ra giao diện HTML
$result = mysqli_stmt_get_result($stmt);
?>
<div class="order-box">
    <h1>ĐƠN HÀNG CỦA BẠN</h1>

    <?php if (mysqli_num_rows($result) == 0) { ?>

        <div class="empty-order">
            <div class="cart-icon">🛒</div>
            <p>Bạn chưa có đơn hàng nào.</p>
            <a href="index.php">Tiếp tục mua sắm</a>
        </div>

    <?php } else { ?>

        <div class="order-table-scroll">
        <table class="order-table">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Ngày đặt</th>
                    <th>Tổng tiền</th>
                    <th>Thanh toán</th>
                    <th>Trạng thái</th>
                    <th>Chi tiết</th>
                </tr>
            </thead>
                        <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                    <tr>
                        <td>
                            <strong>
                                <?php echo htmlspecialchars($row['ma_don'], ENT_QUOTES, 'UTF-8'); ?>
                            </strong>
                        </td>

                        <td>
                            <?php
                            $ngayDat = $row['created_at'] ?? '';
                            $timestamp = $ngayDat !== '' ? strtotime($ngayDat) : false;
                            echo $timestamp !== false ? date("d/m/Y H:i", $timestamp) : '—';
                            ?>
                        </td>

                        <td class="price">
                            <?php echo number_format($row['tong_cong'], 0, ',', '.'); ?>đ
                        </td>

                        <td>
                            <?php echo htmlspecialchars($row['phuong_thuc'], ENT_QUOTES, 'UTF-8'); ?>
                        </td>

                        <td>
                            <span class="status">
                                <?php echo htmlspecialchars($row['trang_thai'] ?? 'Chờ xử lý', ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </td>

                        <td>
                            <a class="detail-btn" href="chitietdonhang.php?id=<?php echo (int)$row['id']; ?>">
                                Xem chi tiết
                            </a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>

    <?php } ?>
</div>
<?php mysqli_stmt_close($stmt); ?>

<style>
.order-box {
    background: #f1f1f1;
    border-radius: 14px;
    padding: 35px;
    min-height: 420px;
}
.order-box h1 {
    color: #ff9500;
    font-size: 36px;
    margin-bottom: 35px;
}

.empty-order {
    text-align: center;
    padding: 60px 0;
}

.empty-order .cart-icon {
    font-size: 45px;
    opacity: .25;
}

.empty-order p {
    font-size: 20px;
    margin: 15px 0;
}

.empty-order a {
    display: inline-block;
    background: #3155ff;
    color: white;
    padding: 16px 38px;
    border-radius: 30px;
    text-decoration: none;
    font-size: 20px;
    font-weight: bold;
}

.order-box .order-table-scroll {
    overflow-x: auto;
    border-radius: 12px;
}

.order-table {
    min-width: 800px;
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
}

.order-table th {
    background: #12a36d;
    color: white;
    padding: 15px;
    font-size: 17px;
}

.order-table td {
    padding: 16px;
    text-align: center;
    border-bottom: 1px solid #eee;
    font-size: 16px;
}

.price {
    color: #ff6600;
    font-weight: bold;
}

.order-box .status {
    display: inline-block;
    white-space: nowrap;
    background: #fff2cc;
    color: #d48806;
    padding: 7px 14px;
    border-radius: 20px;
    font-weight: bold;
}

.order-box .detail-btn {
    display: inline-block;
    white-space: nowrap;
    background: #ffd84d;
    color: #3155ff;
    padding: 9px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

.order-box .detail-btn:hover {
    background: #ff9500;
    color: white;
}
</style>