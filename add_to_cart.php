<?php
session_start();

include "connect.php";

$isAjax = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
}

$input = $isAjax ? $_POST : $_GET;
$rawId = $input['id'] ?? '';
$qty = is_scalar($input['qty'] ?? 1) ? (int)($input['qty'] ?? 1) : 1;
$buyNow = ($input['buy_now'] ?? '0') === '1' ? 1 : 0;

// ID số thuộc products; ID cat_N thuộc category_products.
// Chỉ chọn tên bảng từ hai giá trị cố định, không lấy trực tiếp từ URL.
$isCategory = false;
$productId = 0;
if (is_string($rawId) && preg_match('/^(cat_)?([1-9][0-9]{0,9})$/D', $rawId, $matches)) {
    if ((float)$matches[2] <= 2147483647) {
        $isCategory = $matches[1] === 'cat_';
        $productId = (int)$matches[2];
    }
}
$product = null;
if ($productId > 0) {
    $table = $isCategory ? 'category_products' : 'products';
    $stmt = mysqli_prepare($conn, "SELECT * FROM $table WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $productId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

if (!$product) {
    http_response_code(404);
    if ($isAjax) {
        echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy sản phẩm.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    die('Không tìm thấy sản phẩm.');
}

// Giữ nguyên khóa cat_N trong session để không trùng với products.id.
// Tên, giá và ảnh được lấy từ database, không tin dữ liệu POST.
$id = $isCategory ? 'cat_' . $productId : (string)$productId;
$name = $product['name'];
$price = (int)$product['price'];
$old_price = (int)$product['old_price'];
$img = $isCategory ? (string)$product['image'] : 'images/' . $product['image'];

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
