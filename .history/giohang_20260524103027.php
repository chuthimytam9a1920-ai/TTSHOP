<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$cart = $_SESSION['cart'] ?? [];
$tongTien = 0;
foreach ($cart as $item) {
    $price = (int)($item['price'] ?? 0);
    $qty   = (int)($item['qty'] ?? 1);
    $tongTien += $price * $qty;
}
$is_logged_in = (isset($_SESSION['is_login']) && $_SESSION['is_login'] === true) ? 1 : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Giỏ hàng - TT SHOP</title>
    <link rel="stylesheet" href="./style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="./stylegiohang.css?v=<?php echo time(); ?>">
</head>
<body>
<?php include "header.php"; ?>
<div class="cart-wrapper">
    <h1 class="cart-title">GIỎ HÀNG</h1>
    <?php if (empty($cart)) { ?>
        <div class="empty-cart">
            <p>Chưa có sản phẩm nào trong giỏ hàng.</p>
            <a href="index.php">← Tiếp tục mua hàng</a>
        </div>
    <?php } else { ?>
        <div class="cart-layout">
            <div class="cart-left">
                <div id="cart-list">
                    <?php foreach ($cart as $cartKey => $item) {
                        $id       = (string)$cartKey;
                        $name     = $item['name'] ?? '';
                        $img      = $item['img'] ?? '';
                        $price    = (int)($item['price'] ?? 0);
                        $oldPrice = (int)($item['old_price'] ?? 0);
                        $qty      = (int)($item['qty'] ?? 1);
                        if ($qty < 1) {
                            $qty = 1;
                        }
                        $thanhTien = $price * $qty;
                        $discount = 0;
                        if ($oldPrice > 0 && $oldPrice > $price) {
                            $discount = floor((($oldPrice - $price) / $oldPrice) * 100);
                        }
                    ?>
                        <div class="cart-item" data-id="<?php echo htmlspecialchars($id); ?>">
                            <a 
                                href="remove_cart.php?id=<?php echo urlencode($id); ?>" 
                                class="remove-btn"
                                onclick="return confirm('Bạn có chắc chắn muốn xoá sản phẩm này khỏi giỏ hàng không?');"
                            >×</a>
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                            <div class="cart-name">
                                <h3><?php echo htmlspecialchars($name); ?></h3>
                                <p class="stock">✔ Còn hàng</p>
                            </div>
                            <div class="cart-price">
                                <?php echo number_format($price, 0, ',', '.'); ?>đ
                            </div>
                            <div class="old-price">
                                <?php
                                if ($oldPrice > $price) {
                                    echo number_format($oldPrice, 0, ',', '.') . 'đ';
                                }
                                ?>
                            </div>
                            <div class="discount">
                                <?php
                                if ($discount > 0) {
                                    echo '-' . $discount . '%';
                                }
                                ?>
                            </div>
                            <div class="qty-box" data-id="<?php echo htmlspecialchars($id); ?>">
                                <button type="button" class="cart-minus">-</button>
                                <span class="cart-qty"><?php echo $qty; ?></span>
                                <button type="button" class="cart-plus">+</button>
                            </div>
                            <div class="cart-total-item">
                                <?php echo number_format($thanhTien, 0, ',', '.'); ?>đ
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="coupon-box">
                    <input type="text" placeholder="🎫 Nhập mã giảm giá...">
                    <button type="button">Áp dụng</button>
                </div>
                <a href="index.php" class="continue-buy">← Tiếp tục mua hàng</a>
            </div>
            <div class="cart-summary">
                <h2>
                    Tổng tiền
                    <span class="summary-total">
                        <?php echo number_format($tongTien, 0, ',', '.'); ?>đ
                    </span>
                </h2>
                <div class="invoice-check">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="chk-invoice" style="width: auto; margin: 0;"> Xuất hoá đơn công ty
                    </label>
                </div>
                <div id="invoice-fields" style="display: none;">
                    <label>Tên đơn vị mua hàng:</label>
                    <input type="text" name="ten_cong_ty" placeholder="Công Ty Trách Nhiệm Hữu Hạn ABCXYZ">
                    <label>Mã số thuế:</label>
                    <input type="text" name="ma_so_thue" placeholder="123456789">
                    <label>Địa chỉ công ty:</label>
                    <input type="text" name="dia_chi_cong_ty" placeholder="123 Nguyễn Huệ, phường 6, Quận 8, Thành phố Hồ Chí Minh">
                    <label>Email công ty:</label>
                    <input type="email" name="email_cong_ty" placeholder="ctytnhhabcxyz123@gmail.com">
                </div>
                <p class="note-red">
                    *Lưu ý: Nhập rõ ràng và đầy đủ thông tin hóa đơn,
                    không viết tắt phường/xã, quận/huyện, tỉnh/thành phố, tên công ty.
                </p>
                <label>Ghi chú đơn hàng:</label>
                <textarea name="ghi_chu"></textarea>
                <button class="checkout-btn" type="button">Tiến hành đặt hàng</button>
            </div>
        </div>
    <?php } ?>
</div>
<?php include "footer.php"; ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const isLoggedIn = <?php echo $is_logged_in; ?>;
    const chkInvoice = document.getElementById("chk-invoice");
    const invoiceFields = document.getElementById("invoice-fields");
    if (chkInvoice && invoiceFields) {
        chkInvoice.addEventListener("change", function () {
            if (this.checked) {
                invoiceFields.style.display = "block";
            } else {
                invoiceFields.style.display = "none";
                invoiceFields.querySelectorAll("input").forEach(input => input.value = "");
            }
        });
    }
    document.querySelectorAll(".cart-plus, .cart-minus").forEach(function(btn) {
        btn.addEventListener("click", function () {
            const qtyBox = this.closest(".qty-box");
            const itemBox = this.closest(".cart-item");
            if (!qtyBox || !itemBox) return;
            const id = qtyBox.dataset.id;
            const action = this.classList.contains("cart-plus") ? "plus" : "minus";
            const formData = new FormData();
            formData.append("id", id);
            formData.append("action", action);
            fetch("update_cart.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    if (parseInt(data.qty) <= 0) {
                        itemBox.remove();
                    } else {
                        qtyBox.querySelector(".cart-qty").innerText = data.qty;
                        itemBox.querySelector(".cart-total-item").innerText = data.item_total;
                    }
                    const summaryTotal = document.querySelector(".summary-total");
                    if (summaryTotal) {
                        summaryTotal.innerText = data.total;
                    }
                    const cartCount = document.getElementById("cart-count");
                    if (cartCount) {
                        cartCount.innerText = data.cart_count;
                    }
                    if (parseInt(data.cart_count) <= 0) {
                        location.reload();
                    }
                } else {
                    alert(data.message || "Không thể cập nhật giỏ hàng.");
                }
            })
            .catch(function(error) {
                console.error(error);
                alert("Lỗi kết nối update_cart.php");
            });
        });
    });
    const checkoutBtn = document.querySelector(".checkout-btn");
    if (checkoutBtn) {
        checkoutBtn.addEventListener("click", function () {
            if (isLoggedIn === 0) {
                alert("Bạn cần đăng nhập hoặc đăng ký tài khoản để đặt hàng.");
                window.location.href = "dangky.php";
            } else {
                window.location.href = "thanhtoan.php";
            }
        });
    }
});
</script>
</body>
</html> 