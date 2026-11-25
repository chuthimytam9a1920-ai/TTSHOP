<?php
include "check_admin.php";
include "../connect.php";

$today = date('Y-m-d');
$defaultFromDate = date('Y-m-01');
$fromDate = isset($_GET['from_date']) && is_string($_GET['from_date']) ? $_GET['from_date'] : $defaultFromDate;
$toDate = isset($_GET['to_date']) && is_string($_GET['to_date']) ? $_GET['to_date'] : $today;
$dateError = '';
$summary = [
    'order_count' => 0,
    'delivered_count' => 0,
    'cancelled_count' => 0,
    'pending_count' => 0,
    'revenue' => 0,
];
$dailyReport = [];
$topProducts = [];

$validFromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)
    && checkdate((int)substr($fromDate, 5, 2), (int)substr($fromDate, 8, 2), (int)substr($fromDate, 0, 4));
$validToDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)
    && checkdate((int)substr($toDate, 5, 2), (int)substr($toDate, 8, 2), (int)substr($toDate, 0, 4));

if (!$validFromDate || !$validToDate || $fromDate > $toDate) {
    $dateError = 'Vui lòng chọn khoảng ngày hợp lệ, trong đó ngày bắt đầu không sau ngày kết thúc.';
} else {
    $summaryStatement = mysqli_prepare($conn, "
        SELECT
            COUNT(*) AS order_count,
            COALESCE(SUM(CASE WHEN trang_thai = 'Đã giao' THEN 1 ELSE 0 END), 0) AS delivered_count,
            COALESCE(SUM(CASE WHEN trang_thai IN ('Đã hủy', 'Đã huỷ') THEN 1 ELSE 0 END), 0) AS cancelled_count,
            COALESCE(SUM(CASE WHEN trang_thai IN ('Chờ xử lý', 'Chờ lấy hàng') THEN 1 ELSE 0 END), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN trang_thai = 'Đã giao' THEN tong_cong ELSE 0 END), 0) AS revenue
        FROM donhang
        WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
    ");
    if (!$summaryStatement) {
        die('Không thể chuẩn bị báo cáo tổng quan.');
    }
    mysqli_stmt_bind_param($summaryStatement, 'ss', $fromDate, $toDate);
    if (!mysqli_stmt_execute($summaryStatement)) {
        die('Không thể tải báo cáo tổng quan.');
    }
    $summaryResult = mysqli_stmt_get_result($summaryStatement);
    if (!$summaryResult || !($summaryRow = mysqli_fetch_assoc($summaryResult))) {
        die('Không thể đọc báo cáo tổng quan.');
    }
    foreach ($summary as $key => $value) {
        $summary[$key] = (float)$summaryRow[$key];
    }
    mysqli_stmt_close($summaryStatement);

    $dailyStatement = mysqli_prepare($conn, "
        SELECT
            DATE(created_at) AS report_date,
            COUNT(*) AS order_count,
            COALESCE(SUM(CASE WHEN trang_thai = 'Đã giao' THEN tong_cong ELSE 0 END), 0) AS revenue
        FROM donhang
        WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY DATE(created_at)
        ORDER BY report_date DESC
    ");
    if (!$dailyStatement) {
        die('Không thể chuẩn bị báo cáo theo ngày.');
    }
    mysqli_stmt_bind_param($dailyStatement, 'ss', $fromDate, $toDate);
    if (!mysqli_stmt_execute($dailyStatement)) {
        die('Không thể tải báo cáo theo ngày.');
    }
    $dailyResult = mysqli_stmt_get_result($dailyStatement);
    if (!$dailyResult) {
        die('Không thể đọc báo cáo theo ngày.');
    }
    while ($row = mysqli_fetch_assoc($dailyResult)) {
        $dailyReport[] = $row;
    }
    mysqli_stmt_close($dailyStatement);

    $productsStatement = mysqli_prepare($conn, "
        SELECT
            chitiet_donhang.ten_san_pham AS product_name,
            SUM(chitiet_donhang.so_luong) AS quantity_sold,
            SUM(chitiet_donhang.thanh_tien) AS product_revenue
        FROM chitiet_donhang
        INNER JOIN donhang ON donhang.id = chitiet_donhang.donhang_id
        WHERE donhang.trang_thai = 'Đã giao'
            AND donhang.created_at >= ?
            AND donhang.created_at < DATE_ADD(?, INTERVAL 1 DAY)
        GROUP BY chitiet_donhang.product_id, chitiet_donhang.catalog_id, chitiet_donhang.ten_san_pham
        ORDER BY quantity_sold DESC, product_revenue DESC
        LIMIT 10
    ");
    if (!$productsStatement) {
        die('Không thể chuẩn bị báo cáo sản phẩm.');
    }
    mysqli_stmt_bind_param($productsStatement, 'ss', $fromDate, $toDate);
    if (!mysqli_stmt_execute($productsStatement)) {
        die('Không thể tải báo cáo sản phẩm.');
    }
    $productsResult = mysqli_stmt_get_result($productsStatement);
    if (!$productsResult) {
        die('Không thể đọc báo cáo sản phẩm.');
    }
    while ($row = mysqli_fetch_assoc($productsResult)) {
        $topProducts[] = $row;
    }
    mysqli_stmt_close($productsStatement);
}

$statusTotal = (int)$summary['order_count'];
$deliveredPercent = $statusTotal > 0 ? round($summary['delivered_count'] / $statusTotal * 100) : 0;
$averageDeliveredOrder = $summary['delivered_count'] > 0
    ? $summary['revenue'] / $summary['delivered_count']
    : 0;
$reportId = 'BC-' . date('Ymd-His');
$issuedAt = date('d/m/Y H:i');
$formattedFromDate = $validFromDate ? date('d/m/Y', strtotime($fromDate)) : $fromDate;
$formattedToDate = $validToDate ? date('d/m/Y', strtotime($toDate)) : $toDate;
$dailyOrderTotal = 0;
$maxDailyRevenue = 0;
foreach ($dailyReport as $day) {
    $dailyOrderTotal += (int)$day['order_count'];
    $maxDailyRevenue = max($maxDailyRevenue, (float)$day['revenue']);
}
$maxProductQuantity = 0;
foreach ($topProducts as $product) {
    $maxProductQuantity = max($maxProductQuantity, (int)$product['quantity_sold']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Báo cáo bán hàng - TT SHOP</title>
    <link rel="stylesheet" href="admin.css">
    <style>
        .report-document{max-width:1240px;margin:0 auto}
        .report-intro{display:flex;align-items:center;justify-content:space-between;gap:18px;margin:0 0 18px;padding:24px 26px;border:1px solid #e1ebe4;border-radius:16px;background:linear-gradient(120deg,#fff 48%,#f1f8f3 100%);box-shadow:0 8px 26px rgba(27,54,39,.045)}
        .report-title-lockup{display:flex;align-items:center;gap:16px}
        .report-logo{display:grid;flex:0 0 54px;place-items:center;width:54px;height:54px;border-radius:15px;background:linear-gradient(145deg,#29a96e,#117849);color:#fff;font-size:17px;font-weight:800;letter-spacing:-.05em;box-shadow:0 6px 16px rgba(22,134,83,.19)}
        .report-kicker{margin:0 0 5px;color:#168653;font-size:10px;font-weight:800;letter-spacing:.13em}
        .report-intro h2{margin:0 0 6px;color:#182b21;font-size:22px;letter-spacing:-.025em}
        .report-intro p{margin:0;color:#76847b;font-size:13px;line-height:1.55}
        .report-meta{display:grid;gap:6px;min-width:170px;padding-left:17px;border-left:1px solid #dfe9e2;color:#69766e;font-size:11px}
        .report-meta strong{color:#26372d;font-size:12px}
        .report-actions{display:flex;gap:9px;flex-shrink:0}
        .report-button{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 15px;border:1px solid #dfe7e1;border-radius:9px;background:#fff;color:#34443a;text-decoration:none;font-size:13px;font-weight:700;cursor:pointer}
        .report-button.primary{border-color:#168653;background:#168653;color:#fff}
        .report-button:hover{box-shadow:0 4px 12px rgba(27,54,39,.1)}
        .report-filter{display:flex;align-items:flex-end;flex-wrap:wrap;gap:12px;padding:17px;margin-bottom:20px;border:1px solid #e7ede8;border-radius:12px;background:#fff;box-shadow:0 5px 18px rgba(27,54,39,.035)}
        .report-filter label{display:grid;gap:6px;color:#536258;font-size:12px;font-weight:700}
        .report-filter input{min-height:40px;padding:0 10px;border:1px solid #dce5df;border-radius:8px;background:#fff}
        .report-error{width:100%;margin:0;color:#b42318;font-size:13px}
        .report-period{margin-left:auto;color:#76847b;font-size:12px}
        .report-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}
        .report-metric{position:relative;overflow:hidden;padding:18px 19px;border:1px solid #e7ede8;border-radius:13px;background:#fff;box-shadow:0 5px 18px rgba(27,54,39,.035)}
        .report-metric:before{position:absolute;top:0;left:0;width:100%;height:3px;background:#168653;content:""}
        .report-metric.orders:before{background:#4688e8}
        .report-metric.average:before{background:#9165d6}
        .report-metric.delivery:before{background:#20a66a}
        .report-metric-label{color:#76847b;font-size:11px;font-weight:700}
        .report-metric-value{margin-top:10px;color:#182b21;font-size:22px;font-weight:750;letter-spacing:-.025em}
        .report-metric-note{margin-top:5px;color:#8a968e;font-size:11px}
        .report-status-strip{display:flex;flex-wrap:wrap;align-items:center;gap:9px;margin:-3px 0 20px;padding:12px 15px;border:1px solid #e7ede8;border-radius:11px;background:#fff}
        .report-status-heading{margin-right:3px;color:#6b786f;font-size:11px;font-weight:700}
        .report-status-item{display:inline-flex;align-items:center;gap:6px;padding:6px 9px;border-radius:99px;background:#f6f8f6;color:#48574d;font-size:11px}
        .report-status-item strong{color:#182b21}
        .report-status-item.pending{background:#fff6e3;color:#8d5609}
        .report-status-item.cancelled{background:#fff0ef;color:#a72b23}
        .report-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:18px;align-items:start}
        .report-panel{min-width:0;margin-bottom:18px;padding:20px;border:1px solid #e7ede8;border-radius:13px;background:#fff;box-shadow:0 5px 18px rgba(27,54,39,.035)}
        .report-panel-heading{display:flex;justify-content:space-between;align-items:baseline;gap:10px;margin-bottom:14px}
        .report-panel h3{margin:0;color:#25382d;font-size:14px}
        .report-panel-subtitle{color:#89958d;font-size:10px}
        .report-table-wrap{overflow-x:auto}
        .report-table{width:100%;border-collapse:collapse}
        .report-table th,.report-table td{padding:10px 11px;border-bottom:1px solid #edf1ee;text-align:left;vertical-align:middle;font-size:12px}
        .report-table th{background:#f7f9f7;color:#68776d;font-size:10px;letter-spacing:.04em;text-transform:uppercase}
        .report-table tr:last-child td{border-bottom:0}
        .report-table .number{text-align:right;white-space:nowrap}
        .report-table tfoot td{border-top:1px solid #dfe7e1;border-bottom:0;background:#f7f9f7;color:#26372d;font-weight:750}
        .report-empty{padding:22px;text-align:center;color:#839087;font-size:13px}
        .report-bar-cell{min-width:112px}
        .report-bar{height:5px;margin-top:5px;overflow:hidden;border-radius:99px;background:#edf2ee}
        .report-bar span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#61c88e,#168653)}
        .product-rank{display:inline-grid;width:22px;height:22px;margin-right:6px;place-items:center;border-radius:7px;background:#eef5f0;color:#168653;font-size:10px;font-weight:800}
        .report-footnote{margin:12px 0 0;color:#77837b;font-size:10px;line-height:1.6}
        .report-signatures{display:grid;grid-template-columns:1fr 1fr;gap:35px;margin:22px 0 0;padding:16px 10px 0}
        .report-signature{text-align:center;color:#69766e;font-size:11px}
        .report-signature strong{display:block;color:#34443a;font-size:11px}
        .report-signature span{display:block;margin-top:65px;color:#89958d;font-size:10px}
        .report-print-note{display:none}
        @media(max-width:900px){.report-document{max-width:100%}.report-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.report-grid{grid-template-columns:1fr}}
        @media(max-width:560px){.report-intro{align-items:flex-start;flex-direction:column;padding:19px}.report-title-lockup{align-items:flex-start}.report-logo{flex-basis:44px;width:44px;height:44px;border-radius:12px}.report-intro h2{font-size:19px}.report-meta{grid-template-columns:1fr 1fr;min-width:0;width:100%;padding:11px 0 0;border-top:1px solid #dfe9e2;border-left:0}.report-actions{width:100%}.report-actions>*{flex:1}.report-filter label{flex:1 1 140px}.report-period{width:100%;margin-left:0}.report-metrics{gap:9px}.report-metric{padding:14px}.report-metric-value{font-size:19px}.report-status-strip{gap:6px;padding:10px}.report-status-heading{width:100%}.report-signatures{gap:12px}}
        @media print{
            @page{size:A4 portrait;margin:13mm}
            html,body{background:#fff!important;color:#111!important}
            .sidebar,.report-filter,.report-actions,.topbar,.no-print{display:none!important}
            .layout,.admin-layout{display:block;min-height:0}
            .main{max-width:none;padding:0}
            .report-document{max-width:none}
            .report-intro{margin:0 0 10px;padding:14px 16px;border:1px solid #d9dfdb;border-radius:0;background:#fff;box-shadow:none}
            .report-logo{width:42px;height:42px;flex-basis:42px;border-radius:8px;background:#168653!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .report-kicker{font-size:8px}
            .report-intro h2{font-size:17px}
            .report-intro p{font-size:10px}
            .report-meta{min-width:140px;padding-left:12px;font-size:9px}
            .report-meta strong{font-size:10px}
            .report-print-note{display:block;margin:0 0 12px;color:#536258;font-size:9px}
            .report-metrics{grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin-bottom:8px}
            .report-metric,.report-panel{break-inside:avoid;padding:10px;border:1px solid #d9dfdb;border-radius:0;box-shadow:none}
            .report-metric:before{height:2px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .report-metric-value{font-size:15px}
            .report-metric-label,.report-metric-note{font-size:9px}
            .report-status-strip{gap:6px;margin:0 0 9px;padding:7px;border-radius:0}
            .report-status-heading,.report-status-item{font-size:9px}
            .report-grid{grid-template-columns:1fr;gap:0}
            .report-panel{margin-bottom:9px}
            .report-panel-heading{margin-bottom:7px}
            .report-panel h3{font-size:11px}
            .report-panel-subtitle{font-size:8px}
            .report-table th,.report-table td{padding:5px 6px;font-size:8px}
            .report-table th{background:#f0f3f1!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .report-table tfoot td{background:#f0f3f1!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
            .report-bar-cell{min-width:95px}
            .report-footnote{font-size:8px}
            .report-signatures{margin-top:11px;padding-top:8px}
            .report-signature{font-size:9px}
            .report-signature span{margin-top:42px}
            .report-print-footer{position:fixed;right:0;bottom:-6mm;left:0;color:#77837b;font-size:8px;text-align:center}
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php $page = 'reports'; include "sidebar.php"; ?>

    <main class="main">
        <header class="topbar">
            <div>
                <p class="eyebrow">BÁO CÁO VẬN HÀNH</p>
                <h1>Báo cáo bán hàng</h1>
                <p class="topbar-subtitle">Tổng hợp doanh thu, đơn hàng và sản phẩm theo ngày tạo đơn. Doanh thu chỉ tính đơn đã giao.</p>
            </div>
            <div class="report-actions">
                <button class="report-button primary" type="button" onclick="window.print()">In báo cáo</button>
            </div>
        </header>

        <form class="report-filter" method="GET">
            <label>
                Từ ngày
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" required>
            </label>
            <label>
                Đến ngày
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" required>
            </label>
            <button type="submit" class="report-button primary">Xem báo cáo</button>
            <span class="report-period">
                Kỳ báo cáo: <?php echo htmlspecialchars($formattedFromDate); ?> — <?php echo htmlspecialchars($formattedToDate); ?>
            </span>
            <?php if ($dateError !== '') { ?>
                <p class="report-error"><?php echo htmlspecialchars($dateError); ?></p>
            <?php } ?>
        </form>

        <div class="report-document">
            <section class="report-intro">
                <div class="report-title-lockup">
                    <span class="report-logo" aria-hidden="true">TT</span>
                    <div>
                        <p class="report-kicker">TT SHOP · BÁO CÁO QUẢN TRỊ</p>
                        <h2>Báo cáo kết quả kinh doanh</h2>
                        <p>Tổng hợp hoạt động bán hàng trong kỳ <?php echo htmlspecialchars($formattedFromDate); ?> – <?php echo htmlspecialchars($formattedToDate); ?>.</p>
                    </div>
                </div>
                <div class="report-meta">
                    <span>Mã báo cáo <strong><?php echo htmlspecialchars($reportId); ?></strong></span>
                    <span>Ngày lập <strong><?php echo htmlspecialchars($issuedAt); ?></strong></span>
                    <span>Giao thành công <strong><?php echo $deliveredPercent; ?>%</strong></span>
                </div>
            </section>

            <p class="report-print-note">
                Kỳ báo cáo: <strong><?php echo htmlspecialchars($formattedFromDate); ?> – <?php echo htmlspecialchars($formattedToDate); ?></strong>
                <span> · Mã báo cáo: <?php echo htmlspecialchars($reportId); ?></span>
            </p>

            <section class="report-metrics" aria-label="Chỉ số báo cáo">
                <article class="report-metric">
                    <div class="report-metric-label">Doanh thu đơn đã giao</div>
                    <div class="report-metric-value"><?php echo number_format($summary['revenue'], 0, ',', '.'); ?>đ</div>
                    <div class="report-metric-note">Tổng tiền đơn giao thành công</div>
                </article>
                <article class="report-metric orders">
                    <div class="report-metric-label">Tổng đơn hàng</div>
                    <div class="report-metric-value"><?php echo (int)$summary['order_count']; ?></div>
                    <div class="report-metric-note">Phát sinh trong kỳ báo cáo</div>
                </article>
                <article class="report-metric average">
                    <div class="report-metric-label">Giá trị TB / đơn đã giao</div>
                    <div class="report-metric-value"><?php echo number_format($averageDeliveredOrder, 0, ',', '.'); ?>đ</div>
                    <div class="report-metric-note">Doanh thu chia số đơn đã giao</div>
                </article>
                <article class="report-metric delivery">
                    <div class="report-metric-label">Đơn đã giao</div>
                    <div class="report-metric-value"><?php echo (int)$summary['delivered_count']; ?></div>
                    <div class="report-metric-note">Tỷ lệ hoàn tất <?php echo $deliveredPercent; ?>%</div>
                </article>
            </section>

            <section class="report-status-strip" aria-label="Tình trạng đơn hàng">
                <span class="report-status-heading">Tình trạng đơn hàng</span>
                <span class="report-status-item"><strong>Tổng</strong> <?php echo (int)$summary['order_count']; ?></span>
                <span class="report-status-item"><strong>Đã giao</strong> <?php echo (int)$summary['delivered_count']; ?></span>
                <span class="report-status-item pending"><strong>Chờ xử lý</strong> <?php echo (int)$summary['pending_count']; ?></span>
                <span class="report-status-item cancelled"><strong>Đã hủy</strong> <?php echo (int)$summary['cancelled_count']; ?></span>
            </section>

            <section class="report-grid">
                <article class="report-panel">
                    <div class="report-panel-heading">
                        <h3>Doanh thu và đơn hàng theo ngày</h3>
                        <span class="report-panel-subtitle">Đơn hàng gồm mọi trạng thái</span>
                    </div>
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Ngày</th>
                                    <th class="number">Đơn hàng</th>
                                    <th>Doanh thu đã giao</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($dailyReport) { ?>
                                    <?php foreach ($dailyReport as $day) {
                                        $dailyRevenue = (float)$day['revenue'];
                                        $dailyRevenueWidth = $maxDailyRevenue > 0 ? round($dailyRevenue / $maxDailyRevenue * 100) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($day['report_date'])); ?></td>
                                            <td class="number"><?php echo (int)$day['order_count']; ?></td>
                                            <td class="report-bar-cell">
                                                <strong><?php echo number_format($dailyRevenue, 0, ',', '.'); ?>đ</strong>
                                                <div class="report-bar"><span style="width: <?php echo $dailyRevenueWidth; ?>%;"></span></div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr><td class="report-empty" colspan="3">Không có đơn hàng trong khoảng thời gian này.</td></tr>
                                <?php } ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>Tổng cộng</td>
                                    <td class="number"><?php echo $dailyOrderTotal; ?></td>
                                    <td class="number"><?php echo number_format($summary['revenue'], 0, ',', '.'); ?>đ</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </article>

                <article class="report-panel">
                    <div class="report-panel-heading">
                        <h3>Top sản phẩm đã giao</h3>
                        <span class="report-panel-subtitle">Xếp theo số lượng bán</span>
                    </div>
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th class="number">Đã bán</th>
                                    <th class="number">Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($topProducts) { ?>
                                    <?php foreach ($topProducts as $index => $product) {
                                        $productQuantity = (int)$product['quantity_sold'];
                                        $productBarWidth = $maxProductQuantity > 0 ? round($productQuantity / $maxProductQuantity * 100) : 0;
                                    ?>
                                        <tr>
                                            <td><span class="product-rank"><?php echo $index + 1; ?></span><?php echo htmlspecialchars($product['product_name']); ?></td>
                                            <td class="number">
                                                <strong><?php echo $productQuantity; ?></strong>
                                                <div class="report-bar"><span style="width: <?php echo $productBarWidth; ?>%;"></span></div>
                                            </td>
                                            <td class="number"><?php echo number_format((float)$product['product_revenue'], 0, ',', '.'); ?>đ</td>
                                        </tr>
                                    <?php } ?>
                                <?php } else { ?>
                                    <tr><td class="report-empty" colspan="3">Chưa có sản phẩm được giao trong kỳ này.</td></tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="report-footnote">Doanh thu sản phẩm dựa trên thành tiền từng mặt hàng trong đơn đã giao. Đơn đã hủy trong kỳ: <?php echo (int)$summary['cancelled_count']; ?>.</p>
                </article>
            </section>

            <section class="report-signatures">
                <div class="report-signature">
                    <strong>NGƯỜI LẬP BÁO CÁO</strong>
                    <span>(Ký và ghi rõ họ tên)</span>
                </div>
                <div class="report-signature">
                    <strong>QUẢN LÝ CỬA HÀNG</strong>
                    <span>(Ký và ghi rõ họ tên)</span>
                </div>
            </section>
            <div class="report-print-footer">TT SHOP · <?php echo htmlspecialchars($reportId); ?> · In ngày <?php echo htmlspecialchars($issuedAt); ?></div>
        </div>
    </main>
</div>
</body>
</html>
