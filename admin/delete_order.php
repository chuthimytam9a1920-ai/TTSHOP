<?php
include "check_admin.php";
include "../connect.php";

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {

    $stmtDeleteItems = mysqli_prepare($conn, "
        DELETE FROM chitiet_donhang 
        WHERE donhang_id = ?
    ");

    mysqli_stmt_bind_param($stmtDeleteItems, "i", $id);
    mysqli_stmt_execute($stmtDeleteItems);

    $stmtDeleteOrder = mysqli_prepare($conn, "
        DELETE FROM donhang 
        WHERE id = ?
    ");

    mysqli_stmt_bind_param($stmtDeleteOrder, "i", $id);
    mysqli_stmt_execute($stmtDeleteOrder);
}

header("Location: orders.php");
exit();
?>