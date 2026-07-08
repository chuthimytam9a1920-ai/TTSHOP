<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../connect.php";

$error = "";

if (isset($_SESSION['admin_login']) && $_SESSION['admin_login'] === true) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $sql = "SELECT * FROM admins WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $admin = mysqli_fetch_assoc($result);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_login'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['ho_ten'];

        header("Location: index.php");
        exit();
    } else {
        $error = "Sai tài khoản hoặc mật khẩu!";
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập Admin - TT SHOP</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #eef5f1;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-box {
            width: 420px;
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }

        h1 {
            text-align: center;
            color: #15985d;
            margin-bottom: 30px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-top: 15px;
        }

        input {
            width: 100%;
            height: 48px;
            padding: 0 12px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin-top: 7px;
            box-sizing: border-box;
            font-size: 16px;
        }

        button {
            width: 100%;
            height: 52px;
            border: none;
            border-radius: 8px;
            background: #15985d;
            color: white;
            font-size: 18px;
            font-weight: bold;
            margin-top: 25px;
            cursor: pointer;
        }

        button:hover {
            background: #0d7d4b;
        }

        .error {
            background: #ffe2e2;
            color: red;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="login-box">
    <h1>TT SHOP ADMIN</h1>

    <?php if (!empty($error)) { ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php } ?>

    <form method="POST">
        <label>Tài khoản</label>
        <input type="text" name="username" required>

        <label>Mật khẩu</label>
        <input type="password" name="password" required>

        <button type="submit">Đăng nhập</button>
    </form>
</div>

</body>
</html>