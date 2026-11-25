<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "connect.php";

$message = trim($_POST['message'] ?? '');
$sender_type = $_POST['sender_type'] ?? 'user';
$customer_name = trim($_POST['customer_name'] ?? '');

if ($message == '') {
    exit;
}

if (!in_array($sender_type, ['user', 'admin'])) {
    $sender_type = 'user';
}

if ($customer_name == '') {
    $customer_name = trim(($_SESSION['user_ho'] ?? '') . ' ' . ($_SESSION['user_name'] ?? ''));

    if ($customer_name == '') {
        $customer_name = 'Khách';
    }
}

$sql = "
    INSERT INTO chat_messages (
        customer_name,
        sender_type,
        message
    )
    VALUES (
        ?,
        ?,
        ?
    )
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "sss", $customer_name, $sender_type, $message);
mysqli_stmt_execute($stmt);

echo "success";
?>