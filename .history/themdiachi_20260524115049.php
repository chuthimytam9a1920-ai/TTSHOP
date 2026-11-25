<?php
// Bắt đầu phiên làm việc (session) để kiểm tra quyền truy cập của người dùng
session_start();

// Kết nối tới cơ sở dữ liệu
include "connect.php";

// Kiểm tra bảo mật: Nếu chưa đăng nhập, lập tức chuyển hướng người dùng về trang đăng nhập
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

// Lấy ID của người dùng đang đăng nhập từ session
$user_id = $_SESSION['user_id'];

// Lấy và cắt bỏ khoảng trắng thừa của các thông tin địa chỉ gửi lên từ form
$ho_ten   = trim($_POST['ho_ten'] ?? '');
$sdt      = trim($_POST['sdt'] ?? '');
$cong_ty  = trim($_POST['cong_ty'] ?? '');
$dia_chi  = trim($_POST['dia_chi'] ?? '');
$quoc_gia = trim($_POST['quoc_gia'] ?? 'Vietnam'); // Mặc định là Vietnam nếu để trống
$ma_zip   = trim($_POST['ma_zip'] ?? '');

// Kiểm tra xem người dùng có tích chọn ô "Đặt làm địa chỉ mặc định" hay không (Có tích = 1, Không tích = 0)
$mac_dinh = isset($_POST['mac_dinh']) ? 1 : 0;

// XỬ LÝ ĐỊA CHỈ MẶC ĐỊNH: Nếu địa chỉ mới này được đặt là mặc định
if ($mac_dinh == 1) {
    // Tiến hành cập nhật tất cả các địa chỉ cũ của user này thành không mặc định (mac_dinh = 0) trước khi thêm địa chỉ mới
    $sqlReset = "UPDATE diachi_khachhang SET mac_dinh = 0 WHERE user_id = ?";
    $stmtReset = mysqli_prepare($conn, $sqlReset);
    $stmtReset = mysqli_prepare($conn, $sqlReset);
    mysqli_stmt_bind_param($stmtReset, "i", $user_id);
    mysqli_stmt_execute($stmtReset);
}

// BƯỚC THÊM MỚI: Chuẩn bị câu lệnh SQL để chèn địa chỉ mới này vào bảng diachi_khachhang
$sql = "INSERT INTO diachi_khachhang 
(user_id, ho_ten, so_dien_thoai, cong_ty, dia_chi, quoc_gia, ma_zip, mac_dinh)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "issssssi", $user_id, $ho_ten, $sdt, $cong_ty, $dia_chi, $quoc_gia, $ma_zip, $mac_dinh);

// Thực thi lưu địa chỉ mới vào database
mysqli_stmt_execute($stmt);

// Sau khi thêm thành công, chuyển hướng người dùng quay trở lại trang quản lý tài khoản
header("Location: taikhoan.php");
exit();
?>