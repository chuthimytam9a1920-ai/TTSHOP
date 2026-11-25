
<?php
// huydonhang.php — Hủy đơn của khách hàng bằng POST, CSRF và transaction.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/csrf.php';
header('Cache-Control: no-store');

function hd_redirect(int $userId, bool $ok, string $message): void
{
    $_SESSION['customer_cancel_flash'] = [
        'user_id' => $userId,
        'ok' => $ok,
        'message' => $message,
    ];
    header('Location: taikhoan.php?tab=donhang', true, 303);
    exit;
}

function hd_positive_id($value): ?int
{
    if (!is_int($value) && !is_string($value)) {
        return null;
    }
    $id = filter_var($value, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 2147483647],
    ]);
    return $id === false ? null : $id;
}

// Chỉ cho phép khách hàng đã đăng nhập thực hiện yêu cầu.
$userId = hd_positive_id($_SESSION['user_id'] ?? null);
$email = $_SESSION['user_email'] ?? '';
if (($_SESSION['is_login'] ?? false) !== true || $userId === null
    || !is_string($email) || trim($email) === '') {
    header('Location: dangnhap.php', true, 303);
    exit;
}

// GET chỉ để đọc dữ liệu, tuyệt đối không hủy đơn qua URL.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Chỉ chấp nhận yêu cầu POST. Hãy hủy đơn từ trang tài khoản.');
}

if (!csrf_check($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang tài khoản.');
}

$orderId = hd_positive_id($_POST['id'] ?? null);
if ($orderId === null) {
    hd_redirect($userId, false, 'ID đơn hàng không hợp lệ.');
}

$inTransaction = false;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require_once __DIR__ . '/connect.php';
    $conn->begin_transaction();
    $inTransaction = true;

    // Khóa tài khoản trước, cùng thứ tự với đặt hàng và cập nhật trạng thái Admin.
    $stmt = $conn->prepare('SELECT email FROM users WHERE id = ? FOR UPDATE');
    try {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $owner = $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
    if (!$owner || strcasecmp($email, (string)$owner['email']) !== 0) {
        throw new DomainException('Tài khoản không hợp lệ. Vui lòng đăng nhập lại.');
    }

    // Khóa đúng đơn của khách hiện tại; không tiết lộ đơn của khách khác.
    $stmt = $conn->prepare('SELECT trang_thai FROM donhang
        WHERE id = ? AND user_id = ? FOR UPDATE');
    try {
        $stmt->bind_param('ii', $orderId, $userId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
    if (!$order) {
        throw new DomainException('Không tìm thấy đơn hàng có thể thao tác.');
    }

    // Yêu cầu gửi lại không làm thay đổi đơn hoặc hoàn lượt lần thứ hai.
    $status = (string)$order['trang_thai'];
    if (in_array($status, ['Đã huỷ', 'Đã hủy'], true)) {
        $conn->commit();
        $inTransaction = false;
        hd_redirect($userId, true, 'Đơn hàng này đã được hủy trước đó.');
    }
    if (!in_array($status, ['Chờ xử lý', 'Chờ lấy hàng'], true)) {
        throw new DomainException('Đơn hàng không còn ở trạng thái cho phép hủy.');
    }

    // Chỉ đổi trạng thái. Giữ nguyên giá, hạng khi mua và lịch sử voucher.
    $stmt = $conn->prepare("UPDATE donhang SET trang_thai = 'Đã huỷ'
        WHERE id = ? AND user_id = ?
        AND trang_thai IN ('Chờ xử lý', 'Chờ lấy hàng')");
    try {
        $stmt->bind_param('ii', $orderId, $userId);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('Không cập nhật được đúng một đơn hàng.');
        }
    } finally {
        $stmt->close();
    }

    // voucher_usage() tự loại đơn đã hủy khỏi lượt dùng.
    // membership_get() chỉ tính đơn đã giao. Không sửa hạng lưu trong đơn cũ.
    // Không UPDATE giới hạn voucher, không DELETE lichsu_magiamgia.
    $conn->commit();
    $inTransaction = false;
    hd_redirect($userId, true, 'Đã hủy đơn hàng. Nếu có voucher, lượt sử dụng đã được giải phóng.');
} catch (Throwable $e) {
    if ($inTransaction) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    if (!($e instanceof DomainException)) {
        error_log('TT SHOP cancel order: ' . $e->getMessage());
    }
    hd_redirect($userId, false, $e instanceof DomainException
        ? $e->getMessage() : 'Không thể hủy đơn hàng. Vui lòng thử lại.');
}
