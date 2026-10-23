<?php
// Nạp tự động các lớp bắt đầu bằng App\ từ thư mục app/.
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = __DIR__ . '/app/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require $file; // require trong autoloader, không require thủ công từng class.
    }
});

use App\Exceptions\InvalidAmountException;
use App\Models\BaseModel;
use App\Models\Order;

echo '<h2>Kiểm thử hệ thống Order & Payment</h2>';

echo '<h3>1. Exception</h3>';
try {
    $invalidOrder = new Order(-50000);
} catch (InvalidAmountException $e) {
    echo 'Lỗi: ' . $e->getMessage() . '<br>';
}

echo '<h3>2. Tạo 2 đơn hàng hợp lệ</h3>';
$order = new Order(150000);
$order2 = new Order(250000);
echo $order . '<br>';
echo $order2 . '<br>';

echo '<h3>3. Magic Methods</h3>';
$order->customerName = 'Nguyễn Văn A';
echo 'Khách hàng: ' . $order->customerName . '<br>';
$order->sendEmailReceipt();
echo $order . '<br>';

echo '<h3>4. Trait</h3>';
$order->processOrder();

echo '<h3>5. self và static</h3>';
echo 'createWithSelf(): ' . get_class(Order::createWithSelf()) . '<br>';
echo 'createWithStatic(): ' . get_class(Order::createWithStatic()) . '<br>';

echo '<h3>6. Static count và final</h3>';
echo 'Tổng đối tượng được tạo: ' . BaseModel::$instanceCount . '<br>';
echo 'Phiên bản: ' . $order->getSystemVersion() . '<br>';
