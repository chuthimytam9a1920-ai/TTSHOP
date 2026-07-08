<?php
// Kiểm tra và khởi chạy session nếu chưa được bật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Khai báo định dạng phản hồi trả về là JSON (dùng cho Ajax Fetch ở font-end)
header('Content-Type: application/json; charset=utf-8');

// Nhận ID sản phẩm và hành động (plus/minus) gửi lên từ giao diện
$id = $_POST['id'] ?? '';
$action = $_POST['action'] ?? '';

// Kiểm tra dữ liệu: Nếu thiếu ID hoặc sản phẩm không tồn tại trong giỏ hàng thì dừng lại và trả về lỗi
if ($id === '' || !isset($_SESSION['cart'][$id])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'
    ]);
    exit();
}

// Xử lý hành động "plus": Tăng số lượng sản phẩm hiện tại lên 1 đơn vị
if ($action === 'plus') {
    $_SESSION['cart'][$id]['qty'] = (int)($_SESSION['cart'][$id]['qty'] ?? 1) + 1;
}

// Xử lý hành động "minus": Giảm số lượng đi 1. Nếu số lượng bằng hoặc nhỏ hơn 0 thì xóa hẳn sản phẩm khỏi giỏ hàng (unset)
if ($action === 'minus') {
    $_SESSION['cart'][$id]['qty'] = (int)($_SESSION['cart'][$id]['qty'] ?? 1) - 1;
    if ($_SESSION['cart'][$id]['qty'] <= 0) {
        unset($_SESSION['cart'][$id]); 
    }
}

// Khởi tạo các biến để tính toán lại số liệu mới sau khi tăng/giảm
$total = 0;
$cart_count = 0;
$item_total = "0đ";
$qty = 0;

// Tính riêng "Thành tiền" (Giá x Số lượng) của sản phẩm vừa chỉnh sửa (nếu sản phẩm đó chưa bị xóa)
if (isset($_SESSION['cart'][$id])) {
    $price = (int)($_SESSION['cart'][$id]['price'] ?? 0);
    $qty = (int)($_SESSION['cart'][$id]['qty'] ?? 1);
    $item_total = number_format($price * $qty, 0, ',', '.') . 'đ';
}

// Vòng lặp duyệt qua toàn bộ giỏ hàng để tính lại: Tổng tiền mới ($total) và Tổng số lượng tất cả sản phẩm ($cart_count)
foreach ($_SESSION['cart'] as $item) {
    $price = (int)($item['price'] ?? 0);
    $itemQty = (int)($item['qty'] ?? 1);
    $total += $price * $itemQty;
    $cart_count += $itemQty;
}

// Trả về chuỗi JSON chứa toàn bộ kết quả mới để JavaScript ở giao diện cập nhật ngay lập tức mà không cần load lại trang
echo json_encode([
    'status' => 'success',
    'qty' => $qty,
    'item_total' => $item_total,
    'total' => number_format($total, 0, ',', '.') . 'đ',
    'cart_count' => $cart_count
]);
exit();
?>