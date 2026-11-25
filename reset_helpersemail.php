<?php

// ==========================================
// TT SHOP - PASSWORD RESET HELPERS
// ==========================================

use PHPMailer\PHPMailer\PHPMailer;

$phpMailerAutoload = __DIR__ . '/vendor/autoload.php';
try {
    if (is_file($phpMailerAutoload)) {
        require_once $phpMailerAutoload;
    } else {
        $phpMailerFiles = array(
            __DIR__ . '/PHPMailer/src/Exception.php',
            __DIR__ . '/PHPMailer/src/PHPMailer.php',
            __DIR__ . '/PHPMailer/src/SMTP.php'
        );
        if (count(array_filter($phpMailerFiles, 'is_file')) === count($phpMailerFiles)) {
            foreach ($phpMailerFiles as $phpMailerFile) {
                require_once $phpMailerFile;
            }
        }
    }
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: Unable to load PHPMailer: %s', __FILE__, __LINE__, $exception->getMessage()));
}

function resetMailConfig()
{
    $configFile = __DIR__ . '/config_mail.php';
    if (!is_file($configFile)) {
        return array();
    }

    try {
        $config = require $configFile;
    } catch (Throwable $exception) {
        error_log(sprintf('%s:%d: Invalid mail configuration: %s', __FILE__, __LINE__, $exception->getMessage()));
        return array();
    }

    return is_array($config) ? $config : array();
}

function isLocalResetDevelopment($config = null)
{
    if (!is_array($config)) {
        $config = resetMailConfig();
    }

    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    return ($config['MAIL_DEV_MODE'] ?? false) === true
        && in_array($remoteAddress, array('127.0.0.1', '::1'), true);
}


// ==========================================
// 2. TẠO ĐƯỜNG DẪN WEBSITE
// ==========================================

function resetBaseUrl()
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/TTSHOP-main/TTSHOP-main/xulyquenmatkhau.php';
    $directory = str_replace('\\', '/', dirname($scriptName));
    $path = $directory === '/' || $directory === '.' ? '' : $directory;
    $host = $_SERVER['SERVER_NAME'] ?? 'localhost';
    if (preg_match('/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/i', $host) !== 1) {
        $host = 'localhost';
    }

    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $port = (int)($_SERVER['SERVER_PORT'] ?? ($isHttps ? 443 : 80));
    if (($isHttps && $port !== 443) || (!$isHttps && $port !== 80)) {
        $host .= ':' . $port;
    }

    return $scheme . '://' . $host . $path;
}


// ==========================================
// 3. HÀM GỬI EMAIL KHÔI PHỤC
// ==========================================

function sendResetEmail($email, $resetCode)
{
    if (!class_exists(PHPMailer::class)) {
        error_log(sprintf(
            '%s:%d: PHPMailer is missing. Expected vendor/autoload.php or PHPMailer/src files.',
            __FILE__,
            __LINE__
        ));
        return false;
    }

    $config = resetMailConfig();
    $username = trim((string)($config['MAIL_USERNAME'] ?? ''));
    $appPassword = (string)($config['MAIL_APP_PASSWORD'] ?? '');
    if (
        $username === '' ||
        $appPassword === ''
    ) {
        error_log(sprintf('%s:%d: Gmail SMTP credentials are not configured.', __FILE__, __LINE__));
        return false;
    }

    try {
        $mail = new PHPMailer(true);

        // ==================================
        // CẤU HÌNH SMTP
        // ==================================

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username = $username;

        $mail->Password = $appPassword;

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';

        $mail->Timeout = 15;
        $mail->SMTPDebug = 0;

        // ==================================
        // NGƯỜI GỬI VÀ NGƯỜI NHẬN
        // ==================================

        $fromName = trim((string)($config['MAIL_FROM_NAME'] ?? 'TT SHOP'));
        $mail->setFrom($username, $fromName !== '' ? $fromName : 'TT SHOP');

        $mail->addAddress($email);

        // ==================================
        // NỘI DUNG EMAIL
        // ==================================

        $mail->isHTML(true);

        $mail->Subject = 'Yêu cầu đặt lại mật khẩu - TT SHOP';

        $safeCode = htmlspecialchars($resetCode, ENT_QUOTES, 'UTF-8');

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
        </head>

        <body style="
            margin:0;
            padding:30px 15px;
            background:#f0fdf4;
            font-family:Arial,sans-serif;
        ">

            <div style="
                max-width:550px;
                margin:auto;
                background:#ffffff;
                border-radius:12px;
                padding:35px;
                border:1px solid #dcfce7;
            ">

                <h1 style="
                    color:#15803d;
                    text-align:center;
                    margin-bottom:25px;
                ">
                    TT SHOP
                </h1>

                <h2 style="color:#166534;font-size:22px">
                    Mã xác minh đặt lại mật khẩu
                </h2>

                <p style="
                    font-size:15px;
                    line-height:1.8;
                    color:#444;
                ">
                    Xin chào,
                </p>

                <p style="
                    font-size:15px;
                    line-height:1.8;
                    color:#444;
                ">
                    Chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản TT SHOP của bạn.
                </p>

                <p style="font-size:15px;color:#444">
                    Quay lại trình duyệt đã gửi yêu cầu và nhập mã này trên trang đặt lại mật khẩu:
                </p>

                <div style="text-align:center;margin:28px 0;padding:18px;background:#f0fdf4;border-radius:8px">
                    <strong style="font-size:32px;letter-spacing:8px;color:#166534">' . $safeCode . '</strong>
                </div>

                <p style="font-size:14px;color:#666;line-height:1.7">
                    Mã có hiệu lực trong 10 phút, chỉ dùng được một lần và tối đa 5 lần nhập.
                    Nếu bạn không yêu cầu, hãy bỏ qua email này.
                </p>

                <hr style="
                    border:0;
                    border-top:1px solid #e5e7eb;
                    margin:25px 0;
                ">

                <p style="
                    font-size:12px;
                    color:#999;
                    text-align:center;
                ">
                    Email được gửi tự động từ TT SHOP.
                    Vui lòng không trả lời email này.
                </p>

            </div>

        </body>
        </html>';

        // Nội dung thay thế cho ứng dụng không hỗ trợ HTML
        $mail->AltBody =
            "TT SHOP - Mã xác minh đặt lại mật khẩu\n\n" .
            "Mã xác minh của bạn: " . $resetCode . "\n\n" .
            "Mã có hiệu lực trong 10 phút, chỉ dùng được một lần và tối đa 5 lần nhập.\n" .
            "Nếu bạn không yêu cầu, hãy bỏ qua email này.";

        // ==================================
        // GỬI EMAIL
        // ==================================

        $mail->send();

        return true;

    } catch (Throwable $e) {
        $mailError = isset($mail) ? $mail->ErrorInfo : '';
        error_log(sprintf(
            '%s:%d: Gmail SMTP send failed: %s | PHPMailer: %s',
            __FILE__,
            __LINE__,
            $e->getMessage(),
            $mailError
        ));

        return false;
    }
}
