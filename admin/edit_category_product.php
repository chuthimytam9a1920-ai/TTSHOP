<?php
include "check_admin.php";
include "../connect.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$categories = [
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

$stmt = mysqli_prepare($conn, "SELECT * FROM category_products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    header("Location: category_products.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type_slug = $_POST['type_slug'];
    $price = (int)$_POST['price'];
    $old_price = (int)$_POST['old_price'];
    $sold_count = (int)$_POST['sold_count'];
    $rating_count = (int)$_POST['rating_count'];
    $is_new = isset($_POST['is_new']) ? 1 : 0;

    $imagePath = $product['image'];

    if (!empty($_FILES['image']['name'])) {
        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $target = "../imagescategory/" . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $imagePath = "imagescategory/" . $fileName;
        }
    }

    $sql = "UPDATE category_products 
            SET name = ?, image = ?, price = ?, old_price = ?, sold_count = ?, 
                rating_count = ?, type_slug = ?, is_new = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        "ssiiiisii",
        $name,
        $imagePath,
        $price,
        $old_price,
        $sold_count,
        $rating_count,
        $type_slug,
        $is_new,
        $id
    );

    mysqli_stmt_execute($stmt);

    header("Location: category_products.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa sản phẩm danh mục</title>
    <style>
        body{margin:0;font-family:Arial;background:#f4f6f8}
        .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
        .main{padding:30px}
        .box{background:white;border-radius:14px;padding:25px;box-shadow:0 4px 14px rgba(0,0,0,.08);max-width:800px}
        label{display:block;font-weight:bold;margin-top:15px}
        input,select{width:100%;padding:12px;margin-top:7px;border:1px solid #ddd;border-radius:8px}
        img{width:120px;height:120px;object-fit:contain;margin-top:10px}
        .check{width:auto}
        .btn{margin-top:20px;background:#15985d;color:white;border:0;padding:13px 22px;border-radius:8px;font-weight:bold;cursor:pointer}
        .back{display:inline-block;margin-top:15px;color:#0b72e7;text-decoration:none;font-weight:bold}
body{
    margin:0;
    font-family:Arial;
    background:#f4f6f8;
}

.layout{
    display:grid;
    grid-template-columns:260px 1fr;
    min-height:100vh;
}

.sidebar{
    background:#15985d;
    color:white;
    padding:25px 20px;
}

.sidebar h2{
    font-size:30px;
    margin-bottom:35px;
}

.sidebar a{
    display:block;
    color:white;
    text-decoration:none;
    padding:14px 12px;
    border-radius:8px;
    margin-bottom:8px;
    font-weight:bold;
}

.sidebar a:hover,
.sidebar a.active{
    background:rgba(255,255,255,.18);
}

.main{
    padding:30px;
}

.box{
    max-width:820px;
    background:white;
    padding:30px;
    border-radius:14px;
    box-shadow:0 4px 14px rgba(0,0,0,.08);
}

label{
    display:block;
    font-weight:bold;
    margin-top:15px;
}

input,
select{
    width:100%;
    height:45px;
    margin-top:8px;
    padding:0 12px;
    box-sizing:border-box;
    border:1px solid #ddd;
    border-radius:8px;
}

.check{
    width:auto;
    height:auto;
    margin-right:6px;
}

button,
.back{
    display:inline-block;
    background:#15985d;
    color:white;
    border:none;
    padding:13px 20px;
    border-radius:8px;
    font-weight:bold;
    text-decoration:none;
    cursor:pointer;
    margin-top:20px;
}

.back{
    background:#777;
    margin-left:8px;
}
    </style>
</head>
<body>

<div class="layout">
<?php
$page = "category_products";
include "sidebar.php";
?>

<main class="main">
    <div class="box">
        <h1>Sửa sản phẩm danh mục</h1>

        <form method="POST" enctype="multipart/form-data">
            <label>Tên sản phẩm</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>

            <label>Ảnh hiện tại</label>
            <img src="../<?php echo htmlspecialchars($product['image']); ?>">

            <label>Đổi ảnh mới</label>
            <input type="file" name="image" accept="image/*">

            <label>Danh mục</label>
            <select name="type_slug" required>
                <?php foreach ($categories as $slug => $label) { ?>
                    <option value="<?php echo $slug; ?>" <?php echo ($product['type_slug'] == $slug) ? 'selected' : ''; ?>>
                        <?php echo $label; ?>
                    </option>
                <?php } ?>
            </select>

            <label>Giá mới</label>
            <input type="number" name="price" value="<?php echo (int)$product['price']; ?>" required>

            <label>Giá cũ</label>
            <input type="number" name="old_price" value="<?php echo (int)$product['old_price']; ?>">

            <label>Đã bán</label>
            <input type="number" name="sold_count" value="<?php echo (int)$product['sold_count']; ?>">

            <label>Số lượt đánh giá</label>
            <input type="number" name="rating_count" value="<?php echo (int)$product['rating_count']; ?>">

            <label>
                <input class="check" type="checkbox" name="is_new" value="1"
                    <?php echo ((int)$product['is_new'] === 1) ? 'checked' : ''; ?>>
                Hiển thị NEW
            </label>

            <button class="btn" type="submit">Cập nhật sản phẩm</button>
        </form>

        <a class="back" href="category_products.php">← Quay lại</a>
    </div>
</main>
</div>

</body>
</html>