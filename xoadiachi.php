<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/validate_helpers.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['account_field_errors'] = array('address_action' => validation_message('system'));
    header('Location: taikhoan.php');
    exit();
}

$rawAddressId = $_POST['id'] ?? null;
$addressId = is_scalar($rawAddressId)
    ? filter_var((string)$rawAddressId, FILTER_VALIDATE_INT)
    : false;
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!csrf_check($_POST['csrf_token'] ?? null) || !$addressId || $userId <= 0) {
    $_SESSION['account_field_errors'] = array('address_action' => validation_message('system'));
    header('Location: taikhoan.php');
    exit();
}

try {
    $statement = $conn->prepare('DELETE FROM diachi_khachhang WHERE id = ? AND user_id = ?');
    $statement->bind_param('ii', $addressId, $userId);
    $statement->execute();
    $deletedRows = $statement->affected_rows;
    $statement->close();

    if ($deletedRows !== 1) {
        $_SESSION['account_field_errors'] = array('address_action' => validation_message('system'));
    }
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', __FILE__, __LINE__, $exception->getMessage()));
    $_SESSION['account_field_errors'] = array('address_action' => validation_message('system'));
}

header('Location: taikhoan.php');
exit();
