<?php
namespace App\Models;

use App\Exceptions\InvalidAmountException;
use App\Traits\LoggerTrait;
use App\Traits\NotifierTrait;

class Order extends BaseModel
{
    use LoggerTrait, NotifierTrait {
        NotifierTrait::notify insteadof LoggerTrait;
        LoggerTrait::notify as logNotify;
    }

    private $data = [];
    private $amount;

    // Giá trị mặc định giúp BaseModel::createWithStatic() chạy được.
    // Nếu thầy yêu cầu __construct($amount) bắt buộc, cần sửa factory
    // để truyền số tiền vào (xem giải thích bên ngoài mã nguồn).
    public function __construct($amount = 1)
    {
        if (!is_numeric($amount) || $amount <= 0) {
            throw new InvalidAmountException('Số tiền đơn hàng phải lớn hơn 0!');
        }

        $this->amount = $amount;
        parent::__construct();
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    public function __call($name, $arguments)
    {
        echo "Phương thức {$name} không tồn tại trong class!<br>";
    }

    public function __toString()
    {
        return 'Đơn hàng trị giá: ' . $this->amount;
    }

    public function processOrder()
    {
        $this->log('Thanh toán thành công!');
        $this->notify('Đơn hàng đã thanh toán thành công!');
    }
}
