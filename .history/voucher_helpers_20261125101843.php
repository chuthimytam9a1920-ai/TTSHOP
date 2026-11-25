
<?php
require_once __DIR__ . '/membership.php';

// 1. Chuẩn hóa mã giảm giá

function voucher_normalize(string $code): string
{
    return strtoupper(trim($code));
}


// 2. Tìm mã giảm giá trong database

function voucher_find(
    mysqli $conn,
    string $code,
    bool $lock = false
): ?array
{
    $sql = "SELECT m.*, NOW() AS now_db
            FROM magiamgia m
            WHERE ma_code = ?";

    if ($lock) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Không thể đọc mã giảm giá.'
        );
    }

    try {

        if (
            !$stmt->bind_param('s', $code)
            || !$stmt->execute()
        ) {
            throw new RuntimeException(
                'Không thể đọc mã giảm giá.'
            );
        }

        $result = $stmt->get_result();

        if (!$result) {
            throw new RuntimeException(
                'Không thể lấy dữ liệu voucher.'
            );
        }

        return $result->fetch_assoc() ?: null;

    } finally {
        $stmt->close();
    }
}


// 3. Kiểm tra số lượt sử dụng mã giảm giá

function voucher_usage(
    mysqli $conn,
    int $voucherId,
    int $userId,
    bool $lock = false
): array
{
    // Đơn đã hủy không tính vào lượt sử dụng.

    $sql = "
        SELECT l.user_id

        FROM lichsu_magiamgia l

        JOIN donhang d
        ON d.id = l.donhang_id

        WHERE l.magiamgia_id = ?

        AND COALESCE(d.trang_thai, '')
        NOT IN ('Đã huỷ', 'Đã hủy')
    ";

    if ($lock) {
        $sql .= " FOR UPDATE";
    }

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new RuntimeException(
            'Không thể kiểm tra lượt sử dụng.'
        );
    }

    try {

        if (
            !$stmt->bind_param('i', $voucherId)
            || !$stmt->execute()
        ) {
            throw new RuntimeException(
                'Không thể kiểm tra lượt sử dụng.'
            );
        }

        $result = $stmt->get_result();

        if (!$result) {
            throw new RuntimeException(
                'Không thể đọc lịch sử voucher.'
            );
        }

        $total = 0;
        $mine = 0;

        while ($row = $result->fetch_assoc()) {

            $total++;

            if ((int)$row['user_id'] === $userId) {
                $mine++;
            }
        }

        return [
            'total' => $total,
            'user' => $mine
        ];

    } finally {
        $stmt->close();
    }
}


// 4. Kiểm tra điều kiện sử dụng mã giảm giá

function voucher_rule_error(
    array $v,
    string $tier,
    int $subtotal,
    string $now
): string
{
    // Kiểm tra trạng thái mã

    if ((int)$v['trang_thai'] !== 1) {
        return 'Mã giảm giá đã ngừng hoạt động.';
    }

    // Kiểm tra cấu hình mã

    if (
        (int)$v['phan_tram_giam'] < 1

        || (int)$v['phan_tram_giam'] > 100

        || (int)$v['giam_toi_da'] <= 0

        || (int)$v['don_toi_thieu'] < 0

        || !in_array(
            (int)$v['mien_phi_van_chuyen'],
            [0, 1],
            true
        )

        || !array_key_exists(
            $v['hang_toi_thieu'],
            membership_rules()
        )

        || (
            $v['gioi_han_su_dung'] !== null
            && (int)$v['gioi_han_su_dung'] <= 0
        )

        || (
            $v['gioi_han_moi_user'] !== null
            && (int)$v['gioi_han_moi_user'] <= 0
        )

        || (
            $v['ngay_bat_dau'] !== null
            && $v['ngay_ket_thuc'] !== null
            && $v['ngay_ket_thuc'] <= $v['ngay_bat_dau']
        )
    ) {
        return 'Mã giảm giá có cấu hình không hợp lệ.';
    }

    // Kiểm tra ngày bắt đầu

    if (
        $v['ngay_bat_dau'] !== null
        && $now < $v['ngay_bat_dau']
    ) {
        return 'Mã giảm giá chưa đến thời gian sử dụng.';
    }

    // Kiểm tra ngày hết hạn

    if (
        $v['ngay_ket_thuc'] !== null
        && $now >= $v['ngay_ket_thuc']
    ) {
        return 'Mã giảm giá đã hết hạn.';
    }

    // Kiểm tra hạng thành viên

    if (
        !membership_can_use(
            $tier,
            $v['hang_toi_thieu']
        )
    ) {
        return 'Mã dành cho thành viên từ hạng '
            . $v['hang_toi_thieu']
            . '.';
    }

    // Kiểm tra giá trị đơn hàng tối thiểu

    if (
        $subtotal <= 0
        || $subtotal < (int)$v['don_toi_thieu']
    ) {

        return 'Đơn hàng chưa đạt giá trị tối thiểu '
            . number_format(
                (int)$v['don_toi_thieu'],
                0,
                ',',
                '.'
            )
            . 'đ.';
    }

    // Không có lỗi

    return '';
}


// 5. Tính số tiền giảm giá và tổng thanh toán

function voucher_calculate(
    int $subtotal,
    int $shipping,
    ?array $v = null
): array
{
    // Kiểm tra giá trị tiền

    if (
        $subtotal < 0
        || $shipping < 0
        || $subtotal > 2147483647 - $shipping
    ) {
        throw new InvalidArgumentException(
            'Giá trị đơn hàng không hợp lệ.'
        );
    }

    $discount = 0;

    $shippingDiscount = 0;

    // Nếu có voucher hợp lệ

    if ($v !== null) {

        // Tính tiền giảm theo phần trăm
        // Không vượt giới hạn giảm tối đa

        $discount = min(

            $subtotal,

            (int)$v['giam_toi_da'],

            (int)floor(
                $subtotal
                * (int)$v['phan_tram_giam']
                / 100
            )
        );

        // Kiểm tra ưu đãi miễn phí vận chuyển

        $shippingDiscount =
            (int)$v['mien_phi_van_chuyen'] === 1
            ? $shipping
            : 0;
    }

    return [

        'subtotal' => $subtotal,

        'discount' => $discount,

        'shipping_discount' => $shippingDiscount,

        'shipping' => $shipping - $shippingDiscount,

        'total' => $subtotal
            - $discount
            + $shipping
            - $shippingDiscount
    ];
}


// 6. Kiểm tra toàn bộ điều kiện của voucher

function voucher_evaluate(
    mysqli $conn,
    array $v,
    int $userId,
    string $tier,
    int $subtotal,
    int $shipping,
    bool $lock = false
): array
{
    $error = voucher_rule_error(

        $v,

        $tier,

        $subtotal,

        $v['now_db']
    );

    // Kiểm tra lượt sử dụng nếu có giới hạn

    if (
        $error === ''
        && (
            $v['gioi_han_su_dung'] !== null
            || $v['gioi_han_moi_user'] !== null
        )
    ) {

        $usage = voucher_usage(

            $conn,

            (int)$v['id'],

            $userId,

            $lock
        );

        // Kiểm tra giới hạn toàn hệ thống

        if (
            $v['gioi_han_su_dung'] !== null

            && $usage['total']
                >= (int)$v['gioi_han_su_dung']
        ) {

            $error = 'Mã giảm giá đã hết lượt sử dụng.';

        // Kiểm tra giới hạn của từng khách hàng

        } elseif (
            $v['gioi_han_moi_user'] !== null

            && $usage['user']
                >= (int)$v['gioi_han_moi_user']
        ) {

            $error = 'Bạn đã sử dụng hết lượt của mã này.';
        }
    }

    return array_merge(

        [

            'ok' => $error === '',

            'message' => $error === ''
                ? 'Áp dụng mã giảm giá thành công!'
                : $error,

            'voucher' => $v,

            'tier' => $tier
        ],

        voucher_calculate(

            $subtotal,

            $shipping,

            $error === '' ? $v : null
        )
    );
}


// 7. Hàm chính kiểm tra mã giảm giá

function voucher_validate(
    mysqli $conn,
    int $userId,
    string $code,
    int $subtotal,
    int $shipping = 30000,
    bool $lock = false
): array
{
    // Chuẩn hóa mã

    $code = voucher_normalize($code);

    $v = null;

    // Kiểm tra định dạng mã

    if (
        $code !== ''
        && preg_match('/^[A-Z0-9_-]{1,50}$/D', $code)
    ) {

        $v = voucher_find(
            $conn,
            $code,
            $lock
        );
    }

    // Lấy hạng thành viên thực tế từ database

    $member = membership_get(
        $conn,
        $userId
    );

    $tier = $member['tier'];

    // Trường hợp không nhập mã
    // Hoặc mã không tồn tại

    if ($v === null) {

        return array_merge(

            [

                'ok' => $code === '',

                'message' => $code === ''
                    ? ''
                    : 'Mã giảm giá không tồn tại hoặc không hợp lệ.',

                'voucher' => null,

                'tier' => $tier
            ],

            voucher_calculate(
                $subtotal,
                $shipping
            )
        );
    }

    // Kiểm tra toàn bộ điều kiện sử dụng

    return voucher_evaluate(

        $conn,

        $v,

        $userId,

        $tier,

        $subtotal,

        $shipping,

        $lock
    );
}


// 8. Lấy danh sách voucher dành cho thành viên

function voucher_list(
    mysqli $conn,
    int $userId,
    int $subtotal,
    int $shipping = 30000
): array
{
    // Lấy thông tin hạng thành viên

    $member = membership_get(
        $conn,
        $userId
    );

    // Lấy các mã đang hoạt động

    $sql = "
        SELECT m.*, NOW() AS now_db

        FROM magiamgia m

        WHERE trang_thai = 1

        ORDER BY hang_toi_thieu, id
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new RuntimeException(
            'Không thể tải danh sách mã giảm giá.'
        );
    }

    $list = [];

    while ($v = $result->fetch_assoc()) {

        $list[] = voucher_evaluate(

            $conn,

            $v,

            $userId,

            $member['tier'],

            $subtotal,

            $shipping
        );
    }

    return $list;
}
