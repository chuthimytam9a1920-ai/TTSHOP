<?php session_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - TT SHOP</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="./style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styledangnhap.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"> 
</head>
<body>

<?php include "header.php"; ?>

<section class="login-page">
    <div class="login-box">
        <h2>ĐĂNG NHẬP</h2>
<?php if (isset($_SESSION['login_error'])): ?>
    <div class="error-msg">
        <i class="fas fa-exclamation-circle"></i>
        <?php
            echo $_SESSION['login_error'];
            unset($_SESSION['login_error']);
        ?>
    </div>
<?php endif; ?>
        <form action="xulydangnhap.php" method="POST" autocomplete="off">

            <div class="form-group">
                <label for="email">Email <span>*</span></label>
                <input type="email" id="email" name="email" placeholder="Nhập email của bạn" required autocomplete="none">
            </div>

            <div class="form-group">
                <label for="matkhau">Mật khẩu <span>*</span></label>
                <input type="password" id="matkhau" name="matkhau" placeholder="Nhập mật khẩu" required autocomplete="new-password">
            </div>

            <p class="forgot">
                Quên mật khẩu? Nhấn vào <a href="quenmatkhau.php">đây</a>
            </p>

            <button type="submit" class="login-btn">Đăng nhập</button>

            <p class="register-text">
                Bạn chưa có tài khoản? 
                <a href="dangky.php">Đăng ký tại đây</a>
            </p>

            <div class="social-login">
                <button type="button" class="google">
                    <i class="fab fa-google"></i> Google
                </button>

                <button type="button" class="facebook">
                    <i class="fab fa-facebook"></i> Facebook
                </button>
            </div>

        </form>
    </div>
</section>

<?php include "footer.php"; ?>

</body>
</html>