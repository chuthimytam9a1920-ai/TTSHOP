<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "connect.php";

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$donhang_id = (int)($_POST['donhang_id'] ?? 0);
$so_sao = (int)($_POST['so_sao'] ?? 0);
$nhan_xet = trim($_POST['nhan_xet'] ?? '');

if ($donhang_id <= 0 || $so_sao < 1 || $so_sao > 5) {
    die("Dữ liệu đánh giá không hợp lệ.");
}

$sqlCheckOrder = "
    SELECT *
    FROM donhang
    WHERE id = ?
    AND user_id = ?
    AND trang_thai = 'Đã giao'
";

$stmtCheckOrder = mysqli_prepare($conn, $sqlCheckOrder);
mysqli_stmt_bind_param($stmtCheckOrder, "ii", $donhang_id, $user_id);
mysqli_stmt_execute($stmtCheckOrder);

$resultOrder = mysqli_stmt_get_result($stmtCheckOrder);
$order = mysqli_fetch_assoc($resultOrder);

if (!$order) {
    die("Bạn chỉ có thể đánh giá đơn hàng đã giao.");
}

$sqlCheckReview = "
    SELECT id
    FROM danhgia
    WHERE donhang_id = ?
    AND user_id = ?
    LIMIT 1
";

$stmtCheckReview = mysqli_prepare($conn, $sqlCheckReview);
mysqli_stmt_bind_param($stmtCheckReview, "ii", $donhang_id, $user_id);
mysqli_stmt_execute($stmtCheckReview);

$resultReview = mysqli_stmt_get_result($stmtCheckReview);

if (mysqli_num_rows($resultReview) > 0) {
    die("Bạn đã đánh giá đơn hàng này rồi.");
}

$sqlItems = "
    SELECT *
    FROM chitiet_donhang
    WHERE donhang_id = ?
";

$stmtItems = mysqli_prepare($conn, $sqlItems);
mysqli_stmt_bind_param($stmtItems, "i", $donhang_id);
mysqli_stmt_execute($stmtItems);

$resultItems = mysqli_stmt_get_result($stmtItems);

while ($item = mysqli_fetch_assoc($resultItems)) {
    $ten_san_pham = $item['ten_san_pham'];
    $hinh_anh = $item['hinh_anh'];

    $sqlInsert = "
        INSERT INTO danhgia (
            donhang_id,
            user_id,
            ten_san_pham,
            hinh_anh,
            so_sao,
            nhan_xet
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ";

    $stmtInsert = mysqli_prepare($conn, $sqlInsert);
    mysqli_stmt_bind_param(
        $stmtInsert,
        "iissis",
        $donhang_id,
        $user_id,
        $ten_san_pham,
        $hinh_anh,
        $so_sao,
        $nhan_xet
    );

    mysqli_stmt_execute($stmtInsert);
}

header("Location: chitietdonhang.php?id=" . $donhang_id . "&review=success");
exit();
?>