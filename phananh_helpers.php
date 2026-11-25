<?php
require_once __DIR__ . '/csrf.php';

function pa_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function pa_text($v): string { return is_string($v) ? trim($v) : ''; }
function pa_len(string $s): int { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : preg_match_all('/./us', $s); }
function pa_id($v): int { return is_scalar($v) ? (int)(filter_var($v, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>2147483647]]) ?: 0) : 0; }
function pa_types(): array { return ['Sản phẩm lỗi hoặc hư hỏng','Sản phẩm không đúng mô tả','Giao sai hoặc thiếu sản phẩm','Chất lượng đóng gói','Góp ý dịch vụ','Vấn đề khác']; }
function pa_statuses(): array { return ['pending'=>'Chờ xử lý','processing'=>'Đang xử lý','resolved'=>'Đã xử lý']; }
function pa_code($id): string { return 'PA-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT); }
function pa_date($v): string { return date('d/m/Y H:i', strtotime($v)); }
function pa_stmt($conn, string $sql, string $types='', array $args=[]) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new RuntimeException('Prepare failed');
    if ($types !== '' && !$stmt->bind_param($types, ...$args)) throw new RuntimeException('Bind failed');
    if (!$stmt->execute()) throw new RuntimeException('Execute failed');
    return $stmt;
}
function pa_rows($conn, string $sql, string $types='', array $args=[]): array {
    $s=pa_stmt($conn,$sql,$types,$args); $r=$s->get_result();
    if (!$r) throw new RuntimeException('Read failed');
    $rows=$r->fetch_all(MYSQLI_ASSOC); $s->close(); return $rows;
}
function pa_one($conn, string $sql, string $types='', array $args=[]): ?array { return pa_rows($conn,$sql,$types,$args)[0] ?? null; }
function pa_exec($conn, string $sql, string $types='', array $args=[]): void { pa_stmt($conn,$sql,$types,$args)->close(); }
function pa_customer($conn): ?array {
    if (($_SESSION['is_login'] ?? false) !== true || !($id=pa_id($_SESSION['user_id'] ?? null))) return null;
    $u=pa_one($conn,'SELECT id, ho, ten, email FROM users WHERE id=?','i',[$id]);
    if (!$u || empty($_SESSION['user_email']) || strcasecmp((string)$u['email'],(string)$_SESSION['user_email'])!==0) return null;
    return $u;
}
function pa_admin($conn): int {
    if (($_SESSION['admin_login'] ?? false) !== true || !($id=pa_id($_SESSION['admin_id'] ?? null))) return 0;
    return pa_one($conn,'SELECT id FROM admins WHERE id=?','i',[$id]) ? $id : 0;
}
function pa_upload(): array {
    $f=$_FILES['anh'] ?? null;
    if (!$f || ($f['error'] ?? null) === UPLOAD_ERR_NO_FILE) return [null,null];
    if (($f['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($f['tmp_name'] ?? null) || !is_uploaded_file($f['tmp_name'])) throw new InvalidArgumentException('Không tải được ảnh. Vui lòng kiểm tra giới hạn tải lên và thử lại.');
    $size=filesize($f['tmp_name']);
    if (!$size || $size>2*1024*1024) throw new InvalidArgumentException('Ảnh phải nhỏ hơn hoặc bằng 2 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $info=@getimagesize($f['tmp_name']);
    if (!in_array($mime,['image/jpeg','image/png','image/webp'],true) || !$info || ($info['mime']??'')!==$mime || $info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>20000000) throw new InvalidArgumentException('Chỉ nhận ảnh JPG, PNG, WEBP hợp lệ, tối đa 20 megapixel.');
    $bytes=file_get_contents($f['tmp_name']);
    if ($bytes===false) throw new InvalidArgumentException('Không đọc được ảnh.');
    return [$mime,$bytes];
}
function pa_flash(string $key, int $owner, string $msg): void { $_SESSION[$key]=['owner'=>$owner,'message'=>$msg]; }
function pa_get_flash(string $key, int $owner): string { $f=$_SESSION[$key]??null; unset($_SESSION[$key]); return is_array($f)&&($f['owner']??null)===$owner ? (string)$f['message'] : ''; }
function pa_error(Throwable $e): string { error_log('TT SHOP feedback: '.$e->getMessage()); return 'Không thể xử lý phản ánh. Hãy kiểm tra đã nhập file SQL bổ sung vào đúng cơ sở dữ liệu, hoặc thử lại sau.'; }
function pa_status_html(string $s): string { return '<span class="pa-badge '.pa_h($s).'">'.pa_h(pa_statuses()[$s]??$s).'</span>'; }
function pa_history(array $replies): void {
    foreach($replies as $r) { ?>
    <div class="pa-message <?= $r['nguoi_gui']==='admin'?'from-admin':'from-customer' ?>"><strong><?= $r['nguoi_gui']==='admin'?'TT SHOP · Admin':'Khách hàng' ?></strong> <small><?= pa_h(pa_date($r['created_at'])) ?></small><div class="pa-text"><?= pa_h($r['noi_dung']) ?></div></div>
    <?php }
}
