
<?php

// ==========================================
// TT SHOP - PASSWORD RESET HELPERS
// ==========================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// 1. Nạp thư viện PHPMailer
require_once __DIR__ . '/vendor/autoload.php';


// ==========================================
// 2. TẠO ĐƯỜNG DẪN WEBSITE
// ==========================================

function resetBaseUrl()
{
    // Dùng địa chỉ cố định, không lấy từ HTTP_HOST
    // để tránh tạo liên kết khôi phục đến domain giả.

    return 'http://localhost/baitap';
}


// ==========================================
// 3. HÀM GỬI EMAIL KHÔI PHỤC
// ==========================================

function sendResetEmail($email, $resetLink)
{
    // File này đặt ngoài htdocs để bảo vệ mật khẩu SMTP
    $configFile = 'C:/xampp/ttshop-mail-config.php';

    if (!is_file($configFile)) {
        error_log('TT SHOP: Missing SMTP configuration.');
        return false;
    }

    $config = require $configFile;

    if (
        !is_array($config) ||
        empty($config['smtp_host']) ||
        empty($config['smtp_username']) ||
        empty($config['smtp_password'])
    ) {
        error_log('TT SHOP: Invalid SMTP configuration.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {

        // ==================================
        // CẤU HÌNH SMTP
        // ==================================

        $mail->isSMTP();

        $mail->Host = $config['smtp_host'];

        $mail->SMTPAuth = true;

        $mail->Username = $config['smtp_username'];

        $mail->Password = $config['smtp_password'];

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = 587;

        $mail->CharSet = 'UTF-8';

        $mail->Timeout = 15;

        // ==================================
        // NGƯỜI GỬI VÀ NGƯỜI NHẬN
        // ==================================

        $mail->setFrom(
            $config['smtp_username'],
            'TT SHOP'
        );

        $mail->addAddress($email);

        // ==================================
        // NỘI DUNG EMAIL
        // ==================================

        $mail->isHTML(true);

        $mail->Subject = 'Yêu cầu đặt lại mật khẩu - TT SHOP';

        $safeLink = htmlspecialchars(
            $resetLink,
            ENT_QUOTES,
            'UTF-8'
        );

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

                <h2 style="
                    color:#166534;
                    font-size:22px;
                ">
                    Đặt lại mật khẩu
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
                    Chúng tôi đã nhận được yêu cầu
                    đặt lại mật khẩu cho tài khoản
                    TT SHOP của bạn.
                </p>

                <p style="
                    font-size:15px;
                    color:#444;
                ">
                    Nhấn vào nút bên dưới để
                    tạo mật khẩu mới.
                </p>

                <div style="
                    text-align:center;
                    margin:35px 0;
                ">

                    <a href="' . $safeLink . '"
                       style="
                        display:inline-block;
                        padding:15px 28px;
                        background:#16a34a;
                        color:white;
                        text-decoration:none;
                        border-radius:8px;
                        font-weight:bold;
                        font-size:15px;
                       ">
                        ĐẶT LẠI MẬT KHẨU
                    </a>

                </div>

                <p style="
                    font-size:14px;
                    color:#666;
                    line-height:1.7;
                ">
                    Liên kết chỉ có hiệu lực
                    trong 30 phút và chỉ sử dụng
                    được một lần.
                </p>

                <p style="
                    font-size:14px;
                    color:#666;
                ">
                    Nếu bạn không yêu cầu đổi
                    mật khẩu, hãy bỏ qua email này.
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
            "TT SHOP - Đặt lại mật khẩu\n\n" .
            "Chúng tôi đã nhận được yêu cầu đặt lại mật khẩu.\n" .
            "Truy cập liên kết sau:\n" .
            $resetLink . "\n\n" .
            "Liên kết có hiệu lực trong 30 phút.\n" .
            "Nếu bạn không yêu cầu, hãy bỏ qua email này.";

        // ==================================
        // GỬI EMAIL
        // ==================================

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log(
            'TT SHOP SMTP error: ' . $mail->ErrorInfo
        );

        return false;
    }
}
