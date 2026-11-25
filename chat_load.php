<?php
require_once __DIR__ . '/chat_helpers.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET'); ttchat_json(['ok' => false], 405);
}
$identity = ttchat_identity();
// Token ngan tab cu doc nham lich su sau khi doi tai khoan.
if (!ttchat_csrf($identity['key'], $_SERVER['HTTP_X_CHAT_CSRF'] ?? null)) {
    ttchat_json(['ok' => false, 'message' => 'Phiên chat đã thay đổi. Vui lòng tải lại trang.'], 403);
}
session_write_close();
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/connect.php';
    $conversation = ttchat_conversation($conn, $identity);
    $messages = $conversation ? ttchat_messages($conn, (int)$conversation['id'])->fetch_all(MYSQLI_ASSOC) : [];
    ttchat_json(['ok' => true, 'messages' => $messages]);
} catch (Throwable $e) {
    error_log('TT SHOP chat load: ' . $e->getMessage());
    ttchat_json(['ok' => false, 'message' => 'Không thể tải tin nhắn. Vui lòng thử lại.'], 503);
}
