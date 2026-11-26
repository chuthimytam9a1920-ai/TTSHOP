<?php
require_once __DIR__ . '/../payment_helpers.php';
// admin/orders.php — Danh sách đơn hàng và ưu đãi thành viên.
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../csrf.php';
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../connect.php';

function ao_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function ao_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}
function ao_get(string $key): string {
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}
function ao_valid_date(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value
        && (int)$date->format('Y') >= 1000;
}

$token = csrf_token();
$flash = $_SESSION['admin_order_flash'] ?? null;
unset($_SESSION['admin_order_flash']);
$statuses = ['Chờ xử lý', 'Chờ lấy hàng', 'Đang vận chuyển', 'Đã giao', 'Đã huỷ'];
$keyword = ao_get('keyword');
$status = ao_get('status');
$fromDate = ao_get('from_date');
$toDate = ao_get('to_date');
$voucherFilter = ao_get('voucher');
$voucherCode = ao_get('voucher_code');
$errors = [];
$where = [];
$types = '';
$params = [];

// Giữ bộ lọc cũ, thêm tìm kiếm mã voucher và lọc có/không voucher.
if ($keyword !== '') {
    // Escape ký tự LIKE để tìm theo đúng chuỗi người dùng nhập.
    $like = '%' . strtr($keyword, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $where[] = "(d.ma_don LIKE ? ESCAPE '!' OR d.ho_ten LIKE ? ESCAPE '!' OR d.sdt LIKE ? ESCAPE '!')";
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
if ($status === 'Đã huỷ' || $status === 'Đã hủy') {
    $status = 'Đã huỷ';
    $where[] = "d.trang_thai IN ('Đã huỷ', 'Đã hủy')";
} elseif ($status !== '') {
    if (in_array($status, $statuses, true)) {
        $where[] = 'd.trang_thai = ?';
        $types .= 's';
        $params[] = $status;
    } else {
        $errors[] = 'Trạng thái đơn hàng không hợp lệ.';
    }
}
foreach ([[$fromDate, '>=', 'Ngày bắt đầu'], [$toDate, '<=', 'Ngày kết thúc']] as [$date, $operator, $label]) {
    if ($date !== '') {
        if (!ao_valid_date($date)) {
            $errors[] = $label . ' không hợp lệ.';
        } else {
            $where[] = 'DATE(d.created_at) ' . $operator . ' ?';
            $types .= 's';
            $params[] = $date;
        }
    }
}
if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    $errors[] = 'Ngày bắt đầu không được sau ngày kết thúc.';
}
if ($voucherFilter === 'yes') {
    $where[] = "NULLIF(TRIM(d.ma_giam_gia), '') IS NOT NULL";
} elseif ($voucherFilter === 'no') {
    $where[] = "NULLIF(TRIM(d.ma_giam_gia), '') IS NULL";
} elseif ($voucherFilter !== '') {
    $errors[] = 'Bộ lọc voucher không hợp lệ.';
}
if ($voucherCode !== '') {
    if (!preg_match('/^[A-Za-z0-9_-]{1,50}$/D', $voucherCode)) {
        $errors[] = 'Mã voucher không hợp lệ.';
    } else {
        $where[] = 'd.ma_giam_gia = ?';
        $types .= 's';
        $params[] = strtoupper($voucherCode);
    }
}


$orders = null;
if (!$errors) {
    // Đọc hạng và tiền đã lưu tại thời điểm mua, không tính lại từ cấu hình voucher hiện tại.
    // Gom đánh giá trước khi JOIN để mỗi đơn chỉ xuất hiện một dòng.
    $sql = "SELECT d.*,
                   COALESCE(r.so_danh_gia, 0) AS so_danh_gia,
                   r.so_sao, r.nhan_xet,
                   COALESCE(l.tien_giam_van_chuyen, 0) AS tien_giam_van_chuyen
            FROM donhang d
            LEFT JOIN (
                SELECT donhang_id, COUNT(id) AS so_danh_gia,
                       MAX(so_sao) AS so_sao, MAX(nhan_xet) AS nhan_xet
                FROM danhgia GROUP BY donhang_id
            ) r ON r.donhang_id = d.id
            LEFT JOIN lichsu_magiamgia l ON l.donhang_id = d.id";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY d.id DESC';
    try {
        $stmt = $conn->prepare($sql);
        try {
            if ($types !== '') $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $orders = $stmt->get_result();
        } finally {
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log('TT SHOP admin orders: ' . $e->getMessage());
        $errors[] = 'Không thể tải đơn hàng. Hãy kiểm tra kết nối và SQL nâng cấp voucher.';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        table{width:100%;min-width:1750px;border-collapse:collapse}
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
        .delete{color:red;font-weight:bold;text-decoration:none;background:none;border:0;padding:0;cursor:pointer;font:inherit;font-weight:bold}
        .delete-form{display:inline;margin:0}
        .actions{white-space:nowrap}
        .money{white-space:nowrap}
        .discount-money{color:#15985d;font-weight:bold;white-space:nowrap}
        .voucher-code{color:#15985d;font-weight:bold;white-space:nowrap}
        .order-muted{color:#777;font-size:12px;display:block;margin-top:5px}
        .order-alert{padding:12px 15px;margin-bottom:18px;border-radius:8px;background:#fff0f0;color:#a12020}
        .order-alert.success{background:#eaf8ef;color:#137a43}
        .order-filter input[name="voucher_code"]{width:175px}
        .order-filter input[name="keyword"]{max-width:100%}
        @media(max-width:800px){.layout{grid-template-columns:1fr}.main{padding:15px}.box{padding:15px}}
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
            <h1>Quản lý đơn hàng</h1>
        </div>

        <div class="box">

            <form method="GET" class="order-filter">
                <select name="status" aria-label="Trạng thái đơn hàng">
                    <option value="">Tất cả trạng thái</option>
                    <?php foreach ($statuses as $option): ?>
                        <option value="<?= ao_e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= ao_e($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="keyword" placeholder="Tìm kiếm mã đơn, khách hàng, SĐT..." value="<?= ao_e($keyword) ?>">
                <label for="from-date">Từ</label>
                <input id="from-date" type="date" name="from_date" value="<?= ao_e($fromDate) ?>">
                <label for="to-date">Đến</label>
                <input id="to-date" type="date" name="to_date" value="<?= ao_e($toDate) ?>">
                <select name="voucher" aria-label="Đơn hàng sử dụng voucher">
                    <option value="">Tất cả đơn hàng</option>
                    <option value="yes" <?= $voucherFilter === 'yes' ? 'selected' : '' ?>>Có mã giảm giá</option>
                    <option value="no" <?= $voucherFilter === 'no' ? 'selected' : '' ?>>Không có mã giảm giá</option>
                </select>
                <input type="text" name="voucher_code" maxlength="50" placeholder="Nhập mã voucher" value="<?= ao_e($voucherCode) ?>">

                <button type="submit">Lọc</button>
                <a href="orders.php">Làm mới</a>
            </form>

            <?php if (is_array($flash)): ?>
                <div role="status" class="order-alert <?= !empty($flash['ok']) ? 'success' : '' ?>"><?= ao_e($flash['message'] ?? '') ?></div>
            <?php endif; ?>
            <?php foreach ($errors as $error): ?>
                <div class="order-alert" role="alert"><?= ao_e($error) ?></div>
            <?php endforeach; ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>SĐT</th>
                            <th>Hạng khi mua</th>
                            <th>Tạm tính</th>
                            <th>Mã giảm giá</th>
                            <th>Tiền giảm</th>
                            <th>Phí vận chuyển</th>
                            <th>Tổng thanh toán</th>
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
                                    <td><?= ao_e($row['hang_thanh_vien'] ?: '—') ?></td>
                                    <td class="money"><?= ao_money($row['tam_tinh']) ?></td>
                                    <td>
                                        <?php if (trim((string)$row['ma_giam_gia']) !== ''): ?>
                                            <span class="voucher-code"><?= ao_e($row['ma_giam_gia']) ?></span>
                                        <?php else: ?>—<?php endif; ?>
                                    </td>
                                    <td class="discount-money"><?= (int)$row['tien_giam_gia'] > 0 ? '−' : '' ?><?= ao_money($row['tien_giam_gia']) ?></td>
                                    <td class="money">
                                        <?= (int)$row['phi_van_chuyen'] === 0 ? 'Miễn phí' : ao_money($row['phi_van_chuyen']) ?>
                                        <?php if ((int)$row['tien_giam_van_chuyen'] > 0): ?>
                                            <small class="order-muted">Đã giảm <?= ao_money($row['tien_giam_van_chuyen']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="money"><strong><?= ao_money($row['tong_cong']) ?></strong></td>
                                    <td><strong style="color:<?= ($row['payment_status']??'')==='PAID'?'#08794f':'inherit' ?>"><?php echo payos_escape(payos_label($row)); ?></strong><?php if($row['phuong_thuc']==='PAYOS'): ?><br><small>Đã nhận <?= ao_money($row['payment_amount_received']) ?></small><?php endif; ?></td>

                                    <td>
                                        <span class="status">
                                            <?php echo htmlspecialchars($row['trang_thai']); ?>
                                        </span>
                                    </td>

                                    <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>

                                    <td>
                                        <?php if ((int)$row['so_danh_gia'] > 0) { ?>
                                            <div class="review-stars">
                                                <?php echo str_repeat("★", max(0, min(5, (int)$row['so_sao']))); ?>
                                                <?php echo str_repeat("☆", 5 - max(0, min(5, (int)$row['so_sao']))); ?>
                                            </div>

                                            <div class="review-text">
                                                <?php echo nl2br(ao_e($row['nhan_xet'])); ?>
                                            </div>
                                        <?php } else { ?>
                                            <span class="no-review">Chưa đánh giá</span>
                                        <?php } ?>
                                    </td>

                                    <td class="actions">
                                        <a class="detail" href="order_detail.php?id=<?= (int)$row['id'] ?>">Chi tiết</a>
                                        <?php if($row['phuong_thuc']!=='PAYOS'): ?>|
                                        <form class="delete-form" method="POST" action="delete_order.php" onsubmit="return confirm('Xóa vĩnh viễn đơn hàng, chi tiết, đánh giá và lịch sử voucher liên quan? Không thể hoàn tác.')">
                                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= ao_e($token) ?>">
                                            <button class="delete" type="submit">Xóa</button>
                                        </form><?php endif; ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="15" style="text-align:center;"><?= $errors ? 'Không thể hiển thị đơn hàng.' : 'Không tìm thấy đơn hàng nào' ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</div>

<?php if(payos_ready()): ?><script>
async function syncPayments(){
  if(!document.hidden && !['INPUT','SELECT','TEXTAREA'].includes(document.activeElement?.tagName)){
    try{await fetch('payment_sync.php',{method:'POST',credentials:'same-origin',body:new URLSearchParams({csrf_token:<?= json_encode($token) ?>})});}catch(e){}
    if(!document.hidden && !['INPUT','SELECT','TEXTAREA'].includes(document.activeElement?.tagName)){location.reload();return;}
  }
  setTimeout(syncPayments,45000);
}
setTimeout(syncPayments,45000);
</script><?php endif; ?>
</body>
</html>