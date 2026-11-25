<?php
include "check_admin.php";
include "../connect.php";

/* ===== THỐNG KÊ ===== */

/* Tổng sản phẩm */
$sqlProduct = "SELECT COUNT(*) AS total FROM products";
$resultProduct = mysqli_query($conn, $sqlProduct);
$totalProduct = mysqli_fetch_assoc($resultProduct)['total'] ?? 0;

/* Tổng khách hàng */
$sqlUser = "SELECT COUNT(*) AS total FROM users";
$resultUser = mysqli_query($conn, $sqlUser);
$totalUser = mysqli_fetch_assoc($resultUser)['total'] ?? 0;

/* Tổng đơn hàng */
$sqlOrder = "SELECT COUNT(*) AS total FROM donhang";
$resultOrder = mysqli_query($conn, $sqlOrder);
$totalOrder = mysqli_fetch_assoc($resultOrder)['total'] ?? 0;

/* Doanh thu */
$sqlRevenue = "
    SELECT COALESCE(SUM(tong_cong),0) AS total
    FROM donhang
    WHERE trang_thai = 'Đã giao'
";
$resultRevenue = mysqli_query($conn, $sqlRevenue);
$totalRevenue = mysqli_fetch_assoc($resultRevenue)['total'] ?? 0;

/* Đơn mới */
$sqlNewOrder = "
    SELECT *
    FROM donhang
    ORDER BY id DESC
    LIMIT 5
";
$resultNewOrder = mysqli_query($conn, $sqlNewOrder);

?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - TT SHOP</title>

    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial;
        }

        body{
            background:#f4f7fb;
        }

        .wrapper{
            display:flex;
            min-height:100vh;
        }

        /* SIDEBAR */

        .sidebar{
            width:260px;
            background:#0f172a;
            color:white;
            padding:25px 20px;
        }

        .logo{
            font-size:30px;
            font-weight:900;
            margin-bottom:40px;
            text-align:center;
        }

        .menu{
            display:flex;
            flex-direction:column;
            gap:14px;
        }

        .menu a{
            color:white;
            text-decoration:none;
            padding:14px 16px;
            border-radius:12px;
            transition:.25s;
            font-weight:600;
        }

        .menu a:hover{
            background:#1e293b;
        }

        .menu i{
            width:24px;
        }

        /* CONTENT */

        .content{
            flex:1;
            padding:35px;
        }

        .top-title{
            font-size:34px;
            font-weight:900;
            margin-bottom:30px;
            color:#111827;
        }

        /* CARD */

        .card-grid{
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:22px;
            margin-bottom:40px;
        }

        .card{
            background:white;
            border-radius:20px;
            padding:28px;
            box-shadow:0 8px 24px rgba(0,0,0,.08);
        }

        .card-top{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:18px;
        }

        .card-title{
            color:#6b7280;
            font-size:15px;
            font-weight:700;
        }

        .card-icon{
            width:58px;
            height:58px;
            border-radius:16px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:24px;
            color:white;
        }

        .blue{
            background:#2563eb;
        }

        .green{
            background:#10b981;
        }

        .orange{
            background:#f59e0b;
        }

        .red{
            background:#ef4444;
        }

        .card-number{
            font-size:34px;
            font-weight:900;
            color:#111827;
        }

        /* TABLE */

        .table-box{
            background:white;
            border-radius:20px;
            padding:25px;
            box-shadow:0 8px 24px rgba(0,0,0,.08);
        }

        .table-title{
            font-size:24px;
            font-weight:800;
            margin-bottom:20px;
        }

        table{
            width:100%;
            border-collapse:collapse;
        }

        table th{
            background:#f3f4f6;
            padding:15px;
            text-align:left;
            font-size:15px;
        }

        table td{
            padding:16px;
            border-bottom:1px solid #eee;
        }

        .status{
            padding:8px 14px;
            border-radius:999px;
            font-size:13px;
            font-weight:700;
            display:inline-block;
        }

        .done{
            background:#dcfce7;
            color:#166534;
        }

        .shipping{
            background:#dbeafe;
            color:#1d4ed8;
        }

        .pending{
            background:#fef3c7;
            color:#92400e;
        }

        @media(max-width:1100px){

            .card-grid{
                grid-template-columns:repeat(2,1fr);
            }

        }

        @media(max-width:768px){

            .wrapper{
                flex-direction:column;
            }

            .sidebar{
                width:100%;
            }

            .card-grid{
                grid-template-columns:1fr;
            }

        }

    </style>
</head>

<body>

<div class="wrapper">

    <!-- SIDEBAR -->

    <div class="sidebar">

        <div class="logo">
            TT SHOP
        </div>

        <div class="menu">

            <a href="index.php">
                <i class="fa-solid fa-house"></i>
                Dashboard
            </a>

            <a href="products.php">
                <i class="fa-solid fa-box"></i>
                Quản lý sản phẩm
            </a>

            <a href="category_products.php">
                <i class="fa-solid fa-layer-group"></i>
                Danh mục sản phẩm
            </a>

            <a href="orders.php">
                <i class="fa-solid fa-cart-shopping"></i>
                Quản lý đơn hàng
            </a>

            <a href="customers.php">
                <i class="fa-solid fa-users"></i>
                Khách hàng
            </a>

            <a href="../index.php">
                <i class="fa-solid fa-globe"></i>
                Về website
            </a>

        </div>

    </div>

    <!-- CONTENT -->

    <div class="content">

        <div class="top-title">
            Dashboard Admin
        </div>

        <!-- CARD -->

        <div class="card-grid">

            <div class="card">

                <div class="card-top">
                    <div class="card-title">
                        Tổng sản phẩm
                    </div>

                    <div class="card-icon blue">
                        <i class="fa-solid fa-box"></i>
                    </div>
                </div>

                <div class="card-number">
                    <?php echo $totalProduct; ?>
                </div>

            </div>

            <div class="card">

                <div class="card-top">
                    <div class="card-title">
                        Khách hàng
                    </div>

                    <div class="card-icon green">
                        <i class="fa-solid fa-users"></i>
                    </div>
                </div>

                <div class="card-number">
                    <?php echo $totalUser; ?>
                </div>

            </div>

            <div class="card">

                <div class="card-top">
                    <div class="card-title">
                        Đơn hàng
                    </div>

                    <div class="card-icon orange">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                </div>

                <div class="card-number">
                    <?php echo $totalOrder; ?>
                </div>

            </div>

            <div class="card">

                <div class="card-top">
                    <div class="card-title">
                        Doanh thu
                    </div>

                    <div class="card-icon red">
                        <i class="fa-solid fa-money-bill"></i>
                    </div>
                </div>

                <div class="card-number">
                    <?php echo number_format($totalRevenue,0,',','.'); ?>đ
                </div>

            </div>

        </div>

        <!-- TABLE -->

        <div class="table-box">

            <div class="table-title">
                Đơn hàng mới
            </div>

            <table>

                <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>SĐT</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                </tr>

                <?php while($row = mysqli_fetch_assoc($resultNewOrder)){ ?>

                    <tr>

                        <td>
                            <?php echo $row['ma_don']; ?>
                        </td>

                        <td>
                            <?php echo $row['ho_ten']; ?>
                        </td>

                        <td>
                            <?php echo $row['sdt']; ?>
                        </td>

                        <td>
                            <?php echo number_format($row['tong_cong'],0,',','.'); ?>đ
                        </td>

                        <td>

                            <?php
                            $class = 'pending';

                            if($row['trang_thai'] == 'Đã giao'){
                                $class = 'done';
                            }

                            if($row['trang_thai'] == 'Đang vận chuyển'){
                                $class = 'shipping';
                            }
                            ?>

                            <span class="status <?php echo $class; ?>">
                                <?php echo $row['trang_thai']; ?>
                            </span>

                        </td>

                    </tr>

                <?php } ?>

            </table>

        </div>

    </div>

</div>

</body>
</html>