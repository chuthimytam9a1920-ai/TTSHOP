<?php
namespace App\Traits;

trait LoggerTrait
{
    public function log($message)
    {
        echo '[LOG]: ' . $message . '<br>';
    }

    public function notify($message)
    {
        echo '[LOG NOTIFY]: ' . $message . '<br>';
    }
}
