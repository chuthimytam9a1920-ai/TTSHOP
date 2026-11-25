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
$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    header("Location: taikhoan.php?tab=donhang");
    exit();
}

/* Chỉ cho huỷ đơn của chính user đang đăng nhập
   và chỉ huỷ khi trạng thái còn Chờ xử lý hoặc Chờ lấy hàng */
$sql = "
    UPDATE donhang
    SET trang_thai = 'Đã huỷ'
    WHERE id = ?
    AND user_id = ?
    AND trang_thai IN ('Chờ xử lý', 'Chờ lấy hàng')
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmt);

header("Location: taikhoan.php?tab=donhang&cancel=success");
exit();