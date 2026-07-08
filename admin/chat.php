<?php
include "check_admin.php";
include "../connect.php";

$page = "chat";

$customer = trim($_GET['customer'] ?? '');

/* Gửi tin nhắn admin */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message'] ?? '');
    $customerPost = trim($_POST['customer_name'] ?? '');

    if ($message !== '' && $customerPost !== '') {
        $sql = "
            INSERT INTO chat_messages (
                customer_name,
                sender_type,
                message
            )
            VALUES (
                ?,
                'admin',
                ?
            )
        ";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $customerPost, $message);
        mysqli_stmt_execute($stmt);

        header("Location: chat.php?customer=" . urlencode($customerPost));
        exit();
    }
}

/* Danh sách khách chat */
$customers = mysqli_query($conn, "
    SELECT 
        customer_name,
        MAX(id) AS last_id,
        MAX(created_at) AS last_time
    FROM chat_messages
    GROUP BY customer_name
    ORDER BY last_id DESC
");

/* Nếu chưa chọn khách thì lấy khách mới nhất */
if ($customer === '' && $customers && mysqli_num_rows($customers) > 0) {
    $firstCustomer = mysqli_fetch_assoc($customers);
    $customer = $firstCustomer['customer_name'];

    mysqli_data_seek($customers, 0);
}

/* Tin nhắn theo khách */
$messages = null;

if ($customer !== '') {
    $sqlMsg = "
        SELECT *
        FROM chat_messages
        WHERE customer_name = ?
        ORDER BY id ASC
    ";

    $stmtMsg = mysqli_prepare($conn, $sqlMsg);
    mysqli_stmt_bind_param($stmtMsg, "s", $customer);
    mysqli_stmt_execute($stmtMsg);
    $messages = mysqli_stmt_get_result($stmtMsg);
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

                <?php if ($customers && mysqli_num_rows($customers) > 0) { ?>
                    <?php while ($cus = mysqli_fetch_assoc($customers)) { ?>
                        <a class="customer-item <?php echo ($customer == $cus['customer_name']) ? 'active' : ''; ?>"
                           href="chat.php?customer=<?php echo urlencode($cus['customer_name']); ?>">

                            <div class="customer-name">
                                <?php echo htmlspecialchars($cus['customer_name']); ?>
                            </div>

                            <div class="customer-time">
                                <?php echo date("d/m/Y H:i", strtotime($cus['last_time'])); ?>
                            </div>

                        </a>
                    <?php } ?>
                <?php } else { ?>
                    <div class="empty-chat">
                        Chưa có khách nào nhắn tin.
                    </div>
                <?php } ?>
            </div>

            <div class="chat-panel">

                <?php if ($customer !== '') { ?>

                    <div class="chat-panel-header">
                        <span>Chat với: <?php echo htmlspecialchars($customer); ?></span>
                    </div>

                    <div class="chat-body" id="chatBody">
                        <?php if ($messages && mysqli_num_rows($messages) > 0) { ?>
                            <?php while ($msg = mysqli_fetch_assoc($messages)) { ?>
                                <div class="msg <?php echo $msg['sender_type'] == 'admin' ? 'admin-msg' : 'user-msg'; ?>">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>

                                    <div class="time">
                                        <?php echo date("d/m/Y H:i", strtotime($msg['created_at'])); ?>
                                    </div>
                                </div>
                            <?php } ?>
                        <?php } ?>
                    </div>

                    <form method="POST" class="chat-form">
                        <input type="hidden" name="customer_name" value="<?php echo htmlspecialchars($customer); ?>">

                        <input
                            type="text"
                            name="message"
                            placeholder="Nhập tin nhắn trả lời khách..."
                            required
                        >

                        <button type="submit">
                            Gửi
                        </button>
                    </form>

                <?php } else { ?>

                    <div class="empty-chat">
                        Chọn một khách ở cột trái để bắt đầu chat.
                    </div>

                <?php } ?>

            </div>

        </div>

    </main>

</div>

<script>
const chatBody = document.getElementById("chatBody");

if (chatBody) {
    chatBody.scrollTop = chatBody.scrollHeight;
}
</script>

</body>
</html>