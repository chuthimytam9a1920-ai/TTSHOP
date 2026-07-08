<?php
include "check_admin.php";
include "../connect.php";

$keyword = trim($_GET['keyword'] ?? '');
$rankFilter = $_GET['rank'] ?? 'all';

$whereUser = "";

if ($keyword !== '') {
    $safeKeyword = mysqli_real_escape_string($conn, $keyword);

    if (is_numeric($keyword)) {
        $whereUser = "
            WHERE users.id = " . (int)$keyword . "
            OR users.sdt LIKE '%$safeKeyword%'
            OR CONCAT(users.ho, ' ', users.ten) LIKE '%$safeKeyword%'
            OR users.email LIKE '%$safeKeyword%'
        ";
    } else {
        $whereUser = "
            WHERE CONCAT(users.ho, ' ', users.ten) LIKE '%$safeKeyword%'
            OR users.ho LIKE '%$safeKeyword%'
            OR users.ten LIKE '%$safeKeyword%'
            OR users.email LIKE '%$safeKeyword%'
            OR users.sdt LIKE '%$safeKeyword%'
        ";
    }
}

$rankHaving = "";

if ($rankFilter !== 'all') {
    $safeRank = mysqli_real_escape_string($conn, $rankFilter);
    $rankHaving = "HAVING hang_khach = '$safeRank'";
}

$customers = mysqli_query($conn, "
    SELECT 
        users.*,

        COUNT(
            CASE 
                WHEN donhang.trang_thai = 'Đã giao' 
                THEN donhang.id 
            END
        ) AS so_lan_mua,

        COALESCE(SUM(
            CASE 
                WHEN donhang.trang_thai = 'Đã giao' 
                THEN donhang.tong_cong 
                ELSE 0 
            END
        ), 0) AS tong_da_mua,

        CASE
            WHEN COUNT(CASE WHEN donhang.trang_thai = 'Đã giao' THEN donhang.id END) > 20
              OR COALESCE(SUM(CASE WHEN donhang.trang_thai = 'Đã giao' THEN donhang.tong_cong ELSE 0 END), 0) >= 6000000
            THEN 'Diamond'

            WHEN COUNT(CASE WHEN donhang.trang_thai = 'Đã giao' THEN donhang.id END) >= 11
              OR COALESCE(SUM(CASE WHEN donhang.trang_thai = 'Đã giao' THEN donhang.tong_cong ELSE 0 END), 0) >= 3000000
            THEN 'Platinum'

            WHEN COUNT(CASE WHEN donhang.trang_thai = 'Đã giao' THEN donhang.id END) >= 6
              OR COALESCE(SUM(CASE WHEN donhang.trang_thai = 'Đã giao' THEN donhang.tong_cong ELSE 0 END), 0) >= 1500000
            THEN 'Gold'

            WHEN COUNT(CASE WHEN donhang.trang_thai = 'Đã giao' THEN donhang.id END) >= 3
              OR COALESCE(SUM(CASE WHEN donhang.trang_thai = 'Đã giao' THEN donhang.tong_cong ELSE 0 END), 0) >= 500000
            THEN 'Silver'

            ELSE 'Member'
        END AS hang_khach

    FROM users
    LEFT JOIN donhang ON donhang.user_id = users.id
    $whereUser
    GROUP BY users.id
    $rankHaving
    ORDER BY users.id DESC
");

function goiYChamSoc($hang) {
    switch ($hang) {
        case 'Diamond':
            return 'Chăm sóc VIP, quà tặng riêng';
        case 'Platinum':
            return 'Voucher 15% + freeship';
        case 'Gold':
            return 'Voucher 10% + ưu tiên tư vấn';
        case 'Silver':
            return 'Gửi voucher 5%';
        default:
            return 'Gửi mã giảm giá mua lại';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quản lý khách hàng</title>

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
        .topbar{margin-bottom:25px}
        .filter-bar{display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
        .filter-bar select,.filter-bar input{height:42px;border:1px solid #ddd;border-radius:8px;padding:0 12px}
        .filter-bar input{width:380px}
        .filter-bar button,.filter-bar a{height:42px;display:inline-flex;align-items:center;justify-content:center;padding:0 16px;border-radius:8px;border:0;font-weight:bold;text-decoration:none;cursor:pointer}
        .filter-bar button{background:#15985d;color:white}
        .filter-bar a{background:#777;color:white}
        .table-wrap{width:100%;overflow-x:auto}
        table{width:100%;min-width:1300px;border-collapse:collapse}
        th,td{padding:13px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
        th{background:#f0f0f0}
        .detail{color:#0b72e7;font-weight:bold;text-decoration:none}
        .delete{color:red;font-weight:bold;text-decoration:none}
        .buy-count{background:#dff7ff;color:#0072a3;padding:6px 12px;border-radius:20px;font-weight:bold}
        .rank{display:inline-block;padding:7px 12px;border-radius:20px;font-weight:bold}
        .Member{background:#e9f7ef;color:#15985d}
        .Silver{background:#eeeeee;color:#666}
        .Gold{background:#fff0c7;color:#b66a00}
        .Platinum{background:#e8f1ff;color:#0b72e7}
        .Diamond{background:#ffe1f1;color:#c2185b}
    </style>
</head>

<body>

<div class="layout">

<?php
$page = "customers";
include "sidebar.php";
?>

    <main class="main">
        <div class="topbar">
            <h1>Quản lý khách hàng</h1>
        </div>

        <div class="box">

            <form method="GET" class="filter-bar">
                <select name="rank">
                    <option value="all" <?php echo ($rankFilter == 'all') ? 'selected' : ''; ?>>Tất cả hạng</option>
                    <option value="Member" <?php echo ($rankFilter == 'Member') ? 'selected' : ''; ?>>Member</option>
                    <option value="Silver" <?php echo ($rankFilter == 'Silver') ? 'selected' : ''; ?>>Silver</option>
                    <option value="Gold" <?php echo ($rankFilter == 'Gold') ? 'selected' : ''; ?>>Gold</option>
                    <option value="Platinum" <?php echo ($rankFilter == 'Platinum') ? 'selected' : ''; ?>>Platinum</option>
                    <option value="Diamond" <?php echo ($rankFilter == 'Diamond') ? 'selected' : ''; ?>>Diamond</option>
                </select>

                <input
                    type="text"
                    name="keyword"
                    placeholder="Tìm theo ID, tên, SĐT hoặc email..."
                    value="<?php echo htmlspecialchars($keyword); ?>"
                >

                <button type="submit">Tìm kiếm</button>
                <a href="customers.php">Làm mới</a>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>SĐT</th>
                            <th>Số đơn đã giao</th>
                            <th>Tổng đã mua</th>
                            <th>Hạng khách</th>
                            <th>Gợi ý chăm sóc</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($customers && mysqli_num_rows($customers) > 0) { ?>
                            <?php while ($row = mysqli_fetch_assoc($customers)) {
                                $hang = $row['hang_khach'];
                            ?>
                                <tr>
                                    <td><?php echo (int)$row['id']; ?></td>
                                    <td><?php echo htmlspecialchars(($row['ho'] ?? '') . ' ' . ($row['ten'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($row['email'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($row['sdt'] ?? ''); ?></td>
                                    <td><span class="buy-count"><?php echo (int)$row['so_lan_mua']; ?></span></td>
                                    <td><?php echo number_format((int)$row['tong_da_mua'], 0, ',', '.'); ?>đ</td>
                                    <td><span class="rank <?php echo htmlspecialchars($hang); ?>"><?php echo htmlspecialchars($hang); ?></span></td>
                                    <td><?php echo htmlspecialchars(goiYChamSoc($hang)); ?></td>
                                    <td>
                                        <a class="detail" href="customer_detail.php?id=<?php echo (int)$row['id']; ?>">Chi tiết</a>
                                        |
                                        <a class="delete" href="delete_customer.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Xóa khách hàng này?')">Xóa</a>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="9" style="text-align:center;">Không tìm thấy khách hàng phù hợp</td>
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