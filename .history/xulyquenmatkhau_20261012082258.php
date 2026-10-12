
<?php
session_start();

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/reset_helpers.php';

// Chỉ cho phép gửi yêu cầu bằng phương thức POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quenmatkhau.php');
    exit;
}

// Thông báo chung, tránh tiết lộ email có tài khoản hay không
$successMessage = 'Nếu email đã được đăng ký, chúng tôi sẽ gửi liên kết đặt lại mật khẩu đến hộp thư của bạn. Vui lòng kiểm tra cả thư mục Spam.';

function redirectReset($type, $message)
{
    $_SESSION[$type] = $message;
    header('Location: quenmatkhau.php');
    exit;
}

// 1. Nhận và kiểm tra email
$email = trim($_POST['email'] ?? '');

if (
    empty($email) ||
    strlen($email) > 254 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    redirectReset(
        'reset_error',
        'Vui lòng nhập địa chỉ email hợp lệ.'
    );
}

// 2. Kiểm tra số lần gửi yêu cầu trong một session
$now = time();
$lastRequest = $_SESSION['last_reset_request'] ?? 0;

if ($now - $lastRequest < 60) {
    redirectReset(
        'reset_error',
        'Vui lòng chờ 60 giây trước khi gửi yêu cầu tiếp theo.'
    );
}

$_SESSION['last_reset_request'] = $now;

try {

    // 3. Tìm tài khoản theo email
    $sql = "SELECT id, email
            FROM users
            WHERE email = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Không thể chuẩn bị truy vấn.');
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    // 4. Nếu email không tồn tại, vẫn hiện thông báo chung
    if (!$user) {
        redirectReset('reset_success', $successMessage);
    }

    $userId = (int)$user['id'];

    // 5. Tạo mã bảo mật ngẫu nhiên
    $token = bin2hex(random_bytes(32));

    // Chỉ lưu hash, không lưu token gốc
    $tokenHash = hash('sha256', $token);

    // Hết hạn sau 30 phút
    $expiresAt = date('Y-m-d H:i:s', time() + 1800);

    // 6. Xóa các mã khôi phục cũ của tài khoản
    $deleteSql = "DELETE FROM password_resets
                  WHERE user_id = ?";

    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param('i', $userId);
    $deleteStmt->execute();
    $deleteStmt->close();

    // 7. Lưu mã mới
    $insertSql = "INSERT INTO password_resets
                  (user_id, token_hash, expires_at)
                  VALUES (?, ?, ?)";

    $insertStmt = $conn->prepare($insertSql);

    $insertStmt->bind_param(
        'iss',
        $userId,
        $tokenHash,
        $expiresAt
    );

    if (!$insertStmt->execute()) {
        throw new Exception('Không thể lưu mã khôi phục.');
    }

    $insertStmt->close();

    // 8. Tạo liên kết đặt lại mật khẩu
    $baseUrl = resetBaseUrl();

    $resetLink = $baseUrl .
        '/datlaimatkhau.php?token=' .
        urlencode($token);

    // 9. Gửi email cho khách hàng
    $sent = sendResetEmail(
        $user['email'],
        $resetLink
    );

    if (!$sent) {
        // Vô hiệu hóa mã nếu gửi email thất bại
        $cleanup = $conn->prepare(
            "DELETE FROM password_resets
             WHERE user_id = ? AND token_hash = ?"
        );

        $cleanup->bind_param('is', $userId, $tokenHash);
        $cleanup->execute();
        $cleanup->close();

        error_log('TT SHOP: Failed to send password reset email.');
    }

    // 10. Thông báo chung
    redirectReset('reset_success', $successMessage);

} catch (Throwable $e) {

    // Ghi lỗi vào log của PHP, không hiển thị chi tiết cho khách
    error_log('TT SHOP password reset: ' . $e->getMessage());

    redirectReset(
        'reset_error',
        'Hệ thống tạm thời không thể xử lý yêu cầu. Vui lòng thử lại sau.'
    );
}
