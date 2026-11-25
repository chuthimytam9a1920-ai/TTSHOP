<?php
include "connect.php";

$customer = trim($_GET['customer'] ?? '');

if ($customer == '') {
    exit;
}

$sql = "
    SELECT *
    FROM chat_messages
    WHERE customer_name = ?
    ORDER BY id ASC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "s", $customer);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {

    $class =
        $row['sender_type'] == 'admin'
        ? 'bot-message'
        : 'user-message';

    echo '
        <div class="'.$class.'">
            '.nl2br(htmlspecialchars($row['message'])).'
        </div>
    ';
}
?>