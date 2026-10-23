<?php
namespace App\Traits;

trait NotifierTrait
{
    public function notify($message)
    {
        echo '[PUSH NOTIFY]: ' . $message . '<br>';
    }
}
