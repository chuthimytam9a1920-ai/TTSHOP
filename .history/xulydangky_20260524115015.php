<?php
// Khởi chạy phiên làm việc (session) để lưu trữ thông báo lỗi hoặc trạng thái đăng nhập
session_start();

// Kết nối tới cơ sở dữ liệu
include "connect.php";

// Kiểm tra bảo mật: Nếu không phải nhấn từ nút đăng ký (btn_register) gửi lên thì đá về trang đăng ký
if (!isset($_POST['btn_register'])) {
    header("Location: dangky.php");
    exit();
}

// Lấy dữ liệu từ form và loại bỏ các khoảng trắng thừa ở 2 đầu chuỗi
$ho = trim($_POST['ho'] ?? '');
$ten = trim($_POST['ten'] ?? '');
$email = trim($_POST['register_email'] ?? '');
$sdt = trim($_POST['sdt'] ?? '');
$matkhau = $_POST['register_password'] ?? '';

// Kiểm tra tính hợp lệ: Nếu bỏ trống bất kỳ trường thông tin nào thì báo lỗi và quay lại trang đăng ký
if ($ho === '' || $ten === '' || $email === '' || $sdt === '' || $matkhau === '') {
    $_SESSION['error_message'] = "Vui lòng nhập đầy đủ thông tin!";
    header("Location: dangky.php");
    exit();
}

// Chuẩn bị câu lệnh SQL kiểm tra trùng lặp: Xem Email này đã có ai đăng ký trước đó chưa
$check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
mysqli_stmt_bind_param($check, "s", $email);
mysqli_stmt_execute($check);
mysqli_stmt_store_result($check);

// Nếu số dòng trả về lớn hơn 0 (tức là email đã tồn tại), báo lỗi và dừng quá trình đăng ký
if (mysqli_stmt_num_rows($check) > 0) {
    $_SESSION['error_message'] = "Email này đã được đăng ký!";
    header("Location: dangky.php");
    exit();
}

// Mã hóa mật khẩu bằng thuật toán mã hóa một chiều an toàn để lưu trữ vào database
$password_hash = password_hash($matkhau, PASSWORD_DEFAULT);

// Chuẩn bị câu lệnh SQL để chèn (thêm) thông tin thành viên mới vào bảng users
$sql = "INSERT INTO users (ho, ten, email, sdt, matkhau) VALUES (?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssss", $ho, $ten, $email, $sdt, $password_hash);

// Thực thi câu lệnh chèn dữ liệu
if (mysqli_stmt_execute($stmt)) {
    // Đăng ký thành công: Lấy ID tự động tăng (Primary Key) vừa được tạo ra của user này
    $user_id = mysqli_insert_id($conn);

    // Tự động đăng nhập luôn cho người dùng bằng cách lưu thông tin cơ bản vào $_SESSION
    $_SESSION['is_login'] = true;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_email'] = $email;

    // Chuyển hướng người dùng thẳng tiến tới trang quản lý thông tin tài khoản
    header("Location: taikhoan.php");
    exit();
}

// Phòng trường hợp lỗi hệ thống không chèn được dữ liệu: Báo lỗi thất bại
$_SESSION['error_message'] = "Đăng ký thất bại!";
header("Location: dangky.php");
exit();
?>