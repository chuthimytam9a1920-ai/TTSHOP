<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['admin_login']);
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);

header("Location: login.php");
exit();
?>