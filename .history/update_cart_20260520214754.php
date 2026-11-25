<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();}
header('Content-Type: application/json; charset=utf-8');
$id = $_POST['id'] ?? '';
$action = $_POST['action'] ?? '';
if ($id === '' || !isset($_SESSION['cart'][$id])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Không tìm thấy sản phẩm trong giỏ hàng.'
    ]);
    exit();}
if ($action === 'plus') {
    $_SESSION['cart'][$id]['qty'] = (int)($_SESSION['cart'][$id]['qty'] ?? 1) + 1;}
if ($action === 'minus') {
    $_SESSION['cart'][$id]['qty'] = (int)($_SESSION['cart'][$id]['qty'] ?? 1) - 1;
    if ($_SESSION['cart'][$id]['qty'] <= 0) {
        unset($_SESSION['cart'][$id]); }}
$total = 0;
$cart_count = 0;
$item_total = "0đ";
$qty = 0;
if (isset($_SESSION['cart'][$id])) {
    $price = (int)($_SESSION['cart'][$id]['price'] ?? 0);
    $qty = (int)($_SESSION['cart'][$id]['qty'] ?? 1);
    $item_total = number_format($price * $qty, 0, ',', '.') . 'đ';
}
foreach ($_SESSION['cart'] as $item) {
    $price = (int)($item['price'] ?? 0);
    $itemQty = (int)($item['qty'] ?? 1);
    $total += $price * $itemQty;
    $cart_count += $itemQty;
}
echo json_encode([
    'status' => 'success',
    'qty' => $qty,
    'item_total' => $item_total,
    'total' => number_format($total, 0, ',', '.') . 'đ',
    'cart_count' => $cart_count
]);
exit();
?>