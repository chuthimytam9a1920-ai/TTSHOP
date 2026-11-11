<?php
include "check_admin.php";
include "../connect.php";

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT image FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $product = mysqli_fetch_assoc($result);

    if ($product && !empty($product['image'])) {
        $imagePath = "../images/" . $product['image'];

        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    $stmtDelete = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmtDelete, "i", $id);
    mysqli_stmt_execute($stmtDelete);
}

header("Location: products.php");
exit();
?>