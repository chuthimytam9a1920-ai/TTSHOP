<?php
session_start();

header('Content-Type: application/json; charset=UTF-8');

$id = $_POST['id'] ?? '';
$action = $_POST['action'] ?? '';

if ($id == '' || !isset($_SESSION['cart'][$id])) {
    echo json_encode(['status' => 'error']);
    exit;
}

if ($action === 'plus') {
    $_SESSION['cart'][$id]['qty']++;
}

if ($action === 'minus') {
    $_SESSION['cart'][$id]['qty']--;

    if ($_SESSION['cart'][$id]['qty'] < 1) {
        $_SESSION['cart'][$id]['qty'] = 1;
    }
}

$item = $_SESSION['cart'][$id];

$itemTotal = $item['price'] * $item['qty'];

$total = 0;
$count = 0;

foreach ($_SESSION['cart'] as $cartItem) {
    $total += $cartItem['price'] * $cartItem['qty'];
    $count += $cartItem['qty'];
}

echo json_encode([
    'status' => 'success',
    'qty' => $item['qty'],
    'item_total' => number_format($itemTotal, 0, ',', '.') . 'đ',
    'total' => number_format($total, 0, ',', '.') . 'đ',
    'cart_count' => $count
]);