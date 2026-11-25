
<?php
// membership.php
// Hàm dùng chung cho chương trình thành viên TT SHOP.

// 1. Quy định xếp hạng thành viên

function membership_rules(): array
{
    return [
        'Member' => [
            'orders' => 0,
            'spend' => 0
        ],

        'Silver' => [
            'orders' => 3,
            'spend' => 500000
        ],

        'Gold' => [
            'orders' => 6,
            'spend' => 1500000
        ],

        'Platinum' => [
            'orders' => 11,
            'spend' => 3000000
        ],

        'Diamond' => [
            'orders' => 21,
            'spend' => 6000000
        ],
    ];
}


// 2. Xác định thứ bậc thành viên

function membership_rank(string $tier): int
{
    $rank = array_search(
        $tier,
        array_keys(membership_rules()),
        true
    );

    if ($rank === false) {
        throw new InvalidArgumentException(
            'Hạng thành viên không hợp lệ.'
        );
    }

    return $rank;
}


// 3. Kiểm tra quyền sử dụng mã giảm giá

function membership_can_use(
    string $currentTier,
    string $requiredTier
): bool
{
    return membership_rank($currentTier)
        >= membership_rank($requiredTier);
}


// 4. Tính hạng thành viên

function membership_calculate(
    int $orders,
    int $spend
): array
{
    $orders = max(0, $orders);
    $spend = max(0, $spend);

    $rules = membership_rules();

    $tiers = array_keys($rules);

    $tier = 'Member';

    // Xếp hạng theo số đơn đã giao
    // HOẶC tổng chi tiêu tích lũy.

    foreach ($rules as $name => $rule) {

        if (
            $orders >= $rule['orders']
            ||
            $spend >= $rule['spend']
        ) {
            $tier = $name;
        }
    }

    $rank = membership_rank($tier);

    // Xác định hạng tiếp theo

    $nextTier = $tiers[$rank + 1] ?? null;

    return [

        'tier' => $tier,

        'rank' => $rank,

        'orders' => $orders,

        'spend' => $spend,

        'next_tier' => $nextTier,

        'orders_needed' => $nextTier === null
            ? 0
            : max(
                0,
                $rules[$nextTier]['orders'] - $orders
            ),

        'spend_needed' => $nextTier === null
            ? 0
            : max(
                0,
                $rules[$nextTier]['spend'] - $spend
            ),
    ];
}


// 5. Lấy thông tin thành viên từ database

function membership_get(
    mysqli $conn,
    int $userId
): array
{
    // Kiểm tra ID khách hàng

    if ($userId <= 0) {

        throw new InvalidArgumentException(
            'ID khách hàng không hợp lệ.'
        );
    }

    // Chỉ tính những đơn hàng đã giao thành công

    $sql = "
        SELECT
            COUNT(*) AS so_lan_mua,
            COALESCE(
                SUM(tong_cong),
                0
            ) AS tong_chi_tieu

        FROM donhang

        WHERE user_id = ?
        AND trang_thai = 'Đã giao'
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new RuntimeException(
            'Không thể truy vấn hạng thành viên.'
        );
    }

    try {

        if (
            !$stmt->bind_param('i', $userId)
            ||
            !$stmt->execute()
        ) {

            throw new RuntimeException(
                'Không thể đọc dữ liệu thành viên.'
            );
        }

        if (
            !$stmt->bind_result($orders, $spend)
            ||
            $stmt->fetch() !== true
        ) {

            throw new RuntimeException(
                'Không thể lấy kết quả thành viên.'
            );
        }

        return membership_calculate(
            (int)$orders,
            (int)$spend
        );

    } finally {

        $stmt->close();
    }
}
