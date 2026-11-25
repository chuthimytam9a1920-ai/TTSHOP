<?php
include "check_admin.php";
include "../connect.php";

$id = (int)($_POST['id'] ?? 0);
$trang_thai = $_POST['trang_thai'] ?? 'Chờ xử lý';

$allowed = [
    'Chờ xử lý',
    'Chờ lấy hàng',
    'Đang vận chuyển',
    'Đã giao',
    'Đã huỷ'
];

if ($id > 0 && in_array($trang_thai, $allowed)) {
    $stmt = mysqli_prepare($conn, "UPDATE donhang SET trang_thai = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $trang_thai, $id);
    mysqli_stmt_execute($stmt);
}

header("Location: orders.php");
exit();
?>