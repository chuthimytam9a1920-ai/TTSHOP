<?php
// Bắt đầu hoặc khôi phục một phiên làm việc (session) để lưu thông tin đăng nhập
session_start();

// Kết nối tới cơ sở dữ liệu
include "connect.php";

// Lấy dữ liệu Email (loại bỏ khoảng trắng thừa) và Mật khẩu gửi lên từ Form đăng nhập
$email = trim($_POST['email'] ?? '');
$matkhau = $_POST['matkhau'] ?? '';

// Chuẩn bị câu lệnh SQL tìm kiếm tài khoản dựa vào email (sử dụng Prepared Statement chống SQL Injection)
$sql = "SELECT * FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);

// Lấy kết quả trả về từ database
$result = mysqli_stmt_get_result($stmt);

// Nếu tìm thấy một tài khoản trùng khớp với email đã nhập
if ($user = mysqli_fetch_assoc($result)) {
    // Kiểm tra tính hợp lệ của mật khẩu vừa nhập so với mật khẩu đã mã hóa (hash) lưu trong database
    if (password_verify($matkhau, $user['matkhau'])) {
        // Mật khẩu đúng: Lưu trạng thái đăng nhập và toàn bộ thông tin cá nhân của user vào $_SESSION
        $_SESSION['is_login'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_ho'] = $user['ho'];
        $_SESSION['user_name'] = $user['ten'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_sdt'] = $user['sdt'];
        $_SESSION['user_ngaysinh'] = $user['ngaysinh'];
        $_SESSION['user_diachi'] = $user['diachi'];

        // Chuyển hướng người dùng thẳng tiến tới trang quản lý thông tin tài khoản cá nhân
        header("Location: taikhoan.php");
        exit();
    }
}

// Nếu email không tồn tại hoặc mật khẩu bị sai: Tạo một thông báo lỗi lưu vào session
$_SESSION['login_error'] = "Email hoặc mật khẩu không đúng!";

// Chuyển hướng người dùng quay trở lại trang đăng nhập để thông báo lỗi và yêu cầu nhập lại
header("Location: dangnhap.php");
exit();
?>