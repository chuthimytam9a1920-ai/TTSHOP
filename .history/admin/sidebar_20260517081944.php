<aside class="sidebar">

    <h2>TT SHOP ADMIN</h2>

    <a href="index.php"
       class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
        Dashboard
    </a>

    <a href="products.php"
       class="<?php echo ($page == 'products') ? 'active' : ''; ?>">
        Sản phẩm trang chủ
    </a>

    <a href="category_products.php"
       class="<?php echo ($page == 'category_products') ? 'active' : ''; ?>">
        Sản phẩm danh mục
    </a>

    <a href="orders.php"
       class="<?php echo ($page == 'orders') ? 'active' : ''; ?>">
        Quản lý đơn hàng
    </a>

<a href="customers.php" class="<?php echo ($page == 'customers') ? 'active' : ''; ?>">
    Quản lý khách hàng
</a>
<a href="chat.php"
   class="<?php echo ($page == 'chat') ? 'active' : ''; ?>">
    Chat khách hàng
</a>
    <a href="../index.php">
        Về trang chủ
    </a>

</aside>