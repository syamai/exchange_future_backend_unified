<?php

namespace App\Exceptions;

use App\Exceptions\MarginException;

class DeepIntoMarketException extends MarginException
{
    public $order;
    public $price;

    public function __construct($order, $price)
    {
        $message = 'Cannot open order because current best price is ' . $price;
        $this->order = $order;
        $this->price = $price;
        parent::__construct($message);
    }
}
