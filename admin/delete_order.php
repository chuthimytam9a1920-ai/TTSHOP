
<?php
// admin/delete_order.php — Xóa đơn hàng bằng POST + CSRF và transaction.
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../csrf.php';
header('Cache-Control: no-store');

function order_delete_redirect(bool $ok, string $message): void {
    $_SESSION['admin_order_flash'] = ['ok' => $ok, 'message' => $message];
    header('Location: orders.php', true, 303);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Yêu cầu không hợp lệ. Hãy xóa đơn hàng từ trang quản lý.');
}
if (!csrf_check($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang quản lý đơn hàng.');
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 2147483647]
]);
if ($id === false || $id === null) {
    order_delete_redirect(false, 'ID đơn hàng không hợp lệ.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../connect.php';
$inTransaction = false;
try {
    $conn->begin_transaction();
    $inTransaction = true;

    $stmt = $conn->prepare('SELECT id FROM donhang WHERE id = ? FOR UPDATE');
    try {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $exists = (bool)$stmt->get_result()->fetch_assoc();
    } finally {
        $stmt->close();
    }
    if (!$exists) {
        $conn->rollback();
        $inTransaction = false;
        order_delete_redirect(false, 'Đơn hàng không tồn tại hoặc đã bị xóa.');
    }

    // Chi tiết đơn có FK RESTRICT, phải xóa trước đơn hàng.
    // Đánh giá và lịch sử voucher được xóa theo FK ON DELETE CASCADE
    // trong ttshop_final.sql và update_voucher_fix.sql.
    $stmt = $conn->prepare('DELETE FROM chitiet_donhang WHERE donhang_id = ?');
    try {
        $stmt->bind_param('i', $id);
        $stmt->execute();
    } finally {
        $stmt->close();
    }
    $stmt = $conn->prepare('DELETE FROM donhang WHERE id = ?');
    try {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('Không xóa được đúng một đơn hàng.');
        }
    } finally {
        $stmt->close();
    }
    $conn->commit();
    $inTransaction = false;
    order_delete_redirect(true, 'Đã xóa đơn hàng.');
} catch (Throwable $e) {
    if ($inTransaction) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    error_log('TT SHOP delete order: ' . $e->getMessage());
    order_delete_redirect(false, 'Không thể xóa đơn hàng. Vui lòng thử lại.');
}
