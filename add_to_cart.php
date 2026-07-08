<?php
session_start();

include "connect.php";

$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');

    $id = $_POST['id'] ?? '';
    $name = $_POST['name'] ?? '';
    $price = (int)($_POST['price'] ?? 0);
    $old_price = (int)($_POST['old_price'] ?? 0);
    $img = $_POST['img'] ?? '';
    $qty = (int)($_POST['qty'] ?? 1);
    $buyNow = (int)($_POST['buy_now'] ?? 0);
} else {
    $id = $_GET['id'] ?? '';
    $qty = (int)($_GET['qty'] ?? 1);
    $buyNow = (int)($_GET['buy_now'] ?? 0);

    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);

    if (!$product) {
        die("Không tìm thấy sản phẩm.");
    }

    $name = $product['name'];
    $price = (int)$product['price'];
    $old_price = (int)$product['old_price'];
    $img = "images/" . $product['image'];
}

if ($qty < 1) {
    $qty = 1;
}

if ($id == '' || $name == '' || $price <= 0) {
    if ($isAjax) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Dữ liệu sản phẩm không hợp lệ'
        ]);
        exit;
    }

    die("Dữ liệu sản phẩm không hợp lệ.");
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (isset($_SESSION['cart'][$id])) {
    $_SESSION['cart'][$id]['qty'] += $qty;
    $_SESSION['cart'][$id]['name'] = $name;
    $_SESSION['cart'][$id]['price'] = $price;
    $_SESSION['cart'][$id]['old_price'] = $old_price;
    $_SESSION['cart'][$id]['img'] = $img;
} else {
    $_SESSION['cart'][$id] = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'old_price' => $old_price,
        'img' => $img,
        'qty' => $qty
    ];
}

$cartCount = 0;
$cartTotal = 0;

foreach ($_SESSION['cart'] as $item) {
    $cartCount += (int)$item['qty'];
    $cartTotal += (int)$item['price'] * (int)$item['qty'];
}

if ($isAjax) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Đã thêm vào giỏ hàng thành công!',
        'cart_count' => $cartCount,
        'cart_total' => number_format($cartTotal, 0, ',', '.') . 'đ',
        'redirect' => $buyNow == 1 ? 'giohang.php' : ''
    ]);
    exit;
}

if ($buyNow == 1) {
    header("Location: giohang.php");
    exit;
}

header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;