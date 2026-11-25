
<?php
// Xác định menu đang hoạt động dựa trên tên file.

$currentFile = basename($_SERVER['SCRIPT_NAME'] ?? '');

$menuPages = [

    'index.php' => 'dashboard',

    'products.php' => 'products',

    'add_product.php' => 'products',

    'edit_product.php' => 'products',

    'category_products.php' => 'category_products',

    'add_category_product.php' => 'category_products',

    'edit_category_product.php' => 'category_products',

    'orders.php' => 'orders',
    'reports.php' => 'reports',

    'order_detail.php' => 'orders',

    'customers.php' => 'customers',

    'vouchers.php' => 'vouchers',

    'chat.php' => 'chat'
];

// Giữ tương thích với biến $page của các trang Admin cũ.

$activePage = $page ?? ($menuPages[$currentFile] ?? '');

?>

<aside class="sidebar">
    <a class="admin-brand" href="index.php">
        <span class="brand-mark">TT</span>
        <span><strong>TT SHOP</strong><small>QUẢN TRỊ CỬA HÀNG</small></span>
    </a>

    <nav class="admin-nav" aria-label="Điều hướng quản trị">
        <p class="nav-heading">TỔNG QUAN</p>
        <a href="index.php" class="<?php echo ($activePage === 'dashboard') ? 'active' : ''; ?>">
            <span class="nav-icon">⌂</span><span>Tổng quan</span>
        </a>

        <p class="nav-heading">BÁN HÀNG</p>
        <a href="orders.php" class="<?php echo ($activePage === 'orders') ? 'active' : ''; ?>">
            <span class="nav-icon">▤</span><span>Đơn hàng</span>
        </a>
        <a href="reports.php" class="<?php echo ($activePage === 'reports') ? 'active' : ''; ?>">
            <span class="nav-icon">▥</span><span>Báo cáo</span>
        </a>
        <a href="products.php" class="<?php echo ($activePage === 'products') ? 'active' : ''; ?>">
            <span class="nav-icon">□</span><span>Sản phẩm trang chủ</span>
        </a>
        <a href="category_products.php" class="<?php echo ($activePage === 'category_products') ? 'active' : ''; ?>">
            <span class="nav-icon">▦</span><span>Sản phẩm danh mục</span>
        </a>

        <a href="vouchers.php" class="<?= $activePage === 'vouchers' ? 'active' : '' ?>">
            <span class="nav-icon">◇</span><span>Quản lý mã giảm giá</span>
        </a>

        <p class="nav-heading">KHÁCH HÀNG</p>
        <a href="customers.php" class="<?php echo ($activePage === 'customers') ? 'active' : ''; ?>">
            <span class="nav-icon">◎</span><span>Khách hàng</span>
        </a>
        <a href="chat.php" class="<?php echo ($activePage === 'chat') ? 'active' : ''; ?>">
            <span class="nav-icon">✉</span><span>Tin nhắn</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../index.php" target="_blank" rel="noopener"><span class="nav-icon">↗</span><span>Xem cửa hàng</span></a>
        <a href="logout.php" class="sidebar-logout"><span class="nav-icon">⇥</span><span>Đăng xuất</span></a>
    </div>
</aside>
