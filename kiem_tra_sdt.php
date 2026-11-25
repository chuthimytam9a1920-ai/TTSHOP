<?php
define('TT_SHOP_JSON_REQUEST', true);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/validate_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('trung' => false));
    exit();
}

$phone = normalize_vn_phone($_POST['sdt'] ?? '');
if (!is_valid_vn_phone($phone)) {
    echo json_encode(array('trung' => false));
    exit();
}

try {
    echo json_encode(array('trung' => is_phone_in_use($conn, $phone)));
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', __FILE__, __LINE__, $exception->getMessage()));
    http_response_code(503);
    echo json_encode(array('trung' => false));
}
