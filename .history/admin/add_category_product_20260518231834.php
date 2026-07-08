<?php
include "check_admin.php";
include "../connect.php";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type_slug = $_POST['type_slug'] ?? '';
    $price = (int)($_POST['price'] ?? 0);
    $old_price = (int)($_POST['old_price'] ?? 0);
    $sold_count = (int)($_POST['sold_count'] ?? 0);
    $rating_count = (int)($_POST['rating_count'] ?? 0);
    $mo_ta_chi_tiet = trim($_POST['mo_ta_chi_tiet'] ?? '');
    $is_new = isset($_POST['is_new']) ? 1 : 0;

    $imagePath = "";

    if (!empty($_FILES['image']['name'])) {
        $fileName = time() . "_" . basename($_FILES['image']['name']);
        $target = "../imagescategory/" . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $imagePath = "imagescategory/" . $fileName;
        }
    }

    $sql = "INSERT INTO category_products 
            (name, image, price, old_price, sold_count, rating_count, type_slug, is_new, mo_ta_chi_tiet)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssiiiisis",
        $name,
        $imagePath,
        $price,
        $old_price,
        $sold_count,
        $rating_count,
        $type_slug,
        $is_new,
        $mo_ta_chi_tiet
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
    <title>Thêm sản phẩm danh mục</title>

    <style>
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
        select,
        textarea{
            width:100%;
            margin-top:8px;
            padding:12px;
            box-sizing:border-box;
            border:1px solid #ddd;
            border-radius:8px;
            font-family:Arial;
            font-size:15px;
        }

        input,
        select{
            height:45px;
        }

        textarea{
            min-height:130px;
            resize:vertical;
            line-height:1.5;
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
        .layout{
    display:grid;
    grid-template-columns:260px 1fr;
    min-height:100vh;
}

.sidebar{
    background:#15985d;
    color:white;
    padding:35px 22px;
}

.sidebar h2{
    font-size:32px;
    line-height:1.25;
    margin-bottom:40px;
}

.sidebar a{
    display:block;
    color:white;
    text-decoration:none;
    font-weight:bold;
    font-size:18px;
    padding:16px 14px;
    border-radius:8px;
    margin-bottom:8px;
}

.sidebar a:hover,
.sidebar a.active{
    background:rgba(255,255,255,.18);
}
    </style>
</head>

<body>

<div class="layout">
<aside class="sidebar">
    <h2>TT SHOP<br>ADMIN</h2>

    <a href="index.php">Dashboard</a>
    <a href="products.php">Sản phẩm trang chủ</a>
    <a class="active" href="category_products.php">Sản phẩm danh mục</a>
    <a href="orders.php">Quản lý đơn hàng</a>
    <a href="customers.php">Quản lý khách hàng</a>
    <a href="chat_admin.php">Chat khách hàng</a>
    <a href="../index.php">Về trang chủ</a>
</aside>
<main class="main">

    <div class="box">

        <h1>Thêm sản phẩm danh mục</h1>

        <form method="POST" enctype="multipart/form-data">

            <label>Tên sản phẩm</label>
            <input type="text" name="name" required>

            <label>Ảnh sản phẩm</label>
            <input type="file" name="image" accept="image/*" required>

            <label>Danh mục</label>
            <select name="type_slug" required>
                <?php foreach ($categories as $slug => $label) { ?>
                    <option value="<?php echo $slug; ?>">
                        <?php echo $label; ?>
                    </option>
                <?php } ?>
            </select>

            <label>Giá mới</label>
            <input type="number" name="price" required>

            <label>Giá cũ</label>
            <input type="number" name="old_price" value="0">

            <label>Đã bán</label>
            <input type="number" name="sold_count" value="0">

            <label>Số lượt đánh giá</label>
            <input type="number" name="rating_count" value="0">

            <label>Mô tả chi tiết</label>
            <textarea name="mo_ta_chi_tiet" placeholder="Nhập mô tả chi tiết sản phẩm..."></textarea>

            <label>
                <input class="check" type="checkbox" name="is_new" value="1">
                Hiển thị NEW
            </label>

            <button type="submit">Thêm sản phẩm</button>
            <a class="back" href="category_products.php">← Quay lại</a>

        </form>

    </div>

</main>

</div>

</body>
</html>