<?php
namespace App\Models;

class BaseModel
{
    public static $instanceCount = 0;

    public function __construct()
    {
        self::$instanceCount++;
    }

    public static function createWithSelf()
    {
        return new self;
    }

    public static function createWithStatic()
    {
        return new static;
    }

    final public function getSystemVersion()
    {
        return 'v1.0.0';
    }
}
