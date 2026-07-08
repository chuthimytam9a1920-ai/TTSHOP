<?php
$servername = "localhost";
$username = "root"; // Mặc định của XAMPP
$password = "";     // Mặc định XAMPP để trống
$dbname = "ttshop";

$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}
// Thiết lập tiếng Việt có dấu
mysqli_set_charset($conn, "utf8");
?>