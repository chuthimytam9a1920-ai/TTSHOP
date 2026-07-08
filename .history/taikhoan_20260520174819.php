<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "connect.php";

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

if (!empty($ho))        $percent += 15;
if (!empty($ten))       $percent += 15;
if (!empty($email))     $percent += 15;
if (!empty($sdt))       $percent += 15;
if (!empty($ngaysinh))  $percent += 20;
if (!empty($diachi))    $percent += 20;

if ($percent >= 100) {
    $percent = 100;
    $profileText   = "Tuyệt vời, thông tin của bạn đã đủ";
    $profileMascot = "./imagescategory/mascot-happy.png";
    $profileBtn    = "Xem chi tiết";
} else {
    $profileText   = "Ôi không, bạn chưa cập nhật đủ thông tin!";
    $profileMascot = "./imagescategory/Screenshot 2026-05-07 143553.png";
    $profileBtn    = "Cập nhật ngay";
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

if (!in_array($locTrangThai, $trangThaiHopLe)) {
    $locTrangThai = '';
}

/* Nếu đang lọc đơn hàng thì tự mở tab đơn hàng */
$currentTab = ($locTrangThai != '' || ($_GET['tab'] ?? '') === 'donhang') ? 'donhang' : 'tongquan';

/* Lấy danh sách đơn hàng */
$orderList = [];

if ($locTrangThai != '') {
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
$sqlMember = "
    SELECT 
        COUNT(*) AS so_lan_mua,
        COALESCE(SUM(tong_cong), 0) AS tong_chi_tieu
    FROM donhang
    WHERE user_id = ?
    AND trang_thai = 'Đã giao'
";

$stmtMember = mysqli_prepare($conn, $sqlMember);
mysqli_stmt_bind_param($stmtMember, "i", $user_id);
mysqli_stmt_execute($stmtMember);

$resultMember = mysqli_stmt_get_result($stmtMember);
$member = mysqli_fetch_assoc($resultMember);

$soLanMua = (int)($member['so_lan_mua'] ?? 0);
$tongChiTieu = (int)($member['tong_chi_tieu'] ?? 0);

if ($soLanMua > 20 || $tongChiTieu >= 6000000) {
    $hangKhach = "Diamond";
    $hangTiepTheo = "Đã đạt hạng cao nhất";
    $diemCanLenHang = 0;
} elseif ($soLanMua >= 11 || $tongChiTieu >= 3000000) {
    $hangKhach = "Platinum";
    $hangTiepTheo = "Diamond";
    $diemCanLenHang = 6000000 - $tongChiTieu;
} elseif ($soLanMua >= 6 || $tongChiTieu >= 1500000) {
    $hangKhach = "Gold";
    $hangTiepTheo = "Platinum";
    $diemCanLenHang = 3000000 - $tongChiTieu;
} elseif ($soLanMua >= 3 || $tongChiTieu >= 500000) {
    $hangKhach = "Silver";
    $hangTiepTheo = "Gold";
    $diemCanLenHang = 1500000 - $tongChiTieu;
} else {
    $hangKhach = "Member";
    $hangTiepTheo = "Silver";
    $diemCanLenHang = 500000 - $tongChiTieu;
}

if ($diemCanLenHang < 0) {
    $diemCanLenHang = 0;
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

.cancel-order-btn:hover {
    background: #d9363e;
    color: white;
}
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

                <li data-tab="thanhvien" onclick="showTab('thanhvien', this)">
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

                        <button class="card-btn" onclick="goToTab('thongtin')">
                            <?php echo htmlspecialchars($profileBtn); ?>
                        </button>

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
                                <p>Chương trình khuyến mãi đang diễn ra!</p>
                            </div>
                            <img src="./imagescategory/Screenshot 2026-05-07 143753.png" class="mascot-img" alt="mascot">
                        </div>

                        <button class="card-btn" onclick="window.location.href='index.php'">
                            Mua sắm ngay
                        </button>

                        <div class="card-bottom">
                            <i class="fas fa-fire card-icon"></i>
                            <div class="progress">
                                <div class="progress-fill" style="width:100%;"></div>
                            </div>
                            <span>100%</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- THÔNG TIN -->
            <div id="thongtin" class="tab-content">
                <h2 class="tab-title">THÔNG TIN TÀI KHOẢN</h2>

                <form class="account-form" action="capnhat_tk.php" method="POST">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Họ:</label>
                            <input type="text" name="ho" value="<?php echo htmlspecialchars($ho); ?>">
                        </div>

                        <div class="form-group">
                            <label>Tên:</label>
                            <input type="text" name="ten" value="<?php echo htmlspecialchars($ten); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" readonly style="background:#f1f1f1;">
                    </div>

                    <div class="form-group">
                        <label>Số điện thoại:</label>
                        <input type="text" name="sdt" value="<?php echo htmlspecialchars($sdt); ?>">
                    </div>

                    <div class="form-group">
                        <label>Ngày sinh:</label>
                        <input type="date" name="ngaysinh" value="<?php echo htmlspecialchars($ngaysinh); ?>">
                    </div>

                    <div class="form-group">
                        <label>Địa chỉ:</label>
                        <input type="text" name="diachi" placeholder="Nhập địa chỉ của bạn" value="<?php echo htmlspecialchars($diachi); ?>">
                    </div>

                    <button type="submit" class="update-btn">Lưu thay đổi</button>

                </form>
            </div>

            <!-- THÀNH VIÊN -->
            <div id="thanhvien" class="tab-content">

                <div class="member-wrapper">

                    <h2 class="member-title">CHƯƠNG TRÌNH THÀNH VIÊN</h2>

                    <div class="member-box">

                        <div class="member-top">

                            <div class="member-badge">
                                <img src="images/member.png" alt="">
                            </div>

                            <div class="member-info">
<p>Tổng chi tiêu tích lũy: 
    <span><?php echo number_format($tongChiTieu, 0, ',', '.'); ?>đ</span>
</p>

<p>Hạng thành viên hiện tại: 
    <span><?php echo $hangKhach; ?></span>
</p>

<p>Điểm thành viên khả dụng: 
    <span><?php echo number_format($tongChiTieu, 0, ',', '.'); ?></span>
</p>

<p>Điểm cần để lên hạng: 
    <span><?php echo number_format($diemCanLenHang, 0, ',', '.'); ?></span>
</p>

<p>Hạng tiếp theo: 
    <span><?php echo $hangTiepTheo; ?></span>
</p>
                                <p class="member-more">
                                    Xem thêm thông tin Điểm thành viên và Hạng thành viên
                                    <a href="#">tại đây</a>
                                </p>

                            </div>

                        </div>

                        <div class="member-btn-wrap">
                            <button class="member-btn">NÂNG CẤP TÀI KHOẢN ƯU TIÊN</button>
                        </div>

                        <div class="member-levels">

                            <div class="level active" onclick="showMemberLevel('Member', '0đ - 499.999đ', this)">
                                <img src="imagescategory/ttshop.jpg">
                                <span>Member</span>
                            </div>

                            <div class="level" onclick="showMemberLevel('Silver', '500.000đ - 1.499.000đ', this)">
                                <img src="imagescategory/ttshop.jpg">
                                <span>Silver</span>
                            </div>

                            <div class="level" onclick="showMemberLevel('Gold', '1.500.000đ - 2.999.999đ', this)">
                                <img src="imagescategory/ttshop.jpg">
                                <span>Gold</span>
                            </div>

                            <div class="level" onclick="showMemberLevel('Platinum', '3.000.000đ - 5.999.999đ', this)">
                                <img src="imagescategory/ttshop.jpg">
                                <span>Platinum</span>
                            </div>

                            <div class="level" onclick="showMemberLevel('Diamond', '6.000.000đ trở lên', this)">
                                <img src="imagescategory/ttshop.jpg">
                                <span>Diamond</span>
                            </div>

                        </div>

                        <div class="member-detail" id="member-detail-box">
                            <h3>Chi tiết hạng Member</h3>
                            <p>Chi tiêu tích lũy từ <b>0đ - 499.999đ</b></p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ĐƠN HÀNG -->
            <div id="donhang" class="tab-content <?php echo $currentTab == 'donhang' ? 'active' : ''; ?>">
                <h2 class="tab-title">ĐƠN HÀNG CỦA BẠN</h2>

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
<a href="#"
   class="cancel-order-btn"
   onclick="confirmCancelOrder(<?php echo (int)$order['id']; ?>)">
    Huỷ đơn
</a>
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

                                <a href="xoadiachi.php?id=<?php echo (int)$addr['id']; ?>"
                                   class="delete-address"
                                   onclick="return confirm('Bạn có chắc muốn xóa địa chỉ này không?')">
                                    Xóa
                                </a>
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

                        <form action="themdiachi.php" method="POST" class="address-form">

                            <input type="text" name="ho_ten" placeholder="Họ tên" required>
                            <input type="text" name="sdt" placeholder="Số điện thoại" required>
                            <input type="text" name="cong_ty" placeholder="Công ty">
                            <input type="text" name="dia_chi" placeholder="Địa chỉ" required>

                            <select name="quoc_gia" required>
                                <option value="">-- Please Select --</option>
                                <option value="Vietnam">Vietnam</option>
                                <option value="Japan">Japan</option>
                                <option value="Korea">Korea</option>
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

                        <form action="capnhat_tk.php" method="POST" class="address-form">

                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($fullName); ?>" placeholder="Họ tên" required>
                            <input type="text" name="sdt" value="<?php echo htmlspecialchars($sdt); ?>" placeholder="Số điện thoại" required>
                            <input type="text" name="cong_ty" placeholder="Công ty">
                            <input type="text" name="diachi" value="<?php echo htmlspecialchars($diachi); ?>" placeholder="Địa chỉ" required>

                            <select name="quoc_gia" required>
                                <option value="Vietnam">Vietnam</option>
                                <option value="Japan">Japan</option>
                                <option value="Korea">Korea</option>
                            </select>

                            <input type="text" name="ma_zip" placeholder="Mã Zip">

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
function showTab(tabId, element) {
    document.querySelectorAll(".tab-content").forEach(function(tab) {
        tab.classList.remove("active");
    });

    document.querySelectorAll(".account-sidebar li").forEach(function(li) {
        li.classList.remove("active");
    });

    document.getElementById(tabId).classList.add("active");

    if (element) {
        element.classList.add("active");
    } else {
        const menuItem = document.querySelector('.account-sidebar li[data-tab="' + tabId + '"]');
        if (menuItem) {
            menuItem.classList.add("active");
        }
    }
}

function goToTab(tabId) {
    showTab(tabId, null);
    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });
}

function showMemberLevel(levelName, moneyRange, element) {
    document.querySelectorAll(".level").forEach(function(item) {
        item.classList.remove("active");
    });

    element.classList.add("active");

    document.getElementById("member-detail-box").innerHTML = `
        <h3>Chi tiết hạng ${levelName}</h3>
        <p>Chi tiêu tích lũy từ <b>${moneyRange}</b></p>
    `;
}

function openAddressModal() {
    document.getElementById("addressModal").classList.add("active");
}

function closeAddressModal() {
    document.getElementById("addressModal").classList.remove("active");
}

function openEditDefaultAddressModal(event) {
    event.preventDefault();
    document.getElementById("editDefaultAddressModal").classList.add("active");
}

function closeEditDefaultAddressModal() {
    document.getElementById("editDefaultAddressModal").classList.remove("active");
}
function confirmCancelOrder(orderId) {

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

            window.location.href =
                'huydonhang.php?id=' + orderId;
        }
    });
}
</script>

</body>
</html>