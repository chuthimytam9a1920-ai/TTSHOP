<?php
// Khởi chạy hoặc khôi phục phiên làm việc (session) để kiểm tra trạng thái người dùng
session_start();

// Kết nối tới cơ sở dữ liệu
include "connect.php";

// Kiểm tra bảo mật: Nếu chưa đăng nhập, lập tức chặn lại và đá về trang đăng nhập
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

// Lấy ID của người dùng đang đăng nhập (mặc định bằng 0 nếu không tìm thấy)
$user_id = $_SESSION['user_id'] ?? 0;

// Nhận các thông tin chỉnh sửa mới gửi lên từ form và cắt bỏ khoảng trắng thừa ở 2 đầu
$ho = trim($_POST['ho'] ?? '');
$ten = trim($_POST['ten'] ?? '');
$sdt = trim($_POST['sdt'] ?? '');
$ngaysinh = trim($_POST['ngaysinh'] ?? '');
$diachi = trim($_POST['diachi'] ?? '');

// XỬ LÝ ĐẶC BIỆT: Nếu form gửi lên ô nhập "Họ và tên" chung (full_name) thay vì tách riêng họ/tên
if (isset($_POST['full_name']) && $_POST['full_name'] !== '') {
    $fullName = trim($_POST['full_name']);
    $parts = explode(' ', $fullName); // Cắt chuỗi họ tên thành một mảng các từ dựa vào khoảng trắng
    $ten = array_pop($parts);         // Lấy từ cuối cùng trong mảng ra làm Tên
    $ho = implode(' ', $parts);       // Nối các từ còn lại trong mảng lại với nhau làm Họ
}

// Chuẩn bị câu lệnh SQL cập nhật dữ liệu vào bảng users dựa trên ID tài khoản
$sql = "UPDATE users 
        SET ho = ?, ten = ?, sdt = ?, ngaysinh = ?, diachi = ?
        WHERE id = ?";

// Thực thi câu lệnh SQL bằng Prepared Statement để đảm bảo an toàn, chống hack SQL Injection
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sssssi", $ho, $ten, $sdt, $ngaysinh, $diachi, $user_id);

// Nếu cập nhật vào cơ sở dữ liệu thành công
if (mysqli_stmt_execute($stmt)) {
    // Đồng bộ và ghi đè lại toàn bộ thông tin mới vào các biến $_SESSION để hiển thị chính xác ở giao diện sau đó
    $_SESSION['user_ho'] = $ho;
    $_SESSION['user_name'] = $ten;
    $_SESSION['user_sdt'] = $sdt;
    $_SESSION['user_ngaysinh'] = $ngaysinh;
    $_SESSION['user_diachi'] = $diachi;
}

// Quay trở lại trang quản lý tài khoản sau khi hoàn tất
header("Location: taikhoan.php");
exit();
?>