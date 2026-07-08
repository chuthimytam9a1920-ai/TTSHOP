<?php
session_start();

$id = $_POST['id'];
$name = $_POST['name'];
$price = $_POST['price'];
$img = $_POST['img'];
$qty = $_POST['qty'];

if(!isset($_SESSION['cart'])){
    $_SESSION['cart'] = [];
}

/* Nếu sản phẩm đã tồn tại */
if(isset($_SESSION['cart'][$id])){

    $_SESSION['cart'][$id]['qty'] += $qty;

}else{

    $_SESSION['cart'][$id] = [
        'id' => $id,
        'name' => $name,
        'price' => $price,
        'img' => $img,
        'qty' => $qty
    ];
}

header("Location: giohang.php");
exit();