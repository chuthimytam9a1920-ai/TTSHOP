<?php
include "check_admin.php";
include "../connect.php";

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("ID đơn hàng không hợp lệ.");
}

/* Lấy thông tin đơn hàng */
$stmt = mysqli_prepare($conn, "SELECT * FROM donhang WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if (!$order) {
    die("Không tìm thấy đơn hàng.");
}

/* Lấy sản phẩm trong đơn hàng */
$stmtItems = mysqli_prepare($conn, "
    SELECT * FROM chitiet_donhang 
    WHERE donhang_id = ?
");

mysqli_stmt_bind_param($stmtItems, "i", $id);
mysqli_stmt_execute($stmtItems);
$items = mysqli_stmt_get_result($stmtItems);

/* Ghép địa chỉ đầy đủ */
$diaChiParts = [
    $order['dia_chi'] ?? '',
    $order['phuong_xa'] ?? '',
    $order['quan_huyen'] ?? '',
    $order['tinh_thanh'] ?? ''
];

$diaChiParts = array_filter($diaChiParts, function ($value) {
    return trim($value) !== '';
});

$diaChiDayDu = implode(', ', $diaChiParts);

/* Danh sách trạng thái */
$trangThaiList = [
    'Chờ xử lý',
    'Chờ lấy hàng',
    'Đang vận chuyển',
    'Đã giao',
    'Đã hủy'
];

function statusClass($status) {
    switch ($status) {
        case 'Chờ xử lý':
            return 'status-pending';
        case 'Chờ lấy hàng':
            return 'status-waiting';
        case 'Đang vận chuyển':
            return 'status-shipping';
        case 'Đã giao':
            return 'status-success';
        case 'Đã hủy':
            return 'status-cancel';
        default:
            return 'status-default';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chi tiết đơn hàng</title>

    <style>
        *{box-sizing:border-box}
        body{
            margin:0;
            font-family:Arial;
            background:#f4f6f8;
        }

        .layout{
            display:grid;
            grid-template-columns:260px minmax(0,1fr);
            min-height:100vh;
        }

        .sidebar{
            background:#15985d;
            color:white;
            padding:25px 20px;
        }

        .sidebar h2{
            font-size:30px;
            margin-bottom:35px;
        }

        .sidebar a{
            display:block;
            color:white;
            text-decoration:none;
            padding:14px 12px;
            border-radius:8px;
            margin-bottom:8px;
            font-weight:bold;
        }

        .sidebar a:hover,
        .sidebar a.active{
            background:rgba(255,255,255,.18);
        }

        .main{
            padding:30px;
            min-width:0;
        }

        .topbar,
        .box{
            background:white;
            border-radius:14px;
            padding:25px;
            box-shadow:0 4px 14px rgba(0,0,0,.08);
            margin-bottom:25px;
        }

        .topbar{
            display:flex;
            justify-content:space-between;
            align-items:center;
        }

        .topbar h1{
            margin:0;
            font-size:34px;
        }

        .info-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:14px 35px;
        }

        .info-item{
            font-size:16px;
            line-height:1.5;
        }

        .info-item b{
            display:block;
            margin-bottom:4px;
            color:#333;
        }

        .info-item span{
            color:#111;
        }

        .full{
            grid-column:1 / -1;
        }

        .money{
            font-weight:bold;
            color:#15985d;
        }

        .status-badge{
            display:inline-block;
            padding:7px 13px;
            border-radius:20px;
            font-weight:bold;
            font-size:13px;
        }

        .status-pending,
        .status-waiting,
        .status-default{
            background:#fff0c7;
            color:#b66a00;
        }

        .status-shipping{
            background:#dff0ff;
            color:#0b72e7;
        }

        .status-success{
            background:#dcfce7;
            color:#15803d;
        }

        .status-cancel{
            background:#fee2e2;
            color:#dc2626;
        }

        .status-form{
            margin-top:22px;
            display:flex;
            gap:10px;
            align-items:center;
            flex-wrap:wrap;
        }

        .status-form select{
            height:42px;
            padding:0 14px;
            border:1px solid #ddd;
            border-radius:8px;
            font-weight:bold;
            background:#f7f7f7;
        }

        .status-form button{
            height:42px;
            background:#15985d;
            color:white;
            border:0;
            border-radius:8px;
            padding:0 18px;
            font-weight:bold;
            cursor:pointer;
        }

        .table-wrap{
            width:100%;
            overflow-x:auto;
        }

        table{
            width:100%;
            min-width:850px;
            border-collapse:collapse;
        }

        th,td{
            padding:13px;
            border-bottom:1px solid #eee;
            text-align:left;
            vertical-align:middle;
        }

        th{
            background:#f0f0f0;
        }

        .product-img{
            width:75px;
            height:75px;
            object-fit:contain;
            border:1px solid #eee;
            border-radius:8px;
            background:white;
        }

        .back{
            display:inline-block;
            background:#777;
            color:white;
            padding:12px 18px;
            border-radius:8px;
            text-decoration:none;
            font-weight:bold;
        }

        .back:hover{
            background:#555;
        }

        @media(max-width:900px){
            .layout{
                grid-template-columns:1fr;
            }

            .info-grid{
                grid-template-columns:1fr;
            }

            .full{
                grid-column:auto;
            }
        }
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
        <h1>Chi tiết đơn hàng</h1>
    </div>

    <div class="box">
        <h2>Thông tin đơn hàng</h2>

        <div class="info-grid">
            <div class="info-item">
                <b>Mã đơn</b>
                <span><?php echo htmlspecialchars($order['ma_don'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Ngày đặt</b>
                <span><?php echo htmlspecialchars($order['created_at'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Khách hàng</b>
                <span><?php echo htmlspecialchars($order['ho_ten'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Số điện thoại</b>
                <span><?php echo htmlspecialchars($order['sdt'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Phương thức thanh toán</b>
                <span><?php echo htmlspecialchars($order['phuong_thuc'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Trạng thái</b>
                <span class="status-badge <?php echo statusClass($order['trang_thai'] ?? ''); ?>">
                    <?php echo htmlspecialchars($order['trang_thai'] ?? ''); ?>
                </span>
            </div>

            <div class="info-item">
                <b>Địa chỉ cụ thể</b>
                <span><?php echo htmlspecialchars($order['dia_chi'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Phường / Xã</b>
                <span><?php echo htmlspecialchars($order['phuong_xa'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Quận / Huyện</b>
                <span><?php echo htmlspecialchars($order['quan_huyen'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Tỉnh / Thành</b>
                <span><?php echo htmlspecialchars($order['tinh_thanh'] ?? ''); ?></span>
            </div>

            <div class="info-item full">
                <b>Địa chỉ giao hàng</b>
                <span><?php echo htmlspecialchars($diaChiDayDu); ?></span>
            </div>

            <div class="info-item">
                <b>Tạm tính</b>
                <span><?php echo number_format((int)($order['tam_tinh'] ?? 0),0,',','.'); ?>đ</span>
            </div>

            <div class="info-item">
                <b>Phí vận chuyển</b>
                <span><?php echo number_format((int)($order['phi_van_chuyen'] ?? 0),0,',','.'); ?>đ</span>
            </div>

            <div class="info-item">
                <b>Tổng tiền</b>
                <span class="money"><?php echo number_format((int)($order['tong_cong'] ?? 0),0,',','.'); ?>đ</span>
            </div>
        </div>

        <form method="POST" action="update_order_status.php" class="status-form">
            <input type="hidden" name="id" value="<?php echo (int)$order['id']; ?>">

            <label><b>Cập nhật trạng thái:</b></label>

            <select name="trang_thai">
                <?php foreach ($trangThaiList as $status) { ?>
                    <option value="<?php echo htmlspecialchars($status); ?>"
                        <?php echo (($order['trang_thai'] ?? '') == $status) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($status); ?>
                    </option>
                <?php } ?>
            </select>

            <button type="submit">Cập nhật</button>
        </form>
    </div>

    <div class="box">
        <h2>Sản phẩm trong đơn</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Tên sản phẩm</th>
                        <th>Giá</th>
                        <th>Số lượng</th>
                        <th>Thành tiền</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($items && mysqli_num_rows($items) > 0) { ?>

                        <?php while ($item = mysqli_fetch_assoc($items)) { ?>

                            <?php
                            $tenSanPham = $item['ten_san_pham'] ?? $item['ten_sanpham'] ?? '';
                            $hinhAnh = $item['hinh_anh'] ?? '';
                            $gia = (int)($item['gia'] ?? 0);
                            $soLuong = (int)($item['so_luong'] ?? 0);
                            $thanhTien = (int)($item['thanh_tien'] ?? 0);

                            if ($thanhTien <= 0) {
                                $thanhTien = $gia * $soLuong;
                            }
                            ?>

                            <tr>
                                <td>
                                    <?php if (!empty($hinhAnh)) { ?>
                                        <img class="product-img" src="../<?php echo htmlspecialchars($hinhAnh); ?>" alt="">
                                    <?php } else { ?>
                                        Không có ảnh
                                    <?php } ?>
                                </td>

                                <td><?php echo htmlspecialchars($tenSanPham); ?></td>

                                <td><?php echo number_format($gia,0,',','.'); ?>đ</td>

                                <td><?php echo $soLuong; ?></td>

                                <td><?php echo number_format($thanhTien,0,',','.'); ?>đ</td>
                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>
                            <td colspan="5" style="text-align:center;">
                                Không có sản phẩm
                            </td>
                        </tr>

                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <a class="back" href="orders.php">← Quay lại</a>

</main>

</div>

</body>
</html>