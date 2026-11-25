<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/validate_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dangky.php');
    exit();
}

unset(
    $_SESSION['is_login'],
    $_SESSION['user_id'],
    $_SESSION['user_ho'],
    $_SESSION['user_name'],
    $_SESSION['user_email'],
    $_SESSION['user_sdt'],
    $_SESSION['user_ngaysinh'],
    $_SESSION['user_diachi']
);

$ho = normalize_person_name($_POST['ho'] ?? '');
$ten = normalize_person_name($_POST['ten'] ?? '');
$email = strtolower(trim($_POST['register_email'] ?? ''));
$sdt = normalize_vn_phone($_POST['sdt'] ?? '');
$matkhau = (string)($_POST['register_password'] ?? '');
$matkhauNhapLai = (string)($_POST['register_password_confirm'] ?? '');
$customerType = ($_POST['customer_type'] ?? 'ca-nhan') === 'uu-tien' ? 'uu-tien' : 'ca-nhan';
$tenCongTy = trim($_POST['ten_cong_ty'] ?? '');
$maSoThue = trim($_POST['ma_so_thue'] ?? '');
$diaChiCty = trim($_POST['dia_chi_cty'] ?? '');
$emailCty = strtolower(trim($_POST['email_cty'] ?? ''));
$errors = array();

if (!validate_person_name($ho)) {
    $errors['ho'] = validation_message('person_first');
}
if (!validate_person_name($ten)) {
    $errors['ten'] = validation_message('person_last');
}
if (!validate_personal_email($email)) {
    $errors['register_email'] = validation_message('email_invalid');
}
if (!is_valid_vn_phone($sdt)) {
    $errors['sdt'] = phone_validation_message();
}
$passwordError = password_blocking_error($matkhau);
if ($passwordError !== null) {
    $errors['register_password'] = $passwordError;
}
if ($matkhau !== $matkhauNhapLai) {
    $errors['register_password_confirm'] = validation_message('password_confirm');
}

if ($customerType === 'uu-tien') {
    if (!validate_company_name($tenCongTy)) {
        $errors['ten_cong_ty'] = validation_message('company_name');
    }
    if (!validate_tax_code($maSoThue)) {
        $errors['ma_so_thue'] = validation_message('tax_code');
    }
    if (!validate_company_address($diaChiCty)) {
        $errors['dia_chi_cty'] = validation_message('company_address');
    }
    if ($emailCty === $email) {
        $errors['email_cty'] = validation_message('email_company_same');
    } elseif (!validate_company_email($emailCty, $email)) {
        $errors['email_cty'] = validation_message('email_company_invalid');
    }
} else {
    $tenCongTy = '';
    $maSoThue = '';
    $diaChiCty = '';
    $emailCty = '';
}

$oldValues = array(
    'ho' => $ho,
    'ten' => $ten,
    'register_email' => $email,
    'sdt' => $sdt,
    'ten_cong_ty' => $tenCongTy,
    'ma_so_thue' => $maSoThue,
    'dia_chi_cty' => $diaChiCty,
    'email_cty' => $emailCty,
    'customer_type' => $customerType
);

if ($errors) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_old'] = $oldValues;
    header('Location: dangky.php');
    exit();
}

$currentSql = '';
try {
    $currentSql = 'SELECT id FROM users WHERE email = ? LIMIT 1';
    $checkEmail = $conn->prepare($currentSql);
    $checkEmail->bind_param('s', $email);
    $checkEmail->execute();
    $checkEmail->store_result();
    if ($checkEmail->num_rows > 0) {
        $errors['register_email'] = validation_message('email_taken');
    }
    $checkEmail->close();

    if (is_valid_vn_phone($sdt) && is_phone_in_use($conn, $sdt)) {
        $errors['sdt'] = validation_message('phone_taken');
    }

    if ($customerType === 'uu-tien') {
        $currentSql = 'SELECT id FROM users WHERE ma_so_thue = ? LIMIT 1';
        $checkTaxCode = $conn->prepare($currentSql);
        $checkTaxCode->bind_param('s', $maSoThue);
        $checkTaxCode->execute();
        $checkTaxCode->store_result();
        if ($checkTaxCode->num_rows > 0) {
            $errors['ma_so_thue'] = validation_message('tax_code_taken');
        }
        $checkTaxCode->close();

        $currentSql = 'SELECT id FROM users WHERE email_cty = ? LIMIT 1';
        $checkCompanyEmail = $conn->prepare($currentSql);
        $checkCompanyEmail->bind_param('s', $emailCty);
        $checkCompanyEmail->execute();
        $checkCompanyEmail->store_result();
        if ($checkCompanyEmail->num_rows > 0) {
            $errors['email_cty'] = validation_message('email_company_taken');
        }
        $checkCompanyEmail->close();
    }
} catch (Throwable $exception) {
    error_log(sprintf(
        '%s:%d: %s | mysqli_error=%s | SQL=%s',
        __FILE__,
        __LINE__,
        $exception->getMessage(),
        mysqli_error($conn),
        $currentSql
    ));

    $duplicateField = duplicate_constraint_field($exception);
    $duplicateMessages = array(
        'sdt' => array('sdt', 'phone_taken'),
        'email' => array('register_email', 'email_taken'),
        'ma_so_thue' => array('ma_so_thue', 'tax_code_taken'),
        'email_cty' => array('email_cty', 'email_company_taken')
    );
    if ($duplicateField !== null && isset($duplicateMessages[$duplicateField])) {
        list($field, $messageKey) = $duplicateMessages[$duplicateField];
        $_SESSION['register_errors'] = array($field => validation_message($messageKey));
        $_SESSION['register_old'] = $oldValues;
        header('Location: dangky.php');
        exit();
    }

    $_SESSION['error_message'] = validation_message('system');
    $_SESSION['register_old'] = $oldValues;
    header('Location: dangky.php');
    exit();
}

if ($errors) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_old'] = $oldValues;
    header('Location: dangky.php');
    exit();
}

$insertSql = $customerType === 'uu-tien'
    ? 'INSERT INTO users
        (ho, ten, email, sdt, matkhau, ten_cong_ty, ma_so_thue, dia_chi_cty, email_cty)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    : 'INSERT INTO users (ho, ten, email, sdt, matkhau) VALUES (?, ?, ?, ?, ?)';
$verifySql = 'SELECT id, ho, ten, email, sdt, ngaysinh, diachi
    FROM users WHERE id = ? LIMIT 1';
$currentSql = $insertSql;
$transactionStarted = false;
$insertStatement = null;
$databaseName = '';
$affectedRows = -1;
$newId = 0;
$mysqliError = '';
$executeSucceeded = false;
$passwordHash = password_hash($matkhau, PASSWORD_DEFAULT);

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $databaseStatement = $conn->prepare('SELECT DATABASE()');
    $databaseStatement->execute();
    $databaseStatement->bind_result($databaseName);
    $databaseStatement->fetch();
    $databaseStatement->close();

    $insertStatement = $conn->prepare($insertSql);
    if ($customerType === 'uu-tien') {
        $insertStatement->bind_param(
            'sssssssss',
            $ho,
            $ten,
            $email,
            $sdt,
            $passwordHash,
            $tenCongTy,
            $maSoThue,
            $diaChiCty,
            $emailCty
        );
    } else {
        $insertStatement->bind_param('sssss', $ho, $ten, $email, $sdt, $passwordHash);
    }

    $executeSucceeded = $insertStatement->execute() === true;
    $affectedRows = mysqli_stmt_affected_rows($insertStatement);
    $newId = mysqli_insert_id($conn);
    $mysqliError = mysqli_error($conn);

    error_log(sprintf(
        '%s:%d [DEBUG TẠM] database=%s affected_rows=%d insert_id=%d mysqli_error=%s SQL=%s',
        __FILE__,
        __LINE__,
        $databaseName,
        $affectedRows,
        $newId,
        $mysqliError,
        $insertSql
    ));

    if ($executeSucceeded !== true || $affectedRows !== 1) {
        throw new RuntimeException('INSERT users không tạo đúng một dòng. ' . $mysqliError);
    }

    $conn->commit();
    $transactionStarted = false;

    if ($newId <= 0) {
        throw new RuntimeException('mysqli_insert_id trả về giá trị không hợp lệ.');
    }

    $currentSql = $verifySql;
    $verifyStatement = $conn->prepare($verifySql);
    $verifyStatement->bind_param('i', $newId);
    $verifyStatement->execute();
    $verifyUser = $verifyStatement->get_result()->fetch_assoc();
    $verifyStatement->close();

    if (!$verifyUser || (int)$verifyUser['id'] !== $newId) {
        throw new RuntimeException('Không tìm thấy dòng users vừa thêm sau khi xác nhận.');
    }
} catch (Throwable $exception) {
    $mysqliError = mysqli_error($conn);
    error_log(sprintf(
        '%s:%d: %s | database=%s | affected_rows=%d | insert_id=%d | mysqli_error=%s | SQL=%s',
        __FILE__,
        __LINE__,
        $exception->getMessage(),
        $databaseName,
        $affectedRows,
        mysqli_insert_id($conn),
        $mysqliError,
        $currentSql
    ));

    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(sprintf('%s:%d: %s', __FILE__, __LINE__, $rollbackException->getMessage()));
        }
    }
    if ($insertStatement instanceof mysqli_stmt) {
        $insertStatement->close();
    }

    $duplicateField = duplicate_constraint_field($exception);
    $duplicateMessages = array(
        'sdt' => array('sdt', 'phone_taken'),
        'email' => array('register_email', 'email_taken'),
        'ma_so_thue' => array('ma_so_thue', 'tax_code_taken'),
        'email_cty' => array('email_cty', 'email_company_taken')
    );
    if ($duplicateField !== null && isset($duplicateMessages[$duplicateField])) {
        list($field, $messageKey) = $duplicateMessages[$duplicateField];
        $_SESSION['register_errors'] = array($field => validation_message($messageKey));
        $_SESSION['register_old'] = $oldValues;
        header('Location: dangky.php');
        exit();
    }

    $_SESSION['error_message'] = validation_message('system');
    $_SESSION['register_old'] = $oldValues;
    header('Location: dangky.php');
    exit();
}

$insertStatement->close();
session_regenerate_id(true);
$_SESSION['is_login'] = true;
$_SESSION['user_id'] = $newId;
$_SESSION['user_ho'] = $verifyUser['ho'];
$_SESSION['user_name'] = $verifyUser['ten'];
$_SESSION['user_email'] = $verifyUser['email'];
$_SESSION['user_sdt'] = $verifyUser['sdt'];
$_SESSION['user_ngaysinh'] = $verifyUser['ngaysinh'];
$_SESSION['user_diachi'] = $verifyUser['diachi'];

header('Location: taikhoan.php');
exit();
