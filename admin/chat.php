<?php
require_once __DIR__ . '/../chat_helpers.php';
ttchat_session();
$poll = ($_GET['poll'] ?? '') === '1';
$adminId = (int)($_SESSION['admin_id'] ?? 0);
if (($_SESSION['admin_login'] ?? false) !== true || $adminId <= 0) {
    if ($poll) ttchat_json(['ok' => false], 403);
    header('Location: login.php'); exit;
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$page = 'chat';
$adminScope = 'a:' . $adminId;
$token = ttchat_token($adminScope);
$archive = ($_GET['archive'] ?? '') === '1';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
function ttchat_admin_id($value): int {
    if (!is_string($value) && !is_int($value)) return 0;
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
    return $id === false ? 0 : $id;
}
function ttchat_admin_label(array $row): string {
    if ($row['user_id'] !== null) return ($row['customer_name'] ?: 'Khách hàng') . ' (ID KH ' . $row['user_id'] . ')';
    if (str_starts_with($row['owner_key'], 'u:')) return 'Tài khoản đã xóa (Chat #' . $row['id'] . ')';
    return 'Khách #' . $row['id'];
}
function ttchat_admin_list(array $rows, int $selectedId): string {
    $html = '';
    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $html .= '<a class="customer-item' . ($id === $selectedId ? ' active' : '') . '" href="chat.php?conversation_id=' . $id . '">'
            . '<div class="customer-name">' . ttchat_escape(ttchat_admin_label($row)) . '</div>'
            . '<div class="customer-time">' . ttchat_escape(date('d/m/Y H:i', strtotime($row['last_time']))) . '</div></a>';
    }
    return $html ?: '<div class="empty-chat">Chưa có khách nào nhắn tin.</div>';
}
function ttchat_admin_messages(mysqli_result $messages, bool $legacy = false): string {
    $html = '';
    while ($msg = $messages->fetch_assoc()) {
        $role = $msg['sender_type'];
        $label = $legacy ? ('Tên cũ: ' . ($msg['customer_name'] ?: '(trống)') . ' – ' . $role . ' (chưa xác minh)') :
            ($role === 'bot' ? 'Trợ lý tự động' : ($role === 'admin' ? 'Nhân viên TT SHOP' : 'Khách hàng'));
        $html .= '<div class="msg ' . ($role === 'user' ? 'user-msg' : 'admin-msg') . '" style="overflow-wrap:anywhere">'
            . '<div class="time">' . ttchat_escape($label) . '</div>' . nl2br(ttchat_escape($msg['message']))
            . '<div class="time">' . ttchat_escape(date('d/m/Y H:i', strtotime($msg['created_at']))) . '</div></div>';
    }
    return $html ?: '<div class="empty-chat">Chưa có tin nhắn.</div>';
}
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    require __DIR__ . '/../connect.php';
    if (!ttchat_query($conn, 'SELECT id FROM admins WHERE id = ?', 'i', [$adminId])->fetch_assoc()) {
        if ($poll) ttchat_json(['ok' => false], 403);
        http_response_code(403); exit('Tài khoản Admin không hợp lệ.');
    }
    if ($poll && ($method !== 'GET' || !ttchat_csrf($adminScope, $_SERVER['HTTP_X_CHAT_CSRF'] ?? null))) {
        ttchat_json(['ok' => false], 403);
    }
    if ($method === 'POST') {
        if (!ttchat_csrf($adminScope, $_POST['csrf_token'] ?? null)) {
            http_response_code(403); exit('Phiên xác thực không hợp lệ. Vui lòng tải lại trang.');
        }
        $id = ttchat_admin_id($_POST['conversation_id'] ?? null);
        $message = ttchat_message($_POST['message'] ?? null);
        if (!$id || $message === null) { http_response_code(422); exit('Tin nhắn hoặc mã cuộc trò chuyện không hợp lệ.'); }
        // Chi tra loi hoi thoai moi co owner_key; khong tra loi kho lich su cu.
        $row = ttchat_query($conn, 'SELECT c.id, c.user_id, c.owner_key, m.customer_name FROM chat_conversations c JOIN chat_messages m ON m.conversation_id = c.id WHERE c.id = ? AND c.owner_key IS NOT NULL ORDER BY m.id DESC LIMIT 1', 'i', [$id])->fetch_assoc();
        if (!$row || ($row['user_id'] === null && str_starts_with($row['owner_key'], 'u:'))) {
            http_response_code(404); exit('Cuộc trò chuyện không tồn tại hoặc không còn hoạt động.');
        }
        ttchat_insert($conn, $id, $row['customer_name'], 'admin', $message);
        header('Location: chat.php?conversation_id=' . $id, true, 303); exit;
    }
    if ($method !== 'GET') { header('Allow: GET, POST'); http_response_code(405); exit; }
    $rows = ttchat_query($conn, "SELECT c.id, c.user_id, c.owner_key, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.ho, u.ten)), ''), m.customer_name) AS customer_name, m.created_at AS last_time FROM chat_conversations c JOIN (SELECT conversation_id, MAX(id) AS last_id FROM chat_messages WHERE conversation_id IS NOT NULL GROUP BY conversation_id) latest ON latest.conversation_id = c.id JOIN chat_messages m ON m.id = latest.last_id LEFT JOIN users u ON u.id = c.user_id WHERE c.owner_key IS NOT NULL ORDER BY m.id DESC")->fetch_all(MYSQLI_ASSOC);
    $selectedId = ttchat_admin_id($_GET['conversation_id'] ?? null);
    if (!$selectedId && !$poll && !$archive && !isset($_GET['conversation_id']) && $rows) $selectedId = (int)$rows[0]['id'];
    $selected = null;
    foreach ($rows as $row) if ((int)$row['id'] === $selectedId) { $selected = $row; break; }
    $listHtml = ttchat_admin_list($rows, $archive ? 0 : $selectedId);
    $messagesHtml = $selected ? ttchat_admin_messages(ttchat_messages($conn, $selectedId)) : '';
    if ($poll) ttchat_json(['ok' => true, 'list_html' => $listHtml, 'messages_html' => $messagesHtml]);
    $archiveHtml = $archive ? ttchat_admin_messages(ttchat_query($conn, 'SELECT customer_name, sender_type, message, created_at FROM chat_messages WHERE conversation_id IS NULL ORDER BY id ASC'), true) : '';
} catch (Throwable $e) {
    error_log('TT SHOP admin chat: ' . $e->getMessage());
    if ($poll) ttchat_json(['ok' => false], 503);
    http_response_code(503); exit('Không thể mở chat. Kiểm tra SQL nâng cấp và thử lại.');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Chat khách hàng - TT SHOP</title>

    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f6f8;
        }

        .admin-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: #15985d;
            color: white;
            padding: 25px 20px;
        }

        .sidebar h2 {
            font-size: 30px;
            margin-bottom: 35px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 14px 12px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: rgba(255,255,255,.18);
        }

        .main {
            padding: 30px;
            min-width: 0;
        }

        .topbar {
            background: white;
            border-radius: 14px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 4px 14px rgba(0,0,0,.08);
        }

        .topbar h1 {
            margin: 0;
        }

        .chat-wrapper {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 18px;
        }

        .chat-list,
        .chat-panel {
            background: white;
            border-radius: 14px;
            box-shadow: 0 4px 14px rgba(0,0,0,.08);
            overflow: hidden;
        }

        .chat-list-title {
            background: #15985d;
            color: white;
            padding: 16px;
            font-weight: bold;
        }

        .customer-item {
            display: block;
            padding: 16px;
            text-decoration: none;
            color: #333;
            border-bottom: 1px solid #eee;
        }

        .customer-item:hover,
        .customer-item.active {
            background: #e9f7ef;
            border-left: 5px solid #15985d;
        }

        .customer-name {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .customer-time {
            font-size: 13px;
            color: #777;
        }

        .chat-panel-header {
            background: #15985d;
            color: white;
            padding: 16px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .chat-body {
            height: 520px;
            overflow-y: auto;
            background: #fafafa;
            padding: 18px;
        }

        .msg {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .user-msg {
            background: #e9e9e9;
            color: #333;
        }

        .admin-msg {
            background: #15985d;
            color: white;
            margin-left: auto;
        }

        .time {
            font-size: 12px;
            opacity: .75;
            margin-top: 5px;
        }

        .chat-form {
            display: flex;
            gap: 12px;
            padding: 16px;
            border-top: 1px solid #eee;
        }

        .chat-form input {
            flex: 1;
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 999px;
            outline: none;
        }

        .chat-form button {
            border: 0;
            background: #15985d;
            color: white;
            border-radius: 999px;
            padding: 0 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .empty-chat {
            text-align: center;
            color: #777;
            padding: 80px 20px;
        }
    </style>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="admin.css">
</head>

<body>

<div class="admin-layout">

<?php include "sidebar.php"; ?>

    <main class="main">

        <div class="topbar">
            <h1>Chat khách hàng</h1>
        </div>

        <div class="chat-wrapper">

            <div class="chat-list">
                <div class="chat-list-title">
                    Danh sách khách chat
                </div>

                <a class="customer-item <?php echo $archive ? 'active' : ''; ?>" href="chat.php?archive=1">📁 Lịch sử chat cũ (chỉ xem)</a>
                <div id="ttchatAdminList"><?php echo $listHtml; ?></div>
            </div>

            <div class="chat-panel">

                <?php if ($archive) { ?>
                    <div class="chat-panel-header">Lịch sử chat cũ – chưa xác minh chủ sở hữu</div>
                    <div class="chat-body" id="chatBody">
                        <p>Chỉ Admin xem. Tin nhắn cũ có thể bị gộp theo tên; vai trò người gửi cũ chưa được xác thực.</p>
                        <?php echo $archiveHtml; ?>
                    </div>
                <?php } elseif ($selected) { ?>
                    <div class="chat-panel-header"><span>Chat với: <?php echo ttchat_escape(ttchat_admin_label($selected)); ?></span></div>
                    <div class="chat-body" id="chatBody"><?php echo $messagesHtml; ?></div>
                    <form method="POST" class="chat-form" action="chat.php">
                        <input type="hidden" name="conversation_id" value="<?php echo (int)$selected['id']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo ttchat_escape($token); ?>">
                        <input type="text" name="message" maxlength="2000" placeholder="Nhập tin nhắn trả lời khách..." required>
                        <button type="submit">Gửi</button>
                    </form>
                    <div id="ttchatAdminStatus" role="status" style="padding:0 16px;color:#a52a2a"></div>
                <?php } else { ?>
                    <div class="empty-chat">Chọn một khách ở cột trái để bắt đầu chat.</div>
                <?php } ?>
            </div>

        </div>

    </main>

</div>

<script>
const chatBody = document.getElementById('chatBody');
if (chatBody) chatBody.scrollTop = chatBody.scrollHeight;
<?php if (!$archive) { ?>
let ttchatAdminBusy = false, ttchatAdminStopped = false;
const ttchatSelectedId = <?php echo (int)($selected['id'] ?? 0); ?>;
const ttchatAdminToken = <?php echo json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let ttchatAdminLast = chatBody ? chatBody.innerHTML : '';
setInterval(async function() {
    if (ttchatAdminBusy || ttchatAdminStopped) return;
    ttchatAdminBusy = true;
    try {
        const response = await fetch('chat.php?poll=1&conversation_id=' + ttchatSelectedId, {
            credentials:'same-origin', cache:'no-store', headers:{'X-Chat-CSRF':ttchatAdminToken}
        });
        if (response.status === 403 || response.redirected) {
            ttchatAdminStopped = true;
            if (chatBody) chatBody.textContent = 'Phiên Admin đã hết hạn. Vui lòng đăng nhập lại.';
            document.getElementById('ttchatAdminList').textContent = '';
            const form = document.querySelector('.chat-form'); if (form) form.remove();
            return;
        }
        if (!response.ok) throw new Error('Không thể cập nhật chat.');
        const data = await response.json();
        document.getElementById('ttchatAdminList').innerHTML = data.list_html;
        if (chatBody && data.messages_html !== ttchatAdminLast) {
            const atBottom = chatBody.scrollHeight - chatBody.scrollTop - chatBody.clientHeight < 60;
            chatBody.innerHTML = data.messages_html;
            ttchatAdminLast = data.messages_html;
            if (atBottom) chatBody.scrollTop = chatBody.scrollHeight;
        }
        const status = document.getElementById('ttchatAdminStatus'); if (status) status.textContent = '';
    } catch (_) {
        const status = document.getElementById('ttchatAdminStatus'); if (status) status.textContent = 'Không thể cập nhật chat. Sẽ thử lại.';
    } finally { ttchatAdminBusy = false; }
}, 4000);
<?php } ?>
</script>

</body>
</html>