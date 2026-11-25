<?php
// admin/vouchers.php - Quản lý ưu đãi thành viên TT SHOP.
require_once __DIR__ . '/check_admin.php';
require_once __DIR__ . '/../csrf.php';
require_once __DIR__ . '/../membership.php';
header('Cache-Control: no-store');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../connect.php';

function av_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function av_money($value): string {
    return number_format((int)$value, 0, ',', '.') . 'đ';
}
function av_text(array $input, string $key): string {
    $value = $input[$key] ?? '';
    if (!is_string($value)) throw new DomainException('Dữ liệu không hợp lệ.');
    return trim($value);
}
function av_int(array $input, string $key, string $label, int $min, int $max, bool $nullable = false): ?int {
    $raw = av_text($input, $key);
    if ($nullable && $raw === '') return null;
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
    if ($value === false) throw new DomainException($label . ' phải là số nguyên từ ' . $min . ' đến ' . $max . '.');
    return $value;
}
function av_date(array $input, string $key, string $label): ?string {
    $raw = av_text($input, $key);
    if ($raw === '') return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $raw, new DateTimeZone('Asia/Ho_Chi_Minh'));
    if (!$date || $date->format('Y-m-d\\TH:i') !== $raw || (int)$date->format('Y') < 1000) {
        throw new DomainException($label . ' không hợp lệ.');
    }
    return $date->format('Y-m-d H:i:s');
}
function av_datetime_input($date): string {
    return $date === null || $date === '' ? '' : str_replace(' ', 'T', substr($date, 0, 16));
}
function av_read_form(array $post): array {
    $code = strtoupper(av_text($post, 'ma_code'));
    if (!preg_match('/^[A-Z0-9_-]{1,50}$/D', $code)) {
        throw new DomainException('Mã chỉ gồm A-Z, 0-9, dấu gạch ngang hoặc gạch dưới, tối đa 50 ký tự.');
    }
    $name = av_text($post, 'ten_ma');
    if (!preg_match('/^.{1,150}$/usD', $name)) throw new DomainException('Tên chương trình phải từ 1 đến 150 ký tự.');
    $tier = av_text($post, 'hang_toi_thieu');
    if (!array_key_exists($tier, membership_rules())) throw new DomainException('Hạng thành viên không hợp lệ.');
    $rate = av_int($post, 'phan_tram_giam', 'Phần trăm giảm', 1, 100);
    $min = av_int($post, 'don_toi_thieu', 'Đơn tối thiểu', 0, 2147483647);
    $cap = av_int($post, 'giam_toi_da', 'Giảm tối đa', 1, 2147483647);
    $free = av_int($post, 'mien_phi_van_chuyen', 'Miễn phí vận chuyển', 0, 1);
    $limit = av_int($post, 'gioi_han_su_dung', 'Giới hạn toàn hệ thống', 1, 2147483647, true);
    $perUser = av_int($post, 'gioi_han_moi_user', 'Giới hạn mỗi khách', 1, 2147483647, true);
    $start = av_date($post, 'ngay_bat_dau', 'Ngày bắt đầu');
    $end = av_date($post, 'ngay_ket_thuc', 'Ngày kết thúc');
    if ($start !== null && $end !== null && $end <= $start) throw new DomainException('Ngày kết thúc phải sau ngày bắt đầu.');
    $active = av_int($post, 'trang_thai', 'Trạng thái', 0, 1);
    return [$code, $name, $tier, $rate, $min, $cap, $free, $limit, $perUser, $start, $end, $active];
}
function av_query(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);
    try {
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } finally { $stmt->close(); }
}
function av_flash(string $message): void {
    $_SESSION['admin_voucher_flash'] = $message;
    header('Location: vouchers.php', true, 303);
    exit;
}
function av_status(array $v): array {
    if ((int)$v['trang_thai'] !== 1) return ['Vô hiệu hóa', 'off'];
    if ($v['ngay_bat_dau'] !== null && $v['now_db'] < $v['ngay_bat_dau']) return ['Chưa bắt đầu', 'wait'];
    if ($v['ngay_ket_thuc'] !== null && $v['now_db'] >= $v['ngay_ket_thuc']) return ['Hết hạn', 'off'];
    if ($v['gioi_han_su_dung'] !== null && (int)$v['used_count'] >= (int)$v['gioi_han_su_dung']) return ['Hết lượt', 'wait'];
    return ['Đang hoạt động', 'on'];
}

$token = csrf_token();
$flash = $_SESSION['admin_voucher_flash'] ?? '';
unset($_SESSION['admin_voucher_flash']);
$error = '';
$loadError = '';
$oldForm = null;
$editId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]) ?: 0;

// Mọi thao tác ghi đều sử dụng POST + CSRF, không thay đổi lịch sử đơn cũ.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Phiên xác thực không hợp lệ. Vui lòng quay lại và tải lại trang.');
    }
    try {
        $action = av_text($_POST, 'action');
        if ($action === 'save') {
            $editId = av_int($_POST, 'id', 'ID mã', 0, 2147483647);
            $oldForm = $_POST;
            $values = av_read_form($_POST);
            // Đổi cấu hình bằng một lệnh UPDATE nguyên tử; khóa hàng của checkout vẫn có hiệu lực.
            if ($editId > 0) {
                if (!av_query($conn, 'SELECT id FROM magiamgia WHERE id = ?', 'i', [$editId])) {
                    throw new DomainException('Mã giảm giá không còn tồn tại.');
                }
                $sql = 'UPDATE magiamgia SET ma_code=?, ten_ma=?, hang_toi_thieu=?, phan_tram_giam=?, don_toi_thieu=?, giam_toi_da=?, mien_phi_van_chuyen=?, gioi_han_su_dung=?, gioi_han_moi_user=?, ngay_bat_dau=?, ngay_ket_thuc=?, trang_thai=? WHERE id=?';
                $values[] = $editId;
                $types = 'sssiiiiiissii';
            } else {
                $sql = 'INSERT INTO magiamgia (ma_code,ten_ma,hang_toi_thieu,phan_tram_giam,don_toi_thieu,giam_toi_da,mien_phi_van_chuyen,gioi_han_su_dung,gioi_han_moi_user,ngay_bat_dau,ngay_ket_thuc,trang_thai) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';
                $types = 'sssiiiiiissi';
            }
            $stmt = $conn->prepare($sql);
            try {
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
            } finally { $stmt->close(); }
            av_flash($editId ? 'Đã cập nhật mã giảm giá.' : 'Đã tạo mã giảm giá mới.');
        } elseif ($action === 'toggle') {
            $id = av_int($_POST, 'id', 'ID mã', 1, 2147483647);
            $active = av_int($_POST, 'trang_thai', 'Trạng thái', 0, 1);
            $stmt = $conn->prepare('UPDATE magiamgia SET trang_thai=? WHERE id=?');
            try {
                $stmt->bind_param('ii', $active, $id);
                $stmt->execute();
            } finally { $stmt->close(); }
            av_flash($active ? 'Đã bật mã. Điều kiện thời hạn và lượt dùng vẫn được kiểm tra.' : 'Đã vô hiệu hóa mã giảm giá.');
        } else {
            throw new DomainException('Thao tác không hợp lệ.');
        }
    } catch (DomainException $e) {
        $error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $error = (int)$e->getCode() === 1062 ? 'Mã giảm giá đã tồn tại. Hãy dùng mã khác.' : 'Không lưu được dữ liệu. Vui lòng thử lại.';
        error_log('TT SHOP admin vouchers: ' . $e->getMessage());
    }
}

$defaults = ['ma_code'=>'','ten_ma'=>'','hang_toi_thieu'=>'Member','phan_tram_giam'=>3,'don_toi_thieu'=>100000,'giam_toi_da'=>30000,'mien_phi_van_chuyen'=>0,'gioi_han_su_dung'=>null,'gioi_han_moi_user'=>null,'ngay_bat_dau'=>null,'ngay_ket_thuc'=>null,'trang_thai'=>1];
$form = $defaults;
$vouchers = $history = [];
$historyId = filter_var($_GET['history_id'] ?? 0, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]) ?: 0;
$historyPage = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]) ?: 1;
$historyPages = 1;
$stats = ['active'=>0,'used'=>0,'delivered'=>0,'saved'=>0,'pending'=>0];
try {
    // Lượt hợp lệ trừ đơn hủy, tương thích voucher_helpers.php.
    $vouchers = av_query($conn, "SELECT m.*, NOW() AS now_db,
        COALESCE(s.used_count,0) AS used_count,
        COALESCE(s.delivered_count,0) AS delivered_count,
        COALESCE(s.cancelled_count,0) AS cancelled_count,
        COALESCE(s.saved,0) AS saved,
        COALESCE(s.pending,0) AS pending
        FROM magiamgia m LEFT JOIN (
            SELECT l.magiamgia_id,
                SUM(CASE WHEN COALESCE(d.trang_thai,'') NOT IN ('Đã huỷ','Đã hủy') THEN 1 ELSE 0 END) AS used_count,
                SUM(CASE WHEN d.trang_thai='Đã giao' THEN 1 ELSE 0 END) AS delivered_count,
                SUM(CASE WHEN d.trang_thai IN ('Đã huỷ','Đã hủy') THEN 1 ELSE 0 END) AS cancelled_count,
                SUM(CASE WHEN d.trang_thai='Đã giao' THEN l.tien_giam_gia+l.tien_giam_van_chuyen ELSE 0 END) AS saved,
                SUM(CASE WHEN COALESCE(d.trang_thai,'') NOT IN ('Đã giao','Đã huỷ','Đã hủy') THEN l.tien_giam_gia+l.tien_giam_van_chuyen ELSE 0 END) AS pending
            FROM lichsu_magiamgia l JOIN donhang d ON d.id=l.donhang_id GROUP BY l.magiamgia_id
        ) s ON s.magiamgia_id=m.id ORDER BY m.id DESC");
    foreach ($vouchers as $v) {
                $stats['active'] += av_status($v)[1] === 'on' ? 1 : 0;
        $stats['used'] += (int)$v['used_count'];
        $stats['delivered'] += (int)$v['delivered_count'];
        $stats['saved'] += (int)$v['saved'];
        $stats['pending'] += (int)$v['pending'];
        if ((int)$v['id'] === $editId) $form = array_merge($defaults, $v);
    }
    if ($editId && !isset($form['id'])) {
        $error = $error ?: 'Không tìm thấy mã giảm giá cần sửa.';
        $editId = 0;
    }
    $form['ngay_bat_dau'] = av_datetime_input($form['ngay_bat_dau']);
    $form['ngay_ket_thuc'] = av_datetime_input($form['ngay_ket_thuc']);
    if ($oldForm !== null) {
        foreach ($defaults as $key => $unused) {
            if (isset($oldForm[$key]) && is_string($oldForm[$key])) $form[$key] = $oldForm[$key];
        }
    }
    $where = $historyId ? ' WHERE l.magiamgia_id = ?' : '';
    $params = $historyId ? [$historyId] : [];
    $types = $historyId ? 'i' : '';
    $count = av_query($conn, 'SELECT COUNT(*) AS n FROM lichsu_magiamgia l JOIN donhang d ON d.id=l.donhang_id' . $where, $types, $params);
    $historyPages = max(1, (int)ceil((int)$count[0]['n'] / 20));
    $historyPage = min($historyPage, $historyPages);
    $offset = ($historyPage - 1) * 20;
    $history = av_query($conn, "SELECT l.*,d.ma_don,d.ho_ten,d.hang_thanh_vien,d.trang_thai,d.tong_cong
        FROM lichsu_magiamgia l JOIN donhang d ON d.id=l.donhang_id" . $where . ' ORDER BY l.id DESC LIMIT 20 OFFSET ?', $types . 'i', array_merge($params, [$offset]));
} catch (Throwable $e) {
    $loadError = 'Không thể tải dữ liệu voucher. Hãy kiểm tra kết nối và hai bảng magiamgia, lichsu_magiamgia.';
    error_log('TT SHOP admin voucher list: ' . $e->getMessage());
}
function av_history_url(int $id, int $page): string {
    return 'vouchers.php?' . http_build_query(['history_id'=>$id,'p'=>$page]) . '#history';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản lý mã giảm giá - TT SHOP ADMIN</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f4f6f8;color:#26332d}
.layout{display:grid;grid-template-columns:260px minmax(0,1fr);min-height:100vh}
.sidebar{background:#15985d;color:white;padding:25px 20px}.sidebar h2{font-size:30px;margin-bottom:35px}.sidebar a{display:block;color:white;text-decoration:none;padding:14px 12px;border-radius:8px;margin-bottom:8px;font-weight:bold}.sidebar a:hover,.sidebar a.active{background:rgba(255,255,255,.18)}
.main{padding:30px;min-width:0}.topbar,.box,.metric{background:white;border-radius:14px;padding:22px;box-shadow:0 4px 14px rgba(0,0,0,.08)}.topbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:25px}.topbar h1{margin:0;font-size:25px}.box{margin-bottom:24px}.box h2{margin-top:0;font-size:21px}.muted{color:#64716b;font-size:13px;line-height:1.5}.metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(175px,1fr));gap:15px;margin-bottom:24px}.metric{padding:18px}.metric span{display:block;font-size:13px;color:#64716b}.metric strong{display:block;font-size:25px;color:#15985d;margin-top:8px;overflow-wrap:anywhere}
.fields{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.field label{display:block;font-weight:bold;font-size:14px;margin-bottom:7px}.field input,.field select,.filters select{width:100%;height:42px;border:1px solid #ccc;border-radius:7px;padding:0 10px;background:#fff;font:inherit;font-size:14px}.field small{display:block;margin-top:5px;color:#64716b;line-height:1.4}.actions{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-top:18px}.btn{display:inline-flex;align-items:center;justify-content:center;background:#15985d;color:white;text-decoration:none;border:0;border-radius:8px;padding:11px 15px;font-size:14px;font-weight:bold;cursor:pointer}.btn.secondary{background:#e9eeeb;color:#34443a}.btn.warn{background:#fff0d6;color:#985600}.btn.small{padding:7px 10px;font-size:12px}.inline{display:inline;margin:0}.table-wrap{width:100%;overflow-x:auto}table{width:100%;min-width:1040px;border-collapse:collapse;font-size:14px}th,td{padding:13px 10px;border-bottom:1px solid #eee;text-align:left;vertical-align:middle}th{background:#f0f0f0;white-space:nowrap}td small{display:block;margin-top:5px;color:#64716b}.tag{display:inline-block;padding:6px 10px;border-radius:20px;font-size:12px;font-weight:bold;white-space:nowrap}.on{background:#e2f5e9;color:#087840}.off{background:#eee;color:#666}.wait{background:#fff0c7;color:#995b00}.notice{padding:13px 16px;border-radius:8px;margin-bottom:18px;background:#e4f6ea;color:#087840}.notice.error{background:#fff0f0;color:#a12020}.filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px}.filters select{width:auto;max-width:100%}.pagination{display:flex;gap:12px;align-items:center;margin-top:18px;flex-wrap:wrap}.link{color:#0b72e7;text-decoration:none;font-weight:bold}.nowrap{white-space:nowrap}button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible{outline:2px solid #135cb8;outline-offset:2px}
@media(max-width:1000px){.fields{grid-template-columns:repeat(2,minmax(0,1fr))}.main{padding:20px}}@media(max-width:720px){.layout{grid-template-columns:1fr}.sidebar h2{font-size:24px;margin:0 0 12px}.sidebar{padding:15px}.sidebar a{padding:9px}.main{padding:12px}.fields{grid-template-columns:1fr}.topbar,.box{padding:16px}}
</style>
</head>
<body>
<div class="layout">
<?php $page = 'vouchers'; include __DIR__ . '/sidebar.php'; ?>
<main class="main">
    <header class="topbar"><div><h1>Quản lý mã giảm giá</h1><p class="muted">Chương trình khách hàng thân thiết TT SHOP</p></div><a class="btn" href="vouchers.php#voucher-form">+ Thêm mã giảm giá</a></header>
    <?php if ($flash !== ''): ?><div class="notice" role="status"><?= av_e($flash) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= av_e($error) ?></div><?php endif; ?>
    <?php if ($loadError !== ''): ?><div class="notice error" role="alert"><?= av_e($loadError) ?></div><?php else: ?>
    <section class="metrics" aria-label="Thống kê voucher">
        <div class="metric"><span>Tổng số mã</span><strong><?= count($vouchers) ?></strong></div>
        <div class="metric"><span>Đang hoạt động (còn hạn, còn lượt chung)</span><strong><?= $stats['active'] ?></strong></div>
        <div class="metric"><span>Lượt dùng chưa hủy</span><strong><?= $stats['used'] ?></strong></div>
        <div class="metric"><span>Đơn đã giao có voucher</span><strong><?= $stats['delivered'] ?></strong></div>
        <div class="metric"><span>Đã giảm thực tế (đơn đã giao)</span><strong><?= av_money($stats['saved']) ?></strong></div>
        <div class="metric"><span>Giảm dự kiến (đơn chưa giao)</span><strong><?= av_money($stats['pending']) ?></strong></div>
    </section>
       <section class="box" id="voucher-form">
        <h2><?= $editId ? 'Chỉnh sửa mã giảm giá' : 'Tạo mã giảm giá mới' ?></h2>
        <form method="post" action="vouchers.php<?= $editId ? '?edit=' . $editId : '' ?>#voucher-form">
            <input type="hidden" name="csrf_token" value="<?= av_e($token) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= $editId ?>">
            <div class="fields">
                <div class="field"><label for="ma_code">Mã giảm giá *</label><input id="ma_code" name="ma_code" maxlength="50" pattern="[A-Za-z0-9_\-]+" required value="<?= av_e($form['ma_code']) ?>" placeholder="VD: GOLD10"><small>Chữ, số, gạch ngang hoặc gạch dưới.</small></div>
                <div class="field"><label for="ten_ma">Tên chương trình *</label><input id="ten_ma" name="ten_ma" maxlength="150" required value="<?= av_e($form['ten_ma']) ?>"></div>
                <div class="field"><label for="hang_toi_thieu">Hạng tối thiểu *</label><select id="hang_toi_thieu" name="hang_toi_thieu"><?php foreach (membership_rules() as $tier => $rule): ?><option value="<?= av_e($tier) ?>" <?= $form['hang_toi_thieu']===$tier?'selected':'' ?>><?= av_e($tier) ?> trở lên</option><?php endforeach; ?></select></div>
                <?php foreach (['phan_tram_giam'=>['Phần trăm giảm (%)',1,100], 'don_toi_thieu'=>['Đơn tối thiểu (VNĐ)',0,2147483647], 'giam_toi_da'=>['Giảm tối đa (VNĐ)',1,2147483647]] as $key=>$spec): ?>
                <div class="field"><label for="<?= $key ?>"><?= av_e($spec[0]) ?> *</label><input type="number" id="<?= $key ?>" name="<?= $key ?>" min="<?= $spec[1] ?>" max="<?= $spec[2] ?>" step="1" required value="<?= av_e($form[$key]) ?>"></div>
                <?php endforeach; ?>
                <div class="field"><label for="mien_phi_van_chuyen">Miễn phí vận chuyển</label><select id="mien_phi_van_chuyen" name="mien_phi_van_chuyen"><option value="0" <?= (string)$form['mien_phi_van_chuyen']==='0'?'selected':'' ?>>Không</option><option value="1" <?= (string)$form['mien_phi_van_chuyen']==='1'?'selected':'' ?>>Có - Freeship</option></select></div>
                <?php foreach (['gioi_han_su_dung'=>'Tổng lượt sử dụng','gioi_han_moi_user'=>'Lượt tối đa mỗi khách'] as $key=>$label): ?>
                <div class="field"><label for="<?= $key ?>"><?= av_e($label) ?></label><input type="number" id="<?= $key ?>" name="<?= $key ?>" min="1" max="2147483647" step="1" value="<?= av_e($form[$key]) ?>" placeholder="Không giới hạn"><small>Bỏ trống nếu không giới hạn.</small></div>
                <?php endforeach; ?>
                <?php foreach (['ngay_bat_dau'=>'Thời gian bắt đầu','ngay_ket_thuc'=>'Thời gian kết thúc'] as $key=>$label): ?>
                <div class="field"><label for="<?= $key ?>"><?= av_e($label) ?></label><input type="datetime-local" id="<?= $key ?>" name="<?= $key ?>" value="<?= av_e($form[$key]) ?>"><small>Giờ Việt Nam. Bỏ trống nếu không giới hạn.</small></div>
                <?php endforeach; ?>
                <div class="field"><label for="trang_thai">Trạng thái</label><select id="trang_thai" name="trang_thai"><option value="1" <?= (string)$form['trang_thai']==='1'?'selected':'' ?>>Kích hoạt</option><option value="0" <?= (string)$form['trang_thai']==='0'?'selected':'' ?>>Vô hiệu hóa</option></select></div>
            </div>
            <p class="muted">Mỗi đơn chỉ dùng một mã. Khách có thể dùng mã của hạng hiện tại hoặc thấp hơn. Thay đổi cấu hình chỉ áp dụng cho lần kiểm tra voucher tiếp theo, không sửa số tiền đã lưu trong đơn cũ. Đổi tên mã có thể khiến mã đang lưu trong giỏ hàng không còn hợp lệ.</p>
            <div class="actions"><button class="btn" type="submit"><?= $editId?'Lưu thay đổi':'Tạo mã giảm giá' ?></button><?php if($editId): ?><a class="btn secondary" href="vouchers.php#voucher-form">Hủy chỉnh sửa</a><?php endif; ?></div>
        </form>
    </section>
    <section class="box">
        <h2>Danh sách mã giảm giá</h2>
        <div class="table-wrap"><table><thead><tr><th>Mã / Tên</th><th>Hạng</th><th>Ưu đãi</th><th>Điều kiện</th><th>Thời hạn</th><th>Lượt sử dụng</th><th>Đã giảm thực tế</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
        <?php if (!$vouchers): ?><tr><td colspan="9">Chưa có mã giảm giá.</td></tr><?php endif; ?>
        <?php foreach ($vouchers as $v): [$label,$class]=av_status($v); ?>
        <tr>
            <td><strong><?= av_e($v['ma_code']) ?></strong><small><?= av_e($v['ten_ma']) ?></small></td>
            <td><?= av_e($v['hang_toi_thieu']) ?>+</td>
            <td><strong><?= (int)$v['phan_tram_giam'] ?>%</strong><?php if ((int)$v['mien_phi_van_chuyen']===1): ?><small>+ Freeship</small><?php endif; ?></td>
            <td>Đơn từ <?= av_money($v['don_toi_thieu']) ?><small>Tối đa <?= av_money($v['giam_toi_da']) ?></small></td>
            <td><?= av_e($v['ngay_bat_dau'] ?? 'Không giới hạn') ?><small>Đến: <?= av_e($v['ngay_ket_thuc'] ?? 'Không giới hạn') ?></small></td>
            <td><?= (int)$v['used_count'] ?> / <?= av_e($v['gioi_han_su_dung'] ?? '∞') ?><small>Mỗi khách: <?= av_e($v['gioi_han_moi_user'] ?? '∞') ?></small><small>Đã giao: <?= (int)$v['delivered_count'] ?> · Hủy: <?= (int)$v['cancelled_count'] ?></small></td>
            <td class="nowrap"><?= av_money($v['saved']) ?></td><td><span class="tag <?= $class ?>"><?= av_e($label) ?></span></td>
            <td><div class="actions" style="margin:0"><a class="btn secondary small" href="vouchers.php?edit=<?= (int)$v['id'] ?>#voucher-form">Sửa</a><a class="btn secondary small" href="<?= av_e(av_history_url((int)$v['id'],1)) ?>">Lịch sử</a>
            <form class="inline" method="post" action="vouchers.php"><input type="hidden" name="csrf_token" value="<?= av_e($token) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>"><input type="hidden" name="trang_thai" value="<?= (int)$v['trang_thai']===1?0:1 ?>"><button type="submit" class="btn small <?= (int)$v['trang_thai']===1?'warn':'' ?>"><?= (int)$v['trang_thai']===1?'Vô hiệu hóa':'Kích hoạt' ?></button></form></div></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
        <p class="muted">Không xóa cứng mã để bảo toàn lịch sử. Lượt dùng của đơn đã hủy không tính vào giới hạn. Mã được bật vẫn có thể hết hạn, hết lượt hoặc chưa đủ điều kiện cho từng khách.</p>
    </section>
    <section class="box" id="history">
        <h2>Lịch sử sử dụng mã giảm giá</h2>
        <form method="get" class="filters" action="vouchers.php#history"><label for="history_id">Mã giảm giá:</label><select id="history_id" name="history_id"><option value="0">Tất cả mã</option><?php foreach ($vouchers as $v): ?><option value="<?= (int)$v['id'] ?>" <?= $historyId===(int)$v['id']?'selected':'' ?>><?= av_e($v['ma_code']) ?></option><?php endforeach; ?></select><button class="btn" type="submit">Lọc</button><a class="btn secondary" href="vouchers.php#history">Đặt lại</a></form>
        <div class="table-wrap"><table><thead><tr><th>Thời gian</th><th>Mã đã dùng</th><th>Khách hàng</th><th>Đơn hàng</th><th>Hạng khi mua</th><th>Giảm sản phẩm</th><th>Giảm vận chuyển</th><th>Tổng giảm</th><th>Thanh toán</th><th>Trạng thái</th></tr></thead><tbody>
        <?php if (!$history): ?><tr><td colspan="10">Chưa có lịch sử sử dụng mã giảm giá.</td></tr><?php endif; ?>
        <?php foreach ($history as $h): $cancelled=in_array($h['trang_thai'],['Đã huỷ','Đã hủy'],true); $delivered=$h['trang_thai']==='Đã giao'; ?>
        <tr><td class="nowrap"><?= av_e($h['created_at']) ?></td><td><strong><?= av_e($h['ma_code']) ?></strong></td><td><?= av_e($h['ho_ten']) ?><small>ID: <?= (int)$h['user_id'] ?></small></td><td><a class="link" href="order_detail.php?id=<?= (int)$h['donhang_id'] ?>"><?= av_e($h['ma_don']) ?></a></td><td><?= av_e($h['hang_thanh_vien'] ?? '—') ?></td><td><?= av_money($h['tien_giam_gia']) ?></td><td><?= av_money($h['tien_giam_van_chuyen']) ?></td><td><strong><?= av_money((int)$h['tien_giam_gia']+(int)$h['tien_giam_van_chuyen']) ?></strong></td><td><?= av_money($h['tong_cong']) ?></td><td><span class="tag <?= $cancelled?'off':($delivered?'on':'wait') ?>"><?= av_e($h['trang_thai'] ?? 'Không rõ') ?></span><?php if($cancelled): ?><small>Không tính lượt / tiết kiệm</small><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody></table></div>
        <div class="pagination"><?php if($historyPage>1): ?><a class="btn secondary small" href="<?= av_e(av_history_url($historyId,$historyPage-1)) ?>">Trang trước</a><?php endif; ?><span>Trang <?= $historyPage ?> / <?= $historyPages ?></span><?php if($historyPage<$historyPages): ?><a class="btn secondary small" href="<?= av_e(av_history_url($historyId,$historyPage+1)) ?>">Trang sau</a><?php endif; ?></div>
        <p class="muted">Thống kê thực tế chỉ tính đơn đã giao. Số tiền giảm gồm giảm sản phẩm và giảm phí vận chuyển; không bao gồm giảm giá có sẵn của sản phẩm.</p>
    </section>
    <?php endif; ?>
</main>
</div>
</body>
</html>