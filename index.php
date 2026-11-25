<?php
header('Content-Type: text/html; charset=UTF-8');
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TT SHOP</title>

    <link rel="stylesheet" href="./style.css?v=10">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {

            function addToCart(button, redirectToCart = false) {
                const productItem = button.closest(".product-item");
                const qtyInput = productItem.querySelector(".qty-input");
                const qty = qtyInput ? qtyInput.value : 1;

                const formData = new FormData();
                formData.append("id", button.dataset.id);
                formData.append("name", button.dataset.name);
                formData.append("price", button.dataset.price);
                formData.append("old_price", button.dataset.oldPrice);
                formData.append("img", button.dataset.img);
                formData.append("qty", qty);

                fetch("add_to_cart.php", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === "success") {

                        const cartCount = document.getElementById("cart-count");

                        if (cartCount) {
                            cartCount.innerText = data.cart_count;
                        }

                        if (redirectToCart) {
                            window.location.href = "giohang.php";
                        } else {
                            Swal.fire({
                                icon: "success",
                                title: "Thông báo",
                                text: data.message,
                                confirmButtonText: "OK"
                            });
                        }

                    } else {
                        Swal.fire("Lỗi", data.message, "error");
                    }
                })
                .catch(() => {
                    Swal.fire("Lỗi", "Không gọi được add_to_cart.php", "error");
                });
            }

            document.querySelectorAll(".add-cart").forEach(button => {
                button.addEventListener("click", function () {
                    addToCart(this, false);
                });
            });

            document.querySelectorAll(".buy-now").forEach(button => {
                button.addEventListener("click", function () {
                    addToCart(this, true);
                });
            });

        });
    </script>
</head>

<body>

<?php include "header.php"; ?>

<!-- BANNER SLIDER -->
<section class="banner-section">
    <div class="single-slider">
        <div class="single-banner-frame">
            <img id="mainBanner" src="images/1.png" alt="Banner chính">
        </div>
    </div>
</section>

<section class="quick-menu">
    <div class="quick-menu-container">

        <a href="#top-sale" class="quick-item">
            <img src="images/ii.png" alt="Top Sale">
            <span>Top Sale</span>
        </a>

        <a href="#vpp" class="quick-item">
            <img src="images/iconvpp.png" alt="Văn phòng phẩm doanh nghiệp">
            <span>Văn phòng phẩm<br>doanh nghiệp</span>
        </a>

        <a href="#vo-ghi" class="quick-item">
            <img src="images/iconvoghi.png" alt="Vở ghi - sổ tay">
            <span>Vở ghi - sổ tay</span>
        </a>

        <a href="#but-viet" class="quick-item">
            <img src="images/iconbut.png" alt="Bút viết">
            <span>Bút viết</span>
        </a>

        <a href="#dung-cu" class="quick-item">
            <img src="images/dcht.jpg" alt="Dụng cụ học tập">
            <span>Dụng cụ học tập</span>
        </a>

        <a href="#balo" class="quick-item">
            <img src="images/balo.webp" alt="Ba lô - Cặp sách">
            <span>Ba lô - Cặp sách</span>
        </a>

        <a href="#may-tinh" class="quick-item">
            <img src="images/MT.jpg" alt="Máy tính cầm tay">
            <span>Máy tính cầm tay</span>
        </a>

        <a href="kiemtradonhang.php" class="quick-item">
            <img src="images/iconktradh.png" alt="Kiểm tra đơn hàng">
            <span>Kiểm tra đơn hàng</span>
        </a>

    </div>
</section>

<?php include "products.php"; ?>

<section class="policy-section">
    <h2>📋 Chính sách bán hàng</h2>

    <div class="policy-container">

        <div class="policy-item">
            <div class="icon">💳</div>
            <h4>THANH TOÁN</h4>
            <p>Nhận hàng kiểm tra mới thanh toán</p>
        </div>

        <div class="policy-item">
            <div class="icon">🛡️</div>
            <h4>BẢO HÀNH</h4>
            <p>1 đổi 1 trong 7 ngày nếu lỗi</p>
        </div>

        <div class="policy-item">
            <div class="icon">🚚</div>
            <h4>VẬN CHUYỂN</h4>
            <p>Giao hàng toàn quốc</p>
        </div>

        <div class="policy-item">
            <div class="icon">📞</div>
            <h4>HOTLINE TƯ VẤN</h4>
            <p>Liên hệ: 1900 888 999</p>
        </div>

    </div>
</section>

<section class="review-section">
    <h2>💬 Khách hàng nói gì về TT SHOP</h2>

    <div class="review-list">

        <div class="review-item">
            <img src="images/av.webp" class="review-avatar" alt="Mai Hương">
            <div class="review-content">
                <h4>Mai Hương <span>⭐⭐⭐⭐⭐</span></h4>
                <p>Vở đẹp lắm luôn, giấy dày, viết rất mượt. Giao hàng nhanh!</p>
            </div>
        </div>

        <div class="review-item">
            <img src="images/aaa.jpg" class="review-avatar" alt="Hồng Oanh">
            <div class="review-content">
                <h4>Hồng Oanh <span>⭐⭐⭐⭐⭐</span></h4>
                <p>Bố cục đẹp, giá rẻ hơn ngoài cửa hàng. Sẽ ủng hộ dài dài ❤️</p>
            </div>
        </div>

        <div class="review-item">
            <img src="images/aaaa.jpg" class="review-avatar" alt="Tien Nguyen">
            <div class="review-content">
                <h4>Tien Nguyen <span>⭐⭐⭐⭐⭐</span></h4>
                <p>Shop tư vấn nhiệt tình, sản phẩm đúng như hình. Rất hài lòng!</p>
            </div>
        </div>

        <div class="review-item">
            <img src="imagescategory/aa.jpg" class="review-avatar" alt="Mỹ Tâm">
            <div class="review-content">
                <h4>Mỹ Tâm <span>⭐⭐⭐⭐⭐</span></h4>
                <p>Mình mua rất nhiều đồ của shop rồi. Mình rất hài lòng!</p>
            </div>
        </div>

        <div class="review-item">
            <img src="imagescategory/s.jpg" class="review-avatar" alt="Ngân">
            <div class="review-content">
                <h4>Ngân <span>⭐⭐⭐⭐⭐</span></h4>
                <p>Shop giao nhanh lắm, hàng chất lượng nữa ❤️</p>
            </div>
        </div>

    </div>
</section>

<?php include "footer.php"; ?>

<script src="script.js?v=30"></script>

</body>
</html>