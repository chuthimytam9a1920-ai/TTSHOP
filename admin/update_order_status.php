
<?php
// admin/update_order_status.php
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../csrf.php';
header('Cache-Control: no-store');

function os_redirect(?int $id, bool $ok, string $message): void {
    $_SESSION['admin_order_flash'] = ['ok' => $ok, 'message' => $message];
    header('Location: ' . ($id ? 'order_detail.php?id=' . $id : 'orders.php'), true, 303);
    exit;
}
function os_normalize(string $status): string {
    return $status === 'Đã hủy' ? 'Đã huỷ' : $status;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Chỉ chấp nhận yêu cầu POST.');
}
if (!csrf_check($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang chi tiết đơn hàng.');
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
if (!$id) os_redirect(null, false, 'ID đơn hàng không hợp lệ.');
$new = $_POST['trang_thai'] ?? null;
$expected = $_POST['expected_status'] ?? null;
$allowed = ['Chờ xử lý', 'Chờ lấy hàng', 'Đang vận chuyển', 'Đã giao', 'Đã huỷ'];
if (!is_string($new) || !is_string($expected) || !in_array(os_normalize($new), $allowed, true)) {
    os_redirect($id, false, 'Trạng thái đơn hàng không hợp lệ.');
}
$new = os_normalize($new);
$expected = os_normalize($expected);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../connect.php';
$inTransaction = false;
try {
    // Lấy chủ đơn trước khi khóa theo cùng thứ tự với luồng đặt hàng.
    $stmt = $conn->prepare('SELECT user_id FROM donhang WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $initial = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$initial) os_redirect(null, false, 'Đơn hàng không tồn tại.');
    $userId = (int)$initial['user_id'];

    $conn->begin_transaction();
    $inTransaction = true;
    $stmt = $conn->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $owner = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$owner) throw new DomainException('Không tìm thấy tài khoản của đơn hàng.');

    $stmt = $conn->prepare('SELECT user_id, trang_thai, ma_giam_gia, phuong_thuc, payment_status FROM donhang WHERE id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order || (int)$order['user_id'] !== $userId) {
        throw new DomainException('Đơn hàng đã thay đổi hoặc không còn tồn tại.');
    }
    $current = os_normalize((string)$order['trang_thai']);
    if ($current !== $expected) {
        throw new DomainException('Trạng thái đã thay đổi. Hãy kiểm tra lại trước khi cập nhật.');
    }
    if ($current === $new) {
        $conn->commit();
        $inTransaction = false;
        os_redirect($id, true, 'Trạng thái đơn hàng không thay đổi.');
    }

    if ($order['phuong_thuc']==='PAYOS' && ($order['payment_status']!=='PAID' || $new==='Đã huỷ' || $current==='Đã huỷ')) {
        throw new DomainException('Đơn QR phải được xác nhận đủ tiền trước khi giao. Không hủy hoặc mở lại đơn QR bằng trạng thái vận chuyển; cần đối soát riêng.');
    }
    // Hủy đơn giải phóng lượt sử dụng theo voucher_usage().
    // Không mở lại đơn có voucher đã hủy, tránh vượt giới hạn sau khi lượt được dùng lại.
    if ($current === 'Đã huỷ') {
        $stmt = $conn->prepare('SELECT id FROM lichsu_magiamgia WHERE donhang_id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $history = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (trim((string)($order['ma_giam_gia'] ?? '')) !== '' || $history) {
            throw new DomainException('Không mở lại đơn có voucher đã hủy. Vui lòng tạo đơn mới.');
        }
    }

    // Không sửa giá tiền, hạng lúc mua hoặc lịch sử voucher.
    $stmt = $conn->prepare('UPDATE donhang SET trang_thai = ? WHERE id = ?');
    $stmt->bind_param('si', $new, $id);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
    $inTransaction = false;
    os_redirect($id, true, 'Đã cập nhật trạng thái đơn hàng.');
} catch (Throwable $e) {
    if ($inTransaction) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    if (!($e instanceof DomainException)) {
        error_log('TT SHOP update order status: ' . $e->getMessage());
    }
    os_redirect($id, false, $e instanceof DomainException ? $e->getMessage() : 'Không thể cập nhật trạng thái. Vui lòng thử lại.');
}
