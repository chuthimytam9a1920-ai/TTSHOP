<?php
include "connect.php";

$rawId = $_GET['id'] ?? '';
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
    die("Không tìm thấy sản phẩm.");
}

$cartId = $isCategory ? 'cat_' . $productId : (string)$productId;
$imagePath = $isCategory ? (string)$product['image'] : 'images/' . $product['image'];
$backUrl = $isCategory ? 'category.php?type=' . rawurlencode($product['type_slug']) : 'index.php';
$backLabel = $isCategory ? 'Quay lại danh mục' : 'Quay lại trang chủ';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($product['name']); ?></title>

    <style>
        body {
            margin: 0;
            font-family: Arial;
            background: #eefaf4;
        }

        .detail-wrapper {
            max-width: 1100px;
            margin: 40px auto;
            background: white;
            border-radius: 18px;
            padding: 35px;
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 40px;
            box-shadow: 0 8px 22px rgba(0,0,0,.12);
        }

        .product-img {
            width: 100%;
            height: 420px;
            object-fit: contain;
            border: 1px solid #eee;
            border-radius: 14px;
        }

        h1 {
            margin-top: 0;
            font-size: 32px;
        }

        .price {
            color: #00974f;
            font-size: 32px;
            font-weight: bold;
            margin: 15px 0;
        }

        .old-price {
            color: #777;
            text-decoration: line-through;
            font-size: 20px;
            margin-right: 12px;
        }

        .discount {
            background: #ffd6d6;
            color: red;
            padding: 7px 12px;
            border-radius: 18px;
            font-weight: bold;
        }

        .desc {
            margin-top: 25px;
            line-height: 1.8;
            font-size: 17px;
            white-space: pre-line;
        }

        .btn-group {
            margin-top: 30px;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn {
            padding: 13px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            display: inline-block;
        }

        .add-cart-form {
            margin: 0;
        }

        .cart {
            background: white;
            border: 1px solid #00974f;
            color: #00974f;
            cursor: pointer;
        }

        .buy {
            background: #00974f;
            color: white;
            border: 1px solid #00974f;
            cursor: pointer;
        }

        .back {
            display: inline-block;
            margin-top: 20px;
            color: #00974f;
            font-weight: bold;
            text-decoration: none;
        }
    </style>
</head>

<body>

<div class="detail-wrapper">
    <div>
        <img 
            class="product-img" 
            src="<?php echo htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8'); ?>" 
            alt="<?php echo htmlspecialchars($product['name']); ?>"
        >
    </div>

    <div>
        <h1><?php echo htmlspecialchars($product['name']); ?></h1>

        <div>
            <span class="price">
                <?php echo number_format((int)$product['price'], 0, ',', '.'); ?>đ
            </span>
        </div>

        <?php if (!empty($product['old_price']) && $product['old_price'] > $product['price']) { ?>
            <?php
                $discount = round((($product['old_price'] - $product['price']) / $product['old_price']) * 100);
            ?>
            <div>
                <span class="old-price">
                    <?php echo number_format((int)$product['old_price'], 0, ',', '.'); ?>đ
                </span>

                <span class="discount">
                    -<?php echo $discount; ?>%
                </span>
            </div>
        <?php } ?>

        <div class="desc">
            <h3>Thông tin chi tiết</h3>

            <?php if (!empty($product['mo_ta_chi_tiet'])) { ?>
                <?php echo nl2br(htmlspecialchars($product['mo_ta_chi_tiet'])); ?>
            <?php } else { ?>
                Sản phẩm chưa có thông tin chi tiết.
            <?php } ?>
        </div>

        <div class="btn-group">
            <form method="POST" action="add_to_cart.php" class="add-cart-form">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($cartId, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="qty" value="1">
                <button type="submit" class="btn cart">🛒 Thêm vào giỏ</button>
            </form>

            <form method="POST" action="add_to_cart.php" class="add-cart-form">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($cartId, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="qty" value="1">
                <input type="hidden" name="buy_now" value="1">
                <button type="submit" class="btn buy">Mua ngay</button>
            </form>
        </div>

        <a class="back" href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>">← <?php echo htmlspecialchars($backLabel, ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.querySelectorAll('.add-cart-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        fetch('add_to_cart.php', {
            method: 'POST',
            body: new FormData(form)
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.status === 'success') {
                if (data.redirect === 'giohang.php') {
                    window.location.href = data.redirect;
                    return;
                }
                Swal.fire({
                    icon: 'success',
                    title: 'Thông báo',
                    text: data.message,
                    confirmButtonText: 'OK'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Lỗi',
                    text: data.message || 'Không thể thêm vào giỏ hàng'
                });
            }
        })
        .catch(function() {
            Swal.fire('Lỗi', 'Không thể kết nối. Vui lòng thử lại.', 'error');
        });
    });
});
</script>

</body>
</html>