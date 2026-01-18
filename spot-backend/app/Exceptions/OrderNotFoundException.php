<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;

class OrderNotFoundException extends MarginException
{
    public $type;
    public $orderId;

    public function __construct($type, $orderId)
    {
        $message = 'Order does not exist';
        $this->type = $type;
        $this->orderId = $orderId;
        parent::__construct($message);
    }
}
