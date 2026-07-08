<?php
session_start();
include "connect.php";

$email = trim($_POST['email'] ?? '');
$matkhau = $_POST['matkhau'] ?? '';

$sql = "SELECT * FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if ($user = mysqli_fetch_assoc($result)) {
    if (password_verify($matkhau, $user['matkhau'])) {
        $_SESSION['is_login'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_ho'] = $user['ho'];
        $_SESSION['user_name'] = $user['ten'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_sdt'] = $user['sdt'];
        $_SESSION['user_ngaysinh'] = $user['ngaysinh'];
        $_SESSION['user_diachi'] = $user['diachi'];

        header("Location: taikhoan.php");
        exit();
    }
}

$_SESSION['login_error'] = "Email hoặc mật khẩu không đúng!";
header("Location: dangnhap.php");
exit();