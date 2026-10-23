
<?php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "ttshop";
$port = 3306;

// Kết nối cơ sở dữ liệu MySQL
$conn = mysqli_connect(
    $servername,
    $username,
    $password,
    $dbname,
    $port
);

if (!$conn) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}

// Thiết lập tiếng Việt có dấu
mysqli_set_charset($conn, "utf8mb4");
?>
