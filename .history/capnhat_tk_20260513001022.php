<?php
session_start();
include "connect.php";

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;

$ho = trim($_POST['ho'] ?? '');
$ten = trim($_POST['ten'] ?? '');
$sdt = trim($_POST['sdt'] ?? '');
$ngaysinh = trim($_POST['ngaysinh'] ?? '');
$diachi = trim($_POST['diachi'] ?? '');

if (isset($_POST['full_name']) && $_POST['full_name'] !== '') {
    $fullName = trim($_POST['full_name']);
    $parts = explode(' ', $fullName);
    $ten = array_pop($parts);
    $ho = implode(' ', $parts);
}

$sql = "UPDATE users 
        SET ho = ?, ten = ?, sdt = ?, ngaysinh = ?, diachi = ?
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssi", $ho, $ten, $sdt, $ngaysinh, $diachi, $user_id);

if (mysqli_stmt_execute($stmt)) {
    $_SESSION['user_ho'] = $ho;
    $_SESSION['user_name'] = $ten;
    $_SESSION['user_sdt'] = $sdt;
    $_SESSION['user_ngaysinh'] = $ngaysinh;
    $_SESSION['user_diachi'] = $diachi;
}

header("Location: taikhoan.php");
exit();
?>