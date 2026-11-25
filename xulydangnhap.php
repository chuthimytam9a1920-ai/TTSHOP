<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/validate_helpers.php';

$email = strtolower(trim($_POST['email'] ?? ''));
$matkhau = (string)($_POST['matkhau'] ?? '');

$loginSql = 'SELECT id, ho, ten, email, sdt, matkhau, ngaysinh, diachi
    FROM users WHERE email = ? LIMIT 1';

if (!validate_personal_email($email) || $matkhau === '') {
    $_SESSION['login_error'] = validation_message('login_credentials');
    header('Location: dangnhap.php');
    exit();
}

try {
    $statement = $conn->prepare($loginSql);
    $statement->bind_param('s', $email);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($user && password_verify($matkhau, $user['matkhau'])) {
        session_regenerate_id(true);
        $_SESSION['is_login'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_ho'] = $user['ho'];
        $_SESSION['user_name'] = $user['ten'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_sdt'] = $user['sdt'];
        $_SESSION['user_ngaysinh'] = $user['ngaysinh'];
        $_SESSION['user_diachi'] = $user['diachi'];

        header('Location: taikhoan.php');
        exit();
    }
} catch (Throwable $exception) {
    error_log(sprintf(
        '%s:%d: %s | mysqli_error=%s | SQL=%s',
        __FILE__,
        __LINE__,
        $exception->getMessage(),
        mysqli_error($conn),
        $loginSql
    ));
    $_SESSION['login_error'] = validation_message('system');
    header('Location: dangnhap.php');
    exit();
}

$_SESSION['login_error'] = validation_message('login_credentials');
header('Location: dangnhap.php');
exit();
