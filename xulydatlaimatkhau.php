<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('TT_SHOP_PASSWORD_RESET_FLOW')) {
    define('TT_SHOP_PASSWORD_RESET_FLOW', true);
}
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/validate_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quenmatkhau.php');
    exit();
}

function redirectOtpError($field, $message)
{
    unset($_SESSION['reset_success'], $_SESSION['reset_error']);
    $_SESSION['reset_password_errors'] = array($field => $message);
    header('Location: datlaimatkhau.php');
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
$storedCsrfToken = $_SESSION['reset_password_csrf_token'] ?? '';
if (
    !is_string($csrfToken) ||
    !is_string($storedCsrfToken) ||
    $csrfToken === '' ||
    !hash_equals($storedCsrfToken, $csrfToken)
) {
    unset($_SESSION['reset_password_csrf_token'], $_SESSION['reset_success']);
    $_SESSION['reset_error'] = validation_message('reset_csrf_expired');
    header('Location: quenmatkhau.php');
    exit();
}
unset($_SESSION['reset_password_csrf_token']);

$pendingEmail = $_SESSION['reset_pending_email'] ?? '';
if (!is_string($pendingEmail) || $pendingEmail === '') {
    $_SESSION['reset_error'] = validation_message('reset_otp_invalid');
    header('Location: quenmatkhau.php');
    exit();
}

$otpInput = $_POST['otp_code'] ?? '';
$otp = is_string($otpInput) ? trim($otpInput) : '';
if ($otp === '') {
    redirectOtpError('otp_code', validation_message('reset_otp_required'));
}
if (preg_match('/^[0-9]{6}$/', $otp) !== 1) {
    redirectOtpError('otp_code', validation_message('reset_otp_format'));
}

$newPasswordInput = $_POST['new_password'] ?? '';
$confirmPasswordInput = $_POST['confirm_password'] ?? '';
$newPassword = is_string($newPasswordInput) ? $newPasswordInput : '';
$confirmPassword = is_string($confirmPasswordInput) ? $confirmPasswordInput : '';
$transactionStarted = false;
$currentSql = '';

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $currentSql = 'SELECT id, email, matkhau FROM users WHERE email = ? LIMIT 1 FOR UPDATE';
    $userStatement = $conn->prepare($currentSql);
    $userStatement->bind_param('s', $pendingEmail);
    $userStatement->execute();
    $user = $userStatement->get_result()->fetch_assoc();
    $userStatement->close();

    if (!$user) {
        $conn->rollback();
        $transactionStarted = false;
        redirectOtpError('otp_code', validation_message('reset_otp_invalid'));
    }

    $userId = (int)$user['id'];
    $currentSql = 'SELECT id, token_hash, attempts
        FROM password_resets
        WHERE user_id = ? AND expires_at > NOW() AND used_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1 FOR UPDATE';
    $otpStatement = $conn->prepare($currentSql);
    $otpStatement->bind_param('i', $userId);
    $otpStatement->execute();
    $resetRecord = $otpStatement->get_result()->fetch_assoc();
    $otpStatement->close();

    if (!$resetRecord) {
        $conn->rollback();
        $transactionStarted = false;
        redirectOtpError('otp_code', validation_message('reset_otp_invalid'));
    }

    $expectedHash = hash_hmac('sha256', $otp, session_id());
    if (!hash_equals((string)$resetRecord['token_hash'], $expectedHash)) {
        $nextAttempts = (int)$resetRecord['attempts'] + 1;
        $currentSql = 'UPDATE password_resets
            SET attempts = ?, used_at = CASE WHEN ? >= 5 THEN NOW() ELSE used_at END
            WHERE id = ?';
        $attemptUpdate = $conn->prepare($currentSql);
        $attemptUpdate->bind_param('iii', $nextAttempts, $nextAttempts, $resetRecord['id']);
        if ($attemptUpdate->execute() !== true || $attemptUpdate->affected_rows !== 1) {
            throw new RuntimeException('Không cập nhật được số lần nhập mã xác minh.');
        }
        $attemptUpdate->close();
        $conn->commit();
        $transactionStarted = false;
        redirectOtpError('otp_code', validation_message('reset_otp_invalid'));
    }

    $errors = array();
    $passwordError = password_blocking_error($newPassword);
    if ($passwordError !== null) {
        $errors['new_password'] = $passwordError;
    }
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = validation_message('password_confirm');
    }
    if (!is_string($user['matkhau']) || $user['matkhau'] === '') {
        throw new RuntimeException('Tài khoản không có mật khẩu đã mã hóa hợp lệ.');
    }
    if (!$errors && password_verify($newPassword, $user['matkhau'])) {
        $errors['new_password'] = validation_message('reset_password_same');
    }
    if ($errors) {
        $conn->rollback();
        $transactionStarted = false;
        $_SESSION['reset_password_errors'] = $errors;
        header('Location: datlaimatkhau.php');
        exit();
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $currentSql = 'UPDATE users SET matkhau = ? WHERE id = ?';
    $update = $conn->prepare($currentSql);
    $update->bind_param('si', $passwordHash, $userId);
    if ($update->execute() !== true || $update->affected_rows !== 1) {
        throw new RuntimeException('Không cập nhật được mật khẩu người dùng.');
    }
    $update->close();

    $currentSql = 'DELETE FROM password_resets WHERE user_id = ?';
    $delete = $conn->prepare($currentSql);
    $delete->bind_param('i', $userId);
    if ($delete->execute() !== true || $delete->affected_rows < 1) {
        throw new RuntimeException('Không thể vô hiệu hóa mã xác minh.');
    }
    $delete->close();

    $conn->commit();
    $transactionStarted = false;

    session_regenerate_id(true);
    unset(
        $_SESSION['reset_pending_email'],
        $_SESSION['reset_password_errors'],
        $_SESSION['reset_password_csrf_token'],
        $_SESSION['reset_error'],
        $_SESSION['reset_dev_otp'],
        $_SESSION['reset_dev_link']
    );
    $_SESSION['reset_success'] = 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập bằng mật khẩu mới.';
    header('Location: dangnhap.php');
    exit();
} catch (Throwable $exception) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(sprintf('%s:%d: Rollback failed: %s', __FILE__, __LINE__, $rollbackException->getMessage()));
        }
    }

    error_log(sprintf(
        '%s:%d: %s | SQL=%s',
        __FILE__,
        __LINE__,
        $exception->getMessage(),
        $currentSql
    ));
    $_SESSION['reset_password_errors'] = array('new_password' => validation_message('reset_system'));
    header('Location: datlaimatkhau.php');
    exit();
}
