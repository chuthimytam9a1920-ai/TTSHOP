<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['reset_csrf_token']) ||
    !is_string($_SESSION['reset_csrf_token']) ||
    strlen($_SESSION['reset_csrf_token']) !== 64
) {
    $_SESSION['reset_csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_SESSION['reset_success'] ?? '';
$error = $_SESSION['reset_error'] ?? '';

unset($_SESSION['reset_success'], $_SESSION['reset_error']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Quên mật khẩu - TT SHOP</title>

    <link rel="stylesheet" href="stylequenmatkhau.css">
</head>

<body>

    <div class="reset-container">

        <div class="reset-box">

            <div class="shop-logo">
                <a href="index.php">TT SHOP</a>
            </div>

            <h1>ĐẶT LẠI MẬT KHẨU</h1>

            <p class="description">
                Nhập email đã đăng ký. Chúng tôi sẽ gửi mã xác minh để bạn đặt lại mật khẩu.
            </p>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form action="xulyquenmatkhau.php" method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['reset_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="email">Email</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Nhập email của bạn"
                        data-label="email"
                        maxlength="100"
                        autocomplete="email"
                        required
                    >
                </div>

                <button type="submit" class="reset-btn">
                    GỬI YÊU CẦU
                </button>

            </form>

            <div class="back-login">
                <a href="dangnhap.php">Quay lại đăng nhập</a>
            </div>

            <div class="register-link">
                Bạn chưa có tài khoản?
                <a href="dangky.php">Đăng ký tại đây</a>
            </div>

        </div>

    </div>

        <script src="validate.js"></script>
</body>
</html>
