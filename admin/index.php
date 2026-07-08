<?php
include "check_admin.php";
include "../connect.php";

$productCount = 0;
$userCount = 0;
$orderCount = 0;
$revenue = 0;

/* Tổng sản phẩm */
$result = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM products
");

if ($row = mysqli_fetch_assoc($result)) {
    $productCount = $row['total'];
}

/* Tổng khách hàng */
$result = mysqli_query($conn, "
    SELECT COUNT(*) AS total 
    FROM users
");

if ($row = mysqli_fetch_assoc($result)) {
    $userCount = $row['total'];
}

/* Tổng đơn hàng */

$result = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM donhang
    WHERE trang_thai = 'Đã giao'
");

if ($row = mysqli_fetch_assoc($result)) {
    $orderCount = (int)$row['total'];
}
/* Doanh thu */
$result = mysqli_query($conn, "
    SELECT COALESCE(SUM(tong_cong), 0) AS total
    FROM donhang
    WHERE trang_thai = 'Đã giao'
");

if ($row = mysqli_fetch_assoc($result)) {
    $revenue = $row['total'] ?? 0;
}
/* Đơn hàng mới nhất */
$recentOrders = mysqli_query($conn, "
    SELECT * 
    FROM donhang
    ORDER BY id DESC
    LIMIT 8
");
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - TT SHOP</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
        }

        .admin-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: #15985d;
            color: white;
            padding: 25px 20px;
        }

        .sidebar h2 {
            font-size: 30px;
            margin-bottom: 35px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 14px 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255, 255, 255, 0.18);
        }

        .main {
            padding: 30px;
        }

        .topbar {
            background: white;
            padding: 20px 25px;
            border-radius: 14px;
            margin-bottom: 25px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar h1 {
            margin: 0;
            color: #333;
        }

        .logout {
            color: red;
            font-weight: bold;
            text-decoration: none;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        .card p {
            margin: 0;
            color: #777;
            font-size: 16px;
        }

        .card h3 {
            margin: 12px 0 0;
            font-size: 30px;
            color: #15985d;
        }

        .table-box {
            background: white;
            border-radius: 14px;
            padding: 25px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f0f0f0;
            text-align: left;
        }

        th,
        td {
            padding: 14px;
            border-bottom: 1px solid #eee;
        }

        .status {
            background: #fff3cd;
            color: #856404;
            padding: 6px 10px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
        }

        @media (max-width: 1000px) {
            .admin-layout {
                grid-template-columns: 1fr;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>

<body>

<div class="admin-layout">

    <?php
    $page = "dashboard";
    include "sidebar.php";
    ?>

    <main class="main">

        <div class="topbar">
            <h1>Dashboard</h1>

            <div>
                Xin chào,
                <b><?php echo htmlspecialchars($_SESSION['admin_name']); ?></b>
                |
                <a href="logout.php" class="logout">Đăng xuất</a>
            </div>
        </div>

        <div class="stats">

            <div class="card">
                <p>Tổng sản phẩm</p>
                <h3><?php echo $productCount; ?></h3>
            </div>

            <div class="card">
                <p>Tổng đơn hàng</p>
                <h3><?php echo $orderCount; ?></h3>
            </div>

            <div class="card">
                <p>Khách hàng</p>
                <h3><?php echo $userCount; ?></h3>
            </div>

            <div class="card">
                <p>Doanh thu</p>

                <h3>
                    <?php echo number_format($revenue, 0, ',', '.'); ?>đ
                </h3>
            </div>

        </div>

        <div class="table-box">

            <h2>Đơn hàng mới nhất</h2>

            <table>

                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>SĐT</th>
                        <th>Tổng tiền</th>
                        <th>Thanh toán</th>
                        <th>Trạng thái</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($recentOrders && mysqli_num_rows($recentOrders) > 0) { ?>

                        <?php while ($order = mysqli_fetch_assoc($recentOrders)) { ?>

                            <tr>

                                <td>
                                    <?php echo htmlspecialchars($order['ma_don']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($order['ho_ten']); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($order['sdt']); ?>
                                </td>

                                <td>
                                    <?php echo number_format($order['tong_cong'], 0, ',', '.'); ?>đ
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($order['phuong_thuc']); ?>
                                </td>

                                <td>
                                    <span class="status">
                                        <?php echo htmlspecialchars($order['trang_thai']); ?>
                                    </span>
                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="6" style="text-align:center;">
                                Chưa có đơn hàng nào
                            </td>
                        </tr>

                    <?php } ?>

                </tbody>

            </table>

        </div>

    </main>

</div>

</body>
</html>