<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$id = $_GET['id'] ?? '';
if ($id !== '' && isset($_SESSION['cart'][$id])) {
    unset($_SESSION['cart'][$id]);
}
header("Location: giohang.php");
exit();
?>