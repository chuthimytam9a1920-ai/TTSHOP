
<?php
session_start();

// Nhận thông báo từ trang xử lý gửi email
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
                Chúng tôi sẽ gửi cho bạn một email chứa
                liên kết để đặt lại mật khẩu.
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

            <form action="xulyquenmatkhau.php" method="POST">

                <div class="form-group">
                    <label for="email">Email</label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Nhập email của bạn"
                        maxlength="254"
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

</body>
</html>
