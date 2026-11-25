<?php
include "check_admin.php";
include "../connect.php";

$where = [];

if (!empty($_GET['keyword'])) {
    $keyword = mysqli_real_escape_string($conn, trim($_GET['keyword']));
    $where[] = "(
        donhang.ma_don LIKE '%$keyword%'
        OR donhang.ho_ten LIKE '%$keyword%'
        OR donhang.sdt LIKE '%$keyword%'
    )";
}

if (!empty($_GET['status'])) {
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    $where[] = "donhang.trang_thai = '$status'";
}

if (!empty($_GET['from_date'])) {
    $from_date = mysqli_real_escape_string($conn, $_GET['from_date']);
    $where[] = "DATE(donhang.created_at) >= '$from_date'";
}

if (!empty($_GET['to_date'])) {
    $to_date = mysqli_real_escape_string($conn, $_GET['to_date']);
    $where[] = "DATE(donhang.created_at) <= '$to_date'";
}

$sql = "
    SELECT 
        donhang.*,
        COUNT(danhgia.id) AS so_danh_gia,
        MAX(danhgia.so_sao) AS so_sao,
        MAX(danhgia.nhan_xet) AS nhan_xet
    FROM donhang
    LEFT JOIN danhgia ON danhgia.donhang_id = donhang.id
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " GROUP BY donhang.id ORDER BY donhang.id DESC";

$orders = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý đơn hàng</title>

    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Arial;background:#f4f6f8}
        .layout{display:grid;grid-template-columns:260px minmax(0,1fr);min-height:100vh}
        .sidebar{background:#15985d;color:white;padding:25px 20px}
        .sidebar h2{font-size:30px;margin-bottom:35px}
        .sidebar a{display:block;color:white;text-decoration:none;padding:14px 12px;border-radius:8px;margin-bottom:8px;font-weight:bold}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.18)}
        .main{padding:30px;min-width:0}
        .topbar,.box{background:white;border-radius:14px;padding:22px;box-shadow:0 4px 14px rgba(0,0,0,.08)}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}

        .order-filter{display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
        .order-filter input,.order-filter select{
            height:40px;border:1px solid #ddd;border-radius:7px;padding:0 12px;font-size:14px;background:#f7f7f7;
        }
        .order-filter input[type="text"]{width:360px}
        .order-filter label{font-weight:bold;font-size:14px}
        .order-filter button,.order-filter a{
            height:40px;padding:0 16px;border-radius:7px;border:none;display:inline-flex;
            align-items:center;justify-content:center;text-decoration:none;font-weight:bold;cursor:pointer;
        }
        .order-filter button{background:#15985d;color:white}
        .order-filter a{background:#eee;color:#333}

        .table-wrap{width:100%;overflow-x:auto}
        table{width:100%;min-width:1250px;border-collapse:collapse}
        th,td{padding:13px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
        th{background:#f0f0f0}

        .status{
            display:inline-block;padding:6px 10px;border-radius:20px;font-weight:bold;font-size:13px;
            background:#fff0c7;color:#b66a00;white-space:nowrap;
        }

        .review-stars{color:#ffb400;font-size:20px;font-weight:bold;white-space:nowrap}
        .review-text{max-width:260px;line-height:1.5;color:#333}
        .no-review{color:#999;font-style:italic}

        .detail{color:#0b72e7;font-weight:bold;text-decoration:none}
        .delete{color:red;font-weight:bold;text-decoration:none}
    </style>
</head>

<body>

<div class="layout">

<?php
$page = "orders";
include "sidebar.php";
?>

    <main class="main">
        <div class="topbar">
            <h1>Quản lý đơn hàng</h1>
        </div>

        <div class="box">

            <form method="GET" class="order-filter">
                <select name="status">
                    <option value="">Tất cả</option>
                    <option value="Chờ xử lý" <?php echo ($_GET['status'] ?? '') == 'Chờ xử lý' ? 'selected' : ''; ?>>Chờ xử lý</option>
                    <option value="Chờ lấy hàng" <?php echo ($_GET['status'] ?? '') == 'Chờ lấy hàng' ? 'selected' : ''; ?>>Chờ lấy hàng</option>
                    <option value="Đang vận chuyển" <?php echo ($_GET['status'] ?? '') == 'Đang vận chuyển' ? 'selected' : ''; ?>>Đang vận chuyển</option>
                    <option value="Đã giao" <?php echo ($_GET['status'] ?? '') == 'Đã giao' ? 'selected' : ''; ?>>Đã giao</option>
                    <option value="Đã huỷ" <?php echo ($_GET['status'] ?? '') == 'Đã huỷ' ? 'selected' : ''; ?>>Đã huỷ</option>
                </select>

                <input 
                    type="text" 
                    name="keyword" 
                    placeholder="Tìm kiếm mã đơn, khách hàng, SĐT..." 
                    value="<?php echo htmlspecialchars($_GET['keyword'] ?? ''); ?>"
                >

                <label>Từ</label>
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($_GET['from_date'] ?? ''); ?>">

                <label>Đến</label>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($_GET['to_date'] ?? ''); ?>">

                <button type="submit">Lọc</button>
                <a href="orders.php">Làm mới</a>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>SĐT</th>
                            <th>Tổng tiền</th>
                            <th>Thanh toán</th>
                            <th>Trạng thái</th>
                            <th>Ngày đặt</th>
                            <th>Đánh giá</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($orders && mysqli_num_rows($orders) > 0) { ?>
                            <?php while ($row = mysqli_fetch_assoc($orders)) { ?>
                                <tr>
                                    <td><?php echo (int)$row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['ma_don']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ho_ten']); ?></td>
                                    <td><?php echo htmlspecialchars($row['sdt']); ?></td>
                                    <td><?php echo number_format($row['tong_cong'], 0, ',', '.'); ?>đ</td>
                                    <td><?php echo htmlspecialchars($row['phuong_thuc']); ?></td>

                                    <td>
                                        <span class="status">
                                            <?php echo htmlspecialchars($row['trang_thai']); ?>
                                        </span>
                                    </td>

                                    <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>

                                    <td>
                                        <?php if ((int)$row['so_danh_gia'] > 0) { ?>
                                            <div class="review-stars">
                                                <?php echo str_repeat("★", (int)$row['so_sao']); ?>
                                                <?php echo str_repeat("☆", 5 - (int)$row['so_sao']); ?>
                                            </div>

                                            <div class="review-text">
                                                <?php echo nl2br(htmlspecialchars($row['nhan_xet'])); ?>
                                            </div>
                                        <?php } else { ?>
                                            <span class="no-review">Chưa đánh giá</span>
                                        <?php } ?>
                                    </td>

                                    <td>
                                        <a class="detail" href="order_detail.php?id=<?php echo (int)$row['id']; ?>">Chi tiết</a>
                                        |
                                        <a class="delete" href="delete_order.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Xóa đơn hàng này?')">Xóa</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="10" style="text-align:center;">Không tìm thấy đơn hàng nào</td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</div>

</body>
</html>