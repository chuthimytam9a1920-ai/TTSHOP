<?php
session_start();
include "connect.php";

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$ho_ten = trim($_POST['ho_ten'] ?? '');
$sdt = trim($_POST['sdt'] ?? '');
$cong_ty = trim($_POST['cong_ty'] ?? '');
$dia_chi = trim($_POST['dia_chi'] ?? '');
$quoc_gia = trim($_POST['quoc_gia'] ?? 'Vietnam');
$ma_zip = trim($_POST['ma_zip'] ?? '');
$mac_dinh = isset($_POST['mac_dinh']) ? 1 : 0;

if ($mac_dinh == 1) {
    $sqlReset = "UPDATE diachi_khachhang SET mac_dinh = 0 WHERE user_id = ?";
    $stmtReset = mysqli_prepare($conn, $sqlReset);
    mysqli_stmt_bind_param($stmtReset, "i", $user_id);
    mysqli_stmt_execute($stmtReset);
}

$sql = "INSERT INTO diachi_khachhang 
(user_id, ho_ten, so_dien_thoai, cong_ty, dia_chi, quoc_gia, ma_zip, mac_dinh)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "issssssi", $user_id, $ho_ten, $sdt, $cong_ty, $dia_chi, $quoc_gia, $ma_zip, $mac_dinh);
mysqli_stmt_execute($stmt);

header("Location: taikhoan.php");
exit();
?>