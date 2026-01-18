<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;
use App\Consts;

class InsufficientBalanceException extends MarginException
{
    public $type;
    public $orderId;

    public function __construct($type, $orderId = null)
    {
        if ($type == Consts::AVAILABLE_BALANCE) {
            $message = 'Insufficient available balance';
        } else {
            $message = '';
        }
        $this->type = $type;
        $this->orderId = $orderId;
        parent::__construct($message);
    }
}
