<?php
// Kiểm tra và khởi chạy session nếu chưa được bật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Lấy ID sản phẩm cần xóa được gửi qua đường dẫn (ví dụ: remove_cart.php?id=123)
$id = $_GET['id'] ?? '';

// Nếu có ID và sản phẩm đó thực sự tồn tại trong giỏ hàng, tiến hành xóa bỏ nó (unset)
if ($id !== '' && isset($_SESSION['cart'][$id])) {
    unset($_SESSION['cart'][$id]);
}

// Sau khi xóa xong, lập tức chuyển hướng người dùng quay trở lại trang giỏ hàng (giohang.php)
header("Location: giohang.php");
exit();
?>