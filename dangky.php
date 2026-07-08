<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$emailFromFooter = trim($_GET['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký - TT SHOP</title>

    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="./styledangky.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

<?php include "header.php"; ?>

<section class="register-page">
    <div class="register-box">
        <h2>ĐĂNG KÝ</h2>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']); 
                ?>
            </div>
        <?php endif; ?>

        <p class="customer-title">Bạn là khách hàng:</p>

        <div class="customer-tabs">
            <button type="button" id="tab-ca-nhan" class="active" onclick="toggleCustomerType('ca-nhan')">
                Khách hàng Cá nhân
            </button>

            <button type="button" id="tab-uu-tien" onclick="toggleCustomerType('uu-tien')">
                Khách hàng Ưu tiên (Xuất hoá đơn)
            </button>
        </div>

        <form class="register-form" action="xulydangky.php" method="POST" autocomplete="off">

            <div class="form-row">
                <div class="form-group">
                    <label>Họ <span>*</span>:</label>
                    <input type="text" name="ho" placeholder="Họ" required>
                </div>

                <div class="form-group">
                    <label>Tên <span>*</span>:</label>
                    <input type="text" name="ten" placeholder="Tên" required>
                </div>
            </div>

            <div id="uu-tien-fields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Tên công ty <span>*</span>:</label>
                        <input type="text" name="ten_cong_ty" id="input-cty" placeholder="Tên công ty">
                    </div>

                    <div class="form-group">
                        <label>Mã số thuế <span>*</span>:</label>
                        <input type="text" name="ma_so_thue" id="input-mst" placeholder="Mã số thuế">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Địa chỉ công ty <span>*</span>:</label>
                        <input type="text" name="dia_chi_cty" id="input-dc" placeholder="Địa chỉ công ty">
                    </div>

                    <div class="form-group">
                        <label>Email công ty <span>*</span>:</label>
                        <input type="email" name="email_cty" id="input-email-cty" placeholder="Email công ty">
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email cá nhân <span>*</span>:</label>

                    <input 
                        type="email" 
                        name="register_email" 
                        placeholder="Email cá nhân"
                        value="<?php echo htmlspecialchars($emailFromFooter); ?>"
                        pattern="[a-zA-Z0-9._%+-]+@gmail\.com$"
                        title="Email phải đúng định dạng @gmail.com"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Số điện thoại <span>*</span>:</label>
                    <input 
                        type="text" 
                        name="sdt" 
                        placeholder="Số điện thoại" 
                        pattern="[0-9]{10}" 
                        maxlength="10" 
                        title="Số điện thoại phải gồm đúng 10 số" 
                        required
                    >
                </div>
            </div>

            <div class="form-group full">
                <label>Mật khẩu <span>*</span>:</label>
                <input 
                    type="password" 
                    name="register_password" 
                    placeholder="Mật khẩu" 
                    autocomplete="new-password" 
                    required
                >
            </div>

            <button type="submit" name="btn_register" class="register-btn">
                Đăng ký
            </button>

            <p class="login-text">
                Bạn đã có tài khoản?
                <a href="dangnhap.php">Đăng nhập tại đây</a>
            </p>

            <div class="social-login">
                <a href="#" class="social-btn google">
                    <i class="fab fa-google"></i> Google
                </a>

                <a href="#" class="social-btn facebook">
                    <i class="fab fa-facebook-f"></i> Facebook
                </a>
            </div>

        </form>
    </div> 
</section>

<script>
function toggleCustomerType(type) {
    const fields = document.getElementById('uu-tien-fields');
    const tabPersonal = document.getElementById('tab-ca-nhan');
    const tabPriority = document.getElementById('tab-uu-tien');
    
    const inputs = [
        document.getElementById('input-cty'),
        document.getElementById('input-mst'),
        document.getElementById('input-dc'),
        document.getElementById('input-email-cty')
    ];

    if (type === 'uu-tien') {
        fields.style.display = 'block';
        tabPriority.classList.add('active');
        tabPersonal.classList.remove('active');

        inputs.forEach(function(input) {
            input.setAttribute('required', '');
        });

    } else {
        fields.style.display = 'none';
        tabPersonal.classList.add('active');
        tabPriority.classList.remove('active');

        inputs.forEach(function(input) {
            input.removeAttribute('required');
        });
    }
}
</script>

<?php include "footer.php"; ?>

</body>
</html>