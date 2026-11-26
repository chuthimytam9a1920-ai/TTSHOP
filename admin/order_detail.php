
<?php
require_once __DIR__ . '/../payment_helpers.php';
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../csrf.php';
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../connect.php';

function od_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function od_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}
function od_status(string $status): string {
    return $status === 'Đã hủy' ? 'Đã huỷ' : $status;
}
$token = csrf_token();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);

if (!$id) {
    http_response_code(400);
    die("ID đơn hàng không hợp lệ.");
}

/* Lấy thông tin đơn hàng */
$stmt = mysqli_prepare($conn, "SELECT * FROM donhang WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$order) {
    http_response_code(404);
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

mysqli_stmt_close($stmtItems);

/* Lịch sử voucher: chỉ lấy dữ liệu đã ghi tại thời điểm mua. */
$stmtVoucher = $conn->prepare("SELECT id, magiamgia_id, user_id, ma_code,
    tien_giam_gia, tien_giam_van_chuyen, created_at
    FROM lichsu_magiamgia WHERE donhang_id = ?");
$stmtVoucher->bind_param('i', $id);
$stmtVoucher->execute();
$history = $stmtVoucher->get_result()->fetch_assoc();
$stmtVoucher->close();

$voucherCode = trim((string)($order['ma_giam_gia'] ?? ''));
$tier = $order['hang_thanh_vien'] ?? null;
$subtotal = (int)$order['tam_tinh'];
$discount = (int)($order['tien_giam_gia'] ?? 0);
$shipping = (int)$order['phi_van_chuyen'];
$shippingDiscount = $history ? (int)$history['tien_giam_van_chuyen'] : 0;
$total = (int)$order['tong_cong'];
$currentStatus = od_status((string)($order['trang_thai'] ?? ''));
$cancelled = $currentStatus === 'Đã huỷ';
$hasVoucher = $voucherCode !== '' || $history !== null;
$qr = ($order['phuong_thuc']??'')==='PAYOS';
$terminal = ($cancelled && $hasVoucher) || ($qr && (($order['payment_status']??'')!=='PAID' || $cancelled));
$flash = $_SESSION['admin_order_flash'] ?? null;
unset($_SESSION['admin_order_flash']);

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
    'Đã huỷ'
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
        case 'Đã huỷ':
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
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

        .voucher-code{font-weight:bold;color:#15985d;overflow-wrap:anywhere}
        .muted{color:#666;font-size:14px;line-height:1.5}
        .notice{padding:13px 16px;border-radius:8px;margin-bottom:20px;line-height:1.5}
        .notice-ok{background:#dcfce7;color:#15803d}
        .notice-error{background:#fee2e2;color:#991b1b}
        .notice-warning{background:#fff0c7;color:#855000}
        .info-item span{overflow-wrap:anywhere}
        .info-item span.money{color:#15985d}
        .status-form button:disabled{opacity:.5;cursor:not-allowed}
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
    <link rel="stylesheet" href="admin.css">
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

    <?php if (is_array($flash)): ?>
        <div role="status" class="notice <?= !empty($flash['ok']) ? 'notice-ok' : 'notice-error' ?>">
            <?= od_e($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <div class="box">
        <h2>Thông tin đơn hàng</h2>

        <div class="info-grid">
            <div class="info-item">
                <b>Mã đơn</b>
                <span><?php echo od_e($order['ma_don'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Ngày đặt</b>
                <span><?php echo od_e($order['created_at'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Khách hàng</b>
                <span><?php echo od_e($order['ho_ten'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Số điện thoại</b>
                <span><?php echo od_e($order['sdt'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Phương thức thanh toán</b>
                <span><?php echo payos_escape(payos_label($order)); ?></span>
            </div>

            <?php if($qr): ?>
            <div class="info-item"><b>Tiền đã nhận qua QR</b><span><?= od_money($order['payment_amount_received']) ?></span></div>
            <div class="info-item"><b>Mã giao dịch</b><span><?= od_e($order['payment_reference'] ?? '—') ?></span></div>
            <div class="info-item"><b>Mã payOS</b><span><?= od_e($order['payos_order_code'] ?? '—') ?></span></div>
            <div class="info-item"><b>Thời điểm xác nhận</b><span><?= od_e($order['paid_at'] ?? '—') ?></span></div>
            <?php endif; ?>
            <div class="info-item">
                <b>Trạng thái</b>
                <span class="status-badge <?php echo statusClass($order['trang_thai'] ?? ''); ?>">
                    <?php echo od_e($order['trang_thai'] ?? ''); ?>
                </span>
            </div>

            <div class="info-item">
                <b>Địa chỉ cụ thể</b>
                <span><?php echo od_e($order['dia_chi'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Phường / Xã</b>
                <span><?php echo od_e($order['phuong_xa'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Quận / Huyện</b>
                <span><?php echo od_e($order['quan_huyen'] ?? ''); ?></span>
            </div>

            <div class="info-item">
                <b>Tỉnh / Thành</b>
                <span><?php echo od_e($order['tinh_thanh'] ?? ''); ?></span>
            </div>

            <div class="info-item full">
                <b>Địa chỉ giao hàng</b>
                <span><?php echo od_e($diaChiDayDu); ?></span>
            </div>

            <div class="info-item">
                <b>Hạng thành viên khi mua</b>
                <span><?= od_e($tier ?: 'Chưa ghi nhận (đơn cũ)') ?></span>
            </div>
            <div class="info-item">
                <b>Mã giảm giá</b>
                <span class="voucher-code"><?= od_e($voucherCode !== '' ? $voucherCode : ($history['ma_code'] ?? 'Không sử dụng')) ?></span>
            </div>
            <div class="info-item">
                <b>Tạm tính</b>
                <span><?= od_money($subtotal) ?></span>
            </div>
            <div class="info-item">
                <b>Tiền giảm giá sản phẩm</b>
                <span class="money"><?= $discount > 0 ? '−' : '' ?><?= od_money($discount) ?></span>
            </div>
            <div class="info-item">
                <b>Phí vận chuyển thực trả</b>
                <span><?= od_money($shipping) ?><?= $shipping === 0 && $shippingDiscount > 0 ? ' (Freeship)' : '' ?></span>
            </div>
            <div class="info-item">
                <b>Tiền giảm phí vận chuyển</b>
                <span class="money"><?= $shippingDiscount > 0 ? '−' : '' ?><?= od_money($shippingDiscount) ?></span>
            </div>
            <div class="info-item">
                <b>Tổng tiết kiệm từ voucher</b>
                <span class="money"><?= od_money($discount + $shippingDiscount) ?></span>
                <?php if ($cancelled): ?><p class="muted">Đơn đã hủy, không ghi nhận tiết kiệm thực tế.</p><?php endif; ?>
            </div>
            <div class="info-item">
                <b>Tổng thanh toán sau giảm</b>
                <span class="money"><?= od_money($total) ?></span>
            </div>
        </div>

        <?php if ($total !== $subtotal - $discount + $shipping): ?>
            <p class="notice notice-warning">Tổng tiền lưu trong đơn không khớp công thức hiện tại. Hệ thống giữ nguyên dữ liệu lịch sử; hãy kiểm tra trước khi xử lý.</p>
        <?php endif; ?>
        <?php if ($qr && $terminal): ?>
            <p class="muted">Đơn QR chưa được xác nhận đủ tiền hoặc đã hủy. Không thể cập nhật vận chuyển; kiểm tra giao dịch trong payOS.</p>
        <?php elseif ($terminal): ?>
            <p class="muted">Đơn có voucher đã hủy không được mở lại, để tránh sử dụng vượt giới hạn voucher đã hoàn trả. Khách hàng có thể tạo đơn mới.</p>
        <?php endif; ?>
        <form method="POST" action="update_order_status.php" class="status-form">
            <input type="hidden" name="id" value="<?php echo (int)$order['id']; ?>">

            <input type="hidden" name="csrf_token" value="<?= od_e($token) ?>">
            <input type="hidden" name="expected_status" value="<?= od_e($currentStatus) ?>">
            <label for="order-status"><b>Cập nhật trạng thái:</b></label>

            <select id="order-status" name="trang_thai" required <?= $terminal ? 'disabled' : '' ?>>
                <?php foreach ($trangThaiList as $status) { if($qr && in_array($status,['Đã huỷ','Đã hủy'],true) && !$cancelled) continue; ?>
                    <option value="<?php echo od_e($status); ?>"
                        <?php echo ($currentStatus === $status) ? 'selected' : ''; ?>>
                        <?php echo od_e($status); ?>
                    </option>
                <?php } ?>
            </select>

            <button type="submit" <?= $terminal ? 'disabled' : '' ?>>Cập nhật</button>
        </form>
    </div>

    <div class="box">
        <h2>Lịch sử sử dụng mã giảm giá</h2>
        <?php if ($history): ?>
            <div class="info-grid">
                <div class="info-item"><b>Mã đã sử dụng</b><span><?= od_e($history['ma_code']) ?></span></div>
                <div class="info-item"><b>Thời gian sử dụng</b><span><?= od_e($history['created_at']) ?></span></div>
                <div class="info-item"><b>Giảm giá sản phẩm (lịch sử)</b><span><?= od_money($history['tien_giam_gia']) ?></span></div>
                <div class="info-item"><b>Giảm phí vận chuyển (lịch sử)</b><span><?= od_money($shippingDiscount) ?></span></div>
                <div class="info-item"><b>ID voucher</b><span><?= (int)$history['magiamgia_id'] ?></span></div>
                <div class="info-item"><b>Ghi nhận lượt sử dụng</b><span><?= $cancelled ? 'Đã hoàn trả lượt (đơn hủy)' : 'Đang tính vào lượt sử dụng' ?></span></div>
            </div>
            <?php if ((int)$history['user_id'] !== (int)$order['user_id'] || (int)$history['tien_giam_gia'] !== $discount || $history['ma_code'] !== $voucherCode): ?>
                <p class="notice notice-warning">Dữ liệu lịch sử voucher không khớp thông tin đơn hàng. Cần kiểm tra; hệ thống không tự sửa dữ liệu.</p>
            <?php endif; ?>
            <p class="muted">Lịch sử được lưu tại thời điểm đặt hàng, không tính lại theo cấu hình voucher hiện tại.</p>
        <?php elseif ($hasVoucher): ?>
            <p class="notice notice-warning">Đơn có mã giảm giá nhưng chưa có bản ghi lịch sử. Không thể xác nhận số tiền giảm vận chuyển.</p>
        <?php else: ?>
            <p class="muted">Đơn hàng không sử dụng voucher.</p>
        <?php endif; ?>
        <a href="vouchers.php" class="back">Quản lý mã giảm giá</a>
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
                                        <img class="product-img" src="../<?php echo od_e($hinhAnh); ?>" alt="">
                                    <?php } else { ?>
                                        Không có ảnh
                                    <?php } ?>
                                </td>

                                <td><?php echo od_e($tenSanPham); ?></td>

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
