<?php
// // Kiểm tra xem session (phiên làm việc) đã được bật chưa, nếu chưa thì kích hoạt nó
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// // Lấy dữ liệu giỏ hàng từ session, nếu chưa có sản phẩm nào thì mặc định là một mảng rỗng []
$cart = $_SESSION['cart'] ?? [];

// // Khởi tạo biến lưu tổng tiền của toàn bộ giỏ hàng ban đầu bằng 0
$tongTien = 0;

// // Duyệt qua từng sản phẩm trong giỏ hàng để tính toán tổng tiền
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
<?php include "header.php"; // // Nhúng phần đầu trang (thanh menu, logo,...) vào đây ?>

<div class="cart-wrapper">
    <h1 class="cart-title">GIỎ HÀNG</h1>
    
    <?php if (empty($cart)) { // // Trường hợp 1: Giỏ hàng trống (không có sản phẩm nào) ?>
        <div class="empty-cart">
            <p>Chưa có sản phẩm nào trong giỏ hàng.</p>
            <a href="index.php">← Tiếp tục mua hàng</a>
        </div>
    <?php } else { // // Trường hợp 2: Giỏ hàng có sản phẩm, hiển thị danh sách và phần thanh toán ?>
        <div class="cart-layout">
            
            <!-- // Cột bên trái: Hiển thị danh sách các sản phẩm và ô nhập mã giảm giá -->
            <div class="cart-left">
                <div id="cart-list">
                    <?php 
                    // // Duyệt qua từng item trong giỏ hàng. $cartKey thường là ID sản phẩm, $item chứa thông tin chi tiết
                    foreach ($cart as $cartKey => $item) {
                        $id       = (string)$cartKey; 
                        $name     = $item['name'] ?? '';
                        $img      = $item['img'] ?? '';
                        $price    = (int)($item['price'] ?? 0);
                        $oldPrice = (int)($item['old_price'] ?? 0); 
                        $qty      = (int)($item['qty'] ?? 1);
                        
                        // // Bảo vệ dữ liệu: số lượng tối thiểu luôn phải là 1
                        if ($qty < 1) {
                            $qty = 1;
                        }
                        
                        $thanhTien = $price * $qty; 
                        $discount = 0;              
                        
                        // // Nếu có giá cũ và giá cũ lớn hơn giá hiện tại thì tính toán phần trăm giảm giá (làm tròn xuống)
                        if ($oldPrice > 0 && $oldPrice > $price) {
                            $discount = floor((($oldPrice - $price) / $oldPrice) * 100);
                        }
                    ?>
                        <!-- // Khung bọc thông tin của một sản phẩm, lưu ID vào data-id để JavaScript dễ truy xuất -->
                        <div class="cart-item" data-id="<?php echo htmlspecialchars($id); ?>">
                            
                            <!-- // Nút xóa (X) sản phẩm. Khi click sẽ hiện hộp thoại xác nhận trước khi chuyển hướng đến file xóa -->
                            <a 
                                href="remove_cart.php?id=<?php echo urlencode($id); ?>" 
                                class="remove-btn"
                                onclick="return confirm('Bạn có chắc chắn muốn xoá sản phẩm này khỏi giỏ hàng không?');"
                            >×</a>
                            
                            <!-- // Ảnh sản phẩm -->
                            <img src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($name); ?>">
                            
                            <!-- // Tên sản phẩm và trạng thái kho -->
                            <div class="cart-name">
                                <h3><?php echo htmlspecialchars($name); ?></h3>
                                <p class="stock">✔ Còn hàng</p>
                            </div>
                            
                            <!-- // Hiển thị giá bán hiện tại (định dạng số có dấu chấm phân cách hàng nghìn) -->
                            <div class="cart-price">
                                <?php echo number_format($price, 0, ',', '.'); ?>đ
                            </div>
                            
                            <!-- // Hiển thị giá cũ (nếu có giảm giá) -->
                            <div class="old-price">
                                <?php
                                if ($oldPrice > $price) {
                                    echo number_format($oldPrice, 0, ',', '.') . 'đ';
                                }
                                ?>
                            </div>
                            
                            <!-- // Hiển thị nhãn giảm giá dạng phần trăm (ví dụ: -20%) -->
                            <div class="discount">
                                <?php
                                if ($discount > 0) {
                                    echo '-' . $discount . '%';
                                }
                                ?>
                            </div>
                            
                            <!-- // Bộ công cụ tăng giảm số lượng sản phẩm bằng nút bấm -->
                            <div class="qty-box" data-id="<?php echo htmlspecialchars($id); ?>">
                                <button type="button" class="cart-minus">-</button>
                                <span class="cart-qty"><?php echo $qty; ?></span>
                                <button type="button" class="cart-plus">+</button>
                            </div>
                            
                            <!-- // Hiển thị tổng tiền của riêng sản phẩm này (Thành tiền = Giá * Số lượng) -->
                            <div class="cart-total-item">
                                <?php echo number_format($thanhTien, 0, ',', '.'); ?>đ
                            </div>
                        </div>
                    <?php } ?>
                </div>
                
                <!-- // Khung nhập mã giảm giá (Coupon) -->
                <div class="coupon-box">
                    <input type="text" placeholder="🎫 Nhập mã giảm giá...">
                    <button type="button">Áp dụng</button>
                </div>
                
                <!-- // Nút quay lại trang chủ để tiếp tục xem và mua hàng -->
                <a href="index.php" class="continue-buy">← Tiếp tục mua hàng</a>
            </div>
            
            <!-- // Cột bên phải: Tóm tắt đơn hàng, thông tin hóa đơn và nút tiến hành đặt hàng -->
            <div class="cart-summary">
                <h2>
                    Tổng tiền
                    <!-- // Nơi hiển thị tổng chi phí của cả giỏ hàng -->
                    <span class="summary-total">
                        <?php echo number_format($tongTien, 0, ',', '.'); ?>đ
                    </span>
                </h2>
                
                <!-- // Phần chọn xuất hóa đơn công ty (VAT) -->
                <div class="invoice-check">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin: 0;">
                        <input type="checkbox" id="chk-invoice" style="width: auto; margin: 0;"> Xuất hoá đơn công ty
                    </label>
                </div>
                
                <!-- // Các ô nhập thông tin công ty, mặc định bị ẩn (display: none), chỉ hiện lên khi tích chọn checkbox ở trên -->
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
                
                <!-- // Ô nhập ghi chú thêm cho đơn hàng (ví dụ: giao giờ hành chính, gọi trước khi giao,...) -->
                <label>Ghi chú đơn hàng:</label>
                <textarea name="ghi_chu"></textarea>
                
                <!-- // Nút kích hoạt hành động đặt hàng -->
                <button class="checkout-btn" type="button">Tiến hành đặt hàng</button>
            </div>
        </div>
    <?php } ?>
</div>

<?php include "footer.php"; // // Nhúng phần chân trang vào đây ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // /* // Nhận trạng thái đăng nhập từ PHP truyền sang cho JavaScript (1 là đã login, 0 là chưa) */
    const isLoggedIn = <?php echo $is_logged_in; ?>;
    
    // /* // Lấy các phần tử DOM phục vụ cho chức năng ẩn hiện form hóa đơn công ty */
    const chkInvoice = document.getElementById("chk-invoice");
    const invoiceFields = document.getElementById("invoice-fields");
    
    if (chkInvoice && invoiceFields) {
        // /* // Lắng nghe sự kiện thay đổi trạng thái của checkbox hóa đơn */
        chkInvoice.addEventListener("change", function () {
            if (this.checked) {
                invoiceFields.style.display = "block"; // /* // Nếu tích chọn: Hiện form */
            } else {
                invoiceFields.style.display = "none";  // /* // Nếu bỏ chọn: Ẩn form */
                invoiceFields.querySelectorAll("input").forEach(input => input.value = ""); // /* // Xóa sạch dữ liệu đã nhập trong form */
            }
        });
    }

    // /* // Xử lý sự kiện tăng/giảm số lượng sản phẩm bằng Ajax giúp trải nghiệm không bị tải lại trang */
    document.querySelectorAll(".cart-plus, .cart-minus").forEach(function(btn) {
        btn.addEventListener("click", function () {
            const qtyBox = this.closest(".qty-box");
            const itemBox = this.closest(".cart-item");
            if (!qtyBox || !itemBox) return;
            
            const id = qtyBox.dataset.id; // /* // Lấy ID sản phẩm từ thuộc tính data-id */
            // /* // Xác định hành động là tăng (plus) hay giảm (minus) dựa vào class của nút vừa ấn */
            const action = this.classList.contains("cart-plus") ? "plus" : "minus";
            
            // /* // Đóng gói dữ liệu để gửi lên server */
            const formData = new FormData();
            formData.append("id", id);
            formData.append("action", action);
            
            // /* // Gửi yêu cầu ngầm đến file update_cart.php bằng Fetch API */
            fetch("update_cart.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json()) // /* // Chuyển đổi phản hồi từ server sang định dạng JSON */
            .then(data => {
                if (data.status === "success") {
                    // /* // Nếu số lượng sau cập nhật giảm xuống bằng hoặc nhỏ hơn 0, xóa hẳn hàng sản phẩm đó khỏi giao diện */
                    if (parseInt(data.qty) <= 0) {
                        itemBox.remove();
                    } else {
                        // /* // Ngược lại, cập nhật số lượng mới và tổng tiền mới riêng của sản phẩm đó lên giao diện */
                        qtyBox.querySelector(".cart-qty").innerText = data.qty;
                        itemBox.querySelector(".cart-total-item").innerText = data.item_total;
                    }
                    
                    // /* // Cập nhật lại Tổng số tiền thanh toán của toàn bộ giỏ hàng */
                    const summaryTotal = document.querySelector(".summary-total");
                    if (summaryTotal) {
                        summaryTotal.innerText = data.total;
                    }
                    
                    // /* // Cập nhật số lượng hiển thị trên icon giỏ hàng ở thanh header (nếu có) */
                    const cartCount = document.getElementById("cart-count");
                    if (cartCount) {
                        cartCount.innerText = data.cart_count;
                    }
                    
                    // /* // Nếu giỏ hàng hoàn toàn trống, tải lại trang để hiển thị giao diện "Chưa có sản phẩm nào" */
                    if (parseInt(data.cart_count) <= 0) {
                        location.reload();
                    }
                } else {
                    // /* // Hiển thị thông báo lỗi nếu server trả về trạng thái thất bại */
                    alert(data.message || "Không thể cập nhật giỏ hàng.");
                }
            })
            .catch(function(error) {
                console.error(error);
                alert("Lỗi kết nối update_cart.php");
            });
        });
    });
    // /* // Xử lý sự kiện khi người dùng bấm nút "Tiến hành đặt hàng" */
    const checkoutBtn = document.querySelector(".checkout-btn");
    if (checkoutBtn) {
        checkoutBtn.addEventListener("click", function () {
            // /* // Nếu chưa đăng nhập (isLoggedIn === 0): Báo lỗi và chuyển hướng sang trang đăng ký/đăng nhập */
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