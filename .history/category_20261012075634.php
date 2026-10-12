<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "connect.php";

$type = $_GET['type'] ?? 'vo-oly';

$categoryNames = [
    'vo-oly' => 'Vở ô ly',
    'vo-caro' => 'Vở caro',
    'vo-ke-ngang' => 'Vở kẻ ngang',
    'so-loxo' => 'Sổ lò xo',
    'so-da' => 'Sổ da',
    'but-bi' => 'Bút bi',
    'but-chi' => 'Bút chì',
    'but-muc' => 'Bút mực',
    'but-xoa' => 'Bút xoá',
    'but-quang' => 'Bút dạ quang',
    'thuoc' => 'Thước',
    'note' => 'Giấy note',
    'phan' => 'Phấn',
    'compa' => 'Compa',
    'hop-but' => 'Hộp bút',
    'giay-in' => 'Giấy in',
    'kep-giay' => 'Kẹp giấy',
    'dao-roc' => 'Dao rọc giấy',
    'bia-cong' => 'Bìa còng',
    'keo' => 'Kéo văn phòng',
    'balo-hs' => 'Balo học sinh',
    'balo-vp' => 'Balo văn phòng',
    'casio' => 'Casio',
    'mt-vp' => 'Máy tính văn phòng'
];

$title = $categoryNames[$type] ?? 'Danh mục sản phẩm';

$categoryBanners = [
    'vo-oly' => 'imagescategory/vs.png',
    'vo-caro' => 'imagescategory/vs.png',
    'vo-ke-ngang' => 'imagescategory/vs.png',
    'so-loxo' => 'imagescategory/vs.png',
    'so-da' => 'imagescategory/vs.png',

    'but-bi' => 'imagescategory/bu.png',
    'but-chi' => 'imagescategory/bu.png',
    'but-muc' => 'imagescategory/bu.png',
    'but-xoa' => 'imagescategory/bu.png',
    'but-quang' => 'imagescategory/bu.png',

    'thuoc' => 'imagescategory/đư.png',
    'note' => 'imagescategory/đư.png',
    'phan' => 'imagescategory/đư.png',
    'compa' => 'imagescategory/đư.png',
    'hop-but' => 'imagescategory/đư.png',

    'giay-in' => 'imagescategory/ba.png',
    'kep-giay' => 'imagescategory/ba.png',
    'dao-roc' => 'imagescategory/ba.png',
    'bia-cong' => 'imagescategory/ba.png',
    'keo' => 'imagescategory/ba.png',

    'balo-hs'=>'imagescategory/olyy.png'
];

$banner = $categoryBanners[$type] ?? 'images/olyy.png';

$search = trim($_GET['search'] ?? '');
$filter = $_GET['filter'] ?? [];
$priceFilter = $_GET['price'] ?? [];
$sort = $_GET['sort'] ?? '';

if (!is_array($filter)) $filter = [];
if (!is_array($priceFilter)) $priceFilter = [];

$where = [];
$params = [];
$types = "";

if ($search !== '') {
    $where[] = "name LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
    $title = "Kết quả tìm kiếm: " . $search;
} else {
    $where[] = "type_slug = ?";
    $params[] = $type;
    $types .= "s";
}

if (!empty($filter)) {
    $filterSql = [];

    if (in_array('new', $filter)) {
        $filterSql[] = "is_new = 1";
    }

    if (in_array('hot', $filter)) {
        $filterSql[] = "sold_count > 500";
    }

    if (in_array('sale', $filter)) {
        $filterSql[] = "old_price > price";
    }

    if (!empty($filterSql)) {
        $where[] = "(" . implode(" OR ", $filterSql) . ")";
    }
}

if (!empty($priceFilter)) {
    $priceSql = [];

    if (in_array('under50', $priceFilter)) {
        $priceSql[] = "price < 50000";
    }

    if (in_array('50to100', $priceFilter)) {
        $priceSql[] = "price >= 50000 AND price <= 100000";
    }

    if (in_array('100to500', $priceFilter)) {
        $priceSql[] = "price > 100000 AND price <= 500000";
    }

    if (in_array('500to800', $priceFilter)) {
        $priceSql[] = "price > 500000 AND price <= 800000";
    }

    if (in_array('over800', $priceFilter)) {
        $priceSql[] = "price > 800000";
    }

    if (!empty($priceSql)) {
        $where[] = "(" . implode(" OR ", $priceSql) . ")";
    }
}

$whereSql = implode(" AND ", $where);

$orderSql = "ORDER BY id DESC";

if ($sort === 'asc') {
    $orderSql = "ORDER BY price ASC";
}

if ($sort === 'desc') {
    $orderSql = "ORDER BY price DESC";
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$limit = 4;
$start = ($page - 1) * $limit;

$sqlCount = "SELECT COUNT(*) AS total FROM category_products WHERE $whereSql";
$stmtCount = mysqli_prepare($conn, $sqlCount);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmtCount, $types, ...$params);
}

mysqli_stmt_execute($stmtCount);
$countResult = mysqli_stmt_get_result($stmtCount);
$countRow = mysqli_fetch_assoc($countResult);

$total = $countRow['total'] ?? 0;
$totalPages = ceil($total / $limit);

$sql = "SELECT * FROM category_products WHERE $whereSql $orderSql LIMIT ?, ?";

$paramsProduct = $params;
$paramsProduct[] = $start;
$paramsProduct[] = $limit;

$typesProduct = $types . "ii";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, $typesProduct, ...$paramsProduct);
mysqli_stmt_execute($stmt);

$resultProducts = mysqli_stmt_get_result($stmt);

function buildQuery($extra = []) {
    $query = $_GET;

    foreach ($extra as $key => $value) {
        $query[$key] = $value;
    }

    return http_build_query($query);
}

function tinhGiamGia($oldPrice, $price) {
    $oldPrice = (int)$oldPrice;
    $price = (int)$price;

    if ($oldPrice > 0 && $price > 0 && $oldPrice > $price) {
        return round((($oldPrice - $price) / $oldPrice) * 100);
    }

    return 0;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?> - TT SHOP</title>

    <link rel="stylesheet" href="./style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./stylecategory.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body>

<?php include "header.php"; ?>

<section class="category-page">

    <div class="breadcrumb">
        Trang chủ / Danh mục / <b><?php echo htmlspecialchars($title); ?></b>
    </div>

    <div class="category-layout">

        <aside class="filter-box">

            <form method="GET" id="filterForm">

                <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">

                <?php if ($search !== '') { ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php } ?>

                <h3>LOẠI SẢN PHẨM</h3>

                <label>
                    <input type="checkbox" name="filter[]" value="new"
                        onchange="document.getElementById('filterForm').submit()"
                        <?php echo in_array('new', $filter) ? 'checked' : ''; ?>>
                    Hàng mới
                </label>

                <label>
                    <input type="checkbox" name="filter[]" value="sale"
                        onchange="document.getElementById('filterForm').submit()"
                        <?php echo in_array('sale', $filter) ? 'checked' : ''; ?>>
                    Đang giảm giá
                </label>

                <label>
                    <input type="checkbox" name="filter[]" value="hot"
                        onchange="document.getElementById('filterForm').submit()"
                        <?php echo in_array('hot', $filter) ? 'checked' : ''; ?>>
                    Bán chạy
                </label>

                <h3>MỨC GIÁ</h3>

                <label>
                    <input type="checkbox" name="price[]" value="under50"
                        onchange="document.getElementById('filterForm').submit()"
                        <?php echo in_array('under50', $priceFilter) ? 'checked' : ''; ?>>
                    Dưới 50.000đ
                </label>

                <label>
                    <input type="checkbox" name="price[]" value="50to100"
                        onchange="document.getElementById('filterForm').submit()"
                        <?php echo in_array('50to100', $priceFilter) ? 'checked' : ''; ?>>
                    50.000đ - 100.000đ
                </label>

                <label>
                    <input type="checkbox" name="price[]" value="100to500"
                        onchange="document.getElementById('filterForm').submit()"
                        <?php echo in_array('100to500', $priceFilter) ? 'checked' : ''; ?>>
                    100.000đ - 500.000đ
                </label>

                <label>
                    <input type="checkbox" name="price[]" value="500to800"
                        onchange="document.getElementById('filterForm').submit()"
                        <?php echo in_array('500to800', $priceFilter) ? 'checked' : ''; ?>>
                    500.000đ - 800.000đ
                </label>

                <label>
                    <input type="checkbox" name="price[]" value="over800"
                        onchange="document.getElementById('filterForm').submit()"
                        <?php echo in_array('over800', $priceFilter) ? 'checked' : ''; ?>>
                    Trên 800.000đ
                </label>

            </form>

        </aside>

        <main class="category-main">

            <img class="category-banner"
                 src="<?php echo htmlspecialchars($banner); ?>"
                 alt="<?php echo htmlspecialchars($title); ?>">

            <h2><?php echo htmlspecialchars($title); ?></h2>

            <div class="sort-bar">
                <span>Sắp xếp:</span>

                <a href="?<?php echo buildQuery(['sort' => 'asc', 'page' => 1]); ?>"
                   class="<?php echo ($sort == 'asc') ? 'active' : ''; ?>">
                    Giá tăng dần
                </a>

                <a href="?<?php echo buildQuery(['sort' => 'desc', 'page' => 1]); ?>"
                   class="<?php echo ($sort == 'desc') ? 'active' : ''; ?>">
                    Giá giảm dần
                </a>
            </div>

            <div class="category-product-list">

                <?php if ($resultProducts && mysqli_num_rows($resultProducts) > 0) { ?>

                    <?php while ($p = mysqli_fetch_assoc($resultProducts)) {
                        $price = (int)$p['price'];
                        $oldPrice = (int)$p['old_price'];
                        $soldCount = (int)$p['sold_count'];
                        $discount = tinhGiamGia($oldPrice, $price);

                        $isHot = $soldCount > 500;
                        $isNew = isset($p['is_new']) && (int)$p['is_new'] === 1;
                    ?>

                        <div class="category-product-item">

                            <div class="product-img-box">

                               <div class="product-badges">
    <?php if ($isHot) { ?>
        <span class="badge badge-hot">HOT</span>
    <?php } ?>

    <?php if ($isNew) { ?>
        <span class="badge badge-new">NEW</span>
    <?php } ?>
</div>

                                <img src="<?php echo htmlspecialchars($p['image']); ?>"
                                     alt="<?php echo htmlspecialchars($p['name']); ?>">

                            </div>

                            <div class="sold-badge">
                                📊 Đã bán <?php echo $soldCount; ?>
                            </div>

                            <h3><?php echo htmlspecialchars($p['name']); ?></h3>

                            <div class="stars">
                                ★★★★★
                                <span>(<?php echo (int)$p['rating_count']; ?>)</span>
                            </div>

                            <p class="new-price">
                                <?php echo number_format($price, 0, ',', '.'); ?>đ
                            </p>

                            <?php if ($oldPrice > $price) { ?>
                                <div class="price-sale">

                                    <span class="old-price">
                                        <?php echo number_format($oldPrice, 0, ',', '.'); ?>đ
                                    </span>

                                    <?php if ($discount > 0) { ?>
                                        <span class="discount">
                                            -<?php echo $discount; ?>%
                                        </span>
                                    <?php } ?>

                                </div>
                            <?php } ?>

                            <div class="quantity-label">Số lượng:</div>

                            <div class="quantity-box">
                                <button type="button" class="qty-minus">-</button>
                                <input type="text" value="1" class="qty-input" readonly>
                                <button type="button" class="qty-plus">+</button>
                            </div>

                            <div class="product-actions">

                                <button
                                    type="button"
                                    class="add-cart"
                                    data-id="cat_<?php echo (int)$p['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                    data-price="<?php echo $price; ?>"
                                    data-old-price="<?php echo $oldPrice; ?>"
                                    data-img="<?php echo htmlspecialchars($p['image']); ?>"
                                >
                                    🛒 Thêm vào giỏ
                                </button>

                                <button
                                    type="button"
                                    class="buy-now"
                                    data-id="cat_<?php echo (int)$p['id']; ?>"
                                    data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                    data-price="<?php echo $price; ?>"
                                    data-old-price="<?php echo $oldPrice; ?>"
                                    data-img="<?php echo htmlspecialchars($p['image']); ?>"
                                >
                                    Mua ngay
                                </button>

                            </div>

                            <a href="chitietsanpham.php?id=cat_<?php echo (int)$p['id']; ?>" class="detail-link">
                                👁 Xem chi tiết sản phẩm
                            </a>

                        </div>

                    <?php } ?>

                <?php } else { ?>

                    <p style="font-size:20px; font-weight:bold; padding:30px;">
                        Chưa có sản phẩm trong danh mục này.
                    </p>

                <?php } ?>

            </div>

            <?php if ($totalPages > 1) { ?>

                <div class="pagination">

                    <?php if ($page > 1) { ?>
                        <a href="?<?php echo buildQuery(['page' => $page - 1]); ?>">‹</a>
                    <?php } ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++) { ?>

                        <a href="?<?php echo buildQuery(['page' => $i]); ?>"
                           class="<?php echo ($i == $page) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>

                    <?php } ?>

                    <?php if ($page < $totalPages) { ?>
                        <a href="?<?php echo buildQuery(['page' => $page + 1]); ?>">›</a>
                    <?php } ?>

                </div>

            <?php } ?>

        </main>

    </div>

</section>

<?php include "footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {

    document.querySelectorAll(".qty-plus").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const box = this.closest(".quantity-box");
            const input = box.querySelector(".qty-input");
            input.value = parseInt(input.value) + 1;
        });
    });

    document.querySelectorAll(".qty-minus").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const box = this.closest(".quantity-box");
            const input = box.querySelector(".qty-input");
            let qty = parseInt(input.value);

            if (qty > 1) {
                input.value = qty - 1;
            }
        });
    });

    function addToCart(button, redirectToCart = false) {
        const productItem = button.closest(".category-product-item");
        const qtyInput = productItem.querySelector(".qty-input");
        const qty = qtyInput ? qtyInput.value : 1;

        const formData = new FormData();
        formData.append("id", button.dataset.id);
        formData.append("name", button.dataset.name);
        formData.append("price", button.dataset.price);
        formData.append("old_price", button.dataset.oldPrice);
        formData.append("img", button.dataset.img);
        formData.append("qty", qty);

        fetch("add_to_cart.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === "success") {

                const cartCount = document.getElementById("cart-count");
                if (cartCount) {
                    cartCount.innerText = data.cart_count;
                }

                if (redirectToCart) {
                    window.location.href = "giohang.php";
                } else {
                    Swal.fire({
                        icon: "success",
                        title: "Thông báo",
                        text: data.message,
                        confirmButtonText: "OK"
                    });
                }

            } else {
                Swal.fire("Lỗi", data.message, "error");
            }
        })
        .catch(() => {
            Swal.fire("Lỗi", "Không gọi được add_to_cart.php", "error");
        });
    }

    document.querySelectorAll(".add-cart").forEach(function (button) {
        button.addEventListener("click", function () {
            addToCart(this, false);
        });
    });

    document.querySelectorAll(".buy-now").forEach(function (button) {
        button.addEventListener("click", function () {
            addToCart(this, true);
        });
    });

});
</script>

</body>
</html>