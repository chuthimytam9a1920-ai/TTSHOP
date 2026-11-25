<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "connect.php";
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/voucher_helpers.php';
$csrfToken = csrf_token();
$accountFieldErrors = $_SESSION['account_field_errors'] ?? array();
unset($_SESSION['account_field_errors']);

/* Kiểm tra đăng nhập */
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

/* Đếm địa chỉ */
$sqlCount = "SELECT COUNT(*) AS total FROM diachi_khachhang WHERE user_id = ?";
$stmtCount = mysqli_prepare($conn, $sqlCount);
mysqli_stmt_bind_param($stmtCount, "i", $user_id);
mysqli_stmt_execute($stmtCount);

$resultCount = mysqli_stmt_get_result($stmtCount);
$rowCount = mysqli_fetch_assoc($resultCount);
$soDiaChi = ($rowCount['total'] ?? 0) + 1;

/* Lấy thông tin user */
$sqlUser = "SELECT * FROM users WHERE id = ?";
$stmtUser = mysqli_prepare($conn, $sqlUser);
mysqli_stmt_bind_param($stmtUser, "i", $user_id);
mysqli_stmt_execute($stmtUser);

$resultUser = mysqli_stmt_get_result($stmtUser);
$user = mysqli_fetch_assoc($resultUser);

/* Không sử dụng phiên đăng nhập cũ nếu ID đã thuộc tài khoản khác. */
if (!$user || (isset($_SESSION['user_email']) &&
    strcasecmp((string)$_SESSION['user_email'], (string)$user['email']) !== 0)) {
    unset($_SESSION['is_login'], $_SESSION['user_id'], $_SESSION['user_email'],
          $_SESSION['voucher_code'], $_SESSION['voucher_user_id'],
          $_SESSION['checkout_info'], $_SESSION['checkout_review']);
    header('Location: dangnhap.php');
    exit();
}

/* Thông báo hủy đơn chỉ hiển thị cho đúng tài khoản. */
$cancelFlash = $_SESSION['customer_cancel_flash'] ?? null;
unset($_SESSION['customer_cancel_flash']);
if (!is_array($cancelFlash) || ($cancelFlash['user_id'] ?? null) !== $user_id) {
    $cancelFlash = null;
}

/* Lưu session */
if ($user) {
    $_SESSION['user_ho']        = $user['ho'];
    $_SESSION['user_name']      = $user['ten'];
    $_SESSION['user_email']     = $user['email'];
    $_SESSION['user_sdt']       = $user['sdt'];
    $_SESSION['user_ngaysinh']  = $user['ngaysinh'];
    $_SESSION['user_diachi']    = $user['diachi'];
}

/* Lấy dữ liệu từ session */
$ho        = $_SESSION['user_ho'] ?? '';
$ten       = $_SESSION['user_name'] ?? '';
$email     = $_SESSION['user_email'] ?? '';
$sdt       = $_SESSION['user_sdt'] ?? '';
$ngaysinh  = $_SESSION['user_ngaysinh'] ?? '';
$diachi    = $_SESSION['user_diachi'] ?? '';

/* Lấy danh sách địa chỉ */
$addressList = [];

$sqlAddress = "
    SELECT * 
    FROM diachi_khachhang 
    WHERE user_id = ?
    ORDER BY mac_dinh DESC, id DESC
";

$stmtAddress = mysqli_prepare($conn, $sqlAddress);
mysqli_stmt_bind_param($stmtAddress, "i", $user_id);
mysqli_stmt_execute($stmtAddress);

$resultAddress = mysqli_stmt_get_result($stmtAddress);

if ($resultAddress) {
    while ($row = mysqli_fetch_assoc($resultAddress)) {
        $addressList[] = $row;
    }
}

/* Họ tên đầy đủ */
$fullName = trim($ho . ' ' . $ten);
$fullName = mb_convert_case($fullName, MB_CASE_TITLE, "UTF-8");

/* Tính phần trăm hoàn thiện tài khoản */
$percent = 0;
$profileComplete = true;

foreach ([[$ho, 15], [$ten, 15], [$email, 15], [$sdt, 15], [$ngaysinh, 20], [$diachi, 20]] as [$value, $weight]) {
    if (trim((string)$value) !== '') {
        $percent += $weight;
    } else {
        $profileComplete = false;
    }
}

if ($profileComplete) {
    $percent = 100;
    $profileText   = "Tuyệt vời! Hồ sơ của bạn đã đầy đủ";
    $profileMascot = "./imagescategory/mascot-happy.png";
} else {
    $profileText   = "Ôi không, bạn chưa cập nhật đủ thông tin!";
    $profileMascot = "./imagescategory/Screenshot 2026-05-07 143553.png";
}

/* Tạo avatar */
$first_letter_ho  = mb_substr(trim($ho), 0, 1, "UTF-8");
$first_letter_ten = mb_substr(trim($ten), 0, 1, "UTF-8");

$avatar_text = mb_strtoupper($first_letter_ho . $first_letter_ten, "UTF-8");

if (empty($avatar_text) && !empty($ten)) {
    $avatar_text = mb_strtoupper(mb_substr(trim($ten), 0, 1, "UTF-8"), "UTF-8");
}

/* Đếm sản phẩm trong giỏ hàng */
$cartCount = 0;

if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += (int)$item['qty'];
    }
}

/* Đếm đơn hàng */
$orderCount = 0;

$sqlOrderCount = "SELECT COUNT(*) AS total FROM donhang WHERE user_id = ?";
$stmtOrderCount = mysqli_prepare($conn, $sqlOrderCount);
mysqli_stmt_bind_param($stmtOrderCount, "i", $user_id);
mysqli_stmt_execute($stmtOrderCount);

$resultOrderCount = mysqli_stmt_get_result($stmtOrderCount);
$rowOrderCount = mysqli_fetch_assoc($resultOrderCount);
$orderCount = $rowOrderCount['total'] ?? 0;

/* Lọc trạng thái đơn hàng */
$trangThaiHopLe = [
    'Chờ xử lý',
    'Chờ lấy hàng',
    'Đang vận chuyển',
    'Đã giao',
    'Đã huỷ'
];

$locTrangThai = $_GET['trang_thai'] ?? '';

if ($locTrangThai === 'Đã hủy') {
    $locTrangThai = 'Đã huỷ';
}
if (!is_string($locTrangThai) || !in_array($locTrangThai, $trangThaiHopLe, true)) {
    $locTrangThai = '';
}

/* Nếu đang lọc đơn hàng thì tự mở tab đơn hàng */
$requestedTab = $_GET['tab'] ?? '';
$currentTab = $locTrangThai !== '' || $requestedTab === 'donhang'
    ? 'donhang' : ($requestedTab === 'thanhvien' ? 'thanhvien' : 'tongquan');

/* Lấy danh sách đơn hàng */
$orderList = [];

if ($locTrangThai === 'Đã huỷ') {
    $sqlOrders = "SELECT * FROM donhang WHERE user_id = ?
        AND trang_thai IN ('Đã huỷ', 'Đã hủy') ORDER BY id DESC";
    $stmtOrders = mysqli_prepare($conn, $sqlOrders);
    mysqli_stmt_bind_param($stmtOrders, "i", $user_id);
} elseif ($locTrangThai != '') {
    $sqlOrders = "
        SELECT *
        FROM donhang
        WHERE user_id = ?
        AND trang_thai = ?
        ORDER BY id DESC
    ";

    $stmtOrders = mysqli_prepare($conn, $sqlOrders);
    mysqli_stmt_bind_param($stmtOrders, "is", $user_id, $locTrangThai);
} else {
    $sqlOrders = "
        SELECT *
        FROM donhang
        WHERE user_id = ?
        ORDER BY id DESC
    ";

    $stmtOrders = mysqli_prepare($conn, $sqlOrders);
    mysqli_stmt_bind_param($stmtOrders, "i", $user_id);
}

mysqli_stmt_execute($stmtOrders);
$resultOrders = mysqli_stmt_get_result($stmtOrders);

if ($resultOrders) {
    while ($row = mysqli_fetch_assoc($resultOrders)) {
        $orderList[] = $row;
    }
}
/* Chương trình thành viên: dùng cùng hàm với giỏ hàng và thanh toán. */
function tk_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function tk_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}
function tk_date($value): string {
    $time = $value ? strtotime((string)$value) : false;
    return $time === false ? '—' : date('d/m/Y H:i', $time);
}

$member = null;
$memberError = '';
$voucherError = '';
$historyError = '';
$wallet = [];
$benefits = array_fill_keys(array_keys(membership_rules()), []);
$history = [];
$historyCount = 0;
$historyPage = filter_var($_GET['voucher_page'] ?? 1, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 1000000]]) ?: 1;
$historySize = 10;
$historyPages = 1;
$saved = 0;
$pendingSaved = 0;
$deliveredVoucherOrders = 0;
$progressOrders = 0;
$progressSpend = 0;
$rules = membership_rules();

try {
    $member = membership_get($conn, $user_id);
    if ($member['next_tier'] === null) {
        $progressOrders = $progressSpend = 100;
    } else {
        $nextRule = $rules[$member['next_tier']];
        $progressOrders = min(100, (int)floor(100 * $member['orders'] / $nextRule['orders']));
        $progressSpend = min(100, (int)floor(100 * $member['spend'] / $nextRule['spend']));
    }
} catch (Throwable $e) {
    error_log('TT SHOP membership account: ' . $e->getMessage());
    $memberError = 'Chưa thể tải thông tin thành viên. Vui lòng thử lại sau.';
}

/* Kho voucher không giả định giỏ hàng đã đủ tiền.
   Dùng mức tối thiểu của mỗi mã để kiểm tra hạng, thời hạn và lượt còn lại;
   khi thanh toán, hệ thống sẽ kiểm tra giá trị giỏ hàng thực tế. */
if ($member !== null) {
    try {
        $result = $conn->query("SELECT m.*, NOW() AS now_db FROM magiamgia m
                                WHERE trang_thai = 1 ORDER BY hang_toi_thieu, id");
        if (!$result) throw new RuntimeException('Không thể đọc kho voucher.');
        while ($v = $result->fetch_assoc()) {
            $testSubtotal = max(1, (int)$v['don_toi_thieu']);
            $check = voucher_evaluate($conn, $v, $user_id, $member['tier'], $testSubtotal, 0);
            $wallet[] = ['voucher' => $v, 'ok' => $check['ok'], 'message' => $check['message']];
            // Quyền lợi theo từng hạng, lấy từ chương trình còn hiệu lực trong SQL.
            $required = $v['hang_toi_thieu'];
            if (isset($benefits[$required]) &&
                voucher_rule_error($v, $required, $testSubtotal, $v['now_db']) === '') {
                $benefits[$required][] = $v['ma_code'] . ' – Giảm ' . (int)$v['phan_tram_giam'] . '%'
                    . ((int)$v['mien_phi_van_chuyen'] === 1 ? ' + Freeship' : '')
                    . ', đơn từ ' . tk_money($v['don_toi_thieu'])
                    . ', tối đa ' . tk_money($v['giam_toi_da']);
            }
        }
        $result->free();
    } catch (Throwable $e) {
        error_log('TT SHOP voucher wallet: ' . $e->getMessage());
        $wallet = [];
        $benefits = array_fill_keys(array_keys($rules), []);
        $voucherError = 'Chưa thể tải kho mã giảm giá. Vui lòng thử lại sau.';
    }
}

/* Lịch sử voucher: chỉ đọc đơn của tài khoản đang đăng nhập.
   Tiết kiệm thực tế = giảm sản phẩm + giảm vận chuyển của đơn Đã giao.
   Đơn đã hủy vẫn hiển thị lịch sử nhưng không cộng vào tổng tiết kiệm. */
try {
    $stmt = $conn->prepare("SELECT COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN d.trang_thai = 'Đã giao'
            THEN l.tien_giam_gia + l.tien_giam_van_chuyen ELSE 0 END), 0) AS saved,
        COALESCE(SUM(CASE WHEN d.trang_thai IN ('Chờ xử lý','Chờ lấy hàng','Đang vận chuyển')
            THEN l.tien_giam_gia + l.tien_giam_van_chuyen ELSE 0 END), 0) AS pending_saved,
        COALESCE(SUM(CASE WHEN d.trang_thai = 'Đã giao' THEN 1 ELSE 0 END), 0) AS delivered_count
        FROM lichsu_magiamgia l JOIN donhang d ON d.id = l.donhang_id
        WHERE l.user_id = ? AND d.user_id = ?");
    if (!$stmt) throw new RuntimeException('Không thể đọc tổng tiết kiệm.');
    $stmt->bind_param('ii', $user_id, $user_id);
    if (!$stmt->execute()) throw new RuntimeException('Không thể đọc tổng tiết kiệm.');
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $historyCount = (int)$stats['total'];
    $saved = (int)$stats['saved'];
    $pendingSaved = (int)$stats['pending_saved'];
    $deliveredVoucherOrders = (int)$stats['delivered_count'];
    $historyPages = max(1, (int)ceil($historyCount / $historySize));
    $historyPage = min($historyPage, $historyPages);
    $offset = ($historyPage - 1) * $historySize;
    $stmt = $conn->prepare("SELECT l.ma_code, l.tien_giam_gia, l.tien_giam_van_chuyen,
        l.created_at, d.id AS donhang_id, d.ma_don, d.trang_thai, d.hang_thanh_vien
        FROM lichsu_magiamgia l JOIN donhang d ON d.id = l.donhang_id
        WHERE l.user_id = ? AND d.user_id = ?
        ORDER BY l.created_at DESC, l.id DESC LIMIT ? OFFSET ?");
    if (!$stmt) throw new RuntimeException('Không thể đọc lịch sử voucher.');
    $stmt->bind_param('iiii', $user_id, $user_id, $historySize, $offset);
    if (!$stmt->execute()) throw new RuntimeException('Không thể đọc lịch sử voucher.');
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $history[] = $row;
    $stmt->close();
} catch (Throwable $e) {
    error_log('TT SHOP voucher history: ' . $e->getMessage());
    $historyError = 'Chưa thể tải lịch sử và tổng tiết kiệm. Vui lòng thử lại sau.';
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản - TT SHOP</title>

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="./style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./styletaikhoan.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .order-filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 28px;
        }

        .order-filter-bar a {
            text-decoration: none;
            padding: 11px 18px;
            border-radius: 999px;
            background: #fff;
            color: #ff9500;
            font-size: 15px;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(0,0,0,.08);
            transition: .2s;
        }

        .order-filter-bar a:hover,
        .order-filter-bar a.active {
            background: #ff9500;
            color: #fff;
        }

        .order-list-user {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .order-user-card {
            background: #fff;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }

        .order-user-top,
        .order-user-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .order-user-top h3 {
            color: #ff9500;
            margin: 0 0 8px;
            font-size: 22px;
        }

        .order-user-top p {
            margin: 0;
        }

        .order-user-info {
            margin: 18px 0;
            line-height: 1.8;
        }

        .order-user-info p {
            margin: 4px 0;
        }

        .order-status-user {
            background: #fff2cc;
            color: #d48806;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            white-space: nowrap;
        }

        .order-user-bottom strong {
            color: #ff6600;
            font-size: 20px;
        }

        .order-detail-btn {
            background: #ffd84d;
            color: #3155ff;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            white-space: nowrap;
        }

        .order-detail-btn:hover {
            background: #ff9500;
            color: #fff;
        }
        .order-action-buttons {
    display: flex;
    gap: 10px;
    align-items: center;
}

.cancel-order-btn {
    background: #ff4d4f;
    color: white;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
    white-space: nowrap;
}

.cancel-order-form { margin:0; }
.cancel-order-btn { border:0; cursor:pointer; font:inherit; font-weight:bold; }
.cancel-order-btn:disabled { opacity:.65; cursor:not-allowed; }
.cancel-order-notice { padding:12px 16px; margin:0 0 16px; border-radius:8px; background:#fff1f1; color:#a12020; }
.cancel-order-notice.ok { background:#e9f7ef; color:#137a43; }
.cancel-order-btn:hover {
    background: #d9363e;
    color: white;
}

        /* Bổ sung chương trình thành viên - không thay đổi CSS các tab cũ. */
        #thanhvien .tk-stat-grid {display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin:24px 0}
        #thanhvien .tk-stat {padding:15px;background:#f4faf7;border:1px solid #d7eade;border-radius:10px;overflow-wrap:anywhere}
        #thanhvien .tk-stat small {display:block;color:#555;margin-bottom:8px}
        #thanhvien .tk-stat strong {font-size:22px;color:#008f4c}
        #thanhvien .tk-progress {margin:20px 0;padding:18px;background:#f7f7f7;border-radius:12px}
        #thanhvien .tk-progress label {display:block;margin:12px 0 6px;font-size:15px}
        #thanhvien .tk-track {height:10px;background:#dedede;border-radius:10px;overflow:hidden}
        #thanhvien .tk-track span {display:block;height:100%;background:#10a66c;border-radius:10px}
        #thanhvien .tk-note {color:#666;font-size:14px;line-height:1.6;margin:10px 0}
        #thanhvien .tk-error {color:#a12020;background:#fff2f2;padding:12px;border-radius:8px}
        #thanhvien .tk-subtitle {font-size:22px;color:#ff9800;margin:30px 0 14px}
        #thanhvien .tk-wallet {display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
        #thanhvien .tk-voucher {border:1px dashed #9dcdb3;border-radius:10px;padding:15px;min-width:0;overflow-wrap:anywhere}
        #thanhvien .tk-voucher.locked {border-color:#ccc;background:#fafafa}
        #thanhvien .tk-voucher h4 {margin:0 0 8px;font-size:17px;color:#222}
        #thanhvien .tk-voucher p {margin:6px 0;font-size:14px;line-height:1.5}
        #thanhvien .tk-eligible {color:#008f4c;font-weight:700}
        #thanhvien .tk-unavailable {color:#a12020}
        #thanhvien .tk-actions {display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center}
        #thanhvien .tk-action {display:inline-block;border:1px solid #008f4c;color:#008f4c;background:#fff;padding:8px 12px;border-radius:6px;cursor:pointer;font-size:14px;font-weight:600;text-decoration:none}
        #thanhvien .tk-table-wrap {overflow-x:auto}
        #thanhvien .tk-history {border-collapse:collapse;width:100%;font-size:14px;min-width:640px}
        #thanhvien .tk-history th,#thanhvien .tk-history td {text-align:left;padding:12px 8px;border-bottom:1px solid #e4e4e4;overflow-wrap:anywhere}
        #thanhvien .tk-history th {background:#f5f5f5}
        #thanhvien .tk-pages {display:flex;align-items:center;justify-content:center;gap:12px;margin-top:15px;flex-wrap:wrap}
        #thanhvien .level {border:0;background:transparent;min-width:80px}
        #thanhvien .level.active {background:#fff}
        #thanhvien .tk-level-copy {font-size:15px;line-height:1.6}
        #thanhvien .tk-current {color:#008f4c;font-size:12px!important}
        .tk-order-voucher {color:#008f4c}
        @media(max-width:600px){#thanhvien .member-wrapper{padding:12px}#thanhvien .member-box{padding:16px}#thanhvien .member-info{padding-left:0}#thanhvien .member-levels{border-radius:16px;padding:10px}#thanhvien .member-detail{padding:16px}}
    </style>
</head>

<body>

<?php include "header.php"; ?>

<section class="account-page">

    <div class="breadcrumb">
        <div class="container">
            <a href="index.php">Trang chủ</a> / <b>Tài khoản</b>
        </div>
    </div>

    <div class="account-container">

        <aside class="account-sidebar">

            <div class="user-profile">
                <a href="taikhoan.php" class="avatar-link">
                    <div class="avatar-circle">
                        <?php 
                        echo !empty($avatar_text) 
                            ? htmlspecialchars($avatar_text) 
                            : '<i class="fas fa-user"></i>'; 
                        ?>
                    </div>
                </a>

                <p>
                    <i>Xin chào,
                    <span class="user-name-italic">
                        <?php echo !empty($fullName) ? htmlspecialchars($fullName) : 'Khách hàng'; ?>
                    </span></i>
                </p>
            </div>

            <ul id="account-menu">
                <li class="<?php echo $currentTab == 'tongquan' ? 'active' : ''; ?>" data-tab="tongquan" onclick="showTab('tongquan', this)">
                    <i class="fas fa-th-large"></i> Tổng quan tài khoản
                </li>

                <li data-tab="thongtin" onclick="showTab('thongtin', this)">
                    <i class="fas fa-user-edit"></i> Thông tin tài khoản
                </li>

                <li class="<?= $currentTab === 'thanhvien' ? 'active' : '' ?>" data-tab="thanhvien" onclick="showTab('thanhvien', this)">
                    <i class="fas fa-award"></i> Chương trình thành viên
                </li>

                <li class="<?php echo $currentTab == 'donhang' ? 'active' : ''; ?>" data-tab="donhang" onclick="showTab('donhang', this)">
                    <i class="fas fa-shopping-bag"></i> Danh sách đơn hàng
                </li>

                <li data-tab="diachi" onclick="showTab('diachi', this)">
                    <i class="fas fa-map-marker-alt"></i> Sổ địa chỉ (<?php echo $soDiaChi; ?>)
                </li>

                <li class="logout-item">
                    <a href="dangxuat.php">
                        <i class="fas fa-sign-out-alt"></i> Đăng xuất
                    </a>
                </li>
            </ul>

        </aside>

        <main class="account-main">

            <!-- TỔNG QUAN -->
            <div id="tongquan" class="tab-content <?php echo $currentTab == 'tongquan' ? 'active' : ''; ?>">
                <div class="new-dashboard">

                    <div class="dashboard-card card-blue">
                        <div class="card-top">
                            <div class="card-text">
                                <p><?php echo htmlspecialchars($profileText); ?></p>
                            </div>
                            <img src="<?php echo $profileMascot; ?>" class="mascot-img" alt="mascot">
                        </div>

                        <?php if (!$profileComplete) { ?>
                            <button class="card-btn" onclick="goToTab('thongtin')">
                                Cập nhật ngay
                            </button>
                        <?php } ?>

                        <div class="card-bottom">
                            <i class="fas fa-user-circle card-icon"></i>
                            <div class="progress">
                                <div class="progress-fill" style="width: <?php echo $percent; ?>%;"></div>
                            </div>
                            <span><?php echo $percent; ?>%</span>
                        </div>
                    </div>

                    <div class="dashboard-card card-purple">
                        <div class="card-top">
                            <div class="card-text">
                                <p>Bạn đang có <b><?php echo $cartCount; ?></b> sản phẩm trong giỏ hàng!</p>
                            </div>
                            <img src="./imagescategory/Screenshot 2026-05-07 143715.png" class="mascot-img" alt="mascot">
                        </div>

                        <?php if ($cartCount > 0) { ?>
                            <button class="card-btn" onclick="window.location.href='giohang.php'">
                                Thanh toán ngay
                            </button>
                        <?php } else { ?>
                            <button class="card-btn" onclick="window.location.href='index.php'">
                                Mua sắm ngay
                            </button>
                        <?php } ?>

                        <div class="card-bottom">
                            <i class="fas fa-plus-square card-icon"></i>
                            <div class="progress">
                                <div class="progress-fill" style="width:<?php echo $cartCount > 0 ? 100 : 0; ?>%;"></div>
                            </div>
                            <span><?php echo $cartCount > 0 ? 100 : 0; ?>%</span>
                        </div>
                    </div>

                    <div class="dashboard-card card-green">
                        <div class="card-top">
                            <div class="card-text">
                                <p>
                                    <?php
                                    echo $orderCount > 0
                                        ? "Bạn đã có <b>" . $orderCount . "</b> đơn hàng!"
                                        : "Ôi không, tạo đơn hàng đầu tiên ngay thôi!";
                                    ?>
                                </p>
                            </div>
                            <img src="./imagescategory/Screenshot 2026-05-07 143742.png" class="mascot-img" alt="mascot">
                        </div>

                        <?php if ($orderCount > 0) { ?>
                            <button class="card-btn" onclick="goToTab('donhang')">
                                Xem đơn hàng
                            </button>
                        <?php } else { ?>
                            <button class="card-btn" onclick="window.location.href='index.php'">
                                Mua sắm ngay
                            </button>
                        <?php } ?>

                        <div class="card-bottom">
                            <i class="fas fa-shopping-bag card-icon"></i>
                            <div class="progress">
                                <div class="progress-fill" style="width:<?php echo $orderCount > 0 ? 100 : 0; ?>%;"></div>
                            </div>
                            <span><?php echo $orderCount > 0 ? 100 : 0; ?>%</span>
                        </div>
                    </div>

                    <div class="dashboard-card card-red">
                        <div class="card-top">
                            <div class="card-text">
                                <p>Khám phá ưu đãi dành cho thành viên!</p>
                            </div>
                            <img src="./imagescategory/Screenshot 2026-05-07 143753.png" class="mascot-img" alt="mascot">
                        </div>

                        <button class="card-btn" onclick="goToTab('thanhvien')">
                            Xem ưu đãi
                        </button>

                        <div class="card-bottom">
                            <i class="fas fa-fire card-icon"></i>
                            <div class="progress">
                                <div class="progress-fill" style="width:100%;"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- THÔNG TIN -->
            <div id="thongtin" class="tab-content">
                <h2 class="tab-title">THÔNG TIN TÀI KHOẢN</h2>

                <form class="account-form" action="capnhat_tk.php" method="POST" novalidate>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Họ:</label>
                            <input type="text" name="ho" value="<?php echo htmlspecialchars($ho); ?>">
                            <?php if (!empty($accountFieldErrors['profile_ho'])): ?><small class="field-error" data-error-for="ho" aria-live="polite"><?php echo htmlspecialchars($accountFieldErrors['profile_ho'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label>Tên:</label>
                            <input type="text" name="ten" value="<?php echo htmlspecialchars($ten); ?>">
                            <?php if (!empty($accountFieldErrors['profile_ten'])): ?><small class="field-error" data-error-for="ten" aria-live="polite"><?php echo htmlspecialchars($accountFieldErrors['profile_ten'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly style="background:#f1f1f1;">
                    </div>

                    <div class="form-group">
                        <label>Số điện thoại:</label>
                        <input type="text" name="sdt" value="<?php echo htmlspecialchars($sdt); ?>" data-validate="phone" data-label="số điện thoại" required>
                        <?php if (!empty($accountFieldErrors['profile_sdt'])): ?><small class="field-error" data-error-for="sdt" aria-live="polite"><?php echo htmlspecialchars($accountFieldErrors['profile_sdt'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Ngày sinh:</label>
                        <input type="date" name="ngaysinh" value="<?php echo htmlspecialchars($ngaysinh); ?>">
                        <?php if (!empty($accountFieldErrors['profile_ngaysinh'])): ?><small class="field-error" data-error-for="ngaysinh" aria-live="polite"><?php echo htmlspecialchars($accountFieldErrors['profile_ngaysinh'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Địa chỉ:</label>
                        <input type="text" name="diachi" placeholder="Nhập địa chỉ của bạn" value="<?php echo htmlspecialchars($diachi); ?>">
                    </div>

                    <button type="submit" class="update-btn">Lưu thay đổi</button>

                </form>
            </div>

            <!-- THÀNH VIÊN -->
            <div id="thanhvien" class="tab-content <?= $currentTab === 'thanhvien' ? 'active' : '' ?>">
                <div class="member-wrapper">
                    <h2 class="member-title">CHƯƠNG TRÌNH THÀNH VIÊN</h2>
                    <div class="member-box">
                        <?php if ($memberError !== ''): ?>
                            <p class="tk-error"><?= tk_h($memberError) ?></p>
                        <?php else: ?>
                        <div class="member-top">
                            <div class="member-badge"><img src="images/member.png" alt="Thành viên TT SHOP"></div>
                            <div class="member-info">
                                <p>Tổng chi tiêu tích lũy: <span><?= tk_money($member['spend']) ?></span></p>
                                <p>Hạng thành viên hiện tại: <span><?= tk_h($member['tier']) ?></span></p>
                                <p>Số đơn đã giao: <span><?= (int)$member['orders'] ?></span></p>
                                <p>Hạng tiếp theo: <span><?= tk_h($member['next_tier'] ?? 'Đã đạt hạng cao nhất') ?></span></p>
                                <p class="member-more">Xem thêm thông tin hạng thành viên <a href="#member-detail-box">tại đây</a></p>
                            </div>
                        </div>
                        <div class="tk-progress">
                            <?php if ($member['next_tier'] !== null): ?>
                                <strong>Tiến độ lên hạng <?= tk_h($member['next_tier']) ?></strong>
                                <label>Chi tiêu: <?= tk_money($member['spend']) ?> / <?= tk_money($rules[$member['next_tier']]['spend']) ?> (<?= $progressSpend ?>%)</label>
                                <div class="tk-track" role="progressbar" aria-label="Tiến độ chi tiêu" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progressSpend ?>"><span style="width:<?= $progressSpend ?>%"></span></div>
                                <label>Đơn đã giao: <?= (int)$member['orders'] ?> / <?= (int)$rules[$member['next_tier']]['orders'] ?> (<?= $progressOrders ?>%)</label>
                                <div class="tk-track" role="progressbar" aria-label="Tiến độ số đơn" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progressOrders ?>"><span style="width:<?= $progressOrders ?>%"></span></div>
                                <p class="tk-note">Cần chi tiêu thêm <b><?= tk_money($member['spend_needed']) ?></b> hoặc hoàn tất thêm <b><?= (int)$member['orders_needed'] ?> đơn đã giao</b> để nâng hạng. Chỉ cần đạt một trong hai điều kiện.</p>
                            <?php else: ?>
                                <strong class="tk-eligible">Bạn đã đạt hạng Diamond – hạng cao nhất.</strong>
                                <div class="tk-track" style="margin-top:12px"><span style="width:100%"></span></div>
                            <?php endif; ?>
                            <p class="tk-note">Chi tiêu được tính từ tổng thanh toán của đơn đã giao. Hiện chưa có chức năng đổi điểm lấy voucher.</p>
                        </div>
                        <?php endif; ?>

                        <?php if ($historyError === ''): ?>
                        <div class="tk-stat-grid">
                            <div class="tk-stat"><small>Đã tiết kiệm (đơn đã giao)</small><strong><?= tk_money($saved) ?></strong></div>
                            <div class="tk-stat"><small>Tiết kiệm dự kiến (đơn chưa giao)</small><strong><?= tk_money($pendingSaved) ?></strong></div>
                            <div class="tk-stat"><small>Đơn đã giao có voucher</small><strong><?= $deliveredVoucherOrders ?></strong></div>
                        </div>
                        <?php endif; ?>

                        <div class="member-btn-wrap">
                            <button type="button" class="member-btn">NÂNG CẤP TÀI KHOẢN ƯU TIÊN</button>
                        </div>

                        <?php $selectedTier = $member['tier'] ?? 'Member'; ?>
                        <div class="member-levels" aria-label="Xem quyền lợi từng hạng">
                            <?php foreach ($rules as $tier => $rule): ?>
                                <button type="button" class="level <?= $tier === $selectedTier ? 'active' : '' ?>" data-tier="<?= tk_h($tier) ?>" aria-pressed="<?= $tier === $selectedTier ? 'true' : 'false' ?>">
                                    <img src="imagescategory/ttshop.jpg" alt="">
                                    <span><?= tk_h($tier) ?></span>
                                    <?php if ($member !== null && $tier === $member['tier']): ?><span class="tk-current">Hạng hiện tại</span><?php endif; ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <div class="member-detail" id="member-detail-box">
                            <h3 id="tk-level-title">Chi tiết hạng <?= tk_h($selectedTier) ?></h3>
                            <p id="tk-level-condition" class="tk-level-copy"><?= $selectedTier === 'Member' ? 'Hạng mặc định khi đăng ký tài khoản.' : 'Từ ' . tk_money($rules[$selectedTier]['spend']) . ' hoặc ' . (int)$rules[$selectedTier]['orders'] . ' đơn đã giao.' ?></p>
                            <ul id="tk-level-benefits" style="margin-top:16px">
                                <?php if ($voucherError !== '' || $member === null): ?>
                                    <li>Chưa thể tải quyền lợi từ database.</li>
                                <?php elseif (!$benefits[$selectedTier]): ?>
                                    <li>Hiện chưa có mã đang hiệu lực riêng cho hạng này.</li>
                                <?php else: foreach ($benefits[$selectedTier] as $benefit): ?>
                                    <li><?= tk_h($benefit) ?></li>
                                <?php endforeach; endif; ?>
                            </ul>
                            <p class="tk-note" style="font-size:14px">Được sử dụng mã của hạng hiện tại hoặc hạng thấp hơn nếu đủ các điều kiện của mã. Mỗi đơn chỉ dùng một mã.</p>
                        </div>

                        <h3 class="tk-subtitle" id="tk-wallet-title"><i class="fas fa-ticket-alt"></i> Kho mã giảm giá thành viên</h3>
                        <p class="tk-note">Sao chép mã rồi áp dụng tại giỏ hàng. Điều kiện giá trị đơn hàng sẽ được kiểm tra lại khi sử dụng.</p>
                        <?php if ($voucherError !== '' || $member === null): ?>
                            <p class="tk-error"><?= tk_h($voucherError ?: $memberError) ?></p>
                        <?php elseif (!$wallet): ?>
                            <p class="tk-note">Hiện chưa có mã giảm giá hoạt động.</p>
                        <?php else: ?>
                            <div class="tk-wallet">
                                <?php foreach ($wallet as $entry): $v = $entry['voucher']; ?>
                                    <article class="tk-voucher <?= $entry['ok'] ? '' : 'locked' ?>">
                                        <h4><?= tk_h($v['ma_code']) ?> – Giảm <?= (int)$v['phan_tram_giam'] ?>%</h4>
                                        <p><?= tk_h($v['ten_ma']) ?></p>
                                        <p>Hạng tối thiểu: <b><?= tk_h($v['hang_toi_thieu']) ?></b></p>
                                        <p>Đơn từ <?= tk_money($v['don_toi_thieu']) ?> · Tối đa <?= tk_money($v['giam_toi_da']) ?></p>
                                        <?php if ((int)$v['mien_phi_van_chuyen'] === 1): ?><p class="tk-eligible">+ Miễn phí vận chuyển</p><?php endif; ?>
                                        <p>Bắt đầu: <?= $v['ngay_bat_dau'] === null ? 'Ngay khi kích hoạt' : tk_h(tk_date($v['ngay_bat_dau'])) ?></p>
                                        <p>Hết hạn: <?= $v['ngay_ket_thuc'] === null ? 'Không giới hạn thời hạn' : tk_h(tk_date($v['ngay_ket_thuc'])) ?></p>
                                        <?php if ($v['gioi_han_su_dung'] !== null): ?><p>Giới hạn toàn hệ thống: <?= (int)$v['gioi_han_su_dung'] ?> lượt</p><?php endif; ?>
                                        <?php if ($v['gioi_han_moi_user'] !== null): ?><p>Giới hạn mỗi khách: <?= (int)$v['gioi_han_moi_user'] ?> lượt</p><?php endif; ?>
                                        <?php if ($entry['ok']): ?>
                                            <p class="tk-eligible"><i class="fas fa-check-circle"></i> Có thể dùng nếu đơn đạt mức tối thiểu</p>
                                            <div class="tk-actions">
                                                <button type="button" class="tk-action tk-copy" data-code="<?= tk_h($v['ma_code']) ?>">Sao chép mã</button>
                                                <a class="tk-action" href="giohang.php">Đến giỏ hàng</a>
                                            </div>
                                        <?php else: ?>
                                            <p class="tk-unavailable"><i class="fas fa-lock"></i> <?= tk_h($entry['message']) ?></p>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                            <p id="tk-copy-message" class="tk-note" role="status" aria-live="polite"></p>
                        <?php endif; ?>

                        <h3 class="tk-subtitle" id="tk-history-title"><i class="fas fa-history"></i> Lịch sử sử dụng mã giảm giá</h3>
                        <?php if ($historyError !== ''): ?>
                            <p class="tk-error"><?= tk_h($historyError) ?></p>
                        <?php elseif (!$history): ?>
                            <p class="tk-note">Bạn chưa sử dụng mã giảm giá cho đơn hàng nào.</p>
                        <?php else: ?>
                            <div class="tk-table-wrap">
                                <table class="tk-history">
                                    <thead><tr><th>Ngày sử dụng / đơn hàng</th><th>Mã / hạng khi đặt</th><th>Giảm sản phẩm</th><th>Giảm vận chuyển</th><th>Trạng thái</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($history as $row):
                                        $cancelled = in_array($row['trang_thai'], ['Đã huỷ','Đã hủy'], true); ?>
                                        <tr>
                                            <td><?= tk_h(tk_date($row['created_at'])) ?><br><a href="chitietdonhang.php?id=<?= (int)$row['donhang_id'] ?>"><?= tk_h($row['ma_don']) ?></a></td>
                                            <td><b><?= tk_h($row['ma_code']) ?></b><br><?= tk_h($row['hang_thanh_vien'] ?? '—') ?></td>
                                            <td><?= tk_money($row['tien_giam_gia']) ?></td>
                                            <td><?= tk_money($row['tien_giam_van_chuyen']) ?></td>
                                            <td class="<?= $cancelled ? 'tk-unavailable' : ($row['trang_thai'] === 'Đã giao' ? 'tk-eligible' : '') ?>"><?= tk_h($row['trang_thai']) ?><?php if ($cancelled): ?><br><small>Không tính tiết kiệm</small><?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="tk-pages">
                                <?php if ($historyPage > 1): ?><a class="tk-action" href="taikhoan.php?tab=thanhvien&amp;voucher_page=<?= $historyPage - 1 ?>#tk-history-title">Trang trước</a><?php endif; ?>
                                <span>Trang <?= $historyPage ?>/<?= $historyPages ?> · <?= $historyCount ?> lượt ghi nhận</span>
                                <?php if ($historyPage < $historyPages): ?><a class="tk-action" href="taikhoan.php?tab=thanhvien&amp;voucher_page=<?= $historyPage + 1 ?>#tk-history-title">Trang sau</a><?php endif; ?>
                            </div>
                            <p class="tk-note">Đơn hủy không tính vào tổng tiết kiệm và không chiếm lượt sử dụng theo chính sách voucher hiện tại.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ĐƠN HÀNG -->
            <div id="donhang" class="tab-content <?php echo $currentTab == 'donhang' ? 'active' : ''; ?>">
                <h2 class="tab-title">ĐƠN HÀNG CỦA BẠN</h2>
                <?php if ($cancelFlash !== null): ?>
                    <div role="status" class="cancel-order-notice<?= !empty($cancelFlash['ok']) ? ' ok' : '' ?>">
                        <?= tk_h($cancelFlash['message'] ?? '') ?>
                    </div>
                <?php endif; ?>

                <div class="order-filter-bar">

                    <a href="taikhoan.php?tab=donhang"
                       class="<?php echo $locTrangThai == '' ? 'active' : ''; ?>">
                        Tất cả
                    </a>

                    <?php foreach ($trangThaiHopLe as $tt) { ?>
                        <a href="taikhoan.php?tab=donhang&trang_thai=<?php echo urlencode($tt); ?>"
                           class="<?php echo $locTrangThai == $tt ? 'active' : ''; ?>">
                            <?php echo htmlspecialchars($tt); ?>
                        </a>
                    <?php } ?>

                </div>

                <?php if (empty($orderList)) { ?>

                    <div class="empty-state">
                        <i class="fas fa-shopping-cart" style="font-size:40px; color:#ddd;"></i>

                        <?php if ($locTrangThai != '') { ?>
                            <p>Không có đơn hàng ở trạng thái "<?php echo htmlspecialchars($locTrangThai); ?>".</p>
                        <?php } else { ?>
                            <p>Bạn chưa có đơn hàng nào.</p>
                        <?php } ?>

                        <button class="update-btn" onclick="window.location.href='index.php'">
                            Tiếp tục mua sắm
                        </button>
                    </div>

                <?php } else { ?>

                    <div class="order-list-user">

                        <?php foreach ($orderList as $order) { ?>

                            <div class="order-user-card">

                                <div class="order-user-top">
                                    <div>
                                        <h3>Mã đơn: <?php echo htmlspecialchars($order['ma_don'] ?? ('DH' . $order['id'])); ?></h3>
                                        <p>
                                            Ngày đặt:
                                            <?php
                                            $ngayDat = $order['ngay_dat'] ?? $order['created_at'] ?? '';
                                            echo !empty($ngayDat) ? date("d/m/Y H:i", strtotime($ngayDat)) : 'Đang cập nhật';
                                            ?>
                                        </p>
                                    </div>

                                    <span class="order-status-user">
                                        <?php echo htmlspecialchars($order['trang_thai'] ?? 'Chờ xử lý'); ?>
                                    </span>
                                </div>

                                <div class="order-user-info">
                                    <p><b>Người nhận:</b> <?php echo htmlspecialchars($order['ho_ten'] ?? $order['ten_nguoi_nhan'] ?? 'Đang cập nhật'); ?></p>
                                    <p><b>Số điện thoại:</b> <?php echo htmlspecialchars($order['sdt'] ?? $order['so_dien_thoai'] ?? 'Đang cập nhật'); ?></p>
                                    <p><b>Địa chỉ:</b> <?php echo htmlspecialchars($order['dia_chi'] ?? $order['diachi'] ?? 'Đang cập nhật'); ?></p>
                                    <p><b>Thanh toán:</b> <?php echo htmlspecialchars($order['phuong_thuc'] ?? 'COD'); ?></p>
                                    <?php if (!empty($order['ma_giam_gia'])): ?>
                                        <p class="tk-order-voucher"><b>Mã giảm giá:</b> <?= tk_h($order['ma_giam_gia']) ?> · Giảm <?= tk_money($order['tien_giam_gia'] ?? 0) ?></p>
                                    <?php endif; ?>
                                </div>

                                <div class="order-user-bottom">
                                    <strong>
                                        Tổng tiền:
                                        <?php echo number_format((int)($order['tong_cong'] ?? $order['tong_tien'] ?? 0), 0, ',', '.'); ?>đ
                                    </strong>
<div class="order-action-buttons">

    <a href="chitietdonhang.php?id=<?php echo (int)$order['id']; ?>" class="order-detail-btn">
        Xem chi tiết
    </a>

    <?php if (in_array($order['trang_thai'] ?? '', ['Chờ xử lý', 'Chờ lấy hàng'])) { ?>
<form class="cancel-order-form" method="POST" action="huydonhang.php"
      onsubmit="return confirmCancelOrder(this)">
    <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
    <input type="hidden" name="csrf_token" value="<?= tk_h($csrfToken) ?>">
    <button type="submit" class="cancel-order-btn">Huỷ đơn</button>
</form>
    <?php } ?>

</div>
                                </div>

                            </div>

                        <?php } ?>

                    </div>

                <?php } ?>
            </div>

            <!-- ĐỊA CHỈ -->
            <div id="diachi" class="tab-content">

                <h2 class="tab-title">ĐỊA CHỈ CỦA BẠN</h2>
                <?php if (!empty($accountFieldErrors['address_action'])): ?><small class="field-error" aria-live="polite"><?php echo htmlspecialchars($accountFieldErrors['address_action'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>

                <button class="add-address-btn" onclick="openAddressModal()">
                    Thêm địa chỉ
                </button>

                <div class="address-card">

                    <div class="address-item">

                        <div class="address-left">

                            <p>
                                <b>Họ tên:</b>
                                <span class="address-name">
                                    <?php echo htmlspecialchars($ho . ' ' . $ten); ?>
                                </span>

                                <span class="default-address">
                                    <i class="fas fa-check-circle"></i>
                                    Địa chỉ mặc định
                                </span>
                            </p>

                            <p>
                                <b>Địa chỉ:</b>
                                <span class="address-text">
                                    <?php echo htmlspecialchars($diachi); ?>, Vietnam
                                </span>
                            </p>

                            <p>
                                <b>Số điện thoại:</b>
                                <span class="address-phone">
                                    <?php echo htmlspecialchars($sdt); ?>
                                </span>
                            </p>

                        </div>

                        <div class="address-actions">
                            <a href="#" class="edit-address" onclick="openEditDefaultAddressModal(event)">
                                Chỉnh sửa địa chỉ
                            </a>
                        </div>

                    </div>

                    <?php foreach ($addressList as $addr) { ?>

                        <div class="address-item">

                            <div class="address-left">

                                <p>
                                    <b>Họ tên:</b>
                                    <span class="address-name">
                                        <?php echo htmlspecialchars($addr['ho_ten']); ?>
                                    </span>
                                </p>

                                <p>
                                    <b>Địa chỉ:</b>
                                    <span class="address-text">
                                        <?php echo htmlspecialchars($addr['dia_chi']); ?>,
                                        <?php echo htmlspecialchars($addr['quoc_gia']); ?>
                                    </span>
                                </p>

                                <p>
                                    <b>Số điện thoại:</b>
                                    <span class="address-phone">
                                        <?php echo htmlspecialchars($addr['so_dien_thoai']); ?>
                                    </span>
                                </p>

                            </div>

                            <div class="address-actions">
                                <a href="suadiachi.php?id=<?php echo (int)$addr['id']; ?>" class="edit-address">
                                    Chỉnh sửa địa chỉ
                                </a>

                                <form action="xoadiachi.php" method="POST" class="address-delete-form">
                                    <input type="hidden" name="id" value="<?php echo (int)$addr['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="submit" class="delete-address" onclick="return confirm('Bạn có chắc muốn xóa địa chỉ này không?')">Xóa</button>
                                </form>
                            </div>

                        </div>

                    <?php } ?>

                </div>

                <div class="address-modal" id="addressModal">
                    <div class="address-modal-box">

                        <div class="address-modal-header">
                            <h3>THÊM ĐỊA CHỈ MỚI</h3>
                            <span onclick="closeAddressModal()">&times;</span>
                        </div>

                        <form action="themdiachi.php" method="POST" class="address-form" novalidate>

                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="text" name="ho_ten" placeholder="Họ tên" required>
                            <input type="text" name="sdt" placeholder="Số điện thoại" data-validate="phone" data-label="số điện thoại" required>
                            <?php if (!empty($accountFieldErrors['address_sdt'])): ?><small class="field-error" data-error-for="sdt" aria-live="polite"><?php echo htmlspecialchars($accountFieldErrors['address_sdt'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                            <input type="text" name="cong_ty" placeholder="Công ty">
                            <input type="text" name="dia_chi" placeholder="Địa chỉ" required>

                            <select name="quoc_gia" required>
                                <option value="">-- Chọn quốc gia --</option>
                                <option value="Vietnam">Việt Nam</option>
                                <option value="Japan">Nhật Bản</option>
                                <option value="Korea">Hàn Quốc</option>
                            </select>

                            <input type="text" name="ma_zip" placeholder="Mã Zip">

                            <label class="default-check">
                                <input type="checkbox" name="mac_dinh">
                                Đặt là địa chỉ mặc định?
                            </label>

                            <div class="address-modal-actions">
                                <button type="button" class="cancel-btn" onclick="closeAddressModal()">Hủy</button>
                                <button type="submit" class="save-address-btn">Thêm địa chỉ</button>
                            </div>

                        </form>

                    </div>
                </div>

                <div class="address-modal" id="editDefaultAddressModal">
                    <div class="address-modal-box">

                        <div class="address-modal-header">
                            <h3>CHỈNH SỬA ĐỊA CHỈ</h3>
                            <span onclick="closeEditDefaultAddressModal()">&times;</span>
                        </div>

                        <form action="capnhat_tk.php" method="POST" class="address-form" novalidate>

                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($fullName); ?>" placeholder="Họ tên" required>
                            <?php if (!empty($accountFieldErrors['edit_address_name'])): ?><small class="field-error" data-error-for="full_name" aria-live="polite"><?php echo htmlspecialchars($accountFieldErrors['edit_address_name'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                            <input type="text" name="sdt" value="<?php echo htmlspecialchars($sdt); ?>" placeholder="Số điện thoại" data-validate="phone" data-label="số điện thoại" required>
                            <?php if (!empty($accountFieldErrors['edit_address_sdt'])): ?><small class="field-error" data-error-for="sdt" aria-live="polite"><?php echo htmlspecialchars($accountFieldErrors['edit_address_sdt'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
                            <input type="text" name="diachi" value="<?php echo htmlspecialchars($diachi); ?>" placeholder="Địa chỉ" required>

                            <label class="default-check">
                                <input type="checkbox" checked disabled>
                                Đặt là địa chỉ mặc định?
                            </label>

                            <div class="address-modal-actions">
                                <button type="button" class="cancel-btn" onclick="closeEditDefaultAddressModal()">Hủy</button>
                                <button type="submit" class="save-address-btn">Cập nhật địa chỉ</button>
                            </div>

                        </form>

                    </div>
                </div>

            </div>

        </main>
    </div>

</section>

<?php include "footer.php"; ?>

<script>

function showTab(tabId, element) { // Hàm chuyển tab nội dung

    document.querySelectorAll(".tab-content").forEach(function(tab) {
        tab.classList.remove("active"); // Ẩn tất cả tab
    });

    document.querySelectorAll(".account-sidebar li").forEach(function(li) {
        li.classList.remove("active"); // Xóa trạng thái active của menu sidebar
    });

    document.getElementById(tabId).classList.add("active"); 
    // Hiển thị tab được chọn

    if (element) { 
        // Nếu có phần tử menu được click

        element.classList.add("active"); 
        // Thêm active cho menu hiện tại

    } else {

        const menuItem = document.querySelector(
            '.account-sidebar li[data-tab="' + tabId + '"]'
        );
        // Tìm menu theo data-tab

        if (menuItem) {
            menuItem.classList.add("active");
            // Thêm active cho menu tìm được
        }
    }
}

function goToTab(tabId) { // Hàm chuyển tab bằng nút bấm

    showTab(tabId, null); 
    // Gọi hàm showTab để mở tab

    window.scrollTo({
        top: 0, // Cuộn lên đầu trang
        behavior: "smooth" // Hiệu ứng cuộn mượt
    });
}

// Nội dung quyền lợi lấy từ SQL, không hard-code % giảm giá.
const tkRules = <?= json_encode($rules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const tkBenefits = <?= json_encode($benefits, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const tkBenefitsAvailable = <?= ($voucherError === '' && $member !== null) ? 'true' : 'false' ?>;
const tkMoney = value => Number(value).toLocaleString('vi-VN') + 'đ';

document.querySelectorAll('#thanhvien .level[data-tier]').forEach(button => {
    button.addEventListener('click', () => {
        const tier = button.dataset.tier;
        if (!Object.prototype.hasOwnProperty.call(tkRules, tier)) return;
        document.querySelectorAll('#thanhvien .level[data-tier]').forEach(item => {
            item.classList.toggle('active', item === button);
            item.setAttribute('aria-pressed', item === button ? 'true' : 'false');
        });
        document.getElementById('tk-level-title').textContent = 'Chi tiết hạng ' + tier;
        document.getElementById('tk-level-condition').textContent = tier === 'Member'
            ? 'Hạng mặc định khi đăng ký tài khoản.'
            : 'Từ ' + tkMoney(tkRules[tier].spend) + ' hoặc ' + tkRules[tier].orders + ' đơn đã giao.';
        const list = document.getElementById('tk-level-benefits');
        list.replaceChildren();
        const items = !tkBenefitsAvailable ? ['Chưa thể tải quyền lợi từ database.']
            : (tkBenefits[tier].length ? tkBenefits[tier] : ['Hiện chưa có mã đang hiệu lực riêng cho hạng này.']);
        items.forEach(text => {
            const li = document.createElement('li');
            li.textContent = text;
            list.appendChild(li);
        });
    });
});

document.querySelectorAll('#thanhvien .tk-copy').forEach(button => {
    button.addEventListener('click', async () => {
        const code = button.dataset.code;
        const message = document.getElementById('tk-copy-message');
        try {
            if (!navigator.clipboard || !window.isSecureContext) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(code);
            message.textContent = 'Đã sao chép mã ' + code + '. Dán mã vào ô giảm giá ở giỏ hàng.';
        } catch (e) {
            message.textContent = 'Hãy sao chép thủ công mã ' + code + ' rồi nhập tại giỏ hàng.';
        }
    });
});

function openAddressModal() {

    document.getElementById("addressModal")
    .classList.add("active");
    // Hiển thị popup thêm địa chỉ

}

function closeAddressModal() {

    document.getElementById("addressModal")
    .classList.remove("active");
    // Ẩn popup thêm địa chỉ

}

function openEditDefaultAddressModal(event) {

    event.preventDefault();
    // Ngăn thẻ a load lại trang

    document.getElementById("editDefaultAddressModal")
    .classList.add("active");
    // Hiển thị popup chỉnh sửa địa chỉ

}

function closeEditDefaultAddressModal() {

    document.getElementById("editDefaultAddressModal")
    .classList.remove("active");
    // Ẩn popup chỉnh sửa địa chỉ

}

function confirmCancelOrder(form) {
    // Giữ popup SweetAlert; nếu CDN lỗi, dùng hộp xác nhận của trình duyệt.
    if (typeof Swal === 'undefined') {
        return window.confirm('Bạn có chắc chắn muốn huỷ đơn hàng này không?');
    }
    const button = form.querySelector('button[type="submit"]');
    if (button.disabled) return false;
    button.disabled = true;
    Swal.fire({
        title: 'Huỷ đơn hàng?',
        text: 'Bạn có chắc chắn muốn huỷ đơn hàng này không?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e53935',
        cancelButtonColor: '#999',
        confirmButtonText: 'Đồng ý huỷ',
        cancelButtonText: 'Không',
        borderRadius: '18px'
    }).then((result) => {
        if (result.isConfirmed) {
            // Gửi form POST có CSRF, không gọi lại onsubmit.
            HTMLFormElement.prototype.submit.call(form);
        } else {
            button.disabled = false;
        }
    }).catch(() => {
        button.disabled = false;
    });
    return false;
}

</script>
</body>
</html>