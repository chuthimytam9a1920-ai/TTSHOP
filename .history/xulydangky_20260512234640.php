<?php
session_start();
include "connect.php";

if (!isset($_POST['btn_register'])) {
    header("Location: dangky.php");
    exit();
}

$ho = trim($_POST['ho'] ?? '');
$ten = trim($_POST['ten'] ?? '');
$email = trim($_POST['register_email'] ?? '');
$sdt = trim($_POST['sdt'] ?? '');
$matkhau = $_POST['register_password'] ?? '';

if ($ho === '' || $ten === '' || $email === '' || $sdt === '' || $matkhau === '') {
    $_SESSION['error_message'] = "Vui lòng nhập đầy đủ thông tin!";
    header("Location: dangky.php");
    exit();
}

$check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
mysqli_stmt_bind_param($check, "s", $email);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

if (mysqli_stmt_num_rows($check) > 0) {
    $_SESSION['error_message'] = "Email này đã được đăng ký!";
    header("Location: dangky.php");
    exit();
}

$password_hash = password_hash($matkhau, PASSWORD_DEFAULT);

$sql = "INSERT INTO users (ho, ten, email, sdt, matkhau) VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssss", $ho, $ten, $email, $sdt, $password_hash);

if (mysqli_stmt_execute($stmt)) {
    $user_id = mysqli_insert_id($conn);

    $_SESSION['is_login'] = true;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_email'] = $email;

    header("Location: taikhoan.php");
    exit();
}

$_SESSION['error_message'] = "Đăng ký thất bại!";
header("Location: dangky.php");
exit();