<?php
require_once __DIR__ . '/chat_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST'); ttchat_json(['ok' => false], 405);
}
$identity = ttchat_identity();
if (!ttchat_csrf($identity['key'], $_POST['csrf_token'] ?? null)) {
    ttchat_json(['ok' => false, 'message' => 'Phiên chat đã thay đổi. Vui lòng tải lại trang.'], 403);
}
// Khong chap nhan vai tro, ten hay ID hoi thoai tu client.
foreach (['sender_type', 'customer_name', 'conversation_id', 'user_id'] as $field) {
    if (array_key_exists($field, $_POST)) ttchat_json(['ok' => false, 'message' => 'Yêu cầu không hợp lệ.'], 400);
}
$message = ttchat_message($_POST['message'] ?? null);
if ($message === null) ttchat_json(['ok' => false, 'message' => 'Tin nhắn phải từ 1 đến 2000 ký tự.'], 422);
$now = microtime(true);
if ($now - (float)($_SESSION['ttchat_last_send'] ?? 0) < 1.5) {
    ttchat_json(['ok' => false, 'message' => 'Bạn gửi quá nhanh. Vui lòng thử lại sau vài giây.'], 429);
}
$_SESSION['ttchat_last_send'] = $now;
session_write_close();
$transaction = false;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/connect.php';
    $conn->begin_transaction(); $transaction = true;
    $name = ttchat_customer_name($conn, $identity);
    $conversation = ttchat_conversation($conn, $identity, true);
    if (!$conversation) throw new DomainException('Không tìm thấy cuộc trò chuyện.');
    $id = (int)$conversation['id'];
    ttchat_insert($conn, $id, $name, 'user', $message);
    ttchat_insert($conn, $id, $name, 'bot', ttchat_bot_reply($message));
    $conn->commit(); $transaction = false;
    ttchat_json(['ok' => true]);
} catch (Throwable $e) {
    if ($transaction) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
    error_log('TT SHOP chat send: ' . $e->getMessage());
    ttchat_json(['ok' => false, 'message' => 'Không thể gửi tin nhắn. Vui lòng thử lại.'], 503);
}
