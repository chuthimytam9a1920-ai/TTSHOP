<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "ttshop";
$port = 3306;
$systemErrorMessage = defined('TT_SHOP_PASSWORD_RESET_FLOW')
    ? 'Hệ thống tạm thời không thể xử lý yêu cầu.'
    : 'Hệ thống đang bận, vui lòng thử lại sau.';

$conn = mysqli_connect(
    $servername,
    $username,
    $password,
    $dbname,
    $port
);

if (!$conn) {
    error_log("TT SHOP database connection failed: " . mysqli_connect_error());
    http_response_code(503);
    if (defined('TT_SHOP_JSON_REQUEST')) {
        header('Content-Type: application/json; charset=utf-8');
        die(json_encode(array('trung' => false)));
    }
    die($systemErrorMessage);
}

mysqli_set_charset($conn, "utf8mb4");

try {
    $timezoneStatement = $conn->prepare("SET time_zone = '+07:00'");
    $timezoneStatement->execute();
    $timezoneStatement->close();
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: Unable to set database time zone: %s', __FILE__, __LINE__, $exception->getMessage()));
    http_response_code(503);
    die($systemErrorMessage);
}
