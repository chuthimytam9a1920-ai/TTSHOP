<?php
include "connect.php";

function showProducts($conn, $category_id, $title) {
    $sql = "SELECT * FROM products 
            WHERE category_id = '$category_id'
            ORDER BY id DESC
            LIMIT 8";

    $result = mysqli_query($conn, $sql);
?>

<section class="product-section" id="<?php echo $category_id; ?>">
    <h2><?php echo $title; ?></h2>

    <div class="product-list">

        <?php if ($result && mysqli_num_rows($result) > 0): ?>

            <?php while ($row = mysqli_fetch_assoc($result)): ?>

                <?php
                $discount = 0;

                if ($row['old_price'] > 0 && $row['old_price'] > $row['price']) {
                    $discount = floor((($row['old_price'] - $row['price']) / $row['old_price']) * 100);
                }

                $is_hot = ($row['sold_count'] >= 500) ? 1 : 0;
                $is_flash_sale = (isset($row['is_flash_sale']) && $row['is_flash_sale'] == 1) ? 1 : 0;
                $is_new = (isset($row['is_new']) && $row['is_new'] == 1) ? 1 : 0;
                ?>

                <div class="product-item">

                    <div class="product-img-box">

                        <div class="badge-group">
                            <?php if ($is_hot): ?>
                                <span class="badge-new badge-hot">🔥 HOT</span>                            
                            <?php endif; ?>

                            <?php if ($is_flash_sale): ?>
                                <span class="badge-new badge-flash">⚡ FLASH SALE</span>                            
                            <?php endif; ?>

                            <?php if ($is_new): ?>
                                <span class="badge-new badge-new-product">✨ NEW</span>                            
                            <?php endif; ?>
                        </div>

                        <img src="images/<?php echo $row['image']; ?>" alt="<?php echo $row['name']; ?>">
                    </div>

                    <div class="sold-badge">
                        📊 Đã bán <?php echo $row['sold_count']; ?>
                    </div>

                    <h3><?php echo $row['name']; ?></h3>

                    <div class="stars">
                        <?php echo str_repeat('★', $row['star_count']); ?>
                        <span>(<?php echo $row['review_count']; ?>)</span>
                    </div>

                    <p class="new-price">
                        <?php echo number_format($row['price'], 0, ',', '.'); ?>đ
                    </p>

                    <?php if ($row['old_price'] > $row['price']): ?>
                        <div class="price-sale">
                            <span class="old-price">
                                <?php echo number_format($row['old_price'], 0, ',', '.'); ?>đ
                            </span>

                            <?php if ($discount > 0): ?>
                                <span class="discount">
                                    -<?php echo $discount; ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="quantity-label">Số lượng:</div>

                    <div class="quantity-box">
                        <button type="button" class="qty-minus">-</button>
                        <input type="text" value="1" class="qty-input" readonly>
                        <button type="button" class="qty-plus">+</button>
                    </div>

                    <div class="product-actions">
                        <button 
                            type="button"
                            class="add-cart"
                            data-id="<?php echo $row['id']; ?>"
                            data-name="<?php echo htmlspecialchars($row['name']); ?>"
                            data-price="<?php echo $row['price']; ?>"
                            data-old-price="<?php echo $row['old_price']; ?>"
                            data-img="images/<?php echo $row['image']; ?>"
                        >
                            🛒 Thêm vào giỏ
                        </button>
<button 
    type="button"
    class="buy-now"
    data-id="<?php echo $row['id']; ?>"
    data-name="<?php echo htmlspecialchars($row['name']); ?>"
    data-price="<?php echo $row['price']; ?>"
    data-old-price="<?php echo $row['old_price']; ?>"
    data-img="images/<?php echo $row['image']; ?>"
>
    Mua ngay
</button>
                    </div>

                    <a href="chitietsanpham.php?id=<?php echo $row['id']; ?>" class="detail-link">
                        👁 Xem chi tiết
                    </a>

                </div> <?php endwhile; ?>

        <?php else: ?>

            <p class="empty-product">Đang cập nhật sản phẩm...</p>

        <?php endif; ?>

    </div>
</section>

<?php
}
?>

<?php
showProducts($conn, 'top-sale', 'TOP SALE');
showProducts($conn, 'vpp', 'Văn phòng phẩm doanh nghiệp');
showProducts($conn, 'vo-ghi', 'Vở ghi - sổ tay');
showProducts($conn, 'but-viet', 'Bút viết');
showProducts($conn, 'dung-cu', 'Dụng cụ học tập');
showProducts($conn, 'balo', 'Ba lô - Cặp sách');
showProducts($conn, 'may-tinh', 'Máy tính cầm tay');
?>

<style> 
.product-img-box {
    position: relative;
}

.badge-group {
    position: absolute;
    top: 14px;
    left: 14px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    z-index: 5;
}

.badge-new {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 800;
    letter-spacing: 0.4px;
    backdrop-filter: blur(8px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
    transition: 0.3s;
}
.badge-new:hover {
    transform: translateY(-2px) scale(1.04);
}

.badge-hot {
    background: linear-gradient(135deg, #ff4d4d, #ff0000);
    color: white;
    animation: hotPulse 1s infinite alternate;
}
@keyframes hotPulse {
    from {
        box-shadow: 0 0 8px rgba(255,0,0,0.3);
    }
    to {
        box-shadow: 0 0 18px rgba(255,0,0,0.8);
    }
}
.badge-new-product {
    background: linear-gradient(135deg, #dff8eb, #b7ffd6);
    color: #008a4c;
}

.badge-flash {
    background: linear-gradient(135deg, #ff0303, #ff0303);
    color: white;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 30px;
    box-shadow: 0 6px 18px rgba(236, 193, 64, 0.35);
    animation: flashGlow 1s infinite alternate;
}

.lightning {
    font-size: 17px;
    animation: lightningShake 0.5s infinite alternate;
}
@keyframes lightningShake {
    from {
        transform: rotate(-10deg);
    }
    to {
        transform: rotate(10deg);
    }
}
.badge-new-product {
    background: #dff5eb;
    color: #07965f;
} 
</style>