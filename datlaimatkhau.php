<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/reset_helpersemail.php';
require_once __DIR__ . '/validate_helpers.php';

$pendingEmail = $_SESSION['reset_pending_email'] ?? '';
$hasPendingRequest = is_string($pendingEmail) && $pendingEmail !== '';
$pageError = $hasPendingRequest ? '' : validation_message('reset_otp_invalid');
$success = $_SESSION['reset_success'] ?? '';
$error = $_SESSION['reset_error'] ?? '';
$resetDevOtp = $_SESSION['reset_dev_otp'] ?? '';
$resetDevLink = $_SESSION['reset_dev_link'] ?? '';
$resetErrors = $_SESSION['reset_password_errors'] ?? array();

unset(
    $_SESSION['reset_success'],
    $_SESSION['reset_error'],
    $_SESSION['reset_password_errors']
);

$isLocalDevelopment = isLocalResetDevelopment();
if (!$isLocalDevelopment || !is_string($resetDevOtp)) {
    $resetDevOtp = '';
}
if (!$isLocalDevelopment || !is_string($resetDevLink)) {
    $resetDevLink = '';
}

if (
    $hasPendingRequest &&
    (!isset($_SESSION['reset_password_csrf_token']) ||
        !is_string($_SESSION['reset_password_csrf_token']) ||
        strlen($_SESSION['reset_password_csrf_token']) !== 64)
) {
    $_SESSION['reset_password_csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác minh mã - TT SHOP</title>
    <link rel="stylesheet" href="stylequenmatkhau.css">
    <style>
        .field-error{display:block;color:#c62828;font-size:.875rem;margin-top:4px}
    </style>
</head>
<body>
<main class="reset-container">
    <section class="reset-box">
        <div class="shop-logo"><a href="index.php">TT SHOP</a></div>
        <h1>XÁC MINH MÃ OTP</h1>

        <?php if ($success !== ''): ?>
            <p class="alert alert-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <p class="alert alert-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($pageError !== ''): ?>
            <p class="alert alert-error"><?php echo htmlspecialchars($pageError, ENT_QUOTES, 'UTF-8'); ?></p>
            <a href="quenmatkhau.php">Quay lại yêu cầu mã mới</a>
        <?php else: ?>
            <p class="description">
                Nhập mã 6 chữ số trong email và tạo mật khẩu mới. Mã có hiệu lực trong 10 phút.
            </p>

            <?php if ($resetDevOtp !== ''): ?>
                <div class="alert alert-dev">
                    [DEV] Mã OTP thử nghiệm:
                    <strong><?php echo htmlspecialchars($resetDevOtp, ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
            <?php endif; ?>
            <?php if ($resetDevLink !== ''): ?>
                <div class="alert alert-dev">
                    [DEV] Trang nhập mã:
                    <a href="<?php echo htmlspecialchars($resetDevLink, ENT_QUOTES, 'UTF-8'); ?>">Mở trang xác minh</a>
                </div>
            <?php endif; ?>

            <form
                action="xulydatlaimatkhau.php"
                method="POST"
                novalidate
                data-password-email="<?php echo htmlspecialchars($pendingEmail, ENT_QUOTES, 'UTF-8'); ?>"
            >
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['reset_password_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="otp_code">Mã xác minh <span>*</span></label>
                    <input
                        type="text"
                        id="otp_code"
                        name="otp_code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        placeholder="Nhập mã 6 chữ số"
                        data-validate="otp-code"
                        required
                    >
                    <?php if (!empty($resetErrors['otp_code'])): ?>
                        <small class="field-error" data-error-for="otp_code" aria-live="polite"><?php echo htmlspecialchars($resetErrors['otp_code'], ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="new_password">Mật khẩu mới <span>*</span></label>
                    <div class="password-input-wrap">
                        <input type="password" id="new_password" name="new_password" placeholder="Nhập mật khẩu mới" data-validate="strong-password" data-label="mật khẩu mới" autocomplete="new-password" required>
                        <button type="button" class="password-toggle" data-password-toggle aria-controls="new_password" aria-label="Hiện mật khẩu" aria-pressed="false">
                            <span class="password-toggle-icon" aria-hidden="true"></span>
                        </button>
                    </div>
                    <?php if (!empty($resetErrors['new_password'])): ?>
                        <small class="field-error" data-error-for="new_password" aria-live="polite"><?php echo htmlspecialchars($resetErrors['new_password'], ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php endif; ?>
                    <div data-password-warnings aria-live="polite"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Nhập lại mật khẩu mới <span>*</span></label>
                    <div class="password-input-wrap">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Nhập lại mật khẩu mới" data-validate="password-confirm" data-label="mật khẩu nhập lại" autocomplete="new-password" required>
                        <button type="button" class="password-toggle" data-password-toggle aria-controls="confirm_password" aria-label="Hiện mật khẩu" aria-pressed="false">
                            <span class="password-toggle-icon" aria-hidden="true"></span>
                        </button>
                    </div>
                    <?php if (!empty($resetErrors['confirm_password'])): ?>
                        <small class="field-error" data-error-for="confirm_password" aria-live="polite"><?php echo htmlspecialchars($resetErrors['confirm_password'], ENT_QUOTES, 'UTF-8'); ?></small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="reset-btn">Xác minh mã và đặt lại mật khẩu</button>
            </form>
        <?php endif; ?>
    </section>
</main>
<script src="validate.js"></script>
</body>
</html>
