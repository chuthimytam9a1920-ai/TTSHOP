<?php
include "check_admin.php";
include "../connect.php";

$id = (int)($_GET['id'] ?? 0);

$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    die("Không tìm thấy sản phẩm.");
}

$error = "";

$cats = [
    'top-sale' => 'TOP SALE',
    'vpp' => 'Văn phòng phẩm',
    'vo-ghi' => 'Vở ghi - sổ tay',
    'but-viet' => 'Bút viết',
    'dung-cu' => 'Dụng cụ học tập',
    'balo' => 'Ba lô - Cặp sách',
    'may-tinh' => 'Máy tính cầm tay'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $price = (int)($_POST['price'] ?? 0);
    $old_price = (int)($_POST['old_price'] ?? 0);
    $sold_count = (int)($_POST['sold_count'] ?? 0);
    $star_count = (int)($_POST['star_count'] ?? 5);
    $review_count = (int)($_POST['review_count'] ?? 0);
    $mo_ta_chi_tiet = trim($_POST['mo_ta_chi_tiet'] ?? '');

    $is_flash_sale = isset($_POST['is_flash_sale']) ? 1 : 0;
    $is_new = isset($_POST['is_new']) ? 1 : 0;

    $imageName = $product['image'];

    if (!empty($_FILES['image']['name'])) {
        $imageName = time() . "_" . basename($_FILES['image']['name']);
        $target = "../images/" . $imageName;

        if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $error = "Lỗi tải ảnh lên.";
        }
    }

    if ($error === "") {
        $sql = "UPDATE products SET 
                    name = ?, 
                    category_id = ?, 
                    price = ?, 
                    old_price = ?, 
                    image = ?, 
                    sold_count = ?, 
                    star_count = ?, 
                    review_count = ?, 
                    is_flash_sale = ?, 
                    is_new = ?,
                    mo_ta_chi_tiet = ?
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ssiisiiiiisi",
            $name,
            $category_id,
            $price,
            $old_price,
            $imageName,
            $sold_count,
            $star_count,
            $review_count,
            $is_flash_sale,
            $is_new,
            $mo_ta_chi_tiet,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {
            header("Location: products.php");
            exit();
        } else {
            $error = "Lỗi cập nhật sản phẩm: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Sửa sản phẩm trang chủ</title>

    <style>
        *{box-sizing:border-box}

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
            max-width:850px;
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
        select,
        textarea{
            width:100%;
            margin-top:8px;
            padding:12px;
            box-sizing:border-box;
            border:1px solid #ddd;
            border-radius:8px;
            font-size:15px;
            font-family:Arial;
        }

        input,
        select{
            height:45px;
        }

        textarea{
            min-height:150px;
            resize:vertical;
            line-height:1.6;
        }

        img{
            width:120px;
            height:120px;
            object-fit:contain;
            margin-top:10px;
            border:1px solid #eee;
            border-radius:8px;
            background:white;
        }

        .check{
            width:auto;
            height:auto;
            margin-right:6px;
        }

        .note{
            background:#e8f7ef;
            color:#087a42;
            padding:12px 14px;
            border-radius:8px;
            font-weight:bold;
            margin-bottom:15px;
        }

        .error{
            background:#ffe0e0;
            color:red;
            padding:12px 14px;
            border-radius:8px;
            font-weight:bold;
            margin-bottom:15px;
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
$page = "products";
include "sidebar.php";
?>

    <main class="main">
        <div class="box">
            <h1>Sửa sản phẩm trang chủ</h1>

            <?php if ($error) { ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php } ?>

            <div class="note">
                HOT sẽ tự hiện khi Đã bán &gt; 500. Giảm giá tự tính từ Giá cũ và Giá mới.
            </div>

            <form method="POST" enctype="multipart/form-data">

                <label>Tên sản phẩm</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>" required>

                <label>Danh mục</label>
                <select name="category_id" required>
                    <?php foreach ($cats as $key => $label) { ?>
                        <option value="<?php echo $key; ?>" <?php echo (($product['category_id'] ?? '') == $key) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php } ?>
                </select>

                <label>Giá mới</label>
                <input type="number" name="price" value="<?php echo (int)($product['price'] ?? 0); ?>" required>

                <label>Giá cũ</label>
                <input type="number" name="old_price" value="<?php echo (int)($product['old_price'] ?? 0); ?>">

                <label>Mô tả chi tiết sản phẩm</label>
                <textarea name="mo_ta_chi_tiet" placeholder="Nhập thông tin chi tiết sản phẩm..."><?php echo htmlspecialchars($product['mo_ta_chi_tiet'] ?? ''); ?></textarea>

                <label>Ảnh hiện tại</label>
                <img src="../images/<?php echo htmlspecialchars($product['image'] ?? ''); ?>">

                <label>Đổi ảnh mới</label>
                <input type="file" name="image" accept="image/*">

                <label>Đã bán</label>
                <input type="number" name="sold_count" value="<?php echo (int)($product['sold_count'] ?? 0); ?>">

                <label>Số sao</label>
                <input type="number" name="star_count" value="<?php echo (int)($product['star_count'] ?? 5); ?>" min="1" max="5">

                <label>Số đánh giá</label>
                <input type="number" name="review_count" value="<?php echo (int)($product['review_count'] ?? 0); ?>">

                <label>
                    <input class="check" type="checkbox" name="is_new" value="1"
                        <?php echo ((int)($product['is_new'] ?? 0) === 1) ? 'checked' : ''; ?>>
                    Sản phẩm mới / NEW
                </label>

                <label>
                    <input class="check" type="checkbox" name="is_flash_sale" value="1"
                        <?php echo ((int)($product['is_flash_sale'] ?? 0) === 1) ? 'checked' : ''; ?>>
                    Flash sale
                </label>

                <button type="submit">Cập nhật</button>
                <a href="products.php" class="back">Quay lại</a>

            </form>
        </div>
    </main>

</div>

</body>
</html>