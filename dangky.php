<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$emailFromFooter = strtolower(trim($_GET['email'] ?? ''));
$registerErrors = $_SESSION['register_errors'] ?? array();
$registerOld = $_SESSION['register_old'] ?? array();
$registerMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['register_errors'], $_SESSION['register_old'], $_SESSION['error_message']);

function registrationFieldError($field, $errors)
{
    if (!isset($errors[$field])) {
        return;
    }
    echo '<small class="field-error" data-error-for="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '" aria-live="polite">' . htmlspecialchars($errors[$field], ENT_QUOTES, 'UTF-8') . '</small>';
}
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
</head>

<body>

<?php include "header.php"; ?>

<section class="register-page">
    <div class="register-box">
        <h2>ĐĂNG KÝ</h2>

        <?php if ($registerMessage !== ''): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($registerMessage, ENT_QUOTES, 'UTF-8'); ?>
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

        <form class="register-form" action="xulydangky.php" method="POST" autocomplete="off" novalidate>
            <input type="hidden" name="customer_type" id="customer-type" value="<?php echo htmlspecialchars($registerOld['customer_type'] ?? 'ca-nhan', ENT_QUOTES, 'UTF-8'); ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Họ <span>*</span>:</label>
                    <input type="text" name="ho" placeholder="Họ" value="<?php echo htmlspecialchars($registerOld['ho'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-validate="person-name" data-label="họ" required>
                    <?php registrationFieldError('ho', $registerErrors); ?>
                </div>

                <div class="form-group">
                    <label>Tên <span>*</span>:</label>
                    <input type="text" name="ten" placeholder="Tên" value="<?php echo htmlspecialchars($registerOld['ten'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-validate="person-name" data-label="tên" required>
                    <?php registrationFieldError('ten', $registerErrors); ?>
                </div>
            </div>

            <div id="uu-tien-fields" style="display: none;">
                <div class="form-row">
                    <div class="form-group">
                        <label>Tên công ty <span>*</span>:</label>
                        <input type="text" name="ten_cong_ty" id="input-cty" placeholder="Tên công ty" value="<?php echo htmlspecialchars($registerOld['ten_cong_ty'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-validate="company-name">
                        <?php registrationFieldError('ten_cong_ty', $registerErrors); ?>
                    </div>

                    <div class="form-group">
                        <label>Mã số thuế <span>*</span>:</label>
                        <input type="text" name="ma_so_thue" id="input-mst" placeholder="Mã số thuế" value="<?php echo htmlspecialchars($registerOld['ma_so_thue'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-validate="tax-code">
                        <?php registrationFieldError('ma_so_thue', $registerErrors); ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Địa chỉ công ty <span>*</span>:</label>
                        <input type="text" name="dia_chi_cty" id="input-dc" placeholder="Địa chỉ công ty" value="<?php echo htmlspecialchars($registerOld['dia_chi_cty'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-validate="company-address">
                        <?php registrationFieldError('dia_chi_cty', $registerErrors); ?>
                    </div>

                    <div class="form-group">
                        <label>Email công ty <span>*</span>:</label>
                        <input type="email" name="email_cty" id="input-email-cty" placeholder="Email công ty" value="<?php echo htmlspecialchars($registerOld['email_cty'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-validate="company-email">
                        <?php registrationFieldError('email_cty', $registerErrors); ?>
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
                        value="<?php echo htmlspecialchars($registerOld['register_email'] ?? $emailFromFooter, ENT_QUOTES, 'UTF-8'); ?>"
                        data-validate="personal-email"
                        data-label="email cá nhân"
                        required
                    >
                    <?php registrationFieldError('register_email', $registerErrors); ?>
                </div>

                <div class="form-group">
                    <label>Số điện thoại <span>*</span>:</label>
                    <input type="text" name="sdt" placeholder="Số điện thoại" value="<?php echo htmlspecialchars($registerOld['sdt'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-validate="phone" data-label="số điện thoại" autocomplete="tel" required>
                    <?php registrationFieldError('sdt', $registerErrors); ?>
                </div>
            </div>

            <div class="form-group full">
                <label>Mật khẩu <span>*</span>:</label>
                <div class="password-input-wrap">
                    <input type="password" id="register-password" name="register_password" placeholder="Mật khẩu" data-validate="strong-password" data-label="mật khẩu" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-password-toggle aria-controls="register-password" aria-label="Hiện mật khẩu" aria-pressed="false">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <?php registrationFieldError('register_password', $registerErrors); ?>
                <div data-password-warnings aria-live="polite"></div>
            </div>

            <div class="form-group full">
                <label>Nhập lại mật khẩu <span>*</span>:</label>
                <div class="password-input-wrap">
                    <input type="password" id="register-password-confirm" name="register_password_confirm" placeholder="Nhập lại mật khẩu" data-validate="password-confirm" data-label="mật khẩu nhập lại" autocomplete="new-password" required>
                    <button type="button" class="password-toggle" data-password-toggle aria-controls="register-password-confirm" aria-label="Hiện mật khẩu" aria-pressed="false">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <?php registrationFieldError('register_password_confirm', $registerErrors); ?>
            </div>

            <button type="submit" name="btn_register" value="1" class="register-btn">
                Đăng ký
            </button>

            <p class="login-text">
                Bạn đã có tài khoản?
                <a href="dangnhap.php">Đăng nhập tại đây</a>
            </p>

        </form>
    </div> 
</section>

<script>
function toggleCustomerType(type) {
    const fields = document.getElementById('uu-tien-fields');
    const tabPersonal = document.getElementById('tab-ca-nhan');
    const tabPriority = document.getElementById('tab-uu-tien');
    document.getElementById('customer-type').value = type;
    
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
toggleCustomerType(document.getElementById('customer-type').value);
</script>

<?php include "footer.php"; ?>

</body>
</html>