<?php

namespace App\Exceptions;

class OrderNotValidException extends MarginException
{
    public int $orderId;
    public int $tradeId;

    public function __construct($orderId, $tradeId)
    {
        $message = 'Cannot matching because order value greater than trade value';
        $this->orderId = $orderId;
        $this->tradeId = $tradeId;
        parent::__construct($message);
    }
}
