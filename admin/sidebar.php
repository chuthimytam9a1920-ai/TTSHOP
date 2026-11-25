
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

    'order_detail.php' => 'orders',

    'customers.php' => 'customers',

    'vouchers.php' => 'vouchers',

    'chat.php' => 'chat'
];

// Giữ tương thích với biến $page của các trang Admin cũ.

$activePage = $page ?? ($menuPages[$currentFile] ?? '');

?>

<aside class="sidebar">

    <h2>TT SHOP ADMIN</h2>

    <!-- DASHBOARD -->

    <a href="index.php"
       class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">

        Dashboard

    </a>

    <!-- SẢN PHẨM TRANG CHỦ -->

    <a href="products.php"
       class="<?= $activePage === 'products' ? 'active' : '' ?>">

        Sản phẩm trang chủ

    </a>

    <!-- SẢN PHẨM DANH MỤC -->

    <a href="category_products.php"
       class="<?= $activePage === 'category_products' ? 'active' : '' ?>">

        Sản phẩm danh mục

    </a>

    <!-- QUẢN LÝ ĐƠN HÀNG -->

    <a href="orders.php"
       class="<?= $activePage === 'orders' ? 'active' : '' ?>">

        Quản lý đơn hàng

    </a>

    <!-- QUẢN LÝ KHÁCH HÀNG -->

    <a href="customers.php"
       class="<?= $activePage === 'customers' ? 'active' : '' ?>">

        Quản lý khách hàng

    </a>

    <!-- QUẢN LÝ MÃ GIẢM GIÁ -->

    <a href="vouchers.php"
       class="<?= $activePage === 'vouchers' ? 'active' : '' ?>">

        Quản lý mã giảm giá

    </a>

    <!-- CHAT KHÁCH HÀNG -->

    <a href="chat.php"
       class="<?= $activePage === 'chat' ? 'active' : '' ?>">

        Chat khách hàng

    </a>

    <!-- VỀ TRANG CHỦ -->

    <a href="../index.php">

        Về trang chủ

    </a>

</aside>
