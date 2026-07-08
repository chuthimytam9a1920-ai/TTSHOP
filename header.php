<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ĐẾM GIỎ HÀNG */
$cartCount = 0;

if (isset($_SESSION['cart'])) {

    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['qty'];
    }
}
?>

<header>

    <!-- TOP HEADER -->
    <div class="top-header">

        <!-- LOGO -->
        <div class="logo">
            <a href="index.php">
                TT <span>SHOP</span>
            </a>
        </div>

        <!-- SEARCH -->
        <form class="search-box" action="category.php" method="GET">

            <input 
                type="text" 
                name="search"
                placeholder="Tìm kiếm sản phẩm học tập..."
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
            >

            <button type="submit">
                <i class="fas fa-search"></i>
            </button>

        </form>

        <!-- RIGHT -->
        <div class="header-right">

            <!-- SUPPORT -->
            <div class="support">

                <i class="fas fa-phone-volume"></i>

                <div>
                    <p>1900 888 999</p>
                    <span>Hỗ trợ khách hàng</span>
                </div>

            </div>

            <!-- ACCOUNT -->
            <div class="account">

                <?php if (isset($_SESSION['user_name'])): ?>

                    <a href="taikhoan.php" class="account-link">

                        <div class="header-avatar">
                            <i class="fas fa-user-circle"></i>
                        </div>

                    </a>

                    <div class="user-info">

                        <p>
                            Hi,
                            <b>
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </b>
                        </p>

                        <a href="dangxuat.php" class="logout-link">
                            Đăng xuất
                        </a>

                    </div>

                <?php else: ?>

                    <i class="fas fa-user"></i>

                    <div class="auth-links">

                        <a href="dangnhap.php">
                            Đăng nhập
                        </a>

                        <a href="dangky.php">
                            Đăng ký
                        </a>

                    </div>

                <?php endif; ?>

            </div>

            <!-- CART -->
            <div class="cart">

                <a href="giohang.php" class="cart-link">

                    <i class="fas fa-shopping-bag"></i>
<span class="cart-count" id="cart-count">
    <?php echo $cartCount; ?>
</span>
                </a>

            </div>

        </div>

    </div>
    <!-- MENU -->
    <nav class="menu-bar">

        <ul>

            <!-- VỞ -->
            <li class="menu-item">

                <a href="#">
                    <i class="fas fa-book"></i>
                    Vở ghi - Sổ tay
                    <i class="fas fa-chevron-down"></i>
                </a>

                <ul class="sub-menu">

                    <li>
                        <a href="category.php?type=vo-oly">
                            Vở ô ly
                        </a>
                    </li>

                    <li>
                        <a href="category.php?type=vo-caro">
                            Vở caro
                        </a>
                    </li>

                    <li>
                        <a href="category.php?type=vo-ke-ngang">
                            Vở kẻ ngang
                        </a>
                    </li>

                    <li>
                        <a href="category.php?type=so-loxo">
                            Sổ lò xo
                        </a>
                    </li>

                    <li>
                        <a href="category.php?type=so-da">
                            Sổ da
                        </a>
                    </li>

                </ul>

            </li>

            <!-- BÚT -->
            <li class="menu-item">

                <a href="#">
                    <i class="fas fa-pen"></i>
                    Bút viết
                    <i class="fas fa-chevron-down"></i>
                </a>

                <ul class="sub-menu">

                    <li><a href="category.php?type=but-bi">Bút bi</a></li>
                    <li><a href="category.php?type=but-chi">Bút chì</a></li>
                    <li><a href="category.php?type=but-muc">Bút mực</a></li>
                    <li><a href="category.php?type=but-xoa">Bút xoá</a></li>
                    <li><a href="category.php?type=but-quang">Bút dạ quang</a></li>

                </ul>

            </li>

            <!-- DỤNG CỤ -->
            <li class="menu-item">

                <a href="#">
                    <i class="fas fa-ruler-combined"></i>
                    Dụng cụ học tập
                    <i class="fas fa-chevron-down"></i>
                </a>

                <ul class="sub-menu">

                    <li><a href="category.php?type=thuoc">Thước</a></li>
                    <li><a href="category.php?type=note">Giấy note</a></li>
                    <li><a href="category.php?type=phan">Phấn</a></li>
                    <li><a href="category.php?type=compa">Compa</a></li>
                    <li><a href="category.php?type=hop-but">Hộp bút</a></li>

                </ul>

            </li>

            <!-- VPP -->
            <li class="menu-item">

                <a href="#">
                    <i class="fas fa-paperclip"></i>
                    Văn phòng phẩm
                    <i class="fas fa-chevron-down"></i>
                </a>

                <ul class="sub-menu">

                    <li><a href="category.php?type=giay-in">Giấy in</a></li>
                    <li><a href="category.php?type=kep-giay">Kẹp giấy</a></li>
                    <li><a href="category.php?type=dao-roc">Dao rọc giấy</a></li>
                    <li><a href="category.php?type=bia-cong">Bìa còng</a></li>
                    <li><a href="category.php?type=keo">Kéo văn phòng</a></li>

                </ul>

            </li>

            <!-- BALO -->
            <li class="menu-item">

                <a href="#">
                    <i class="fas fa-school"></i>
                    Balo - Cặp sách
                    <i class="fas fa-chevron-down"></i>
                </a>

                <ul class="sub-menu">

                    <li><a href="category.php?type=balo-hs">Balo học sinh</a></li>
                    <li><a href="category.php?type=balo-vp">Balo văn phòng</a></li>

                </ul>

            </li>

            <!-- MÁY TÍNH -->
            <li class="menu-item">

                <a href="#">
                    <i class="fas fa-calculator"></i>
                    Máy tính cầm tay
                    <i class="fas fa-chevron-down"></i>
                </a>

                <ul class="sub-menu">

                    <li><a href="category.php?type=casio">Casio</a></li>
                    <li><a href="category.php?type=mt-vp">Máy tính văn phòng</a></li>

                </ul>

            </li>

        </ul>

    </nav>

</header>