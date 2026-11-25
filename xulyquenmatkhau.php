<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('TT_SHOP_PASSWORD_RESET_FLOW')) {
    define('TT_SHOP_PASSWORD_RESET_FLOW', true);
}
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/reset_helpersemail.php';
require_once __DIR__ . '/validate_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quenmatkhau.php');
    exit();
}

$resetRequestStartedAt = microtime(true);

function redirectReset($type, $message, $recordRequest = false, $destination = 'quenmatkhau.php')
{
    global $resetRequestStartedAt;

    $minimumDuration = 0.8;
    if (isset($resetRequestStartedAt)) {
        $remaining = $minimumDuration - (microtime(true) - $resetRequestStartedAt);
        if ($remaining > 0) {
            usleep((int)($remaining * 1000000));
        }
    }

    if ($recordRequest) {
        $_SESSION['last_reset_request'] = time();
    }

    unset($_SESSION['reset_success'], $_SESSION['reset_error']);
    $_SESSION[$type] = $message;
    header('Location: ' . $destination);
    exit();
}

$csrfToken = $_POST['csrf_token'] ?? '';
$storedCsrfToken = $_SESSION['reset_csrf_token'] ?? '';
if (
    !is_string($csrfToken) ||
    !is_string($storedCsrfToken) ||
    $csrfToken === '' ||
    !hash_equals($storedCsrfToken, $csrfToken)
) {
    redirectReset('reset_error', validation_message('reset_csrf_expired'));
}
unset($_SESSION['reset_csrf_token']);

$emailInput = $_POST['email'] ?? '';
$email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
if ($email === '' || strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    unset($_SESSION['reset_pending_email'], $_SESSION['reset_dev_otp'], $_SESSION['reset_dev_link']);
    redirectReset('reset_error', validation_message('email_invalid'));
}

$now = time();
$requestHistory = $_SESSION['reset_request_history'] ?? array();
if (!is_array($requestHistory)) {
    $requestHistory = array();
}
$cutoff = $now - 900;
$requestHistory = array_values(array_filter($requestHistory, function ($timestamp) use ($cutoff) {
    return is_int($timestamp) && $timestamp >= $cutoff;
}));

$currentSql = '';
$userId = 0;
$tokenHash = '';
$transactionStarted = false;

try {
    $currentSql = 'DELETE FROM password_resets WHERE expires_at < NOW()';
    $cleanupExpired = $conn->prepare($currentSql);
    $cleanupExpired->execute();
    $cleanupExpired->close();

    if (count($requestHistory) >= 5) {
        $_SESSION['reset_request_history'] = $requestHistory;
        unset($_SESSION['reset_pending_email']);
        redirectReset('reset_error', validation_message('reset_too_many_requests'), true);
    }
    $requestHistory[] = $now;
    $_SESSION['reset_request_history'] = $requestHistory;
    $previousPendingEmail = $_SESSION['reset_pending_email'] ?? '';
    $preserveDevOtp = is_string($previousPendingEmail)
        && $previousPendingEmail === $email
        && isLocalResetDevelopment(resetMailConfig());
    if (!$preserveDevOtp) {
        unset($_SESSION['reset_dev_otp'], $_SESSION['reset_dev_link']);
    }
    $_SESSION['reset_pending_email'] = $email;

    $conn->begin_transaction();
    $transactionStarted = true;

    $currentSql = 'SELECT id, email FROM users WHERE email = ? LIMIT 1 FOR UPDATE';
    $userStatement = $conn->prepare($currentSql);
    $userStatement->bind_param('s', $email);
    $userStatement->execute();
    $user = $userStatement->get_result()->fetch_assoc();
    $userStatement->close();

    if (!$user) {
        $conn->commit();
        $transactionStarted = false;
        unset($_SESSION['reset_dev_otp'], $_SESSION['reset_dev_link']);
        redirectReset(
            'reset_success',
            validation_message('reset_email_sent'),
            true,
            'datlaimatkhau.php'
        );
    }

    $userId = (int)$user['id'];
    $currentSql = 'SELECT id FROM password_resets
        WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
        LIMIT 1 FOR UPDATE';
    $recentStatement = $conn->prepare($currentSql);
    $recentStatement->bind_param('i', $userId);
    $recentStatement->execute();
    $recentStatement->store_result();
    $hasRecentRequest = $recentStatement->num_rows > 0;
    $recentStatement->close();

    if ($hasRecentRequest) {
        $conn->commit();
        $transactionStarted = false;
        redirectReset(
            'reset_success',
            validation_message('reset_email_sent'),
            true,
            'datlaimatkhau.php'
        );
    }

    unset($_SESSION['reset_dev_otp'], $_SESSION['reset_dev_link']);
    $currentSql = 'DELETE FROM password_resets WHERE user_id = ?';
    $deleteStatement = $conn->prepare($currentSql);
    $deleteStatement->bind_param('i', $userId);
    $deleteStatement->execute();
    $deleteStatement->close();

    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $sessionId = session_id();
    if ($sessionId === '') {
        throw new RuntimeException('Không tạo được phiên để xác minh mã đặt lại mật khẩu.');
    }
    $tokenHash = hash_hmac('sha256', $otp, $sessionId);
    $attempts = 0;

    $currentSql = 'INSERT INTO password_resets (user_id, token_hash, expires_at, attempts)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), ?)';
    $insertStatement = $conn->prepare($currentSql);
    $insertStatement->bind_param('isi', $userId, $tokenHash, $attempts);
    if ($insertStatement->execute() !== true || $insertStatement->affected_rows !== 1) {
        throw new RuntimeException('Không lưu được mã xác minh đặt lại mật khẩu.');
    }
    $insertStatement->close();

    $conn->commit();
    $transactionStarted = false;

    if (isLocalResetDevelopment(resetMailConfig())) {
        $_SESSION['reset_dev_otp'] = $otp;
        $_SESSION['reset_dev_link'] = resetBaseUrl() . '/datlaimatkhau.php';
        redirectReset(
            'reset_success',
            validation_message('reset_email_sent'),
            true,
            'datlaimatkhau.php'
        );
    }

    if (!sendResetEmail($user['email'], $otp)) {
        $currentSql = 'DELETE FROM password_resets WHERE user_id = ? AND token_hash = ?';
        try {
            $deleteFailedOtp = $conn->prepare($currentSql);
            $deleteFailedOtp->bind_param('is', $userId, $tokenHash);
            $deleteFailedOtp->execute();
            $deleteFailedOtp->close();
        } catch (Throwable $cleanupException) {
            error_log(sprintf(
                '%s:%d: Unable to remove unsent OTP: %s | SQL=%s',
                __FILE__,
                __LINE__,
                $cleanupException->getMessage(),
                $currentSql
            ));
        }

        unset($_SESSION['reset_pending_email']);
        error_log(sprintf('%s:%d: Reset OTP email delivery failed for user_id=%d.', __FILE__, __LINE__, $userId));
        redirectReset('reset_error', validation_message('reset_email_failed'), true);
    }

    redirectReset(
        'reset_success',
        validation_message('reset_email_sent'),
        true,
        'datlaimatkhau.php'
    );
} catch (Throwable $exception) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(sprintf('%s:%d: Rollback failed: %s', __FILE__, __LINE__, $rollbackException->getMessage()));
        }
    }

    if (!$transactionStarted && $userId > 0 && $tokenHash !== '') {
        try {
            $deleteOtpSql = 'DELETE FROM password_resets WHERE user_id = ? AND token_hash = ?';
            $deleteOtpStatement = $conn->prepare($deleteOtpSql);
            $deleteOtpStatement->bind_param('is', $userId, $tokenHash);
            $deleteOtpStatement->execute();
            $deleteOtpStatement->close();
        } catch (Throwable $cleanupException) {
            error_log(sprintf('%s:%d: OTP cleanup failed: %s', __FILE__, __LINE__, $cleanupException->getMessage()));
        }
    }

    unset($_SESSION['reset_pending_email']);
    error_log(sprintf(
        '%s:%d: %s | SQL=%s',
        __FILE__,
        __LINE__,
        $exception->getMessage(),
        $currentSql
    ));
    redirectReset('reset_error', validation_message('reset_system'), true);
}
