<?php

function normalize_vn_phone($phone)
{
    $phone = preg_replace('/[\s.\-]+/u', '', trim((string)$phone));

    if (strpos($phone, '+84') === 0) {
        $phone = '0' . substr($phone, 3);
    } elseif (strpos($phone, '84') === 0) {
        $phone = '0' . substr($phone, 2);
    }

    return $phone;
}

function validation_messages()
{
    return array(
        'person_first' => 'Họ không hợp lệ.',
        'person_last' => 'Tên không hợp lệ.',
        'person_full_name' => 'Họ tên không hợp lệ.',
        'full_name_required' => 'Vui lòng nhập đầy đủ họ và tên.',
        'birth_date_invalid' => 'Ngày sinh không hợp lệ.',
        'phone_invalid' => 'Số điện thoại không hợp lệ. Vui lòng nhập đúng.',
        'phone_taken' => 'Số điện thoại này đã được sử dụng.',
        'email_invalid' => 'Email không hợp lệ.',
        'email_taken' => 'Email này đã được đăng ký.',
        'email_company_invalid' => 'Email công ty không hợp lệ.',
        'email_company_same' => 'Email công ty phải khác email cá nhân.',
        'email_company_taken' => 'Email công ty này đã được đăng ký.',
        'password_required' => 'Vui lòng nhập mật khẩu.',
        'password_min' => 'Mật khẩu phải có ít nhất 6 ký tự.',
        'password_max' => 'Mật khẩu không được quá 64 ký tự.',
        'password_whitespace' => 'Mật khẩu không được chứa khoảng trắng.',
        'password_confirm' => 'Mật khẩu nhập lại không khớp.',
        'password_warning_length' => 'Nên dùng ít nhất 8 ký tự.',
        'password_warning_uppercase' => 'Mật khẩu thiếu chữ in hoa (A-Z).',
        'password_warning_lowercase' => 'Mật khẩu thiếu chữ thường (a-z).',
        'password_warning_number' => 'Mật khẩu thiếu chữ số (0-9).',
        'password_warning_special' => 'Mật khẩu thiếu ký tự đặc biệt (ví dụ @ # $ !).',
        'password_warning_email' => 'Mật khẩu chứa phần đầu email của bạn.',
        'password_warning_phone' => 'Mật khẩu chứa số điện thoại của bạn.',
        'password_warning_name' => 'Mật khẩu chứa họ tên của bạn.',
        'password_warning_common' => 'Mật khẩu này quá phổ biến, dễ bị đoán ra.',
        'login_credentials' => 'Email hoặc mật khẩu không đúng.',
        'reset_token_invalid' => 'Liên kết đã hết hạn hoặc không hợp lệ.',
        'reset_csrf_expired' => 'Phiên làm việc đã hết hạn. Vui lòng thử lại.',
        'reset_too_many_requests' => 'Bạn thao tác quá nhiều lần. Vui lòng thử lại sau ít phút.',
        'reset_email_sent' => 'Nếu email đã được đăng ký, chúng tôi đã gửi mã xác minh đặt lại mật khẩu. Vui lòng kiểm tra hộp thư (kể cả mục Spam).',
        'reset_email_failed' => 'Không gửi được email. Vui lòng thử lại sau.',
        'reset_otp_invalid' => 'Mã xác minh không đúng hoặc đã hết hạn.',
        'reset_otp_required' => 'Vui lòng nhập mã xác minh.',
        'reset_otp_format' => 'Mã xác minh phải gồm 6 chữ số.',
        'reset_password_same' => 'Mật khẩu mới không được trùng mật khẩu cũ.',
        'reset_system' => 'Hệ thống tạm thời không thể xử lý yêu cầu.',
        'company_name' => 'Tên công ty không hợp lệ.',
        'tax_code' => 'Mã số thuế không hợp lệ.',
        'tax_code_taken' => 'Mã số thuế này đã được đăng ký.',
        'company_address' => 'Địa chỉ công ty không hợp lệ.',
        'required' => 'Vui lòng nhập thông tin này.',
        'reset_wait' => 'Vui lòng chờ 60 giây rồi thử lại.',
        'recipient_required' => 'Vui lòng nhập họ tên người nhận.',
        'address_required' => 'Vui lòng nhập địa chỉ giao hàng.',
        'locations_required' => 'Vui lòng chọn đầy đủ địa điểm giao hàng.',
        'payment_required' => 'Vui lòng chọn phương thức thanh toán hợp lệ.',
        'select_required' => 'Vui lòng chọn một mục.',
        'system' => 'Hệ thống đang bận, vui lòng thử lại sau.'
    );
}

function validation_message($key)
{
    $messages = validation_messages();
    return $messages[$key] ?? $messages['system'];
}

function duplicate_constraint_field($exception)
{
    if ((int)$exception->getCode() !== 1062) {
        return null;
    }

    $message = (string)$exception->getMessage();
    $keyPosition = stripos($message, 'for key');
    if ($keyPosition === false) {
        return null;
    }

    preg_match_all('/[a-z0-9_]+/i', substr($message, $keyPosition + 7), $keyParts);
    if (empty($keyParts[0])) {
        return null;
    }
    $key = strtolower(end($keyParts[0]));

    if (strpos($key, 'sdt') !== false) {
        return 'sdt';
    }
    if (strpos($key, 'ma_so_thue') !== false || strpos($key, 'tax_code') !== false || strpos($key, 'mst') !== false) {
        return 'ma_so_thue';
    }
    if (strpos($key, 'email_cty') !== false) {
        return 'email_cty';
    }
    if (strpos($key, 'email') !== false) {
        return 'email';
    }

    return null;
}

function is_valid_vn_phone($phone)
{
    // Chỉ xác minh định dạng và đầu số; số có đang hoạt động cần được xác thực bằng OTP/SMS.
    $phone = normalize_vn_phone($phone);

    if (preg_match('/^0(3[2-9]|5[25689]|7[06-9]|8[1-9]|9[0-9])[0-9]{7}$/', $phone) !== 1) {
        return false;
    }

    $tail = substr($phone, 3);
    if (strlen($tail) === 7 && preg_match('/^([0-9])\1{6}$/', $tail) === 1) {
        return false;
    }

    $increasing = true;
    $decreasing = true;
    for ($index = 1; $index < strlen($phone); $index++) {
        if ((int)$phone[$index] !== (int)$phone[$index - 1] + 1) {
            $increasing = false;
        }
        if ((int)$phone[$index] !== (int)$phone[$index - 1] - 1) {
            $decreasing = false;
        }
    }

    return !$increasing && !$decreasing;
}

function validate_personal_email($email)
{
    $email = strtolower(trim((string)$email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        && preg_match('/^(?=[a-z0-9.]*[a-z])[a-z0-9](?:[a-z0-9.]{4,28}[a-z0-9])@gmail\.com$/', $email) === 1;
}

function is_phone_in_use($conn, $phone, $excludeUserId = null)
{
    $phone = normalize_vn_phone($phone);
    if ($excludeUserId === null) {
        $statement = $conn->prepare('SELECT id FROM users WHERE sdt = ? LIMIT 1');
        $statement->bind_param('s', $phone);
    } else {
        $statement = $conn->prepare('SELECT id FROM users WHERE sdt = ? AND id <> ? LIMIT 1');
        $statement->bind_param('si', $phone, $excludeUserId);
    }

    $statement->execute();
    $statement->store_result();
    $found = $statement->num_rows > 0;
    $statement->close();
    if ($found) {
        return true;
    }

    if ($excludeUserId === null) {
        $legacyStatement = $conn->prepare('SELECT id, sdt FROM users');
    } else {
        $legacyStatement = $conn->prepare('SELECT id, sdt FROM users WHERE id <> ?');
        $legacyStatement->bind_param('i', $excludeUserId);
    }
    $legacyStatement->execute();
    $result = $legacyStatement->get_result();
    while ($user = $result->fetch_assoc()) {
        if (normalize_vn_phone($user['sdt'] ?? '') === $phone) {
            $legacyStatement->close();
            return true;
        }
    }
    $legacyStatement->close();

    return false;
}

function normalize_person_name($name)
{
    $name = trim((string)$name);
    return preg_replace('/\s+/u', ' ', $name);
}

function validate_person_name($name)
{
    $name = normalize_person_name($name);
    return $name !== ''
        && (function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name)) <= 50
        && preg_match('/^(?=.*\p{L})[\p{L}\s\'.-]{1,50}$/u', $name) === 1;
}

function validate_company_name($name)
{
    $name = trim((string)$name);
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
    return $length >= 5
        && $length <= 150
        && preg_match('/\p{L}/u', $name) === 1;
}

function validate_tax_code($taxCode)
{
    return preg_match('/^[0-9]{10}(?:-[0-9]{3})?$/', trim((string)$taxCode)) === 1;
}

function validate_company_address($address)
{
    $address = trim((string)$address);
    $length = function_exists('mb_strlen') ? mb_strlen($address, 'UTF-8') : strlen($address);
    return $length >= 10
        && preg_match('/\p{L}/u', $address) === 1
        && preg_match('/[0-9,]/', $address) === 1;
}

function disposable_email_domains()
{
    return array(
        'mailinator.com',
        'tempmail.com',
        '10minutemail.com',
        'guerrillamail.com',
        'yopmail.com',
        'throwawaymail.com',
        'getnada.com',
        'trashmail.com',
        'maildrop.cc',
        'temp-mail.org',
        'dispostable.com',
        'fakeinbox.com',
        'mohmal.com',
        'minuteinbox.com'
    );
}

function validate_company_email($email, $personalEmail)
{
    $email = strtolower(trim((string)$email));
    $personalEmail = strtolower(trim((string)$personalEmail));

    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $email === $personalEmail) {
        return false;
    }

    $parts = explode('@', $email);
    $domain = end($parts);
    if (in_array($domain, disposable_email_domains(), true)) {
        return false;
    }

    if (function_exists('checkdnsrr')) {
        $hasMxOrA = @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A');
        if (!$hasMxOrA) {
            // Khi DNS không truy cập được, chỉ xác minh cú pháp thay vì báo nhầm địa chỉ sai.
            $dnsAvailable = @checkdnsrr('google.com', 'A');
            if ($dnsAvailable) {
                return false;
            }
        }
    }

    return true;
}

function common_passwords()
{
    return array(
        '123456', '12345678', '123456789', '1234567890', 'password', 'password1',
        'password123', 'qwerty', 'qwerty123', 'abc12345', 'abc123456',
        '11111111', '00000000', '87654321', 'iloveyou', 'admin123',
        'welcome1', 'letmein1', 'monkey123', 'dragon123', 'football1',
        'sunshine1', 'princess1', 'trustno1', 'passw0rd', '654321',
        'matkhau', 'matkhau123', 'matkhau1234', 'dungroi123', 't12345678'
    );
}

function password_blocking_error($password)
{
    $password = (string)$password;
    $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);

    if ($password === '') {
        return validation_message('password_required');
    }
    if ($length < 6) {
        return validation_message('password_min');
    }
    if ($length > 64) {
        return validation_message('password_max');
    }
    if (preg_match('/\s/u', $password) === 1) {
        return validation_message('password_whitespace');
    }

    return null;
}

function password_soft_warnings($password, $email, $phone, $name)
{
    $password = (string)$password;
    if ($password === '') {
        return array();
    }

    $length = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
    $emailLocal = strtolower((string)strstr((string)$email, '@', true));
    $name = normalize_person_name($name);
    $normalizedPhone = normalize_vn_phone($phone);
    $lowerPassword = function_exists('mb_strtolower')
        ? mb_strtolower($password, 'UTF-8')
        : strtolower($password);
    $lowerName = function_exists('mb_strtolower')
        ? mb_strtolower($name, 'UTF-8')
        : strtolower($name);
    $warnings = array();
    if ($length < 8) {
        $warnings[] = validation_message('password_warning_length');
    }
    if (preg_match('/\p{Lu}/u', $password) !== 1) {
        $warnings[] = validation_message('password_warning_uppercase');
    }
    if (preg_match('/\p{Ll}/u', $password) !== 1) {
        $warnings[] = validation_message('password_warning_lowercase');
    }
    if (preg_match('/[0-9]/', $password) !== 1) {
        $warnings[] = validation_message('password_warning_number');
    }
    if (preg_match('/[\p{P}\p{S}]/u', $password) !== 1) {
        $warnings[] = validation_message('password_warning_special');
    }
    if ($emailLocal !== '' && stripos($lowerPassword, $emailLocal) !== false) {
        $warnings[] = validation_message('password_warning_email');
    }
    if ($normalizedPhone !== '' && strpos($password, $normalizedPhone) !== false) {
        $warnings[] = validation_message('password_warning_phone');
    }

    $containsName = false;
    foreach (preg_split('/\s+/u', $lowerName, -1, PREG_SPLIT_NO_EMPTY) as $namePart) {
        $namePartLength = function_exists('mb_strlen') ? mb_strlen($namePart, 'UTF-8') : strlen($namePart);
        $namePartFound = function_exists('mb_strpos')
            ? mb_strpos($lowerPassword, $namePart, 0, 'UTF-8') !== false
            : strpos($lowerPassword, $namePart) !== false;
        if ($namePartLength >= 3 && $namePartFound) {
            $containsName = true;
            break;
        }
    }
    if ($containsName) {
        $warnings[] = validation_message('password_warning_name');
    }
    if (in_array($lowerPassword, array_map('strtolower', common_passwords()), true)) {
        $warnings[] = validation_message('password_warning_common');
    }

    return $warnings;
}

function phone_validation_message()
{
    return validation_message('phone_invalid');
}

