<?php
// Khởi chạy hoặc khôi phục phiên làm việc (session) để lưu trữ giỏ hàng
session_start();

// Lấy các thông tin của sản phẩm được gửi lên từ nút bấm/form
$id = $_POST['id'];
$name = $_POST['name'];
$price = $_POST['price'];
$img = $_POST['img'];
$qty = $_POST['qty'];

// Kiểm tra nếu trong hệ thống chưa từng có giỏ hàng, tiến hành khởi tạo một mảng trống ['cart']
if(!isset($_SESSION['cart'])){
    $_SESSION['cart'] = [];
}

// TRƯỜNG HỢP 1: Nếu sản phẩm này ĐÃ TỒN TẠI trong giỏ hàng rồi
if(isset($_SESSION['cart'][$id])){
    // Tiến hành cộng dồn số lượng mua mới vào số lượng cũ đang có
    $_SESSION['cart'][$id]['qty'] += $qty;

// TRƯỜNG HỢP 2: Nếu đây là sản phẩm MỚI (chưa có trong giỏ hàng)
}else{
    // Tạo mới một phần tử trong mảng cart với key chính là ID sản phẩm và lưu đầy đủ thông tin của nó
    $_SESSION['cart'][$id] = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'img' => $img,
        'qty' => $qty
    ];
}

// Sau khi cập nhật giỏ hàng thành công, tự động chuyển hướng người dùng sang trang giỏ hàng (giohang.php)
header("Location: giohang.php");
exit();
?>