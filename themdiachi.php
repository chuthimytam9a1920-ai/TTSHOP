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

$userId = (int)$_SESSION['user_id'];
$hoTen = trim($_POST['ho_ten'] ?? '');
$sdt = normalize_vn_phone($_POST['sdt'] ?? '');
$congTy = trim($_POST['cong_ty'] ?? '');
$diaChi = trim($_POST['dia_chi'] ?? '');
$quocGia = trim($_POST['quoc_gia'] ?? 'Vietnam');
$maZip = trim($_POST['ma_zip'] ?? '');
$macDinh = isset($_POST['mac_dinh']) ? 1 : 0;

if (!csrf_check($_POST['csrf_token'] ?? null)) {
    $_SESSION['account_field_errors'] = array('address_action' => validation_message('system'));
    header('Location: taikhoan.php');
    exit();
}

if (!is_valid_vn_phone($sdt)) {
    $_SESSION['account_field_errors'] = array('address_sdt' => validation_message('phone_invalid'));
    header('Location: taikhoan.php');
    exit();
}

try {
    if ($macDinh === 1) {
        $resetStatement = $conn->prepare('UPDATE diachi_khachhang SET mac_dinh = 0 WHERE user_id = ?');
        $resetStatement->bind_param('i', $userId);
        $resetStatement->execute();
        $resetStatement->close();
    }

    $statement = $conn->prepare(
        'INSERT INTO diachi_khachhang
         (user_id, ho_ten, so_dien_thoai, cong_ty, dia_chi, quoc_gia, ma_zip, mac_dinh)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->bind_param('issssssi', $userId, $hoTen, $sdt, $congTy, $diaChi, $quocGia, $maZip, $macDinh);
    $statement->execute();
    $statement->close();
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', __FILE__, __LINE__, $exception->getMessage()));
    $_SESSION['account_field_errors'] = array('address_sdt' => validation_message('system'));
}

header('Location: taikhoan.php');
exit();
