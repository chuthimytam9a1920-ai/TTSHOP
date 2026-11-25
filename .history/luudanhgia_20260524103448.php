<?php
// Kiểm tra và khởi chạy session nếu chưa được bật
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kết nối tới cơ sở dữ liệu (Database)
include "connect.php";

// Kiểm tra bảo mật: Nếu chưa đăng nhập, lập tức đá người dùng về trang đăng nhập
if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header("Location: dangnhap.php");
    exit();
}

// Lấy thông tin ID người dùng, ID đơn hàng, số sao (1-5) và nội dung nhận xét gửi lên từ form
$user_id = (int)$_SESSION['user_id'];
$donhang_id = (int)($_POST['donhang_id'] ?? 0);
$so_sao = (int)($_POST['so_sao'] ?? 0);
$nhan_xet = trim($_POST['nhan_xet'] ?? '');

// Ràng buộc dữ liệu đầu vào: ID đơn hàng phải hợp lệ và số sao bắt buộc phải nằm trong khoảng từ 1 đến 5
if ($donhang_id <= 0 || $so_sao < 1 || $so_sao > 5) {
    die("Dữ liệu đánh giá không hợp lệ.");
}

// BƯỚC 1: Kiểm tra xem đơn hàng này có thật sự là của người dùng này và đã ở trạng thái "Đã giao" hay chưa
$sqlCheckOrder = "
    SELECT id
    FROM donhang
    WHERE id = ?
    AND user_id = ?
    AND trang_thai = 'Đã giao'
";
$stmtCheckOrder = mysqli_prepare($conn, $sqlCheckOrder);
mysqli_stmt_bind_param($stmtCheckOrder, "ii", $donhang_id, $user_id);
mysqli_stmt_execute($stmtCheckOrder);
$resultOrder = mysqli_stmt_get_result($stmtCheckOrder);
$order = mysqli_fetch_assoc($resultOrder);

// Nếu không khớp thông tin hoặc đơn hàng chưa giao xong, chặn không cho đánh giá
if (!$order) {
    die("Bạn chỉ có thể đánh giá đơn hàng đã giao.");
}

// BƯỚC 2: Kiểm tra chống spam (Đảm bảo người dùng này chưa từng đánh giá đơn hàng này trước đó)
$sqlCheckReview = "
    SELECT id
    FROM danhgia
    WHERE donhang_id = ?
    AND user_id = ?
    LIMIT 1
";
$stmtCheckReview = mysqli_prepare($conn, $sqlCheckReview);
mysqli_stmt_bind_param($stmtCheckReview, "ii", $donhang_id, $user_id);
mysqli_stmt_execute($stmtCheckReview);
$resultReview = mysqli_stmt_get_result($stmtCheckReview);

// Nếu đã tồn tại bản ghi đánh giá rồi thì dừng lại và báo lỗi
if (mysqli_num_rows($resultReview) > 0) {
    die("Bạn đã đánh giá đơn hàng này rồi.");
}

// BƯỚC 3: Lấy toàn bộ danh sách sản phẩm nằm trong đơn hàng đó từ bảng chi tiết đơn hàng
$sqlItems = "
    SELECT *
    FROM chitiet_donhang
    WHERE donhang_id = ?
";
$stmtItems = mysqli_prepare($conn, $sqlItems);
mysqli_stmt_bind_param($stmtItems, "i", $donhang_id);
mysqli_stmt_execute($stmtItems);
$resultItems = mysqli_stmt_get_result($stmtItems);

// Chạy vòng lặp lưu đánh giá: Áp dụng cùng số sao và nội dung nhận xét cho từng sản phẩm có trong đơn hàng
while ($item = mysqli_fetch_assoc($resultItems)) {
    $ten_san_pham = $item['ten_san_pham'];
    $hinh_anh = $item['hinh_anh'];
    
    // Câu lệnh SQL thêm dữ liệu vào bảng danhgia
    $sqlInsert = "
        INSERT INTO danhgia (
            donhang_id,
            user_id,
            ten_san_pham,
            hinh_anh,
            so_sao,
            nhan_xet
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ";
    $stmtInsert = mysqli_prepare($conn, $sqlInsert);
    mysqli_stmt_bind_param(
        $stmtInsert,
        "iissis",
        $donhang_id,
        $user_id,
        $ten_san_pham,
        $hinh_anh,
        $so_sao,
        $nhan_xet
    );
    mysqli_stmt_execute($stmtInsert);
}

// Sau khi lưu thành công, chuyển hướng về trang chi tiết đơn hàng kèm theo thông báo thành công (review=success)
header("Location: chitietdonhang.php?id=" . $donhang_id . "&review=success");
exit();
?>