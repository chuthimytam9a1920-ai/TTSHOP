        .grand-total { border-top: 1px solid #ddd; padding-top: 25px; margin-top: 25px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; font-size: 20px; }
        .grand-total strong { font-size: 30px; }
        .vnd { color: #999; font-size: 15px; margin-right: 8px; }
        @media (max-width: 900px) {
            .checkout-page { grid-template-columns: 1fr; }
            .checkout-left, .checkout-right { padding: 30px 20px; }
            .complete-btn { width: 100%; }
            .product-item { gap: 10px; }
        }
    </style>
</head>
<body>
<div class="checkout-page">
    <div class="checkout-left">
        <a href="index.php" class="logo">TT SHOP</a>
        <div class="breadcrumb">
            <a href="giohang.php" class="step-link">Giỏ hàng</a>
            <span class="arrow">›</span>
            <a href="thanhtoan.php" class="step-link">Thông tin giao hàng</a>
            <span class="arrow">›</span>
            <span class="step-active">Phương thức thanh toán</span>
        </div>
        <?php if ($checkoutError !== ''): ?>
            <p class="error-box" role="alert"><?= checkout_escape($checkoutError) ?></p>
        <?php endif; ?>

        <?php if ($voucherNotice !== ''): ?>
            <p class="warning-box" role="status"><?= checkout_escape($voucherNotice) ?></p>
        <?php endif; ?>
        <h2>Thông tin giao hàng</h2>
        <div class="delivery-info">
            <strong><?= checkout_escape($checkout['ho_ten']) ?></strong><br>
            <?= checkout_escape($checkout['sdt']) ?><br>
            <?= checkout_escape($checkout['dia_chi']) ?><br>
            <a href="thanhtoan.php">Thay đổi thông tin</a>
        </div>

        <form id="paymentForm" action="xuly_dathang.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= checkout_escape($token) ?>">
            <input type="hidden" name="review_id" value="<?= checkout_escape($reviewId) ?>">
            <input type="hidden" name="review_code" value="<?= checkout_escape($code) ?>">
            <input type="hidden" name="review_total" value="<?= (int)$totals['total'] ?>">
            <h2>Phương thức vận chuyển</h2>
            <div class="shipping-box">
                <div class="shipping-row">
                    <span class="check-square">✓</span>
                    <span>Giao hàng tận nơi</span>
                    <span class="ship-price"><?= !$ready ? "—" : ($totals['shipping'] === 0 ? "Miễn phí" : checkout_money($totals['shipping'])) ?></span>
                </div>
            </div>
            <h2>Phương thức thanh toán</h2>
            <div class="payment-box">
                <div class="payment-row">
                    <label>
                        <input type="radio" name="phuong_thuc" value="COD" checked required>
                        <i class="fas fa-money-bill-wave pay-icon" aria-hidden="true"></i>
                        Thanh toán khi giao hàng (COD)
                    </label>
                </div>
            </div>
            <div class="checkout-action">
                <a href="giohang.php" class="back-cart">Giỏ hàng</a>
                <button type="submit" id="completeBtn" class="complete-btn" <?= !$ready ? 'disabled' : '' ?>>Hoàn tất đơn hàng</button>
            </div>
        </form>
    </div>
    <div class="checkout-right">
        <?php foreach ($cart as $item):
            $name = $item['name'] ?? '';
            $img = $item['img'] ?? '';
            $price = (int)($item['price'] ?? 0);
            $qty = max(1, (int)($item['qty'] ?? 1));
            $thanhTien = $price * $qty;
        ?>
            <div class="product-item">
                <div class="product-img">
                    <img src="<?= checkout_escape($img) ?>" alt="<?= checkout_escape($name) ?>">
                    <span class="qty-badge"><?= $qty ?></span>
                </div>
                <div class="product-name"><?= checkout_escape($name) ?></div>
                <div class="product-price"><?= checkout_money($thanhTien) ?></div>
            </div>
        <?php endforeach; ?>
        <div class="voucher-summary">
            <strong><i class="fas fa-ticket" aria-hidden="true"></i> Mã giảm giá</strong><br>
            <?php if ($ready && $code !== ''): ?>
                <span class="saving"><?= checkout_escape($code) ?> — Đã áp dụng</span><br>
            <?php else: ?>
                <span>Chưa áp dụng mã giảm giá.</span><br>
            <?php endif; ?>
            <a href="thanhtoan.php">Chọn, thay đổi hoặc gỡ mã giảm giá</a>
        </div>
        <div class="loyal-title">Chương trình khách hàng thân thiết</div>
        <?php if ($member !== null): ?>
        <div class="member-box">
            <strong><i class="fas fa-award" aria-hidden="true"></i> Hạng <?= checkout_escape($member['tier']) ?></strong><br>
            <?= (int)$member['orders'] ?> đơn đã giao · Chi tiêu <?= checkout_money($member['spend']) ?>
        </div>
        <?php endif; ?>
        <div class="total-row"><span>Tạm tính</span><strong><?= $ready ? checkout_money($totals['subtotal']) : '—' ?></strong></div>
        <div class="total-row saving"><span>Giảm giá thành viên</span><strong><?= $ready ? '−'.checkout_money($totals['discount']) : '—' ?></strong></div>
        <div class="total-row"><span>Phí vận chuyển</span><strong><?= !$ready ? '—' : ($totals['shipping'] === 0 ? 'Miễn phí' : checkout_money($totals['shipping'])) ?></strong></div>
        <div class="total-row saving"><span>Tiết kiệm</span><strong><?= $ready ? checkout_money($totals['discount'] + $totals['shipping_discount']) : '—' ?></strong></div>
        <div class="grand-total">
            <span>Tổng cộng</span>
            <div><span class="vnd">VND</span><strong><?= $ready ? checkout_money($totals['total']) : '—' ?></strong></div>
        </div>
    </div>
</div>
<script>
    const ready = <?= $ready ? 'true' : 'false' ?>;
    const paymentForm = document.getElementById('paymentForm');
    const completeBtn = document.getElementById('completeBtn');
    paymentForm.addEventListener('submit', function (event) {
        if (!ready || completeBtn.disabled) {
            event.preventDefault();
            return;
        }
        completeBtn.disabled = true;
        completeBtn.textContent = 'Đang xử lý đơn hàng...';
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { window.location.reload(); return; }
        completeBtn.disabled = !ready;
        completeBtn.textContent = 'Hoàn tất đơn hàng';
    });
</script>
</body>
