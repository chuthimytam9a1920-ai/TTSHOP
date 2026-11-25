<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/validate_helpers.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$isAddressUpdate = isset($_POST['full_name']);
$sdt = normalize_vn_phone($_POST['sdt'] ?? '');
$diachi = trim($_POST['diachi'] ?? '');
$errorField = $isAddressUpdate ? 'edit_address_sdt' : 'profile_sdt';
$fieldErrors = array();
$ngaysinh = null;

if ($isAddressUpdate) {
    $fullName = normalize_person_name($_POST['full_name'] ?? '');
    $nameParts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
    if (count($nameParts) < 2) {
        $fieldErrors['edit_address_name'] = validation_message('full_name_required');
    } else {
        $ten = array_pop($nameParts);
        $ho = implode(' ', $nameParts);
        if (!validate_person_name($ho) || !validate_person_name($ten)) {
            $fieldErrors['edit_address_name'] = validation_message('person_full_name');
        }
    }
} else {
    $ho = normalize_person_name($_POST['ho'] ?? '');
    $ten = normalize_person_name($_POST['ten'] ?? '');
    if (!validate_person_name($ho)) {
        $fieldErrors['profile_ho'] = validation_message('person_first');
    }
    if (!validate_person_name($ten)) {
        $fieldErrors['profile_ten'] = validation_message('person_last');
    }

    $rawBirthDate = trim($_POST['ngaysinh'] ?? '');
    if ($rawBirthDate !== '') {
        $birthDate = DateTime::createFromFormat('!Y-m-d', $rawBirthDate);
        $birthDateErrors = DateTime::getLastErrors();
        $invalidBirthDate = $birthDate === false
            || ($birthDateErrors !== false
                && ($birthDateErrors['warning_count'] > 0 || $birthDateErrors['error_count'] > 0))
            || $birthDate->format('Y-m-d') !== $rawBirthDate
            || $birthDate < new DateTime('1900-01-01')
            || $birthDate > new DateTime('today');

        if ($invalidBirthDate) {
            $fieldErrors['profile_ngaysinh'] = validation_message('birth_date_invalid');
        } else {
            $ngaysinh = $rawBirthDate;
        }
    }
}

if (!is_valid_vn_phone($sdt)) {
    $fieldErrors[$errorField] = validation_message('phone_invalid');
}

if ($fieldErrors) {
    $_SESSION['account_field_errors'] = $fieldErrors;
    header('Location: taikhoan.php');
    exit();
}

try {
    if (is_phone_in_use($conn, $sdt, $userId)) {
        $_SESSION['account_field_errors'] = array($errorField => validation_message('phone_taken'));
        header('Location: taikhoan.php');
        exit();
    }

    if ($isAddressUpdate) {
        $statement = $conn->prepare(
            'UPDATE users SET ho = ?, ten = ?, sdt = ?, diachi = ? WHERE id = ?'
        );
        $statement->bind_param('ssssi', $ho, $ten, $sdt, $diachi, $userId);
    } else {
        $statement = $conn->prepare(
            'UPDATE users SET ho = ?, ten = ?, sdt = ?, ngaysinh = ?, diachi = ? WHERE id = ?'
        );
        $statement->bind_param('sssssi', $ho, $ten, $sdt, $ngaysinh, $diachi, $userId);
    }
    if ($statement->execute() !== true) {
        throw new RuntimeException('Không thể cập nhật thông tin tài khoản.');
    }
    $statement->close();

    $_SESSION['user_ho'] = $ho;
    $_SESSION['user_name'] = $ten;
    $_SESSION['user_sdt'] = $sdt;
    $_SESSION['user_diachi'] = $diachi;
    if (!$isAddressUpdate) {
        $_SESSION['user_ngaysinh'] = $ngaysinh;
    }
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', __FILE__, __LINE__, $exception->getMessage()));
    $message = duplicate_constraint_field($exception) === 'sdt'
        ? validation_message('phone_taken')
        : validation_message('system');
    $_SESSION['account_field_errors'] = array($errorField => $message);
}

header('Location: taikhoan.php');
exit();
