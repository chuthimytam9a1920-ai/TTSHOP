<?php
// Giao diện thông tin dùng chung cho 10 liên kết trong footer.
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ttInfoPages = [
    'huongdanmuahang' => ['Hướng dẫn mua hàng', 'fa-cart-shopping', 'Mua sắm đồ dùng học tập dễ dàng, chỉ với vài bước.'],
    'huongdanthanhtoan' => ['Hướng dẫn thanh toán', 'fa-money-bill-wave', 'Kiểm tra chi phí và hoàn tất đơn hàng tại TT SHOP.'],
    'chinhsachgiaohang' => ['Chính sách giao hàng', 'fa-truck-fast', 'Thông tin vận chuyển và theo dõi đơn hàng.'],
    'doitrahoantien' => ['Chính sách đổi trả & hoàn tiền', 'fa-rotate-left', 'Hướng dẫn xử lý khi sản phẩm gặp vấn đề.'],
    'khachhangthanthiet' => ['Khách hàng thân thiết', 'fa-award', 'Tích lũy đơn hàng, nâng hạng và khám phá ưu đãi.'],
    'gioithieu' => ['Giới thiệu', 'fa-store', 'TT SHOP – đồng hành cùng hành trình học tập của bạn.'],
    'sanphamhocbang' => ['Sản phẩm học tập', 'fa-book-open', 'Khám phá các nhóm sản phẩm của TT SHOP.'],
    'baomat' => ['Chính sách bảo mật', 'fa-shield-halved', 'Thông tin về dữ liệu được sử dụng khi mua sắm.'],
    'lienhe' => ['Thông tin liên hệ', 'fa-headset', 'Kết nối với TT SHOP qua các kênh hỗ trợ.'],
    'phananh' => ['Phản ánh chất lượng sản phẩm', 'fa-comment-dots', 'Gửi thông tin về sản phẩm cần được hỗ trợ.'],
];
if (!isset($ttInfoPage, $ttInfoPages[$ttInfoPage])) { http_response_code(404); exit('Không tìm thấy trang.'); }
function ttinfo_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$ttInfoCurrent = $ttInfoPages[$ttInfoPage];
$ttInfoGroups = ['Hỗ trợ khách hàng' => array_slice($ttInfoPages, 0, 5, true), 'Về TT SHOP' => array_slice($ttInfoPages, 5, 5, true)];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= ttinfo_e($ttInfoCurrent[0]) ?> - TT SHOP</title>
    <link rel="stylesheet" href="style.css?v=10">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="footer_info.css?v=1">
</head>
<body class="tt-info-body">
<?php require __DIR__ . '/header.php'; ?>
<main class="tt-info-wrap">
    <nav class="tt-info-crumb" aria-label="Đường dẫn"><a href="index.php">Trang chủ</a> <span>›</span> <span><?= ttinfo_e($ttInfoCurrent[0]) ?></span></nav>
    <div class="tt-info-hero">
        <div class="tt-info-hero-icon"><i class="fas <?= ttinfo_e($ttInfoCurrent[1]) ?>" aria-hidden="true"></i></div>
        <div><p class="tt-info-kicker">TT SHOP / TRUNG TÂM HỖ TRỢ</p><h1><?= ttinfo_e($ttInfoCurrent[0]) ?></h1><p><?= ttinfo_e($ttInfoCurrent[2]) ?></p></div>
    </div>
    <div class="tt-info-layout">
        <aside class="tt-info-sidebar" aria-label="Danh mục thông tin">
            <?php foreach ($ttInfoGroups as $group => $items): ?>
                <div class="tt-info-menu"><h2><?= ttinfo_e($group) ?></h2>
                    <?php foreach ($items as $slug => $item): ?>
                    <a href="<?= ttinfo_e($slug) ?>.php" <?= $slug === $ttInfoPage ? 'class="active" aria-current="page"' : '' ?>><i class="fas <?= ttinfo_e($item[1]) ?>" aria-hidden="true"></i><span><?= ttinfo_e($item[0]) ?></span></a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            <div class="tt-info-side-help"><i class="fas fa-phone-volume" aria-hidden="true"></i><div><strong>Cần hỗ trợ thêm?</strong><a href="tel:1900888999">1900 888 999</a></div></div>
        </aside>
        <article class="tt-info-content">
<?php switch ($ttInfoPage): case 'huongdanmuahang': ?>
            <h2>Các bước đặt hàng</h2>
            <ol class="tt-info-steps">
                <li><strong>Tìm sản phẩm</strong><p>Sử dụng thanh tìm kiếm hoặc menu danh mục để chọn đồ dùng học tập. Xem hình ảnh, giá và thông tin sản phẩm trước khi mua.</p></li>
                <li><strong>Thêm vào giỏ hàng</strong><p>Chọn số lượng, bấm thêm vào giỏ hàng hoặc mua ngay. Mở giỏ hàng để kiểm tra và điều chỉnh số lượng.</p></li>
                <li><strong>Đăng nhập và nhập thông tin giao hàng</strong><p>Đăng nhập hoặc đăng ký tài khoản nếu cần. Kiểm tra họ tên người nhận, số điện thoại và địa chỉ nhận hàng.</p></li>
                <li><strong>Áp dụng ưu đãi và chọn phương thức thanh toán</strong><p>Nhập mã giảm giá nếu đủ điều kiện. Kiểm tra tiền hàng, phí vận chuyển, số tiền giảm và tổng thanh toán. Chọn COD.</p></li>
                <li><strong>Hoàn tất và theo dõi đơn hàng</strong><p>Xác nhận đặt hàng. Vào Tài khoản → Danh sách đơn hàng để xem trạng thái và chi tiết đơn.</p></li>
            </ol>
            <div class="tt-info-actions"><a class="tt-info-btn" href="sanphamhocbang.php">Khám phá sản phẩm</a><a class="tt-info-btn secondary" href="giohang.php">Xem giỏ hàng</a></div>
<?php break; case 'huongdanthanhtoan': ?>
            <h2>Phương thức thanh toán hiện có</h2>
            <div class="tt-info-highlight"><i class="fas fa-money-bill-wave" aria-hidden="true"></i><div><strong>Thanh toán khi giao hàng (COD)</strong><p>Khách hàng thanh toán số tiền cần trả khi nhận hàng. Website hiện chưa có cổng thanh toán trực tuyến.</p></div></div>
            <h2>Quy trình thanh toán</h2>
            <ol><li>Kiểm tra sản phẩm và số lượng trong giỏ hàng.</li><li>Xác nhận thông tin nhận hàng và áp dụng voucher phù hợp.</li><li>Kiểm tra tổng tiền sau giảm giá và phí giao hàng.</li><li>Chọn COD, bấm Hoàn tất đơn hàng và lưu mã đơn để theo dõi.</li></ol>
            <h2>Lưu ý khi dùng voucher</h2><p>Mỗi mã có điều kiện riêng về hạng thành viên, giá trị đơn tối thiểu, mức giảm tối đa, thời hạn và lượt sử dụng. Số tiền hiển thị ở bước xác nhận cuối cùng là số tiền cần kiểm tra trước khi đặt hàng.</p>
            <div class="tt-info-actions"><a class="tt-info-btn" href="giohang.php">Đến giỏ hàng</a><a class="tt-info-btn secondary" href="khachhangthanthiet.php">Xem chương trình thành viên</a></div>
<?php break; case 'chinhsachgiaohang': ?>
            <div class="tt-info-facts"><div><i class="fas fa-map-location-dot"></i><span>Phạm vi dự kiến</span><strong>Toàn quốc</strong></div><div><i class="fas fa-clock"></i><span>Thời gian dự kiến</span><strong>2–5 ngày</strong></div><div><i class="fas fa-truck"></i><span>Phí cơ bản</span><strong>30.000 đ</strong></div></div>
            <h2>Phí và thời gian giao hàng</h2><p>Phí vận chuyển cơ bản đang áp dụng trong quy trình thanh toán là 30.000 đồng. Voucher miễn phí vận chuyển có thể làm giảm khoản phí này nếu đáp ứng điều kiện. Thời gian giao dự kiến theo thông tin hỗ trợ của shop là 2–5 ngày tùy khu vực; không phải cam kết giao vào một ngày cụ thể.</p>
            <h2>Theo dõi đơn hàng</h2><p>Đăng nhập, vào Tài khoản → Danh sách đơn hàng để xem tình trạng xử lý, giao hàng và chi tiết đơn của bạn.</p>
            <h2>Khi cần thay đổi thông tin nhận hàng</h2><p>Liên hệ shop sớm và cung cấp mã đơn hàng để được hỗ trợ kiểm tra khả năng thay đổi trước khi giao. Nếu muốn hủy đơn, chức năng hủy chỉ áp dụng khi đơn đang Chờ xử lý hoặc Chờ lấy hàng.</p>
            <div class="tt-info-actions"><a class="tt-info-btn" href="taikhoan.php?tab=donhang">Xem đơn hàng của tôi</a><a class="tt-info-btn secondary" href="lienhe.php">Liên hệ hỗ trợ</a></div>
<?php break; case 'doitrahoantien': ?>
            <h2>Điều kiện tiếp nhận đổi trả</h2><p>Theo thông tin hỗ trợ hiện có của TT SHOP, shop hỗ trợ đổi trả trong vòng 7 ngày nếu sản phẩm bị lỗi từ nhà sản xuất. Nếu nhận sai sản phẩm, thiếu hàng hoặc phát hiện hư hỏng, khách hàng nên liên hệ để shop kiểm tra và xác nhận phương án xử lý.</p>
            <h2>Quy trình yêu cầu</h2><ol class="tt-info-steps"><li><strong>Chuẩn bị thông tin</strong><p>Mã đơn, tên sản phẩm, mô tả vấn đề và hình ảnh nếu có. Giữ lại sản phẩm và bao bì liên quan để đối chiếu.</p></li><li><strong>Liên hệ TT SHOP</strong><p>Gửi yêu cầu qua trang phản ánh hoặc gọi hotline. Không tự ý gửi sản phẩm về khi chưa được hướng dẫn.</p></li><li><strong>Xác nhận phương án</strong><p>Shop kiểm tra thông tin và trao đổi về đổi sản phẩm, hoàn tiền hoặc phương án phù hợp, cùng chi phí và thời gian xử lý nếu có.</p></li></ol>
            <p class="tt-info-note">Phương án hoàn tiền, thời hạn xử lý và phí đổi trả cần được shop xác nhận theo từng trường hợp; trang này không tự động thực hiện hoàn tiền.</p>
            <div class="tt-info-actions"><a class="tt-info-btn" href="phananh.php">Gửi phản ánh</a><a class="tt-info-btn secondary" href="taikhoan.php?tab=donhang">Xem đơn hàng</a></div>
<?php break; case 'khachhangthanthiet': require_once __DIR__ . '/membership.php'; ?>
            <h2>Hệ thống hạng thành viên</h2><p>Hạng được xác định theo số đơn đã giao thành công <strong>hoặc</strong> tổng chi tiêu tích lũy từ các đơn đã giao. Bạn chỉ cần đạt một trong hai điều kiện để đủ ngưỡng hạng tương ứng.</p>
            <div class="tt-info-table-wrap"><table class="tt-info-table"><thead><tr><th>Hạng</th><th>Đơn đã giao</th><th>Chi tiêu tích lũy</th></tr></thead><tbody>
            <?php foreach (membership_rules() as $tier => $rule): ?><tr><th scope="row"><span class="tt-info-tier <?= ttinfo_e(strtolower($tier)) ?>"><?= ttinfo_e($tier) ?></span></th><td><?= (int)$rule['orders'] ?> đơn</td><td><?= number_format($rule['spend'], 0, ',', '.') ?> đ</td></tr><?php endforeach; ?>
            </tbody></table></div>
            <h2>Quyền lợi và cách sử dụng</h2><ul><li>Khám phá mã giảm giá theo hạng thành viên và điều kiện của từng mã.</li><li>Hạng cao hơn có thể sử dụng mã dành cho hạng thấp hơn nếu đáp ứng điều kiện khác.</li><li>Kiểm tra tiến độ nâng hạng, kho voucher và lịch sử sử dụng tại trang tài khoản.</li></ul><p class="tt-info-note">Mức giảm và ưu đãi vận chuyển có thể thay đổi theo cấu hình voucher thực tế. Vui lòng xem kho mã hiện hành thay vì dựa vào mức ưu đãi cố định.</p>
            <div class="tt-info-actions"><a class="tt-info-btn" href="taikhoan.php?tab=thanhvien">Xem hạng và kho voucher</a><a class="tt-info-btn secondary" href="dangky.php">Đăng ký tài khoản</a></div>
<?php break; case 'gioithieu': ?>
            <h2>Chào mừng bạn đến TT SHOP!</h2><p>TT SHOP là website thương mại điện tử chuyên cung cấp đồ dùng học tập, văn phòng phẩm, balo, sổ tay và máy tính cầm tay, phục vụ nhu cầu của học sinh, sinh viên và người làm việc văn phòng.</p>
            <h2>Mục tiêu của chúng tôi</h2><p>Mang đến trải nghiệm tìm kiếm, lựa chọn và đặt mua sản phẩm thuận tiện; giúp khách hàng dễ dàng theo dõi đơn hàng, tiếp cận ưu đãi và liên hệ hỗ trợ.</p>
            <div class="tt-info-facts"><div><i class="fas fa-book-open"></i><strong>Sản phẩm đa dạng</strong><span>Phục vụ học tập và công việc</span></div><div><i class="fas fa-cart-shopping"></i><strong>Mua sắm tiện lợi</strong><span>Tìm kiếm và đặt hàng trực tuyến</span></div><div><i class="fas fa-headset"></i><strong>Hỗ trợ khách hàng</strong><span>Trao đổi khi cần trợ giúp</span></div></div>
            <div class="tt-info-actions"><a class="tt-info-btn" href="sanphamhocbang.php">Khám phá sản phẩm</a><a class="tt-info-btn secondary" href="lienhe.php">Liên hệ TT SHOP</a></div>
<?php break; case 'sanphamhocbang': ?>
            <h2>Danh mục sản phẩm</h2><p>Chọn một nhóm hoặc loại sản phẩm để mở danh mục đang có trên website.</p>
            <?php $ttCats = [
                ['fa-book','Vở ghi – Sổ tay',['vo-oly'=>'Vở ô ly','vo-caro'=>'Vở caro','vo-ke-ngang'=>'Vở kẻ ngang','so-loxo'=>'Sổ lò xo','so-da'=>'Sổ da']],
                ['fa-pen','Bút viết',['but-bi'=>'Bút bi','but-chi'=>'Bút chì','but-muc'=>'Bút mực','but-xoa'=>'Bút xoá','but-quang'=>'Bút dạ quang']],
                ['fa-ruler-combined','Dụng cụ học tập',['thuoc'=>'Thước','note'=>'Giấy note','phan'=>'Phấn','compa'=>'Compa','hop-but'=>'Hộp bút']],
                ['fa-paperclip','Văn phòng phẩm',['giay-in'=>'Giấy in','kep-giay'=>'Kẹp giấy','dao-roc'=>'Dao rọc giấy','bia-cong'=>'Bìa còng','keo'=>'Kéo văn phòng']],
                ['fa-bag-shopping','Balo – Cặp sách',['balo-hs'=>'Balo học sinh','balo-vp'=>'Balo văn phòng']],
                ['fa-calculator','Máy tính cầm tay',['casio'=>'Casio','mt-vp'=>'Máy tính văn phòng']]
            ]; ?>
            <div class="tt-info-cat-grid"><?php foreach ($ttCats as [$ico,$name,$links]): ?><section class="tt-info-cat"><h3><i class="fas <?= ttinfo_e($ico) ?>" aria-hidden="true"></i> <?= ttinfo_e($name) ?></h3><div><?php foreach ($links as $slug=>$label): ?><a href="category.php?type=<?= ttinfo_e($slug) ?>"><?= ttinfo_e($label) ?></a><?php endforeach; ?></div></section><?php endforeach; ?></div>
            <div class="tt-info-actions"><a class="tt-info-btn" href="index.php">Về trang mua sắm</a></div>
<?php break; case 'baomat': ?>
            <h2>Thông tin được sử dụng khi mua hàng</h2><p>Website sử dụng thông tin tài khoản, họ tên, email, số điện thoại, địa chỉ nhận hàng, lịch sử đơn hàng, voucher và nội dung hỗ trợ mà khách hàng cung cấp trong quá trình sử dụng.</p>
            <h2>Mục đích sử dụng</h2><ul><li>Quản lý tài khoản, xác thực đăng nhập và hỗ trợ khôi phục mật khẩu.</li><li>Xử lý đơn hàng, giao hàng và giải quyết yêu cầu hỗ trợ.</li><li>Tính hạng thành viên và điều kiện sử dụng ưu đãi.</li></ul>
            <h2>Quản lý thông tin cá nhân</h2><p>Khách hàng có thể truy cập tài khoản để kiểm tra và cập nhật thông tin, địa chỉ nhận hàng. Nếu cần yêu cầu chỉnh sửa hoặc xóa dữ liệu, vui lòng liên hệ shop để được hướng dẫn.</p>
            <h2>Lưu ý an toàn</h2><p>Không chia sẻ mật khẩu hoặc mã xác thực với người khác. Đăng xuất khi dùng máy tính chung. Không gửi thông tin thẻ ngân hàng, mật khẩu hoặc mã OTP qua email hay khung chat.</p>
            <p class="tt-info-note">Nội dung này là bản mô tả chức năng hiện tại và hướng dẫn sử dụng an toàn. Phạm vi chia sẻ, thời hạn lưu trữ và quy trình xử lý yêu cầu dữ liệu cần được đơn vị vận hành xác nhận trước khi công bố chính thức.</p>
            <div class="tt-info-actions"><a class="tt-info-btn" href="taikhoan.php">Quản lý tài khoản</a><a class="tt-info-btn secondary" href="lienhe.php">Liên hệ về dữ liệu</a></div>
<?php break; case 'lienhe': ?>
            <h2>Thông tin cửa hàng</h2>
            <div class="tt-info-contact"><i class="fas fa-location-dot"></i><div><h3>Địa chỉ</h3><p>Thôn Thanh Nga, xã Việt Yên, tỉnh Hưng Yên</p></div></div>
            <div class="tt-info-contact"><i class="fas fa-phone-volume"></i><div><h3>Hotline</h3><a href="tel:1900888999">1900 888 999</a></div></div>
            <div class="tt-info-contact"><i class="fas fa-envelope"></i><div><h3>Email</h3><a href="mailto:TTSHOP@gmail.com">TTSHOP@gmail.com</a></div></div>
            <div class="tt-info-contact"><i class="fab fa-facebook"></i><div><h3>Facebook</h3><a href="https://www.facebook.com/profile.php?id=61589045422725" target="_blank" rel="noopener noreferrer">Trang Facebook TT SHOP</a></div></div>
            <h2>Hỗ trợ về đơn hàng và sản phẩm</h2><p>Để được hỗ trợ thuận tiện, hãy chuẩn bị mã đơn hàng và nội dung cần trao đổi. Bạn có thể dùng các kênh liên hệ trên hoặc biểu mẫu soạn email phản ánh.</p>
            <div class="tt-info-actions"><a class="tt-info-btn" href="phananh.php">Gửi phản ánh</a><a class="tt-info-btn secondary" href="taikhoan.php?tab=donhang">Xem đơn hàng</a></div>
<?php break; case 'phananh': ?>
            <h2>Thông tin phản ánh</h2><p>Điền thông tin để tạo email phản ánh gửi TT SHOP. Vui lòng mô tả rõ vấn đề để cửa hàng có thể kiểm tra. Không nhập mật khẩu, OTP hoặc thông tin thanh toán nhạy cảm.</p>
            <form id="tt-info-feedback" class="tt-info-form" method="post">
                <div class="tt-info-form-grid"><div><label for="tt-f-name">Họ và tên *</label><input id="tt-f-name" name="name" maxlength="100" required autocomplete="name" placeholder="Họ và tên"></div><div><label for="tt-f-order">Mã đơn hàng (nếu có)</label><input id="tt-f-order" name="order" maxlength="80" placeholder="Mã đơn hàng"></div></div>
                <label for="tt-f-type">Loại phản ánh *</label><select id="tt-f-type" name="type" required><option value="">Chọn vấn đề</option><option>Sản phẩm lỗi hoặc hư hỏng</option><option>Sản phẩm không đúng mô tả</option><option>Giao sai hoặc thiếu sản phẩm</option><option>Chất lượng đóng gói</option><option>Vấn đề khác</option></select>
                <label for="tt-f-product">Tên sản phẩm</label><input id="tt-f-product" name="product" maxlength="150" placeholder="Nhập tên sản phẩm">
                <label for="tt-f-message">Nội dung phản ánh *</label><textarea id="tt-f-message" name="message" required maxlength="1500" rows="6" placeholder="Mô tả vấn đề bạn gặp phải..."></textarea>
                <p class="tt-info-note">Nút bên dưới mở ứng dụng email với nội dung đã điền. Bạn có thể đính kèm ảnh tại đó và cần tự bấm Gửi. Biểu mẫu này không lưu dữ liệu vào website.</p>
                <button class="tt-info-btn" type="submit" disabled><i class="fas fa-envelope"></i> Soạn email phản ánh</button>
                <p id="tt-f-status" role="status" hidden></p>
            </form>
            <noscript><p>Vui lòng bật JavaScript để soạn email từ biểu mẫu, hoặc gửi trực tiếp đến <a href="mailto:TTSHOP@gmail.com">TTSHOP@gmail.com</a>.</p></noscript>
            <script>
            const feedbackForm = document.getElementById('tt-info-feedback');
            feedbackForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const f = new FormData(this);
                const subject = 'TT SHOP - Phản ánh chất lượng sản phẩm';
                const body = ['Họ và tên: '+f.get('name'), 'Mã đơn hàng: '+(f.get('order') || 'Không có'), 'Loại phản ánh: '+f.get('type'), 'Tên sản phẩm: '+(f.get('product') || 'Chưa cung cấp'), '', 'Nội dung:', f.get('message')].join('\r\n');
                const status = document.getElementById('tt-f-status');
                status.hidden = false;
                status.textContent = 'Nếu ứng dụng email không mở, vui lòng gửi thủ công đến TTSHOP@gmail.com. Chưa có phản ánh nào được gửi tự động.';
                window.location.href = 'mailto:TTSHOP@gmail.com?subject='+encodeURIComponent(subject)+'&body='+encodeURIComponent(body);
            });
            feedbackForm.querySelector('button[type=submit]').disabled = false;
            </script>
            <p>Cần trao đổi trực tiếp? Gọi <a href="tel:1900888999">1900 888 999</a> hoặc xem <a href="lienhe.php">thông tin liên hệ</a>.</p>
<?php break; endswitch; ?>
        </article>
    </div>
</main>
<?php require __DIR__ . '/footer.php'; ?>
</body>
</html>
