
<?php
// ap_dung_magiamgia.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/voucher_helpers.php';


// 1. Hàm trả kết quả JSON

function voucher_json(int $http, array $data): void
{
    http_response_code($http);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


// 2. Xóa mã giảm giá khỏi SESSION

function voucher_clear_session(): void
{
    unset(
        $_SESSION['voucher_code'],
        $_SESSION['voucher_user_id']
    );
}


// 3. Lấy lại giá sản phẩm từ database

function voucher_refresh_cart(mysqli $conn): int
{
    $cart = $_SESSION['cart'] ?? [];

    if (!is_array($cart)) {
        throw new DomainException(
            'Giỏ hàng không hợp lệ.'
        );
    }

    $subtotal = 0;

    $updated = [];

    foreach ($cart as $key => $item) {

        if (!is_array($item)) {
            throw new DomainException(
                'Dữ liệu giỏ hàng không hợp lệ.'
            );
        }

        $id = (string)$key;

        // Kiểm tra số lượng sản phẩm

        $qty = filter_var(
            $item['qty'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 2147483647
                ]
            ]
        );

        if ($qty === false) {
            throw new DomainException(
                'Số lượng sản phẩm không hợp lệ.'
            );
        }

        // Xác định bảng chứa sản phẩm

        if (
            preg_match(
                '/^cat_([1-9][0-9]{0,9})$/D',
                $id,
                $m
            )
        ) {

            $table = 'category_products';

            $productId = (int)$m[1];

        } elseif (
            preg_match(
                '/^[1-9][0-9]{0,9}$/D',
                $id
            )
        ) {

            $table = 'products';

            $productId = (int)$id;

        } else {

            throw new DomainException(
                'Mã sản phẩm trong giỏ hàng không hợp lệ.'
            );
        }

        if ($productId > 2147483647) {
            throw new DomainException(
                'Mã sản phẩm không hợp lệ.'
            );
        }

        // Lấy thông tin sản phẩm thực tế

        $stmt = $conn->prepare(
            "SELECT
                name,
                price,
                old_price,
                image

            FROM `$table`

            WHERE id = ?"
        );

        try {

            $stmt->bind_param('i', $productId);

            $stmt->execute();

            $product = $stmt
                ->get_result()
                ->fetch_assoc();

        } finally {

            $stmt->close();
        }

        // Kiểm tra sản phẩm còn tồn tại

        if (
            !$product
            || (int)$product['price'] <= 0
        ) {

            throw new DomainException(
                'Có sản phẩm không còn bán hoặc giá không hợp lệ. Vui lòng cập nhật giỏ hàng.'
            );
        }

        $price = (int)$product['price'];

        // Kiểm tra giới hạn giá trị đơn hàng

        if (
            $price > intdiv(
                2147483647 - 30000 - $subtotal,
                $qty
            )
        ) {

            throw new DomainException(
                'Tổng giá trị đơn hàng vượt giới hạn.'
            );
        }

        $subtotal += $price * $qty;

        // Cập nhật thông tin giỏ hàng từ database

        $item['id'] = $id;

        $item['qty'] = $qty;

        $item['name'] = $product['name'];

        $item['price'] = $price;

        $item['old_price'] =
            (int)$product['old_price'];

        $item['img'] = $table === 'products'
            ? 'images/' . $product['image']
            : $product['image'];

        $updated[$key] = $item;
    }

    $_SESSION['cart'] = $updated;

    return $subtotal;
}


// 4. Chuẩn hóa dữ liệu trả về AJAX

function voucher_response(
    array $result,
    ?string $code,
    int $http = 200
): void
{
    voucher_json($http, [

        'status' => $result['ok']
            ? 'success'
            : 'error',

        'ok' => $result['ok'],

        'message' => $result['message'],

        'applied' => $code !== null,

        'code' => $code,

        'tier' => $result['tier'],

        'subtotal' => $result['subtotal'],

        'discount' => $result['discount'],

        'shipping_discount' =>
            $result['shipping_discount'],

        'shipping' => $result['shipping'],

        'total' => $result['total']
    ]);
}


// 5. Kiểm tra phương thức gửi yêu cầu

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {

    header('Allow: POST');

    voucher_json(405, [

        'status' => 'error',

        'ok' => false,

        'message' => 'Phương thức yêu cầu không hợp lệ.'
    ]);
}


// 6. Kiểm tra đăng nhập

$userId = (int)($_SESSION['user_id'] ?? 0);

if (
    ($_SESSION['is_login'] ?? false) !== true
    || $userId <= 0
) {

    voucher_json(401, [

        'status' => 'error',

        'ok' => false,

        'message' =>
            'Bạn cần đăng nhập để sử dụng mã giảm giá.'
    ]);
}


// 7. Kiểm tra bảo mật CSRF

if (!csrf_check($_POST['csrf_token'] ?? null)) {

    voucher_json(403, [

        'status' => 'error',

        'ok' => false,

        'message' =>
            'Phiên xác thực không hợp lệ. Vui lòng tải lại trang.'
    ]);
}


// 8. Kiểm tra hành động của khách hàng

$action = $_POST['action'] ?? '';

if (
    !in_array(
        $action,
        ['apply', 'remove', 'status'],
        true
    )
) {

    voucher_json(400, [

        'status' => 'error',

        'ok' => false,

        'message' => 'Thao tác không hợp lệ.'
    ]);
}


// 9. Kiểm tra dữ liệu mã giảm giá

$inputCode = $_POST['code'] ?? '';

if (
    $action === 'apply'
    && (
        !is_string($inputCode)
        || strlen($inputCode) > 100
    )
) {

    voucher_json(400, [

        'status' => 'error',

        'ok' => false,

        'message' => 'Mã giảm giá không hợp lệ.'
    ]);
}


// 10. Bắt đầu xử lý mã giảm giá

try {

    mysqli_report(
        MYSQLI_REPORT_ERROR
        | MYSQLI_REPORT_STRICT
    );

    // Đảm bảo lỗi kết nối trả về JSON

    ob_start(
        static function (string $body): string {

            return json_encode([

                'status' => 'error',

                'ok' => false,

                'message' =>
                    'Không thể kết nối database.'

            ], JSON_UNESCAPED_UNICODE);
        }
    );

    try {

        require __DIR__ . '/connect.php';

    } finally {

        ob_end_clean();
    }


    // 11. Kiểm tra tài khoản trong database

    $stmt = $conn->prepare(
        'SELECT email FROM users WHERE id = ?'
    );

    try {

        $stmt->bind_param('i', $userId);

        $stmt->execute();

        $account = $stmt
            ->get_result()
            ->fetch_assoc();

    } finally {

        $stmt->close();
    }

    $email = $_SESSION['user_email'] ?? '';

    if (
        !$account
        || !is_string($email)
        || $email === ''
        || strcasecmp(
            $email,
            $account['email']
        ) !== 0
    ) {

        voucher_clear_session();

        voucher_json(401, [

            'status' => 'error',

            'ok' => false,

            'message' =>
                'Tài khoản không hợp lệ. Vui lòng đăng nhập lại.'
        ]);
    }


    // 12. Không dùng mã của tài khoản khác

    if (
        (int)($_SESSION['voucher_user_id'] ?? 0)
        !== $userId
    ) {

        voucher_clear_session();
    }


    // 13. Xóa mã nếu khách hàng chọn gỡ mã

    if ($action === 'remove') {

        voucher_clear_session();
    }


    // 14. Tính lại tổng tiền giỏ hàng

    $subtotal = voucher_refresh_cart($conn);

    $shipping = empty($_SESSION['cart'])
        ? 0
        : 30000;


    // 15. Kiểm tra giỏ hàng trống

    if (empty($_SESSION['cart'])) {

        voucher_clear_session();

        $result = voucher_validate(

            $conn,

            $userId,

            '',

            0,

            0
        );

        $result['ok'] = $action !== 'apply';

        $result['message'] = 'Giỏ hàng đang trống.';

        voucher_response(

            $result,

            null,

            $result['ok'] ? 200 : 422
        );
    }


    // 16. Xử lý gỡ mã giảm giá

    if ($action === 'remove') {

        $result = voucher_validate(

            $conn,

            $userId,

            '',

            $subtotal,

            $shipping
        );

        $result['message'] = 'Đã gỡ mã giảm giá.';

        voucher_response($result, null);
    }


    // 17. Lấy mã cần kiểm tra

    $code = $action === 'apply'

        ? voucher_normalize($inputCode)

        : (string)($_SESSION['voucher_code'] ?? '');


    // 18. Kiểm tra điều kiện sử dụng voucher

    $result = voucher_validate(

        $conn,

        $userId,

        $code,

        $subtotal,

        $shipping
    );


    // Kiểm tra trường hợp không nhập mã

    if (
        $action === 'apply'
        && $code === ''
    ) {

        $result['ok'] = false;

        $result['message'] =
            'Vui lòng nhập mã giảm giá.';
    }


    // 19. Nếu mã không hợp lệ

    if (!$result['ok']) {

        voucher_clear_session();

        voucher_response(

            $result,

            null,

            422
        );
    }


    // 20. Nếu mã giảm giá hợp lệ

    if ($result['voucher'] !== null) {

        $code = $result['voucher']['ma_code'];

        // Lưu mã và chủ tài khoản vào SESSION

        $_SESSION['voucher_code'] = $code;

        $_SESSION['voucher_user_id'] = $userId;

        voucher_response(

            $result,

            $code
        );
    }


    // 21. Không sử dụng mã giảm giá

    voucher_clear_session();

    voucher_response(

        $result,

        null
    );


} catch (DomainException $e) {

    voucher_clear_session();

    voucher_json(422, [

        'status' => 'error',

        'ok' => false,

        'applied' => false,

        'code' => null,

        'message' => $e->getMessage()
    ]);


} catch (Throwable $e) {

    // Ghi lỗi hệ thống vào nhật ký PHP

    error_log(
        'TT SHOP voucher: ' . $e->getMessage()
    );

    voucher_json(500, [

        'status' => 'error',

        'ok' => false,

        'message' =>
            'Không thể xử lý mã giảm giá. Vui lòng thử lại sau.'
    ]);
}
