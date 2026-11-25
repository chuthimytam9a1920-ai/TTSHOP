<?php
include "check_admin.php";
include "../connect.php";

$keyword = trim($_GET['keyword'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$categoryList = [
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

$where = [];

if ($keyword !== '') {
    $safeKeyword = mysqli_real_escape_string($conn, $keyword);

    if (is_numeric($keyword)) {
        $where[] = "(id = " . (int)$keyword . " OR name LIKE '%$safeKeyword%')";
    } else {
        $where[] = "name LIKE '%$safeKeyword%'";
    }
}

if ($filter === 'hot') {
    $where[] = "sold_count > 500";
} elseif ($filter === 'new') {
    $where[] = "is_new = 1";
} elseif ($filter !== 'all') {
    $safeFilter = mysqli_real_escape_string($conn, $filter);
    $where[] = "type_slug = '$safeFilter'";
}

$whereSql = "";
if (!empty($where)) {
    $whereSql = "WHERE " . implode(" AND ", $where);
}

$result = mysqli_query($conn, "SELECT * FROM category_products $whereSql ORDER BY id DESC");

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
    <title>Quản lý sản phẩm danh mục</title>

    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Arial;background:#f4f6f8}
        .layout{display:grid;grid-template-columns:260px minmax(0,1fr);min-height:100vh}
        .sidebar{background:#15985d;color:white;padding:25px 20px}
        .sidebar h2{font-size:28px;margin-bottom:35px}
        .sidebar a{display:block;color:white;text-decoration:none;padding:14px 12px;border-radius:8px;margin-bottom:8px;font-weight:bold}
        .sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.18)}
        .main{padding:30px;min-width:0}
        .topbar,.box{background:white;border-radius:14px;padding:22px;box-shadow:0 4px 14px rgba(0,0,0,.08)}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}
        .btn{background:#15985d;color:white;text-decoration:none;padding:12px 18px;border-radius:8px;font-weight:bold}
        .filter-bar{display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
        .filter-bar select,.filter-bar input{height:42px;border:1px solid #ddd;border-radius:8px;padding:0 12px}
        .filter-bar input{width:360px}
        .filter-bar button,.filter-bar a{height:42px;display:inline-flex;align-items:center;justify-content:center;padding:0 16px;border-radius:8px;border:0;font-weight:bold;text-decoration:none;cursor:pointer}
        .filter-bar button{background:#15985d;color:white}
        .filter-bar a{background:#777;color:white}
        .table-wrap{width:100%;overflow-x:auto}
        table{width:100%;min-width:1200px;border-collapse:collapse}
        th,td{padding:13px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}
        th{background:#f0f0f0}
        img{width:70px;height:70px;object-fit:contain}
        .edit{color:#0b72e7;font-weight:bold;text-decoration:none}
        .delete{color:red;font-weight:bold;text-decoration:none}
        .tag{display:inline-block;padding:6px 10px;border-radius:20px;font-size:13px;font-weight:bold;white-space:nowrap}
        .tag-hot{background:#ffe1e1;color:#d60000}
        .tag-new{background:#dff7ff;color:#0072a3}
        .tag-sale{background:#fff0c7;color:#b66a00}
        .tag-no{background:#eee;color:#777}
    </style>
</head>

<body>

<div class="layout">

<?php
$page = "category_products";
include "sidebar.php";
?>

    <main class="main">
        <div class="topbar">
            <h1>Sản phẩm danh mục</h1>
            <a href="add_category_product.php" class="btn">+ Thêm sản phẩm</a>
        </div>

        <div class="box">

            <form method="GET" class="filter-bar">
                <select name="filter">
                    <option value="all" <?php echo ($filter == 'all') ? 'selected' : ''; ?>>
                        Tất cả
                    </option>

                    <?php foreach ($categoryList as $slug => $name) { ?>
                        <option value="<?php echo htmlspecialchars($slug); ?>"
                            <?php echo ($filter == $slug) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php } ?>

                    <option value="hot" <?php echo ($filter == 'hot') ? 'selected' : ''; ?>>
                        HOT
                    </option>

                    <option value="new" <?php echo ($filter == 'new') ? 'selected' : ''; ?>>
                        NEW
                    </option>
                </select>

                <input
                    type="text"
                    name="keyword"
                    placeholder="Tìm theo ID hoặc tên sản phẩm..."
                    value="<?php echo htmlspecialchars($keyword); ?>"
                >

                <button type="submit">Tìm kiếm</button>
                <a href="category_products.php">Làm mới</a>
            </form>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>Tên sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Giá</th>
                            <th>Giá cũ</th>
                            <th>Giảm giá</th>
                            <th>Đã bán</th>
                            <th>HOT</th>
                            <th>NEW</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0) { ?>

                            <?php while ($row = mysqli_fetch_assoc($result)) {
                                $price = (int)$row['price'];
                                $oldPrice = (int)$row['old_price'];
                                $soldCount = (int)$row['sold_count'];
                                $discount = tinhGiamGia($oldPrice, $price);

                                $isHot = $soldCount > 500;
                                $isNew = isset($row['is_new']) && (int)$row['is_new'] === 1;
                            ?>

                                <tr>
                                    <td><?php echo (int)$row['id']; ?></td>

                                    <td>
                                        <img src="../<?php echo htmlspecialchars($row['image']); ?>">
                                    </td>

                                    <td><?php echo htmlspecialchars($row['name']); ?></td>

                                    <td><?php echo htmlspecialchars($row['type_slug']); ?></td>

                                    <td><?php echo number_format($price, 0, ',', '.'); ?>đ</td>

                                    <td>
                                        <?php echo ($oldPrice > 0) ? number_format($oldPrice, 0, ',', '.') . 'đ' : '-'; ?>
                                    </td>

                                    <td>
                                        <?php if ($discount > 0) { ?>
                                            <span class="tag tag-sale">-<?php echo $discount; ?>%</span>
                                        <?php } else { ?>
                                            <span class="tag tag-no">Không</span>
                                        <?php } ?>
                                    </td>

                                    <td><?php echo $soldCount; ?></td>

                                    <td>
                                        <?php if ($isHot) { ?>
                                            <span class="tag tag-hot">HOT</span>
                                        <?php } else { ?>
                                            <span class="tag tag-no">Không</span>
                                        <?php } ?>
                                    </td>

                                    <td>
                                        <?php if ($isNew) { ?>
                                            <span class="tag tag-new">NEW</span>
                                        <?php } else { ?>
                                            <span class="tag tag-no">Không</span>
                                        <?php } ?>
                                    </td>

                                    <td>
                                        <a class="edit" href="edit_category_product.php?id=<?php echo (int)$row['id']; ?>">Sửa</a>
                                        |
                                        <a class="delete" href="delete_category_product.php?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Xóa sản phẩm này?')">Xóa</a>
                                    </td>
                                </tr>

                            <?php } ?>

                        <?php } else { ?>

                            <tr>
                                <td colspan="11" style="text-align:center;">Không tìm thấy sản phẩm phù hợp</td>
                            </tr>

                        <?php } ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>

</div>

</body>
</html>