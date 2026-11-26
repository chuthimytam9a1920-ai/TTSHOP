<?php
require_once __DIR__ . '/../payment_helpers.php';
include "check_admin.php";
include "../connect.php";

$productCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM products");
$customerCountResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM users");
$orderStatusResult = mysqli_query($conn, "SELECT trang_thai, COUNT(*) AS total FROM donhang GROUP BY trang_thai");

if (!$productCountResult || !$customerCountResult || !$orderStatusResult) {
    die("Không thể tải dữ liệu tổng quan quản trị.");
}

$productCount = (int)mysqli_fetch_assoc($productCountResult)['total'];
$customerCount = (int)mysqli_fetch_assoc($customerCountResult)['total'];
$orderCounts = [];
while ($row = mysqli_fetch_assoc($orderStatusResult)) {
    $orderCounts[$row['trang_thai']] = (int)$row['total'];
}

$knownOrderCount = ($orderCounts['Chờ xử lý'] ?? 0)
    + ($orderCounts['Chờ lấy hàng'] ?? 0)
    + ($orderCounts['Đang vận chuyển'] ?? 0)
    + ($orderCounts['Đã giao'] ?? 0)
    + ($orderCounts['Đã hủy'] ?? 0)
    + ($orderCounts['Đã huỷ'] ?? 0);
$allOrderCount = array_sum($orderCounts);
$statusChart = [
    ['label' => 'Chờ xử lý / lấy hàng', 'count' => ($orderCounts['Chờ xử lý'] ?? 0) + ($orderCounts['Chờ lấy hàng'] ?? 0), 'color' => '#f0a329'],
    ['label' => 'Đang vận chuyển', 'count' => $orderCounts['Đang vận chuyển'] ?? 0, 'color' => '#4688e8'],
    ['label' => 'Đã giao', 'count' => $orderCounts['Đã giao'] ?? 0, 'color' => '#20a66a'],
    ['label' => 'Đã hủy', 'count' => ($orderCounts['Đã hủy'] ?? 0) + ($orderCounts['Đã huỷ'] ?? 0), 'color' => '#e36161'],
];
if ($allOrderCount > $knownOrderCount) {
    $statusChart[] = ['label' => 'Trạng thái khác', 'count' => $allOrderCount - $knownOrderCount, 'color' => '#98a2b3'];
}

$revenueTrendResult = mysqli_query($conn, "
    SELECT DATE(created_at) AS order_date, SUM(tong_cong) AS daily_revenue
    FROM donhang
    WHERE trang_thai = 'Đã giao'
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    GROUP BY DATE(created_at)
    ORDER BY order_date
");
if (!$revenueTrendResult) {
    die("Không thể tải biểu đồ doanh thu.");
}
$revenueByDate = [];
while ($row = mysqli_fetch_assoc($revenueTrendResult)) {
    $revenueByDate[$row['order_date']] = (float)$row['daily_revenue'];
}
$revenueTrend = [];
for ($dayOffset = 6; $dayOffset >= 0; $dayOffset--) {
    $date = date('Y-m-d', strtotime('-' . $dayOffset . ' days'));
    $revenueTrend[] = [
        'label' => date('d/m', strtotime($date)),
        'date' => $date,
        'revenue' => $revenueByDate[$date] ?? 0,
    ];
}
$maxDailyRevenue = max(array_column($revenueTrend, 'revenue'));
$statusChartTotal = array_sum(array_column($statusChart, 'count'));
$statusChartParts = [];
$statusChartAngle = 0;
foreach ($statusChart as $statusItem) {
    if ($statusItem['count'] === 0 || $statusChartTotal === 0) {
        continue;
    }
    $nextAngle = $statusChartAngle + ($statusItem['count'] / $statusChartTotal * 360);
    $statusChartParts[] = $statusItem['color'] . ' ' . number_format($statusChartAngle, 2, '.', '') . 'deg ' . number_format($nextAngle, 2, '.', '') . 'deg';
    $statusChartAngle = $nextAngle;
}
$statusChartBackground = $statusChartParts
    ? 'conic-gradient(' . implode(', ', $statusChartParts) . ')'
    : 'conic-gradient(#e9eeeb 0deg 360deg)';

$pendingCount = ($orderCounts['Chờ xử lý'] ?? 0) + ($orderCounts['Chờ lấy hàng'] ?? 0);
$shippingCount = $orderCounts['Đang vận chuyển'] ?? 0;
$deliveredCount = $orderCounts['Đã giao'] ?? 0;
$revenue = 0;
$revenuePeriod = !isset($_GET['revenue_period'])
    ? 'all'
    : (is_string($_GET['revenue_period']) ? $_GET['revenue_period'] : '');
$fromDate = isset($_GET['from_date']) && is_string($_GET['from_date']) ? $_GET['from_date'] : '';
$toDate = isset($_GET['to_date']) && is_string($_GET['to_date']) ? $_GET['to_date'] : '';
$revenueError = '';
$revenueCondition = '';
$revenueParams = [];

$allowedRevenuePeriods = ['all', 'today', '7days', 'month', 'year', 'custom'];
if (!in_array($revenuePeriod, $allowedRevenuePeriods, true)) {
    $revenueError = 'Khoảng thời gian không hợp lệ.';
} else {
    switch ($revenuePeriod) {
        case 'today':
            $revenueCondition = ' AND created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
            break;
        case '7days':
            $revenueCondition = ' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
            break;
        case 'month':
            $revenueCondition = " AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND created_at < DATE_ADD(LAST_DAY(CURDATE()), INTERVAL 1 DAY)";
            break;
        case 'year':
            $revenueCondition = ' AND created_at >= MAKEDATE(YEAR(CURDATE()), 1) AND created_at < MAKEDATE(YEAR(CURDATE()) + 1, 1)';
            break;
        case 'custom':
            $validFromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)
                && checkdate((int)substr($fromDate, 5, 2), (int)substr($fromDate, 8, 2), (int)substr($fromDate, 0, 4));
            $validToDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)
                && checkdate((int)substr($toDate, 5, 2), (int)substr($toDate, 8, 2), (int)substr($toDate, 0, 4));

            if (!$validFromDate || !$validToDate || $fromDate > $toDate) {
                $revenueError = 'Vui lòng chọn khoảng ngày hợp lệ (từ ngày không lớn hơn đến ngày).';
            } else {
                $revenueCondition = ' AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)';
                $revenueParams = [$fromDate, $toDate];
            }
            break;
    }
}

if ($revenueError === '') {
    $revenueStatement = mysqli_prepare($conn, "
        SELECT COALESCE(SUM(tong_cong), 0)
        FROM donhang
        WHERE trang_thai = 'Đã giao'$revenueCondition
    ");
    if (!$revenueStatement) {
        die("Không thể chuẩn bị thống kê doanh thu.");
    }
    if ($revenueParams) {
        mysqli_stmt_bind_param($revenueStatement, "ss", $revenueParams[0], $revenueParams[1]);
    }
    if (!mysqli_stmt_execute($revenueStatement)
        || !mysqli_stmt_bind_result($revenueStatement, $revenue)
        || !mysqli_stmt_fetch($revenueStatement)
    ) {
        die("Không thể tải thống kê doanh thu.");
    }
    mysqli_stmt_close($revenueStatement);
}

$recentOrders = mysqli_query($conn, "
    SELECT id, ma_don, ho_ten, tong_cong, trang_thai, created_at, phuong_thuc, payment_status
    FROM donhang
    ORDER BY id DESC
    LIMIT 8
");
if (!$recentOrders) {
    die("Không thể tải danh sách đơn hàng mới.");
}

$statusClasses = [
    'Chờ xử lý' => 'status-pending',
    'Chờ lấy hàng' => 'status-pending',
    'Đang vận chuyển' => 'status-shipping',
    'Đã giao' => 'status-delivered',
    'Đã hủy' => 'status-cancelled',
    'Đã huỷ' => 'status-cancelled',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tổng quan quản trị - TT SHOP</title>
    <style>
        .dashboard-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:22px}
        .metric-card{background:#fff;border:1px solid #e8eee9;border-radius:14px;padding:20px;box-shadow:0 3px 12px rgba(20,50,35,.04)}
        .metric-label{color:#667085;font-size:14px;font-weight:600}
        .metric-value{font-size:28px;font-weight:750;color:#182b21;margin:9px 0 0}
        .metric-note{color:#7a8790;font-size:13px;margin:6px 0 0}
        .metric-card.revenue{border-top:3px solid #168653}
        .metric-card.needs-action{border-top:3px solid #e9a23b}
        .chart-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(280px,.85fr);gap:18px;margin-bottom:22px}
        .chart-panel{min-width:0;background:#fff;border:1px solid #e8eee9;border-radius:14px;padding:20px;box-shadow:0 3px 12px rgba(20,50,35,.04)}
        .chart-caption{margin:-8px 0 16px;color:#7a8790;font-size:12px}
        .revenue-chart{display:flex;align-items:flex-end;gap:12px;height:205px;padding:10px 6px 0;border-bottom:1px solid #e9eeeb;background:repeating-linear-gradient(to top,transparent 0,transparent 48px,#f0f3f1 49px,#f0f3f1 50px)}
        .revenue-column{display:flex;flex:1;min-width:0;height:100%;flex-direction:column;justify-content:flex-end;align-items:center;gap:7px}
        .revenue-column-value{min-height:14px;color:#68756d;font-size:10px;white-space:nowrap}
        .revenue-column-track{display:flex;align-items:flex-end;justify-content:center;width:100%;height:calc(100% - 35px)}
        .revenue-column-bar{width:min(34px,70%);min-height:0;border-radius:7px 7px 2px 2px;background:linear-gradient(180deg,#42bd7d,#168653);transition:height .25s ease}
        .revenue-column-label{padding-bottom:8px;color:#68756d;font-size:11px}
        .status-chart-layout{display:flex;align-items:center;justify-content:center;gap:24px;min-height:205px}
        .status-donut{display:grid;flex:0 0 154px;place-items:center;width:154px;height:154px;border-radius:50%;background:#e9eeeb}
        .status-donut-hole{display:flex;flex-direction:column;align-items:center;justify-content:center;width:106px;height:106px;border-radius:50%;background:#fff}
        .status-donut-total{color:#182b21;font-size:25px;font-weight:750}
        .status-donut-label{margin-top:3px;color:#7a8790;font-size:11px}
        .status-legend{display:grid;gap:11px;min-width:0}
        .status-legend-item{display:flex;align-items:center;gap:8px;color:#47534b;font-size:12px}
        .status-legend-marker{flex:0 0 9px;width:9px;height:9px;border-radius:50%}
        .status-legend-count{margin-left:auto;padding-left:8px;color:#182b21;font-weight:700}
        .dashboard-columns{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(250px,.8fr);gap:18px;align-items:start}
        .panel{background:#fff;border:1px solid #e8eee9;border-radius:14px;padding:20px;box-shadow:0 3px 12px rgba(20,50,35,.04);margin-bottom:18px}
        .panel-heading{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:0 0 16px}
        .panel-heading h2{font-size:18px;margin:0;color:#182b21}
        .panel-link{font-size:14px;font-weight:700;color:#137a4a;text-decoration:none}
        .panel-link:hover{text-decoration:underline}
        .revenue-filter{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:14px}
        .revenue-filter select,.revenue-filter input,.revenue-filter button{min-height:36px;border:1px solid #dce4df;border-radius:7px;padding:0 9px;background:#fff;font:inherit;font-size:13px}
        .revenue-filter button{background:#168653;border-color:#168653;color:#fff;font-weight:700;cursor:pointer}
        #custom-revenue-dates{display:flex;flex-wrap:wrap;gap:8px}
        .revenue-error{margin:10px 0 0;color:#b42318;font-size:13px}
        .status-list{display:grid;gap:10px}
        .status-link{display:flex;justify-content:space-between;align-items:center;gap:10px;text-decoration:none;color:#344054;padding:12px;border:1px solid #edf0ed;border-radius:9px}
        .status-link:hover{border-color:#b6d9c6;background:#f8fcf9}
        .status-number{min-width:30px;text-align:center;padding:4px 8px;border-radius:99px;background:#f1f5f2;font-weight:700}
        .table-scroll{overflow-x:auto}
        .orders-table{width:100%;border-collapse:collapse;min-width:620px}
        .orders-table th,.orders-table td{padding:12px 10px;border-bottom:1px solid #edf0ed;text-align:left;vertical-align:middle}
        .orders-table th{color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em;background:#f8faf9}
        .orders-table td{font-size:14px}
        .order-code{font-weight:700;color:#182b21;text-decoration:none}
        .order-code:hover{color:#137a4a}
        .order-date{font-size:12px;color:#7a8790;margin-top:4px}
        .status-badge{display:inline-block;padding:5px 9px;border-radius:99px;font-size:12px;font-weight:700;white-space:nowrap}
        .status-pending{background:#fff3d6;color:#9a5b00}
        .status-shipping{background:#e5f1ff;color:#175cd3}
        .status-delivered{background:#dcfce7;color:#15803d}
        .status-cancelled{background:#fee4e2;color:#b42318}
        .empty-state{text-align:center;color:#7a8790;padding:24px}
        .quick-actions{display:grid;gap:9px}
        .quick-action{display:block;text-decoration:none;color:#344054;padding:12px 14px;border:1px solid #e4e9e5;border-radius:9px;font-weight:650}
        .quick-action:hover{background:#f5fbf7;border-color:#b6d9c6;color:#137a4a}
        @media(max-width:1050px){.dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.chart-grid,.dashboard-columns{grid-template-columns:1fr}}
        @media(max-width:560px){.dashboard-grid{grid-template-columns:1fr}.metric-value{font-size:24px}.panel,.chart-panel{padding:15px}.status-chart-layout{gap:16px}.status-donut{flex-basis:128px;width:128px;height:128px}.status-donut-hole{width:88px;height:88px}.revenue-chart{gap:5px}.revenue-column-value{font-size:8px}}
        .metric-value{overflow-wrap:anywhere}
        .status-chart-layout{flex-wrap:wrap}
        .chart-panel,.panel,.dashboard-columns>*,.chart-grid>*{min-width:0}
        .revenue-column-value{white-space:normal;overflow-wrap:anywhere;text-align:center}
    </style>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">
    <?php $page = "dashboard"; include "sidebar.php"; ?>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">TRUNG TÂM ĐIỀU HÀNH</p>
                <h1>Tổng quan cửa hàng</h1>
                <p class="topbar-subtitle">Xin chào, <?php echo htmlspecialchars((string)($_SESSION['admin_name'] ?? 'Admin')); ?>. Đây là tình hình hoạt động của cửa hàng.</p>
            </div>
            <a class="header-action" href="../index.php" target="_blank" rel="noopener">Xem cửa hàng</a>
        </header>

        <section class="dashboard-grid" aria-label="Chỉ số cửa hàng">
            <article class="metric-card needs-action">
                <div class="metric-label">Đơn cần xử lý</div>
                <div class="metric-value"><?php echo $pendingCount; ?></div>
                <p class="metric-note">Chờ xử lý hoặc chờ lấy hàng</p>
            </article>
            <article class="metric-card">
                <div class="metric-label">Đang vận chuyển</div>
                <div class="metric-value"><?php echo $shippingCount; ?></div>
                <p class="metric-note">Đơn đang giao đến khách</p>
            </article>
            <article class="metric-card">
                <div class="metric-label">Sản phẩm</div>
                <div class="metric-value"><?php echo $productCount; ?></div>
                <p class="metric-note">Khách hàng: <?php echo $customerCount; ?> · Tổng đơn: <?php echo $allOrderCount; ?> · Đã giao: <?php echo $deliveredCount; ?></p>
            </article>
            <article class="metric-card revenue">
                <div class="metric-label">Doanh thu đã giao</div>
                <p class="metric-note">Theo ngày tạo đơn</p>
                <div class="metric-value"><?php echo number_format((float)$revenue, 0, ',', '.'); ?>đ</div>
                <form method="GET" class="revenue-filter" id="revenue-filter">
                    <select name="revenue_period" id="revenue-period" aria-label="Khoảng thời gian doanh thu">
                        <option value="all" <?php echo $revenuePeriod === 'all' ? 'selected' : ''; ?>>Toàn thời gian</option>
                        <option value="today" <?php echo $revenuePeriod === 'today' ? 'selected' : ''; ?>>Hôm nay</option>
                        <option value="7days" <?php echo $revenuePeriod === '7days' ? 'selected' : ''; ?>>7 ngày qua</option>
                        <option value="month" <?php echo $revenuePeriod === 'month' ? 'selected' : ''; ?>>Tháng này</option>
                        <option value="year" <?php echo $revenuePeriod === 'year' ? 'selected' : ''; ?>>Năm nay</option>
                        <option value="custom" <?php echo $revenuePeriod === 'custom' ? 'selected' : ''; ?>>Tùy chọn</option>
                    </select>
                    <div id="custom-revenue-dates" style="<?php echo $revenuePeriod === 'custom' ? '' : 'display:none;'; ?>">
                        <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" aria-label="Từ ngày">
                        <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" aria-label="Đến ngày">
                    </div>
                    <button type="submit">Áp dụng</button>
                </form>
                <?php if ($revenueError !== '') { ?>
                    <p class="revenue-error"><?php echo htmlspecialchars($revenueError); ?></p>
                <?php } ?>
            </article>
        </section>

        <section class="chart-grid" aria-label="Biểu đồ cửa hàng">
            <article class="chart-panel">
                <div class="panel-heading">
                    <h2>Doanh thu theo ngày</h2>
                    <span class="panel-link">7 ngày gần nhất</span>
                </div>
                <p class="chart-caption">Chỉ tính các đơn hàng đã giao thành công.</p>
                <div class="revenue-chart" role="img" aria-label="Biểu đồ doanh thu 7 ngày gần nhất">
                    <?php foreach ($revenueTrend as $trendDay) {
                        $barHeight = $maxDailyRevenue > 0
                            ? round(($trendDay['revenue'] / $maxDailyRevenue) * 100, 2)
                            : 0;
                    ?>
                        <div class="revenue-column" title="<?php echo htmlspecialchars($trendDay['date'] . ': ' . number_format($trendDay['revenue'], 0, ',', '.') . 'đ'); ?>">
                            <span class="revenue-column-value">
                                <?php echo $trendDay['revenue'] > 0 ? number_format($trendDay['revenue'] / 1000000, 1, ',', '.') . 'tr' : ''; ?>
                            </span>
                            <div class="revenue-column-track">
                                <div class="revenue-column-bar" style="height: <?php echo $barHeight; ?>%;"></div>
                            </div>
                            <span class="revenue-column-label"><?php echo htmlspecialchars($trendDay['label']); ?></span>
                        </div>
                    <?php } ?>
                </div>
            </article>

            <article class="chart-panel">
                <div class="panel-heading">
                    <h2>Đơn hàng theo trạng thái</h2>
                    <a class="panel-link" href="orders.php">Xem đơn hàng</a>
                </div>
                <p class="chart-caption">Tổng quan tất cả đơn hàng hiện có.</p>
                <div class="status-chart-layout">
                    <div class="status-donut" role="img" aria-label="Biểu đồ trạng thái của <?php echo $statusChartTotal; ?> đơn hàng" style="background: <?php echo htmlspecialchars($statusChartBackground); ?>;">
                        <div class="status-donut-hole">
                            <span class="status-donut-total"><?php echo $statusChartTotal; ?></span>
                            <span class="status-donut-label">đơn hàng</span>
                        </div>
                    </div>
                    <div class="status-legend">
                        <?php foreach ($statusChart as $statusItem) { ?>
                            <div class="status-legend-item">
                                <span class="status-legend-marker" style="background: <?php echo htmlspecialchars($statusItem['color']); ?>;"></span>
                                <span><?php echo htmlspecialchars($statusItem['label']); ?></span>
                                <span class="status-legend-count"><?php echo $statusItem['count']; ?></span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </article>
        </section>

        <section class="dashboard-columns">
            <div class="panel">
                <div class="panel-heading">
                    <h2>Đơn hàng gần đây</h2>
                    <a class="panel-link" href="orders.php">Quản lý đơn hàng</a>
                </div>
                <div class="table-scroll">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Đơn hàng</th>
                                <th>Khách hàng</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($recentOrders) > 0) { ?>
                                <?php while ($order = mysqli_fetch_assoc($recentOrders)) {
                                    $statusClass = $statusClasses[$order['trang_thai']] ?? 'status-pending';
                                ?>
                                    <tr>
                                        <td>
                                            <a class="order-code" href="order_detail.php?id=<?php echo (int)$order['id']; ?>">
                                                <?php echo htmlspecialchars($order['ma_don']); ?>
                                            </a>
                                            <div class="order-date"><?php echo htmlspecialchars($order['created_at'] ?? ''); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['ho_ten']); ?></td>
                                        <td><?php echo number_format((float)$order['tong_cong'], 0, ',', '.'); ?>đ</td>
                                        <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['trang_thai']); ?></span><?php if($order['phuong_thuc']==='PAYOS'): ?><br><small><?= payos_escape(payos_label($order)) ?></small><?php endif; ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr><td class="empty-state" colspan="4">Chưa có đơn hàng nào.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <aside>
                <section class="panel">
                    <div class="panel-heading"><h2>Trạng thái đơn</h2></div>
                    <div class="status-list">
                        <a class="status-link" href="orders.php">
                            <span>Cần xử lý</span><span class="status-number"><?php echo $pendingCount; ?></span>
                        </a>
                        <a class="status-link" href="orders.php?status=%C4%90ang%20v%E1%BA%ADn%20chuy%E1%BB%83n">
                            <span>Đang vận chuyển</span><span class="status-number"><?php echo $shippingCount; ?></span>
                        </a>
                        <a class="status-link" href="orders.php?status=%C4%90%C3%A3%20giao">
                            <span>Đã giao</span><span class="status-number"><?php echo $deliveredCount; ?></span>
                        </a>
                    </div>
                </section>
                <section class="panel">
                    <div class="panel-heading"><h2>Thao tác nhanh</h2></div>
                    <div class="quick-actions">
                        <a class="quick-action" href="add_product.php">+ Thêm sản phẩm trang chủ</a>
                        <a class="quick-action" href="add_category_product.php">+ Thêm sản phẩm danh mục</a>
                        <a class="quick-action" href="customers.php">Xem khách hàng</a>
                        <a class="quick-action" href="vouchers.php">Quản lý mã giảm giá</a>
                        <a class="quick-action" href="reports.php">Báo cáo bán hàng</a>
                        <a class="quick-action" href="chat.php">Tin nhắn khách hàng</a>
                    </div>
                </section>
            </aside>
        </section>
    </main>
</div>
<script>
    const revenuePeriod = document.getElementById('revenue-period');
    const customRevenueDates = document.getElementById('custom-revenue-dates');
    revenuePeriod.addEventListener('change', function () {
        customRevenueDates.style.display = this.value === 'custom' ? 'flex' : 'none';
    });
</script>
</body>
</html>
