<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/validate_helpers.php';
require_once __DIR__ . '/csrf.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$requestId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['id'] ?? null)
    : ($_GET['id'] ?? null);
$addressId = is_scalar($requestId)
    ? filter_var((string)$requestId, FILTER_VALIDATE_INT)
    : false;

if (!$addressId || $userId <= 0) {
    $_SESSION['account_field_errors'] = array('address_action' => validation_message('system'));
    header('Location: taikhoan.php');
    exit();
}

try {
    $selectSql = 'SELECT id, ho_ten, so_dien_thoai, cong_ty, dia_chi, quoc_gia, ma_zip, mac_dinh
        FROM diachi_khachhang WHERE id = ? AND user_id = ? LIMIT 1';
    $selectStatement = $conn->prepare($selectSql);
    $selectStatement->bind_param('ii', $addressId, $userId);
    $selectStatement->execute();
    $address = $selectStatement->get_result()->fetch_assoc();
    $selectStatement->close();
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', __FILE__, __LINE__, $exception->getMessage()));
    $_SESSION['account_field_errors'] = array('address_action' => validation_message('system'));
    header('Location: taikhoan.php');
    exit();
}

if (!$address) {
    $_SESSION['account_field_errors'] = array('address_action' => validation_message('system'));
    header('Location: taikhoan.php');
    exit();
}

$errors = array();
$values = $address;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = array(
        'ho_ten' => trim($_POST['ho_ten'] ?? ''),
        'so_dien_thoai' => normalize_vn_phone($_POST['sdt'] ?? ''),
        'cong_ty' => trim($_POST['cong_ty'] ?? ''),
        'dia_chi' => trim($_POST['dia_chi'] ?? ''),
        'quoc_gia' => trim($_POST['quoc_gia'] ?? ''),
        'ma_zip' => trim($_POST['ma_zip'] ?? ''),
        'mac_dinh' => isset($_POST['mac_dinh']) ? 1 : 0
    );

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors['form'] = validation_message('system');
    }
    if (!validate_person_name($values['ho_ten'])) {
        $errors['ho_ten'] = validation_message('recipient_required');
    }
    if (!is_valid_vn_phone($values['so_dien_thoai'])) {
        $errors['sdt'] = phone_validation_message();
    }
    if ($values['dia_chi'] === '') {
        $errors['dia_chi'] = validation_message('address_required');
    }
    if (!in_array($values['quoc_gia'], array('Vietnam', 'Japan', 'Korea'), true)) {
        $errors['quoc_gia'] = validation_message('locations_required');
    }

    if (!$errors) {
        $transactionStarted = false;
        try {
            $conn->begin_transaction();
            $transactionStarted = true;

            if ($values['mac_dinh'] === 1) {
                $resetDefault = $conn->prepare(
                    'UPDATE diachi_khachhang SET mac_dinh = 0 WHERE user_id = ? AND id <> ?'
                );
                $resetDefault->bind_param('ii', $userId, $addressId);
                $resetDefault->execute();
                $resetDefault->close();
            }

            $updateSql = 'UPDATE diachi_khachhang
                SET ho_ten = ?, so_dien_thoai = ?, cong_ty = ?, dia_chi = ?,
                    quoc_gia = ?, ma_zip = ?, mac_dinh = ?
                WHERE id = ? AND user_id = ?';
            $update = $conn->prepare($updateSql);
            $hoTen = $values['ho_ten'];
            $phone = $values['so_dien_thoai'];
            $company = $values['cong_ty'];
            $addressText = $values['dia_chi'];
            $country = $values['quoc_gia'];
            $zipCode = $values['ma_zip'];
            $isDefault = (int)$values['mac_dinh'];
            $update->bind_param(
                'ssssssiii',
                $hoTen,
                $phone,
                $company,
                $addressText,
                $country,
                $zipCode,
                $isDefault,
                $addressId,
                $userId
            );
            $update->execute();
            $update->close();

            $conn->commit();
            $transactionStarted = false;
            header('Location: taikhoan.php');
            exit();
        } catch (Throwable $exception) {
            if ($transactionStarted) {
                $conn->rollback();
            }
            error_log(sprintf('%s:%d: %s', __FILE__, __LINE__, $exception->getMessage()));
            $errors['form'] = validation_message('system');
        }
    }
}

$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa địa chỉ - TT SHOP</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .edit-address-page{max-width:760px;margin:40px auto;padding:24px;background:#fff;border-radius:12px}
        .edit-address-page h1{margin:0 0 20px;color:#166534}
        .edit-address-page label{display:block;margin:14px 0 6px}
        .edit-address-page input,.edit-address-page select{box-sizing:border-box;width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:6px}
        .edit-address-page button{margin-top:18px;padding:12px 20px;border:0;border-radius:6px;background:#16a34a;color:#fff;cursor:pointer}
        .field-error{display:block;margin-top:4px;color:#b91c1c}
        .default-check{display:flex!important;align-items:center;gap:8px}
        .default-check input{width:auto}
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<main class="edit-address-page">
    <h1>Chỉnh sửa địa chỉ</h1>
    <?php if (!empty($errors['form'])): ?>
        <p class="field-error"><?php echo htmlspecialchars($errors['form'], ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form action="suadiachi.php" method="POST" novalidate>
        <input type="hidden" name="id" value="<?php echo (int)$addressId; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="ho_ten">Họ tên người nhận</label>
        <input id="ho_ten" name="ho_ten" value="<?php echo htmlspecialchars($values['ho_ten'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        <?php if (!empty($errors['ho_ten'])): ?><small class="field-error"><?php echo htmlspecialchars($errors['ho_ten'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>

        <label for="sdt">Số điện thoại</label>
        <input id="sdt" name="sdt" value="<?php echo htmlspecialchars($values['so_dien_thoai'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        <?php if (!empty($errors['sdt'])): ?><small class="field-error"><?php echo htmlspecialchars($errors['sdt'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>

        <label for="dia_chi">Địa chỉ</label>
        <input id="dia_chi" name="dia_chi" value="<?php echo htmlspecialchars($values['dia_chi'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        <?php if (!empty($errors['dia_chi'])): ?><small class="field-error"><?php echo htmlspecialchars($errors['dia_chi'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>

        <label for="cong_ty">Công ty</label>
        <input id="cong_ty" name="cong_ty" value="<?php echo htmlspecialchars($values['cong_ty'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label for="quoc_gia">Quốc gia</label>
        <select id="quoc_gia" name="quoc_gia" required>
            <option value="Vietnam" <?php echo ($values['quoc_gia'] ?? '') === 'Vietnam' ? 'selected' : ''; ?>>Việt Nam</option>
            <option value="Japan" <?php echo ($values['quoc_gia'] ?? '') === 'Japan' ? 'selected' : ''; ?>>Nhật Bản</option>
            <option value="Korea" <?php echo ($values['quoc_gia'] ?? '') === 'Korea' ? 'selected' : ''; ?>>Hàn Quốc</option>
        </select>
        <?php if (!empty($errors['quoc_gia'])): ?><small class="field-error"><?php echo htmlspecialchars($errors['quoc_gia'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>

        <label for="ma_zip">Mã Zip</label>
        <input id="ma_zip" name="ma_zip" value="<?php echo htmlspecialchars($values['ma_zip'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

        <label class="default-check">
            <input type="checkbox" name="mac_dinh" <?php echo !empty($values['mac_dinh']) ? 'checked' : ''; ?>>
            Đặt làm địa chỉ mặc định
        </label>

        <button type="submit">Lưu địa chỉ</button>
        <a href="taikhoan.php">Quay lại tài khoản</a>
    </form>
</main>
<?php include 'footer.php'; ?>
</body>
</html>
